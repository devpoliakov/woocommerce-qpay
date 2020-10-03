<?php /*
 * Plugin Name: WooCommerce qPay Payment Gateway
 * Description: Allow payments via qpay.
 * Author: Yurii Poliakov
 * Version: 1.0.1
 */

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'qpay_add_gateway_class' );
function qpay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Qpay_Gateway'; // your class name is here
	return $gateways;
}
 

add_action( 'plugins_loaded', 'qpay_init_gateway_class' );
function qpay_init_gateway_class() {
 
	class WC_Qpay_Gateway extends WC_Payment_Gateway {
 
 		public function __construct() {
 
	$this->id = 'qpay'; // payment gateway plugin ID
	$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
	$this->has_fields = true; // in case you need a custom credit card form
	$this->method_title = 'Qpay Gateway';
	$this->method_description = 'Description of Qpay payment gateway'; // will be displayed on the options page
 
	// gateways can support subscriptions, refunds, saved payment methods,
	// but in this tutorial we begin with simple payments
	$this->supports = array(
		'products'
	);
 
	// Method with all the options fields
	$this->init_form_fields();
 
	// Load the settings.
	$this->init_settings();
	$this->title = $this->get_option( 'title' );
	$this->description = $this->get_option( 'description' );
	$this->enabled = $this->get_option( 'enabled' );
	$this->testmode = 'yes' === $this->get_option( 'testmode' );
	$this->secret_key = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
	$this->bank_id = $this->get_option( 'bank_id' );
	$this->merchant_id = $this->get_option( 'merchant_id' );
 
	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
	// We need custom JavaScript to obtain a token
	add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
 
	// You can also register a webhook here
	// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 }
 
	public function init_form_fields(){
	 
		$this->form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable Qpay Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'Credit Card',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay with your credit card via our super-cool payment gateway.',
			),
			'testmode' => array(
				'title'       => 'Test mode',
				'label'       => 'Enable Test Mode',
				'type'        => 'checkbox',
				'description' => 'Place the payment gateway in test mode using test API keys.',
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_bank_id' => array(
				'title'       => 'Test Publishable Key',
				'type'        => 'text'
			),
			'test_private_key' => array(
				'title'       => 'Test Private Key',
				'type'        => 'password',
			),
			'bank_id' => array(
				'title'       => 'Live Bank ID',
				'type'        => 'text'
			),			
			'merchant_id' => array(
				'title'       => 'Live Merchant ID',
				'type'        => 'text'
			),
			'secret_key' => array(
				'title'       => 'Live Private Key',
				'type'        => 'password'
			)
		);
	}
 
 
		public function validate_fields(){
		 
			if( empty( $_POST[ 'billing_first_name' ]) ) {
				wc_add_notice(  'First name is required!', 'error' );
				return false;
			}
			return true;
		 
		}
 
		public function webhook() {
 
			$order = wc_get_order( $_GET['id'] );
			$order->payment_complete();
			$order->reduce_order_stock();
		 
			//update_option('webhook_debug', $_GET);
		}

		public function payment_scripts() {
 
			// we need JavaScript to process a token only on cart/checkout pages, right?
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}
		 
			// if our payment gateway is disabled, we do not have to enqueue JS too
			if ( 'no' === $this->enabled ) {
				return;
			}
		 
			// no reason to enqueue JavaScript if API keys are not set
			if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
				return;
			}
		 
			// do not work with card detailes without SSL unless your website is in a test mode
			if ( ! $this->testmode && ! is_ssl() ) {
				return;
			}
		 
		 
			//wp_register_script( 'woocommerce_qpaytoken', plugins_url( 'qpaytoken.js', __FILE__ ), array( 'jquery' ) );
		 /*
			wp_localize_script( 'woocommerce_qpaytoken', 'qpay_params', array(
				'publishableKey' => $this->publishable_key
			) );
*/		 
			wp_enqueue_script( 'woocommerce_qpaytoken' );
		 
		}

		public function payment_fields() {
 
			// ok, let's display some description before the payment form
			if ( $this->description ) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ( $this->testmode ) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
					$this->description  = trim( $this->description );
				}
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}
		 
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			// Add this action hook if you want your custom payment gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );

				/* qpay hardcode integration */

				$orderid = 7778;
				$secret_key = $this->secret_key;
				$bank_id = $this->bank_id;
				$merchant_id = $this->merchant_id;
				$payment_url = 'https://www.qpay.gov.qa/QPayOnePC/PaymentPayServlet';
				$theme_id = '510000084';
				$time_deference = 0;
				function random_num($size) {
					$alpha_key = '';
					$keys = range('A', 'Z');
					for ($i = 0; $i < 2; $i++) {
						$alpha_key .= $keys[array_rand($keys)];
					}
					$length = $size - 2;
					$key = '';
					$keys = range(0, 9);
					for ($i = 0; $i < $length; $i++) {
						$key .= $keys[array_rand($keys)];
					}
					return $alpha_key . $key;
				}
				function generateRandomString($length = 18) {
				    $characters = '0123456789';
				    $charactersLength = strlen($characters);
				    $randomString = '';
				    for ($i = 0; $i < $length; $i++) {
				        $randomString .= $characters[rand(0, $charactersLength - 1)];
				    }
				    return $randomString;
				}
				$pun_1 = $orderid.generateRandomString();
				$pun_key = generateRandomString();
				$mercnt_sess_id = random_num(23);
				//$amnt = $this->cart->total() + $this->session->userdata('delivery_charge');
				$amnt = 1;
				$PAYONE_SECRET_KEY = $secret_key;
				$formatedRequestDate = date('dmYHis');
				$parameters = array();
				$parameters['Action'] = '0';
				$parameters['BankID'] = $bank_id;
				$parameters['MerchantID'] = $merchant_id;
				$parameters['CurrencyCode'] = '634';
				$parameters['Amount'] = $amnt * 100;
				$parameters['PUN'] = $pun_key;  // order id
				$parameters['PaymentDescription'] = urlencode("PaymentDescription");
				$parameters['MerchantModuleSessionID'] = $mercnt_sess_id;  //alphanumeric unique id generated
				$parameters['TransactionRequestDate'] = $formatedRequestDate;
				//$parameters['Quantity'] = count($this->cart->contents());
				$parameters['Quantity'] = count(array(1));
				$parameters['Lang'] = 'EN';
				$parameters['NationalID'] = "";
				$parameters['ExtraFields_f14'] = site_url('success');
				$parameters['ExtraFields_f3'] = $theme_id;
				ksort($parameters);
				$orderedString = $PAYONE_SECRET_KEY;
				foreach($parameters as $k=>$param){
				//echo $param.chr(10);
				 $orderedString .= $param;
				}
				/////echo "--- Ordered String ---".chr(10);
				//echo $orderedString.chr(10);
				$secureHash = hash('sha256', $orderedString, false);
				//echo "--- Hash Value ---".chr(10);
				//echo $secureHash;
				$attributesData = array();
				$attributesData['Action'] = "0";
				$attributesData['TransactionRequestDate'] = $formatedRequestDate;
				$attributesData['Amount'] = $amnt * 100;
				$attributesData['NationalID'] = "";
				$attributesData['PUN'] = $pun_key;
				$attributesData['MerchantModuleSessionID'] = $mercnt_sess_id;
				$attributesData['MerchantID'] = $merchant_id;
				$attributesData['BankID'] = $bank_id;
				$attributesData['Lang'] = "EN";
				$attributesData['CurrencyCode'] = "634";
				$attributesData['ExtraFields_f3'] = $theme_id;
				$attributesData['ExtraFields_f14'] = site_url('success');
				//$attributesData['Quantity'] = count($this->cart->contents());
				$attributesData['Quantity'] = count(array(1));
				$attributesData['PaymentDescription'] = urldecode("PaymentDescription");
				$attributesData['SecureHash'] = $secureHash;
				$attributesData['PG_REDIRECT_URL'] = $payment_url;
				//var_dump($attributesData);die;
				$_SESSION['PayOneParams'] = $attributesData;
				$parameters = $_SESSION['PayOneParams'];
				//var_dump($parameters);
				$redirectURL = $parameters["PG_REDIRECT_URL"];
				$amount = $parameters["Amount"];
				$currencyCode = $parameters["CurrencyCode"];
				$pun = $parameters["PUN"];
				$merchantModuleSessionID = $parameters["MerchantModuleSessionID"];
				$paymentDescription = $parameters["PaymentDescription"];
				$nationalID = $parameters["NationalID"];
				$merchantID = $parameters["MerchantID"];
				$bankID = $parameters["BankID"];
				$lang = $parameters["Lang"];
				$action = $parameters["Action"];
				$secureHash = $parameters["SecureHash"];
				$transactionRequestDate = $parameters["TransactionRequestDate"];
				$extraFields_f3 = $parameters["ExtraFields_f3"];
				$extraFields_f14 = $parameters["ExtraFields_f14"];
				$quantity = $parameters["Quantity"];


				 ?>
				 <input type="text" name="Amount" value="<?php echo $amount; ?>" />
				<input type="text" name="CurrencyCode" value="<?php echo $currencyCode ; ?>" />
				<input type="text" id="pun_id" name="PUN" value="<?php echo $pun; ?>" />
				<input type="text" name="MerchantModuleSessionID" value="<?php echo $merchantModuleSessionID ;?>" />
				<input type="text" name="PaymentDescription" value="<?php echo $paymentDescription; ?>" />
				<input type="text" name="NationalID" value="<?php echo $nationalID ;?>" />
				<input type="text" name="MerchantID" value="<?php echo $merchantID; ?>" />
				<input type="text" name="BankID" value="<?php echo $bankID ; ?>" />
				<input type="text" name="Lang" value="<?php echo $lang; ?>" />
				<input type="text" name="Action" value="<?php echo $action; ?>" />
				<input type="text" id="hash_value" name="SecureHash" value="<?php echo $secureHash; ?>" />
				<input type="text" id="transaction_date" name="TransactionRequestDate" value="<?php echo $transactionRequestDate; ?>" />
				<input type="text" name="ExtraFields_f3" value="<?php echo $extraFields_f3; ?>" />
				<input type="text" name="ExtraFields_f14" value="<?php echo $extraFields_f14; ?>" />
				<input type="text" name="Quantity" value="<?php echo $quantity; ?>" />
				 <?php
					do_action( 'woocommerce_credit_card_form_end', $this->id );
				 
					echo '<div class="clear"></div></fieldset>';
		 
		}	
 	}
}
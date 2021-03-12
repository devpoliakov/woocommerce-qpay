<?php /*
 * Plugin Name: WooCommerce qPay Payment Gateway
 * Description: Allow payments via qpay.
 * Author: Yurii Poliakov
 * Version: 1.0.1
 */

if ( !class_exists( 'WooCommerce' ) ) 
	return;

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'qpay_add_gateway_class' );
function qpay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Qpay_Gateway'; // your class name is here
	return $gateways;
}
 
 require_once ('inc/hooks.php');

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
	$this->theme_id = $this->get_option( 'theme_id' );
 
	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
	// We need custom JavaScript to obtain a token
	add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
 
	// You can also register a webhook here
	add_action( 'woocommerce_api_qpay-success', array( $this, 'webhook' ) );
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
			'theme_id' => array(
				'title'       => 'Theme ID',
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

			$response_parameters = $_POST;

			ksort($response_parameters);
	        $orderedString = $this->secret_key;


	        foreach ($response_parameters as $k => $param) {
                if (($k != "Response_SecureHash") && ($param != "null") && ($k != "Response_StatusMessage")) {
                    $orderedString .= $param;
                }
                if ($k == "Response_StatusMessage") {
                    $msg = str_replace(' ', '+', $param);
                    $orderedString .= $msg;
                }
            }
	        $secureHash = hash('sha256', $orderedString, false);

	        $order = new WC_Order( $_POST['Response_PUN'] );

	        $status_code =  $_POST['Response_Status'];
	        $receivedSecureHash =  $_POST['Response_SecureHash'];


	        if (($status_code == "0000") && ($receivedSecureHash == $secureHash)) {		
				//$order->payment_complete();
				//$order->reduce_order_stock();

				$order->update_status( 'processing' );
				
				// payment email for admin
				$wc_email = WC()->mailer()->get_emails()['WC_Email_New_Order'];
			    $wc_email->settings['subject'] = __('{site_title} - Processing order ('.$_POST['Response_PUN'].') - {order_date}');
			    $wc_email->settings['heading'] = __('Processing Order ('.$_POST['Response_PUN'].')'); 
			    $wc_email->trigger( $_POST['Response_PUN'] );


			}else if (($status_code == "2996") && ($receivedSecureHash == $secureHash)) {
				$order->update_status( 'cancelled', __( 'Cancelled', '' ), true );
				$order->add_order_note('cancell_note');
				$email_order = WC()->mailer()->get_emails()['WC_Email_Cancelled_Order'];
				//$email_order->trigger( $_POST['Response_PUN'] );

				//$emailer->customer_invoice($order);				
			}else{
				$order->update_status( 'failed', __( 'Failed', '' ) );
				$order->add_order_note('fail_note');
				$email_order = WC()->mailer()->get_emails()['WC_Email_Failed_Order'];
				//$email_order->trigger( $_POST['Response_PUN'] );

				$emailer = new WC_Emails();
				$emailer->customer_invoice($order);
			}
			

				wp_redirect( $order->get_checkout_order_received_url() ); 
				exit;

		 
			
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

			do_action( 'woocommerce_credit_card_form_end', $this->id );
				 
					echo '<div class="clear"></div></fieldset>';
		 
		}	


		public function process_payment( $order_id ) {
    
		    global $woocommerce;
 
			$order = new WC_Order( $order_id );

			// Mark as on-hold (we're awaiting the payment)
		    $order->update_status( 'on-hold', __( 'Awaiting offline payment', '' ) );
		            
		    // Reduce stock levels
		    $order->reduce_order_stock();

		    // Remove cart
		    WC()->cart->empty_cart();

        /* qpay hardcode integration */

        $secret_key = $this->secret_key;
        $bank_id = $this->bank_id;
        $merchant_id = $this->merchant_id;
        $payment_url = 'https://pgtest3.qcb.gov.qa/QPayOnePC/PaymentPayServlet';
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

        $mercnt_sess_id = random_num(23);

        $amnt = 1;
        $PAYONE_SECRET_KEY = $secret_key;
        $formatedRequestDate = date('dmYHis');
        $parameters = array();
        $parameters['Action'] = '0';
        $parameters['BankID'] = $bank_id;
        $parameters['MerchantID'] = $merchant_id;
        $parameters['CurrencyCode'] = '634';
        $parameters['Amount'] = $order->get_total() * 100;
        $parameters['PUN'] = $order_id;  // order id
        $parameters['PaymentDescription'] = urlencode("PaymentDescription");
        $parameters['MerchantModuleSessionID'] = $mercnt_sess_id;  //alphanumeric unique id generated
        $parameters['TransactionRequestDate'] = $formatedRequestDate;
        //$parameters['Quantity'] = count($this->cart->contents());
        $parameters['Quantity'] = count(array(1));
        $parameters['Lang'] = 'en';
        $parameters['NationalID'] = "";
        $parameters['ExtraFields_f14'] = 'https://plaza-hollandi.com/wc-api/qpay-success';
        //$parameters['ExtraFields_f3'] = $theme_id;
        ksort($parameters);
        $orderedString = $PAYONE_SECRET_KEY;

        foreach($parameters as $k=>$param){
         $orderedString .= $param;
        }

        $secureHash = hash('sha256', $orderedString, false);



// Payload would look something like this.
			$payload = array(
);

        $payload['Action'] = "0";
        $payload['TransactionRequestDate'] = $formatedRequestDate;
        $payload['Amount'] = $order->get_total() * 100;
        $payload['NationalID'] = "";
        $payload['PUN'] = $order_id;
        $payload['MerchantModuleSessionID'] = $mercnt_sess_id;
        $payload['MerchantID'] = $merchant_id;
        $payload['BankID'] = $bank_id;
        $payload['Lang'] = "en";
        $payload['CurrencyCode'] = "634";
        //$payload['ExtraFields_f3'] = $theme_id;
        $payload['ExtraFields_f14'] = 'https://plaza-hollandi.com/wc-api/qpay-success';
        $payload['Quantity'] = count(array(1));
        $payload['PaymentDescription'] = urldecode("PaymentDescription");
        $payload['SecureHash'] = $secureHash;
        $payload['PG_REDIRECT_URL'] = $payment_url;
        //$payload['qpay_params_log'] = $qpay_params_log;



		    $environment_url = 'https://pgtest3.qcb.gov.qa/QPayOnePC/PaymentPayServlet';		    
			$querystring = http_build_query( $payload );

			return array(
			                'result'   => 'success',
			                'redirect' => $environment_url . '?' . $querystring,
			            );
			    

		}
 	}
}
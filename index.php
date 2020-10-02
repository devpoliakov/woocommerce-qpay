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
	$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
	$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
 
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
		'test_publishable_key' => array(
			'title'       => 'Test Publishable Key',
			'type'        => 'text'
		),
		'test_private_key' => array(
			'title'       => 'Test Private Key',
			'type'        => 'password',
		),
		'publishable_key' => array(
			'title'       => 'Live Publishable Key',
			'type'        => 'text'
		),
		'private_key' => array(
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
 	}
}
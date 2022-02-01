<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://unimedia.mn/
 * @since             1.0.0
 * @package           Qplugin
 *
 * @wordpress-plugin
 * Plugin Name:       QPlugin
 * Plugin URI:        https://github.com/biligunb/qplugin
 * Description:       WooCommerce - Payment Gateway using QPay
 * Version:           1.0.0
 * Author:            Unimedia Solutions LLC engineers
 * Author URI:        https://unimedia.mn/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       qplugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'QPLUGIN_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-qplugin-activator.php
 */
function activate_qplugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-qplugin-activator.php';
	Qplugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-qplugin-deactivator.php
 */
function deactivate_qplugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-qplugin-deactivator.php';
	Qplugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_qplugin' );
register_deactivation_hook( __FILE__, 'deactivate_qplugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-qplugin.php';


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'qplugin_add_gateway_class' );
function qplugin_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_QPlugin_Gateway';
	return $gateways;
}

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'qplugin_init_gateway_class' );
function qplugin_init_gateway_class() {
	class WC_QPlugin_Gateway extends WC_Payment_Gateway {
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
			$this->id = 'qplugin'; // payment gateway plugin ID
			$this->icon = plugin_dir_url( __FILE__ ) . 'public/images/icons/logo_100px.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'QPlugin Gateway';
			$this->method_description = 'Payment using Qpay'; // will be displayed on the options page
		
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

		/**
 		 * Plugin options
 		 */
 		public function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable QPlugin Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Qpay',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay using QR code (qpay.mn)',
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

		/**
		 * You will need it if you want your custom credit card form
		 */
		public function payment_fields() { }

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() { }

		/*
 		 * Fields validation
		 */
		public function validate_fields() { }

		/*
		 * We're processing the payments here
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;
			// we need it to get order detail
			$order = wc_get_order($order_id);

			// Mark as pending (we're awaiting the payment)
			$order->update_status( $this->default_status );

			define( 'WP_DEBUG', true );
        	$invoice_date = get_the_date( 'Y-m-d H:i:s' ) . '.00'; // "2019-11-29 09:11:03.840"

			$auth_basic_token = '';
			$array_with_parameters->json_data->invoice_date = $invoice_date; 
			$array_with_parameters->json_data->invoice_total_amount = $order->get_total();

			$args = array(
				'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . $this->auth_basic_token ),
				'body'        => json_encode($array_with_parameters),
				'method'      => 'POST',
				'data_format' => 'body',
			);
			$response = wp_remote_post($this->get_payment_url(), $args);

			if(!is_wp_error($response)) {
				$body = json_decode($response['body'], true);
				debug_to_console($body);
				$content = 'content';

				$redirect_url = add_query_arg( array('qpay_code' => $content), $order->get_checkout_payment_url( true ) );

				return array(
					'result' => 'success',
					'redirect' => apply_filters( 'qpay_process_payment_redirect', $redirect_url, $order )
				);
			} else {
				wc_add_notice('Connection error.', 'error');
				return;
			}
		}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() { }
		
		/**
		 * Get the payment URL.
		 *
		 * @return string.
		 */
		protected function get_payment_url() {
			return 'https://merchant-sandbox.qpay.mn/v2/auth/token';
		}
 	}
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_qplugin() {

	$plugin = new Qplugin();
	$plugin->run();

}
run_qplugin();

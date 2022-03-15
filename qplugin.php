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

  echo "\n$output\n";
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
      $this->test_username = $this->testmode ? $this->get_option( 'test_username' ) : $this->get_option( 'username' );
      $this->test_password = $this->testmode ? $this->get_option( 'test_password' ) : $this->get_option( 'password' );
    
      // This action hook saves the settings
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    
      // We need custom JavaScript to obtain a token
      add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

      // thank you page output
      add_action( 'woocommerce_receipt_'.$this->id, array( $this, 'generate_qr_code' ), 4, 1 );
      
      // You can also register a webhook here
      add_action( 'woocommerce_api_qplugin', array( $this, 'webhook' ) );
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
        'test_username' => array(
          'title'       => 'Test username',
          'type'        => 'text',
          'default'     => 'TEST_MERCHANT'
        ),
        'test_password' => array(
          'title'       => 'Test password',
          'type'        => 'text',
          'default'     => '123456'
        ),
        'username' => array(
          'title'       => 'Production username',
          'type'        => 'text'
        ),
        'password' => array(
          'title'       => 'Production password',
          'type'        => 'password'
        )
      );
    }

    /**
     * You will need it if you want your custom credit card form
     */
    public function payment_fields() { }

    /**
     * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
     */
    public function payment_scripts() {
      // we need JavaScript to process a token only on cart/checkout pages
      if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
        return;
      }
  
      // if our payment gateway is disabled, we do not have to enqueue JS too
      if ( 'no' === $this->enabled ) {
        return;
      }
      
      // no reason to continue if followings are not set
      if ( empty( $this->test_username ) || empty( $this->test_password )) {
        return;
      }
    }

    /**
     * Fields validation
     */
    public function validate_fields() { }

    /**
     * We're processing the payments here
     */
    public function process_payment( $order_id ) {
      global $woocommerce;
      // we need it to get order detail
      $order = wc_get_order($order_id);

      // Mark as pending (we're awaiting the payment)
      $order->update_status( $this->default_status );

      define( 'WP_DEBUG', true );
      define( 'WP_DEBUG_LOG', true );
      define( 'WP_DEBUG_DISPLAY', false );

      $redirect_url = add_query_arg( array( 'orderId' => $order_id ), $order->get_checkout_payment_url( true ) );

      return array(
        'result' => 'success',
        'redirect' => apply_filters( 'qpay_process_payment_redirect', $redirect_url, $order )
      );
    }

    /**
     * Show Qpay details as html output
     *
     * @param WC_Order $order_id Order id.
     * @return string
     */
    public function generate_qr_code( $order_id ) {
      global $woocommerce;

      $order = wc_get_order( $order_id );

      // Reduce stock levels
      $order->reduce_order_stock();
              
      // Remove cart
      $woocommerce->cart->empty_cart();

      $invoice_due_date = get_the_date( 'Y-m-d H:i:s' ) . '.00'; // "2019-11-29 09:11:03.840"

      $array_with_parameters->sender_invoice_no = '1234567';
      $array_with_parameters->invoice_code = 'TEST_INVOICE';
      $array_with_parameters->invoice_receiver_code = 'terminal';
      $array_with_parameters->invoice_description = 'Invoice description';
      // $array_with_parameters->invoice_due_date = $invoice_due_date;
      // $array_with_parameters->lines = array('line_description'=>'Invoice description','line_quantity'=>'1.00', 'line_unit_price'=>$order->get_total());
      $array_with_parameters->lines = array (0 => array ('line_description' => 'Invoice description', 'line_quantity' => '1.00', 'line_unit_price' => '11.00' ));
      $array_with_parameters->amount = 10;

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode( 'TEST_MERCHANT' . ':' . '123456' ) ),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response = wp_remote_post($this->get_auth_token_url(), $args);
      $body = json_decode($response['body'], true);
      $access_token = $body['access_token'];

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token ),
        'body'        => json_encode($array_with_parameters),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response = wp_remote_post($this->get_create_invoice_url(), $args);

      if(!is_wp_error($response)) {
        $body = json_decode($response['body'], true);

        // debug_to_console($body);
        $invoiceId = $body['invoice_id'];
        $qrCode = $body['qr_image'];

        ?>
          <div class="checkout-qplugin-payment">
            <img src="data:image/png;base64,<?php echo $qrCode ?>" alt="" />
          </div>
        <?php
        return;
      } else {
        wc_add_notice('Connection error.', 'error');
        return;
      }
    }

    /**
     * In case you need a webhook, like PayPal IPN etc
     */
    public function webhook() {
      $order = wc_get_order( $_GET['id'] );

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode( 'asdf' . ':' . 'asdf' ) ),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      // $response = wp_remote_post($this->get_auth_token_url(), $args);
      $response = wp_remote_post('https://merchant.qpay.mn/v2/auth/token', $args);
      $body = json_decode($response['body'], true);
      $access_token = $body['access_token'];
      print_r('Access token');
      debug_to_console($access_token);

      $args2 = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token ),
        'method'      => 'GET',
        'data_format' => 'body',
      );
      $response2 = wp_remote_post($this->get_payment_url($_GET['qpay_payment_id']), $args2);
      print_r('Response');
      print_r($response2);
      error_log(print_r($response2, true));
      $body = json_decode($response2['body'], true);
      error_log($body['object_id']);

      $array_with_parameters->object_type = 'INVOICE';
      $array_with_parameters->object_id = $body['object_id'];

      $args2 = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token ),
        'body'        => json_encode($array_with_parameters),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response2 = wp_remote_post($this->get_check_payment_url(), $args2);
      print_r('Response');
      print_r($response2);
      // error_log(print_r($response2, true));

      $body = json_decode($response2['body'], true);
      // error_log(print_r($body, true));
      $payment_status = $body['rows'][0]['payment_status'];
      $payment_id = $body['rows'][0]['payment_id'];
      // error_log($payment_status);
      // error_log($payment_id);

      // If payment status = PAID & paymentId = qpay_payment_id
      if ($payment_status == 'PAID' && $payment_id == $_GET['qpay_payment_id']) {
        $order->payment_complete();
        print_r('order');
        print_r($order);
      }

      update_option('webhook_debug', $_GET);
     }

    /**
     * Get the authorization token URL.
     *
     * @return string.
     */
    protected function get_auth_token_url() {
      return 'https://merchant-sandbox.qpay.mn/v2/auth/token';
    }

    /**
     * Get the create invoice URL.
     *
     * @return string.
     */
    protected function get_create_invoice_url() {
		  return 'https://merchant-sandbox.qpay.mn/v2/invoice';
	  }

    /**
     * Get the payment URL.
     *
     * @return string.
     */
    protected function get_payment_url($qpay_payment_id) {
		  // return 'https://merchant-sandbox.qpay.mn/v2/payment/check';
		  return "https://merchant.qpay.mn/v2/payment/$qpay_payment_id";
	  }

    /**
     * Get the check payment URL.
     *
     * @return string.
     */
    protected function get_check_payment_url() {
		  // return 'https://merchant-sandbox.qpay.mn/v2/payment/check';
		  return 'https://merchant.qpay.mn/v2/payment/check';
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

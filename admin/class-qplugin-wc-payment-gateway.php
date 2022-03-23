<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://unimedia.mn/
 * @since      1.0.0
 *
 * @package    Qplugin
 * @subpackage Qplugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Qplugin
 * @subpackage Qplugin/admin
 * @author     Unimedia Solutions LLC engineers <hr_mongolia@unimedia.co.jp>
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! class_exists( 'WC_QPlugin_Gateway' ) ) {

  class WC_QPlugin_Gateway extends WC_Payment_Gateway {
    /**
     * Class constructor
     */
    public function __construct() {
      $this->id = 'qplugin'; // payment gateway plugin ID
      $this->icon = plugin_dir_url( __FILE__ ) . '../public/images/icons/logo_100px.png'; // URL of the icon that will be displayed on checkout page near your gateway name
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
    public function init_form_fields() {
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

      // bootstrap
      wp_register_style( 'bootstrap', plugins_url( 'css/bootstrap.min.css' , __FILE__ ), array(), '5.1.3' );
      wp_register_script( 'bootstrap', plugins_url( 'js/bootstrap.bundle.min.js' , __FILE__ ), array( 'jquery' ), '5.1.3', true );

      // jquery confirm
      wp_register_style( 'jquery-confirm', plugins_url( 'css/jquery-confirm.min.css' , __FILE__ ), array(), '3.3.2' );
      wp_register_script( 'jquery-confirm', plugins_url( 'js/jquery-confirm.min.js' , __FILE__ ), array( 'jquery', 'bootstrap' ), '3.3.2', true );

      wp_register_style( 'qpay', plugins_url( 'css/qpay.css' , __FILE__ ), array(), '1.0.0' );
      wp_register_script( 'qpay', plugins_url( 'js/qpay.js' , __FILE__ ), array( 'jquery', 'bootstrap', 'jquery-confirm'), '1.0.0', true );
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

        wp_enqueue_style( 'bootstrap' );
        wp_enqueue_script( 'bootstrap' );
        wp_enqueue_style( 'jquery-confirm' );
        wp_enqueue_script( 'jquery-confirm' );
        wp_enqueue_style( 'qpay' );
        wp_enqueue_script( 'qpay' );

        wp_localize_script( 'qpay', 'qpay_params',
          array(
            'url' => admin_url()."admin-ajax.php?action=fetch_order_status&order_id=".$order_id,
            'orderId' => $order_id,
            'qrcode' => "data:image/png;base64,".$qrCode,
            'expire' => 120,
            'icon' => plugin_dir_url( __FILE__ ) . '../public/images/icons/qpay logo.svg',
            'success' => plugin_dir_url( __FILE__ ) . '../public/images/gifs/payment-success.gif',
            'successText' => 'Төлбөр амжилттай төлөгдлөө',
            'failure' => plugin_dir_url( __FILE__ ) . '../public/images/gifs/payment-failure.gif',
            'failureText' => 'Төлбөр төлөх хугацаа дууслаа',
          )
        );
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
      print_r($access_token);

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

return new WC_QPlugin_Gateway();

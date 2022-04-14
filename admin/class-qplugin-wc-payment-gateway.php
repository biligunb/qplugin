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

// Check qplugin_write_log
if (!function_exists('qplugin_write_log')) {
  /**
   * Write log
   */
  function qplugin_write_log($topic, $log) {
    error_log(print_r("$topic: " . json_encode($log, JSON_PRETTY_PRINT), true));
  }
}

if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!class_exists('WC_QPlugin_Gateway')) {

  class WC_QPlugin_Gateway extends WC_Payment_Gateway {
    /**
     * Class constructor
     */
    public function __construct() {
      $this->id = 'qplugin'; // payment gateway plugin ID
      $this->icon = plugin_dir_url(__FILE__) . '../public/images/icons/logo_100px.png'; // URL of the icon that will be displayed on checkout page near your gateway name
      $this->has_fields = true; // in case you need a custom credit card form
      $this->method_title = 'Gateway for Qpay on WooCommerce';
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
      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->enabled = $this->get_option('enabled');
      $this->username = $this->get_option('username');
      $this->password = $this->get_option('password');
      $this->invoice_code = $this->get_option('invoice_code');

      // This action hook saves the settings
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

      // We need custom JavaScript to obtain a token
      add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

      // thank you page output
      add_action('woocommerce_receipt_' . $this->id, array($this, 'qplugin_generate_qr_code'), 4, 1);

      // You can also register a webhook here
      add_action('woocommerce_api_qplugin', array($this, 'webhook'));
    }

    /**
     * Plugin options
     */
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title'       => 'Enable/Disable',
          'label'       => 'Enable Gateway for Qpay on WooCommerce',
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
        'invoice_code' => array(
          'title'       => 'Invoice code (from QPay)',
          'type'        => 'text',
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
    public function payment_fields() {
    }

    /**
     * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
     */
    public function payment_scripts() {
      // we need JavaScript to process a token only on cart/checkout pages
      if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
        return;
      }

      // if our payment gateway is disabled, we do not have to enqueue JS too
      if ('no' === $this->enabled) {
        return;
      }

      // no reason to continue if followings are not set
      if (empty($this->username) || empty($this->password)) {
        return;
      }

      // bootstrap
      wp_register_style('bootstrap', plugins_url('css/bootstrap.min.css', __FILE__), array(), '5.1.3');
      wp_register_script('bootstrap', plugins_url('js/bootstrap.bundle.min.js', __FILE__), array('jquery'), '5.1.3', true);

      // jquery confirm
      wp_register_style('jquery-confirm', plugins_url('css/jquery-confirm.min.css', __FILE__), array(), '3.3.2');
      wp_register_script('jquery-confirm', plugins_url('js/jquery-confirm.min.js', __FILE__), array('jquery', 'bootstrap'), '3.3.2', true);

      wp_register_style('qpay', plugins_url('css/qpay.css', __FILE__), array(), '1.0.0');
      wp_register_script('qpay', plugins_url('js/qpay.js', __FILE__), array('jquery', 'bootstrap', 'jquery-confirm'), '1.0.0', true);
    }

    /**
     * Fields validation
     */
    public function validate_fields() {
    }

    /**
     * We're processing the payments here
     */
    public function process_payment($order_id) {
      global $woocommerce;
      // we need it to get order detail
      $order = wc_get_order($order_id);

      // Mark as pending (we're awaiting the payment)
      $order->update_status($this->default_status);

      qplugin_write_log("ProcessPayment:Order", $order);

      if (!defined('WP_DEBUG')) define('WP_DEBUG', true);
      if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', true);
      if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);

      $redirect_url = add_query_arg(array('orderId' => $order_id), $order->get_checkout_payment_url(true));

      return array(
        'result' => 'success',
        'redirect' => apply_filters('qpay_process_payment_redirect', $redirect_url, $order)
      );
    }

    /**
     * Show Qpay details as html output
     *
     * @param WC_Order $order_id Order id.
     * @return string
     */
    public function qplugin_generate_qr_code($order_id) {
      global $woocommerce;

      $order = wc_get_order($order_id);

      // Reduce stock levels
      wc_reduce_stock_levels($order_id);

      // Remove cart
      $woocommerce->cart->empty_cart();

      $timestamp_now = get_the_date('Y-m-d H:i:s') . '.00'; // "2019-11-29 09:11:03.840"
      $invoice_due_date = date('Y-m-d H:i:s', strtotime('+2 minutes'));
      $customer_email = $order->get_billing_email();

      $array_with_parameters = [];
      $array_with_parameters['invoice_code'] = "$this->invoice_code"; // тохиргооноос авах
      $array_with_parameters['invoice_due_date'] = "$invoice_due_date";
      $array_with_parameters['invoice_description'] = "$order_id";
      $array_with_parameters['invoice_receiver_code'] = "$customer_email"; // mail, phone -> checkout дээр авах
      $array_with_parameters['sender_invoice_no'] = "$timestamp_now"; // timestamp
      $array_with_parameters['amount'] = $order->get_total();
      $array_with_parameters['callback_url'] = site_url("wc-api/qplugin?id=$order_id");

      qplugin_write_log("GenerateQRCode:Invoice params: ", $array_with_parameters);

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response = wp_remote_post($this->qplugin_get_auth_token_url(), $args);

      qplugin_write_log("GenerateQRCode:QPay auth response", $response);

      $body = json_decode($response['body'], true);
      $access_token = $body['access_token'];

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
        'body'        => json_encode($array_with_parameters),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response = wp_remote_post($this->qplugin_get_create_invoice_url(), $args);

      qplugin_write_log("GenerateQRCode:QPay create invoice response", $response);

      if (!is_wp_error($response)) {
        $body = json_decode($response['body'], true);
        qplugin_write_log("GenerateQRCode:QPay create invoice body", $body);

        $invoiceId = $body['invoice_id'];
        $qrcode = $body['qr_image'];
        $deeplink = $body['qPay_shortUrl'];

        wp_enqueue_style('bootstrap');
        wp_enqueue_script('bootstrap');
        wp_enqueue_style('jquery-confirm');
        wp_enqueue_script('jquery-confirm');
        wp_enqueue_style('qpay');
        wp_enqueue_script('qpay');

        wp_localize_script(
          'qpay',
          'qpay_params',
          array(
            'icon' => plugin_dir_url(__FILE__) . '../public/images/icons/qpay logo.svg',
            'url' => admin_url() . "admin-ajax.php?action=qplugin_fetch_order_status&order_id=" . $order_id,
            'deeplink' => $deeplink,
            'deeplinkText' => 'Банкны апп ашиглах',
            'orderId' => $order_id,
            'qrcode' => "data:image/png;base64," . $qrcode,
            'expire' => 120,
            'processingText' => 'Та төлбөр төлөгдөх хүртэл түр хүлээнэ үү!',
            'success' => plugin_dir_url(__FILE__) . '../public/images/gifs/payment-success.gif',
            'successText' => 'Төлбөр амжилттай төлөгдлөө',
            'failure' => plugin_dir_url(__FILE__) . '../public/images/gifs/payment-failure.gif',
            'expiredText' => 'Төлбөр төлөх хугацаа дууслаа',
            'cancelledText' => 'Захиалга цуцлагдсан байна',
            'failedText' => 'Захиалга амжилтгүй боллоо',
            'serverErrorText' => 'Серверт алдаа гарлаа. Та админтай холбогдож захиалгаа шалгуулна уу',
            'redirectUrl' => apply_filters('qplugin_payment_redirect_url', $this->get_return_url($order), $order),
            'orderUrl' => $order->get_view_order_url(),
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
      $order = wc_get_order($_GET['id']);

      $args = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response = wp_remote_post($this->qplugin_get_auth_token_url(), $args);
      $body = json_decode($response['body'], true);
      $access_token = $body['access_token'];
      qplugin_write_log("Webhook:QPay auth response", $response);


      $args2 = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
        'method'      => 'GET',
        'data_format' => 'body',
      );
      $response2 = wp_remote_post($this->qplugin_get_payment_url($_GET['qpay_payment_id']), $args2);
      qplugin_write_log("Webhook:QPay payment response", $response2);
      $body = json_decode($response2['body'], true);
      qplugin_write_log("Webhook:QPay object_id", $body['object_id']);

      $array_with_parameters->object_type = 'INVOICE';
      $array_with_parameters->object_id = $body['object_id'];

      $args2 = array(
        'headers'     => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
        'body'        => json_encode($array_with_parameters),
        'method'      => 'POST',
        'data_format' => 'body',
      );
      $response2 = wp_remote_post($this->qplugin_get_check_payment_url(), $args2);
      qplugin_write_log("Webhook:QPay check payment response", $response2);

      $body = json_decode($response2['body'], true);
      $payment_status = $body['rows'][0]['payment_status'];
      $payment_id = $body['rows'][0]['payment_id'];

      // If payment status = PAID & paymentId = qpay_payment_id
      if ($payment_status == 'PAID' && $payment_id == $_GET['qpay_payment_id']) {
        $order->payment_complete();
        qplugin_write_log('Webhook:Order', $order);
      }

      update_option('webhook_debug', $_GET);
    }

    /**
     * Get the authorization token URL.
     *
     * @return string.
     */
    protected function qplugin_get_auth_token_url() {
      return 'https://merchant.qpay.mn/v2/auth/token';
    }

    /**
     * Get the create invoice URL.
     *
     * @return string.
     */
    protected function qplugin_get_create_invoice_url() {
      return 'https://merchant.qpay.mn/v2/invoice';
    }

    /**
     * Get the payment URL.
     *
     * @return string.
     */
    protected function qplugin_get_payment_url($qpay_payment_id) {
      return "https://merchant.qpay.mn/v2/payment/$qpay_payment_id";
    }

    /**
     * Get the check payment URL.
     *
     * @return string.
     */
    protected function qplugin_get_check_payment_url() {
      return 'https://merchant.qpay.mn/v2/payment/check';
    }
  }
}

return new WC_QPlugin_Gateway();

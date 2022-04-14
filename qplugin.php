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
 * @package           Gateway for Qpay on WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Gateway for Qpay on WooCommerce
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
if (!defined('WPINC')) {
  die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('QPLUGIN_VERSION', '1.0.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-qplugin-activator.php
 */
function activate_qplugin() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-qplugin-activator.php';
  Qplugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-qplugin-deactivator.php
 */
function deactivate_qplugin() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-qplugin-deactivator.php';
  Qplugin_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_qplugin');
register_deactivation_hook(__FILE__, 'deactivate_qplugin');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-qplugin.php';


// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;


// Check qplugin_write_log
if (!function_exists('qplugin_write_log')) {
  /**
   * Write log
   */
  function qplugin_write_log($topic, $log) {
    error_log(print_r("$topic: " . json_encode($log, JSON_PRETTY_PRINT), true));
  }
}

function qplugin_fetch_order_status() {
  $order = wc_get_order($_REQUEST['order_id']);
  $order_data = $order->get_data();
  qplugin_write_log("FetchOrderStatus:Order", [
    'orderId' => $order_data['id'],
    'status' => $order_data['status'],
  ]);
  echo "" . esc_attr($order_data['status']) . "";
  die();
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

  // custom ajax api here
  add_action('wp_ajax_nopriv_qplugin_fetch_order_status', 'qplugin_fetch_order_status');
  add_action('wp_ajax_qplugin_fetch_order_status', 'qplugin_fetch_order_status');
}

run_qplugin();

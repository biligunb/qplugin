<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://unimedia.mn/
 * @since      1.0.0
 *
 * @package    Qplugin
 * @subpackage Qplugin/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Qplugin
 * @subpackage Qplugin/includes
 * @author     Unimedia Solutions LLC engineers <hr_mongolia@unimedia.co.jp>
 */
class Qplugin_i18n {


  /**
   * Load the plugin text domain for translation.
   *
   * @since    1.0.0
   */
  public function load_plugin_textdomain() {

    load_plugin_textdomain(
      'qplugin',
      false,
      dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
    );
  }
}

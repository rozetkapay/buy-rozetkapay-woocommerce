<?php
/**
 * Plugin Name: Buy with RozetkaPay Gateway for WooCommerce
 * Plugin URI:
 * Description: WooCommerce Payment Gateway for Buy with RozetkaPay
 * Version: 1.0.7
 * Author: RozetkaPay
 * License: GPL2
 * Text Domain: buy-rozetkapay-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.3
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 *
 * @package RozetkaPay Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin path constants.
define( 'ROZETKAPAY_GATEWAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROZETKAPAY_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once 'includes/class-rozetkapay-init.php';

RozetkaPay_Init::init();

register_activation_hook( __FILE__, array( 'RozetkaPay_Init', 'activate_plugin' ) );

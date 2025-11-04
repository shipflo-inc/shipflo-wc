<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://sgssandhu.com
 * @since             1.0.0
 * @package           ShipFlo_Wc
 *
 * @wordpress-plugin
 * Plugin Name:       ShipFlo WooCommerce
 * Plugin URI:        https://oxosolutions.com/products/wordpress-plugins/shipflo-wc/
 * Description:       The ShipFlo plugin syncs your WooCommerce orders with the ShipFlo delivery platform, automating dispatch and providing real-time tracking for a hassle-free logistics experience.
 * Version:           1.1.0.0
 * Author:            OXO Solutions®
 * Author URI:        https://oxosolutions.com/
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       shipflo-wc
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/shipflo-inc/shipflo-wc
 * GitHub Branch: main
 */


require_once plugin_dir_path(__FILE__) . 'includes/shipflo-wc-constants.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-shipflo-wc-activator.php
 */
function activate_shipflo_wc() 
{
	require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/class-shipflo-wc-activator.php';
	ShipFlo_Wc_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-shipflo-wc-deactivator.php
 */
function deactivate_shipflo_wc() 
{
	require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/class-shipflo-wc-deactivator.php';
	ShipFlo_Wc_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_shipflo_wc' );
register_deactivation_hook( __FILE__, 'deactivate_shipflo_wc' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require SHIPFLO_WC_PLUGIN_DIR . 'includes/class-shipflo-wc.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_shipflo_wc() 
{
	$plugin = new ShipFlo_Wc();
	$plugin->run();
}

run_shipflo_wc();
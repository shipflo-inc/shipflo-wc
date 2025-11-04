<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

require_once SHIPFLO_WC_PLUGIN_DIR . 'includes/functions/shipflo-wc-common.php';

/**
 * Fired during plugin activation
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc_Activator
{
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{
		shipflo_logger('notice', '[ShipFlo] Plugin activated successfully.');
	}
}

<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin deactivation
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 *
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    ShipFlo_Wc
 * @subpackage ShipFlo_Wc/includes
 * @author     SGS Sandhu <sgs.sandhu@gmail.com>
 */
class ShipFlo_Wc_Deactivator 
{
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() 
	{
		// Clear the scheduled log push event
        $timestamp = wp_next_scheduled('shipflo_push_logs_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'shipflo_push_logs_event');
        }

        // Also clear all occurrences, just in case
        wp_clear_scheduled_hook('shipflo_push_logs_event');
	}
}

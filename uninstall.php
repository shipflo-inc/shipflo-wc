<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path(__FILE__) . 'includes/shipflo-wc-constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/shipflo-wc-common.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shipflo-wc-uninstaller.php';

ShipFlo_Wc_Uninstaller::uninstall();
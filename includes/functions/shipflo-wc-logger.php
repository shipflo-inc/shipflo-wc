<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/** Debug Functions */
function shipflo_logger(string $level, string $message) 
{
	$wc_logger = wc_get_logger();
    $context = array('source' => 'Shipflo WooCommerce');
    $wc_logger->log($level, $message, $context);
}
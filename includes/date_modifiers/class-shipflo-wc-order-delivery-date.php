<?php

defined('ABSPATH') || exit;

class ShipFlo_Wc_Order_Delivery_Date extends ShipFlo_Wc_Date_Picker_Object
{
	protected $utc;
	protected $wp_tz;

	public function __construct($order_id)
	{
		$this->utc   = new DateTimeZone('UTC');
		$this->wp_tz = wp_timezone();

		if (is_plugin_active('order-delivery-date-for-woocommerce/order_delivery_date.php')) {
			$this->initialize_ordd_lite($order_id);
		} elseif (is_plugin_active('order-delivery-date/order_delivery_date.php')) {
			$this->initialize_ordd_pro($order_id);
		}
	}

	private function initialize_ordd_lite($order_id)
	{
		$delivery_timestamp = null;

		// Step 1: Try timeslot timestamp (most precise)
		$timeslot_timestamp = shipflo_get_order_meta($order_id, '_orddd_lite_timeslot_timestamp', true);
		if (is_numeric($timeslot_timestamp)) {
			$delivery_timestamp = (int)$timeslot_timestamp;
		}

		// Step 2: Fallback to full-day timestamp
		if (!$delivery_timestamp) {
			$full_day_timestamp = shipflo_get_order_meta($order_id, '_orddd_lite_timestamp', true);
			if (is_numeric($full_day_timestamp)) {
				$delivery_timestamp = (int)$full_day_timestamp;
			}
		}

		// Step 3: Fallback to delivery date string
		if (!$delivery_timestamp) {
			$label_lite = get_option('orddd_lite_delivery_date_field_label');
			$date_lite  = $label_lite ? shipflo_get_order_meta($order_id, $label_lite, true) : null;

			if ($date_lite) {
				$parsed = strtotime($date_lite);
				if ($parsed) {
					$delivery_timestamp = $parsed;
				} else {
					shipflo_logger('error', "[ShipFlo] ORDDD Lite: Failed to parse delivery date '{$date_lite}' for order #$order_id");
				}
			}
		}

		if (!$delivery_timestamp) {
			shipflo_logger('error', "[ShipFlo] ORDDD Lite: Could not resolve delivery timestamp for order #$order_id");
			return;
		}

		// Step 4: Base DateTime in WP timezone
		$dt = new DateTime('@' . $delivery_timestamp);
		$dt->setTimezone($this->wp_tz);

		// Step 5: Handle time slot
		$time_slot_str = Orddd_Lite_Common::orddd_get_order_timeslot($order_id);
		$this->process_time_slot($order_id, $dt, $time_slot_str, 'Lite');
	}

	private function initialize_ordd_pro($order_id)
	{
		$delivery_timestamp = shipflo_get_order_meta($order_id, '_orddd_timestamp', true);

		if (!is_numeric($delivery_timestamp)) {
			shipflo_logger('warning', "[ShipFlo] ORDDD Pro: Missing or invalid _orddd_timestamp for order #$order_id");
			return;
		}

		$dt = new DateTime('@' . (int)$delivery_timestamp);
		$dt->setTimezone($this->wp_tz);

		$time_slot_str = Orddd_Common::orddd_get_order_timeslot($order_id);
		$this->process_time_slot($order_id, $dt, $time_slot_str, 'Pro');
	}

	private function process_time_slot($order_id, DateTime $dt, ?string $time_slot_str, string $plugin)
	{
		if (!$time_slot_str) {
			shipflo_logger('info', "[ShipFlo] {$plugin}: No time slot found for order #$order_id");
			return;
		}

		shipflo_logger('info', "[ShipFlo] {$plugin}: Time slot raw: '{$time_slot_str}'");

		// Handle "ASAP" delivery
		if (stripos($time_slot_str, 'As Soon As Possible') !== false) {
			do_action('shipflo_order_delivery_asap', $order_id);
			shipflo_logger('info', "[ShipFlo] {$plugin}: ASAP triggered for order #$order_id");

			$now = new DateTime('now', $this->wp_tz);
			$now->modify('+30 minutes');

			$utcNow = clone $now;
			$utcNow->setTimezone($this->utc);

			$this->delivery_date_time = $utcNow;
			$this->delivery_time_flag = true;

			shipflo_logger('info', "[ShipFlo] {$plugin}: ASAP delivery set to {$utcNow->format('Y-m-d H:i:s T')}");
			return;
		}

		// Handle fixed time slot (e.g. "03:00 PM - 06:00 PM")
		if (preg_match('/(\d{1,2}:\d{2}\s*[APMapm\.]*)/', $time_slot_str, $m)) {
			$time = DateTime::createFromFormat('g:i A', strtoupper(trim($m[1])));
			if ($time) {
				$dt->setTime((int)$time->format('H'), (int)$time->format('i'));
				shipflo_logger('info', "[ShipFlo] {$plugin}: Parsed start time={$time->format('H:i')} (local)");
			} else {
				shipflo_logger('warning', "[ShipFlo] {$plugin}: Failed to parse time slot start from '{$time_slot_str}'");
			}
		}

		// Step 6: Convert to UTC and finalize
		$local = $dt->format('Y-m-d H:i:s');
		$utc   = clone $dt;
		$utc->setTimezone($this->utc);

		$this->delivery_date_time = $utc;
		$this->delivery_time_flag = true;

		shipflo_logger('info', sprintf(
			"[ShipFlo] ORDDD %s: local=%s (%s), UTC=%s",
			$plugin,
			$local,
			$this->wp_tz->getName(),
			$utc->format('Y-m-d H:i:s')
		));
	}
}
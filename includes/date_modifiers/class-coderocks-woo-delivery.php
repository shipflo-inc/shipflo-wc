<?php

defined('ABSPATH') || exit;

class Coderocks_Woo_Delivery extends ShipFlo_Wc_Date_Picker_Object
{
	protected $pickup_date_time = null;
	protected $delivery_date_time = null;
	protected $pickup_time_flag = false;
	protected $delivery_time_flag = false;
	protected $time_zone;
	protected $utc;
	protected $date_format = 'Y-m-d';
	protected $time_format = 'H:i:s';

	public function __construct($order_id)
	{
		$this->utc = new DateTimeZone('UTC');

		if (
			is_plugin_active('woo-delivery/coderockz-woo-delivery.php') ||
			is_plugin_active('coderockz-woocommerce-delivery-date-time-pro/coderockz-woo-delivery.php')
		) {
			require_once CODEROCKZ_WOO_DELIVERY_DIR . '/includes/class-coderockz-woo-delivery-helper.php';
			require_once CODEROCKZ_WOO_DELIVERY_DIR . '/includes/class-coderockz-woo-delivery-delivery-option.php';

			// Resolve plugin or site timezone
			$tz_string = (new Coderockz_Woo_Delivery_Helper())->get_the_timezone() ?: wp_timezone_string();
			try {
				$this->time_zone = new DateTimeZone($tz_string);
			} catch (Exception $e) {
				$this->time_zone = new DateTimeZone('UTC');
			}

			$this->set_pickup_datetime($order_id);
			$this->set_delivery_datetime($order_id);
		}
	}

	private function set_pickup_datetime($order_id)
	{
		$date = shipflo_get_order_meta($order_id, 'pickup_date', true);
		$time = shipflo_get_order_meta($order_id, 'pickup_time', true);

		shipflo_logger('info', "[ShipFlo] Raw pickup_date: '{$date}', pickup_time: '{$time}'");

		if (empty($date)) return;

		$datetime = $date;
		if (!empty($time)) {
			$this->pickup_time_flag = true;
			$start_time = trim(explode('-', $time)[0] ?? '');
			$datetime = "{$date} {$start_time}";
		}

		// Try several formats to cover 24h and 12h inputs
		$formats = [
			'Y-m-d g:i A',   // e.g. 2025-10-30 3:00 PM
			'Y-m-d H:i',     // e.g. 2025-10-30 15:00
			'Y-m-d H:i:s',
		];

		$dt = $this->try_parse_datetime($datetime, $formats);
		if (!$dt) {
			shipflo_logger('error', "[ShipFlo] Failed to parse pickup datetime from '{$datetime}'");
			return;
		}

		$local_time = $dt->format('Y-m-d H:i:s');
		$utc_dt = clone $dt;
		$utc_dt->setTimezone($this->utc);

		$this->pickup_date_time = $utc_dt;

		shipflo_logger('info', sprintf(
			'[ShipFlo] Parsed pickup: local=%s (%s), UTC=%s',
			$local_time,
			$this->time_zone->getName(),
			$utc_dt->format('Y-m-d H:i:s')
		));
	}

	private function set_delivery_datetime($order_id)
	{
		$date = shipflo_get_order_meta($order_id, 'delivery_date', true);
		$time = shipflo_get_order_meta($order_id, 'delivery_time', true);

		shipflo_logger('info', "[ShipFlo] Raw delivery_date: '{$date}', delivery_time: '{$time}'");

		if (empty($date)) return;

		$datetime = $date;
		if (!empty($time)) {
			$this->delivery_time_flag = true;
			$start_time = trim(explode('-', $time)[0] ?? '');
			$datetime = "{$date} {$start_time}";
		}

		$formats = [
			'Y-m-d g:i A',   // 12-hour format with AM/PM
			'Y-m-d H:i',     // 24-hour
			'Y-m-d H:i:s',
		];

		$dt = $this->try_parse_datetime($datetime, $formats);
		if (!$dt) {
			shipflo_logger('error', "[ShipFlo] Failed to parse delivery datetime from '{$datetime}'");
			return;
		}

		$local_time = $dt->format('Y-m-d H:i:s');
		$utc_dt = clone $dt;
		$utc_dt->setTimezone($this->utc);

		$this->delivery_date_time = $utc_dt;

		shipflo_logger('info', sprintf(
			'[ShipFlo] Parsed delivery: local=%s (%s), UTC=%s',
			$local_time,
			$this->time_zone->getName(),
			$utc_dt->format('Y-m-d H:i:s')
		));
	}

	private function try_parse_datetime(string $datetime, array $formats): ?DateTime
	{
		foreach ($formats as $format) {
			$dt = DateTime::createFromFormat($format, trim($datetime), $this->time_zone);
			if ($dt !== false) return $dt;
		}
		return null;
	}
}
<?php

// If this file is called directly, abort.
defined('ABSPATH') || exit;

class ShipFlo_Wc_Date_Picker_Object
{
	protected $pickup_date_time;
	protected $delivery_date_time;
	protected $time_zone;

	protected $delivery_time_flag = false;
	protected $pickup_time_flag = false;

	protected $utc;
	protected $date_format = 'Y-m-d';
	protected $time_format = 'H:i:s';

	public function __construct($pickup = null, $delivery = null)
	{
		// Dynamically fetch site timezone (prefer helper → fallback to WP setting → fallback UTC)
		$siteTzString = function_exists('shipflo_get_datetime_timezone')
			? (shipflo_get_datetime_timezone() ?: wp_timezone_string())
			: wp_timezone_string();

		try {
			$this->time_zone = new DateTimeZone($siteTzString);
		} catch (Exception $e) {
			$this->time_zone = new DateTimeZone('UTC');
		}

		$this->utc = new DateTimeZone('UTC');

		// Initialize local (site timezone) datetimes
		$this->pickup_date_time = $pickup ? new DateTime($pickup, $this->time_zone) : null;
		$this->delivery_date_time = $delivery ? new DateTime($delivery, $this->time_zone) : null;

		if (!$pickup) {
			$this->pickup_time_flag = true;
		}
		if ($delivery) {
			$this->delivery_time_flag = true;
		}
	}

	// ===== Presence checkers =====
	public function has_pickup_date()
	{
		return isset($this->pickup_date_time);
	}

	public function has_pickup_time()
	{
		return $this->pickup_time_flag;
	}

	public function has_delivery_date()
	{
		return isset($this->delivery_date_time);
	}

	public function has_delivery_time()
	{
		return $this->delivery_time_flag;
	}

	// ===== Getters (formatted local) =====
	public function get_pickup_date()
	{
		return $this->has_pickup_date() ? $this->pickup_date_time->format($this->date_format) : null;
	}

	public function get_pickup_time()
	{
		return $this->has_pickup_date() ? $this->pickup_date_time->format($this->time_format) : null;
	}

	public function get_delivery_date()
	{
		return $this->has_delivery_date() ? $this->delivery_date_time->format($this->date_format) : null;
	}

	public function get_delivery_time()
	{
		return $this->has_delivery_date() ? $this->delivery_date_time->format($this->time_format) : null;
	}

	// ===== ISO 8601 getters (convert to UTC safely) =====
	public function get_pickup_datetime_iso()
	{
		if (!$this->has_pickup_date()) {
			return null;
		}
		$utcClone = clone $this->pickup_date_time;
		$utcClone->setTimezone($this->utc);
		return $utcClone->format('Y-m-d\TH:i:s\Z');
	}

	public function get_delivery_datetime_iso()
	{
		if (!$this->has_delivery_date()) {
			return null;
		}
		$utcClone = clone $this->delivery_date_time;
		$utcClone->setTimezone($this->utc);
		return $utcClone->format('Y-m-d\TH:i:s\Z');
	}

	// Optional — useful for debugging or logging
	public function get_local_delivery_datetime_iso()
	{
		return $this->has_delivery_date()
			? $this->delivery_date_time->format('Y-m-d\TH:i:sP')
			: null;
	}

	public function get_local_pickup_datetime_iso()
	{
		return $this->has_pickup_date()
			? $this->pickup_date_time->format('Y-m-d\TH:i:sP')
			: null;
	}

	// ===== Robust date parser =====
	public function try_parse_date($dateString): ?int
	{
		$formats = [
			'd/m/y', 'd.m.y', 'd.m.Y', 'd-m-y', 'Y-m-d',
			'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y.m.d',
			'd F, Y', 'F d, Y', 'd M, Y', 'D, d M Y',
			'l, d F Y', 'D, d F Y', 'l, j F Y',
			'D, j M Y', 'j F, Y', 'j M, Y', 'j M Y',
			'j F Y', 'F j, Y', 'M j, Y',
		];

		foreach ($formats as $format) {
			$dt = DateTime::createFromFormat($format, $dateString, $this->time_zone);
			if ($dt && $dt->format($format) === $dateString) {
				return $dt->getTimestamp();
			}
		}

		// Last resort fallback using strtotime with local timezone
		$dt = new DateTime($dateString, $this->time_zone);
		return $dt ? $dt->getTimestamp() : null;
	}
}
<?php

defined('ABSPATH') || exit;

/**
 * Perform an HTTP request to ShipFlo backend with standardized headers and logging.
 */
function shipflo_request(string $method, string $url, ?string $apiKey = NULL, ?array $body = NULL)
{
    $headers = [
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ];

    // Use X-API-KEY
    if ($apiKey) {
        $headers['X-API-KEY'] = trim(shipflo_remove_emoji($apiKey));
    }

    $args = [
        'method'  => strtoupper($method),
        'timeout' => 45,
        'headers' => $headers,
    ];

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        shipflo_logger('error', "[ShipFlo] Request error: " . $response->get_error_message());
        return [
            'success' => false,
            'status'  => 0,
            'data'    => null,
            'error'   => $response->get_error_message(),
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $json   = json_decode($body, true);
    $jsonOk = json_last_error() === JSON_ERROR_NONE;

    if (!$jsonOk) {
        shipflo_logger('error', "[ShipFlo] Invalid JSON from $url: " . substr($body, 0, 300));
        return ['success' => false, 'status' => $status, 'data' => null, 'error' => 'Invalid JSON'];
    }

    return [
        'success' => $status >= 200 && $status < 300,
        'status'  => $status,
        'data'    => $json,
        'error'   => $json['error'] ?? null,
    ];
}

/**
 * Verify the provided API key with ShipFlo backend.
 */
function shipflo_verify_api_key(string $apiKey, string $webhookSecret)
{
    $url = shipflo_get_api_verify_url();
    $response = shipflo_request('POST', $url, $apiKey, ['webhook_secret' => $webhookSecret]);

    if (!$response['success'] || empty($response['data']['merchant_id'])) {
        shipflo_logger('error', "[ShipFlo] API Key verification failed [{$response['status']}]");
        WC_Admin_Settings::add_error(__('ShipFlo API Key verification failed. Please check your key.', 'woocommerce-settings-tab-shipflo'));
        return false;
    }

    shipflo_logger('notice', "[ShipFlo] API Key verified successfully.");
    return $response['data'];
}

/**
 * Fetch active postal codes from backend (cached for 24 hours).
 */
function shipflo_get_postal_codes()
{
    if (($cached = get_transient(SHIPFLO_ACTIVE_POSTAL_CODES_TRANSIENT)) !== false) {
        return $cached;
    }

    $apiKey = shipflo_get_api_key();
    if (empty($apiKey)) {
        shipflo_logger('error', '[ShipFlo] Missing API key during postal codes fetch.');
        return false;
    }

    $url = shipflo_get_api_postal_codes();

    $response = shipflo_request('GET', $url, $apiKey);

    if (!$response['success']) {
        shipflo_logger('error', "[ShipFlo] Failed to fetch postal codes [{$response['status']}]");
        return false;
    }

    $postal_codes = $response['data']['postal_codes'] ?? null;
    if (empty($postal_codes) || !is_array($postal_codes)) {
        shipflo_logger('error', '[ShipFlo] Invalid postal codes response structure.');
        return false;
    }

    set_transient(SHIPFLO_ACTIVE_POSTAL_CODES_TRANSIENT, $postal_codes, DAY_IN_SECONDS);
    return $postal_codes;
}

/**
 * Push the latest ShipFlo plugin logs to the backend.
 */
function shipflo_push_latest_log_to_backend()
{
    $apiKey = shipflo_get_api_key();
    if (!$apiKey) {
        shipflo_logger('error', "[ShipFlo] Missing API key");
        return;
    }

    $endpoint = trailingslashit(shipflo_get_api_logs_url()) . 'woocommerce';
    $logs_dir = trailingslashit(WP_CONTENT_DIR) . 'uploads/wc-logs/';

    $log_files = glob($logs_dir . 'shipflo-woocommerce-*.log');
    if (empty($log_files)) {
        shipflo_logger('error', "[ShipFlo] No log files found");
        return;
    }

    usort($log_files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $latest = $log_files[0];

    $offset_file = $latest . '.offset';
    $last_offset = file_exists($offset_file) ? (int) file_get_contents($offset_file) : 0;

    $fh = fopen($latest, 'r');
    if (!$fh) {
        shipflo_logger('error', "[ShipFlo] Failed to open log file");
        return;
    }

    fseek($fh, $last_offset);
    $new_content = stream_get_contents($fh);
    $new_offset = ftell($fh);
    fclose($fh);

    if (trim($new_content) === '') {
        shipflo_logger('info', "[ShipFlo] No new content in log");
        return;
    }

    $response = shipflo_request('POST', $endpoint, $apiKey, [
        'file'    => basename($latest),
        'offset'  => $last_offset,
        'content' => $new_content,
    ]);

    if (is_wp_error($response)) {
        shipflo_logger('error', "[ShipFlo] Failed to push logs: [{$response['status']}]");
    } else {
        shipflo_logger('info', "[ShipFlo] Logs Pushed successfully [{$response['status']}]");
        file_put_contents($offset_file, (string) $new_offset);
    }
}

function shipflo_post_orders(array $payloads)
{
    $success = false;

    foreach ($payloads as $apiKey => $payload_array) {
        $apiKey = trim($apiKey);
        foreach ($payload_array as $payload) {
            $response = shipflo_post_order($payload, $apiKey, shipflo_get_api_orders_url());

            if ($response['success']) {
                $success = true;
            } else {
                shipflo_logger('error', "[ShipFlo] Order post failed for {$payload['order_id']} [{$response['status']}]");
            }
        }
    }

    return $success;
}

function shipflo_post_order(array $payload, string $apiKey, string $url)
{
    $order_id = $payload['order_id'] ?? null;
    if (!$order_id || strlen($apiKey) < 3) {
        return [
            'success' => false,
            'status'  => 0,
            'error'   => 'Invalid API key or missing order_id',
        ];
    }

    $response = shipflo_request('POST', $url, $apiKey, $payload);

    // Clear transient
    delete_transient("ShipFlo_order_posted{$order_id}");

    $success = $response['success'];
    $status  = $response['status'];
    $error   = $response['error'] ?? null;
    $data    = $response['data'] ?? [];

    shipflo_update_order_meta($order_id, SHIPFLO_LAST_ATTEMPTED, time());

    if ($success) {
        shipflo_update_order_meta($order_id, SHIPFLO_DISPATCH_STATUS, 'posted');
        shipflo_logger('notice', "[ShipFlo] Order $order_id: Successfully posted [HTTP $status]");
    } else {
        $errorMsg = $error ?? ($data['message'] ?? 'Unknown error');
        shipflo_update_order_meta($order_id, SHIPFLO_DISPATCH_STATUS, 'failed');
        shipflo_update_order_meta($order_id, SHIPFLO_ERROR, $errorMsg);

        shipflo_logger('error', "[ShipFlo] Order $order_id: Post failed – $errorMsg [HTTP $status]");
    }

    // Retry logic
    $incrementRetry = $status == 202 || !$success;
    if ($incrementRetry) {
        $retry_count = (int) shipflo_get_order_meta($order_id, SHIPFLO_RETRY_COUNT, true);
        $new_retry_count = $retry_count + 1;

        shipflo_update_order_meta($order_id, SHIPFLO_RETRY_COUNT, $new_retry_count);

        if ($new_retry_count >= SHIPFLO_MAX_RETRY) {
            shipflo_logger('notice', "[ShipFlo] Order $order_id: Reached max retries ($new_retry_count).");
        }
    }

    return [
        'success' => $success,
        'status'  => $status,
        'error'   => $error,
        'data'    => $data,
    ];
}
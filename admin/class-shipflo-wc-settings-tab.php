<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class ShipFlo_Wc_Settings_Tab
{
    public static function add_settings_tab($settings_tabs)
    {
        $settings_tabs['settings_tab_shipflo'] = __('ShipFlo', 'woocommerce-settings-tab-shipflo');
        return $settings_tabs;
    }

    public static function settings_tab()
    {
        woocommerce_admin_fields(self::get_settings());
    }

    public static function update_settings()
    {
        if (!current_user_can('manage_woocommerce')) {
            WC_Admin_Settings::add_error(__('You do not have sufficient permissions to manage ShipFlo settings.', 'woocommerce-settings-tab-shipflo'));
            return;
        }
        check_admin_referer('woocommerce-settings');

        woocommerce_update_options(self::get_settings());

        if (!isset($_POST[SHIPFLO_API_KEY_OPTION_ID])) {
            return;
        }
        
        $submitted_api_key = sanitize_text_field(wp_unslash($_POST[SHIPFLO_API_KEY_OPTION_ID]));

        // If user clears the API key manually
        if (empty($submitted_api_key)) {
            shipflo_uninstall_cleanup();
            WC_Admin_Settings::add_message(__('ShipFlo API Key cleared.', 'woocommerce-settings-tab-shipflo'));
            shipflo_logger('notice', '[ShipFlo] API Key manually cleared by user.');
            return;
        }

        if (empty($submitted_api_key)) {
            WC_Admin_Settings::add_message(__('ShipFlo API Key cleared.', 'woocommerce-settings-tab-shipflo'));
            shipflo_logger('notice', '[ShipFlo] API Key cleared by user.');
            return;
        }

        // Retrieve and decrypt the existing stored key
        $existing_api_key_encrypted = get_option(SHIPFLO_API_KEY_OPTION_ID);
        $existing_api_key_plain = $existing_api_key_encrypted ? shipflo_decrypt_data($existing_api_key_encrypted) : NULL;

        // If the new key differs, clear existing data first
        if (!empty($existing_api_key_plain) && $existing_api_key_plain !== $submitted_api_key) {
            shipflo_uninstall_cleanup();
            shipflo_logger('notice', '[ShipFlo] Previous API key and merchant data cleared before re-verification.');
        }

        shipflo_logger('info', '[ShipFlo] Attempting to verify new ShipFlo API Key.');
        $webhook_secret = shipflo_get_webhook_secret();
        $merchant_details = shipflo_verify_api_key($submitted_api_key, $webhook_secret);

        if ($merchant_details !== false && isset($merchant_details['merchant_id'], $merchant_details['webhook_secret'])) {
            $encrypted_api_key = shipflo_encrypt_data($submitted_api_key);

            if (empty($encrypted_api_key)) {
                shipflo_logger('error', '[ShipFlo] Encryption failed for API Key after successful verification.');
                WC_Admin_Settings::add_error(__('Internal error: API Key encryption failed.', 'woocommerce-settings-tab-shipflo'));
                return;
            }

            update_option(SHIPFLO_MERCHANT_REGISTERED_UUID, wp_generate_uuid4(), false);
            update_option(SHIPFLO_API_KEY_OPTION_ID, $encrypted_api_key, false);
            update_option(SHIPFLO_MERCHANT_ID_OPTION_ID, sanitize_text_field($merchant_details['merchant_id']), false);
            update_option(SHIPFLO_WEBHOOK_SECRET, sanitize_text_field($merchant_details['webhook_secret']), false);

            WC_Admin_Settings::add_message(__('ShipFlo API Key successfully verified and saved.', 'woocommerce-settings-tab-shipflo'));
            shipflo_logger('notice', '[ShipFlo] API Key verified and merchant details saved.');

        } else {
            shipflo_logger('error', '[ShipFlo] API Key verification failed. Clearing integration data.');

            delete_option(SHIPFLO_API_KEY_OPTION_ID);
            delete_option(SHIPFLO_MERCHANT_ID_OPTION_ID);
            delete_option(SHIPFLO_WEBHOOK_SECRET);
            delete_option(SHIPFLO_MERCHANT_REGISTERED_UUID);
        }
    }

    public static function get_settings()
    {
        return apply_filters('wc_settings_tab_shipflo_settings', [
            [
                'name'     => __('General Settings', 'woocommerce-settings-tab-shipflo'),
                'type'     => 'title',
                'id'       => 'wc_settings_tab_shipflo_general_section_title',
            ],
            [
                'name'     => __('ShipFlo API Key', 'woocommerce-settings-tab-shipflo'),
                'type'     => 'password',
                'desc'     => __('Login to your ShipFlo account → Profile → Generate API Key → Copy it.', 'woocommerce-settings-tab-shipflo'),
                'id'       => SHIPFLO_API_KEY_OPTION_ID,
                'default'  => '',
                'autoload' => false,
            ],
            [
                'type'     => 'sectionend',
                'id'       => 'wc_settings_tab_shipflo_general_section_end',
            ],
        ]);
    }
}
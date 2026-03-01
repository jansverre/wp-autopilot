<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class License {

    /**
     * Gumroad API endpoint for license verification.
     */
    const GUMROAD_API_URL  = 'https://api.gumroad.com/v2/licenses/verify';
    const GUMROAD_PRODUCT_ID = ''; // Set when Gumroad product is created.

    /**
     * Transient key for caching pro status.
     */
    const TRANSIENT_KEY = 'wpa_pro_status';

    /**
     * Grace period in days for pre-v2 users.
     */
    const GRACE_PERIOD_DAYS = 30;

    /**
     * In-memory cache for is_pro() result.
     *
     * @var bool|null
     */
    private static $is_pro_cache = null;

    /**
     * Check if the current site has Pro access.
     *
     * Priority:
     * 1. WPA_PRO constant (developer override)
     * 2. In-memory cache
     * 3. Transient cache (24h)
     * 4. Stored license key validation
     * 5. Grace period for pre-v2 users
     *
     * @return bool
     */
    public static function is_pro() {
        // All features unlocked until Gumroad product is configured.
        if ( empty( self::GUMROAD_PRODUCT_ID ) ) {
            return true;
        }

        // Developer override via wp-config.php.
        if ( defined( 'WPA_PRO' ) && WPA_PRO ) {
            return true;
        }

        // In-memory cache.
        if ( self::$is_pro_cache !== null ) {
            return self::$is_pro_cache;
        }

        // Transient cache (24h).
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) {
            self::$is_pro_cache = ( $cached === 'yes' );
            return self::$is_pro_cache;
        }

        // Check stored license.
        $license_key = get_option( 'wpa_license_key', '' );
        if ( ! empty( $license_key ) ) {
            $valid = self::validate_key( $license_key );
            self::$is_pro_cache = $valid;
            set_transient( self::TRANSIENT_KEY, $valid ? 'yes' : 'no', DAY_IN_SECONDS );
            return self::$is_pro_cache;
        }

        // Grace period for pre-v2 users.
        if ( self::is_in_grace_period() ) {
            self::$is_pro_cache = true;
            set_transient( self::TRANSIENT_KEY, 'yes', DAY_IN_SECONDS );
            return true;
        }

        self::$is_pro_cache = false;
        set_transient( self::TRANSIENT_KEY, 'no', DAY_IN_SECONDS );
        return false;
    }

    /**
     * Activate a license key.
     *
     * @param string $key License key from Gumroad.
     * @return array {success: bool, message: string}
     */
    public static function activate( $key ) {
        $key = sanitize_text_field( trim( $key ) );

        if ( empty( $key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Please enter a license key.', 'wp-autopilot' ),
            );
        }

        $response = wp_remote_post( self::GUMROAD_API_URL, array(
            'timeout' => 15,
            'body'    => array(
                'product_id'           => self::GUMROAD_PRODUCT_ID,
                'license_key'          => $key,
                'increment_uses_count' => 'true',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Could not connect to license server.', 'wp-autopilot' ),
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['success'] ) && self::is_purchase_valid( $data['purchase'] ?? array() ) ) {
            update_option( 'wpa_license_key', $key );

            // Clear caches.
            self::$is_pro_cache = true;
            set_transient( self::TRANSIENT_KEY, 'yes', DAY_IN_SECONDS );

            return array(
                'success' => true,
                'message' => __( 'License activated successfully!', 'wp-autopilot' ),
            );
        }

        // Valid key but purchase refunded/disputed/cancelled.
        if ( ! empty( $data['success'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'This license is no longer valid (refunded or cancelled).', 'wp-autopilot' ),
            );
        }

        $error = $data['message'] ?? __( 'Invalid license key.', 'wp-autopilot' );

        return array(
            'success' => false,
            'message' => $error,
        );
    }

    /**
     * Deactivate the current license (local only — Gumroad decrement requires OAuth).
     *
     * @return array {success: bool, message: string}
     */
    public static function deactivate() {
        $key = get_option( 'wpa_license_key', '' );

        if ( empty( $key ) ) {
            return array(
                'success' => false,
                'message' => __( 'No license key found.', 'wp-autopilot' ),
            );
        }

        delete_option( 'wpa_license_key' );
        self::$is_pro_cache = null;
        delete_transient( self::TRANSIENT_KEY );

        return array(
            'success' => true,
            'message' => __( 'License deactivated successfully.', 'wp-autopilot' ),
        );
    }

    /**
     * Validate a license key against Gumroad.
     *
     * @param string $key License key.
     * @return bool
     */
    private static function validate_key( $key ) {
        $response = wp_remote_post( self::GUMROAD_API_URL, array(
            'timeout' => 15,
            'body'    => array(
                'product_id'           => self::GUMROAD_PRODUCT_ID,
                'license_key'          => $key,
                'increment_uses_count' => 'false',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            // If we can't reach the server, give benefit of the doubt.
            return true;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return ! empty( $data['success'] ) && self::is_purchase_valid( $data['purchase'] ?? array() );
    }

    /**
     * Check that a Gumroad purchase is still valid (not refunded, disputed, or cancelled).
     *
     * @param array $purchase Purchase data from Gumroad API response.
     * @return bool
     */
    private static function is_purchase_valid( $purchase ) {
        if ( empty( $purchase ) ) {
            return false;
        }

        if ( ! empty( $purchase['refunded'] ) ) {
            return false;
        }

        if ( ! empty( $purchase['disputed'] ) ) {
            return false;
        }

        if ( ! empty( $purchase['subscription_cancelled_at'] ) ) {
            return false;
        }

        if ( ! empty( $purchase['subscription_failed_at'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current site is within the v2 grace period.
     *
     * @return bool
     */
    private static function is_in_grace_period() {
        $installed_before_v2 = get_option( 'wpa_installed_before_v2', '' );

        if ( empty( $installed_before_v2 ) ) {
            return false;
        }

        $grace_end = strtotime( $installed_before_v2 ) + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );

        return time() < $grace_end;
    }

    /**
     * Get the stored license key (masked for display).
     *
     * @return string
     */
    public static function get_masked_key() {
        $key = get_option( 'wpa_license_key', '' );
        if ( empty( $key ) || strlen( $key ) < 8 ) {
            return '';
        }
        return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
    }

    /**
     * Check if a license key is stored.
     *
     * @return bool
     */
    public static function has_key() {
        return ! empty( get_option( 'wpa_license_key', '' ) );
    }

    /**
     * Flush cached pro status (useful after activation/deactivation).
     */
    public static function flush_cache() {
        self::$is_pro_cache = null;
        delete_transient( self::TRANSIENT_KEY );
    }
}

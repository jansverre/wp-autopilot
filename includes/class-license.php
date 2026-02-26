<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class License {

    /**
     * Lemon Squeezy API endpoint for license validation.
     */
    const LS_VALIDATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    const LS_ACTIVATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/activate';
    const LS_DEACTIVATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/deactivate';

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
     * @param string $key License key from Lemon Squeezy.
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

        $response = wp_remote_post( self::LS_ACTIVATE_URL, array(
            'timeout' => 15,
            'body'    => array(
                'license_key'   => $key,
                'instance_name' => self::get_instance_name(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Could not connect to license server.', 'wp-autopilot' ),
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['activated'] ) || ( ! empty( $data['license_key'] ) && $data['license_key']['status'] === 'active' ) ) {
            // Store the key and instance ID.
            update_option( 'wpa_license_key', $key );
            if ( ! empty( $data['instance']['id'] ) ) {
                update_option( 'wpa_license_instance_id', $data['instance']['id'] );
            }

            // Clear caches.
            self::$is_pro_cache = true;
            set_transient( self::TRANSIENT_KEY, 'yes', DAY_IN_SECONDS );

            return array(
                'success' => true,
                'message' => __( 'License activated successfully!', 'wp-autopilot' ),
            );
        }

        $error = $data['error'] ?? ( $data['message'] ?? __( 'Invalid license key.', 'wp-autopilot' ) );

        return array(
            'success' => false,
            'message' => $error,
        );
    }

    /**
     * Deactivate the current license.
     *
     * @return array {success: bool, message: string}
     */
    public static function deactivate() {
        $key         = get_option( 'wpa_license_key', '' );
        $instance_id = get_option( 'wpa_license_instance_id', '' );

        if ( empty( $key ) ) {
            return array(
                'success' => false,
                'message' => __( 'No license key found.', 'wp-autopilot' ),
            );
        }

        $response = wp_remote_post( self::LS_DEACTIVATE_URL, array(
            'timeout' => 15,
            'body'    => array(
                'license_key' => $key,
                'instance_id' => $instance_id,
            ),
        ) );

        // Remove local data regardless of API response.
        delete_option( 'wpa_license_key' );
        delete_option( 'wpa_license_instance_id' );
        self::$is_pro_cache = null;
        delete_transient( self::TRANSIENT_KEY );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => true,
                'message' => __( 'License removed locally. Could not reach license server.', 'wp-autopilot' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'License deactivated successfully.', 'wp-autopilot' ),
        );
    }

    /**
     * Validate a license key against Lemon Squeezy.
     *
     * @param string $key License key.
     * @return bool
     */
    private static function validate_key( $key ) {
        $instance_id = get_option( 'wpa_license_instance_id', '' );

        $response = wp_remote_post( self::LS_VALIDATE_URL, array(
            'timeout' => 15,
            'body'    => array(
                'license_key' => $key,
                'instance_id' => $instance_id,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            // If we can't reach the server, give benefit of the doubt.
            return true;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return ! empty( $data['valid'] );
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
     * Get a unique instance name for this site.
     *
     * @return string
     */
    private static function get_instance_name() {
        return wp_parse_url( home_url(), PHP_URL_HOST );
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

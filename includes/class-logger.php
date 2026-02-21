<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    const MAX_ROWS = 500;

    /**
     * Log an info message.
     */
    public static function info( $message, $context = '' ) {
        self::log( 'info', $message, $context );
    }

    /**
     * Log a warning message.
     */
    public static function warning( $message, $context = '' ) {
        self::log( 'warning', $message, $context );
    }

    /**
     * Log an error message.
     */
    public static function error( $message, $context = '' ) {
        self::log( 'error', $message, $context );
    }

    /**
     * Insert a log row and prune old entries.
     */
    private static function log( $level, $message, $context = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_log';

        if ( is_array( $context ) || is_object( $context ) ) {
            $context = wp_json_encode( $context );
        }

        $wpdb->insert(
            $table,
            array(
                'level'      => $level,
                'message'    => $message,
                'context'    => $context,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        self::prune();
    }

    /**
     * Keep only the latest MAX_ROWS entries.
     */
    private static function prune() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_log';

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        if ( $count > self::MAX_ROWS ) {
            $delete_count = $count - self::MAX_ROWS;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
                    $delete_count
                )
            );
        }
    }

    /**
     * Get latest log entries.
     *
     * @param int $limit Number of rows.
     * @return array
     */
    public static function get_latest( $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_log';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
}

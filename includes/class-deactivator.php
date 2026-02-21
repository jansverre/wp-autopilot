<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Run on plugin deactivation. Remove scheduled cron events.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'wpa_run_autopilot' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wpa_run_autopilot' );
        }
        wp_clear_scheduled_hook( 'wpa_run_autopilot' );
    }
}

<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpa_seen_articles" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpa_internal_links" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpa_log" );

// Delete all plugin options.
$options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpa\_%'"
);

foreach ( $options as $option ) {
    delete_option( $option );
}

<?php
/**
 * Fired when the plugin is uninstalled.
 * Only deletes data if "Delete data on uninstall" is enabled in settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user wants data removed. Default: keep data.
$delete_data = get_option( 'wpa_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
    return;
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

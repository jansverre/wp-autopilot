<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();

        // Index existing posts for internal linking.
        $links = new InternalLinks();
        $links->sync_existing_posts();
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $seen = $wpdb->prefix . 'wpa_seen_articles';
        $links = $wpdb->prefix . 'wpa_internal_links';
        $log   = $wpdb->prefix . 'wpa_log';

        $sql = "CREATE TABLE {$seen} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hash VARCHAR(32) NOT NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY hash (hash)
        ) {$charset};

        CREATE TABLE {$links} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(500) NOT NULL,
            url VARCHAR(2083) NOT NULL,
            keywords TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id)
        ) {$charset};

        CREATE TABLE {$log} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Set default plugin options if they don't already exist.
     */
    private static function set_default_options() {
        $defaults = array(
            // API keys
            'openrouter_api_key'  => '',
            'fal_api_key'         => '',

            // AI settings
            'ai_model'            => 'google/gemini-3-flash-preview',
            'ai_custom_model'     => '',
            'ai_language'         => 'norsk',
            'ai_niche'            => '',
            'ai_style'            => 'informativ og engasjerende',
            'ai_temperature'      => 0.7,

            // Content settings
            'min_words'           => 600,
            'max_words'           => 1200,
            'include_source_link' => true,

            // Publishing settings
            'post_status'         => 'draft',
            'post_author'         => 1,
            'default_category'    => 0,

            // Cron settings
            'enabled'             => false,
            'cron_interval'       => 'every_6_hours',
            'max_per_run'         => 3,
            'max_per_day'         => 10,

            // Image settings
            'generate_images'     => true,
            'image_model'         => 'fal-ai/flux-2-pro',
            'image_custom_model'  => '',
            'image_style'         => 'photorealistic editorial style',

            // Work hours
            'work_hours_enabled'  => false,
            'work_hours_start'    => 8,
            'work_hours_end'      => 22,

            // Filtering
            'keyword_include'     => '',
            'keyword_exclude'     => '',

            // Feeds stored as JSON array
            'feeds'               => '[]',
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( 'wpa_' . $key ) === false ) {
                add_option( 'wpa_' . $key, $value );
            }
        }
    }
}

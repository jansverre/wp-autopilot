<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Current DB schema version.
     */
    const DB_VERSION = 4;

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();

        update_option( 'wpa_db_version', self::DB_VERSION );

        // Index existing posts for internal linking.
        $links = new InternalLinks();
        $links->sync_existing_posts();
    }

    /**
     * Run on plugins_loaded to handle seamless DB upgrades.
     */
    public static function maybe_upgrade() {
        $current = (int) get_option( 'wpa_db_version', 1 );

        if ( $current < self::DB_VERSION ) {
            self::create_tables();
            self::set_default_options();
            update_option( 'wpa_db_version', self::DB_VERSION );
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $seen  = $wpdb->prefix . 'wpa_seen_articles';
        $links = $wpdb->prefix . 'wpa_internal_links';
        $log   = $wpdb->prefix . 'wpa_log';
        $costs = $wpdb->prefix . 'wpa_costs';

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
        ) {$charset};

        CREATE TABLE {$costs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(30) NOT NULL,
            model VARCHAR(200) NOT NULL DEFAULT '',
            tokens_in INT UNSIGNED DEFAULT 0,
            tokens_out INT UNSIGNED DEFAULT 0,
            cost_usd DECIMAL(10,6) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY type (type)
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
            'site_identity'       => '',

            // Content settings
            'min_words'           => 600,
            'max_words'           => 1200,
            'include_source_link' => true,

            // Publishing settings
            'post_status'         => 'draft',
            'post_author'         => 1,
            'default_category'    => 0,

            // Author settings
            'post_authors'        => '[]',
            'author_method'       => 'single',
            'author_index'        => 0,

            // Writing styles (per-author JSON)
            'writing_styles'      => '{}',

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

            // Inline image settings
            'inline_images_enabled'    => false,
            'inline_images_frequency'  => 'every_other_h2',
            'inline_image_model'       => 'fal-ai/flux-2-pro',
            'inline_image_custom_model' => '',

            // Work hours
            'work_hours_enabled'  => false,
            'work_hours_start'    => 8,
            'work_hours_end'      => 22,

            // Filtering
            'keyword_include'     => '',
            'keyword_exclude'     => '',

            // Data management
            'delete_data_on_uninstall' => false,

            // Facebook sharing
            'fb_enabled'          => false,
            'fb_page_id'          => '',
            'fb_access_token'     => '',
            'fb_image_mode'       => 'featured_image',
            'fb_author_face'      => false,
            'fb_author_photos'    => '{}',
            'fb_poster_per_run'   => 0,
            'fb_poster_daily_limit' => 0,
            'fb_poster_authors'   => '[]',
            'fb_no_poster_mode'   => 'ai_text',

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

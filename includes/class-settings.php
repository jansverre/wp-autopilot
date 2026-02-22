<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    private static $cache = array();

    /**
     * Initialize settings (no-op placeholder for future use).
     */
    public static function init() {
        // Intentionally empty — called early to ensure autoload.
    }

    /**
     * Get a single setting value.
     *
     * @param string $key     Setting key (without wpa_ prefix).
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        if ( isset( self::$cache[ $key ] ) ) {
            return self::$cache[ $key ];
        }

        $value = get_option( 'wpa_' . $key, $default );
        self::$cache[ $key ] = $value;

        return $value;
    }

    /**
     * Set a single setting value.
     *
     * @param string $key   Setting key (without wpa_ prefix).
     * @param mixed  $value Value to save.
     */
    public static function set( $key, $value ) {
        update_option( 'wpa_' . $key, $value );
        self::$cache[ $key ] = $value;
    }

    /**
     * Get all plugin settings as an associative array.
     *
     * @return array
     */
    public static function all() {
        $keys = array(
            'openrouter_api_key',
            'fal_api_key',
            'ai_model',
            'ai_custom_model',
            'ai_language',
            'ai_niche',
            'ai_style',
            'ai_temperature',
            'min_words',
            'max_words',
            'include_source_link',
            'post_status',
            'post_author',
            'default_category',
            'enabled',
            'cron_interval',
            'max_per_run',
            'max_per_day',
            'generate_images',
            'image_model',
            'image_custom_model',
            'image_style',
            'work_hours_enabled',
            'work_hours_start',
            'work_hours_end',
            'keyword_include',
            'keyword_exclude',
            'delete_data_on_uninstall',
            'feeds',
        );

        $settings = array();
        foreach ( $keys as $key ) {
            $settings[ $key ] = self::get( $key );
        }

        return $settings;
    }

    /**
     * Clear the internal cache (useful after bulk updates).
     */
    public static function flush_cache() {
        self::$cache = array();
    }
}

<?php

namespace WPAutopilot\Admin;

use WPAutopilot\Includes\Settings;
use WPAutopilot\Includes\Logger;
use WPAutopilot\Includes\Cron;
use WPAutopilot\Includes\InternalLinks;
use WPAutopilot\Includes\ArticleWriter;
use WPAutopilot\Includes\CostTracker;
use WPAutopilot\Includes\FacebookSharer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    /**
     * Register admin hooks.
     */
    public function register() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_settings_save' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_wpa_add_feed', array( $this, 'ajax_add_feed' ) );
        add_action( 'wp_ajax_wpa_delete_feed', array( $this, 'ajax_delete_feed' ) );
        add_action( 'wp_ajax_wpa_toggle_feed', array( $this, 'ajax_toggle_feed' ) );
        add_action( 'wp_ajax_wpa_run_now', array( $this, 'ajax_run_now' ) );
        add_action( 'wp_ajax_wpa_reindex', array( $this, 'ajax_reindex' ) );
        add_action( 'wp_ajax_wpa_get_log', array( $this, 'ajax_get_log' ) );
        add_action( 'wp_ajax_wpa_analyze_style', array( $this, 'ajax_analyze_style' ) );
        add_action( 'wp_ajax_wpa_save_writing_style', array( $this, 'ajax_save_writing_style' ) );
        add_action( 'wp_ajax_wpa_test_fb', array( $this, 'ajax_test_fb' ) );
    }

    /**
     * Add admin menu pages.
     */
    public function add_menu() {
        add_menu_page(
            'WP Autopilot',
            'WP Autopilot',
            'manage_options',
            'wpa-settings',
            array( $this, 'render_settings' ),
            'dashicons-airplane',
            80
        );

        add_submenu_page(
            'wpa-settings',
            __( 'Settings', 'wp-autopilot' ),
            __( 'Settings', 'wp-autopilot' ),
            'manage_options',
            'wpa-settings',
            array( $this, 'render_settings' )
        );

        add_submenu_page(
            'wpa-settings',
            __( 'Feeds', 'wp-autopilot' ),
            __( 'Feeds', 'wp-autopilot' ),
            'manage_options',
            'wpa-feeds',
            array( $this, 'render_feeds' )
        );

        add_submenu_page(
            'wpa-settings',
            __( 'Status', 'wp-autopilot' ),
            __( 'Status', 'wp-autopilot' ),
            'manage_options',
            'wpa-status',
            array( $this, 'render_status' )
        );
    }

    /**
     * Enqueue admin CSS and JS only on plugin pages.
     */
    public function enqueue_assets( $hook ) {
        $plugin_pages = array(
            'toplevel_page_wpa-settings',
            'wp-autopilot_page_wpa-feeds',
            'wp-autopilot_page_wpa-status',
        );

        if ( ! in_array( $hook, $plugin_pages, true ) ) {
            return;
        }

        // Enqueue WP Media uploader on settings page (for FB author photos).
        if ( $hook === 'toplevel_page_wpa-settings' ) {
            wp_enqueue_media();
        }

        wp_enqueue_style(
            'wpa-admin-css',
            WPA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPA_VERSION
        );

        wp_enqueue_script(
            'wpa-admin-js',
            WPA_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WPA_VERSION,
            true
        );

        wp_localize_script( 'wpa-admin-js', 'wpaAdmin', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wpa_admin_nonce' ),
            'writingStyles' => json_decode( Settings::get( 'writing_styles', '{}' ), true ),
            'i18n'          => array(
                'urlRequired'           => __( 'URL is required.', 'wp-autopilot' ),
                'feedAdded'             => __( 'Feed added.', 'wp-autopilot' ),
                'errorAdding'           => __( 'Error adding feed.', 'wp-autopilot' ),
                'networkError'          => __( 'Network error.', 'wp-autopilot' ),
                'confirmDeleteFeed'     => __( 'Are you sure you want to delete this feed?', 'wp-autopilot' ),
                'noFeeds'               => __( 'No feeds added yet.', 'wp-autopilot' ),
                'active'                => __( 'Active', 'wp-autopilot' ),
                'inactive'              => __( 'Inactive', 'wp-autopilot' ),
                'activate'              => __( 'Activate', 'wp-autopilot' ),
                'deactivate'            => __( 'Deactivate', 'wp-autopilot' ),
                'delete'                => __( 'Delete', 'wp-autopilot' ),
                'runningAutopilot'      => __( 'Running autopilot... This may take a few minutes.', 'wp-autopilot' ),
                'errorRunning'          => __( 'Error during run.', 'wp-autopilot' ),
                'networkOrTimeout'      => __( 'Network error or timeout.', 'wp-autopilot' ),
                'reindexing'            => __( 'Re-indexing...', 'wp-autopilot' ),
                'errorReindexing'       => __( 'Error during re-indexing.', 'wp-autopilot' ),
                'noLogEntries'          => __( 'No log entries yet.', 'wp-autopilot' ),
                'analyzingStyle'        => __( 'Analyzing writing style...', 'wp-autopilot' ),
                'analysisComplete'      => __( 'Analysis complete. Click "Save writing style" to keep it.', 'wp-autopilot' ),
                'errorAnalysis'         => __( 'Error during analysis.', 'wp-autopilot' ),
                'styleSaved'            => __( 'Writing style saved.', 'wp-autopilot' ),
                'errorSaving'           => __( 'Error saving.', 'wp-autopilot' ),
                'fillPageIdAndToken'    => __( 'Please enter Page ID and access token.', 'wp-autopilot' ),
                'connected'             => __( 'Connected:', 'wp-autopilot' ),
                'connectionError'       => __( 'Connection error.', 'wp-autopilot' ),
                'selectAuthorPhoto'     => __( 'Select author photo', 'wp-autopilot' ),
                'useThisImage'          => __( 'Use this image', 'wp-autopilot' ),
            ),
        ) );
    }

    /**
     * Render Settings page.
     */
    public function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include WPA_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render Feeds page.
     */
    public function render_feeds() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include WPA_PLUGIN_DIR . 'admin/views/feeds.php';
    }

    /**
     * Render Status page.
     */
    public function render_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include WPA_PLUGIN_DIR . 'admin/views/status.php';
    }

    /**
     * Handle settings form submission.
     */
    public function handle_settings_save() {
        if ( ! isset( $_POST['wpa_settings_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wpa_settings_nonce'], 'wpa_save_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $old_interval = Settings::get( 'cron_interval' );
        $old_enabled  = Settings::get( 'enabled' );

        // Text fields.
        $text_fields = array(
            'openrouter_api_key', 'fal_api_key', 'github_token', 'ai_model', 'ai_custom_model',
            'ai_language', 'ai_niche', 'ai_style', 'image_model', 'image_custom_model', 'image_style',
            'keyword_include', 'keyword_exclude',
            'inline_image_model', 'inline_image_custom_model',
        );
        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ 'wpa_' . $field ] ) ) {
                Settings::set( $field, sanitize_text_field( wp_unslash( $_POST[ 'wpa_' . $field ] ) ) );
            }
        }

        // Textarea fields (preserve line breaks).
        $textarea_fields = array( 'site_identity' );
        foreach ( $textarea_fields as $field ) {
            if ( isset( $_POST[ 'wpa_' . $field ] ) ) {
                Settings::set( $field, sanitize_textarea_field( wp_unslash( $_POST[ 'wpa_' . $field ] ) ) );
            }
        }

        // Numeric fields.
        $numeric_fields = array(
            'min_words', 'max_words', 'max_per_run', 'max_per_day', 'post_author', 'default_category',
            'work_hours_start', 'work_hours_end',
        );
        foreach ( $numeric_fields as $field ) {
            if ( isset( $_POST[ 'wpa_' . $field ] ) ) {
                Settings::set( $field, absint( $_POST[ 'wpa_' . $field ] ) );
            }
        }

        // Float fields.
        if ( isset( $_POST['wpa_ai_temperature'] ) ) {
            $temp = (float) $_POST['wpa_ai_temperature'];
            $temp = max( 0, min( 2, $temp ) );
            Settings::set( 'ai_temperature', $temp );
        }

        // Select fields.
        if ( isset( $_POST['wpa_post_status'] ) ) {
            $status = sanitize_text_field( wp_unslash( $_POST['wpa_post_status'] ) );
            if ( in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ) {
                Settings::set( 'post_status', $status );
            }
        }

        if ( isset( $_POST['wpa_cron_interval'] ) ) {
            Settings::set( 'cron_interval', sanitize_text_field( wp_unslash( $_POST['wpa_cron_interval'] ) ) );
        }

        if ( isset( $_POST['wpa_inline_images_frequency'] ) ) {
            $freq = sanitize_text_field( wp_unslash( $_POST['wpa_inline_images_frequency'] ) );
            if ( in_array( $freq, array( 'every_h2', 'every_other_h2', 'every_third_h2' ), true ) ) {
                Settings::set( 'inline_images_frequency', $freq );
            }
        }

        // Author method.
        if ( isset( $_POST['wpa_author_method'] ) ) {
            $method = sanitize_text_field( wp_unslash( $_POST['wpa_author_method'] ) );
            if ( in_array( $method, array( 'single', 'random', 'round_robin', 'percentage' ), true ) ) {
                Settings::set( 'author_method', $method );
            }
        }

        // Post authors JSON (validated array of {id, weight}).
        if ( isset( $_POST['wpa_post_authors'] ) ) {
            $raw = wp_unslash( $_POST['wpa_post_authors'] );
            $authors = json_decode( $raw, true );
            if ( is_array( $authors ) ) {
                $clean = array();
                foreach ( $authors as $a ) {
                    if ( isset( $a['id'] ) && get_userdata( (int) $a['id'] ) ) {
                        $clean[] = array(
                            'id'     => (int) $a['id'],
                            'weight' => max( 1, (int) ( $a['weight'] ?? 1 ) ),
                        );
                    }
                }
                Settings::set( 'post_authors', wp_json_encode( $clean ) );
            }
        }

        // Facebook settings.
        if ( isset( $_POST['wpa_fb_page_id'] ) ) {
            Settings::set( 'fb_page_id', sanitize_text_field( wp_unslash( $_POST['wpa_fb_page_id'] ) ) );
        }
        if ( isset( $_POST['wpa_fb_access_token'] ) ) {
            Settings::set( 'fb_access_token', sanitize_text_field( wp_unslash( $_POST['wpa_fb_access_token'] ) ) );
        }
        if ( isset( $_POST['wpa_fb_image_mode'] ) ) {
            $fb_mode = sanitize_text_field( wp_unslash( $_POST['wpa_fb_image_mode'] ) );
            if ( in_array( $fb_mode, array( 'featured_image', 'generated_poster' ), true ) ) {
                Settings::set( 'fb_image_mode', $fb_mode );
            }
        }

        // Facebook author photos (collect per-author hidden inputs).
        $fb_author_photos = array();
        $wp_users = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ), 'fields' => 'ID' ) );
        foreach ( $wp_users as $uid ) {
            $key = 'wpa_fb_author_photo_' . $uid;
            if ( isset( $_POST[ $key ] ) && absint( $_POST[ $key ] ) > 0 ) {
                $fb_author_photos[ (int) $uid ] = absint( $_POST[ $key ] );
            }
        }
        Settings::set( 'fb_author_photos', wp_json_encode( $fb_author_photos ) );

        // Checkboxes.
        Settings::set( 'enabled', ! empty( $_POST['wpa_enabled'] ) );
        Settings::set( 'generate_images', ! empty( $_POST['wpa_generate_images'] ) );
        Settings::set( 'include_source_link', ! empty( $_POST['wpa_include_source_link'] ) );
        Settings::set( 'work_hours_enabled', ! empty( $_POST['wpa_work_hours_enabled'] ) );
        Settings::set( 'delete_data_on_uninstall', ! empty( $_POST['wpa_delete_data_on_uninstall'] ) );
        Settings::set( 'inline_images_enabled', ! empty( $_POST['wpa_inline_images_enabled'] ) );
        Settings::set( 'fb_enabled', ! empty( $_POST['wpa_fb_enabled'] ) );
        Settings::set( 'fb_author_face', ! empty( $_POST['wpa_fb_author_face'] ) );

        // Reschedule cron if interval or enabled changed.
        $new_interval = Settings::get( 'cron_interval' );
        $new_enabled  = Settings::get( 'enabled' );
        if ( $old_interval !== $new_interval || $old_enabled !== $new_enabled ) {
            $cron = new Cron();
            $cron->reschedule();
        }

        Settings::flush_cache();

        add_settings_error( 'wpa_settings', 'settings_updated', __( 'Settings saved.', 'wp-autopilot' ), 'updated' );
    }

    /**
     * AJAX: Add a new feed.
     */
    public function ajax_add_feed() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $url  = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

        if ( empty( $url ) ) {
            wp_send_json_error( __( 'URL is required.', 'wp-autopilot' ) );
        }

        $feeds = json_decode( Settings::get( 'feeds', '[]' ), true );
        if ( ! is_array( $feeds ) ) {
            $feeds = array();
        }

        $feeds[] = array(
            'id'     => wp_generate_uuid4(),
            'name'   => $name ?: wp_parse_url( $url, PHP_URL_HOST ),
            'url'    => $url,
            'active' => true,
        );

        Settings::set( 'feeds', wp_json_encode( $feeds ) );

        wp_send_json_success( array( 'feeds' => $feeds ) );
    }

    /**
     * AJAX: Delete a feed.
     */
    public function ajax_delete_feed() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $feed_id = sanitize_text_field( wp_unslash( $_POST['feed_id'] ?? '' ) );
        $feeds   = json_decode( Settings::get( 'feeds', '[]' ), true );

        $feeds = array_filter( $feeds, function ( $feed ) use ( $feed_id ) {
            return $feed['id'] !== $feed_id;
        } );

        Settings::set( 'feeds', wp_json_encode( array_values( $feeds ) ) );

        wp_send_json_success( array( 'feeds' => array_values( $feeds ) ) );
    }

    /**
     * AJAX: Toggle feed active/inactive.
     */
    public function ajax_toggle_feed() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $feed_id = sanitize_text_field( wp_unslash( $_POST['feed_id'] ?? '' ) );
        $feeds   = json_decode( Settings::get( 'feeds', '[]' ), true );

        foreach ( $feeds as &$feed ) {
            if ( $feed['id'] === $feed_id ) {
                $feed['active'] = ! $feed['active'];
                break;
            }
        }

        Settings::set( 'feeds', wp_json_encode( $feeds ) );

        wp_send_json_success( array( 'feeds' => $feeds ) );
    }

    /**
     * AJAX: Run autopilot now.
     */
    public function ajax_run_now() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        define( 'WPA_MANUAL_RUN', true );

        $cron = new Cron();
        $cron->run();

        wp_send_json_success( array( 'message' => __( 'Run completed.', 'wp-autopilot' ) ) );
    }

    /**
     * AJAX: Re-index internal links.
     */
    public function ajax_reindex() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $links = new InternalLinks();
        $links->sync_existing_posts();

        wp_send_json_success( array( 'message' => __( 'Re-indexing completed.', 'wp-autopilot' ) ) );
    }

    /**
     * AJAX: Get latest log entries.
     */
    public function ajax_get_log() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $logs = Logger::get_latest( 50 );

        wp_send_json_success( array( 'logs' => $logs ) );
    }

    /**
     * AJAX: Analyze writing style for a specific author.
     */
    public function ajax_analyze_style() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $author_id = absint( $_POST['author_id'] ?? 0 );
        $num_posts = absint( $_POST['num_posts'] ?? 5 );

        if ( ! $author_id || ! get_userdata( $author_id ) ) {
            wp_send_json_error( __( 'Invalid author.', 'wp-autopilot' ) );
        }

        $writer = new ArticleWriter();
        $result = $writer->analyze_style( $author_id, $num_posts );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result['error'] );
        }

        // Log style analysis cost.
        if ( ! empty( $result['response_data'] ) ) {
            CostTracker::log_text( null, $result['model'], $result['response_data'] );
        }

        wp_send_json_success( array( 'style' => $result['style'] ) );
    }

    /**
     * AJAX: Test Facebook connection.
     */
    public function ajax_test_fb() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $page_id      = sanitize_text_field( wp_unslash( $_POST['page_id'] ?? '' ) );
        $access_token = sanitize_text_field( wp_unslash( $_POST['access_token'] ?? '' ) );

        if ( empty( $page_id ) || empty( $access_token ) ) {
            wp_send_json_error( __( 'Please enter Page ID and access token.', 'wp-autopilot' ) );
        }

        $api_url = 'https://graph.facebook.com/' . FacebookSharer::FB_API_VERSION
            . '/' . $page_id . '?fields=name,id&access_token=' . urlencode( $access_token );

        $response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Network error: ', 'wp-autopilot' ) . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            $error_msg = $data['error']['message'] ?? __( 'Unknown error', 'wp-autopilot' );
            /* translators: 1: HTTP status code, 2: error message */
            wp_send_json_error( sprintf( __( 'Facebook returned error %d: %s', 'wp-autopilot' ), $status_code, $error_msg ) );
        }

        $name = $data['name'] ?? __( 'Unknown page', 'wp-autopilot' );
        wp_send_json_success( array( 'name' => $name, 'id' => $data['id'] ?? $page_id ) );
    }

    /**
     * AJAX: Save writing style for a specific author.
     */
    public function ajax_save_writing_style() {
        check_ajax_referer( 'wpa_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'wp-autopilot' ) );
        }

        $author_id = absint( $_POST['author_id'] ?? 0 );
        $style     = sanitize_textarea_field( wp_unslash( $_POST['style'] ?? '' ) );

        if ( ! $author_id ) {
            wp_send_json_error( __( 'Invalid author.', 'wp-autopilot' ) );
        }

        $writing_styles = json_decode( Settings::get( 'writing_styles', '{}' ), true );
        if ( ! is_array( $writing_styles ) ) {
            $writing_styles = array();
        }

        if ( empty( $style ) ) {
            unset( $writing_styles[ $author_id ] );
        } else {
            $writing_styles[ $author_id ] = $style;
        }

        Settings::set( 'writing_styles', wp_json_encode( $writing_styles ) );

        wp_send_json_success( array(
            'message'        => __( 'Writing style saved.', 'wp-autopilot' ),
            'writing_styles' => $writing_styles,
        ) );
    }
}

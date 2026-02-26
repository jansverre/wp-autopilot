<?php
/**
 * Plugin Name:       WP Autopilot
 * Plugin URI:        https://github.com/jansverre/wp-autopilot
 * Description:       AI-powered content automation — fetches news from RSS feeds, writes articles via OpenRouter, generates featured images with fal.ai, and publishes to WordPress on autopilot.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Jan Sverre Bauge
 * Author URI:        https://github.com/jansverre
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-autopilot
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPA_VERSION', '2.0.0' );
define( 'WPA_PLUGIN_FILE', __FILE__ );
define( 'WPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * GitHub-based auto-updates via plugin-update-checker.
 */
require_once WPA_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$wpa_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/jansverre/wp-autopilot',
    __FILE__,
    'wp-autopilot'
);

// Use GitHub releases as the source for updates.
$wpa_update_checker->getVcsApi()->enableReleaseAssets();

// GitHub API auth token avoids rate limiting (60/h → 5000/h).
// A token with NO scopes/permissions is sufficient for public repos.
$wpa_github_token = get_option( 'wpa_github_token', '' );
if ( ! empty( $wpa_github_token ) ) {
    $wpa_update_checker->setAuthentication( $wpa_github_token );
}

/**
 * Autoloader for WPAutopilot classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'WPAutopilot\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $parts    = explode( '\\', $relative );
    $class_name = array_pop( $parts );

    // Convert namespace parts to lowercase directory names
    $subdir = strtolower( implode( '/', $parts ) );

    // Convert class name: FeedFetcher -> class-feed-fetcher.php
    $file_name = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) ) . '.php';

    $file = WPA_PLUGIN_DIR . ( $subdir ? $subdir . '/' : '' ) . $file_name;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function () {
    \WPAutopilot\Includes\Activator::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
    \WPAutopilot\Includes\Deactivator::deactivate();
} );

/**
 * Bootstrap the plugin.
 */
add_action( 'plugins_loaded', function () {
    // Load translations.
    load_plugin_textdomain( 'wp-autopilot', false, dirname( WPA_PLUGIN_BASENAME ) . '/languages' );

    // Load settings helper early.
    \WPAutopilot\Includes\Settings::init();

    // Seamless DB upgrades (new tables, new defaults) without reactivation.
    \WPAutopilot\Includes\Activator::maybe_upgrade();

    // Register cron.
    $cron = new \WPAutopilot\Includes\Cron();
    $cron->register();

    // Clean up internal links index when a post is deleted or trashed.
    add_action( 'before_delete_post', function ( $post_id ) {
        $links = new \WPAutopilot\Includes\InternalLinks();
        $links->remove_article( $post_id );
    } );
    add_action( 'wp_trash_post', function ( $post_id ) {
        $links = new \WPAutopilot\Includes\InternalLinks();
        $links->remove_article( $post_id );
    } );

    // Re-add to index if a post is untrashed.
    add_action( 'untrashed_post', function ( $post_id ) {
        $post = get_post( $post_id );
        if ( $post && $post->post_type === 'post' ) {
            $links = new \WPAutopilot\Includes\InternalLinks();
            $links->add_article( $post->ID, $post->post_title, get_permalink( $post->ID ), $post->post_content );
        }
    } );

    // Facebook sharing for scheduled posts (future → publish, Pro only).
    add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
        if ( $new_status !== 'publish' || $old_status !== 'future' ) {
            return;
        }

        if ( ! \WPAutopilot\Includes\License::is_pro() ) {
            return;
        }

        if ( ! \WPAutopilot\Includes\Settings::get( 'fb_enabled' ) ) {
            return;
        }

        // Check that this is an autopilot article (exists in wpa_seen_articles).
        global $wpdb;
        $seen_table = $wpdb->prefix . 'wpa_seen_articles';
        $is_autopilot = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$seen_table} WHERE post_id = %d",
            $post->ID
        ) );

        if ( ! $is_autopilot ) {
            return;
        }

        // Prevent double sharing.
        if ( get_post_meta( $post->ID, '_wpa_fb_shared', true ) ) {
            return;
        }

        $article = array(
            'title'   => $post->post_title,
            'excerpt' => $post->post_excerpt ?: wp_trim_words( $post->post_content, 30 ),
        );

        $fb = new \WPAutopilot\Includes\FacebookSharer();
        $fb->share( $post->ID, $article, (int) $post->post_author );
    }, 10, 3 );

    // Admin panel.
    if ( is_admin() ) {
        $admin = new \WPAutopilot\Admin\Admin();
        $admin->register();
    }
} );

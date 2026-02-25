<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPAutopilot\Includes\Settings;
use WPAutopilot\Includes\Logger;
use WPAutopilot\Includes\CostTracker;

$settings   = Settings::all();
$next_run   = wp_next_scheduled( 'wpa_run_autopilot' );
$logs       = Logger::get_latest( 50 );

global $wpdb;
$articles_today = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpa_seen_articles WHERE post_id IS NOT NULL AND created_at >= %s",
        current_time( 'Y-m-d' ) . ' 00:00:00'
    )
);
$total_articles = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wpa_seen_articles WHERE post_id IS NOT NULL"
);
$indexed_links = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wpa_internal_links"
);

// Cost summary.
$cost_summary = CostTracker::get_summary();
$article_costs = CostTracker::get_article_costs( 20 );
?>

<?php include WPA_PLUGIN_DIR . 'admin/partials/header.php'; ?>

<!-- Status Cards -->
<div class="wpa-status-cards">
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Autopilot', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">
            <span class="wpa-status-badge <?php echo $settings['enabled'] ? 'wpa-active' : 'wpa-inactive'; ?>">
                <?php echo $settings['enabled'] ? esc_html__( 'Enabled', 'wp-autopilot' ) : esc_html__( 'Disabled', 'wp-autopilot' ); ?>
            </span>
        </p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Next Run', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">
            <?php
            if ( $next_run ) {
                echo esc_html( date_i18n( 'j. M Y H:i', $next_run ) );
            } else {
                esc_html_e( 'Not scheduled', 'wp-autopilot' );
            }
            ?>
        </p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Articles Today', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value"><?php echo esc_html( $articles_today . ' / ' . $settings['max_per_day'] ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Total Published', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value"><?php echo esc_html( $total_articles ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Indexed Articles', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value"><?php echo esc_html( $indexed_links ); ?></p>
    </div>
</div>

<!-- Cost Cards -->
<div class="wpa-status-cards">
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Cost Today', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">$<?php echo esc_html( number_format( $cost_summary['cost_today'], 4 ) ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Cost 7 Days', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">$<?php echo esc_html( number_format( $cost_summary['cost_7d'], 4 ) ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Cost 30 Days', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">$<?php echo esc_html( number_format( $cost_summary['cost_30d'], 4 ) ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Avg. per Article', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value">$<?php echo esc_html( number_format( $cost_summary['avg_per_article'], 4 ) ); ?></p>
    </div>
    <div class="wpa-card">
        <h3><?php esc_html_e( 'Total Tokens', 'wp-autopilot' ); ?></h3>
        <p class="wpa-card-value"><?php echo esc_html( number_format( $cost_summary['tokens_in_total'] + $cost_summary['tokens_out_total'] ) ); ?></p>
    </div>
</div>

<!-- Actions -->
<div class="wpa-section">
    <h2><?php esc_html_e( 'Actions', 'wp-autopilot' ); ?></h2>
    <p>
        <button type="button" id="wpa-run-now" class="button button-primary">
            <?php esc_html_e( 'Run Autopilot Now', 'wp-autopilot' ); ?>
        </button>
        <button type="button" id="wpa-reindex" class="button">
            <?php esc_html_e( 'Re-index Internal Links', 'wp-autopilot' ); ?>
        </button>
        <span id="wpa-action-spinner" class="spinner"></span>
    </p>
    <p id="wpa-action-message" class="wpa-message"></p>
</div>

<!-- Cost Table -->
<?php if ( ! empty( $article_costs ) ) : ?>
<div class="wpa-section">
    <h2><?php esc_html_e( 'Cost per Article (last 20)', 'wp-autopilot' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 15%;"><?php esc_html_e( 'Time', 'wp-autopilot' ); ?></th>
                <th><?php esc_html_e( 'Article', 'wp-autopilot' ); ?></th>
                <th style="width: 10%;"><?php esc_html_e( 'Types', 'wp-autopilot' ); ?></th>
                <th style="width: 10%;"><?php esc_html_e( 'Tokens In', 'wp-autopilot' ); ?></th>
                <th style="width: 10%;"><?php esc_html_e( 'Tokens Out', 'wp-autopilot' ); ?></th>
                <th style="width: 10%;"><?php esc_html_e( 'Cost', 'wp-autopilot' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $article_costs as $cost ) :
                $post_title = get_the_title( $cost['post_id'] );
                if ( empty( $post_title ) ) {
                    $post_title = '#' . $cost['post_id'];
                }
            ?>
                <tr>
                    <td><?php echo esc_html( $cost['created_at'] ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $cost['post_id'] ) ); ?>">
                            <?php echo esc_html( mb_strimwidth( $post_title, 0, 60, '...' ) ); ?>
                        </a>
                    </td>
                    <td><small><?php echo esc_html( $cost['types'] ); ?></small></td>
                    <td><?php echo esc_html( number_format( (int) $cost['tokens_in'] ) ); ?></td>
                    <td><?php echo esc_html( number_format( (int) $cost['tokens_out'] ) ); ?></td>
                    <td>$<?php echo esc_html( number_format( (float) $cost['cost_usd'], 4 ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Log -->
<div class="wpa-section">
    <h2><?php esc_html_e( 'Log (last 50 entries)', 'wp-autopilot' ); ?></h2>
    <button type="button" id="wpa-refresh-log" class="button" style="margin-bottom: 10px;"><?php esc_html_e( 'Refresh Log', 'wp-autopilot' ); ?></button>
    <table class="wp-list-table widefat fixed striped" id="wpa-log-table">
        <thead>
            <tr>
                <th style="width: 15%;"><?php esc_html_e( 'Time', 'wp-autopilot' ); ?></th>
                <th style="width: 8%;"><?php esc_html_e( 'Level', 'wp-autopilot' ); ?></th>
                <th><?php esc_html_e( 'Message', 'wp-autopilot' ); ?></th>
                <th style="width: 20%;"><?php esc_html_e( 'Context', 'wp-autopilot' ); ?></th>
            </tr>
        </thead>
        <tbody id="wpa-log-body">
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e( 'No log entries yet.', 'wp-autopilot' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['created_at'] ); ?></td>
                        <td>
                            <span class="wpa-log-level wpa-log-<?php echo esc_attr( $log['level'] ); ?>">
                                <?php echo esc_html( $log['level'] ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log['message'] ); ?></td>
                        <td><small><?php echo esc_html( mb_strimwidth( $log['context'] ?? '', 0, 100, '...' ) ); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include WPA_PLUGIN_DIR . 'admin/partials/footer.php'; ?>

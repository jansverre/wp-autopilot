<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPAutopilot\Includes\Settings;
use WPAutopilot\Includes\Logger;

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
?>

<?php include WPA_PLUGIN_DIR . 'admin/partials/header.php'; ?>

<!-- Status Cards -->
<div class="wpa-status-cards">
    <div class="wpa-card">
        <h3>Autopilot</h3>
        <p class="wpa-card-value">
            <span class="wpa-status-badge <?php echo $settings['enabled'] ? 'wpa-active' : 'wpa-inactive'; ?>">
                <?php echo $settings['enabled'] ? 'Aktivert' : 'Deaktivert'; ?>
            </span>
        </p>
    </div>
    <div class="wpa-card">
        <h3>Neste kjøring</h3>
        <p class="wpa-card-value">
            <?php
            if ( $next_run ) {
                echo esc_html( date_i18n( 'j. M Y H:i', $next_run ) );
            } else {
                echo 'Ikke planlagt';
            }
            ?>
        </p>
    </div>
    <div class="wpa-card">
        <h3>Artikler i dag</h3>
        <p class="wpa-card-value"><?php echo esc_html( $articles_today . ' / ' . $settings['max_per_day'] ); ?></p>
    </div>
    <div class="wpa-card">
        <h3>Totalt publisert</h3>
        <p class="wpa-card-value"><?php echo esc_html( $total_articles ); ?></p>
    </div>
    <div class="wpa-card">
        <h3>Indekserte artikler</h3>
        <p class="wpa-card-value"><?php echo esc_html( $indexed_links ); ?></p>
    </div>
</div>

<!-- Actions -->
<div class="wpa-section">
    <h2>Handlinger</h2>
    <p>
        <button type="button" id="wpa-run-now" class="button button-primary">
            Kjør autopilot nå
        </button>
        <button type="button" id="wpa-reindex" class="button">
            Re-indekser interne lenker
        </button>
        <span id="wpa-action-spinner" class="spinner"></span>
    </p>
    <p id="wpa-action-message" class="wpa-message"></p>
</div>

<!-- Log -->
<div class="wpa-section">
    <h2>Logg (siste 50 rader)</h2>
    <button type="button" id="wpa-refresh-log" class="button" style="margin-bottom: 10px;">Oppdater logg</button>
    <table class="wp-list-table widefat fixed striped" id="wpa-log-table">
        <thead>
            <tr>
                <th style="width: 15%;">Tidspunkt</th>
                <th style="width: 8%;">Nivå</th>
                <th>Melding</th>
                <th style="width: 20%;">Kontekst</th>
            </tr>
        </thead>
        <tbody id="wpa-log-body">
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="4">Ingen logg-innslag ennå.</td>
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

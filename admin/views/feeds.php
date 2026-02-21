<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPAutopilot\Includes\Settings;

$feeds = json_decode( Settings::get( 'feeds', '[]' ), true );
if ( ! is_array( $feeds ) ) {
    $feeds = array();
}
?>

<?php include WPA_PLUGIN_DIR . 'admin/partials/header.php'; ?>

<div class="wpa-section">
    <h2>Legg til feed</h2>
    <div class="wpa-add-feed-form">
        <input type="text" id="wpa-feed-name" placeholder="Navn (valgfritt)" class="regular-text">
        <input type="url" id="wpa-feed-url" placeholder="https://example.com/feed" class="regular-text" required>
        <button type="button" id="wpa-add-feed" class="button button-primary">Legg til</button>
        <span id="wpa-feed-spinner" class="spinner"></span>
    </div>
    <p id="wpa-feed-message" class="wpa-message"></p>
</div>

<div class="wpa-section">
    <h2>Aktive feeds</h2>
    <table class="wp-list-table widefat fixed striped" id="wpa-feeds-table">
        <thead>
            <tr>
                <th style="width: 25%;">Navn</th>
                <th>URL</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 15%;">Handlinger</th>
            </tr>
        </thead>
        <tbody id="wpa-feeds-body">
            <?php if ( empty( $feeds ) ) : ?>
                <tr class="wpa-no-feeds">
                    <td colspan="4">Ingen feeds lagt til ennå.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $feeds as $feed ) : ?>
                    <tr data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
                        <td><?php echo esc_html( $feed['name'] ); ?></td>
                        <td><code><?php echo esc_html( $feed['url'] ); ?></code></td>
                        <td>
                            <span class="wpa-status-badge <?php echo $feed['active'] ? 'wpa-active' : 'wpa-inactive'; ?>">
                                <?php echo $feed['active'] ? 'Aktiv' : 'Inaktiv'; ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button wpa-toggle-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
                                <?php echo $feed['active'] ? 'Deaktiver' : 'Aktiver'; ?>
                            </button>
                            <button type="button" class="button wpa-delete-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">Slett</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include WPA_PLUGIN_DIR . 'admin/partials/footer.php'; ?>

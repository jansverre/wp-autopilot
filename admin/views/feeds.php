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
    <h2><?php esc_html_e( 'Add Feed', 'wp-autopilot' ); ?></h2>
    <div class="wpa-add-feed-form">
        <input type="text" id="wpa-feed-name" placeholder="<?php esc_attr_e( 'Name (optional)', 'wp-autopilot' ); ?>" class="regular-text">
        <input type="url" id="wpa-feed-url" placeholder="https://example.com/feed" class="regular-text" required>
        <button type="button" id="wpa-add-feed" class="button button-primary"><?php esc_html_e( 'Add', 'wp-autopilot' ); ?></button>
        <span id="wpa-feed-spinner" class="spinner"></span>
    </div>
    <p id="wpa-feed-message" class="wpa-message"></p>
</div>

<div class="wpa-section">
    <h2><?php esc_html_e( 'Active Feeds', 'wp-autopilot' ); ?></h2>
    <table class="wp-list-table widefat fixed striped" id="wpa-feeds-table">
        <thead>
            <tr>
                <th style="width: 25%;"><?php esc_html_e( 'Name', 'wp-autopilot' ); ?></th>
                <th><?php esc_html_e( 'URL', 'wp-autopilot' ); ?></th>
                <th style="width: 10%;"><?php esc_html_e( 'Status', 'wp-autopilot' ); ?></th>
                <th style="width: 15%;"><?php esc_html_e( 'Actions', 'wp-autopilot' ); ?></th>
            </tr>
        </thead>
        <tbody id="wpa-feeds-body">
            <?php if ( empty( $feeds ) ) : ?>
                <tr class="wpa-no-feeds">
                    <td colspan="4"><?php esc_html_e( 'No feeds added yet.', 'wp-autopilot' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $feeds as $feed ) : ?>
                    <tr data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
                        <td><?php echo esc_html( $feed['name'] ); ?></td>
                        <td><code><?php echo esc_html( $feed['url'] ); ?></code></td>
                        <td>
                            <span class="wpa-status-badge <?php echo $feed['active'] ? 'wpa-active' : 'wpa-inactive'; ?>">
                                <?php echo $feed['active'] ? esc_html__( 'Active', 'wp-autopilot' ) : esc_html__( 'Inactive', 'wp-autopilot' ); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button wpa-toggle-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
                                <?php echo $feed['active'] ? esc_html__( 'Deactivate', 'wp-autopilot' ) : esc_html__( 'Activate', 'wp-autopilot' ); ?>
                            </button>
                            <button type="button" class="button wpa-delete-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>"><?php esc_html_e( 'Delete', 'wp-autopilot' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include WPA_PLUGIN_DIR . 'admin/partials/footer.php'; ?>

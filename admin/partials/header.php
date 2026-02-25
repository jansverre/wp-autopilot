<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wpa-wrap">
    <h1>WP Autopilot</h1>
    <nav class="nav-tab-wrapper wpa-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpa-settings' ) ); ?>"
           class="nav-tab <?php echo ( $_GET['page'] ?? '' ) === 'wpa-settings' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Settings', 'wp-autopilot' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpa-feeds' ) ); ?>"
           class="nav-tab <?php echo ( $_GET['page'] ?? '' ) === 'wpa-feeds' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Feeds', 'wp-autopilot' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpa-status' ) ); ?>"
           class="nav-tab <?php echo ( $_GET['page'] ?? '' ) === 'wpa-status' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Status', 'wp-autopilot' ); ?>
        </a>
    </nav>
    <div class="wpa-content">

<?php

/**
 * Plugin Name: Storage for Woo via S3-Compatible
 * Description: Enable secure cloud storage and delivery of your digital products through S3-Compatible services for WooCommerce.
 * Version: 1.0.1
 * Author: mohammadr3z
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storage-for-woo-via-s3-compatible
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check for Composer autoload (required for Guzzle/AWS)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants
if (!defined('WCS3_PLUGIN_DIR')) {
    define('WCS3_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WCS3_PLUGIN_URL')) {
    define('WCS3_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('WCS3_VERSION')) {
    define('WCS3_VERSION', '1.0.1');
}

// Load plugin classes
require_once WCS3_PLUGIN_DIR . 'includes/class-s3-config.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-s3-client.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-s3-uploader.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-s3-downloader.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-media-library.php';
require_once WCS3_PLUGIN_DIR . 'includes/class-main-plugin.php';

// Initialize plugin on plugins_loaded
add_action('plugins_loaded', function () {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Storage for WooCommerce via S3-Compatible:', 'storage-for-woo-via-s3-compatible'); ?></strong>
                    <?php esc_html_e('WooCommerce is required for this plugin to work.', 'storage-for-woo-via-s3-compatible'); ?>
                </p>
            </div>
<?php
        });
        return;
    }
    new WCS3_S3Storage();
});

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array('WCS3_Admin_Settings', 'activatePlugin'));
register_deactivation_hook(__FILE__, array('WCS3_Admin_Settings', 'deactivatePlugin'));

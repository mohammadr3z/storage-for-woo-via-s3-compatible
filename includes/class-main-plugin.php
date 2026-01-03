<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 * 
 * Initializes all plugin components and sets up hooks.
 */
class WCS3_S3Storage
{
    private $settings;
    private $media_library;
    private $downloader;
    private $uploader;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize plugin components
     */
    private function init()
    {
        add_action('admin_notices', array($this, 'showConfigurationNotice'));

        // Initialize components
        $this->settings = new WCS3_Admin_Settings();
        $this->media_library = new WCS3_Media_Library();
        $this->downloader = new WCS3_S3_Downloader();
        $this->uploader = new WCS3_S3_Uploader();
    }

    /**
     * Show admin notice if S3 is not configured
     */
    public function showConfigurationNotice()
    {
        if (!is_admin()) {
            return;
        }

        // Don't show on S3 settings page itself
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'wc-settings') !== false) {
            return;
        }

        $config = new WCS3_S3_Config();

        // Show notice if not configured
        if (!$config->isConfigured()) {
            $settings_url = admin_url('admin.php?page=wc-settings&tab=wcs3_s3');
?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Storage for WooCommerce via S3-Compatible:', 'storage-for-woo-via-s3-compatible'); ?></strong>
                    <?php esc_html_e('Please configure S3 settings to start using cloud storage for your digital products.', 'storage-for-woo-via-s3-compatible'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Configure S3', 'storage-for-woo-via-s3-compatible'); ?>
                    </a>
                </p>
            </div>
<?php
        }
    }

    /**
     * Get plugin version
     * @return string
     */
    public function getVersion()
    {
        return WCS3_VERSION;
    }
}

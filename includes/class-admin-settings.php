<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3-Compatible Admin Settings for WooCommerce
 * 
 * Integrates S3 configuration with WooCommerce settings panel
 * and handles credential validation.
 */
class WCS3_Admin_Settings
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new WCS3_S3_Config();
        $this->client = new WCS3_S3_Client();

        // Add WooCommerce settings tab
        add_filter('woocommerce_settings_tabs_array', array($this, 'addSettingsTab'), 50);
        add_action('woocommerce_settings_tabs_wcs3_s3', array($this, 'outputSettingsPage'));
        add_action('woocommerce_update_options_wcs3_s3', array($this, 'saveSettings'));

        // Enqueue admin scripts/styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));

        // Admin notices
        add_action('admin_notices', array($this, 'showAdminNotices'));
    }

    /**
     * Add WooCommerce settings tab
     * 
     * @param array $tabs
     * @return array
     */
    public function addSettingsTab($tabs)
    {
        $tabs['wcs3_s3'] = __('S3-Compatible Storage', 'storage-for-woo-via-s3-compatible');
        return $tabs;
    }

    /**
     * Output settings page
     */
    public function outputSettingsPage()
    {
        $is_connected = $this->config->isConfigured();
        $is_configured_for_buckets = $this->config->isConfiguredForBucketList();



        $bucket_options = array('' => __('-- Select Bucket --', 'storage-for-woo-via-s3-compatible'));

        // Try to fetch buckets if keys are present
        if ($is_configured_for_buckets) {
            try {
                $buckets = $this->client->getBucketsList();
                if (is_array($buckets) && !empty($buckets)) {
                    foreach ($buckets as $bucket) {
                        $bucket_options[$bucket] = $bucket;
                    }
                } else {
                    $bucket_options[''] = __('-- No buckets found --', 'storage-for-woo-via-s3-compatible');
                }
            } catch (Exception $e) {
                $bucket_options[''] = __('-- Error loading buckets --', 'storage-for-woo-via-s3-compatible');
            }
        } else {
            $bucket_options[''] = __('-- Save credentials first to load buckets --', 'storage-for-woo-via-s3-compatible');
        }

?>
        <h2><?php esc_html_e('S3-Compatible Storage Settings', 'storage-for-woo-via-s3-compatible'); ?></h2>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Access Key', 'storage-for-woo-via-s3-compatible'); ?></th>
                <td>
                    <input type="text" name="wcs3_access_key" value="<?php echo esc_attr($this->config->getAccessKey()); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Enter your S3 Access Key ID.', 'storage-for-woo-via-s3-compatible'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Secret Key', 'storage-for-woo-via-s3-compatible'); ?></th>
                <td>
                    <input type="password" name="wcs3_secret_key" value="<?php echo esc_attr($this->config->getSecretKey()); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Enter your S3 Secret Access Key.', 'storage-for-woo-via-s3-compatible'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Endpoint', 'storage-for-woo-via-s3-compatible'); ?></th>
                <td>
                    <input type="text" name="wcs3_endpoint" value="<?php echo esc_attr($this->config->getRawEndpoint()); ?>" class="regular-text" placeholder="https://" />
                    <p class="description"><?php esc_html_e('Enter your S3 compatible endpoint URL (e.g. https://s3.example.com). The URL should start with https:// for proper functionality.', 'storage-for-woo-via-s3-compatible'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Bucket', 'storage-for-woo-via-s3-compatible'); ?></th>
                <td>
                    <select name="wcs3_bucket" class="regular-text" <?php disabled(!$is_configured_for_buckets); ?>>
                        <?php foreach ($bucket_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($this->config->getBucket(), $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php if ($is_configured_for_buckets): ?>
                            <?php esc_html_e('Select your S3 Bucket.', 'storage-for-woo-via-s3-compatible'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Please save your S3 credentials first to enable bucket selection.', 'storage-for-woo-via-s3-compatible'); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Link Expiration Time (Minutes)', 'storage-for-woo-via-s3-compatible'); ?></th>
                <td>
                    <input type="number" name="wcs3_link_expiration_time" value="<?php echo esc_attr($this->config->getLinkExpirationTime()); ?>" class="small-text" min="1" max="60" />
                    <p class="description"><?php esc_html_e('The duration (in minutes) that the signed download link remains valid (Default: 5).', 'storage-for-woo-via-s3-compatible'); ?></p>
                </td>
            </tr>
            <td>
                <?php if (!$is_connected): ?>
                    <span class="wcs3-notice"><?php esc_html_e('Please enter your S3 credentials and bucket details.', 'storage-for-woo-via-s3-compatible'); ?></span>
                <?php endif; ?>
            </td>
            </tr>
        </table>
<?php
    }

    /**
     * Save settings
     */
    public function saveSettings()
    {
        // Save Access Key
        if (isset($_POST['wcs3_access_key'])) {
            update_option(WCS3_S3_Config::KEY_ACCESS_KEY, sanitize_text_field(wp_unslash($_POST['wcs3_access_key'])));
        }

        // Save Secret Key
        if (isset($_POST['wcs3_secret_key'])) {
            update_option(WCS3_S3_Config::KEY_SECRET_KEY, sanitize_text_field(wp_unslash($_POST['wcs3_secret_key'])));
        }

        // Save Endpoint
        if (isset($_POST['wcs3_endpoint'])) {
            update_option(WCS3_S3_Config::KEY_ENDPOINT, sanitize_text_field(wp_unslash($_POST['wcs3_endpoint'])));
        }

        // Region is no longer saved via UI, defaults to us-east-1 in config if needed.
        // We can optionally remove the option if it exists, or just ignore it.

        // Save Bucket
        if (isset($_POST['wcs3_bucket'])) {
            update_option(WCS3_S3_Config::KEY_BUCKET, sanitize_text_field(wp_unslash($_POST['wcs3_bucket'])));
        }

        // Save Link Expiration Time
        if (isset($_POST['wcs3_link_expiration_time'])) {
            $minutes = (int) $_POST['wcs3_link_expiration_time'];
            if ($minutes < 1) $minutes = 5;
            if ($minutes > 60) $minutes = 60;
            update_option('wcs3_link_expiration_time', $minutes);
        }
    }

    /**
     * Flush rewrite rules on plugin activation
     */
    public static function activatePlugin()
    {
        // No rewrite rules needed for S3 yet
    }

    /**
     * Flush rewrite rules on plugin deactivation
     */
    public static function deactivatePlugin()
    {
        // No rewrite rules needed for S3 yet
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        wp_enqueue_script('jquery');

        wp_register_style('wcs3-admin-settings', WCS3_PLUGIN_URL . 'assets/css/admin-settings.css', array(), WCS3_VERSION);
        wp_enqueue_style('wcs3-admin-settings');

        // Reuse standard JS if needed, or create specific
        // wp_register_script('wcs3-admin-settings', WCS3_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), WCS3_VERSION, true);
        // wp_enqueue_script('wcs3-admin-settings');
    }

    /**
     * Show admin notices
     */
    public function showAdminNotices()
    {
        // Notices can be added here if needed
    }
}

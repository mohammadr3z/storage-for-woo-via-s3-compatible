<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3-Compatible Media Library Integration for WooCommerce
 * 
 * Adds custom tabs to WordPress media uploader for browsing
 * and uploading files to S3.
 */
class WCS3_Media_Library
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new WCS3_S3_Config();
        $this->client = new WCS3_S3_Client();

        // Media library integration
        add_action('media_upload_wcs3_lib', array($this, 'registerLibraryTab'));
        add_action('admin_head', array($this, 'setupAdminJS'));

        // Enqueue styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));

        // Add S3 button to WooCommerce downloadable files
        add_action('admin_footer', array($this, 'addS3ButtonScript'));
    }

    /**
     * Add S3 tabs to media uploader
     */
    public function addS3Tabs($default_tabs)
    {
        if ($this->config->isConfigured()) {
            $default_tabs['wcs3_lib'] = esc_html__('S3 Library', 'storage-for-woo-via-s3-compatible');
        }
        return $default_tabs;
    }

    /**
     * Register S3 Library tab
     */
    public function registerLibraryTab()
    {
        $mediaCapability = apply_filters('wcs3_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            wp_die(esc_html__('You do not have permission to access S3 library.', 'storage-for-woo-via-s3-compatible'));
        }

        // Check nonce for GET requests with parameters
        if (!empty($_GET) && (isset($_GET['path']) || isset($_GET['_wpnonce']))) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-woo-via-s3-compatible'));
            }
        }

        if (!empty($_POST)) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-woo-via-s3-compatible'));
            }

            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }
        wp_iframe(array($this, 'renderLibraryTab'));
    }

    /**
     * Render S3 Library tab content
     */
    public function renderLibraryTab()
    {
        wp_enqueue_style('media');
        wp_enqueue_style('wcs3-media-library');
        wp_enqueue_style('wcs3-media-container');
        wp_enqueue_style('wcs3-upload');
        wp_enqueue_script('wcs3-media-library');
        wp_enqueue_script('wcs3-upload');

        $path = $this->getPath();

        // Check if S3 is connected
        if (!$this->config->isConfigured()) {
?>
            <div id="media-items" class="wcs3-media-container">
                <h3 class="media-title"><?php esc_html_e('S3 File Browser', 'storage-for-woo-via-s3-compatible'); ?></h3>

                <div class="wcs3-notice warning">
                    <h4><?php esc_html_e('S3 not configured', 'storage-for-woo-via-s3-compatible'); ?></h4>
                    <p><?php esc_html_e('Please configure S3 settings in the plugin settings before browsing files.', 'storage-for-woo-via-s3-compatible'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=wcs3_s3')); ?>" class="button-primary">
                            <?php esc_html_e('Configure S3 Settings', 'storage-for-woo-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php
            return;
        }

        // Try to get files
        try {
            $files = $this->client->listFiles($path);
            $connection_error = false;
        } catch (Exception $e) {
            $files = [];
            $connection_error = true;
            $this->config->debug('S3 connection error: ' . $e->getMessage());
        }
        ?>

        <?php
        // Calculate back URL for header if in subfolder
        $back_url = '';
        if (!empty($path)) {
            $parent_path = dirname($path);
            $parent_path = ($parent_path === '/' || $parent_path === '.') ? '' : $parent_path;
            $back_url = remove_query_arg(array('wcs3_success', 'wcs3_filename', 'error'));
            $back_url = add_query_arg(array(
                'path' => $parent_path,
                '_wpnonce' => wp_create_nonce('media-form')
            ), $back_url);
        }
        ?>
        <div style="width: inherit;" id="media-items">
            <div class="wcs3-header-row">
                <h3 class="media-title"><?php esc_html_e('Select a file from S3', 'storage-for-woo-via-s3-compatible'); ?></h3>
                <div class="wcs3-header-buttons">
                    <button type="button" class="button button-primary" id="wcs3-toggle-upload">
                        <?php esc_html_e('Upload File', 'storage-for-woo-via-s3-compatible'); ?>
                    </button>
                </div>
            </div>

            <?php if ($connection_error) { ?>
                <div class="wcs3-notice warning">
                    <h4><?php esc_html_e('Connection Error', 'storage-for-woo-via-s3-compatible'); ?></h4>
                    <p><?php esc_html_e('Unable to connect to S3.', 'storage-for-woo-via-s3-compatible'); ?></p>
                    <p><?php esc_html_e('Please check your S3 configuration settings and try again.', 'storage-for-woo-via-s3-compatible'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=wcs3_s3')); ?>" class="button-primary">
                            <?php esc_html_e('Check Settings', 'storage-for-woo-via-s3-compatible'); ?>
                        </a>
                    </p>
                </div>
            <?php } elseif (!$connection_error) { ?>

                <div class="wcs3-breadcrumb-nav">
                    <div class="wcs3-nav-group">
                        <?php if (!empty($back_url)) { ?>
                            <a href="<?php echo esc_url($back_url); ?>" class="wcs3-nav-back" title="<?php esc_attr_e('Go Back', 'storage-for-woo-via-s3-compatible'); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </a>
                        <?php } else { ?>
                            <span class="wcs3-nav-back disabled">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </span>
                        <?php } ?>

                        <div class="wcs3-breadcrumbs">
                            <?php
                            $bucket_name = $this->config->getBucket();

                            if (!empty($path)) {
                                // Build breadcrumb navigation
                                $path_parts = explode('/', trim($path, '/'));
                                $breadcrumb_path = '';
                                $breadcrumb_links = array();

                                // Root link
                                $root_url = remove_query_arg(array('path', 'wcs3_success', 'wcs3_filename', 'error'));
                                $root_url = add_query_arg(array('_wpnonce' => wp_create_nonce('media-form')), $root_url);
                                $breadcrumb_links[] = '<a href="' . esc_url($root_url) . '">' . esc_html($bucket_name) . '</a>';

                                // Build path links
                                foreach ($path_parts as $index => $part) {
                                    $breadcrumb_path .= '/' . $part;
                                    if ($index === count($path_parts) - 1) {
                                        $breadcrumb_links[] = '<span class="current">' . esc_html($part) . '</span>';
                                    } else {
                                        $folder_url = remove_query_arg(array('wcs3_success', 'wcs3_filename', 'error'));
                                        $folder_url = add_query_arg(array(
                                            'path' => $breadcrumb_path,
                                            '_wpnonce' => wp_create_nonce('media-form')
                                        ), $folder_url);
                                        $breadcrumb_links[] = '<a href="' . esc_url($folder_url) . '">' . esc_html($part) . '</a>';
                                    }
                                }

                                echo wp_kses(implode(' <span class="sep">/</span> ', $breadcrumb_links), array(
                                    'a' => array('href' => array()),
                                    'span' => array('class' => array())
                                ));
                            } else {
                                echo '<span class="current">' . esc_html($bucket_name) . '</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <?php if (!empty($files)) { ?>
                        <div class="wcs3-search-inline">
                            <input type="search"
                                id="wcs3-file-search"
                                class="wcs3-search-input"
                                placeholder="<?php esc_attr_e('Search files...', 'storage-for-woo-via-s3-compatible'); ?>">
                        </div>
                    <?php } ?>
                </div>



                <?php
                // Upload form integrated into Library
                $successFlag = filter_input(INPUT_GET, 'wcs3_success', FILTER_SANITIZE_NUMBER_INT);
                $errorMsg = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($errorMsg) {
                    $this->config->debug('Upload error: ' . $errorMsg);
                ?>
                    <div class="wcs3-notice warning">
                        <h4><?php esc_html_e('Error', 'storage-for-woo-via-s3-compatible'); ?></h4>
                        <p><?php esc_html_e('An error occurred during the upload process. Please try again.', 'storage-for-woo-via-s3-compatible'); ?></p>
                    </div>
                <?php
                }

                if (!empty($successFlag) && '1' == $successFlag) {
                    $savedPathAndFilename = filter_input(INPUT_GET, 'wcs3_filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $savedPathAndFilename = sanitize_text_field($savedPathAndFilename);
                    $lastSlashPos = strrpos($savedPathAndFilename, '/');
                    $savedFilename = $lastSlashPos !== false ? substr($savedPathAndFilename, $lastSlashPos + 1) : $savedPathAndFilename;
                ?>
                    <div class="wcs3-notice success">
                        <h4><?php esc_html_e('Upload Successful', 'storage-for-woo-via-s3-compatible'); ?></h4>
                        <p>
                            <?php
                            /* translators: %s: Uploaded file name */
                            printf(esc_html__('File %s uploaded successfully!', 'storage-for-woo-via-s3-compatible'), '<strong>' . esc_html($savedFilename) . '</strong>');
                            ?>
                        </p>
                        <p>
                            <a href="javascript:void(0)"
                                id="wcs3_save_link"
                                class="button-primary"
                                data-wcs3-fn="<?php echo esc_attr($savedFilename); ?>"
                                data-wcs3-path="<?php echo esc_attr(ltrim($savedPathAndFilename, '/')); ?>">
                                <?php esc_html_e('Use this file in your Download', 'storage-for-woo-via-s3-compatible'); ?>
                            </a>
                        </p>
                    </div>
                <?php
                }
                ?>
                <!-- Upload Form (Hidden by default) -->
                <form enctype="multipart/form-data" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wcs3-upload-form" id="wcs3-upload-section" style="display: none;">
                    <?php wp_nonce_field('wcs3_upload', 'wcs3_nonce'); ?>
                    <input type="hidden" name="action" value="wcs3_upload" />
                    <div class="upload-field">
                        <input type="file"
                            name="wcs3_file"
                            accept=".zip,.rar,.7z,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" />
                    </div>
                    <p class="description">
                        <?php
                        /* translators: %s: Maximum file size allowed */
                        printf(esc_html__('Maximum upload file size: %s', 'storage-for-woo-via-s3-compatible'), esc_html(size_format(wp_max_upload_size())));
                        ?>
                    </p>
                    <input type="submit"
                        class="button-primary"
                        value="<?php esc_attr_e('Upload', 'storage-for-woo-via-s3-compatible'); ?>" />
                    <input type="hidden" name="wcs3_path" value="<?php echo esc_attr($path); ?>" />
                </form>

                <?php if (is_array($files) && !empty($files)) { ?>


                    <!-- File Display Table -->
                    <table class="wp-list-table widefat fixed wcs3-files-table">
                        <thead>
                            <tr>
                                <th class="column-primary" style="width: 40%;"><?php esc_html_e('File Name', 'storage-for-woo-via-s3-compatible'); ?></th>
                                <th class="column-size" style="width: 20%;"><?php esc_html_e('File Size', 'storage-for-woo-via-s3-compatible'); ?></th>
                                <th class="column-date" style="width: 25%;"><?php esc_html_e('Last Modified', 'storage-for-woo-via-s3-compatible'); ?></th>
                                <th class="column-actions" style="width: 15%;"><?php esc_html_e('Actions', 'storage-for-woo-via-s3-compatible'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort: folders first, then files
                            usort($files, function ($a, $b) {
                                if ($a['is_folder'] && !$b['is_folder']) return -1;
                                if (!$a['is_folder'] && $b['is_folder']) return 1;
                                return strcasecmp($a['name'], $b['name']);
                            });

                            foreach ($files as $file) {
                                // Handle folders
                                if ($file['is_folder']) {
                                    $folder_url = add_query_arg(array(
                                        'path' => $file['path'],
                                        '_wpnonce' => wp_create_nonce('media-form')
                                    ));
                            ?>
                                    <tr class="wcs3-folder-row">
                                        <td class="column-primary" data-label="<?php esc_attr_e('Folder Name', 'storage-for-woo-via-s3-compatible'); ?>">
                                            <a href="<?php echo esc_url($folder_url); ?>" class="folder-link">
                                                <span class="dashicons dashicons-category"></span>
                                                <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                            </a>
                                        </td>
                                        <td class="column-size">—</td>
                                        <td class="column-date">—</td>
                                        <td class="column-actions">
                                            <a href="<?php echo esc_url($folder_url); ?>" class="button-secondary button-small">
                                                <?php esc_html_e('Open', 'storage-for-woo-via-s3-compatible'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    continue;
                                }

                                // Handle files
                                $file_size = $this->formatFileSize($file['size']);
                                $last_modified = !empty($file['modified']) ? $this->formatHumanDate($file['modified']) : '—';
                                ?>
                                <tr>
                                    <td class="column-primary" data-label="<?php esc_attr_e('File Name', 'storage-for-woo-via-s3-compatible'); ?>">
                                        <div class="wcs3-file-display">
                                            <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="column-size" data-label="<?php esc_attr_e('File Size', 'storage-for-woo-via-s3-compatible'); ?>">
                                        <span class="file-size"><?php echo esc_html($file_size); ?></span>
                                    </td>
                                    <td class="column-date" data-label="<?php esc_attr_e('Last Modified', 'storage-for-woo-via-s3-compatible'); ?>">
                                        <span class="file-date"><?php echo esc_html($last_modified); ?></span>
                                    </td>
                                    <td class="column-actions" data-label="<?php esc_attr_e('Actions', 'storage-for-woo-via-s3-compatible'); ?>">
                                        <?php
                                        $file_path = ltrim($file['path'], '/');
                                        $full_uri = $this->config->getUrlPrefix() . $file_path;
                                        ?>
                                        <a class="save-wcs3-file button-secondary button-small"
                                            href="javascript:void(0)"
                                            data-wcs3-filename="<?php echo esc_attr($file['name']); ?>"
                                            data-wcs3-link="<?php echo esc_attr($full_uri); ?>">
                                            <?php esc_html_e('Select File', 'storage-for-woo-via-s3-compatible'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <div class="wcs3-notice info" style="margin-top: 15px;">
                        <p><?php esc_html_e('This folder is empty. Use the upload form above to add files.', 'storage-for-woo-via-s3-compatible'); ?></p>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
<?php
    }


    /**
     * Setup admin JavaScript
     */
    public function setupAdminJS()
    {
        wp_enqueue_script('wcs3-admin-upload-buttons');
    }

    /**
     * Get current path from GET param
     */
    private function getPath()
    {
        $mediaCapability = apply_filters('wcs3_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            return '';
        }

        if (!empty($_GET['path'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-woo-via-s3-compatible'));
            }
        }

        $path = !empty($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';

        // Prevent directory traversal
        if (strpos($path, '..') !== false) {
            return '';
        }

        return $path;
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($size)
    {
        if ($size == 0) return '0 B';

        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $power = floor(log($size, 1024));

        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Format date in human readable format
     */
    private function formatHumanDate($date)
    {
        try {
            $timestamp = strtotime($date);
            if ($timestamp) {
                return date_i18n('j F Y', $timestamp);
            }
        } catch (Exception $e) {
            // Ignore date formatting errors
        }
        return $date;
    }

    /**
     * Enqueue CSS styles and JS scripts
     */
    public function enqueueStyles()
    {
        // Register styles
        wp_register_style('wcs3-media-library', WCS3_PLUGIN_URL . 'assets/css/s3-media-library.css', array(), WCS3_VERSION);
        wp_register_style('wcs3-upload', WCS3_PLUGIN_URL . 'assets/css/s3-upload.css', array(), WCS3_VERSION);
        wp_register_style('wcs3-media-container', WCS3_PLUGIN_URL . 'assets/css/s3-media-container.css', array(), WCS3_VERSION);
        wp_register_style('wcs3-modal', WCS3_PLUGIN_URL . 'assets/css/s3-modal.css', array('dashicons'), WCS3_VERSION);
        wp_register_style('wcs3-browse-button', WCS3_PLUGIN_URL . 'assets/css/s3-browse-button.css', array(), WCS3_VERSION);

        // Register scripts
        wp_register_script('wcs3-media-library', WCS3_PLUGIN_URL . 'assets/js/s3-media-library.js', array('jquery'), WCS3_VERSION, true);
        wp_register_script('wcs3-upload', WCS3_PLUGIN_URL . 'assets/js/s3-upload.js', array('jquery'), WCS3_VERSION, true);
        wp_register_script('wcs3-admin-upload-buttons', WCS3_PLUGIN_URL . 'assets/js/admin-upload-buttons.js', array('jquery'), WCS3_VERSION, true);
        wp_register_script('wcs3-modal', WCS3_PLUGIN_URL . 'assets/js/s3-modal.js', array('jquery'), WCS3_VERSION, true);
        wp_register_script('wcs3-browse-button', WCS3_PLUGIN_URL . 'assets/js/s3-browse-button.js', array('jquery', 'wcs3-modal'), WCS3_VERSION, true);

        // Localize scripts
        wp_localize_script('wcs3-media-library', 'wcs3_i18n', array(
            'file_selected_success' => esc_html__('File selected successfully!', 'storage-for-woo-via-s3-compatible'),
            'file_selected_error' => esc_html__('Error selecting file. Please try again.', 'storage-for-woo-via-s3-compatible')
        ));

        wp_add_inline_script('wcs3-media-library', 'var wcs3_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');

        wp_localize_script('wcs3-upload', 'wcs3_i18n', array(
            'file_size_too_large' => esc_html__('File size too large. Maximum allowed size is', 'storage-for-woo-via-s3-compatible')
        ));

        wp_add_inline_script('wcs3-upload', 'var wcs3_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
        wp_add_inline_script('wcs3-upload', 'var wcs3_max_upload_size = ' . wp_json_encode(wp_max_upload_size()) . ';', 'before');

        wp_add_inline_script('wcs3-admin-upload-buttons', 'var wcs3_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
    }

    /**
     * Add S3 button script to WooCommerce product pages
     */
    public function addS3ButtonScript()
    {
        global $pagenow, $typenow;

        // Only on product edit pages
        if (!($pagenow === 'post.php' || $pagenow === 'post-new.php') || $typenow !== 'product') {
            return;
        }

        // Only if connected
        if (!$this->config->isConfigured()) {
            return;
        }

        // Enqueue modal assets
        wp_enqueue_style('wcs3-modal');
        wp_enqueue_script('wcs3-modal');

        // Enqueue browse button assets
        wp_enqueue_style('wcs3-browse-button');
        wp_enqueue_script('wcs3-browse-button');

        // Localize script with dynamic data
        $s3_url = admin_url('media-upload.php?type=wcs3_lib&tab=wcs3_lib');
        wp_localize_script('wcs3-browse-button', 'wcs3_browse_button', array(
            'modal_url'   => $s3_url,
            'modal_title' => __('S3 Library', 'storage-for-woo-via-s3-compatible'),
            'nonce'       => wp_create_nonce('media-form')
        ));

        wp_add_inline_script('wcs3-browse-button', 'var wcs3_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3 Uploader for WooCommerce
 * 
 * Handles file uploads to S3 from WordPress admin.
 */
class WCS3_S3_Uploader
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new WCS3_S3_Config();
        $this->client = new WCS3_S3_Client();

        // Register upload handler for admin-post.php
        add_action('admin_post_wcs3_upload', array($this, 'performFileUpload'));

        // Register AJAX upload handler
        add_action('wp_ajax_wcs3_ajax_upload', array($this, 'ajaxUpload'));
    }

    /**
     * Handle file upload to S3.
     */
    public function performFileUpload()
    {
        if (!is_admin()) {
            return;
        }

        // Verify Nonce
        if (!isset($_POST['wcs3_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcs3_nonce'])), 'wcs3_upload')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-woo-via-s3-compatible'), esc_html__('Error', 'storage-for-woo-via-s3-compatible'), array('back_link' => true));
        }

        $uploadCapability = apply_filters('wcs3_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_die(esc_html__('You do not have permission to upload files to S3.', 'storage-for-woo-via-s3-compatible'));
        }

        $validation = $this->validateUpload();
        if ($validation !== true) {
            wp_die($validation);
            return;
        }

        $path = filter_input(INPUT_POST, 'wcs3_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($path)) {
            $path = '';
        }
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        if (!$this->config->isConfigured()) {
            wp_die(esc_html__('S3 is not configured. Please configure S3 settings first.', 'storage-for-woo-via-s3-compatible'), esc_html__('Error', 'storage-for-woo-via-s3-compatible'), array('back_link' => true));
        }

        try {
            $path_display = $this->processUpload($_FILES['wcs3_file'], $path);

            // Create secure redirect URL
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=wc-settings&tab=wcs3_s3');
            }

            $redirectURL = add_query_arg(
                array(
                    'wcs3_success'  => '1',
                    'wcs3_filename' => rawurlencode($path_display),
                ),
                $referer
            );
            wp_safe_redirect(esc_url_raw($redirectURL));
            exit;
        } catch (Exception $e) {
            $this->config->debug('File upload error: ' . $e->getMessage());
            wp_die(esc_html__('An error occurred while attempting to upload your file.', 'storage-for-woo-via-s3-compatible'), esc_html__('Error', 'storage-for-woo-via-s3-compatible'), array('back_link' => true));
        }
    }

    /**
     * Handle AJAX file upload.
     */
    public function ajaxUpload()
    {
        // Verify nonce without dying, handle manually
        if (!isset($_POST['wcs3_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcs3_nonce'])), 'wcs3_upload')) {
            wp_send_json_error(esc_html__('Security check failed.', 'storage-for-woo-via-s3-compatible'));
        }

        $uploadCapability = apply_filters('wcs3_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_send_json_error(esc_html__('You do not have permission to upload files to S3.', 'storage-for-woo-via-s3-compatible'));
        }

        $validation = $this->validateUpload();
        if ($validation !== true) {
            wp_send_json_error($validation);
        }

        $path = isset($_POST['wcs3_path']) ? sanitize_text_field(wp_unslash($_POST['wcs3_path'])) : '';
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        if (!$this->config->isConfigured()) {
            wp_send_json_error(esc_html__('S3 is not configured.', 'storage-for-woo-via-s3-compatible'));
        }

        try {
            $path_display = $this->processUpload($_FILES['wcs3_file'], $path);

            // Return success with file info
            wp_send_json_success(array(
                'message' => esc_html__('File uploaded successfully!', 'storage-for-woo-via-s3-compatible'),
                'filename' => basename($path_display),
                'path' => $path_display,
                // Ensure data keys match what JS expects
                'wcs3_link' => ltrim($path_display, '/')
            ));
        } catch (Exception $e) {
            $this->config->debug('AJAX upload error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Core upload processing logic
     * 
     * @param array $file_array $_FILES item
     * @param string $path Target folder path
     * @return string Uploaded file path
     * @throws Exception
     */
    private function processUpload($file_array, $path)
    {
        // Check and sanitize file name
        $filename = '';
        if (isset($file_array['name']) && !empty($file_array['name'])) {
            $filename = $path . sanitize_file_name($file_array['name']);
        } else {
            throw new Exception(esc_html__('No file selected.', 'storage-for-woo-via-s3-compatible'));
        }

        $client = $this->client->getS3Client();
        $bucket = $this->config->getBucket();

        if (!$client || !$bucket) {
            throw new Exception(esc_html__('S3 configuration is incomplete.', 'storage-for-woo-via-s3-compatible'));
        }

        // Read file content securely
        // NOTE: We now use streaming upload, so we don't need to read file content into memory.
        if (
            !isset($file_array['tmp_name']) ||
            !is_uploaded_file($file_array['tmp_name']) ||
            !is_readable($file_array['tmp_name'])
        ) {
            throw new Exception(esc_html__('Invalid file upload.', 'storage-for-woo-via-s3-compatible'));
        }

        // Upload to S3 (Passing file path which allows streaming)
        $result = $this->client->uploadFile($filename, $file_array['tmp_name']);

        if (!$result) {
            throw new Exception(esc_html__('Failed to upload file to S3.', 'storage-for-woo-via-s3-compatible'));
        }

        return $filename;
    }

    /**
     * Validate file upload.
     * @return bool|string False if valid, error message string if invalid
     */
    private function validateUpload()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in calling methods.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is server-generated path.
        if (
            !isset($_FILES['wcs3_file']) ||
            !isset($_FILES['wcs3_file']['name']) ||
            !isset($_FILES['wcs3_file']['tmp_name']) ||
            !isset($_FILES['wcs3_file']['size']) ||
            empty($_FILES['wcs3_file']['name'])
        ) {
            return esc_html__('Please select a file to upload.', 'storage-for-woo-via-s3-compatible');
        }

        if (!is_uploaded_file($_FILES['wcs3_file']['tmp_name'])) {
            return esc_html__('Invalid file upload.', 'storage-for-woo-via-s3-compatible');
        }

        if (!$this->isAllowedFileType(sanitize_file_name($_FILES['wcs3_file']['name']))) {
            return esc_html__('File type not allowed. Only safe file types are permitted.', 'storage-for-woo-via-s3-compatible');
        }

        if (!$this->validateFileContentType($_FILES['wcs3_file'])) {
            return esc_html__('File content type validation failed.', 'storage-for-woo-via-s3-compatible');
        }

        $fileSize = absint($_FILES['wcs3_file']['size']);
        $maxSize = wp_max_upload_size();
        if ($fileSize > $maxSize || $fileSize <= 0) {
            return sprintf(
                /* translators: %s: Maximum file size allowed */
                esc_html__('File size too large. Maximum allowed size is %s', 'storage-for-woo-via-s3-compatible'),
                esc_html(size_format($maxSize))
            );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return true;
    }

    /**
     * Check if file type is allowed
     * @param string $filename
     * @return bool
     */
    private function isAllowedFileType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $allowedExtensions = array(
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            'pdf',
            'doc',
            'docx',
            'txt',
            'rtf',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'mp3',
            'wav',
            'ogg',
            'flac',
            'm4a',
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'webm',
            'epub',
            'mobi',
            'azw',
            'azw3',
            'xls',
            'xlsx',
            'csv',
            'ppt',
            'pptx',
            'css',
            'js',
            'json',
            'xml'
        );

        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        $dangerousPatterns = array(
            '.php',
            '.phtml',
            '.asp',
            '.aspx',
            '.jsp',
            '.cgi',
            '.pl',
            '.py',
            '.exe',
            '.com',
            '.bat',
            '.cmd',
            '.scr',
            '.vbs',
            '.jar',
            '.sh',
            '.bash',
            '.zsh',
            '.fish',
            '.htaccess',
            '.htpasswd'
        );

        $lowerFilename = strtolower($filename);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerFilename, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate file content type (MIME type)
     * @param array $file The uploaded file array
     * @return bool
     */
    private function validateFileContentType($file)
    {
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            return false;
        }

        $filetype = wp_check_filetype_and_ext($file['tmp_name'], sanitize_file_name($file['name']));

        if (!$filetype || !isset($filetype['ext']) || !isset($filetype['type'])) {
            return false;
        }

        if (false === $filetype['ext'] || false === $filetype['type']) {
            return false;
        }

        $actualExtension = strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION));
        if ($filetype['ext'] !== $actualExtension) {
            return false;
        }

        $allowedMimeTypes = array(
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/rtf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/flac',
            'audio/x-m4a',
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/x-flv',
            'video/webm',
            'application/epub+zip',
            'application/x-mobipocket-ebook',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/css',
            'application/javascript',
            'text/javascript',
            'application/json',
            'application/xml',
            'text/xml',
        );

        $allowedMimeTypes = apply_filters('wcs3_allowed_mime_types', $allowedMimeTypes);

        if (!in_array($filetype['type'], $allowedMimeTypes, true)) {
            return false;
        }

        return true;
    }
}

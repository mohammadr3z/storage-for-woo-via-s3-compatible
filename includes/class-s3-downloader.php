<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3 Downloader for WooCommerce
 * 
 * Generates signed download links for WooCommerce downloads stored in S3.
 */
class WCS3_S3_Downloader
{
    private $client;
    private $config;


    public function __construct()
    {
        $this->config = new WCS3_S3_Config();
        $this->client = new WCS3_S3_Client();

        // Hook into WooCommerce download file path
        add_filter('woocommerce_file_download_path', array($this, 'generateUrl'), 10, 3);

        // Bypass file existence check for S3 files
        add_filter('woocommerce_downloadable_file_exists', array($this, 'bypassFileExistsCheck'), 999, 2);

        // Whitelist custom protocol so wp_kses/esc_url doesn't strip it
        add_filter('kses_allowed_protocols', array($this, 'allowCustomProtocol'), 999);
    }



    /**
     * Bypass WooCommerce file existence check for S3 files
     * 
     * @param bool $exists Whether the file exists
     * @param string $file_url The file URL
     * @return bool
     */
    public function bypassFileExistsCheck($exists, $file_url)
    {
        $match = (strpos($file_url, $this->config->getUrlPrefix()) === 0);

        if ($match) {
            return true;
        }
        return $exists;
    }

    /**
     * Whitelist wc-s3cs protocol for wp_kses/esc_url
     */
    public function allowCustomProtocol($protocols)
    {
        $protocols[] = 'wc-s3cs';
        return $protocols;
    }

    /**
     * Generate a signed S3 URL for download.
     * 
     * This method is hooked to 'woocommerce_file_download_path' filter.
     * 
     * @param string $file_path The original file URL
     * @param WC_Product $product The product object
     * @param string $download_id The download ID
     * @return string The signed download URL or original file
     */
    public function generateUrl($file_path, $product, $download_id)
    {


        // Don't generate temp links ONLY on product edit pages (to prevent saving temp links to DB)
        if (is_admin()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only context check for WooCommerce AJAX save, no data processing.
            $is_ajax_save = defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'woocommerce_save_attributes';

            if ($is_ajax_save) {
                return $file_path;
            }

            // If we are on the product edit screen, return original path
            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen && $screen->id === 'product') {
                    return $file_path;
                }
            }
        }

        if (empty($file_path)) {
            return $file_path;
        }

        // Check if this is a S3 file
        $urlPrefix = $this->config->getUrlPrefix();
        if (strpos($file_path, $urlPrefix) !== 0) {
            return $file_path;
        }

        // Extract the S3 path from the URL
        $path = substr($file_path, strlen($urlPrefix));

        if (!$this->config->isConfigured()) {
            return $file_path;
        }

        try {
            // Get signed link from S3
            $signedLink = $this->client->getSignedUrl($path);

            if ($signedLink) {
                // If this is a download request, redirect immediately
                // We must check if the 'key' param matches the current $download_id to prevent
                // redirecting the wrong file when multiple files exist for a product.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce handles download verification, this is part of the download filter chain.
                $requested_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies download permissions before this filter.
                if (isset($_GET['download_file']) && !defined('DOING_AJAX') && $requested_key === $download_id) {
                    header('Location: ' . $signedLink);
                    exit;
                }

                // Return original path so bypassFileExistsCheck works
                return $file_path;
            }

            return $file_path;
        } catch (Exception $e) {
            return $file_path;
        }
    }
}

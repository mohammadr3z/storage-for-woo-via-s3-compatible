<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3-Compatible Configuration Manager
 * 
 * Handles all S3 API configuration including Access Keys,
 * Secret Keys, Region, Endpoint, and Bucket settings.
 */
class WCS3_S3_Config
{
    // Option keys for storing configuration in WordPress database
    const KEY_ACCESS_KEY = 'wcs3_access_key';
    const KEY_SECRET_KEY = 'wcs3_secret_key';
    const KEY_REGION = 'wcs3_region';
    const KEY_ENDPOINT = 'wcs3_endpoint';
    const KEY_BUCKET = 'wcs3_bucket';

    // URL prefix for S3 file URLs in WooCommerce
    const URL_PREFIX = 'wc-s3cs://';

    /**
     * Get the URL prefix for S3 file URLs
     * 
     * @return string The URL prefix (default: 'wc-s3cs://')
     */
    public function getUrlPrefix()
    {
        return apply_filters('wcs3_url_prefix', self::URL_PREFIX);
    }

    /**
     * Get S3 Access Key
     * @return string
     */
    public function getAccessKey()
    {
        return get_option(self::KEY_ACCESS_KEY, '');
    }

    /**
     * Get S3 Secret Key
     * @return string
     */
    public function getSecretKey()
    {
        return get_option(self::KEY_SECRET_KEY, '');
    }

    /**
     * Get S3 Region
     * @return string
     */
    public function getRegion()
    {
        return get_option(self::KEY_REGION, 'us-east-1');
    }

    /**
     * Get Raw S3 Endpoint (as saved in DB)
     * @return string
     */
    public function getRawEndpoint()
    {
        return get_option(self::KEY_ENDPOINT, '');
    }

    /**
     * Get S3 Endpoint
     * @return string
     */
    public function getEndpoint()
    {
        $endpoint = get_option(self::KEY_ENDPOINT, '');

        if (empty($endpoint)) {
            return '';
        }

        // Ensure endpoint starts with https:// or http://
        if (!preg_match('/^https?:\/\//i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }

        // Parse and validate URL
        $parsed = wp_parse_url($endpoint);

        // Validate URL structure
        if (!$parsed || !isset($parsed['host'])) {
            $this->debug('Invalid endpoint URL: missing host');
            return '';
        }

        // Security: Block private IP addresses and localhost to prevent SSRF
        $host = $parsed['host'];

        // Check if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Block private and reserved IP ranges
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->debug('Security: Blocked private/reserved IP address in endpoint');
                return '';
            }
        }

        // Block localhost and local domains
        $blocked_hosts = array('localhost', '127.0.0.1', '0.0.0.0', '::1');
        if (in_array(strtolower($host), $blocked_hosts, true)) {
            $this->debug('Security: Blocked localhost in endpoint');
            return '';
        }

        // Rebuild clean URL
        $clean_endpoint = $parsed['scheme'] . '://' . $parsed['host'];

        // Add port if specified and not default
        if (isset($parsed['port'])) {
            $default_port = ($parsed['scheme'] === 'https') ? 443 : 80;
            if ($parsed['port'] != $default_port) {
                $clean_endpoint .= ':' . $parsed['port'];
            }
        }

        // Add path if exists
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $clean_endpoint .= rtrim($parsed['path'], '/');
        }

        return $clean_endpoint;
    }

    /**
     * Get S3 Bucket Name
     * @return string
     */
    public function getBucket()
    {
        return get_option(self::KEY_BUCKET, '');
    }

    /**
     * Check if S3 credentials are configured
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->getAccessKey()) &&
            !empty($this->getSecretKey()) &&
            !empty($this->getBucket());
    }

    /**
     * Check if S3 credentials are configured for listing buckets
     * @return bool
     */
    public function isConfiguredForBucketList()
    {
        return !empty($this->getAccessKey()) &&
            !empty($this->getSecretKey()) &&
            !empty($this->getEndpoint());
    }

    /**
     * Debug logging helper
     * @param mixed $log
     */
    public function debug($log)
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_array($log) || is_object($log)) {
                $message = wp_json_encode($log, JSON_UNESCAPED_UNICODE);
                if ($message !== false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[WCS3] ' . $message);
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[WCS3] ' . sanitize_text_field($log));
            }
        }
    }
    /**
     * Get link expiration time in minutes
     *
     * @return int
     */
    public function getLinkExpirationTime()
    {
        $minutes = (int) get_option('wcs3_link_expiration_time', 5);
        if ($minutes < 1) {
            $minutes = 5;
        }
        if ($minutes > 60) {
            $minutes = 60;
        }
        return $minutes;
    }
}

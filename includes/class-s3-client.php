<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * S3 API Client
 * 
 * Handles all S3 API communications using Guzzle and manual AWS Signature V4/V2.
 * Removes dependency on the official AWS SDK.
 */
class WCS3_S3_Client
{
    private $httpClient = null;
    private $config;

    public function __construct()
    {
        $this->config = new WCS3_S3_Config();
    }

    /**
     * Get HTTP Client instance
     * @return \GuzzleHttp\Client|null
     */
    public function getS3Client()
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        if (!$this->config->isConfiguredForBucketList()) {
            return null;
        }

        try {
            // Advanced settings for compatibility (Matches EDD plugin)
            $clientOptions = [
                'base_uri' => $this->config->getEndpoint(),
                'timeout'  => 30,
                'connect_timeout' => 15,
                'verify' => true,
                'http_errors' => false,
                'allow_redirects' => true,
                'curl' => [
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'storage-for-woo-via-s3-compatible/1.0',
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // IPv4 only
                    CURLOPT_TCP_NODELAY => true,
                    CURLOPT_FRESH_CONNECT => false,
                    CURLOPT_FORBID_REUSE => false
                ]
            ];

            $this->httpClient = new \GuzzleHttp\Client($clientOptions);
            return $this->httpClient;
        } catch (Exception $e) {
            $this->config->debug('Error creating HTTP client: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get list of available S3 buckets.
     * @return array
     */
    public function getBucketsList()
    {
        $client = $this->getS3Client();
        if (!$client) {
            return array();
        }

        if (!$this->config->isConfiguredForBucketList()) {
            return array();
        }

        $endpoint = $this->config->getEndpoint();

        // Try different methods
        $response = $this->tryMultipleAuthMethods($client, $endpoint, 'GET', '/', '');

        if (!$response) {
            $this->config->debug('All authentication methods failed');
            return array();
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->config->debug('Non-200 response: ' . $statusCode);
                return array();
            }

            $responseBody = $response->getBody()->getContents();

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseBody);

            if ($xml === false) {
                // Try JSON parsing fallback if some services return JSON
                $json = json_decode($responseBody, true);
                if ($json && isset($json['buckets'])) {
                    libxml_use_internal_errors(false);
                    return array_column($json['buckets'], 'name');
                }

                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = 'XML parsing error: ';
                foreach ($errors as $error) {
                    $errorMsg .= $error->message . ' ';
                }
                $this->config->debug($errorMsg);
                $this->config->debug('Response status: ' . $response->getStatusCode() . ' - XML parsing failed');

                libxml_use_internal_errors(false);
                return array();
            }
            libxml_use_internal_errors(false);

            $buckets = [];

            // Support for different XML formats
            if (isset($xml->Buckets->Bucket)) {
                foreach ($xml->Buckets->Bucket as $bucket) {
                    $buckets[] = (string)$bucket->Name;
                }
            } elseif (isset($xml->bucket)) {
                foreach ($xml->bucket as $bucket) {
                    $buckets[] = (string)$bucket->name;
                }
            } elseif (isset($xml->ListAllMyBucketsResult->Buckets->Bucket)) {
                foreach ($xml->ListAllMyBucketsResult->Buckets->Bucket as $bucket) {
                    $buckets[] = (string)$bucket->Name;
                }
            }

            $this->config->debug('Found ' . count($buckets) . ' buckets');
            return $buckets;
        } catch (Exception $e) {
            $this->config->debug('Error listing buckets: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * List files in an S3 bucket folder
     * 
     * @param string $path Folder path (prefix)
     * @return array List of files
     */
    public function listFiles($path = '')
    {
        $files = [];
        // listFiles is used for logic where we already have a bucket selected.
        // EDD has listFiles and listFilesWithFolders.
        // We will adapt listFilesWithFolders logic here but filter for compatibility.

        $client = $this->getS3Client();
        $bucket = $this->config->getBucket();

        if (!$client || empty($bucket)) {
            return $files;
        }

        try {
            $endpoint = $this->config->getEndpoint();

            // Path cleanup
            $prefix = ltrim($path, '/');
            if (!empty($prefix) && substr($prefix, -1) !== '/') {
                $prefix .= '/';
            }

            // Using GET request with V4 signing primarily, but could use tryMultiple if needed.
            // EDD implementation uses listFilesWithFolders which calls makeRequestWithV4Auth or similar manually?
            // Wait, looking at EDD code, listFilesWithFolders does explicit V4 signing block.
            // It doesn't call tryMultipleAuthMethods.

            // So we stick to our robust V4 implementation, OR we can refactor listFiles to use makeRequestWithV4Auth too.
            // For consistency, let's use makeRequestWithV4Auth logic here.

            $query = 'delimiter=%2F&list-type=2';
            if (!empty($prefix)) {
                $query .= '&prefix=' . rawurlencode($prefix);
            }

            $uri = "/$bucket";

            // EDD uses rawurlencode in query params manually.
            $response = $this->makeRequestWithV4Auth($client, $endpoint, 'GET', $uri, $query);

            if (!$response || $response->getStatusCode() !== 200) {
                // Try legacy if loop
                // EDD listFilesWithFolders does not fallback.
                return $files;
            }

            $responseContent = $response->getBody()->getContents();

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseContent);

            if ($xml === false) {
                libxml_use_internal_errors(false);
                return [];
            }
            libxml_use_internal_errors(false);

            if (isset($xml->CommonPrefixes)) {
                foreach ($xml->CommonPrefixes as $prefix_item) {
                    $folderPath = (string)$prefix_item->Prefix;
                    $folderName = rtrim($folderPath, '/');
                    if (strpos($folderName, '/') !== false) {
                        $folderName = substr($folderName, strrpos($folderName, '/') + 1);
                    }

                    $files[] = [
                        'name' => $folderName,
                        'path' => (string)$prefix_item->Prefix,
                        'path_lower' => strtolower((string)$prefix_item->Prefix),
                        'size' => 0,
                        'modified' => '',
                        'is_folder' => true
                    ];
                }
            }

            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $object) {
                    $key = (string)$object->Key;
                    if (!empty($prefix) && $key === $prefix) {
                        continue;
                    }

                    $files[] = [
                        'name' => basename($key),
                        'path' => $key,
                        'path_lower' => strtolower($key),
                        'size' => (int)$object->Size,
                        'modified' => (string)$object->LastModified,
                        'is_folder' => false
                    ];
                }
            }

            return $files;
        } catch (Exception $e) {
            $this->config->debug('List files error: ' . $e->getMessage());
            return $files;
        }
    }

    /**
     * Get a signed download link for a file
     * 
     * @param string $path File key in S3
     * @return string|false Signed URL or false on failure
     */
    public function getSignedUrl($path)
    {
        if (!$this->config->isConfigured()) {
            return false;
        }

        try {
            $path = ltrim($path, '/');
            // Use dynamic expiration time from settings
            $expiry_minutes = $this->config->getLinkExpirationTime();

            $endpoint = rtrim($this->config->getEndpoint(), '/');
            $bucket = $this->config->getBucket();
            $accessKey = $this->config->getAccessKey();
            $secretKey = $this->config->getSecretKey();
            $region = $this->config->getRegion();

            $date = gmdate('Ymd\\THis\\Z');
            $shortDate = gmdate('Ymd');
            $expires = $expiry_minutes * 60;
            $service = 's3';

            $host = wp_parse_url($endpoint, PHP_URL_HOST);
            $uri = '/' . $bucket . '/' . $path;

            // Query params for signature
            $query_params = [
                'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
                'X-Amz-Credential' => $accessKey . '/' . $shortDate . '/' . $region . '/s3/aws4_request',
                'X-Amz-Date' => $date,
                'X-Amz-Expires' => $expires,
                'response-content-disposition' => 'attachment; filename="' . str_replace(array('"', "\r", "\n"), '', basename($path)) . '"',
                'X-Amz-SignedHeaders' => 'host'
            ];

            // Canonical Request
            // Encode object key segments for correct URI and Canonical URI
            $key_parts = explode('/', $path);
            $encoded_key_parts = array_map('rawurlencode', $key_parts);
            // Re-implode with /
            $encoded_object_key = implode('/', $encoded_key_parts);

            // Override URI with encoded version
            $uri = '/' . $bucket . '/' . $encoded_object_key;

            // Use the already encoded URI for canonical URI to avoid double encoding or mismatches
            $canonicalUri = $uri;

            ksort($query_params);
            $canonicalQueryString = http_build_query($query_params);
            $canonicalQueryString = str_replace('+', '%20', $canonicalQueryString);

            $canonicalHeaders = "host:$host\n";
            $signedHeaders = 'host';
            $payloadHash = 'UNSIGNED-PAYLOAD';

            $canonicalRequest = "GET\n$canonicalUri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

            // String to Sign
            $algorithm = 'AWS4-HMAC-SHA256';
            $credentialScope = "$shortDate/$region/$service/aws4_request";
            $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);

            // Signature
            $kSecret = 'AWS4' . $secretKey;
            $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
            $kRegion = hash_hmac('sha256', $region, $kDate, true);
            $kService = hash_hmac('sha256', $service, $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);

            $query_params['X-Amz-Signature'] = $signature;
            $finalQueryString = http_build_query($query_params);
            $finalQueryString = str_replace('+', '%20', $finalQueryString);

            $endpointParts = wp_parse_url($endpoint);
            $scheme = isset($endpointParts['scheme']) ? $endpointParts['scheme'] . '://' : 'https://';

            return $scheme . $host . $uri . '?' . $finalQueryString;
        } catch (Exception $e) {
            $this->config->debug('Get signed URL error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload a file to S3
     * 
     * @param string $path Destination key in S3
     * @param string $content File content
     * @return array|false File metadata or false on failure
     */
    /**
     * Upload a file to S3
     * 
     * @param string $path Destination key in S3
     * @param string $filePath Local file path
     * @return array|false File metadata or false on failure
     */
    public function uploadFile($path, $filePath)
    {
        $client = $this->getS3Client();
        $bucket = $this->config->getBucket();

        if (!$client || empty($bucket)) {
            return false;
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->config->debug('Upload file not found or unreadable: ' . $filePath);
            return false;
        }

        try {
            $path = ltrim($path, '/');
            $endpoint = $this->config->getEndpoint();
            $accessKey = $this->config->getAccessKey();
            $secretKey = $this->config->getSecretKey();
            $region = $this->config->getRegion();

            $date = gmdate('Ymd\\THis\\Z');
            $shortDate = gmdate('Ymd');
            $service = 's3';

            // Content Hash - use hash_file for memory efficiency
            $contentHash = hash_file('sha256', $filePath);
            if ($contentHash === false) {
                throw new Exception(esc_html__('Failed to generate file hash.', 'storage-for-woo-via-s3-compatible'));
            }

            // Determine Content Type
            $contentType = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($filePath);
                if ($mime) {
                    $contentType = $mime;
                }
            }

            // Fallback / Override based on extension if octet-stream
            if ($contentType === 'application/octet-stream') {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'pdf' => 'application/pdf',
                    'zip' => 'application/zip',
                    'txt' => 'text/plain',
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'json' => 'application/json',
                    'xml' => 'application/xml',
                    'mp3' => 'audio/mpeg',
                    'mp4' => 'video/mp4'
                ];
                if (isset($mimes[$ext])) {
                    $contentType = $mimes[$ext];
                }
            }

            // Encode filename for URI
            $parts = explode('/', $path);
            $encodedParts = array_map('rawurlencode', $parts);
            $encodedPath = implode('/', $encodedParts);

            $method = 'PUT';
            $canonicalUri = "/$bucket/$encodedPath";
            $host = wp_parse_url($endpoint, PHP_URL_HOST);

            $fileSize = filesize($filePath);
            if ($fileSize === false) {
                throw new Exception(esc_html__('Failed to get file size.', 'storage-for-woo-via-s3-compatible'));
            }

            $canonicalHeaders = "content-length:$fileSize\ncontent-type:$contentType\nhost:$host\nx-amz-content-sha256:$contentHash\nx-amz-date:$date\n";
            $signedHeaders = 'content-length;content-type;host;x-amz-content-sha256;x-amz-date';

            $canonicalRequest = "$method\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$contentHash";

            $algorithm = 'AWS4-HMAC-SHA256';
            $credentialScope = "$shortDate/$region/$service/aws4_request";
            $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);

            $kSecret = 'AWS4' . $secretKey;
            $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
            $kRegion = hash_hmac('sha256', $region, $kDate, true);
            $kService = hash_hmac('sha256', $service, $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);

            $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

            $requestUrl = rtrim($endpoint, '/') . "/$bucket/$encodedPath";

            // Open stream
            $stream = fopen($filePath, 'r');
            if ($stream === false) {
                throw new Exception(esc_html__('Failed to open file stream.', 'storage-for-woo-via-s3-compatible'));
            }

            $response = $client->request('PUT', $requestUrl, [
                'headers' => [
                    'Content-Type' => $contentType,
                    'Content-Length' => $fileSize,
                    'Host' => $host,
                    'X-Amz-Content-SHA256' => $contentHash,
                    'X-Amz-Date' => $date,
                    'Authorization' => $authorization
                ],
                'body' => $stream
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Exception(sprintf(
                    // translators: %1$s: Status code, %2$s: Reason phrase
                    esc_html__('S3 upload failed with status: %1$s %2$s', 'storage-for-woo-via-s3-compatible'),
                    $statusCode,
                    $response->getReasonPhrase()
                ));
            }

            return [
                'name' => basename($path),
                'path_display' => '/' . $path,
                'size' => $fileSize
            ];
        } catch (Exception $e) {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            $this->config->debug('Upload error: ' . $e->getMessage());
            return false;
        }
    }



    /**
     * Try different authentication methods for compatibility
     */
    private function tryMultipleAuthMethods($client, $endpoint, $method = 'GET', $uri = '/', $queryString = '')
    {
        // Method 1: AWS Signature V4 (Standard)
        try {
            return $this->makeRequestWithV4Auth($client, $endpoint, $method, $uri, $queryString);
        } catch (Exception $e) {
            $this->config->debug('V4 Auth failed: ' . $e->getMessage());
        }

        // Method 2: Simple Authorization Header (For simpler services or V2)
        try {
            return $this->makeRequestWithSimpleAuth($client, $endpoint, $method, $uri, $queryString);
        } catch (Exception $e) {
            $this->config->debug('Simple Auth failed: ' . $e->getMessage());
        }

        // No fallback to unauthenticated requests for security
        return false;
    }

    private function makeRequestWithV4Auth($client, $endpoint, $method, $uri, $queryString)
    {
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();
        $region = $this->config->getRegion();

        $date = gmdate('Ymd\\THis\\Z');
        $shortDate = gmdate('Ymd');
        $service = 's3';

        $host = wp_parse_url($endpoint, PHP_URL_HOST);
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\nx-amz-date:$date\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $payloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        // Note: EDD doesn't rawurlencode $uri in canonical request directly if it's passed as simple '/'.
        // But for path-style, uri is resource path.
        // We assume $uri passed here is already encoded or safe.
        // EDD passed '/' for buckets list.

        $canonicalRequest = "$method\n$uri\n$queryString\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$shortDate/$region/$service/aws4_request";
        $stringToSign = "$algorithm\n$date\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        $requestUrl = rtrim($endpoint, '/') . $uri;
        if (!empty($queryString)) {
            $requestUrl .= '?' . $queryString;
        }

        return $client->request($method, $requestUrl, [
            'headers' => [
                'Host' => $host,
                'X-Amz-Content-SHA256' => $payloadHash,
                'X-Amz-Date' => $date,
                'Authorization' => $authorization
            ]
        ]);
    }

    private function makeRequestWithSimpleAuth($client, $endpoint, $method, $uri, $queryString)
    {
        $accessKey = $this->config->getAccessKey();
        $secretKey = $this->config->getSecretKey();

        $requestUrl = rtrim($endpoint, '/') . $uri;
        if (!empty($queryString)) {
            $requestUrl .= '?' . $queryString;
        }

        // Implementation of AWS V2 / Simple Auth
        // StringToSign = HTTP-Verb + "\n" +
        // Content-MD5 + "\n" +
        // Content-Type + "\n" +
        // Date + "\n" +
        // CanonicalizedAmzHeaders +
        // CanonicalizedResource;

        // Simplified version often accepted by S3 clones for simple GETs
        // Note: hashing logic might vary. EDD uses:
        // 'Authorization' => 'AWS ' . $accessKey . ':' . base64_encode(hash_hmac('sha1', $requestUrl, $secretKey, true))
        // This is not standard AWS V2 (which signs resource path, not URL).
        // But we copy EDD's logic exactly as requested.

        return $client->request($method, $requestUrl, [
            'headers' => [
                'Authorization' => 'AWS ' . $accessKey . ':' . base64_encode(hash_hmac('sha1', $requestUrl, $secretKey, true))
            ]
        ]);
    }
}

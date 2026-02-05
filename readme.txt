=== Storage for Woo via S3-Compatible ===
Contributors: mohammadr3z
Tags: woocommerce, s3, amazon, digital downloads, cloud storage
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable secure cloud storage and delivery of your WooCommerce digital products through S3-Compatible storage.

== Description ==

Storage for WooCommerce via S3-Compatible is a powerful extension for WooCommerce that allows you to store and deliver your digital products using Amazon S3 or any S3-compatible storage service. This plugin provides seamless integration with S3 APIs, featuring signed URLs with configurable expiration times.

= Key Features =

* **S3-Compatible Integration**: Store your digital products in Amazon S3, Wasabi, MinIO, DigitalOcean Spaces, Backblaze B2, and more
* **Signed Download Links**: Generates secure signed URLs with configurable expiration (1-60 minutes)
* **Easy File Management**: Upload files directly to S3 through WordPress admin
* **Media Library Integration**: Browse and select files from your S3 bucket within WordPress
* **Folder Support**: Navigate and organize files in folders (prefixes)
* **Security First**: Built with WordPress security best practices
* **Developer Friendly**: Clean, well-documented code with hooks and filters

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/storage-for-woo-via-s3-compatible` directory, or install the plugin through the WordPress plugins screen directly.
2. Make sure you have WooCommerce plugin installed and activated.
3. Run `composer install` in the plugin directory if installing from source (not needed for release versions).
4. Activate the plugin through the 'Plugins' screen in WordPress.
5. Navigate to WooCommerce > Settings > S3-Compatible to configure the plugin.

== Configuration ==

1. Go to WooCommerce > Settings > S3-Compatible
2. Enter your S3 credentials:
   * Access Key
   * Secret Key
   * Bucket Name
   * Endpoint URL (e.g., https://s3.example.com)
3. Set the Link Expiration time (1-60 minutes)
4. Save the settings

== Usage ==

= Browsing and Selecting Files =

1. When creating or editing a downloadable product in WooCommerce
2. Click "Browse S3" button next to the file URL field
3. Browse your S3 bucket using the folder navigation
4. Use the breadcrumb navigation bar to quickly jump to parent folders
5. Use the search box in the header to filter files by name
6. Click "Select File" to use an existing file for your download

= Uploading New Files =

1. In the S3 browser, click the "Upload File" button in the header row
2. The upload form will appear above the file list
3. Choose your file and click "Upload"
4. After a successful upload, the file URL will be automatically set with the S3 prefix
5. Click the button again to hide the upload form

== Frequently Asked Questions ==

= How secure are the download links? =

The plugin generates signed URLs that are only valid for the configured duration (1-60 minutes). These links are generated on-demand when a customer initiates a download, ensuring that each download session gets a fresh, time-limited URL.

= Can I customize the link expiration time? =

Yes! Unlike some other cloud storage services, S3 allows you to configure the link expiration time. Go to WooCommerce > Settings > S3 Storage and set your preferred duration (1-60 minutes).

= What file types are supported for upload? =

The plugin supports safe file types including:
* Archives: ZIP, RAR, 7Z, TAR, GZ
* Documents: PDF, DOC, DOCX, TXT, RTF, XLS, XLSX, CSV, PPT, PPTX
* Images: JPG, JPEG, PNG, GIF, WEBP
* Audio: MP3, WAV, OGG, FLAC, M4A
* Video: MP4, AVI, MOV, WMV, FLV, WEBM
* E-books: EPUB, MOBI, AZW, AZW3
* Web files: CSS, JS, JSON, XML

Dangerous file types (executables, scripts) are automatically blocked for security.

= Which S3 providers are supported? =

Any S3-compatible storage service, including:
* Amazon S3
* DigitalOcean Spaces
* Linode Object Storage
* Wasabi
* Backblaze B2 (with S3-compatible API)
* Cloudflare R2
* MinIO
* Storj
* ArvanCloud
* Hetzner Object Storage
* And many others

= Can I customize the URL prefix for S3 files? =

Yes, developers can customize the URL prefix using the `wcs3_url_prefix` filter. Add this code to your theme's functions.php:

`
function customize_s3_url_prefix($prefix) {
    return 'wc-myprefix://'; // Change to your preferred prefix
}
add_filter('wcs3_url_prefix', 'customize_s3_url_prefix');
`

= Can I customize the allowed file types (MIME types)? =

Yes, developers can customize the allowed MIME types using the `wcs3_allowed_mime_types` filter.

== Screenshots ==

1. Plugin Settings
2. Browse button for link selection
3. Library popup display
4. Upload form display

== Changelog ==

= 1.0.5 =
* Improved: UI styles and enhanced layout consistency for better harmony.
* Improved: Comprehensive code improvements and stability optimizations.
* Added: Skeleton loader with shimmer animation for better UX while loading S3 browser modal.

= 1.0.4 =
* Improved WordPress coding standards compliance

= 1.0.3 =
* Added proper PHPCS annotations for nonce verification and input sanitization

= 1.0.2 =
* Added force download for files - browser now downloads files instead of opening them inline

= 1.0.1 =
* Improved file browser to open directly in the folder of the existing file
* Fixed issue with remembering last folder location
* Use wp_enqueue commands: Replaced inline <style> and <script> in includes/class-media-library.php (admin media library)

= 1.0.0 =
* Initial release
* S3-compatible storage integration
* Signed download link generation with configurable expiration
* Media library integration
* File upload functionality
* Admin settings interface
* Security enhancements and validation
* Internationalization support

== External services ==

This plugin connects to S3-compatible storage APIs to manage files and create download links.

It sends the necessary authentication credentials and file requests to your configured S3 endpoint. This happens when you browse your S3 bucket in the dashboard, upload files, or when a customer downloads a file.

* **Service**: S3-Compatible Storage API
* **Used for**: File browsing, uploading, and generating signed download links.
* **Data sent**: API credentials, file metadata, file content (during upload).
* **URLs**: Depends on your configured endpoint:
    * Amazon S3: `https://s3.amazonaws.com`
    * Wasabi: `https://s3.wasabisys.com`
    * MinIO: Your self-hosted endpoint
* **Legal**: Refer to your storage provider's Terms of Service and Privacy Policy

== Support ==

For support and bug reports, please use the WordPress.org plugin support forum.

If you find this plugin helpful, please consider leaving a review on WordPress.org.

== Other Storage Providers ==

Looking for a different storage provider? Check out our other plugins:

* [Storage for WooCommerce via Dropbox](https://wordpress.org/plugins/storage-for-woo-via-dropbox/) - Use Dropbox for your digital product storage

== Privacy Policy ==

This plugin requires S3 API credentials to access your storage for file management. It does not collect or store any personal data beyond the API credentials needed to maintain the connection. All file storage and delivery is handled through your configured S3 endpoint's secure infrastructure.

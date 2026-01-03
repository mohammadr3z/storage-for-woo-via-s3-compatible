/**
 * Admin Upload Buttons Handler for S3 WooCommerce
 */
jQuery(function ($) {
    // Handler for "Use this file in your Download" button after upload
    $('#wcs3_save_link').click(function () {
        var filename = $(this).data('wcs3-fn');
        var fileurl = wcs3_url_prefix + $(this).data('wcs3-path');

        if (parent.window && parent.window !== window) {
            var $parent = $(parent.document);
            var $filenameInput = $parent.find('.wc_file_table input[name*="[name]"]').last();
            var $fileurlInput = $parent.find('.wc_file_table input[name*="[file]"]').last();

            if ($filenameInput.length && $fileurlInput.length) {
                $filenameInput.val(filename);
                $fileurlInput.val(fileurl);
                try { parent.window.tb_remove(); } catch (e) { window.tb_remove(); }
            }
        }
        return false;
    });
});

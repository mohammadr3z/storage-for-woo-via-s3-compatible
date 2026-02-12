/**
 * S3-Compatible Admin Settings JavaScript for WooCommerce
 */
jQuery(document).ready(function ($) {
    function wcs3_checkCredentials() {
        var accessKey = $('input[name="wcs3_access_key"]').val();
        var secretKey = $('input[name="wcs3_secret_key"]').val();
        var endpoint = $('input[name="wcs3_endpoint"]').val();

        var bucketRow = $('select[name="wcs3_bucket"]').closest('tr');
        var bucketSelect = $('select[name="wcs3_bucket"]');

        if (accessKey && secretKey && endpoint) {
            bucketRow.removeClass('wcs3-bucket-disabled');
            bucketSelect.prop('disabled', false);
        } else {
            bucketRow.addClass('wcs3-bucket-disabled');
            bucketSelect.prop('disabled', true);
            bucketSelect.val('');
        }
    }

    wcs3_checkCredentials();

    $('.wcs3-credential').on('input change', function () {
        wcs3_checkCredentials();
    });
});

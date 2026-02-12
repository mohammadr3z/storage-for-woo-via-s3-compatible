/**
 * S3 Browse Button Script
 * Handles S3 browse button click events in WooCommerce downloadable files section (AJAX Version)
 */
jQuery(function ($) {

    // Add S3 button next to each "Choose file" button
    function addS3Buttons() {

        // Target the upload_file_button class directly
        $('.upload_file_button').each(function () {
            var $chooseBtn = $(this);

            // Check if S3 button already exists
            if ($chooseBtn.siblings('.wcs3_file_button').length === 0 &&
                $chooseBtn.parent().find('.wcs3_file_button').length === 0) {

                var $row = $chooseBtn.closest('tr');
                var $s3Btn = $('<a href="#" class="button wcs3_file_button">Browse S3</a>');

                $s3Btn.on('click', function (e) {
                    e.preventDefault();

                    // Store references to the input fields for this row
                    window.wcs3_current_row = $row;
                    window.wcs3_current_name_input = $row.find('input[name="_wc_file_names[]"]');
                    window.wcs3_current_url_input = $row.find('input[name="_wc_file_urls[]"]');

                    var currentUrl = window.wcs3_current_url_input.val();
                    var folderPath = '';
                    // Using wcs3_browse_button object which should be localized
                    var urlPrefix = wcs3_browse_button.url_prefix;

                    if (currentUrl && currentUrl.indexOf(urlPrefix) === 0) {
                        // Remove prefix
                        var path = currentUrl.substring(urlPrefix.length);
                        // Remove filename, keep folder path
                        var lastSlash = path.lastIndexOf('/');
                        if (lastSlash !== -1) {
                            folderPath = path.substring(0, lastSlash);
                        }
                    }

                    WCS3Modal.open(folderPath, wcs3_browse_button.modal_title);
                });

                $chooseBtn.after($s3Btn);

            }
        });
    }

    // Initial run after short delay to ensure DOM is ready
    setTimeout(addS3Buttons, 500);

    // Run when document is fully ready
    $(document).ready(function () {
        addS3Buttons();
    });

    // Run when new rows are added
    $(document).on('click', '.insert', function () {
        setTimeout(addS3Buttons, 200);
    });

    // Also observe DOM changes for the downloadable files section
    var observer = new MutationObserver(function (mutations) {
        addS3Buttons();
    });

    // Try multiple possible parent selectors
    var targets = [
        document.querySelector('#downloadable_product_data tbody'),
        document.querySelector('.downloadable_files tbody'),
        document.querySelector('#woocommerce-product-data')
    ];

    targets.forEach(function (target) {
        if (target) {
            observer.observe(target, {
                childList: true,
                subtree: true
            });

        }
    });
});

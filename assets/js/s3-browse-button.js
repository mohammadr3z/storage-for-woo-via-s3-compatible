/**
 * S3 Browse Button Script
 * Adds S3 browse buttons to WooCommerce downloadable files section
 */
jQuery(function ($) {
    console.log('WCS3: Script loaded');

    // Add S3 button next to each "Choose file" button
    function addS3Buttons() {
        console.log('WCS3: addS3Buttons called');

        // Target the upload_file_button class directly
        $('.upload_file_button').each(function () {
            var $chooseBtn = $(this);
            console.log('WCS3: Found button', $chooseBtn);

            // Check if S3 button already exists
            if ($chooseBtn.siblings('.wcs3_file_button').length === 0 &&
                $chooseBtn.parent().find('.wcs3_file_button').length === 0) {

                var $row = $chooseBtn.closest('tr');
                var $s3Btn = $('<a href="#" class="button wcs3_file_button">Browse S3</a>');

                $s3Btn.on('click', function (e) {
                    e.preventDefault();

                    // Store references to the input fields for this row
                    window.wcs3_current_name_input = $row.find('input[name="_wc_file_names[]"]');
                    window.wcs3_current_url_input = $row.find('input[name="_wc_file_urls[]"]');

                    console.log('WCS3: Opening modal', window.wcs3_current_name_input, window.wcs3_current_url_input);

                    // Context-Aware: Open in the folder of the current file
                    var currentUrl = window.wcs3_current_url_input.val();
                    // Use dynamic prefix variable injected from PHP, fallback to default if undefined
                    var prefix = (typeof wcs3_url_prefix !== 'undefined') ? wcs3_url_prefix : 'wc-s3cs://';
                    var folderPath = '';

                    if (currentUrl && currentUrl.indexOf(prefix) === 0) {
                        // Remove prefix
                        var path = currentUrl.substring(prefix.length);
                        // Remove filename, keep folder path
                        var lastSlash = path.lastIndexOf('/');
                        if (lastSlash !== -1) {
                            folderPath = path.substring(0, lastSlash);
                        }
                    }

                    var modalUrl = wcs3_browse_button.modal_url;
                    if (folderPath) {
                        modalUrl += '&path=' + encodeURIComponent(folderPath);
                        modalUrl += '&_wpnonce=' + wcs3_browse_button.nonce;
                    }

                    // Open Custom Modal
                    WCS3Modal.open(modalUrl, wcs3_browse_button.modal_title);
                });

                $chooseBtn.after($s3Btn);
                console.log('WCS3: Button added');
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
            console.log('WCS3: Observing', target);
        }
    });
});

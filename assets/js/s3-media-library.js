/**
 * S3-Compatible Media Library JavaScript for WooCommerce
 */
jQuery(function ($) {
    // Fallback for undefined variables
    var url_prefix = (typeof wcs3_url_prefix !== 'undefined') ? wcs3_url_prefix : 'wc-s3cs://';
    var i18n = (typeof wcs3_i18n !== 'undefined') ? wcs3_i18n : {
        file_selected_success: 'File selected successfully!',
        file_selected_error: 'Error selecting file. Please try again.'
    };

    console.log('WCS3 Media Library: Loaded with prefix', url_prefix);

    // Helper to construct correct URI
    function getS3URI(link) {
        if (!link) return '';
        if (link.indexOf('wc-s3cs://') === 0) {
            return link;
        }
        // If it starts with http, it's likely a mistake or direct link, but we want wc-s3cs://path
        if (link.indexOf('http') === 0) {
            // Should ideally extract path, but for now fallback to prefix + link
            return link;
        }

        // Ensure path starts with / if needed, or handle prefix correctly
        // wcs3_url_prefix is 'wc-s3cs://'
        return url_prefix + link.replace(/^\//, '');
    }

    // File selection handler
    $('.save-wcs3-file').click(function () {
        var filename = $(this).data('wcs3-filename');
        var link = $(this).data('wcs3-link');
        var fileurl = getS3URI(link);
        var success = false;

        console.log('WCS3: Selecting file', filename, fileurl);

        // ... rest of the handler logic

        // Method 1: Use stored references from S3 button click
        if (parent.window && parent.window.wcs3_current_name_input && parent.window.wcs3_current_url_input) {
            console.log('WCS3: Using stored references');
            parent.window.wcs3_current_name_input.val(filename);
            parent.window.wcs3_current_url_input.val(fileurl);
            success = true;
            if (parent.WCS3Modal) {
                parent.WCS3Modal.close();
            }
        }

        // Method 2: Try WooCommerce file table inputs in parent
        if (!success && parent.window && parent.window !== window) {
            var $parent = $(parent.document);
            var $filenameInput = $parent.find('input[name="_wc_file_names[]"]').last();
            var $fileurlInput = $parent.find('input[name="_wc_file_urls[]"]').last();

            console.log('WCS3: Method 2 - Found', $filenameInput.length, $fileurlInput.length);

            if ($filenameInput.length && $fileurlInput.length) {
                $filenameInput.val(filename);
                $fileurlInput.val(fileurl);
                success = true;
                if (parent.WCS3Modal) {
                    parent.WCS3Modal.close();
                }
            }
        }

        if (!success) {
            alert(i18n.file_selected_error);
        }

        return false;
    });

    // Handler for upload success link
    $('#wcs3_save_link').click(function () {
        var filename = $(this).data('wcs3-fn');
        var link = $(this).data('wcs3-path');
        var fileurl = getS3URI(link);

        console.log('WCS3: Upload link clicked', filename, fileurl);

        if (parent.window && parent.window.wcs3_current_name_input && parent.window.wcs3_current_url_input) {
            parent.window.wcs3_current_name_input.val(filename);
            parent.window.wcs3_current_url_input.val(fileurl);
            if (parent.WCS3Modal) {
                parent.WCS3Modal.close();
            }
        }
        return false;
    });

    // Search functionality for S3 files
    $('#wcs3-file-search').on('input search', function () {
        var searchTerm = $(this).val().toLowerCase();
        var $fileRows = $('.wcs3-files-table tbody tr');
        var visibleCount = 0;

        $fileRows.each(function () {
            var $row = $(this);
            var fileName = $row.find('.file-name').text().toLowerCase();

            if (fileName.indexOf(searchTerm) !== -1) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        // Show/hide "no results" message
        var $noResults = $('.wcs3-no-search-results');
        if (visibleCount === 0 && searchTerm.length > 0) {
            if ($noResults.length === 0) {
                $('.wcs3-files-table').after('<div class="wcs3-no-search-results" style="padding: 20px; text-align: center; color: #666; font-style: italic;">No files found matching your search.</div>');
            } else {
                $noResults.show();
            }
        } else {
            $noResults.hide();
        }
    });

    // Keyboard shortcut for search
    $(document).keydown(function (e) {
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#wcs3-file-search').focus();
        }
    });

    // Toggle upload form
    $('#wcs3-toggle-upload').click(function () {
        $('#wcs3-upload-section').slideToggle(200);
    });
});

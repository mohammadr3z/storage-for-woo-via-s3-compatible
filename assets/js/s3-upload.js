/**
 * S3 Upload JavaScript for WooCommerce (AJAX Version)
 */
jQuery(function ($) {
    // File size validation - using existing wcs3_max_upload_size
    $(document).on('change', 'input[name="wcs3_file"]', function () {
        if (this.files && this.files[0]) {
            var fileSize = this.files[0].size;
            var maxSize = wcs3_max_upload_size;
            if (fileSize > maxSize) {
                alert(wcs3_i18n.file_size_too_large + ' ' + (maxSize / 1024 / 1024).toFixed(2) + 'MB');
                this.value = '';
            }
        }
    });

    // Helper to show notice
    function showUploadError(message) {
        $('.wcs3-notice').remove();
        var errorHtml = '<div class="wcs3-notice warning"><p>' + message + '</p></div>';
        var $uploadSection = $('#wcs3-upload-section');
        if ($uploadSection.length && $uploadSection.is(':visible')) {
            $uploadSection.prepend(errorHtml);
        } else {
            // Fallback
            $('#wcs3-modal-container').prepend(errorHtml);
        }
    }

    // Handle Upload Form Submission
    $(document).on('submit', '.wcs3-upload-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('input[type="submit"]');
        var $fileInput = $form.find('input[name="wcs3_file"]');
        var file = $fileInput[0].files[0];

        if (!file) {
            showUploadError(wcs3_i18n.file_selected_error || 'Please select a file.');
            return;
        }

        // Prepare FormData
        var formData = new FormData();
        formData.append('action', 'wcs3_ajax_upload');
        formData.append('wcs3_file', file);
        formData.append('wcs3_nonce', $form.find('input[name="wcs3_nonce"]').val());
        // Path input is updated by media library JS on navigation
        formData.append('wcs3_path', $form.find('input[name="wcs3_path"]').val());

        $btn.prop('disabled', true).val('Uploading...');

        // Remove previous notices
        $('.wcs3-notice').remove();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Refresh library
                    if (window.WCS3MediaLibrary) {
                        // Reload current path (which is what we uploaded to)
                        var currentPath = $form.find('input[name="wcs3_path"]').val();

                        // Wait for content to be loaded before showing notice
                        $(document).one('wcs3_content_loaded', function () {
                            // Create success notice HTML
                            var filename = response.data.filename;
                            var path = response.data.path;
                            // Use explicit link if provided, otherwise parse path
                            if (response.data.wcs3_link) {
                                path = response.data.wcs3_link;
                            } else if (path.charAt(0) === '/') {
                                path = path.substring(1);
                            }

                            var successHtml =
                                '<div class="wcs3-notice success">' +
                                '<h4>' + (response.data.message || 'Upload Successful') + '</h4>' +
                                '<p>File <strong>' + filename + '</strong> uploaded successfully.</p>' +
                                '<p>' +
                                '<button type="button" class="button button-primary save-wcs3-file" ' +
                                'data-wcs3-filename="' + filename + '" ' +
                                'data-wcs3-link="' + path + '">' +
                                'Use this file' +
                                '</button>' +
                                '</p>' +
                                '</div>';

                            // Inject notice after the upload section (or before table if upload section hidden)
                            var $uploadSection = $('#wcs3-upload-section');
                            if ($uploadSection.length) {
                                $uploadSection.after(successHtml);
                            } else {
                                // Fallback: prepend to container
                                $('#wcs3-modal-container').prepend(successHtml);
                            }
                        });

                        window.WCS3MediaLibrary.load(currentPath);
                    }

                    // Reset form
                    $fileInput.val('');
                    // Remove existing notices
                    $('.wcs3-notice, .wcs3-no-search-results').remove();
                } else {
                    var errorMsg = 'Unknown error';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (typeof response.data === 'object') {
                            if (response.data.message) {
                                errorMsg = response.data.message;
                            } else if (Array.isArray(response.data) && response.data.length > 0) {
                                errorMsg = response.data[0];
                            } else {
                                // Try to extract values if it's a simple object
                                var values = Object.values(response.data);
                                if (values.length > 0) {
                                    errorMsg = values.join(', ');
                                }
                            }
                        }
                    }
                    showUploadError('Upload Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                var errorDetails = '';
                if (xhr.status) {
                    errorDetails += ' (Status: ' + xhr.status + ')';
                }
                if (xhr.responseText) {
                    // Truncate response text if too long (e.g. HTML error page)
                    var text = xhr.responseText.substring(0, 100);
                    errorDetails += '<br>Response: ' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                }
                showUploadError('Connection error during upload.' + errorDetails);
            },
            complete: function () {
                $btn.prop('disabled', false).val('Upload');
            }
        });
    });
});

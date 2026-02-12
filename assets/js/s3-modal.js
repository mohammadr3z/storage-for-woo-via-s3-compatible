/**
 * WCS3 Modal JS (AJAX Version)
 */
var WCS3Modal = (function ($) {
    var $modal, $overlay, $container, $closeBtn, $skeleton;

    // Shared skeleton rows for reuse
    var skeletonRowsHtml =
        '<tr><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 60%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 80%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="wcs3-skeleton-cell" style="width: 55%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 50%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 75%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="wcs3-skeleton-cell" style="width: 80%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 45%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td></tr>' +
        '<tr><td><div class="wcs3-skeleton-cell" style="width: 65%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 55%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 85%;"></div></td><td><div class="wcs3-skeleton-cell" style="width: 70%;"></div></td></tr>';

    function init() {
        if ($('#wcs3-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure with real UI elements
        var skeletonHtml =
            '<div class="wcs3-skeleton-loader">' +
            '<div class="wcs3-header-row">' +
            '<h3 class="media-title">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_select_file || 'Select a file from S3') + '</h3>' +
            '<div class="wcs3-header-buttons">' +
            '<button type="button" class="button button-primary" id="wcs3-toggle-upload">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_upload || 'Upload File') + '</button>' +
            '</div>' +
            '</div>' +
            '<div class="wcs3-breadcrumb-nav wcs3-skeleton-breadcrumb">' +
            '<div class="wcs3-nav-group">' +
            '<span class="wcs3-nav-back disabled"><span class="dashicons dashicons-arrow-left-alt2"></span></span>' +
            '<div class="wcs3-breadcrumbs"><div class="wcs3-skeleton-cell" style="width: 120px; height: 18px;"></div></div>' +
            '</div>' +
            '<div class="wcs3-search-inline"><input type="search" class="wcs3-search-input" placeholder="' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_search || 'Search files...') + '" disabled></div>' +
            '</div>' +
            '<table class="wp-list-table widefat fixed wcs3-files-table">' +
            '<thead><tr>' +
            '<th class="column-primary" style="width: 40%;">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_file_name || 'File Name') + '</th>' +
            '<th class="column-size" style="width: 20%;">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_file_size || 'File Size') + '</th>' +
            '<th class="column-date" style="width: 25%;">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_last_modified || 'Last Modified') + '</th>' +
            '<th class="column-actions" style="width: 15%;">' + (typeof wcs3_browse_button !== 'undefined' && wcs3_browse_button.i18n_actions || 'Actions') + '</th>' +
            '</tr></thead>' +
            '<tbody>' + skeletonRowsHtml + '</tbody></table>' +
            '</div>';

        // Create DOM structure with skeleton (div container instead of iframe)
        var html =
            '<div id="wcs3-modal-overlay" class="wcs3-modal-overlay">' +
            '<div class="wcs3-modal">' +
            '<div class="wcs3-modal-header">' +
            '<h1 class="wcs3-modal-title"></h1>' +
            '<button type="button" class="wcs3-modal-close">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>' +
            '</div>' +
            '<div class="wcs3-modal-content">' +
            skeletonHtml +
            '<div id="wcs3-modal-container" class="wcs3-modal-container hidden"></div>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#wcs3-modal-overlay');
        $modal = $overlay.find('.wcs3-modal');
        $container = $overlay.find('#wcs3-modal-container');
        $title = $overlay.find('.wcs3-modal-title');
        $closeBtn = $overlay.find('.wcs3-modal-close');
        $skeleton = $overlay.find('.wcs3-skeleton-loader');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) {
                close();
            }
        });

        // Global event for content loaded
        $(document).on('wcs3_content_loaded', function () {
            $skeleton.addClass('hidden');
            $container.removeClass('hidden');
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide container
        $skeleton.removeClass('hidden');
        $container.addClass('hidden');

        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');

        // Trigger library load via AJAX
        if (window.WCS3MediaLibrary) {
            window.WCS3MediaLibrary.load(url || '');
        }
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $container.empty().addClass('hidden');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close,
        getSkeletonRows: function () {
            return skeletonRowsHtml;
        }
    };

})(jQuery);

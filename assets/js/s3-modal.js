/**
 * WCS3 Modal JS
 */
var WCS3Modal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn, $skeleton;

    function init() {
        if ($('#wcs3-modal-overlay').length) {
            return;
        }

        // Skeleton HTML structure
        var skeletonHtml =
            '<div class="wcs3-skeleton-loader">' +
            '<div class="wcs3-skeleton-header">' +
            '<div class="wcs3-skeleton-title"></div>' +
            '<div class="wcs3-skeleton-button"></div>' +
            '</div>' +
            '<div class="wcs3-skeleton-breadcrumb">' +
            '<div class="wcs3-skeleton-back-btn"></div>' +
            '<div class="wcs3-skeleton-path"></div>' +
            '<div class="wcs3-skeleton-search"></div>' +
            '</div>' +
            '<div class="wcs3-skeleton-table">' +
            '<div class="wcs3-skeleton-thead">' +
            '<div class="wcs3-skeleton-row">' +
            '<div class="wcs3-skeleton-cell name"></div>' +
            '<div class="wcs3-skeleton-cell size"></div>' +
            '<div class="wcs3-skeleton-cell date"></div>' +
            '<div class="wcs3-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
            '<div class="wcs3-skeleton-row">' +
            '<div class="wcs3-skeleton-cell name"></div>' +
            '<div class="wcs3-skeleton-cell size"></div>' +
            '<div class="wcs3-skeleton-cell date"></div>' +
            '<div class="wcs3-skeleton-cell action"></div>' +
            '</div>' +
            '</div>' +
            '</div>';

        // Create DOM structure with skeleton
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
            '<iframe class="wcs3-modal-frame loading" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#wcs3-modal-overlay');
        $modal = $overlay.find('.wcs3-modal');
        $iframe = $overlay.find('.wcs3-modal-frame');
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

        // Handle iframe load event
        $iframe.on('load', function () {
            $skeleton.addClass('hidden');
            $iframe.removeClass('loading').addClass('loaded');
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');

        // Reset state: show skeleton, hide iframe
        $skeleton.removeClass('hidden');
        $iframe.removeClass('loaded').addClass('loading');

        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', '');
            $iframe.removeClass('loaded').addClass('loading');
            $skeleton.removeClass('hidden');
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);

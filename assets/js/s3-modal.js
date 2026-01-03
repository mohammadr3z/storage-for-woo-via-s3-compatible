/**
 * WCS3 Modal JS
 */
var WCS3Modal = (function ($) {
    var $modal, $overlay, $iframe, $closeBtn;

    function init() {
        if ($('#wcs3-modal-overlay').length) {
            return;
        }

        // Create DOM structure
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
            '<iframe class="wcs3-modal-frame" src=""></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);

        $overlay = $('#wcs3-modal-overlay');
        $modal = $overlay.find('.wcs3-modal');
        $iframe = $overlay.find('.wcs3-modal-frame');
        $title = $overlay.find('.wcs3-modal-title');
        $closeBtn = $overlay.find('.wcs3-modal-close');

        // Event listeners
        $closeBtn.on('click', close);
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) {
                close();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $overlay.hasClass('open')) { // ESC
                close();
            }
        });
    }

    function open(url, title) {
        init();
        $title.text(title || 'Select File');
        $iframe.attr('src', url);
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden'); // Prevent body scroll
    }

    function close() {
        if ($overlay) {
            $overlay.removeClass('open');
            $iframe.attr('src', ''); // Stop loading
            $('body').css('overflow', '');
        }
    }

    return {
        open: open,
        close: close
    };

})(jQuery);

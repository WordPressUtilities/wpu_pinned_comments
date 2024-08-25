document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    document.addEventListener("click", function(e) {
        var $icon_link = e.target.closest('.wpu-pin-comment[data-comment-id]');
        if (!$icon_link) {
            return;
        }
        e.preventDefault();

        jQuery.ajax({
            type: 'POST',
            url: wpu_pinned_comments_admin.ajax_url,
            data: {
                action: 'wpu_pinned_comments_toggle_status',
                comment_id: $icon_link.getAttribute('data-comment-id'),
                status: $icon_link.getAttribute('data-status')
            },
            success: function(response) {
                if (!response.data.icon_html) {
                    return;
                }

                /* Edit all visible icon with the same ID */
                Array.prototype.forEach.call(document.querySelectorAll('.wpu-pin-comment[data-comment-id="' + $icon_link.getAttribute('data-comment-id') + '"]'), function($icon) {
                    $icon.setAttribute('data-status', response.data.status);
                    $icon.innerHTML = response.data.icon_html;
                });
            }
        });
    });



});

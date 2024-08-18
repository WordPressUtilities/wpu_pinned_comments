document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('.wpu-pin-comment[data-comment-id]'), function($icon_link) {
        $icon_link.addEventListener('click', function(e) {
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
                    $icon_link.setAttribute('data-status', response.data.status);
                    $icon_link.innerHTML = response.data.icon_html;
                }
            });
        });

    });
});

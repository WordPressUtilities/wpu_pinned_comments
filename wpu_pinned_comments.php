<?php
/*
Plugin Name: WPU Pinned Comments
Plugin URI: https://github.com/WordPressUtilities/wpu_pinned_comments
Update URI: https://github.com/WordPressUtilities/wpu_pinned_comments
Description: Pin some comments
Version: 0.4.0
Author: Darklg
Author URI: https://darklg.me
Text Domain: wpu_pinned_comments
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit();
}

class WPU_Pinned_Comments {
    private $plugin_version = '0.4.0';
    private $plugin_settings = array(
        'id' => 'wpu_pinned_comments',
        'name' => 'WPU Pinned Comments'
    );
    private $plugin_description;
    private $flag_apply_filters = false;

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

        /* Admin field */
        add_action('add_meta_boxes_comment', array(&$this, 'add_comment_meta_box'));
        add_action('edit_comment', array(&$this, 'save_comment_meta'), 10, 2);

        /* Admin list */
        add_filter('manage_edit-comments_columns', array(&$this, 'add_pinned_column'));
        add_action('manage_comments_custom_column', array(&$this, 'display_pinned_column'), 10, 2);
        add_filter('manage_edit-comments_sortable_columns', array(&$this, 'add_pinned_column_sortable'));
        add_action('pre_get_comments', array(&$this, 'sort_comments_by_pinned'));

        /* Add quick link displaying only pinned comments */
        add_filter('views_edit-comments', array(&$this, 'add_pinned_comments_quick_link'));

        /* Add a pin link to .row-actions */
        add_filter('comment_row_actions', array(&$this, 'add_pinned_comment_row_actions'), 10, 2);

        /* Admin JS */
        add_action('admin_enqueue_scripts', array(&$this, 'enqueue_admin_scripts'));

        /* Ajax */
        add_action('wp_ajax_wpu_pinned_comments_toggle_status', array(&$this, 'toggle_comment_status'));

    }

    public function plugins_loaded() {
        # TRANSLATION
        if (!load_plugin_textdomain('wpu_pinned_comments', false, dirname(plugin_basename(__FILE__)) . '/lang/')) {
            load_muplugin_textdomain('wpu_pinned_comments', dirname(plugin_basename(__FILE__)) . '/lang/');
        }
        $this->plugin_description = __('Pin some comments', 'wpu_pinned_comments');

    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    public function enqueue_admin_scripts() {
        wp_enqueue_script('wpu-pinned-comments-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), $this->plugin_version, true);
        wp_localize_script('wpu-pinned-comments-admin', 'wpu_pinned_comments_admin', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    public function get_pinned_icon($pinned = true) {
        if (!$pinned) {
            return '<span class="dashicons dashicons-star-empty" title="' . __('Not pinned', 'wpu_pinned_comments') . '"></span>';
        }
        return '<span class="dashicons dashicons-star-filled" title="' . __('Pinned', 'wpu_pinned_comments') . '"></span>';
    }

    /* Filter in comments list
    -------------------------- */

    public function add_pinned_column($columns) {
        $columns['wpu_pinned_comment'] = __('Pinned', 'wpu_pinned_comments');
        return $columns;
    }

    public function add_pinned_column_sortable($columns) {
        $columns['wpu_pinned_comment'] = 'wpu_pinned_comment';
        return $columns;
    }

    public function display_pinned_column($column, $comment_id) {
        if ($column != 'wpu_pinned_comment') {
            return;
        }
        $pinned = $this->is_pinned($comment_id);
        $icon_result = $this->get_pinned_icon($pinned);
        if (current_user_can('edit_comment', $comment_id)) {
            echo '<a href="#" data class="wpu-pin-comment" data-status="' . ($pinned ? '1' : '0') . '" data-comment-id="' . $comment_id . '">' . $icon_result . '</a>';
        } else {
            echo $icon_result;
        }
    }

    public function toggle_comment_status() {
        if (!isset($_POST['comment_id']) || !isset($_POST['status'])) {
            wp_send_json_error(__('Invalid request', 'wpu_pinned_comments'));
        }

        $comment_id = intval($_POST['comment_id']);
        $is_pinned = intval($_POST['status']);

        if (!current_user_can('edit_comment', $comment_id)) {
            wp_send_json_error(__('You do not have permission to edit this comment', 'wpu_pinned_comments'));
        }

        if ($is_pinned) {
            $this->unpin_comment($comment_id);
            wp_send_json_success(array(
                'icon_html' => $this->get_pinned_icon(false),
                'status' => 0
            ));
        } else {
            $this->pin_comment($comment_id);
            wp_send_json_success(array(
                'icon_html' => $this->get_pinned_icon(true),
                'status' => 1
            ));
        }

    }

    public function sort_comments_by_pinned($wp_comment_query) {
        if (is_admin()) {
            /* Display only pinned comments */
            if (isset($_GET['wpu_pinned_comment'])) {
                /* Avoid recursive call */
                if (!$this->flag_apply_filters) {
                    $this->flag_apply_filters = true;
                    $wp_comment_query->query_vars['comment__in'] = $this->get_pinned_comments(0, array('ids' => true));
                    $this->flag_apply_filters = false;
                }
            }
            if (isset($_GET['orderby']) && $_GET['orderby'] == 'wpu_pinned_comment') {
                $this->set_order_comments_pinned($wp_comment_query);
            }
        } else {
            /* Display pinned comments above comments */
            $this->set_order_comments_pinned($wp_comment_query);
        }
    }

    public function set_order_comments_pinned($wp_comment_query) {
        $wp_comment_query->query_vars['orderby'] = 'meta_value_num comment_date';
        $wp_comment_query->query_vars['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'wpu_pinned_comment',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => 'wpu_pinned_comment',
                'value' => 1
            )
        );
    }

    /* Quick links */
    public function add_pinned_comments_quick_link($links = array()) {
        $count = $this->get_pinned_comments(0, array('count' => true));
        $url = admin_url('edit-comments.php?wpu_pinned_comment=1');
        $label = __('Pinned comments', 'wpu_pinned_comments');
        $current_attribute = isset($_GET['wpu_pinned_comment']) ? ' class="current"' : '';
        $links['wpu_pinned_comment'] = '<a ' . $current_attribute . ' href="' . $url . '">' . $label . '</a> <span class="count">(' . $count . ')</span>';
        if (isset($links['all'])) {
            $links['all'] = str_replace('class="current"', '', $links['all']);
        }
        return $links;
    }

    public function add_pinned_comment_row_actions($actions, $comment) {
        if (!current_user_can('edit_comment', $comment->comment_ID)) {
            return $actions;
        }
        $pinned = $this->is_pinned($comment->comment_ID);
        $label = $this->get_pinned_icon($pinned);

        $actions['wpu_pinned_comment'] = '<a href="#"  class="wpu-pin-comment" data-status="' . ($pinned ? '1' : '0') . '" data-comment-id="' . $comment->comment_ID . '">' . $label . '</a>';
        return $actions;
    }

    /* Admin field
    -------------------------- */

    public function add_comment_meta_box() {
        add_meta_box(
            'wpu_pinned_comment',
            __('Pinned comment', 'wpu_pinned_comments'),
            array(&$this, 'display_comment_meta_box'),
            'comment',
            'normal',
            'high'
        );
    }

    public function display_comment_meta_box($comment) {
        $is_pinned = $this->is_pinned($comment->comment_ID);
        echo '<label for="wpu_pinned_comment_box">';
        echo '<input type="checkbox" name="wpu_pinned_comment" id="wpu_pinned_comment_box" value="1" ' . checked($is_pinned, 1, false) . ' />';
        echo '<input type="hidden" name="wpu_pinned_comment_nonce" value="' . wp_create_nonce('wpu_pinned_comment') . '" />';
        echo __('Pin this comment', 'wpu_pinned_comments');
        echo '</label>';
    }

    public function save_comment_meta($comment_id) {
        if (!isset($_POST['wpu_pinned_comment_nonce']) || !wp_verify_nonce($_POST['wpu_pinned_comment_nonce'], 'wpu_pinned_comment')) {
            return;
        }
        if (!current_user_can('edit_comment', $comment_id)) {
            return;
        }
        if (isset($_POST['wpu_pinned_comment']) && $_POST['wpu_pinned_comment'] == 1) {
            $this->pin_comment($comment_id);
        } else {
            $this->unpin_comment($comment_id);
        }
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    public function is_pinned($comment_id) {
        return get_comment_meta($comment_id, 'wpu_pinned_comment', true);
    }

    public function pin_comment($comment_id) {
        return update_comment_meta($comment_id, 'wpu_pinned_comment', 1);
    }

    public function unpin_comment($comment_id) {
        return delete_comment_meta($comment_id, 'wpu_pinned_comment');
    }

    public function get_pinned_comments($post_id, $args = array()) {
        $defaults = array(
            'count' => false,
            'ids' => false
        );
        if (!is_array($args)) {
            $args = array();
        }
        $args = array_merge($defaults, $args);
        $q = array(
            'post_id' => $post_id,
            'meta_key' => 'wpu_pinned_comment',
            'meta_value' => 1
        );
        if (!$post_id) {
            unset($q['post_id']);
        }
        if ($args['ids']) {
            $q['fields'] = 'ids';
        }
        if ($args['count']) {
            $q['count'] = true;
        }
        return get_comments($q);
    }
}

$WPU_Pinned_Comments = new WPU_Pinned_Comments();

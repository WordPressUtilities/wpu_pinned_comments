<?php
/*
Plugin Name: WPU Pinned Comments
Plugin URI: https://github.com/WordPressUtilities/wpu_pinned_comments
Update URI: https://github.com/WordPressUtilities/wpu_pinned_comments
Description: Pin some comments
Version: 0.1.0
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
    private $plugin_version = '0.1.0';
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
        add_filter('manage_edit-comments_sortable_columns', array(&$this, 'add_pinned_column'));
        add_action('pre_get_comments', array(&$this, 'sort_comments_by_pinned'));

        /* Add quick link displaying only pinned comments */
        add_filter('views_edit-comments', array(&$this, 'add_pinned_comments_quick_link'));

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

    /* Filter in comments list
    -------------------------- */

    public function add_pinned_column($columns) {
        $columns['wpu_pinned_comment'] = __('Pinned', 'wpu_pinned_comment');
        return $columns;
    }

    public function display_pinned_column($column, $comment_id) {
        if ($column == 'wpu_pinned_comment') {
            $is_pinned = $this->is_pinned($comment_id);
            if (!$is_pinned) {
                return;
            }
            echo ($is_pinned) ? __('Yes', 'wpu_pinned_comment') : __('No', 'wpu_pinned_comment');
        }
    }

    public function sort_comments_by_pinned($wp_comment_query) {
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET['wpu_pinned_comment'])) {
            return;
        }
        /* Avoid recursive call */
        if (!$this->flag_apply_filters) {
            $this->flag_apply_filters = true;
            $wp_comment_query->query_vars['comment__in'] = $this->get_pinned_comments(0, true);
            $this->flag_apply_filters = false;
        }
    }

    /* Quick links */
    public function add_pinned_comments_quick_link($links = array()) {
        $count = count($this->get_pinned_comments(0, true));
        $url = admin_url('edit-comments.php?wpu_pinned_comment=1');
        $label = __('Pinned comments', 'wpu_pinned_comment');
        $html = isset($_GET['wpu_pinned_comment']) ? '<strong>' . $label . '</strong>' : '<a href="' . $url . '">' . __('Pinned comments', 'wpu_pinned_comment') . '</a>';
        $links['wpu_pinned_comment'] = $html . ' (' . $count . ')';
        return $links;
    }

    /* Admin field
    -------------------------- */

    public function add_comment_meta_box() {
        add_meta_box(
            'wpu_pinned_comment',
            __('Pinned comment', 'wpu_pinned_comment'),
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
        echo __('Pin this comment', 'wpu_pinned_comment');
        echo '</label>';
    }

    public function save_comment_meta($comment_id) {
        if (!isset($_POST['wpu_pinned_comment_nonce']) || !wp_verify_nonce($_POST['wpu_pinned_comment_nonce'], 'wpu_pinned_comment')) {
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

    public function get_pinned_comments($post_id, $ids = false) {
        $args = array(
            'post_id' => $post_id,
            'meta_key' => 'wpu_pinned_comment',
            'meta_value' => 1,
            'number' => -1
        );
        if ($ids) {
            $args['fields'] = 'ids';
        }

        return get_comments($args);
    }
}

$WPU_Pinned_Comments = new WPU_Pinned_Comments();
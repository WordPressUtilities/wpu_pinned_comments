<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete all comments metas */
global $wpdb;
$q = $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE meta_key = %s", 'wpu_pinned_comment');
$wpdb->query($q);

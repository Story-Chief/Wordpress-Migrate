<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
    delete_option('storychief_migrate_completed');

    global $wpdb;

    // Remove all meta fields related to the migration
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_complete'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_error'));
}
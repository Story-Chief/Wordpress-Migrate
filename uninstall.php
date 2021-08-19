<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
    delete_option('storychief_migrate_completed');
    delete_option('storychief_migrate_encryption_key');

    global $wpdb;

    // Remove all meta fields related to the migration
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_complete'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_error'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_category'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_tag'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_destination_id'));
}
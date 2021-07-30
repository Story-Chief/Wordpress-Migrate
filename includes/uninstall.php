<?php

/**
 * This will remove all of the meta fields, that was used to track the progression of the migration.
 */
function storychief_migrate_register_uninstall_hook()
{
    delete_option('storychief_migrate_completed');

    global $wpdb;

    // Remove all meta fields related to the migration
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_complete'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'storychief_migrate_error'));
}
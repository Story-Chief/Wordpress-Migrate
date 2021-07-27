<?php

/**
 * Plugin Name: StoryChief Migrate
 * Plugin URI: https://storychief.io/wordpress
 * Description: This plugin lets you migrate existing posts to StoryChief.
 * Version: 1.0.0
 * Author: StoryChief
 * Author URI: http://storychief.io
 * License: GPL3
 */

namespace StorychiefMigrate;

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

if (!defined('STORYCHIEF_MIGRATE_DIR')) {
    define('STORYCHIEF_MIGRATE_DIR', __DIR__);
}

// TODO: remove api key from the database when disabling / removing the plugin
// TODO: remove post meta values "storychief_migrated_check"

require __DIR__.'/includes/class.rest.php';
require __DIR__.'/includes/class.admin.php';

add_action('admin_init', [\StorychiefMigrate\Admin::class, 'admin_init']);
add_action('admin_menu', [\StorychiefMigrate\Admin::class, 'admin_menu']);
add_action('rest_api_init', [\StorychiefMigrate\Admin::class, 'admin_json']);
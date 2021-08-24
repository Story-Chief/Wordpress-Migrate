<?php

/**
 * Plugin Name: StoryChief Migrate
 * Plugin URI: https://storychief.io/wordpress
 * Description: This plugin helps you to migrate existing posts to StoryChief.
 * Version: 0.2
 * Requires PHP: 7.2
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

require __DIR__.'/includes/class.rest.php';
require __DIR__.'/includes/class.admin.php';
require __DIR__.'/includes/class.transfer.php';

if (is_admin()) {
    add_action('admin_init', [\StorychiefMigrate\Admin::class, 'admin_init']);
    add_action('admin_menu', [\StorychiefMigrate\Admin::class, 'admin_menu']);
}
add_action('rest_api_init', [\StorychiefMigrate\Admin::class, 'admin_json']);

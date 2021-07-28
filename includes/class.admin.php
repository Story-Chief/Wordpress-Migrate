<?php

namespace StoryChiefMigrate;

use WP_Query;

use function Storychief\Settings\get_sc_option;
use function Storychief\Settings\update_sc_option;

class Admin
{
    const REST_URI = 'https://api.storychief.sc/1.0';
    const NONCE = 'storychief-migrate-update-api-key';

    public static function admin_init()
    {
        if (
            isset($_POST['action'], $_POST['_wpnonce']) && $_POST['action'] === 'enter-api-key' &&
            current_user_can('manage_options')
        ) {
            self::save_configuration();
        }
    }

    public static function admin_menu()
    {
        add_options_page(
            'StoryChief Migrate',
            'StoryChief Migrate',
            'manage_options',
            'storychief-migrate',
            array(
                Admin::class,
                'display_configuration_page'
            )
        );
    }

    public static function admin_json()
    {
        register_rest_route(
            'storychief/migrate',
            'connection_check',
            array(
                'methods' => 'POST',
                'callback' => [Rest::class, 'connection_check'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            )
        );
        register_rest_route(
            'storychief/migrate',
            'destinations',
            array(
                'methods' => 'POST',
                'callback' => [Rest::class, 'destinations'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            )
        );

        register_rest_route(
            'storychief/migrate',
            'run',
            array(
                'methods' => 'POST',
                'callback' => [Rest::class, 'run'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            )
        );
    }

    public static function save_configuration()
    {
        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            return false;
        }

        if (isset($_POST['api_key'])) {
            update_sc_option('migrate_api_key', $_POST['api_key']);
        }
        if (isset($_POST['destination_id'])) {
            update_sc_option('migrate_destination_id', $_POST['destination_id']);
        }
        // TODO: show a success message
    }

    public static function display_configuration_page()
    {
        if (!defined('STORYCHIEF_DIR')) {
            // Show an error, that the main plugin needs to be installed
            return require STORYCHIEF_MIGRATE_DIR.'/views/error-dependency.php';
        }

        $uri = plugin_dir_url(STORYCHIEF_MIGRATE_DIR.'/index.php');
        $total_posts = Admin::get_total_posts();
        $total_completed = Admin::get_total_completed();
        $total_percentage = Admin::get_total_percentage();
        $completed = (bool)get_sc_option('migrate_completed');

        wp_enqueue_style('storychief-migrate-css', $uri.'/css/main.css', null);
        wp_enqueue_script('storychief-migrate-js', $uri.'/js/main.js', null, null, true);
        wp_localize_script(
            'storychief-migrate-js',
            'wpStoryChiefMigrate',
            [
                'rest_api_url' => rest_url(''),
                'nonce' => wp_create_nonce('wp_rest'),
                'total_posts' => $total_posts,
                'total_completed' => $total_completed,
                'total_percentage' => $total_percentage,
                'completed' => $completed,
            ]
        );

        require STORYCHIEF_MIGRATE_DIR.'/views/general.php';
    }

    public static function get_total_percentage()
    {
        $total_posts = Admin::get_total_posts();
        $total_completed = Admin::get_total_completed();

        if (!$total_posts) {
            return 100;
        }

        return $total_completed <= $total_posts ? ($total_completed / $total_posts * 100) : 100;
    }

    public static function get_page_url()
    {
        $args = ['page' => 'storychief-migrate'];

        return add_query_arg($args, admin_url('options-general.php'));
    }

    public static function get_total_posts()
    {
        return (new WP_Query(
            [
                'post_type' => get_sc_option('post_type'),
                'posts_per_page' => 1,
            ]
        ))->found_posts;
    }

    public static function get_total_completed()
    {
        if (is_array($posts_migrated = get_sc_option('posts_migrated'))) {
            return count($posts_migrated);
        }

        return 0;
    }

    /**
     * Validate if the API-key works
     *
     * @param  string  $api_key
     * @return bool
     */
    public static function connection_check($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        $response = wp_remote_get(
            Admin::REST_URI.'/me',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$api_key,
                ],
                'sslverify' => false,
            ]
        );

        return $response['response']['code'] < 400;
    }

    /**
     * Detect if the channel exists and is configured correctly as a WordPress website.
     *
     * @param $api_key
     * @param $destination_id
     * @return bool
     */
    public static function destination_exists($api_key, $destination_id)
    {
        if (empty($destination_id)) {
            return false;
        }

        $response = wp_remote_get(
            Admin::REST_URI.'/destinations/'.$destination_id,
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$api_key,
                ],
                'sslverify' => false,
            ]
        );

        if ($response['response']['code'] >= 400) {
            return false;
        }

        $json = json_decode($response['body'], true);

        return
            isset($json['data']['id'], $json['data']['status'], $json['data']['type']) &&
            $json['data']['status'] === 'configured' &&
            $json['data']['type'] === 'wordpress';
    }
}
<?php

namespace StoryChiefMigrate;

use WP_Error;
use WP_Query;

use function Storychief\Settings\get_sc_option;

class Admin
{
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

    public static function settings_link($links)
    {
        $settings_link = '<a href="'.self::get_settings_url().'">'.__('Settings').'</a>';

        array_push($links, $settings_link);

        return $links;
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

        wp_enqueue_style('storychief-migrate-css', $uri.'/css/main.css', null, filemtime(STORYCHIEF_MIGRATE_DIR . '/css/main.css'));
        wp_enqueue_script('storychief-migrate-js', $uri.'/js/main.js', null, filemtime(STORYCHIEF_MIGRATE_DIR . '/js/main.js'), true);

        wp_localize_script(
            'storychief-migrate-js',
            'wpStoryChiefMigrate',
            [
                'rest_api_url' => rest_url(''),
                'settings_url' => self::get_settings_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'total_posts' => $total_posts,
                'total_completed' => $total_completed,
                'total_percentage' => $total_percentage,
                'completed' => $completed,
            ]
        );

        require STORYCHIEF_MIGRATE_DIR.'/views/general.php';
    }

    public static function get_settings_url()
    {
        return admin_url('options-general.php?page=storychief-migrate');
    }

    public static function get_rest_url()
    {
        return defined('STORYCHIEF_REST_URI') ? STORYCHIEF_REST_URI : 'https://api.storychief.io/1.0';
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
            apply_filters('storychief_migrate_wp_query', 
                [
                    'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                    'post_type' => get_sc_option('post_type'),
                    'posts_per_page' => 1,
                ], 
                'get_total_posts'
            )
        ))->found_posts;
    }

    public static function get_total_completed()
    {
        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', 
                [
                    'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                    'post_type' => get_sc_option('post_type'),
                    'posts_per_page' => 5,
                    'meta_query' => [
                        [
                            'key' => 'storychief_migrate_complete',
                            'compare' => 'EXISTS',
                        ]
                    ]
                ], 
                'get_total_completed'
            )
        ))->found_posts;
    }

    public static function total_errors()
    {
        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', 
                [
                    'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                    'post_type' => get_sc_option('post_type'),
                    'posts_per_page' => 1,
                    'meta_query' => [
                        [
                            'key' => 'storychief_migrate_error',
                            'compare' => 'EXISTS',
                        ]
                    ]
                ],
                'total_errors'
            )
        ))->found_posts;
    }

    public static function get_errors()
    {
        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', 
                [
                    'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                    'post_type' => get_sc_option('post_type'),
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => 'storychief_migrate_error',
                            'compare' => 'EXISTS',
                        ]
                    ]
                ],
                'get_errors'
            )
        ));
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
            Admin::get_rest_url().'/me',
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

        if ($response instanceof WP_Error) {
            return false;
        }

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
            Admin::get_rest_url().'/destinations/'.$destination_id,
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
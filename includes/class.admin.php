<?php

namespace StoryChiefMigrate;

use WP_Error;
use WP_Query;

class Admin
{

    public static function admin_init()
    {
        add_filter(
            'plugin_action_links_story-chief-migrate/story-chief-migrate.php',
            array(self::class, 'settings_link')
        );
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
            'preview',
            array(
                'methods' => 'POST',
                'callback' => [Rest::class, 'preview'],
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

        register_rest_route(
            'storychief/migrate',
            'errors',
            array(
                'methods' => 'POST',
                'callback' => [Rest::class, 'errors'],
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
        $uri = plugin_dir_url(STORYCHIEF_MIGRATE_DIR.'/index.php');
        $completed = (bool)get_option('storychief_migrate_completed');

        // todo: clean-up to a different method
        $post_types = [];
        $post_types_raw = get_post_types(['public' => true, 'show_in_menu' => true], 'objects');

        foreach ($post_types_raw as $post_type) {
            if (in_array($post_type->name, ['page', 'attachment'])) {
                continue;
            }

            $taxonomies = get_taxonomies(
                [
                    'object_type' => [$post_type->name],
                    'show_in_menu' => true,
                ],
                'objects'
            );

            $post_types[$post_type->name] = [
                'name' => $post_type->name,
                'label' => $post_type->label,
                'taxonomies' => array_values(
                    array_map(function ($taxonomy) {
                        return [
                            'name' => $taxonomy->name,
                            'label' => $taxonomy->label,
                        ];
                    }, $taxonomies)
                ),
                'taxonomy_objects' => []
            ];

            foreach ($taxonomies as $taxonomy) {
                $terms = get_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => true]);

                if (count($terms)) {
                    $post_types[$post_type->name]['taxonomy_objects'][$taxonomy->name] = [
                        'name' => $taxonomy->name,
                        'label' => $taxonomy->label,
                        'post_type' => $post_type->name,
                        'items' => []
                    ];

                    foreach ($terms as $term) {
                        $the_query = new WP_Query(
                            [
                                'post_status' => [
                                    'publish',
                                    'draft',
                                    'pending',
                                    'future',
                                    'private'
                                ],
                                'post_type' => $post_type->name,
                                'category__in' => [$term->term_id],
                                'posts_per_page' => 1,
                            ]
                        );

                        $post_types[$post_type->name]['taxonomy_objects'][$taxonomy->name]['items'][] = [
                            'value' => $term->term_id,
                            'label' => $term->name,
                            'total' => $the_query->found_posts,
                        ];
                    }
                }
            }

            // Filter out taxonomy types without items
            $post_types[$post_type->name]['taxonomy_objects'] = array_filter(
                $post_types[$post_type->name]['taxonomy_objects'],
                function (array $taxonomy) {
                    return count($taxonomy['items']);
                }
            );
            $post_types[$post_type->name]['taxonomy_objects'] = array_values(
                $post_types[$post_type->name]['taxonomy_objects']
            );
        }


        wp_enqueue_style(
            'storychief-migrate-css',
            $uri.'/css/main.css',
            null,
            filemtime(STORYCHIEF_MIGRATE_DIR.'/css/main.css')
        );
        wp_enqueue_script(
            'storychief-migrate-js',
            $uri.'/dist/main.bundle.js',
            null,
            filemtime(STORYCHIEF_MIGRATE_DIR.'/dist/main.bundle.js'),
            true
        );

        wp_localize_script(
            'storychief-migrate-js',
            'scm',
            [
                'rest_api_url' => rest_url(''),
                'settings_url' => self::get_settings_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'completed' => $completed,
                'post_types' => $post_types,
            ]
        );

        require STORYCHIEF_MIGRATE_DIR.'/views/general.php';
    }

    public static function get_settings_url(): string
    {
        return admin_url('options-general.php?page=storychief-migrate');
    }

    public static function get_rest_url(): string
    {
        return defined('STORYCHIEF_REST_URI') ? STORYCHIEF_REST_URI : 'https://api.storychief.io/1.0';
    }

    public static function get_total_percentage(string $post_type, array $params)
    {
        $total_posts = Admin::get_total_posts($post_type, $params);
        $total_completed = Admin::get_total_completed($post_type, $params);

        if (!$total_posts) {
            return 100;
        }

        return $total_completed <= $total_posts ? ($total_completed / $total_posts * 100) : 100;
    }

    public static function get_page_url(): string
    {
        $args = ['page' => 'storychief-migrate'];

        return add_query_arg($args, admin_url('options-general.php'));
    }

    public static function prepare_query(string $post_type, array $filters): array
    {
        $post_query = [
            'post_type' => $post_type,
            'post_status' => $filters['post_status'],
            'posts_per_page' => 10,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids'
        ];

        if (isset($filters['filter_terms']) && is_array($filters['filter_terms']) && count($filters['filter_terms'])) {
            $post_query['category__in'] = $filters['filter_terms'];
        }

        return $post_query;
    }

    public static function get_total_posts(string $post_type, $params): int
    {
        $post_query = Admin::prepare_query($post_type, $params);
        $post_query['posts_per_page'] = 1;

        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', $post_query, 'get_total_posts')
        ))->found_posts;
    }

    public static function get_total_completed(string $post_type, array $params): int
    {
        $post_query = Admin::prepare_query($post_type, $params);
        $post_query['posts_per_page'] = 1;
        $post_query['meta_query'] = [
            [
                'key' => 'storychief_migrate_complete',
                'compare' => 'EXISTS',
            ]
        ];

        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', $post_query, 'get_total_completed')
        ))->found_posts;
    }

    public static function total_errors(string $post_type, array $params): int
    {
        $post_query = Admin::prepare_query($post_type, $params);
        $post_query['posts_per_page'] = 1;
        $post_query['meta_query'] = [
            [
                'key' => 'storychief_migrate_error',
                'compare' => 'EXISTS',
            ]
        ];

        return (new WP_Query(
            apply_filters('storychief_migrate_wp_query', $post_query, 'total_errors')
        ))->found_posts;
    }

    public static function get_errors(): WP_Query
    {
        $post_query = [
            'post_status' => ['publish', 'draft', 'future', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'storychief_migrate_error',
                    'compare' => 'EXISTS',
                ]
            ],
        ];

        return new WP_Query($post_query);
    }

    /**
     * Validate if the API-key works
     *
     * @param  string  $api_key
     * @return bool
     */
    public static function connection_check(string $api_key): bool
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
     * @param  string  $api_key
     * @param  int  $destination_id
     * @return bool
     */
    public static function destination_exists(string $api_key, int $destination_id): bool
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
<?php

namespace StoryChiefMigrate;

use WP_Error;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;

class Rest extends WP_REST_Controller
{
    public static function connection_check(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();

        return [
            'data' => [
                'success' => Admin::connection_check($params['api_key'] ?? null),
            ],
        ];
    }

    public static function save_api_key(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();

        if (extension_loaded('openssl')) {
            update_option('storychief_migrate_api_key', Admin::encrypt($params['api_key'], get_option('storychief_migrate_encryption_key')));
        }

        return [
            'data' => [
                'success' => true,
            ],
        ];
    }

    public static function get_api_key(WP_REST_Request $request): array
    {
        if (!extension_loaded('openssl')) {
            return [
                'data' => [
                    'api_key' => null,
                ],
            ];
        }

        $api_key = get_option('storychief_migrate_api_key');

        return [
            'data' => [
                'api_key' => $api_key ? Admin::decrypt($api_key, get_option('storychief_migrate_encryption_key')) : null,
            ],
        ];
    }

    /**
     * This route returns every WordPress destination inside StoryChief
     *
     * @param  WP_REST_Request  $request
     * @return WP_Error|array
     */
    public static function destinations(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $api_key = $params['api_key'] ?? null;

        if (!Admin::connection_check($api_key)) {
            return new WP_Error(
                'invalid_api_key',
                "Sorry, your API-key is incorrect",
                ['status' => 403]
            );
        }

        $response = wp_remote_get(
            Admin::get_rest_url().'/destinations?destination_type=wordpress',
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

        $data = array_filter(
            json_decode($response['body'], true)['data'],
            function (array $destination) {
                return $destination['status'] === 'configured';
            }
        );

        $data = array_map(
            function (array $destination) {
                return [
                    'id' => $destination['id'],
                    'name' => $destination['name'],
                ];
            },
            $data
        );

        return [
            'data' => $data,
        ];
    }

    protected static function errorConnection(): WP_Error
    {
        return new WP_Error(
            'invalid_api_key',
            "Sorry, your API-key is incorrect.",
            ['status' => 403]
        );
    }

    protected static function errorDestination(): WP_Error
    {
        return new WP_Error(
            'invalid_channel',
            "Sorry, that destination does not exist.",
            ['status' => 403]
        );
    }

    public static function preview(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        $post_query = Admin::prepare_query($params['post_type'], $params);
        $post_query['posts_per_page'] = -1;
        $post_query['fields'] = 'ids';
        $post_query['meta_query'] = [
            [
                'key' => 'storychief_migrate_complete',
                'compare' => 'NOT EXISTS',
            ]
        ];

        $post_query = apply_filters('storychief_migrate_wp_query', $post_query, 'preview');

        $the_post_query = new WP_Query($post_query);
        $total_posts = $the_post_query->found_posts;
        $total_categories = 0;
        $total_tags = 0;

        if ($total_posts && isset($params['category']) && is_string($params['category'])) {
            $category_query = [
                'taxonomy' => $params['category'],
                'object_ids' => $the_post_query->posts,
                'hide_empty' => false,
                'fields' => 'ids',
            ];

            $total_categories = count((new \WP_Term_Query($category_query))->terms);
        }

        if ($total_posts && isset($params['tag']) && is_string($params['tag'])) {
            $tag_query = [
                'taxonomy' => $params['tag'],
                'object_ids' => $the_post_query->posts,
                'hide_empty' => false,
                'fields' => 'ids',
            ];

            $total_tags = count((new \WP_Term_Query($tag_query))->terms);
        }

        return [
            'data' => [
                'total_posts' => $total_posts,
                'total_categories' => $total_categories,
                'total_tags' => $total_tags,
            ],
        ];
    }

    /**
     * @param  WP_REST_Request  $request
     * @return array[]|WP_Error
     */
    public static function run(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $api_key = $params['api_key'] ?? null;
        $destination_id = $params['destination_id'] ?? null;

        if (!Admin::connection_check($api_key)) {
            return self::errorConnection();
        }

        if (!Admin::destination_exists($api_key, $destination_id)) {
            return self::errorDestination();
        }

        set_time_limit(120); // Set a limit, in-case the server has no time limit

        $post_type = $params['post_type'];

        $post_query = Admin::prepare_query($post_type, $params);
        $post_query['posts_per_page'] = 5;
        $post_query['meta_query'] = [
            [
                'key' => 'storychief_migrate_complete',
                'compare' => 'NOT EXISTS',
            ]
        ];
        $the_query = new WP_Query(apply_filters('storychief_migrate_wp_query', $post_query, 'run'));

        $transfer = new Transfer(
            $api_key,
            $destination_id,
            $params['category'] ?? null,
            $params['tag'] ?? null
        );
        $transfer->execute($the_query);

        $total_posts = Admin::get_total_posts($post_type, $params);
        $total_completed = Admin::get_total_completed($post_type, $params);
        $total_percentage = Admin::get_total_percentage($post_type, $params);

        // Mark inside the migration as complete (when 100%), and ignore newer posts
        update_option('storychief_migrate_completed', $total_completed >= $total_posts);

        return [
            'data' => [
                'total_posts' => $total_posts,
                'total_completed' => $total_completed,
                'total_percentage' => $total_percentage,
                'total_failed' => Admin::total_errors($post_type, $params),
                'completed' => $total_completed >= $total_posts,
            ],
        ];
    }


    public static function errors(WP_REST_Request $request): array
    {
        $the_query = Admin::get_errors();
        $errors = [];

        while ($the_query->have_posts()) {
            $the_query->the_post();

            $post_error = get_post_meta(get_the_ID(), 'storychief_migrate_error', true);

            $errors[] = [
                'ID' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_the_permalink(),
                'category' => get_post_meta(get_the_ID(), 'storychief_migrate_category', true),
                'tag' => get_post_meta(get_the_ID(), 'storychief_migrate_tag', true),
                'error' => $post_error,
            ];
        }

        return [
            'data' => $errors,
        ];
    }

    public static function retry(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $post_id = $params['post_id'] ?? null;
        $api_key = Admin::decrypt(get_option('storychief_migrate_api_key'), get_option('storychief_migrate_encryption_key'));

        if (!Admin::connection_check($api_key)) {
            return self::errorConnection();
        }

        $destination_id = +get_post_meta($post_id, 'storychief_migrate_destination_id', true);
        $category = get_post_meta($post_id, 'storychief_migrate_category', true);
        $tag = get_post_meta($post_id, 'storychief_migrate_tag', true);

        if (!Admin::destination_exists($api_key, $destination_id)) {
            return self::errorDestination();
        }

        set_time_limit(120);

        $the_query = new WP_Query(
            [
                'post_status' => 'any',
                'post__in' => [$post_id],
            ]
        );

        $transfer = new Transfer(
            $api_key,
            $destination_id,
            $category,
            $tag
        );
        $transfer->execute($the_query);

        $error = null;

        if (Admin::has_error($post_id)) {
            $error = [
                'ID' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_the_permalink(),
                'category' => get_post_meta(get_the_ID(), 'storychief_migrate_category', true),
                'tag' => get_post_meta(get_the_ID(), 'storychief_migrate_tag', true),
                'error' => get_post_meta(get_the_ID(), 'storychief_migrate_error', true),
            ];
        }

        return [
            'data' => [
                'success' => !$error,
                'error' => $error,
            ]
        ];
    }
}
<?php

namespace StoryChiefMigrate;

use WP_Error;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;
use WP_Term;
use WP_User;
use WP_User_Query;

use function Storychief\Settings\get_sc_option;
use function Storychief\Settings\update_sc_option;

class Rest extends WP_REST_Controller
{
    public static function connection_check(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        return [
            'data' => [
                'success' => Admin::connection_check(isset($params['api_key']) ? $params['api_key'] : null),
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
        $api_key = isset($params['api_key']) ? $params['api_key'] : null;

        if (!Admin::connection_check($api_key)) {
            return new WP_Error(
                'invalid_api_key',
                "Sorry, your API-key is incorrect",
                ['status' => 403]
            );
        }

        $response = wp_remote_get(
            Admin::REST_URI.'/destinations?destination_type=wordpress',
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

    /**
     * @param  WP_REST_Request  $request
     * @return array[]|WP_Error
     */
    public static function run(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $api_key = isset($params['api_key']) ? $params['api_key'] : null;
        $destination_id = isset($params['destination_id']) ? $params['destination_id'] : null;

        if (!Admin::connection_check($api_key)) {
            return new WP_Error(
                'invalid_api_key',
                "Sorry, your API-key is incorrect.",
                ['status' => 403]
            );
        }

        if (!Admin::destination_exists($api_key, $destination_id)) {
            return new WP_Error(
                'invalid_api_key',
                "Sorry, that channel does not exist.",
                ['status' => 403]
            );
        }

        // Mapping of keys between WordPress and StoryChief
        $authors = self::get_authors($api_key);
        $post_type = get_sc_option('post_type');
        $posts_migrated = get_sc_option('posts_migrated');
        $categories = self::get_terms($api_key, 'categories');
        $tags = self::get_terms($api_key, 'tags');

        $the_user_query = new WP_User_Query(
            [
                'role' => ['administrator'],
            ]
        );
        $administrators = $the_user_query->get_results();

        if (!is_array($authors)) {
            $authors = [];
        }

        if (!is_array($posts_migrated)) {
            $posts_migrated = [];
        }


        $the_query = new WP_Query(
            [
                'post_type' => $post_type,
                'posts_per_page' => 5,
                'post__not_in' => $posts_migrated,
            ]
        );

        while ($the_query->have_posts()) {
            $the_query->the_post();

            $post = get_post(get_the_ID());
            $post_user = get_userdata($post->post_author);
            $post_categories = [];
            $post_tags = [];
            $post_published_at = date("Y-m-d\\TH:i:s+00:00", strtotime($post->post_date_gmt)); // W3C timestamp
            $post_author_id = null;
            $post_content = get_the_content(null, false, $post->ID);
            $post_content = apply_filters('the_content', str_replace(']]>', ']]&gt;', $post_content));

            if (!$post_user) {
                // Edge case:
                // Some posts may not have an author, this can be because the original user got deleted
                $post_user = $administrators[0];
            }

            $post_author_id = isset($authors[$post_user->user_email]) ?
                $authors[$post_user->user_email] :
                self::create_author($api_key, $post_user);

            foreach (wp_get_post_categories($post->ID) as $category_id) {
                /** @var WP_Term $category */
                $category = get_category($category_id);

                if (isset($categories[$category->slug])) {
                    $post_categories[] = $categories[$category->slug];
                } else {
                    // Create category through the API
                    $sc_category_id = self::create_term($api_key, $category, 'categories');

                    $categories[$category->slug] = $sc_category_id;
                    $post_categories[] = $sc_category_id;
                }
            }

            foreach (wp_get_post_tags($post->ID) as $tag_id) {
                /** @var WP_Term $tag */
                $tag = get_category($tag_id);

                if (isset($tags[$tag->slug])) {
                    $post_tags[] = $tags[$tag->slug];
                } else {
                    // Create tag through the API
                    $sc_tag_id = self::create_term($api_key, $tag, 'tags');

                    $tags[$tag->slug] = $sc_tag_id;
                    $post_tags[] = $sc_tag_id;
                }
            }

            // Apply hook:
            // Developers can use this hook, to add extra parameters such as language, source_id, ...
            // Read more: https://developers.storychief.io/
            $body = apply_filters(
                'storychief_migrate_alter_body',
                [
                    'title' => get_the_title(),
                    'content' => $post_content,
                        //'<img src="https://d2wvyaiai4v4wx.cloudfront.net/account_1/Guinea-pigs-Chip-Dash-06-Hara_a894a5ac36f1519281f1510c3596f9c8.jpg">',
                    'excerpt' => get_the_excerpt(),
                    'slug' => $post->post_name,
                    'custom_fields' => [],
                    'seo_title' => get_the_title(),
                    'seo_description' => get_the_excerpt(),
                    'categories' => $post_categories,
                    'tags' => $post_tags,
                    'author_id' => $post_author_id,
                ],
                $post
            );

            $response = wp_remote_post(
                Admin::REST_URI.'/stories',
                [
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$api_key,
                    ],
                    'sslverify' => false,
                    'body' => json_encode($body),
                ]
            );

            $data = json_decode($response['body'], true);

            if ($response['response']['code'] >= 400) {
                return new WP_Error(
                    'invalid_data',
                    $data['message'], [
                        'status' => 400,
                        'errors' => $data['errors'],
                        'post' => $body,
                    ]
                );
            }

            $posts_migrated[] = $post->ID;

            // Mark the current post as migrated
            update_sc_option('posts_migrated', $posts_migrated);

            // Set the story in SC as published
            wp_remote_post(
                Admin::REST_URI.'/stories/'.$data['data']['id'].'/destinations',
                [
                    'method' => 'PUT',
                    'timeout' => 10,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$api_key,
                    ],
                    'sslverify' => false,
                    'body' => json_encode(
                        [
                            'published_at' => $post_published_at,
                            'external_id' => $post->ID,
                            'destination_id' => $destination_id,
                            'external_url' => get_post_permalink($post->ID),
                        ]
                    ),
                ]
            );
        }

        wp_reset_postdata();

        $total_posts = Admin::get_total_posts();
        $total_completed = Admin::get_total_completed();
        $total_percentage = Admin::get_total_percentage();

        // Mark inside the migration as complete (when 100%), and ignore newer posts
        update_sc_option('migrate_completed', $total_completed >= $total_posts);

        return [
            'data' => [
                'total_posts' => $total_posts,
                'total_completed' => $total_completed,
                'total_percentage' => $total_percentage,
                'completed' => $total_completed >= $total_posts,
            ],
        ];
    }

    protected static function get_authors($api_key)
    {
        $authors = [];
        $response = wp_remote_get(
            Admin::REST_URI.'/authors?count=200',
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

        $json = json_decode($response['body'], true);

        foreach ($json['data'] as $author) {
            $authors[$author['email']] = $author['id'];
        }

        return $authors;
    }

    protected static function get_terms($api_key, $type)
    {
        $data = [];
        $response = wp_remote_get(
            Admin::REST_URI.'/'.$type.'?count=500',
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

        $json = json_decode($response['body'], true);

        foreach ($json['data'] as $row) {
            $data[$row['slug']] = $row['id'];
        }

        return $data;
    }

    protected static function create_author($api_key, WP_User $user)
    {
        // 1. check if the user exists as an author
        $data = [
            'email' => $user->user_email,
            'firstname' => get_user_meta($user->ID, 'first_name', true),
            'lastname' => get_user_meta($user->ID, 'last_name', true),
            'description' => get_user_meta($user->ID, 'description', true),
            'profile_picture' => get_avatar_url(
                $user->ID,
                [
                    'size' => 200,
                ]
            ),
        ];

        $response = wp_remote_post(
            Admin::REST_URI.'/authors',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$api_key,
                ],
                'sslverify' => false,
                // Hook: allow to add Twitter, Facebook, ... link
                'body' => json_encode(apply_filters('storychief_migrate_user_data', $data, $user->ID)),
            ]
        );

        $json = json_decode($response['body'], true);

        return $json['data']['id'];
    }

    protected static function create_term($api_key, WP_Term $term, $type)
    {
        $response = wp_remote_post(
            Admin::REST_URI.'/'.$type,
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$api_key,
                ],
                'sslverify' => false,
                'body' => json_encode(
                    [
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ]
                ),
            ]
        );

        $json = json_decode($response['body'], true);

        return $json['data']['id'];
    }
}
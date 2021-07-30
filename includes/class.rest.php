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
use function Storychief\Webhook\storychief_debug_mode;

class Rest extends WP_REST_Controller
{
    public static function connection_check(WP_REST_Request $request)
    {
        storychief_debug_mode();

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
        storychief_debug_mode();

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

    /**
     * @param  WP_REST_Request  $request
     * @return array[]|WP_Error
     */
    public static function run(WP_REST_Request $request)
    {
        storychief_debug_mode();

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

        $the_query = new WP_Query(
            [
                'post_status' => 'any',
                'post_type' => get_sc_option('post_type'),
                'posts_per_page' => 5,
                'meta_query' => [
                    [
                        'key' => 'storychief_migrate_complete',
                        'compare' => 'NOT EXISTS',
                    ]
                ]
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

            $post_body = [
                'title' => get_the_title(),
                'content' => $post_content,
                'excerpt' => get_the_excerpt(),
                'slug' => $post->post_name,
                'custom_fields' => [],
                'seo_title' => get_the_title(),
                'seo_description' => get_the_excerpt(),
                'categories' => $post_categories,
                'tags' => $post_tags,
                'author_id' => $post_author_id,
            ];

            if (has_post_thumbnail()) {
                $post_body['featured_image'] = get_the_post_thumbnail_url($post->ID, 'full');
                $post_body['featured_image_alt'] = get_post_meta(
                    get_post_thumbnail_id(),
                    '_wp_attachment_image_alt',
                    true
                );
            }

            $body = apply_filters(
                'storychief_migrate_alter_body',
                $post_body,
                $post
            );

            $response = wp_remote_post(
                Admin::get_rest_url().'/stories',
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
            update_post_meta($post->ID, 'storychief_migrate_complete', 1);

            if ($response instanceof WP_Error) {
                /** @var WP_Error $response */
                update_post_meta(
                    $post->ID,
                    'storychief_migrate_error',
                    [
                        'code' => 500,
                        'message' => $response->get_error_message(),
                        'type' => 'curl',
                        'errors' => $response->errors,
                    ]
                );
                continue;
            }

            $data = json_decode($response['body'], true);
            $code = (int)floor(round($response['response']['code']) / 100) * 100;

            if ($response['response']['code'] >= 400) {
                if ($code === 400) {
                    update_post_meta(
                        $post->ID,
                        'storychief_migrate_error',
                        [
                            'code' => $response['response']['code'],
                            'message' => $response['response']['message'],
                            'type' => 'invalid_request',
                            'errors' => $response['response']['errors'],
                        ]
                    );
                    continue;
                }

                if ($code === 500) {
                    update_post_meta(
                        $post->ID,
                        'storychief_migrate_error',
                        [
                            'code' => $response['response']['code'],
                            'message' => $response['response']['message'],
                            'type' => 'internal_server_error',
                            'errors' => [],
                        ]
                    );
                    continue;
                }
            }

            // Set the story in SC as published
            wp_remote_post(
                Admin::get_rest_url().'/stories/'.$data['data']['id'].'/destinations',
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
            Admin::get_rest_url().'/authors?count=200',
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
            Admin::get_rest_url().'/'.$type.'?count=500',
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
        $author = [
            'email' => $user->user_email,
            'profile_picture' => get_avatar_url(
                $user->ID,
                [
                    'size' => 200,
                ]
            ),
        ];

        $firstname = get_user_meta($user->ID, 'first_name', true);
        $lastname = get_user_meta($user->ID, 'last_name', true);
        $bio = get_user_meta($user->ID, 'description', true);

        $author['firstname'] = !empty($firstname) ? $firstname : '-';

        if (!empty($lastname)) {
            $author['lastname'] = $lastname;
        }

        if (!empty($bio)) {
            $author['bio'] = $bio;
        }

        // The hook allows to add a Twitter, Facebook link
        $body = apply_filters('storychief_migrate_alter_create_author', $author, $user);

        $response = wp_remote_post(
            Admin::get_rest_url().'/authors',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$api_key,
                ],
                'sslverify' => false,
                'body' => json_encode($body),
            ]
        );

        $json = json_decode($response['body'], true);

        return $json['data']['id'];
    }

    /**
     * @param  string  $api_key
     * @param  WP_Term  $term
     * @param  string  $type  'categories' or 'tags'
     * @return int
     */
    protected static function create_term($api_key, WP_Term $term, $type)
    {
        $response = wp_remote_post(
            Admin::get_rest_url().'/'.$type,
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
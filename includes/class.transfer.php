<?php

namespace StoryChiefMigrate;

use WP_Error;
use WP_Query;
use WP_Term;
use WP_User_Query;

class Transfer
{
    protected $api_key;
    protected $destination_id;
    protected $category;
    protected $tag;
    protected $authors;
    protected $administrators;
    protected $categories;
    protected $tags;

    public function __construct(string $api_key, string $destination_id, $category, $tag)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Show errors, to avoid the default error message
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('error_reporting', 1);
            ini_set('html_errors', 1);
        }

        $this->api_key = $api_key;
        $this->destination_id = $destination_id;
        $this->category = $category;
        $this->tag = $tag;
    }

    public function execute(WP_Query $the_query)
    {;
        // Mapping of keys between WordPress and StoryChief
        $authors = Admin::get_authors($this->api_key);
        $categories = $this->category ? Admin::get_terms($this->api_key, 'categories') : [];
        $tags = $this->tag ? Admin::get_terms($this->api_key, 'tags') : [];

        $the_user_query = new WP_User_Query(
            [
                'role' => ['administrator'],
            ]
        );
        $administrators = $the_user_query->get_results();

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
            $post_content = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $post_content);

            if (!$post_user) {
                // Edge case:
                // Some posts may not have an author, this can be because the original user got deleted
                $post_user = $administrators[0];
            }

            if (isset($authors[$post_user->user_email])) {
                $post_author_id = $authors[$post_user->user_email];
            } else {
                // If the author doesn't exist, create that one in StoryChief
                $post_author_id = $authors[$post_user->user_email] = Admin::create_author($this->api_key, $post_user);
            }

            if ($this->category && ($terms = get_the_terms($post->ID, $this->category))) {
                foreach ($terms as $category) {
                    if (isset($categories[$category->slug])) {
                        $post_categories[] = $categories[$category->slug];
                    } else {
                        // Create category through the API
                        $sc_category_id = Admin::create_term($this->api_key, $category, 'categories');

                        $categories[$category->slug] = $sc_category_id;
                        $post_categories[] = $sc_category_id;
                    }
                }
            }

            if ($this->tag && ($terms = get_the_terms($post->ID, $this->tag))) {
                foreach ($terms as $tag) {
                    if (isset($tags[$tag->slug])) {
                        $post_tags[] = $tags[$tag->slug];
                    } else {
                        // Create tag through the API
                        $sc_tag_id = Admin::create_term($this->api_key, $tag, 'tags');

                        $tags[$tag->slug] = $sc_tag_id;
                        $post_tags[] = $sc_tag_id;
                    }
                }
            }

            // Apply hook:
            // Developers can use this hook, to add extra parameters such as language, source_id, ...
            // Read more: https://developers.storychief.io/

            $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : null;

            $post_body = [
                'title' => get_the_title(),
                'content' => $post_content,
                'slug' => $post->post_name,
                'custom_fields' => [],
                'seo_title' => get_the_title(),
                'seo_description' => $excerpt,
                'categories' => $post_categories,
                'tags' => $post_tags,
                'author_id' => $post_author_id,
            ];

            if (has_post_thumbnail()) {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');

                if ($image && isset($image[0])) {
                    $image_url = str_replace(basename($image[0]), '', $image[0]).rawurlencode(basename($image[0]));

                    // Replace white space with %20, or else URL validation fails
                    $post_body['featured_image'] = $image_url;
                    $post_body['featured_image_alt'] = get_post_meta(
                        get_post_thumbnail_id(),
                        '_wp_attachment_image_alt',
                        true
                    );
                }
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
                        'Authorization' => 'Bearer '.$this->api_key,
                    ],
                    'sslverify' => false,
                    'body' => json_encode($body),
                ]
            );
            // Mark this post as complete
            update_post_meta($post->ID, 'storychief_migrate_complete', 1);

            // Keep the mapping, for possible retry
            update_post_meta($post->ID, 'storychief_migrate_category', $this->category);
            update_post_meta($post->ID, 'storychief_migrate_tag', $this->tag);
            update_post_meta($post->ID, 'storychief_migrate_destination_id', $this->destination_id);

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
                            'message' => $data['message'] ?? $response['response']['message'],
                            'type' => 'invalid_request',
                            'errors' => $data['errors'] ?? [],
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

            // Remove error, in retry
            delete_post_meta($post->ID, 'storychief_migrate_error');

            // Set the story in SC as published
            wp_remote_post(
                Admin::get_rest_url().'/stories/'.$data['data']['id'].'/destinations',
                [
                    'method' => 'PUT',
                    'timeout' => 10,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$this->api_key,
                    ],
                    'sslverify' => false,
                    'body' => json_encode(
                        [
                            'published_at' => $post_published_at,
                            'external_id' => $post->ID,
                            'destination_id' => $this->destination_id,
                            'external_url' => get_post_permalink($post->ID),
                        ]
                    ),
                ]
            );
        }
        wp_reset_postdata();
    }
}
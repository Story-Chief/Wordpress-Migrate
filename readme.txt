=== StoryChief ===
Contributors: StoryChief
Donate link: https://storychief.io
Tags: Content marketing calendar, social media scheduling, content marketing, schedule facebook posts, schedule to twitter, schedule posts to Linkedin, social media analytics
Requires at least: 4.6
Tested up to: 5.6
Requires PHP: 7.2
Stable tag: 0.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Migrate all your existing WordPress posts, to your all-in-one Content Marketing Workspace.

== Description ==

Migrate all your existing WordPress posts, to your all-in-one Content Marketing Workspace.

This plugin:

*  Copies your existing posts inside WordPress to StoryChief

=== How It Works ===

1. Register on [StoryChief](https://app.storychief.io/register)
2. Add a WordPress channel
3. Install and activate the [main plugin](https://wordpress.org/plugins/story-chief/) first
4. Configure the plugin by saving your encryption key
5. Publish from StoryChief to your WordPress website
6. Install the migrate plugin
7. Create an API-key and use it on the StoryChief Migrate settings page

=== Requirements ===

* This plugin requires a [StoryChief](https://storychief.io) account.
	* Not a StoryChief user yet? [Sign up for free!](https://app.storychief.io/register)
* PHP version 5.4 or higher
* This plugin requires the [main StoryChief plugin](https://wordpress.org/plugins/story-chief/) as a dependency

=== Warning ===

* StoryChief doesn't support tables and columns, these tags won't be imported properly
* This plugin doesn't handle multi-lang or custom fields, instead you can use the mentioned hooks

=== Actions and filters ===

Developers: This plugin has numerous [actions and filters](https://codex.wordpress.org/Plugin_API) available that can be used to modify the default behaviour of the plugin.

==== Filter: storychief_migrate_alter_body ====

This plugin doesn't handle multi-lang or custom fields, instead you can use the following hook to
extends the what is being send back to storychief.

* [Custom Fields](https://help.storychief.io/en/articles/5376131-custom-fields) in StoryChief
* [Manage multi-language content](https://help.storychief.io/en/articles/913139-manage-multi-language-content) in StoryChief
* [API documentation](https://developers.storychief.io/#6cb1bcf5-5132-46b5-99b3-ae72705fbd2e)

```php
<?php

function example_storychief_migrate_alter_body (array $body, \WP_Post $post) {
    /* === Example handle ACF Custom Fields === */

    // Text, numbers, email
    $body['custom_fields']['cfApiKeyTextField'] = get_field('text_field', $post->ID);

    // Image
    $image = get_field('image_field', $post->ID);
    $body['custom_fields']['cfApiKeyImageField'] = $image['url']; // For images we need the full URL

    // File
    $file = get_field('file_field', $post->ID);
    $body['custom_fields']['cfApiKeyFileField'] = $file['url']; // For files we need the full URL

    /* === Example handle languages === */

    $body['language'] = get_locale();

    return $body;
}
add_filter('storychief_migrate_alter_body', 'example_storychief_migrate_alter_body', null, 2);

```
==== Filter: storychief_migrate_alter_create_author ====

You hook on altering the author data, send back to storychief.io

* [API documentation](https://developers.storychief.io/#2c7072d9-b879-4088-b96d-7fd94ccbdb98)

```php
<?php

function example_storychief_migrate_alter_body (array $author, \WP_User $user) {
    /* === Example add Facebook, Twitter, ... links === */

    $author['instagram_link'] = get_field('instagram_link', 'user_' . $user->ID);
    $author['linkedin_link'] = get_field('linkedin_link', 'user_' . $user->ID);
    $author['twitter_link'] = get_field('twitter_link', 'user_' . $user->ID);
    $author['facebook_link'] = get_field('facebook_link', 'user_' . $user->ID);

    return $author;
}
add_filter('storychief_migrate_alter_create_author', 'example_storychief_migrate_alter_create_author', null, 2);

```

==== Filter: storychief_migrate_wp_query ====

This hooks allows you, to alter the parameters send to WP Query. This can be useful to modify the post type or only migrate posts that contains a specific category.

* [WP Query documentation](https://developer.wordpress.org/reference/classes/wp_query/)

```php
<?php
function example_storychief_migrate_wp_query (array $query_args, $query_type) {
    // documentation: https://developer.wordpress.org/reference/classes/wp_query/#parameters
    
    // 1. example: Override the post type value
    $query_args['post_type'] = 'vlog';

    // 2. example: only get posts that contain a specific category
    // documentation: https://developer.wordpress.org/reference/classes/wp_query/#category-parameters
    $query_args['category__in'] = [20];

    $query_args['post_status'] = ['publish']; // Only retrieve published posts and ignore drafts

    return $query_args;
}
add_filter('storychief_migrate_wp_query', 'example_storychief_migrate_wp_query', null, 2);

```

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress
3.  Use the Settings -> StoryChief screen to configure the plugin
4.  Copy over your api key from StoryChief

== Frequently Asked Questions ==

Find our complete FAQ [here](https://help.storychief.io/en/?q=wordpress)

== Changelog ==

= 0.1 =
* Migrate your posts from WordPress to StoryChief
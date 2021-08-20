<?php

add_action('init', function () {
    register_post_type('blog', [
        'label' => 'Blog',
        'public' => true,
        'publicly_queryable' => true,
        'show_in_rest' => true,
        'show_in_admin_bar' => true,
    ]);

    register_taxonomy(
        'blog_keyword',
        'blog',
        // post type name
        array(
            'labels' => array(
                'name' => 'Keywords',
                'singular_name' => 'Keyword',
                'search_items' => __('Search Keywords'),
                'all_items' => __('All Keywords'),
                'parent_item' => __('Parent Keyword'),
                'parent_item_colon' => __('Parent Keyword:'),
                'edit_item' => __('Edit Keyword'),
                'update_item' => __('Update Keyword'),
                'add_new_item' => __('Add New Keyword'),
                'new_item_name' => __('New Keyword'),
                'menu_name' => __('Keywords'),
                'not_found' => __('No Keywords found')
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'public' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => array('slug' => 'keyword'),
        )
    );

    register_taxonomy(
        'blog_tag',
        // The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
        'blog',
        // post type name
        array(
            'labels' => array(
                'name' => 'Blog tags',
                'singular_name' => 'Blog tag',
                'search_items' => __('Search Blog tags'),
                'all_items' => __('All Blog tags'),
                'parent_item' => __('Parent Blog tag'),
                'parent_item_colon' => __('Parent Blog tag:'),
                'edit_item' => __('Edit Blog tag'),
                'update_item' => __('Update Blog tag'),
                'add_new_item' => __('Add New Blog tag'),
                'new_item_name' => __('New Blog tag'),
                'menu_name' => __('Blog tags'),
                'not_found' => __('No Blog tags found')
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'public' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => array('slug' => 'keyword'),
        )
    );
});
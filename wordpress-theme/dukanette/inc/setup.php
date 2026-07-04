<?php
if (!defined('ABSPATH')) {
    exit;
}

function dukanette_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => __('Menu principal', 'dukanette'),
        'footer'  => __('Menu pied de page', 'dukanette'),
    ]);
}
add_action('after_setup_theme', 'dukanette_setup');

/**
 * Recipe content already uses real Unicode emoji, so WP's emoji shim (which
 * fetches a script from s.w.org for older browsers) is dead weight.
 */
function dukanette_disable_emoji() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'dukanette_disable_emoji');

/**
 * The three structural categories of the site; anything else in the
 * database is stray/misc terms (spam taxonomy injection, a couple of
 * misfiled posts) and shouldn't show up in navigation.
 */
function dukanette_main_category_slugs() {
    return ['recette', 'sale', 'sucre'];
}

function dukanette_main_categories() {
    $slugs = dukanette_main_category_slugs();
    $categories = get_categories([
        'hide_empty' => true,
        'slug'       => $slugs,
    ]);

    usort($categories, function ($a, $b) use ($slugs) {
        return array_search($a->slug, $slugs) <=> array_search($b->slug, $slugs);
    });

    return $categories;
}

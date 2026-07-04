<?php
/**
 * Lightweight view counter stored in post meta — no plugin, no extra table.
 * Powers the "Les plus consultées" section and page.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUKANETTE_VIEWS_META_KEY', 'dukanette_views');

function dukanette_count_view() {
    if (!is_singular('post') || is_preview() || is_admin()) {
        return;
    }
    // Don't let the site owner's own browsing skew the ranking.
    if (current_user_can('edit_posts')) {
        return;
    }
    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }
    $views = (int) get_post_meta($post_id, DUKANETTE_VIEWS_META_KEY, true);
    update_post_meta($post_id, DUKANETTE_VIEWS_META_KEY, $views + 1);
}
add_action('template_redirect', 'dukanette_count_view');

function dukanette_get_views($post_id) {
    return (int) get_post_meta($post_id, DUKANETTE_VIEWS_META_KEY, true);
}

function dukanette_get_popular_posts($count = 4, $paged = 1) {
    return new WP_Query([
        'posts_per_page' => $count,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'meta_key'       => DUKANETTE_VIEWS_META_KEY,
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ]);
}

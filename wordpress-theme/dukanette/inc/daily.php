<?php
/**
 * "Menu du jour" and "info du jour" — deterministic per calendar day (same
 * pick for every visitor, changes at midnight), cached in a transient so we
 * don't re-run the picking query on every request.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dukanette_get_daily_pick($category_slug) {
    $today = current_time('Y-m-d');
    $transient_key = 'dukanette_daily_' . $category_slug . '_' . $today;

    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached ?: null;
    }

    $category = get_category_by_slug($category_slug);
    if (!$category || !$category->count) {
        set_transient($transient_key, 0, DAY_IN_SECONDS);
        return null;
    }

    $seed = crc32($today . '-' . $category_slug);
    $offset = $seed % $category->count;

    $query = new WP_Query([
        'category_name'  => $category_slug,
        'posts_per_page' => 1,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);

    $post = $query->have_posts() ? $query->posts[0] : null;
    set_transient($transient_key, $post ?: 0, DAY_IN_SECONDS);

    return $post;
}

function dukanette_get_daily_tip() {
    $raw = get_option('dukanette_daily_tips', '');
    if ($raw === '') {
        $raw = dukanette_default_daily_tips();
    }
    $tips = array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));
    if (!$tips) {
        return '';
    }
    $index = (int) current_time('z') % count($tips);
    return $tips[$index];
}

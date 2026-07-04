<?php
/**
 * Favorites are stored as a simple array of post IDs in user meta —
 * no extra database table needed.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUKANETTE_FAVORITES_META_KEY', 'dukanette_favorite_posts');

function dukanette_get_user_favorites($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return [];
    }
    $favorites = get_user_meta($user_id, DUKANETTE_FAVORITES_META_KEY, true);
    return is_array($favorites) ? array_map('intval', $favorites) : [];
}

function dukanette_is_favorited($post_id, $user_id = null) {
    return in_array((int) $post_id, dukanette_get_user_favorites($user_id), true);
}

function dukanette_toggle_favorite() {
    check_ajax_referer('dukanette_ajax', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Connecte-toi pour ajouter des favoris.', 'dukanette')], 401);
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || get_post_status($post_id) !== 'publish') {
        wp_send_json_error(['message' => __('Article introuvable.', 'dukanette')], 404);
    }

    $user_id = get_current_user_id();
    $favorites = dukanette_get_user_favorites($user_id);

    if (in_array($post_id, $favorites, true)) {
        $favorites = array_values(array_diff($favorites, [$post_id]));
        $favorited = false;
    } else {
        $favorites[] = $post_id;
        $favorited = true;
    }

    update_user_meta($user_id, DUKANETTE_FAVORITES_META_KEY, $favorites);

    wp_send_json_success(['favorited' => $favorited]);
}
add_action('wp_ajax_dukanette_toggle_favorite', 'dukanette_toggle_favorite');

<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves account/utility pages by slug rather than hardcoding paths, so
 * links keep working whether the site uses pretty permalinks or the
 * default "plain" ?page_id= structure.
 */
function dukanette_page_url($slug) {
    static $cache = [];
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }
    $page = get_page_by_path($slug);
    $cache[$slug] = $page ? get_permalink($page) : home_url('/' . $slug . '/');
    return $cache[$slug];
}

function dukanette_login_url($redirect_to) {
    return add_query_arg('redirect_to', rawurlencode($redirect_to), dukanette_page_url('login'));
}

function dukanette_favorite_button($post_id) {
    if (!is_user_logged_in()) {
        printf(
            '<a href="%s" class="favorite-btn" aria-label="%s">%s</a>',
            esc_url(dukanette_login_url(get_permalink($post_id))),
            esc_attr__('Se connecter pour ajouter aux favoris', 'dukanette'),
            '♡'
        );
        return;
    }

    $active = dukanette_is_favorited($post_id);
    printf(
        '<button type="button" class="favorite-btn%s" data-post-id="%d" aria-pressed="%s" aria-label="%s">%s</button>',
        $active ? ' is-active' : '',
        (int) $post_id,
        $active ? 'true' : 'false',
        esc_attr__('Ajouter aux favoris', 'dukanette'),
        $active ? '❤' : '♡'
    );
}

function dukanette_pagination() {
    $links = paginate_links([
        'prev_text' => '← ' . __('Précédent', 'dukanette'),
        'next_text' => __('Suivant', 'dukanette') . ' →',
        'type'      => 'array',
    ]);

    if (!$links) {
        return;
    }

    echo '<nav class="pagination" aria-label="' . esc_attr__('Pagination', 'dukanette') . '">';
    foreach ($links as $link) {
        echo wp_kses_post($link);
    }
    echo '</nav>';
}

function dukanette_post_card($post_id = null, $priority = false) {
    $post_id = $post_id ?: get_the_ID();
    set_query_var('dukanette_card_post_id', $post_id);
    set_query_var('dukanette_card_priority', $priority);
    get_template_part('template-parts/post-card');
}

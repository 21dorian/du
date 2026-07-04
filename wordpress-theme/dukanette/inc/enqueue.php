<?php
if (!defined('ABSPATH')) {
    exit;
}

function dukanette_enqueue_assets() {
    wp_enqueue_style(
        'dukanette-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style('dukanette-main', DUKANETTE_URI . '/assets/css/main.css', ['dukanette-fonts'], DUKANETTE_VERSION);
    wp_enqueue_script('dukanette-main', DUKANETTE_URI . '/assets/js/main.js', [], DUKANETTE_VERSION, true);

    wp_localize_script('dukanette-main', 'dukanetteData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('dukanette_ajax'),
        'isLoggedIn' => is_user_logged_in(),
        'loginUrl' => wp_login_url(get_permalink()),
    ]);

    if (is_singular('post') && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'dukanette_enqueue_assets');

function dukanette_font_preconnect() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
add_action('wp_head', 'dukanette_font_preconnect', 1);

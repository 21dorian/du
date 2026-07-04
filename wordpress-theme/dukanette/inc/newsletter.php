<?php
/**
 * Newsletter signup: API key is entered by the site owner in the
 * Customizer (never hardcoded), subscription happens server-side via
 * Buttondown's API so the key never reaches the browser.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dukanette_customize_register($wp_customize) {
    $wp_customize->add_section('dukanette_newsletter', [
        'title'    => __('Newsletter', 'dukanette'),
        'priority' => 160,
    ]);

    $wp_customize->add_setting('dukanette_buttondown_api_key', [
        'type'              => 'option',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    $wp_customize->add_control('dukanette_buttondown_api_key', [
        'label'       => __('Clé API Buttondown', 'dukanette'),
        'description' => __('Créée sur buttondown.email — Réglages > Emails sortants > API Key.', 'dukanette'),
        'section'     => 'dukanette_newsletter',
        'type'        => 'text',
    ]);
}
add_action('customize_register', 'dukanette_customize_register');

function dukanette_newsletter_subscribe() {
    check_ajax_referer('dukanette_ajax', 'nonce');

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => __('Adresse email invalide.', 'dukanette')], 400);
    }

    $api_key = get_option('dukanette_buttondown_api_key');
    if (!$api_key) {
        wp_send_json_error(['message' => __('Newsletter pas encore configurée.', 'dukanette')], 503);
    }

    $response = wp_remote_post('https://api.buttondown.email/v1/subscribers', [
        'headers' => [
            'Authorization' => 'Token ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode(['email' => $email]),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => __('Inscription impossible pour le moment.', 'dukanette')], 502);
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status >= 400 && $status !== 409) {
        wp_send_json_error(['message' => __('Inscription impossible pour le moment.', 'dukanette')], 502);
    }

    wp_send_json_success();
}
add_action('wp_ajax_dukanette_newsletter_subscribe', 'dukanette_newsletter_subscribe');
add_action('wp_ajax_nopriv_dukanette_newsletter_subscribe', 'dukanette_newsletter_subscribe');

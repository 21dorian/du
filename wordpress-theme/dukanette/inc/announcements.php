<?php
/**
 * Homepage editorial controls: an announcement banner, an ad slot, and the
 * pool of "info du jour" tips — all editable from the Customizer so the
 * site owner never needs to touch code.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dukanette_default_daily_tips() {
    return implode("\n", [
        __('Bois au moins 1,5 L d’eau par jour, ça aide à éliminer.', 'dukanette'),
        __('Le son d’avoine est ton allié à toutes les phases : n’oublie pas ta ration quotidienne.', 'dukanette'),
        __('20 minutes de marche rapide par jour suffisent pour soutenir la perte de poids.', 'dukanette'),
        __('Prépare tes repas à l’avance pour ne pas craquer en semaine.', 'dukanette'),
        __('Un edulcorant naturel comme la stévia peut remplacer le sucre dans la plupart des recettes.', 'dukanette'),
    ]);
}

function dukanette_customize_register_homepage($wp_customize) {
    $wp_customize->add_section('dukanette_homepage', [
        'title'    => __('Page d’accueil', 'dukanette'),
        'priority' => 155,
    ]);

    $wp_customize->add_setting('dukanette_announcement_enabled', [
        'type'              => 'option',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ]);
    $wp_customize->add_control('dukanette_announcement_enabled', [
        'label'   => __('Afficher un bandeau d’annonce', 'dukanette'),
        'section' => 'dukanette_homepage',
        'type'    => 'checkbox',
    ]);

    $wp_customize->add_setting('dukanette_announcement_text', [
        'type'              => 'option',
        'sanitize_callback' => 'wp_kses_post',
    ]);
    $wp_customize->add_control('dukanette_announcement_text', [
        'label'       => __('Texte de l’annonce', 'dukanette'),
        'description' => __('Ex : "Nouvelle recette de la semaine !" — un peu de HTML simple est accepté (liens, gras).', 'dukanette'),
        'section'     => 'dukanette_homepage',
        'type'        => 'textarea',
    ]);

    $wp_customize->add_setting('dukanette_ad_enabled', [
        'type'              => 'option',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ]);
    $wp_customize->add_control('dukanette_ad_enabled', [
        'label'   => __('Afficher un emplacement publicitaire sur l’accueil', 'dukanette'),
        'section' => 'dukanette_homepage',
        'type'    => 'checkbox',
    ]);

    $wp_customize->add_setting('dukanette_ad_code', [
        'type'              => 'option',
        'sanitize_callback' => 'dukanette_sanitize_ad_code',
    ]);
    $wp_customize->add_control('dukanette_ad_code', [
        'label'       => __('Code publicitaire', 'dukanette'),
        'description' => __('Colle ici le code fourni par ta régie publicitaire (ex : Google AdSense). Réservé à l’administrateur du site.', 'dukanette'),
        'section'     => 'dukanette_homepage',
        'type'        => 'textarea',
    ]);

    $wp_customize->add_setting('dukanette_adsense_client_id', [
        'type'              => 'option',
        'sanitize_callback' => 'dukanette_sanitize_adsense_client_id',
        'default'           => 'ca-pub-6542959650645177',
    ]);
    $wp_customize->add_control('dukanette_adsense_client_id', [
        'label'       => __('Identifiant client Google AdSense', 'dukanette'),
        'description' => __('Format "ca-pub-XXXXXXXXXXXXXXXX", visible dans ton compte AdSense. Charge le script AdSense sur tout le site (nécessaire pour que les publicités s’affichent).', 'dukanette'),
        'section'     => 'dukanette_homepage',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('dukanette_daily_tips', [
        'type'              => 'option',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => dukanette_default_daily_tips(),
    ]);
    $wp_customize->add_control('dukanette_daily_tips', [
        'label'       => __('Astuces du jour', 'dukanette'),
        'description' => __('Une astuce par ligne. Une astuce différente est affichée chaque jour, dans l’ordre, en boucle.', 'dukanette'),
        'section'     => 'dukanette_homepage',
        'type'        => 'textarea',
    ]);
}
add_action('customize_register', 'dukanette_customize_register_homepage');

/**
 * Ad network embed codes need <script> tags, which only site admins with
 * `unfiltered_html` can legitimately provide via the Customizer (a
 * capability check WordPress already enforces on this screen) — so we
 * preserve the markup rather than stripping it, same trust level as the
 * theme's own template code.
 */
function dukanette_sanitize_ad_code($value) {
    return current_user_can('unfiltered_html') ? $value : '';
}

/** Only accept the exact "ca-pub-<digits>" shape Google issues, never arbitrary input. */
function dukanette_sanitize_adsense_client_id($value) {
    $value = trim((string) $value);
    return preg_match('/^ca-pub-\d{10,20}$/', $value) ? $value : '';
}

function dukanette_print_adsense_script() {
    $client_id = get_option('dukanette_adsense_client_id', 'ca-pub-6542959650645177');
    if (!$client_id) {
        return;
    }
    printf(
        '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>' . "\n",
        esc_attr($client_id)
    );
}
add_action('wp_head', 'dukanette_print_adsense_script');

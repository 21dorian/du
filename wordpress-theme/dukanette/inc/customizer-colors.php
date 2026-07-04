<?php
/**
 * Color controls in the Customizer so the site owner can retint the theme
 * without touching code. Also pins the site to the light palette by
 * default — the automatic dark mode surprised more than it helped.
 */

if (!defined('ABSPATH')) {
    exit;
}

const DUKANETTE_DEFAULT_ACCENT = '#a8853b';
const DUKANETTE_DEFAULT_PAPER = '#faf5ec';

function dukanette_customize_register_colors($wp_customize) {
    $wp_customize->add_section('dukanette_colors', [
        'title'    => __('Couleurs du thème', 'dukanette'),
        'priority' => 150,
    ]);

    $wp_customize->add_setting('dukanette_color_accent', [
        'type'              => 'option',
        'default'           => DUKANETTE_DEFAULT_ACCENT,
        'sanitize_callback' => 'sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'dukanette_color_accent', [
        'label'       => __('Couleur d’accent', 'dukanette'),
        'description' => __('Boutons, liens, pastilles, numéros du palmarès (or champagne par défaut).', 'dukanette'),
        'section'     => 'dukanette_colors',
    ]));

    $wp_customize->add_setting('dukanette_color_paper', [
        'type'              => 'option',
        'default'           => DUKANETTE_DEFAULT_PAPER,
        'sanitize_callback' => 'sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'dukanette_color_paper', [
        'label'       => __('Couleur de fond', 'dukanette'),
        'description' => __('Le fond général des pages (ivoire par défaut).', 'dukanette'),
        'section'     => 'dukanette_colors',
    ]));

    $wp_customize->add_setting('dukanette_dark_mode_auto', [
        'type'              => 'option',
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);
    $wp_customize->add_control('dukanette_dark_mode_auto', [
        'label'       => __('Activer le mode sombre automatique', 'dukanette'),
        'description' => __('Si coché, le site s’assombrit pour les visiteurs dont l’appareil est en mode sombre. Décoché, le site garde toujours ses couleurs claires.', 'dukanette'),
        'section'     => 'dukanette_colors',
        'type'        => 'checkbox',
    ]);
}
add_action('customize_register', 'dukanette_customize_register_colors');

/** Multiply a hex color's channels by $factor (0–1 darkens, >1 lightens toward 255). */
function dukanette_shade_hex($hex, $factor) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#' . $hex;
    }
    $out = '#';
    foreach (str_split($hex, 2) as $channel) {
        $value = hexdec($channel);
        $value = $factor <= 1
            ? (int) round($value * $factor)
            : (int) round($value + (255 - $value) * min(1, $factor - 1));
        $out .= str_pad(dechex(max(0, min(255, $value))), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

function dukanette_color_overrides_css() {
    $accent = get_option('dukanette_color_accent', DUKANETTE_DEFAULT_ACCENT) ?: DUKANETTE_DEFAULT_ACCENT;
    $paper = get_option('dukanette_color_paper', DUKANETTE_DEFAULT_PAPER) ?: DUKANETTE_DEFAULT_PAPER;
    $dark_auto = get_option('dukanette_dark_mode_auto', false);

    $accent_dark = dukanette_shade_hex($accent, 0.82);
    $accent_soft = dukanette_shade_hex($accent, 1.86);

    $css = sprintf(
        ':root{--gold:%1$s;--gold-dark:%2$s;--gold-soft:%3$s;--paper:%4$s;--surface:%5$s;}',
        $accent,
        $accent_dark,
        $accent_soft,
        $paper,
        dukanette_shade_hex($paper, 1.5)
    );

    if (!$dark_auto) {
        // Re-assert the light palette inside the dark-mode media query so the
        // site keeps its daytime look on dark-mode devices.
        $css .= sprintf(
            '@media (prefers-color-scheme: dark){:root{--paper:%1$s;--surface:%2$s;--ink:#211a12;--muted:#86765f;--hairline:#e6dac4;--gold:%3$s;--gold-dark:%4$s;--gold-soft:%5$s;--deep:#1d1610;--deep-2:#16100b;--cream:#f4ebdc;--cream-muted:rgba(244,235,220,0.62);}}',
            $paper,
            dukanette_shade_hex($paper, 1.5),
            $accent,
            $accent_dark,
            $accent_soft
        );
    }

    return $css;
}

function dukanette_enqueue_color_overrides() {
    wp_add_inline_style('dukanette-main', dukanette_color_overrides_css());
}
add_action('wp_enqueue_scripts', 'dukanette_enqueue_color_overrides', 20);

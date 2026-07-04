<?php
/**
 * A chunk of the recipe content was authored as raw markdown that WP never
 * converts (it only wraps text in <p>/<br>), so "**bold**" and "### heading"
 * show up as literal characters. Clean those up for display.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dukanette_heading_level($hashes) {
    return min(6, strlen($hashes) + 2);
}

function dukanette_fix_legacy_markdown($html) {
    // WP sometimes already turned "# Title" into a real heading tag but left the "#" inside it.
    $html = preg_replace_callback('/(<h[1-6]>)\s*#{1,6}\s*/', function ($m) {
        return $m[1];
    }, $html);

    // A paragraph that is only a heading: <p>### Title</p>
    $html = preg_replace_callback('/<p>\s*(#{1,6})\s*([^<]+?)\s*<\/p>/', function ($m) {
        $level = dukanette_heading_level($m[1]);
        return "<h{$level}>{$m[2]}</h{$level}>";
    }, $html);

    // A heading followed by more content in the same paragraph: <p>### Title<br />...
    $html = preg_replace_callback('/<p>(#{1,6})\s*([^<\n]+?)\s*(<br\s*\/?>)/', function ($m) {
        $level = dukanette_heading_level($m[1]);
        return "<h{$level}>{$m[2]}</h{$level}><p>";
    }, $html);

    $html = preg_replace('/\*\*([^*<]+)\*\*/', '<strong>$1</strong>', $html);

    return $html;
}
add_filter('the_content', 'dukanette_fix_legacy_markdown', 20);

function dukanette_clean_title($title) {
    if (is_admin()) {
        return $title;
    }
    return trim(str_replace('**', '', $title));
}
add_filter('the_title', 'dukanette_clean_title', 20);

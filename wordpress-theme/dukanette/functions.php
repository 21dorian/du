<?php
/**
 * Theme bootstrap: pulls in the pieces from inc/ and wires up core WP support.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUKANETTE_VERSION', '1.0.0');
define('DUKANETTE_DIR', get_template_directory());
define('DUKANETTE_URI', get_template_directory_uri());

require_once DUKANETTE_DIR . '/inc/setup.php';
require_once DUKANETTE_DIR . '/inc/enqueue.php';
require_once DUKANETTE_DIR . '/inc/helpers.php';
require_once DUKANETTE_DIR . '/inc/content-filters.php';
require_once DUKANETTE_DIR . '/inc/favorites.php';
require_once DUKANETTE_DIR . '/inc/newsletter.php';
require_once DUKANETTE_DIR . '/inc/auth.php';
require_once DUKANETTE_DIR . '/inc/daily.php';
require_once DUKANETTE_DIR . '/inc/announcements.php';

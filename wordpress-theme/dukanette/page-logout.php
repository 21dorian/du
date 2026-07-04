<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_safe_redirect(wp_logout_url(home_url('/')));
exit;

<?php
/**
 * Points the password-reset email link at our own /resetpass/ page instead
 * of wp-login.php, keeping visitors inside the themed experience.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dukanette_reset_password_message($message, $key, $user_login) {
    $core_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user_login), 'login');
    $themed_url = add_query_arg(
        ['key' => rawurlencode($key), 'login' => rawurlencode($user_login)],
        dukanette_page_url('resetpass')
    );
    return str_replace($core_url, $themed_url, $message);
}
add_filter('retrieve_password_message', 'dukanette_reset_password_message', 10, 3);

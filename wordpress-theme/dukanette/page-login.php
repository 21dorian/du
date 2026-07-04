<?php
if (!defined('ABSPATH')) {
    exit;
}

$redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : dukanette_page_url('favoris');

if (is_user_logged_in()) {
    wp_safe_redirect($redirect_to);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dukanette_login_nonce']) && wp_verify_nonce($_POST['dukanette_login_nonce'], 'dukanette_login')) {
    $creds = [
        'user_login'    => sanitize_user(wp_unslash($_POST['log'] ?? '')),
        'user_password' => $_POST['pwd'] ?? '',
        'remember'      => !empty($_POST['rememberme']),
    ];
    $user = wp_signon($creds, is_ssl());
    if (is_wp_error($user)) {
        $error = __('Identifiants incorrects. Réessaie.', 'dukanette');
    } else {
        wp_safe_redirect($redirect_to);
        exit;
    }
}

get_header();
?>

<div class="wrap page-auth">
    <h1><?php esc_html_e('Se connecter', 'dukanette'); ?></h1>

    <?php if ($error) : ?>
        <p class="form-error"><?php echo esc_html($error); ?></p>
    <?php endif; ?>

    <form method="post" class="auth-form">
        <?php wp_nonce_field('dukanette_login', 'dukanette_login_nonce'); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
        <label for="log"><?php esc_html_e('Identifiant ou email', 'dukanette'); ?></label>
        <input type="text" name="log" id="log" required>

        <label for="pwd"><?php esc_html_e('Mot de passe', 'dukanette'); ?></label>
        <input type="password" name="pwd" id="pwd" required>

        <label class="checkbox-label">
            <input type="checkbox" name="rememberme" value="1"> <?php esc_html_e('Se souvenir de moi', 'dukanette'); ?>
        </label>

        <button type="submit" class="btn btn-primary"><?php esc_html_e('Se connecter', 'dukanette'); ?></button>
    </form>

    <p class="auth-links">
        <a href="<?php echo esc_url(dukanette_page_url('lostpassword')); ?>"><?php esc_html_e('Mot de passe oublié ?', 'dukanette'); ?></a>
        <?php if (get_option('users_can_register')) : ?>
            · <a href="<?php echo esc_url(dukanette_page_url('register')); ?>"><?php esc_html_e('Créer un compte', 'dukanette'); ?></a>
        <?php endif; ?>
    </p>
</div>

<?php get_footer(); ?>

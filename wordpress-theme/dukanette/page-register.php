<?php
if (!defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in()) {
    wp_safe_redirect(dukanette_page_url('favoris'));
    exit;
}

$error = '';
if (get_option('users_can_register') && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['dukanette_register_nonce']) && wp_verify_nonce($_POST['dukanette_register_nonce'], 'dukanette_register')
) {
    $username = sanitize_user(wp_unslash($_POST['user_login'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
    $password = $_POST['user_pass'] ?? '';

    if (!$username || !$email || !is_email($email)) {
        $error = __('Merci de remplir tous les champs avec un email valide.', 'dukanette');
    } elseif (strlen($password) < 8) {
        $error = __('Le mot de passe doit faire au moins 8 caractères.', 'dukanette');
    } elseif (username_exists($username)) {
        $error = __('Ce nom d’utilisateur est déjà pris.', 'dukanette');
    } elseif (email_exists($email)) {
        $error = __('Un compte existe déjà avec cet email.', 'dukanette');
    } else {
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            $error = $user_id->get_error_message();
        } else {
            wp_new_user_notification($user_id, null, 'user');
            $user = wp_signon(['user_login' => $username, 'user_password' => $password], is_ssl());
            if (!is_wp_error($user)) {
                wp_safe_redirect(dukanette_page_url('favoris'));
                exit;
            }
        }
    }
}

get_header();
?>

<div class="wrap page-auth">
    <h1><?php esc_html_e('Créer un compte', 'dukanette'); ?></h1>

    <?php if (!get_option('users_can_register')) : ?>
        <p class="empty-state"><?php esc_html_e('Les inscriptions sont temporairement fermées.', 'dukanette'); ?></p>
    <?php else : ?>
        <?php if ($error) : ?>
            <p class="form-error"><?php echo esc_html($error); ?></p>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <?php wp_nonce_field('dukanette_register', 'dukanette_register_nonce'); ?>
            <label for="user_login"><?php esc_html_e('Nom d’utilisateur', 'dukanette'); ?></label>
            <input type="text" name="user_login" id="user_login" required>

            <label for="user_email"><?php esc_html_e('Email', 'dukanette'); ?></label>
            <input type="email" name="user_email" id="user_email" required>

            <label for="user_pass"><?php esc_html_e('Mot de passe', 'dukanette'); ?></label>
            <input type="password" name="user_pass" id="user_pass" minlength="8" required>

            <button type="submit" class="btn btn-primary"><?php esc_html_e('Créer mon compte', 'dukanette'); ?></button>
        </form>

        <p class="auth-links">
            <a href="<?php echo esc_url(dukanette_page_url('login')); ?>"><?php esc_html_e('J’ai déjà un compte', 'dukanette'); ?></a>
        </p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

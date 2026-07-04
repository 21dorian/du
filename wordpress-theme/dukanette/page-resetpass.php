<?php
if (!defined('ABSPATH')) {
    exit;
}

$key = sanitize_text_field(wp_unslash($_GET['key'] ?? ($_POST['key'] ?? '')));
$login = sanitize_text_field(wp_unslash($_GET['login'] ?? ($_POST['login'] ?? '')));

$user = $key && $login ? check_password_reset_key($key, $login) : null;
$error = '';
$done = false;

if (!$key || !$login) {
    $error = __('Lien de réinitialisation invalide.', 'dukanette');
} elseif (is_wp_error($user)) {
    $error = __('Ce lien a expiré ou n’est plus valide. Demande un nouveau lien.', 'dukanette');
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dukanette_resetpass_nonce']) && wp_verify_nonce($_POST['dukanette_resetpass_nonce'], 'dukanette_resetpass')) {
    $password = $_POST['pass1'] ?? '';
    $password2 = $_POST['pass2'] ?? '';

    if (strlen($password) < 8) {
        $error = __('Le mot de passe doit faire au moins 8 caractères.', 'dukanette');
    } elseif ($password !== $password2) {
        $error = __('Les deux mots de passe ne correspondent pas.', 'dukanette');
    } else {
        reset_password($user, $password);
        $done = true;
    }
}

get_header();
?>

<div class="wrap page-auth">
    <h1><?php esc_html_e('Réinitialiser le mot de passe', 'dukanette'); ?></h1>

    <?php if ($done) : ?>
        <p class="form-success"><?php esc_html_e('Ton mot de passe a été changé.', 'dukanette'); ?></p>
        <p class="auth-links">
            <a href="<?php echo esc_url(dukanette_page_url('login')); ?>" class="btn btn-primary"><?php esc_html_e('Se connecter', 'dukanette'); ?></a>
        </p>
    <?php elseif ($error) : ?>
        <p class="form-error"><?php echo esc_html($error); ?></p>
        <p class="auth-links">
            <a href="<?php echo esc_url(dukanette_page_url('lostpassword')); ?>"><?php esc_html_e('Demander un nouveau lien', 'dukanette'); ?></a>
        </p>
    <?php else : ?>
        <form method="post" class="auth-form">
            <?php wp_nonce_field('dukanette_resetpass', 'dukanette_resetpass_nonce'); ?>
            <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
            <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">

            <label for="pass1"><?php esc_html_e('Nouveau mot de passe', 'dukanette'); ?></label>
            <input type="password" name="pass1" id="pass1" minlength="8" required>

            <label for="pass2"><?php esc_html_e('Confirme le mot de passe', 'dukanette'); ?></label>
            <input type="password" name="pass2" id="pass2" minlength="8" required>

            <button type="submit" class="btn btn-primary"><?php esc_html_e('Changer le mot de passe', 'dukanette'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

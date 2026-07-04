<?php
if (!defined('ABSPATH')) {
    exit;
}

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dukanette_lostpassword_nonce']) && wp_verify_nonce($_POST['dukanette_lostpassword_nonce'], 'dukanette_lostpassword')) {
    $login = sanitize_text_field(wp_unslash($_POST['user_login'] ?? ''));
    if (!$login) {
        $error = __('Merci de renseigner ton email ou identifiant.', 'dukanette');
    } else {
        $result = retrieve_password($login);
        // Always show a generic success message, whether or not the account
        // exists, so the form can't be used to probe registered emails.
        $sent = true;
        if (is_wp_error($result) && $result->get_error_code() === 'invalidcombo') {
            $sent = true;
        }
    }
}

get_header();
?>

<div class="wrap page-auth">
    <h1><?php esc_html_e('Mot de passe oublié', 'dukanette'); ?></h1>

    <?php if ($sent) : ?>
        <p class="form-success">
            <?php esc_html_e('Si un compte existe avec cet identifiant, un email de réinitialisation vient d’être envoyé.', 'dukanette'); ?>
        </p>
    <?php else : ?>
        <?php if ($error) : ?>
            <p class="form-error"><?php echo esc_html($error); ?></p>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <?php wp_nonce_field('dukanette_lostpassword', 'dukanette_lostpassword_nonce'); ?>
            <label for="user_login"><?php esc_html_e('Email ou identifiant', 'dukanette'); ?></label>
            <input type="text" name="user_login" id="user_login" required>

            <button type="submit" class="btn btn-primary"><?php esc_html_e('Envoyer le lien de réinitialisation', 'dukanette'); ?></button>
        </form>
    <?php endif; ?>

    <p class="auth-links">
        <a href="<?php echo esc_url(dukanette_page_url('login')); ?>"><?php esc_html_e('Retour à la connexion', 'dukanette'); ?></a>
    </p>
</div>

<?php get_footer(); ?>

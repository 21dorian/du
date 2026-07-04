<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>

<div class="wrap page-newsletter">
    <h1><?php esc_html_e('Une recette par semaine, dans ta boîte mail', 'dukanette'); ?></h1>
    <p><?php esc_html_e('Pas de spam, juste une nouvelle idée gourmande et compatible régime chaque semaine.', 'dukanette'); ?></p>

    <form class="newsletter-form">
        <input type="email" required placeholder="ton@email.fr" aria-label="<?php esc_attr_e('Adresse email', 'dukanette'); ?>">
        <button type="submit" class="btn btn-primary"><?php esc_html_e('Je m’inscris', 'dukanette'); ?></button>
        <p class="newsletter-status" hidden></p>
    </form>
</div>

<?php get_footer(); ?>

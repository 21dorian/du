<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>

<div class="wrap page-404">
    <h1><?php esc_html_e('Page introuvable', 'dukanette'); ?></h1>
    <p><?php esc_html_e('Cette recette a peut-être été déplacée ou n’existe plus.', 'dukanette'); ?></p>
    <?php get_search_form(); ?>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary"><?php esc_html_e('Retour à l’accueil', 'dukanette'); ?></a>
</div>

<?php get_footer(); ?>

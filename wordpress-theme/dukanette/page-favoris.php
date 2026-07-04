<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_safe_redirect(dukanette_login_url(dukanette_page_url('favoris')));
    exit;
}

get_header();

$favorite_ids = dukanette_get_user_favorites();
$favorites_query = $favorite_ids ? new WP_Query([
    'post__in'       => $favorite_ids,
    'orderby'        => 'post__in',
    'posts_per_page' => 50,
    'post_status'    => 'publish',
]) : null;
?>

<div class="wrap page-archive">
    <header class="archive-header">
        <h1><?php esc_html_e('Mes favoris', 'dukanette'); ?></h1>
    </header>

    <?php if ($favorites_query && $favorites_query->have_posts()) : ?>
        <div class="post-grid">
            <?php while ($favorites_query->have_posts()) : $favorites_query->the_post(); ?>
                <?php dukanette_post_card(); ?>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p class="empty-state">
            <?php esc_html_e('Pas encore de favoris — clique sur le cœur d’une recette pour l’ajouter ici.', 'dukanette'); ?>
        </p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

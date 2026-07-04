<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$popular = dukanette_get_popular_posts(12);
?>

<div class="wrap page-archive">
    <header class="archive-header">
        <p class="eyebrow eyebrow-center"><?php esc_html_e('Le palmarès des lectrices', 'dukanette'); ?></p>
        <h1><?php esc_html_e('Les recettes les plus consultées', 'dukanette'); ?></h1>
    </header>

    <?php if ($popular->have_posts()) : ?>
        <div class="post-grid">
            <?php while ($popular->have_posts()) : $popular->the_post(); ?>
                <?php dukanette_post_card(); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Le palmarès se construit au fil des visites — reviens bientôt !', 'dukanette'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$term = get_queried_object();
?>

<div class="wrap page-archive">
    <header class="archive-header">
        <h1><?php echo esc_html(is_a($term, 'WP_Term') ? $term->name : get_the_archive_title()); ?></h1>
        <?php if (is_a($term, 'WP_Term')) : ?>
            <p class="archive-count">
                <?php
                printf(
                    /* translators: %d: number of posts */
                    esc_html(_n('%d recette', '%d recettes', $term->count, 'dukanette')),
                    (int) $term->count
                );
                ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="post-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php dukanette_post_card(); ?>
            <?php endwhile; ?>
        </div>
        <?php dukanette_pagination(); ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Aucun article ici pour le moment.', 'dukanette'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

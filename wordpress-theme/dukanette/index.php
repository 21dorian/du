<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>

<div class="wrap page-archive">
    <?php if (have_posts()) : ?>
        <div class="post-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php dukanette_post_card(); ?>
            <?php endwhile; ?>
        </div>
        <?php dukanette_pagination(); ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Rien à afficher.', 'dukanette'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

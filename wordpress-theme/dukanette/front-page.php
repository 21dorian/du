<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$latest = new WP_Query([
    'posts_per_page' => 9,
    'post_status'    => 'publish',
]);
$posts = $latest->posts;
$featured = array_shift($posts);
$sucre_link = get_category_link(get_category_by_slug('sucre'));
$sale_link = get_category_link(get_category_by_slug('sale'));
?>

<div class="wrap page-home">
    <section class="hero">
        <h1><?php esc_html_e('Se faire plaisir, même au régime', 'dukanette'); ?></h1>
        <p><?php esc_html_e('Des recettes sucrées et salées gourmandes pour toutes les phases du régime Dukan, testées et approuvées par Choupette.', 'dukanette'); ?></p>
        <div class="hero-actions">
            <a href="<?php echo esc_url($sucre_link); ?>" class="btn btn-primary"><?php esc_html_e('Recettes sucrées', 'dukanette'); ?></a>
            <a href="<?php echo esc_url($sale_link); ?>" class="btn btn-outline"><?php esc_html_e('Recettes salées', 'dukanette'); ?></a>
        </div>
    </section>

    <?php if ($featured) : ?>
        <section class="section">
            <h2 class="section-title"><?php esc_html_e('À la une', 'dukanette'); ?></h2>
            <div class="post-grid post-grid-single">
                <?php dukanette_post_card($featured->ID, true); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><?php esc_html_e('Derniers articles', 'dukanette'); ?></h2>
            <a href="<?php echo esc_url(get_category_link(get_category_by_slug('recette'))); ?>" class="section-link">
                <?php esc_html_e('Voir tout →', 'dukanette'); ?>
            </a>
        </div>
        <div class="post-grid">
            <?php foreach ($posts as $post) : ?>
                <?php dukanette_post_card($post->ID); ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php
wp_reset_postdata();
get_footer();

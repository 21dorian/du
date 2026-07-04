<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$latest = new WP_Query([
    'posts_per_page' => 6,
    'post_status'    => 'publish',
]);
$posts = $latest->posts;
$sucre_link = get_category_link(get_category_by_slug('sucre'));
$sale_link = get_category_link(get_category_by_slug('sale'));

$daily_sucre = dukanette_get_daily_pick('sucre');
$daily_sale = dukanette_get_daily_pick('sale');
$daily_tip = dukanette_get_daily_tip();

$announcement_enabled = get_option('dukanette_announcement_enabled');
$announcement_text = get_option('dukanette_announcement_text');
$ad_enabled = get_option('dukanette_ad_enabled');
$ad_code = get_option('dukanette_ad_code');
?>

<?php if ($announcement_enabled && $announcement_text) : ?>
    <div class="announcement-banner">
        <div class="wrap"><?php echo wp_kses_post(wpautop($announcement_text)); ?></div>
    </div>
<?php endif; ?>

<div class="wrap page-home">
    <section class="hero">
        <h1><?php esc_html_e('Se faire plaisir, même au régime', 'dukanette'); ?></h1>
        <p><?php esc_html_e('Des recettes sucrées et salées gourmandes pour toutes les phases du régime Dukan, testées et approuvées par Choupette.', 'dukanette'); ?></p>
        <div class="hero-actions">
            <a href="<?php echo esc_url($sucre_link); ?>" class="btn btn-primary"><?php esc_html_e('Recettes sucrées', 'dukanette'); ?></a>
            <a href="<?php echo esc_url($sale_link); ?>" class="btn btn-outline"><?php esc_html_e('Recettes salées', 'dukanette'); ?></a>
        </div>
    </section>

    <?php if ($daily_sucre || $daily_sale || $daily_tip) : ?>
        <section class="section daily-section">
            <h2 class="section-title"><?php esc_html_e('Aujourd’hui', 'dukanette'); ?></h2>
            <div class="daily-grid">
                <?php if ($daily_sucre || $daily_sale) : ?>
                    <div class="daily-menu">
                        <p class="daily-label"><?php esc_html_e('Le menu du jour', 'dukanette'); ?></p>
                        <div class="post-grid">
                            <?php if ($daily_sucre) : ?>
                                <?php dukanette_post_card($daily_sucre->ID, true); ?>
                            <?php endif; ?>
                            <?php if ($daily_sale) : ?>
                                <?php dukanette_post_card($daily_sale->ID, true); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($daily_tip) : ?>
                    <aside class="daily-tip">
                        <p class="daily-label"><?php esc_html_e('L’astuce du jour', 'dukanette'); ?></p>
                        <p class="daily-tip-text"><?php echo esc_html($daily_tip); ?></p>
                    </aside>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($ad_enabled && $ad_code) : ?>
        <section class="ad-slot">
            <p class="ad-slot-label"><?php esc_html_e('Publicité', 'dukanette'); ?></p>
            <?php echo $ad_code; // phpcs:ignore -- trusted, admin-only Customizer field, see inc/announcements.php ?>
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

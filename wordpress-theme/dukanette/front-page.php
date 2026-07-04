<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$latest = new WP_Query([
    'posts_per_page' => 7,
    'post_status'    => 'publish',
]);
$posts = $latest->posts;
$featured = array_shift($posts);
$sucre = get_category_by_slug('sucre');
$sale = get_category_by_slug('sale');
$recette = get_category_by_slug('recette');
$total_recipes = $recette ? $recette->count : 0;

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

<div class="page-home">

    <section class="hero-editorial">
        <div class="wrap hero-editorial-inner">
            <div class="hero-copy">
                <p class="eyebrow"><?php esc_html_e('Le blog Dukan de Choupette', 'dukanette'); ?></p>
                <h1><?php esc_html_e('Se faire plaisir,', 'dukanette'); ?> <em><?php esc_html_e('même au régime', 'dukanette'); ?></em></h1>
                <p class="hero-lead"><?php esc_html_e('Des recettes sucrées et salées gourmandes pour toutes les phases du régime Dukan — testées, approuvées et dévorées.', 'dukanette'); ?></p>
                <div class="hero-actions">
                    <?php if ($sucre) : ?>
                        <a href="<?php echo esc_url(get_category_link($sucre)); ?>" class="btn btn-primary"><?php esc_html_e('Recettes sucrées', 'dukanette'); ?></a>
                    <?php endif; ?>
                    <?php if ($sale) : ?>
                        <a href="<?php echo esc_url(get_category_link($sale)); ?>" class="btn btn-outline"><?php esc_html_e('Recettes salées', 'dukanette'); ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($total_recipes) : ?>
                    <p class="hero-stats">
                        <strong><?php echo esc_html(number_format_i18n($total_recipes)); ?></strong> <?php esc_html_e('recettes', 'dukanette'); ?>
                        <span aria-hidden="true">·</span> <?php esc_html_e('Sucré & salé', 'dukanette'); ?>
                        <span aria-hidden="true">·</span> <?php esc_html_e('Toutes les phases', 'dukanette'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($featured) : ?>
                <a class="hero-feature" href="<?php echo esc_url(get_permalink($featured)); ?>">
                    <?php if (has_post_thumbnail($featured)) : ?>
                        <?php echo get_the_post_thumbnail($featured->ID, 'large', ['loading' => 'eager', 'fetchpriority' => 'high']); ?>
                    <?php endif; ?>
                    <div class="hero-feature-overlay">
                        <span class="hero-feature-badge"><?php esc_html_e('À la une', 'dukanette'); ?></span>
                        <h2><?php echo esc_html(get_the_title($featured)); ?></h2>
                        <time datetime="<?php echo esc_attr(get_the_date('c', $featured)); ?>"><?php echo esc_html(get_the_date('j F Y', $featured)); ?></time>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($daily_sucre || $daily_sale || $daily_tip) : ?>
        <section class="daily-band">
            <div class="wrap">
                <div class="section-header">
                    <div>
                        <p class="eyebrow"><?php echo esc_html(date_i18n('l j F')); ?></p>
                        <h2 class="section-title"><?php esc_html_e('Aujourd’hui', 'dukanette'); ?></h2>
                    </div>
                </div>
                <div class="daily-grid">
                    <?php if ($daily_sucre || $daily_sale) : ?>
                        <div class="daily-menu">
                            <p class="daily-label"><?php esc_html_e('Le menu du jour', 'dukanette'); ?></p>
                            <div class="post-grid post-grid-2">
                                <?php if ($daily_sucre) : ?>
                                    <?php dukanette_post_card($daily_sucre->ID); ?>
                                <?php endif; ?>
                                <?php if ($daily_sale) : ?>
                                    <?php dukanette_post_card($daily_sale->ID); ?>
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
            </div>
        </section>
    <?php endif; ?>

    <?php if ($ad_enabled && $ad_code) : ?>
        <div class="wrap">
            <section class="ad-slot">
                <p class="ad-slot-label"><?php esc_html_e('Publicité', 'dukanette'); ?></p>
                <?php echo $ad_code; // phpcs:ignore -- trusted, admin-only Customizer field, see inc/announcements.php ?>
            </section>
        </div>
    <?php endif; ?>

    <section class="section wrap">
        <div class="section-header">
            <div>
                <p class="eyebrow"><?php esc_html_e('Fraîchement publiées', 'dukanette'); ?></p>
                <h2 class="section-title"><?php esc_html_e('Derniers articles', 'dukanette'); ?></h2>
            </div>
            <?php if ($recette) : ?>
                <a href="<?php echo esc_url(get_category_link($recette)); ?>" class="section-link"><?php esc_html_e('Tout voir', 'dukanette'); ?> →</a>
            <?php endif; ?>
        </div>
        <div class="post-grid">
            <?php foreach ($posts as $post) : ?>
                <?php dukanette_post_card($post->ID); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="newsletter-band">
        <div class="wrap newsletter-band-inner">
            <div>
                <p class="eyebrow eyebrow-light"><?php esc_html_e('Newsletter', 'dukanette'); ?></p>
                <h2><?php esc_html_e('Une recette gourmande par semaine, dans ta boîte mail', 'dukanette'); ?></h2>
                <p><?php esc_html_e('Pas de spam — juste une nouvelle idée compatible régime chaque semaine.', 'dukanette'); ?></p>
            </div>
            <form class="newsletter-form">
                <input type="email" required placeholder="ton@email.fr" aria-label="<?php esc_attr_e('Adresse email', 'dukanette'); ?>">
                <button type="submit" class="btn btn-light"><?php esc_html_e('Je m’inscris', 'dukanette'); ?></button>
                <p class="newsletter-status" hidden></p>
            </form>
        </div>
    </section>

</div>

<?php
wp_reset_postdata();
get_footer();

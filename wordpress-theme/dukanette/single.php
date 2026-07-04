<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

while (have_posts()) :
    the_post();
    $categories = get_the_category();
    $tags = get_the_tags();
    $views = dukanette_get_views(get_the_ID());
    ?>

    <article <?php post_class('wrap page-single'); ?>>
        <div class="single-meta-top">
            <?php foreach ($categories as $category) : ?>
                <a href="<?php echo esc_url(get_category_link($category)); ?>" class="tag-pill">
                    <?php echo esc_html($category->name); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="single-title-row">
            <h1><?php the_title(); ?></h1>
            <?php dukanette_favorite_button(get_the_ID()); ?>
        </div>

        <p class="single-date">
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
            <?php if ($views > 0) : ?>
                <span aria-hidden="true"> · </span>
                <?php
                printf(
                    esc_html(_n('%s lecture', '%s lectures', $views, 'dukanette')),
                    esc_html(number_format_i18n($views))
                );
                ?>
            <?php endif; ?>
        </p>

        <?php if (has_post_thumbnail()) : ?>
            <div class="single-media">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <div class="prose-recipe">
            <?php the_content(); ?>
        </div>

        <?php if ($tags) : ?>
            <div class="single-tags">
                <?php foreach ($tags as $tag) : ?>
                    <a href="<?php echo esc_url(get_tag_link($tag)); ?>" class="tag-pill tag-pill-outline">
                        #<?php echo esc_html($tag->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $prev_post = get_previous_post();
        $next_post = get_next_post();
        if ($prev_post || $next_post) :
            ?>
            <nav class="post-nav" aria-label="<?php esc_attr_e('Autres recettes', 'dukanette'); ?>">
                <?php if ($prev_post) : ?>
                    <a href="<?php echo esc_url(get_permalink($prev_post)); ?>" class="post-nav-item post-nav-prev">
                        <span class="post-nav-label">← <?php esc_html_e('Recette précédente', 'dukanette'); ?></span>
                        <span class="post-nav-title"><?php echo esc_html(get_the_title($prev_post)); ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($next_post) : ?>
                    <a href="<?php echo esc_url(get_permalink($next_post)); ?>" class="post-nav-item post-nav-next">
                        <span class="post-nav-label"><?php esc_html_e('Recette suivante', 'dukanette'); ?> →</span>
                        <span class="post-nav-title"><?php echo esc_html(get_the_title($next_post)); ?></span>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php if (comments_open() || get_comments_number()) : ?>
            <section class="single-comments">
                <?php comments_template(); ?>
            </section>
        <?php endif; ?>
    </article>

    <?php
    $related = $categories ? new WP_Query([
        'category__in'   => wp_list_pluck($categories, 'term_id'),
        'post__not_in'   => [get_the_ID()],
        'posts_per_page' => 3,
        'orderby'        => 'rand',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]) : null;
    if ($related && $related->have_posts()) :
        ?>
        <section class="related-section">
            <div class="wrap">
                <div class="section-header">
                    <div>
                        <p class="eyebrow"><?php esc_html_e('Pour continuer', 'dukanette'); ?></p>
                        <h2 class="section-title"><?php esc_html_e('Tu aimeras aussi', 'dukanette'); ?></h2>
                    </div>
                </div>
                <div class="post-grid">
                    <?php while ($related->have_posts()) : $related->the_post(); ?>
                        <?php dukanette_post_card(); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php endwhile; ?>

<?php get_footer(); ?>

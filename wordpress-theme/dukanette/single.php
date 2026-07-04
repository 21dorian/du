<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

while (have_posts()) :
    the_post();
    $categories = get_the_category();
    $tags = get_the_tags();
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

        <time class="single-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
            <?php echo esc_html(get_the_date('j F Y')); ?>
        </time>

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

        <?php if (comments_open() || get_comments_number()) : ?>
            <section class="single-comments">
                <?php comments_template(); ?>
            </section>
        <?php endif; ?>
    </article>

<?php endwhile; ?>

<?php get_footer(); ?>

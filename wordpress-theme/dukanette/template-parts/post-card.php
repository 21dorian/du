<?php
$post_id = get_query_var('dukanette_card_post_id', get_the_ID());
$priority = get_query_var('dukanette_card_priority', false);
$post = get_post($post_id);
if (!$post) {
    return;
}
$categories = get_the_category($post_id);
$main_category = $categories ? $categories[0] : null;
?>
<article class="post-card">
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="post-card-media">
        <?php if (has_post_thumbnail($post_id)) : ?>
            <?php echo get_the_post_thumbnail($post_id, 'medium_large', $priority ? ['loading' => 'eager', 'fetchpriority' => 'high'] : ['loading' => 'lazy']); ?>
        <?php else : ?>
            <span class="post-card-media-fallback" aria-hidden="true">🍰</span>
        <?php endif; ?>
    </a>
    <div class="post-card-body">
        <div class="post-card-top">
            <?php if ($main_category) : ?>
                <a href="<?php echo esc_url(get_category_link($main_category)); ?>" class="tag-pill">
                    <?php echo esc_html($main_category->name); ?>
                </a>
            <?php endif; ?>
            <?php dukanette_favorite_button($post_id); ?>
        </div>
        <h3 class="post-card-title">
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a>
        </h3>
        <p class="post-card-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 20)); ?></p>
        <time class="post-card-date" datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
            <?php echo esc_html(get_the_date('j F Y', $post_id)); ?>
        </time>
    </div>
</article>

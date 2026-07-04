<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>

<div class="wrap page-search">
    <h1><?php esc_html_e('Rechercher une recette', 'dukanette'); ?></h1>

    <form class="search-form search-form-page" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="search" name="s" required placeholder="<?php esc_attr_e('Rechercher une recette…', 'dukanette'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
        <button type="submit"><?php esc_html_e('Chercher', 'dukanette'); ?></button>
    </form>

    <?php if (get_search_query()) : ?>
        <p class="archive-count">
            <?php
            global $wp_query;
            printf(
                /* translators: 1: number of results, 2: search term */
                esc_html(_n('%1$d résultat pour « %2$s »', '%1$d résultats pour « %2$s »', $wp_query->found_posts, 'dukanette')),
                (int) $wp_query->found_posts,
                esc_html(get_search_query())
            );
            ?>
        </p>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <div class="post-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php dukanette_post_card(); ?>
            <?php endwhile; ?>
        </div>
        <?php dukanette_pagination(); ?>
    <?php elseif (get_search_query()) : ?>
        <p class="empty-state"><?php esc_html_e('Aucun article ne correspond à cette recherche.', 'dukanette'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>

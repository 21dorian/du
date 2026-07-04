<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="wrap site-header-inner">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
            <?php bloginfo('name'); ?>
        </a>

        <nav class="site-nav" aria-label="<?php esc_attr_e('Navigation principale', 'dukanette'); ?>">
            <?php foreach (dukanette_main_categories() as $category) : ?>
                <a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="site-header-actions">
            <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="search" name="s" required placeholder="<?php esc_attr_e('Rechercher une recette…', 'dukanette'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
                <button type="submit"><?php esc_html_e('Chercher', 'dukanette'); ?></button>
            </form>

            <a href="<?php echo esc_url(dukanette_page_url('newsletter')); ?>" class="btn btn-outline">
                <?php esc_html_e('Newsletter', 'dukanette'); ?>
            </a>

            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(dukanette_page_url('favoris')); ?>" class="btn btn-ghost">
                    <?php esc_html_e('Mes favoris', 'dukanette'); ?>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="btn btn-ghost">
                    <?php esc_html_e('Déconnexion', 'dukanette'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url(dukanette_page_url('login')); ?>" class="btn btn-ghost">
                    <?php esc_html_e('Se connecter', 'dukanette'); ?>
                </a>
            <?php endif; ?>
        </div>

        <button type="button" class="mobile-nav-toggle" aria-expanded="false" aria-controls="mobile-nav" aria-label="<?php esc_attr_e('Ouvrir le menu', 'dukanette'); ?>">
            <span aria-hidden="true">☰</span>
        </button>
    </div>

    <div id="mobile-nav" class="mobile-nav" hidden>
        <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="search" name="s" required placeholder="<?php esc_attr_e('Rechercher une recette…', 'dukanette'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
            <button type="submit"><?php esc_html_e('Chercher', 'dukanette'); ?></button>
        </form>
        <?php foreach (dukanette_main_categories() as $category) : ?>
            <a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url(dukanette_page_url('newsletter')); ?>"><?php esc_html_e('Newsletter', 'dukanette'); ?></a>
        <?php if (is_user_logged_in()) : ?>
            <a href="<?php echo esc_url(dukanette_page_url('favoris')); ?>"><?php esc_html_e('Mes favoris', 'dukanette'); ?></a>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Déconnexion', 'dukanette'); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url(dukanette_page_url('login')); ?>"><?php esc_html_e('Se connecter', 'dukanette'); ?></a>
        <?php endif; ?>
    </div>
</header>

<main class="site-main">

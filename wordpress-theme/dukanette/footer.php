<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
</main>

<footer class="site-footer">
    <div class="wrap site-footer-inner">
        <div class="site-footer-about">
            <p class="site-footer-title"><?php bloginfo('name'); ?></p>
            <p><?php bloginfo('description'); ?></p>
        </div>
        <div class="site-footer-links">
            <div>
                <p class="site-footer-heading"><?php esc_html_e('Explorer', 'dukanette'); ?></p>
                <ul>
                    <?php foreach (dukanette_main_categories() as $category) : ?>
                        <li><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <p class="site-footer-heading"><?php esc_html_e('Suivre', 'dukanette'); ?></p>
                <ul>
                    <li><a href="<?php echo esc_url(dukanette_page_url('newsletter')); ?>"><?php esc_html_e('Newsletter', 'dukanette'); ?></a></li>
                    <li><a href="<?php echo esc_url(get_bloginfo('rss2_url')); ?>"><?php esc_html_e('Flux RSS', 'dukanette'); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
    <p class="site-footer-legal wrap">
        © <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('Tous droits réservés.', 'dukanette'); ?>
    </p>
</footer>

<?php wp_footer(); ?>
</body>
</html>

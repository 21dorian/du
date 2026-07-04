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
            <p class="site-footer-tagline"><?php esc_html_e('Des recettes sucrées et salées pour toutes les phases du régime, sans jamais se priver.', 'dukanette'); ?></p>
        </div>
        <div class="site-footer-links">
            <div>
                <p class="site-footer-heading"><?php esc_html_e('Explorer', 'dukanette'); ?></p>
                <ul>
                    <?php foreach (dukanette_main_categories() as $category) : ?>
                        <li><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?php echo esc_url(home_url('/?s=')); ?>"><?php esc_html_e('Recherche', 'dukanette'); ?></a></li>
                </ul>
            </div>
            <div>
                <p class="site-footer-heading"><?php esc_html_e('Mon espace', 'dukanette'); ?></p>
                <ul>
                    <?php if (is_user_logged_in()) : ?>
                        <li><a href="<?php echo esc_url(dukanette_page_url('favoris')); ?>"><?php esc_html_e('Mes favoris', 'dukanette'); ?></a></li>
                        <li><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Déconnexion', 'dukanette'); ?></a></li>
                    <?php else : ?>
                        <li><a href="<?php echo esc_url(dukanette_page_url('login')); ?>"><?php esc_html_e('Se connecter', 'dukanette'); ?></a></li>
                        <?php if (get_option('users_can_register')) : ?>
                            <li><a href="<?php echo esc_url(dukanette_page_url('register')); ?>"><?php esc_html_e('Créer un compte', 'dukanette'); ?></a></li>
                        <?php endif; ?>
                    <?php endif; ?>
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
    <div class="wrap site-footer-legal">
        <p>© <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('Tous droits réservés.', 'dukanette'); ?></p>
        <p class="site-footer-madewith"><?php esc_html_e('Fait avec beaucoup de son d’avoine 🌾', 'dukanette'); ?></p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

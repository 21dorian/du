<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="search" name="s" required placeholder="<?php esc_attr_e('Rechercher une recette…', 'dukanette'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
    <button type="submit"><?php esc_html_e('Chercher', 'dukanette'); ?></button>
</form>

<?php
if (!defined('ABSPATH')) {
    exit;
}

if (post_password_required()) {
    return;
}
?>

<h2 class="comments-title">
    <?php
    $count = get_comments_number();
    printf(
        esc_html(_n('%d commentaire', '%d commentaires', $count, 'dukanette')),
        (int) $count
    );
    ?>
</h2>

<?php if (have_comments()) : ?>
    <ol class="comment-list">
        <?php
        wp_list_comments([
            'style'       => 'ol',
            'short_ping'  => true,
            'avatar_size' => 48,
        ]);
        ?>
    </ol>
    <?php the_comments_navigation(); ?>
<?php endif; ?>

<?php if (!comments_open() && get_comments_number()) : ?>
    <p class="comments-closed"><?php esc_html_e('Les commentaires sont fermés.', 'dukanette'); ?></p>
<?php endif; ?>

<?php
comment_form([
    'class_form'   => 'comment-form',
    'title_reply'  => __('Laisser un commentaire', 'dukanette'),
    'label_submit' => __('Publier', 'dukanette'),
]);

<?php get_header(); ?>

<!-- Index Template -->

<?php if (is_front_page()) { get_template_part('snippets/homepage_carousel'); } ?>

<main>
    <div class="post-content">
        <?php get_template_part( 'loop' ); ?>
    </div>
</main>

<?php if (is_front_page()) { dynamic_sidebar( 'home_instagram' ); } ?>

<?php get_footer(); ?>
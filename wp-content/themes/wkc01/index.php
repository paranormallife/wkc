<?php get_header(); ?>

<!-- Index Template -->

<?php if (is_home()) { get_template_part('snippets/homepage_carousel'); } ?>

<?php get_template_part( 'loop' ); ?>

<?php get_footer(); ?>
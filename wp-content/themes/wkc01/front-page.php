<?php get_header(); ?>

<!-- Index Template -->

<?php get_template_part('snippets/homepage_carousel'); ?>

<main>
    <div class="post-content">
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

        <article>
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>        
        </article>

        <?php endwhile; else: ?>
        <p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
        <?php endif; ?>
    </div>
</main>

<?php if (is_front_page()) { dynamic_sidebar( 'home_instagram' ); } ?>

<?php get_footer(); ?>
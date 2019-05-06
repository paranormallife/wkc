<?php /* Template Name: No Hero Image */ get_header(); ?>

<!-- Index Template -->

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

<?php get_footer(); ?>
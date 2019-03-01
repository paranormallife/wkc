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

<section class="eventbrite-home">
    
    <?php
    $the_events = new Eventbrite_Query( array( 'display_private' => true, 'limit' => 5, 'status' => 'all' ) );
    while ( $the_events->have_posts() ) : $the_events->the_post(); ?>

        <?php echo $post_title; ?>

    <?php endwhile; wp_reset_postdata(); ?>

</section>

<?php if (is_front_page()) { dynamic_sidebar( 'home_instagram' ); } ?>

<?php get_footer(); ?>
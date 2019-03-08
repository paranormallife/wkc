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
    <h2>Upcoming Events</h2>
    <?php echo do_shortcode('[eventbrite_events posts_per_page="5" col="2"]'); ?>
    <a href="/calendar" class="button filled"><span>View Full Calendar</span></a>
</section>

<?php if (is_front_page()) { dynamic_sidebar( 'home_instagram' ); } ?>

<?php get_footer(); ?>
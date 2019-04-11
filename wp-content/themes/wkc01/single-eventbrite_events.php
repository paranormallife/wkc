<?php get_header(); ?>

<!-- single-eventbrite_events.php ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->

<main>
    <div class="post-content">
        
        
            <?php if ( have_posts() ) : while ( have_posts() ) : the_post();
        $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail', false, '' ); ?>

        <?php if( $thumb != '' ) { ?>
            <div class="post-featured-image">
                <img src="<?php echo $thumb[0]; ?>" title="<?php the_title(); ?>" />
            </div>
        <?php } ?>

        <article>
            <h1><?php the_title(); ?></h1>
            <div class="event-description"><?php echo get_the_content(); ?></div>
            <div class="event-meta"><?php the_content(); ?></div>
        </article>

        <?php endwhile; else: ?>
        <p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
        <?php endif; ?>


    </div>
</main>

<?php get_footer(); ?>
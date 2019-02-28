<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail', false, '' ); ?>

<div class="post-featured-image">
    <img src="<?php echo $thumb[0]; ?>" title="<?php the_title(); ?>" />
</div>

<article>
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>        
</article>

<?php endwhile; else: ?>
<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
<?php endif; ?>
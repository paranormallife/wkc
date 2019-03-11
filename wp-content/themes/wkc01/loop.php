<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail', false, '' ); ?>

<?php if( $thumb != '' ) { ?>
    <div class="post-featured-image">
        <img src="<?php echo $thumb[0]; ?>" title="<?php the_title(); ?>" />
    </div>
<?php } else {

$args = array(
   'post_type' => 'attachment',
   'posts_per_page' => 1,
   'post_status' => 'any',
	'tax_query' => array(
		array(
			'taxonomy' => 'media_role',
			'field'    => 'slug',
			'terms'    => 'placeholder',
		),
	),
);
$the_query = new WP_Query( $args );
while ( $the_query->have_posts() ) : $the_query->the_post(); 
$URL = wp_get_attachment_url( $post->ID );?>

    <div class="post-featured-image">
        <img src="<?php echo $URL; ?>" title="<?php the_title(); ?>" />
    </div>

<?php endwhile;

// Reset Post Data
wp_reset_postdata();

} ?>

<article>
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>        
</article>

<?php endwhile; else: ?>
<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
<?php endif; ?>
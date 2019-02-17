<!-- /snippets/homepage_carousel.php : Homepage Hero Carousel -->

<?php
$the_query = new WP_Query( array( 'post_type' => 'hero', 'order' => 'ASC', 'posts_per-page' => -1 ) );
while ( $the_query->have_posts() ) : $the_query->the_post();// GET THE POST THUMBNAIL
$bg_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail', false, '' );
$subtitle = get_post_meta( $post->ID, 'hero_subtitle', true );
$summary = get_post_meta( $post->ID, 'hero_summary', true );
$url = get_post_meta( $post->ID, 'hero_url', true ); ?>

<article>
    <div class="image">
        <img src="<?php echo $bg_image[0]; ?>" />
    </div>
    <div class="content">
        <h2><?php the_title(); ?></h2>
    </div>
    
</article>

<?php endwhile;

// Reset Post Data
wp_reset_postdata();

?>
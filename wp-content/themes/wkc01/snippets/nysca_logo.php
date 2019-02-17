<!-- /snippets/nysca_logo.php | Get the Nysca Logo -->

<?php
$args = array(
   'post_type' => 'attachment',
   'posts_per_page' => 1,
   'post_status' => 'any',
	'tax_query' => array(
		array(
			'taxonomy' => 'media_role',
			'field'    => 'slug',
			'terms'    => 'nysca_logo',
		),
	),
);
$the_query = new WP_Query( $args );
// The Loop
while ( $the_query->have_posts() ) : $the_query->the_post();
$URL = wp_get_attachment_url( $post->ID ); ?>

    <a href="https://www.nysca.org/" class="nysca-logo" title="New York State Council on the Arts">
        <img src="<?php echo $URL; ?>" />
    </a>

<?php endwhile;

// Reset Post Data
wp_reset_postdata();

?>

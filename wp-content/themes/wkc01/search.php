<?php get_header(); ?>

<!-- Index Template -->

<main>
    <div class="post-content search-results">

        <h1>Search Results</h1>

        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

            <article class="search-result">
                <a href="<?php the_permalink(); ?>">
                    <h2><?php the_title(); ?></h2>
                </a>
            </article>

        <?php endwhile; else : ?>
	        <article><?php esc_html_e( 'Sorry, your search doesn\'t match any content.' ); ?></article>
        <?php endif; ?>

    
    </div>
</main>

<?php get_footer(); ?>
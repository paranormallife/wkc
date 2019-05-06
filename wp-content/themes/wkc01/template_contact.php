<?php /* Template Name: Contact Page */ get_header(); ?>

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

<div class="map">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2946.202081406543!2d-74.85397548414159!3d42.40214167918417!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89dc73e7417df521%3A0xbb27ce042a2412c6!2s49+W+Kortright+Church+Rd%2C+East+Meredith%2C+NY+13757!5e0!3m2!1sen!2sus!4v1554148983731!5m2!1sen!2sus" width="100%" height="400" frameborder="0" style="border:0" allowfullscreen></iframe>
</div>

<?php get_footer(); ?>
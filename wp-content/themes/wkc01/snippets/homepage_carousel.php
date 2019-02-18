<!-- /snippets/homepage_carousel.php : Homepage Hero Carousel -->

<section id="homepage_carousel" class="swiper-container">
    <div class="swiper-wrapper">

        <?php
        $the_query = new WP_Query( array( 'post_type' => 'hero', 'order' => 'ASC', 'posts_per-page' => -1 ) );
        while ( $the_query->have_posts() ) : $the_query->the_post();// GET THE POST THUMBNAIL
        $bg_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail', false, '' );
        $subtitle = get_post_meta( $post->ID, 'hero_subtitle', true );
        $summary = get_post_meta( $post->ID, 'hero_summary', true );
        $url = get_post_meta( $post->ID, 'hero_url', true ); ?>

        <article class="swiper-slide">
            <div class="image">
                <?php if( $url != '' ) {
                    echo '<a class="slide" href="/' . $url . '" title="';
                    the_title();
                    echo '" style="background-image:url(\'' . $bg_image[0] . '\');">' ;
                    the_title();
                    echo '</a>';
                } else {
                    echo '<div class="slide" style="background-image:url(\'' . $bg_image[0] . '\');">' ;
                    the_title();
                    echo '</div>';
                } ?>
                
            </div>
            <div class="content">
                <h2><?php the_title(); ?></h2>
                <?php if( $subtitle != '' ) { echo '<h3 class="subtitle">' . $subtitle . '</h3>'; } ?>
                <?php if( $summary != '' ) { echo '<p class="summary">' . $summary . '</p>'; } ?>
                <?php if( $url != '' ) { echo '<a class="url" href="/' . $url . '">Read More</a>'; } ?>
            </div>
        </article>

        <?php endwhile; wp_reset_postdata(); ?>

    </div>
    
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>

</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/4.4.6/js/swiper.min.js"></script>

<script>
  var mySwiper = new Swiper ('.swiper-container', {
    // Optional parameters
    direction: 'horizontal',
    loop: true,

    // If we need pagination
    pagination: {
      el: '.swiper-pagination',
    },

    // Navigation arrows
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },

    // And if we need scrollbar
    scrollbar: {
      el: '.swiper-scrollbar',
    },
  })
  </script>
<?php

/**
 * @package WordPress
 * @subpackage asw_template
 */

// Thumbnails
add_theme_support('post-thumbnails');



//menus
add_action( 'init', 'register_my_menus' );
function register_my_menus() {
	register_nav_menus(
		array(
			'nav1' => __( 'Header Navigation' ),
			'nav2' => __( 'Footer Navigation' )
		)
	);
}



// make sure quotes and single quotes dont end up in the url
add_action( 'title_save_pre', 'do_replace_dashes' );
function do_replace_dashes($string_to_clean) {
    $string_to_clean = str_replace( array('&#8212;', '—', '&#8211;', '–', '‚', '„', '“', '”', '’', '‘', '…'), array(' -- ',' -- ', '--','--', ',', ',,', '"', '"', "'", "'", '...'), $string_to_clean );
    return $string_to_clean;
}

//remove wp version from head
remove_action('wp_head', 'wp_generator');


// Custom Taxonomies (Should be above Custom Post Types)
function asw_register_taxonomies() {
	register_taxonomy("media_role", array("attachment"), 
	array(
		"hierarchical" => true, 
		"label" => __('Media Roles', 'attachment'), 
		"singular_label" => "Media Role",
		"show_in_rest" => "true", 
		"rewrite" => true));
}


// Custom Post Types

function js_init() {
  asw_register_custom_types(); // Register Custom Post Types
  asw_register_taxonomies(); // Register Custom Taxonomies
}

add_action('init', 'js_init');

function asw_register_custom_types() {
	

	// FRONT PAGE HERO
	register_post_type(
		  'hero', array(
			  'labels' => array(
				  'name' => 'Homepage Slides', 
				  'singular_name' => 'Homepage Carousel', 
				  'add_new' => 'Add new slide', 
				  'add_new_item' => 'Add new slide', 
				  'new_item' => 'New slide', 
				  'view_item' => 'View slides',
				  'edit_item' => 'Edit slide',
				  'not_found' =>  __('No slides found'),
				  'not_found_in_trash' => __('No slides found in Trash')
			  ), 
			  'public' => true,
			  'publicly_queryable' => true,
			  'show_ui' => true,
			  'query_var' => true,
			  'rewrite' => false,
			  'capability_type' => 'post',
			  'has_archive' => false,
			  'menu_icon' => 'dashicons-images-alt',
			  'exclude_from_search' => true, // If this is set to TRUE, Taxonomy pages won't work.
			  'hierarchical' => true,
			  'menu_position' => null,
			  'supports' => array('title', 'thumbnail')
		 )
	  );
	
	flush_rewrite_rules();
 
 	add_action('add_meta_boxes', 'asw_add_meta');
	add_action('save_post', 'asw_save_meta');
 
}


function asw_add_meta() {
	add_meta_box('hero_subtitle_field', 'Subtitle', 'hero_subtitle', array('hero'), 'normal', 'high');
	add_meta_box('hero_summary_field', 'Summary', 'hero_summary', array('hero'), 'normal', 'high');
	add_meta_box('hero_url_field', 'URL', 'hero_url', array('hero'), 'normal', 'high');
}

function hero_subtitle($post) {
    echo '<div id="hero_subtitle">';
    echo '<input type="text" style="width:95%;" id="hero_subtitle" name="hero_subtitle" placeholder="Subtitle" value="' . get_post_meta($post->ID, 'hero_subtitle', true) . '" />';
    echo '</div>';
}

function hero_summary($post) {
    echo '<div id="hero_summary">';
    echo '<textarea style="width:95%;" id="hero_summary" name="hero_summary" placeholder="Summary...">' . get_post_meta($post->ID, 'hero_summary', true) . '</textarea>';
    echo '</div>';
}

function hero_url($post) {
    echo '<div id="hero_url">';
    echo '<input type="text" style="width:95%;" id="hero_url" name="hero_url" placeholder="/page-permalink" value="' . get_post_meta($post->ID, 'hero_url', true) . '" />';
    echo '</div>';
}


// Save the Custom Field Data
function asw_save_meta($post_id) {

    if (wp_is_post_revision($post_id)) {
        return $post_id;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
       return $post_id;
    }

    if (isset($_POST['hero_subtitle'])) {
       update_post_meta($post_id, 'hero_subtitle', $_POST['hero_subtitle']);
    }

    if (isset($_POST['hero_summary'])) {
       update_post_meta($post_id, 'hero_summary', $_POST['hero_summary']);
    }

    if (isset($_POST['hero_url'])) {
       update_post_meta($post_id, 'hero_url', $_POST['hero_url']);
    }
	
}


function asw_widgets_init() {

	register_sidebar( array(
		'name'          => 'Home Instagram Feed',
		'id'            => 'home_instagram',
		'before_widget' => '<div>',
		'after_widget'  => '</div>',
		'before_title'  => '<h2>',
		'after_title'   => '</h2>',
	) );

}
add_action( 'widgets_init', 'asw_widgets_init' );

function mytheme_setup() {
    // Add support for Block Styles
	add_theme_support( 'wp-block-styles' );
	// Add Color Palettes
	add_theme_support( 'editor-color-palette', array(
		array(
			'name' => __( 'Black', 'wkc01' ),
			'slug' => 'black',
			'color' => '#000',
		),
		array(
			'name' => __( 'White', 'wkc01' ),
			'slug' => 'white',
			'color' => '#FFF',
		),
		array(
			'name' => __( 'WKC Blue', 'wkc01' ),
			'slug' => 'wkc_blue',
			'color' => '#1d5565',
		),
		array(
			'name' => __( 'WKC Gold', 'wkc01' ),
			'slug' => 'wkc_gold',
			'color' => '#E2B33E',
		),
	) );
	add_theme_support( 'disable-custom-colors' );
	add_theme_support('disable-custom-font-sizes');
}
add_action( 'after_setup_theme', 'mytheme_setup' );

add_action('acf/init', 'my_register_blocks');
function my_register_blocks() {

    // check function exists
    if( function_exists('acf_register_block') ) {

        // register a testimonial block
        acf_register_block(array(
            'name'              => 'testimonial',
            'title'             => __('Testimonial'),
            'description'       => __('A custom testimonial block.'),
            'render_template'   => 'snippets/team-member.php',
            'category'          => 'formatting',
            'icon'              => 'admin-comments',
            'mode'              => 'preview',
            'keywords'          => array( 'testimonial', 'quote' ),
        ));
    }
}

?>
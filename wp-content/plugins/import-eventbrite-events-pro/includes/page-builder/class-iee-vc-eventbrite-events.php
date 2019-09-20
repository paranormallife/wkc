<?php
/**
 * Class for Custom Visual Composer Element
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Eventbrite_Events_Pro
 * @subpackage Import_Eventbrite_Events_Pro/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Element Class
class IEE_VC_Eventbrite_Events extends WPBakeryShortCode {

	// Element Init
    function __construct() {
        add_action( 'init', array( $this, 'vc_eventbrite_events_mapping' ) );
    }

    // Element Mapping
    public function vc_eventbrite_events_mapping() {

        // Stop all if VC is not enabled
        if ( !defined( 'WPB_VC_VERSION' ) ) {
            return;
        }

        $event_cats = get_terms( 'eventbrite_category', array( 'hide_empty' => false ) );
        $categories = array( __('All Categories', 'import-eventbrite-events-pro' ) => '' );
        if( !empty( $event_cats ) ){
			foreach ( $event_cats as $event_cat ) {
				$categories[$event_cat->name] = $event_cat->term_id;
		}
        }

        // Map the block with vc_map()
        vc_map(
            array(
                'name' => __('Eventbrite Events', 'import-eventbrite-events-pro'),
                'base' => 'eventbrite_events',
                'category' => __('Eventbrite Events', 'import-eventbrite-events-pro'),
                'icon' => IEE_PLUGIN_URL.'assets/images/iee_icon.png',

                'params' => array(
					array(
                        'type' 		  => 'dropdown',
                        'class' 	  => 'category-class',
                        'heading' 	  => __( 'Event Category', 'import-eventbrite-events-pro' ),
                        'param_name'  => 'category',
                        'value' 	  => $categories,
                        'description' => __( 'Select Event Category from which you want to show Events.', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' 	  => 'General',
                    ),

                    array(
                        'type' => 'dropdown',
                        'class' => 'col-class',
                        'heading' => __( 'Columns', 'import-eventbrite-events-pro' ),
                        'param_name' => 'col',
                        'value' => array(
							__( '1 Column', 'import-eventbrite-events-pro' ) => '1',
							__( '2 Columns', 'import-eventbrite-events-pro' ) => '2',
							__( '3 Columns', 'import-eventbrite-events-pro' ) => '3',
							__( '4 Columns', 'import-eventbrite-events-pro' ) => '4',
                        ),
                        'std'         => '3', // default value
                        'description' => __( 'How many columns you want to set in Events grid.', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                    ),

                    array(
                        'type' => 'dropdown',
                        'class' => 'past_events-class',
                        'heading' => __( 'Show Past Events', 'import-eventbrite-events-pro' ),
                        'param_name' => 'past_events',
                        'value' => array(
							__( 'No', 'import-eventbrite-events-pro' ) => 'no',
							__( 'Yes', 'import-eventbrite-events-pro' ) => 'yes'
                        ),
                        'std'         => 'no', // default value
                        'description' => __( 'Want to show past events?', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                    ),

                    array(
                        'type' => 'textfield',
                        'class' => 'start_date-class',
                        'edit_field_class' => 'iee_datepicker vc_col-xs-6',
                        'heading' => __( 'Start Date', 'import-eventbrite-events-pro' ),
                        'param_name' => 'start_date',
                        'description' => __( 'Show events from this date.', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                        'dependency' => array(
							'element'   => 'past_events',
							'value'		=> 'no',
                        ),
                    ),

                    array(
                        'type' => 'textfield',
                        'edit_field_class' => 'iee_datepicker vc_col-xs-6',
                        'heading' => __( 'End Date', 'import-eventbrite-events-pro' ),
                        'param_name' => 'end_date',
                        'description' => __( 'Show events till this date.', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                        'dependency' => array(
							'element'   => 'past_events',
							'value'		=> 'no',
                        ),
                    ),

                    array(
                        'type' => 'textfield',
                        'edit_field_class' => 'vc_col-xs-6',
                        'heading' => __( 'Order By', 'import-eventbrite-events-pro' ),
                        'param_name' => 'orderby',
                        'description' => __( 'Enter Events Orderby examples: start_date, end_date. default: start_date.', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                    ),

                    array(
                        'type' => 'dropdown',
                        'class' => 'order-class',
                        'edit_field_class' => 'vc_col-xs-6',
                        'heading' => __( 'Order', 'import-eventbrite-events-pro' ),
                        'param_name' => 'order',
                        'value' => array(
							'ASC',
							'DESC'
                        ),
                        'std'         => 'ASC', // default value
                        'description' => __( 'Order of Events, Depends on orderby', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General',
                    ),

                    array(
                        'type' => 'textfield',
                        'class' => 'posts_per_page-class',
                        'edit_field_class' => 'vc_col-xs-6',
                        'heading' => __( 'Events per Page', 'import-eventbrite-events-pro' ),
                        'param_name' => 'posts_per_page',
                        'description' => __( 'How Many Events you wants to show per page?', 'import-eventbrite-events-pro' ),
                        'admin_label' => false,
                        'group' => 'General'
                    ),
                ),
			)
        );

    }
}

// Visual Composer Element Class Init
new IEE_VC_Eventbrite_Events();
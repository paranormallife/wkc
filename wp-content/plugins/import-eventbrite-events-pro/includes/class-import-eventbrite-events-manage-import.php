<?php
/**
 * Class for manane Imports submissions.
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Eventbrite_Events_Pro
 * @subpackage Import_Eventbrite_Events_Pro/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Eventbrite_Events_Pro_Manage_Import {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_import_form_submit' ) , 99);
		add_action( 'admin_init', array( $this, 'handle_save_scheduled_import' ) , 99);
	}

	/**
	 * Process insert group form for TEC.
	 *
	 * @since    1.0.0
	 */
	public function handle_import_form_submit() {
		global $iee_errors; 
		$event_data = array();

		if ( isset( $_POST['iee_action'] ) && $_POST['iee_action'] == 'iee_import_submit' &&  check_admin_referer( 'iee_import_form_nonce_action', 'iee_import_form_nonce' ) ) {
			
			$event_data['import_into'] = isset( $_POST['event_plugin'] ) ? sanitize_text_field( $_POST['event_plugin']) : '';
			if( $event_data['import_into'] == '' ){
				$iee_errors[] = esc_html__( 'Please provide Import into plugin for Event import.', 'import-eventbrite-events-pro' );
				return;
			}
			$event_data['import_type'] = isset( $_POST['import_type'] ) ? sanitize_text_field( $_POST['import_type']) : 'onetime';
			$event_data['import_frequency'] = isset( $_POST['import_frequency'] ) ? sanitize_text_field( $_POST['import_frequency']) : 'daily';
			$event_data['event_status'] = isset( $_POST['event_status'] ) ? sanitize_text_field( $_POST['event_status']) : 'pending';
			$event_data['event_cats'] = isset( $_POST['event_cats'] ) ? $_POST['event_cats'] : array();

			$this->handle_eventbrite_import_form_submit( $event_data );
		}
	}

	/**
	 * Update Scheduled import.
	 *
	 * @since    1.2
	 */
	public function handle_save_scheduled_import() {
		global $iee_errors, $iee_success_msg;
		if ( isset( $_POST['iee_action'] ) && $_POST['iee_action'] == 'iee_save_scheduled_import' &&  check_admin_referer( 'iee_scheduled_import_nonce_action', 'iee_scheduled_import_nonce' ) ) {

			$scheduled_title = isset($_POST['scheduled_import_name']) ? sanitize_text_field( $_POST['scheduled_import_name'] ) : '';
			$event_plugin = isset($_POST['event_plugin']) ? sanitize_text_field( $_POST['event_plugin'] ) : '';
			$import_frequency = isset($_POST['import_frequency']) ? sanitize_text_field( $_POST['import_frequency'] ) : '';
			$event_status = isset($_POST['event_status']) ? sanitize_text_field( $_POST['event_status'] ) : '';
			$event_cats = isset($_POST['event_cats']) ? array_map( 'absint', $_POST['event_cats'] ) : array();
			$event_tags = isset($_POST['event_tags']) ? array_map( 'absint', $_POST['event_tags'] ) : array();


			if( isset( $_POST['scheduled_id'] ) && $_POST['scheduled_id'] != '' ){
				$scheduled_import = get_post( absint( $_POST['scheduled_id'] ) );
				if( !empty( $scheduled_import ) ){
					$import_eventdata = get_post_meta( $scheduled_import->ID, 'import_eventdata', true );
					// Update scheduled import title
					if( $scheduled_title != get_the_title( $scheduled_import->ID ) ){
						wp_update_post( array(
								'ID'           => $scheduled_import->ID,
      							'post_title'   => $scheduled_title,
							) );
					}

					// Check if delete and reschedule needed.
					$need_reschedule = false;
					if( $import_eventdata['import_frequency'] != $import_frequency ){
						$need_reschedule = true;
					}

					// Set updated scheduled import data
					$import_eventdata['import_into'] = $event_plugin;
					$import_eventdata['import_frequency'] = $import_frequency;
					$import_eventdata['event_status'] = $event_status;
					$import_eventdata['event_cats'] = $event_cats;
					$import_eventdata['event_tags'] = $event_tags;

					// Update scheduled import data
					update_post_meta( $scheduled_import->ID, 'import_eventdata', $import_eventdata );

					if( $need_reschedule ){
						wp_clear_scheduled_hook( 'iee_run_scheduled_import', array( 'post_id' => $scheduled_import->ID ) );
						wp_schedule_event( time(), $import_frequency, 'iee_run_scheduled_import', array( 'post_id' => $scheduled_import->ID ) );
					}

					$page = isset($_GET['page'] ) ? $_GET['page'] : 'eventbrite_event';
					$tab = isset($_GET['tab'] ) ? $_GET['tab'] : 'scheduled';
					$wp_redirect = admin_url( 'admin.php?page='.$page );
					$query_args = array(
						'tab' 		 => $tab,
						'iee_msg' => 'ieesiu_success',
					);
        			wp_redirect(  add_query_arg( $query_args, $wp_redirect ) );
					exit;
				}
			}else{
				$iee_errors[] = __( 'Something went wrong! please try again.', 'import-eventbrite-events-pro' );
			}
		}
	}

	/**
	 * Handle Eventbrite import form submit.
	 *
	 * @since    1.0.0
	 */
	public function handle_eventbrite_import_form_submit( $event_data ){
		global $iee_errors, $iee_success_msg, $iee_events;
		$import_events = array();
		$eventbrite_options = iee_get_import_options('eventbrite');
		if( !isset( $eventbrite_options['eventbrite_oauth_token'] ) || $eventbrite_options['eventbrite_oauth_token'] == '' ){
			$iee_errors[] = esc_html__( 'Please insert Eventbrite "Personal OAuth token" in settings.', 'import-eventbrite-events-pro' );
			return;
		}

		$event_data['import_origin'] = 'eventbrite';
		$event_data['import_by'] = isset( $_POST['eventbrite_import_by'] ) ? sanitize_text_field( $_POST['eventbrite_import_by']) : 'event_id';
		$event_data['eventbrite_event_id'] = isset( $_POST['iee_eventbrite_id'] ) ? array_map( 'trim', (array) explode( "\n", preg_replace( "/^\n+|^[\t\s]*\n+/m", '', $_POST['iee_eventbrite_id'] ) ) ) : array();
		//$event_data['eventbrite_event_id'] = isset( $_POST['iee_eventbrite_id'] ) ? sanitize_text_field( $_POST['iee_eventbrite_id']) : '';
		$event_data['organizer_id'] = isset( $_POST['iee_organizer_id'] ) ? sanitize_text_field( $_POST['iee_organizer_id']) : '';
		
		if( $event_data['import_by'] == 'event_id' ){

			$import_events = $iee_events->eventbrite_pro->import_event_by_event_id( $event_data );
			
		}else{
			
			if( $event_data['import_by'] == 'organizer_id' ){
				if( !is_numeric( $event_data['organizer_id'] ) ){
					$iee_errors[] = esc_html__( 'Please provide valid Eventbrite organizer ID.', 'import-eventbrite-events-pro' );
					return;
				}
				$organizer_name = $iee_events->eventbrite->get_organizer_name_by_id( $event_data['organizer_id'] );
				$title =  $organizer_name . ' (by Oraganizer ID)';
			}
			if( $event_data['import_by'] == 'your_events' ){
				$title = 'Your profile Events';	
			}

			if( 'scheduled' == $event_data['import_type'] ){
				$insert_args = array(
					'post_type' => 'iee_scheduled_import',
					'post_status' => 'publish',
					'post_title' => $title
				);
				
				$insert = wp_insert_post( $insert_args, true );
				if ( is_wp_error( $insert ) ) {
					$iee_errors[] = esc_html__( 'Something went wrong when insert url.', 'import-eventbrite-events-pro' ) . $insert->get_error_message();
					return;
				}
				$import_frequency = isset( $event_data['import_frequency']) ? $event_data['import_frequency'] : 'twicedaily';
				update_post_meta( $insert, 'import_origin', 'eventbrite' );
				update_post_meta( $insert, 'import_eventdata', $event_data );
				wp_schedule_event( time(), $import_frequency, 'iee_run_scheduled_import', array( 'post_id' => $insert ) );
				$iee_success_msg[] = esc_html__( 'Import scheduled successfully.', 'import-eventbrite-events-pro' );

			}else{

				$import_events = $iee_events->eventbrite_pro->import_events( $event_data );
				
			}
		}
		if( $import_events && !empty( $import_events ) ){
			$iee_events->common->display_import_success_message( $import_events, $event_data );
		}else{
			if( empty( $iee_errors ) && 'scheduled' != $event_data['import_type'] ){
				$iee_success_msg[] = esc_html__( 'Nothing to import.', 'import-eventbrite-events-pro' );
			} 
		}
	}
}

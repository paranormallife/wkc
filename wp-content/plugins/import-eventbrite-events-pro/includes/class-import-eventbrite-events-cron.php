<?php
/**
 * Import Events Cron.
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Eventbrite_Events_Pro
 * @subpackage Import_Eventbrite_Events_Pro/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Eventbrite_Events_Pro_Cron {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct() {
		$this->load_scheduler();
	}

	/**
	 * Load the all requred hooks for run scheduler
	 *
	 * @since    1.0.0
	 */
	public function load_scheduler() {
		// Remove cron on delete meetup url.
		add_action( 'delete_post', array( $this, 'remove_scheduled_import' ) );

		// setup custom cron recurrences.
		add_filter( 'cron_schedules', array( $this, 'setup_custom_cron_recurrences' ) );

		// run scheduled importer
		add_action( 'iee_run_scheduled_import', array( $this, 'run_scheduled_importer' ), 100 );
	}

	/**
	 * Run scheduled event importer.
	 *
	 * @since    1.0.0
	 * @param int $post_id Options.
	 * @return null/void
	 */
	public function run_scheduled_importer( $post_id = 0 ) {
		global $iee_events;

		$post = get_post( $post_id );
		if( !$post || empty( $post ) ){
			return; 
		}
		$iee_events->eventbrite_pro->background_import_events( $post_id );
		return true;
	}

	/**
	 * Setup cron on add new scheduled import.
	 *
	 * @since    1.0.0
	 * @param int 	 $post_id Post ID.
	 * @param object $post Post.
	 * @param bool   $update is update or new insert.
	 * @return void
	 */
	public function setup_scheduled_import( $post_id, $post, $update ) {
		// check if not post update.
		if ( ! $update ) {

			$import_eventdata = get_post_meta( $post_id, 'import_eventdata', true );
			$import_frequency = isset( $import_eventdata['import_frequency']) ? $import_eventdata['import_frequency'] : 'twicedaily';
			wp_schedule_event( time(), $import_frequency, 'iee_run_scheduled_import', array( 'post_id' => $post_id ) );

		}
	}

	/**
	 * Remove saved cron scheduled import on delete scheduled event.
	 *
	 * @since    1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove_scheduled_import( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( $post_type == 'iee_scheduled_import' ){
			wp_clear_scheduled_hook( 'iee_run_scheduled_import', array( 'post_id' => $post_id ) );
		}
	}

	/**
	 * Setup custom cron recurrences.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function setup_custom_cron_recurrences( $schedules ) {
		// Weekly Schedule.
		$schedules['weekly'] = array(
			'display' => __( 'Once Weekly', 'import-eventbrite-events-pro' ),
			'interval' => 604800,
		);
		// Monthly Schedule.
		$schedules['monthly'] = array(
			'display' => __( 'Once a Month', 'import-eventbrite-events-pro' ),
			'interval' => 2635200,
		);
		return $schedules;
	}
}
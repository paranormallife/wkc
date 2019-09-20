<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Async_Request', false ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing/wp-async-request.php';
}

if ( ! class_exists( 'WP_Background_Process', false ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing/wp-background-process.php';
}

if( class_exists( 'WP_Background_Process', false ) ):

/*
 * IEE_Background_Process Class
 */
class IEE_Background_Process extends WP_Background_Process {
	/**
	 * @var string
	 */
	protected $action = 'iee_import';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	public function task( $item ) {
		global $iee_events;

		$post_id = isset( $item['import_id'] ) ? $item['import_id'] : 0;
		$post = get_post( $post_id );
		if( !$post || empty( $post ) ){
			return false; 
		}

		$is_stop_import = get_transient('iee_stop_import');
		if( !empty($is_stop_import) && $is_stop_import === $post_id ){
			$params['force_stopped'] = true;
			$this->after_import_process($item);
			return false;
		}

		$imported_events = array();
		$options = iee_get_import_options( 'eventbrite' );
		$import_origin = get_post_meta( $post_id, 'import_origin', true );
		$event_data = get_post_meta( $post_id, 'import_eventdata', true );

		$eventbrite_oauth_token = isset( $options['eventbrite_oauth_token'] ) ? $options['eventbrite_oauth_token'] : '';
		$organizer_id = isset( $event_data['organizer_id'] ) ? $event_data['organizer_id'] : '';
		
		if( $event_data['import_by'] == 'organizer_id' ){

			$eventbrite_api_url = 'https://www.eventbriteapi.com/v3/organizers/' . $organizer_id . '/events/?status=live&token=' . $eventbrite_oauth_token;

		}elseif( $event_data['import_by'] == 'your_events' ){

			$eventbrite_api_url = 'https://www.eventbriteapi.com/v3/users/me/events/?status=live&token=' .  $eventbrite_oauth_token;

		}

		$page = $item['page'];
		$eventbrite_api_url .= '&page=' . $page;
		$eventbrite_response = wp_remote_get( $eventbrite_api_url );
		if ( is_wp_error( $eventbrite_response ) ) {
			$this->after_import_process($item);
			error_log( __( 'wp_remote_get failed with error.', 'import-eventbrite-events-pro') );
			return false;
		}

		$eventbrite_events = json_decode( $eventbrite_response['body'], true );
		if ( is_array( $eventbrite_events ) && ! isset( $eventbrite_events['error'] ) ) {
			$events = $eventbrite_events['events'];
			$total_events = count($events);
			if( !empty( $events ) ){
				$count = 0;
				foreach( $events as $index => $event ){
					if($index <= $item['event_index'] ){
						$count++;
						continue;
					}
					$description = $iee_events->eventbrite->get_eventbrite_event_description($event['id']);
					if(!empty($description)){
						$event['description']['html'] = $description;
					}
					$item['imported_events'][] = $iee_events->eventbrite->save_eventbrite_event( $event, $event_data );
					$item['event_index'] = $count;
					$count++;
					if ( $item['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
						break;
					}
				}
			}

			$done = false;
			if($total_events == ($item['event_index']+1)){
				$done = true;
			}

			$total_pages = $eventbrite_events['pagination']['page_count'];
			if( $total_pages > $item['page'] ){
				if($done){
					$item['page']  = $item['page'] + 1;
					$item['event_index'] = -1;	
				}				
				return $item;
			}else{
				if($done){
					$this->after_import_process($item);
					return false;
				}
				return $item;
			}

		}else{
			$this->after_import_process($item);
			error_log( __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro') );
			return false;
		}
		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		error_log('Completed');
		// Show notice to user or perform some other arbitrary task...
	}

	/**
	 * Delete all import batches.
	 *
	 * @return IEE_Background_Process
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Kill process.
	 *
	 * Stop processing queue items, clear cronjob and delete all batches.
	 */
	public function kill_process() {
		if ( ! $this->is_queue_empty() ) {
			$this->delete_all_batches();
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}
	}

	public function after_import_process( $import_result ){
		global $iee_events;

		// Logs import data
		error_log( '[IMPORT_STATS_IMPORTER]' . print_r( $import_result, true ) );

		$import_events = isset( $import_result['imported_events'] ) ? $import_result['imported_events'] : array();
		$post_id = isset( $import_result['import_id'] ) ? $import_result['import_id'] : 0;
		$import_eventdata = get_post_meta( $post_id, 'import_eventdata', true );
		if( $import_events && !empty( $import_events ) ){

			$imported_ids = array();
			foreach ($import_events as $imported_event ) {
				$imported_ids[] = $imported_event['id'];
			}
			$imported_ids = array_unique( $imported_ids );
			$old_imported_ids = get_post_meta( $post_id, 'iee_sync_events', true );
			if(empty($old_imported_ids)){
				$old_imported_ids = array();
			}

			// Advanced Synchronization Start
			$xtiee_options = get_option( IEE_OPTIONS );
			$advanced_sync = isset($xtiee_options['advanced_sync']) ? $xtiee_options['advanced_sync'] : 'no';

			if( $advanced_sync == 'yes' ){
				$array_diff = array_diff( $old_imported_ids, $imported_ids );
				if( !empty( $array_diff ) ){
					$this->iee_delete_events_during_sync( $array_diff );
				}
			}else{
				if( is_array( $old_imported_ids ) ){
					$imported_ids = array_merge( $old_imported_ids, $imported_ids );	
				}				
				$imported_ids = array_unique( $imported_ids );
			}
			// Advanced Synchronization End
			update_post_meta( $post_id, 'iee_sync_events', $imported_ids );
		}
		$iee_events->common->display_import_success_message( $import_events, $import_eventdata, $post_id );
	}

	/**
	 * Delete events during sync
	 *
	 * @since    1.3
	 * @access   public
	 */
	public function iee_delete_events_during_sync( $events ) {
		if( !empty( $events ) ){
			foreach ($events as $event ) {
				$import_origin = get_post_meta( (int)$event, 'iee_event_origin', true );
				if( $import_origin == 'eventbrite' ){
					wp_trash_post( $event );
				}
			}
		}
	}
}

$importer = new IEE_Background_Process();

endif;
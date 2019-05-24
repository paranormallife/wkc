<?php
/**
 * Class for eventbrite Imports.
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Eventbrite_Events_Pro
 * @subpackage Import_Eventbrite_Events_Pro/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Eventbrite_Events_Pro_Eventbrite {

	public $oauth_token;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $iee_events;
		$options = iee_get_import_options( 'eventbrite' );
		$this->oauth_token = isset( $options['eventbrite_oauth_token'] ) ? $options['eventbrite_oauth_token'] : '';
	}

	/**
	 * import Eventbrite events by oraganiser or by user.
	 *
	 * @since    1.0.0
	 * @param array $eventdata  import event data.
	 * @return /boolean
	 */
	public function import_events( $event_data = array() ){

		global $iee_errors, $iee_events;
		$imported_events = array();
		$options = iee_get_import_options( 'eventbrite' );
		$eventbrite_oauth_token = isset( $options['eventbrite_oauth_token'] ) ? $options['eventbrite_oauth_token'] : '';
		$organizer_id = isset( $event_data['organizer_id'] ) ? $event_data['organizer_id'] : '';
		
		if( $event_data['import_by'] == 'organizer_id' ){

			$eventbrite_api_url = 'https://www.eventbriteapi.com/v3/organizers/' . $organizer_id . '/events/?status=live&token=' .  $this->oauth_token;

		}elseif( $event_data['import_by'] == 'your_events' ){

			$eventbrite_api_url = 'https://www.eventbriteapi.com/v3/users/me/events/?status=live&token=' .  $this->oauth_token;
		}

		$eventbrite_response = wp_remote_get( $eventbrite_api_url );

		if ( is_wp_error( $eventbrite_response ) ) {
			$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
			return;
		}

		$eventbrite_events = json_decode( $eventbrite_response['body'], true );
		if ( is_array( $eventbrite_events ) && ! isset( $eventbrite_events['error'] ) ) {
			$imported_events = array();
			$total_pages = $eventbrite_events['pagination']['page_count'];
			if( $total_pages > 1 ){
				for( $i = 1; $i <= $total_pages; $i++ ){
					$eventbrite_api = $eventbrite_api_url. '&page=' . $i;
					$eventbrite_response_loop = wp_remote_get( $eventbrite_api );
					if ( is_wp_error( $eventbrite_response_loop ) ) {
						$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
						return;
					}
					$eventbrite_events_loop = json_decode( $eventbrite_response_loop['body'], true );
					if ( is_array( $eventbrite_events_loop ) && ! isset( $eventbrite_events_loop['error'] ) ) {
						$events_loop = $eventbrite_events_loop['events'];
						if( !empty( $events_loop ) ){
							foreach( $events_loop as $event_loop ){
								$description = $iee_events->eventbrite->get_eventbrite_event_description($event_loop['id']);
								if(!empty($description)){
									$event_loop['description']['html'] = $description;
								}
								$imported_events[] = $iee_events->eventbrite->save_eventbrite_event( $event_loop, $event_data );
							}
						}	
					}					
				}
			}else{
				$events = $eventbrite_events['events'];
				if( !empty( $events ) ){
					foreach( $events as $event ){
						$description = $iee_events->eventbrite->get_eventbrite_event_description($event['id']);
						if(!empty($description)){
							$event['description']['html'] = $description;
						}
						$imported_events[] = $iee_events->eventbrite->save_eventbrite_event( $event, $event_data );
					}
				}	
			}			
			return $imported_events;

		}else{
			$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
			return;
		}
	}


	/**
	 * import Eventbrite events by oraganiser or by user in background.
	 *
	 * @since    1.0
	 * @param array $eventdata  import event data.
	 * @return /boolean
	 */
	public function background_import_events( $post_id = 0 ){
		global $iee_errors, $iee_events;

		$post = get_post( $post_id );
		if( !$post || empty( $post ) ){
			return; 
		}

		$default_args = array(
			'import_id'			=> $post_id, // Import_ID
			'page'				=> 1, // Page Number
			'event_index'		=> -1, // product index needed incase of memory issuee or timeout
			'prevent_timeouts'	=> true // Check memory and time usage and abort if reaching limit.
		);

		//$params = wp_parse_args( $params, $default_args );
		$params = $default_args;

		$import = new IEE_Background_Process();
		$import->push_to_queue( $params );
		$import->save()->dispatch();
		return true;
	}

	/**
	 * import Eventbrite event by ID.
	 *
	 * @since    1.0.0
	 * @param array $eventdata  import event data.
	 * @return /boolean
	 */
	public function import_event_by_event_id( $event_data = array() ){
		global $iee_errors, $iee_events;
		$options = iee_get_import_options( 'eventbrite' );
		$eventbrite_oauth_token = isset( $options['eventbrite_oauth_token'] ) ? $options['eventbrite_oauth_token'] : '';
		if ( $this->oauth_token == '' ) {
			$iee_errors[] = __( 'Please insert Eventbrite "Personal OAuth token".', 'import-eventbrite-events-pro');
			return;
		}
		$imported_events = array();
		$eventbrite_ids = isset( $event_data['eventbrite_event_id'] ) ? $event_data['eventbrite_event_id'] : 0;
		if( !empty( $eventbrite_ids ) ){
			foreach ($eventbrite_ids as $eventbrite_id ) {
				if( $eventbrite_id != '' ){

					if( !is_numeric( $eventbrite_id ) ){
						$iee_errors[] = sprintf( esc_html__( 'Please provide valid Eventbrite event ID: %s.', 'import-eventbrite-events-pro' ), $eventbrite_id ) ;
						continue;
					}

					$eventbrite_api_url = 'https://www.eventbriteapi.com/v3/events/' . $eventbrite_id . '/?token=' .  $this->oauth_token;
				    $eventbrite_response = wp_remote_get( $eventbrite_api_url , array( 'headers' => array( 'Content-Type' => 'application/json' ) ) );

					if ( is_wp_error( $eventbrite_response ) ) {
						$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
						return;
					}

					$eventbrite_event = json_decode( $eventbrite_response['body'], true );
					$description = $iee_events->eventbrite->get_eventbrite_event_description($eventbrite_id);
					if(!empty($description)){
						$eventbrite_event['description']['html'] = $description;
					}
					if ( is_array( $eventbrite_event ) && ! isset( $eventbrite_event['error'] ) ) {
						// check if recurring event
						$resource_uri = isset( $eventbrite_event['resource_uri'] ) ? $eventbrite_event['resource_uri'] : '';
						if( false !== strpos( $eventbrite_event['resource_uri'], '/series/' ) ){
							// Fetch recurring Events
							$reventbrite_api_url = 'https://www.eventbriteapi.com/v3/series/' . $eventbrite_id . '/events/?time_filter=current_future&token=' .  $this->oauth_token;
						    $reventbrite_response = wp_remote_get( $reventbrite_api_url , array( 'headers' => array( 'Content-Type' => 'application/json' ) ) );
							if ( is_wp_error( $reventbrite_response ) ) {
								$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
								return;
							}

							$reventbrite_events = json_decode( $reventbrite_response['body'], true );
							if ( is_array( $reventbrite_events ) && ! isset( $reventbrite_events['error'] ) ) {
								$rtotal_pages = $reventbrite_events['pagination']['page_count'];
								if( $rtotal_pages > 1 ){
									for( $i = 1; $i <= $rtotal_pages; $i++ ){
										$reventbrite_response_loop = wp_remote_get( $reventbrite_api_url. '&page=' . $i );
										if ( is_wp_error( $reventbrite_response_loop ) ) {
											$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
											return;
										}
										$reventbrite_events_loop = json_decode( $reventbrite_response_loop['body'], true );
										if ( is_array( $reventbrite_events_loop ) && ! isset( $reventbrite_events_loop['error'] ) ) {
											$revents_loop = $reventbrite_events_loop['events'];
											if( !empty( $revents_loop ) ){
												foreach( $revents_loop as $revent_loop ){
													$description = $iee_events->eventbrite->get_eventbrite_event_description($revent_loop['id']);
													if(!empty($description)){
														$revents_loop['description']['html'] = $description;
													}
													$imported_events[] = $iee_events->eventbrite->save_eventbrite_event( $revent_loop, $event_data );
												}
											}
										}
									}
								}else{
									$revents = $reventbrite_events['events'];
									if( !empty( $revents ) ){
										foreach( $revents as $revent ){
											$imported_events[] = $iee_events->eventbrite->save_eventbrite_event( $revent, $event_data );
										}
									}
								}

							}else{
								$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
								return;
							}

						}else{
							$imported_events[] = $iee_events->eventbrite->save_eventbrite_event( $eventbrite_event, $event_data );
						}
						
					}else{
						$iee_errors[] = __( 'Something went wrong, please try again.', 'import-eventbrite-events-pro');
						return;
					}
				}		
			}
		}
		return $imported_events;
		
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;
		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}
		return $return;
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}
		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @return bool
	 */
	protected function time_exceeded($start_time) {
		$max_time = 20; // 20 seconds.
		if (function_exists('ini_get')) {
			$max_execution_time = ini_get('max_execution_time');
			if (is_numeric($max_execution_time) && $max_execution_time > 0) {
				if ($max_execution_time >= 30) {
					$max_execution_time -= 10;
				}
				$max_time = $max_execution_time;
			}
		}
		$time_limit = min(50, $max_time);
		$finish = $start_time + $time_limit;
		if (time() >= $finish) {
			return true;
		}
		return false;
	}
}

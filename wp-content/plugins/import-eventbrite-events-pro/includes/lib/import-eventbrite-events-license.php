<?php
define( 'IEE_LICENSE_SITE_URL', 'https://xylusthemes.com' );
define( 'IEE_PRO_PLUGIN_NAME', 'Import Eventbrite Events Pro' );

// the name of the settings page for the license input to be displayed
define( 'IEE_PRO_PLUGIN_LICENSE_PAGE', 'iee_license' );

if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
}

function iee_pro_plugin_updater() {

	// retrieve our license key from the DB
	$license_key = trim( get_option( 'iee_pro_license_key' ) );

	// setup the updater
	$edd_updater = new EDD_SL_Plugin_Updater( IEE_LICENSE_SITE_URL, IEEPRO_PLUGIN_FILE, array(
			'version'   => IEEPRO_VERSION,              // current version number
			'license'   => $license_key,         // license key (used get_option above to retrieve from DB)
			'item_name' => IEE_PRO_PLUGIN_NAME, // name of this plugin
			'author'    => 'Xylus Themes'  		 // author of this plugin
		)
	);

}
add_action( 'admin_init', 'iee_pro_plugin_updater', 0 );

function iee_pro_license_page() {
	$license = get_option( 'iee_pro_license_key' );
	$status  = get_option( 'iee_pro_license_status' );
	?>
	<div class="iee_container">
    <div class="iee_row">
        <div class="xtei-column iee_well">
		<h3><?php _e( ' License Options', 'import-eventbrite-events-pro'); ?></h3>
		<form method="post" action="options.php">

			<?php settings_fields('iee_pro_license'); ?>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php _e('License Key',  'import-eventbrite-events-pro'); ?>
						</th>
						<td>
							<input id="iee_pro_license_key" name="iee_pro_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" placeholder="<?php _e('Enter your license key',  'import-eventbrite-events-pro'); ?>" />
						</td>
					</tr>
					<?php if( false !== $license ) { ?>
						<tr valign="top">
							<th scope="row" valign="top">
								<?php _e('Activate License', 'import-eventbrite-events-pro'); ?>
							</th>
							<td>
								<?php if( $status !== false && $status == 'valid' ) { ?>
									<span style="color:green;"><?php _e('active'); ?></span>
									<?php wp_nonce_field( 'iee_pro_nonce', 'iee_pro_nonce' ); ?>
									<input type="submit" class="button-secondary" name="iee_pro_license_deactivate" value="<?php _e('Deactivate License', 'import-eventbrite-events-pro'); ?>"/>
								<?php } else {
									wp_nonce_field( 'iee_pro_nonce', 'iee_pro_nonce' ); ?>
									<input type="submit" class="button-secondary" name="iee_pro_license_activate" value="<?php _e('Activate License', 'import-eventbrite-events-pro'); ?>"/>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php submit_button(); ?>

		</form>
		</div>
	</div>
	</div>
	<?php
}

function iee_pro_register_option() {
	// creates our settings in the options table
	register_setting('iee_pro_license', 'iee_pro_license_key', 'iee_pro_sanitize_license' );
}
add_action('admin_init', 'iee_pro_register_option');

function iee_pro_sanitize_license( $new ) {
	$old = get_option( 'iee_pro_license_key' );
	if( $old && $old != $new ) {
		delete_option( 'iee_pro_license_status' ); // new license has been entered, so must reactivate
	}
	return $new;
}

// activate License
function iee_pro_activate_license() {

	// listen for our activate button to be clicked
	if( isset( $_POST['iee_pro_license_activate'] ) ) {

		// run a quick security check
	 	if( ! check_admin_referer( 'iee_pro_nonce', 'iee_pro_nonce' ) )
			return; // get out if we didn't click the Activate button

		// retrieve the license from the database
		$license = trim( get_option( 'iee_pro_license_key' ) );


		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( IEE_PRO_PLUGIN_NAME ), // the name of our product in EDD
			'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( IEE_LICENSE_SITE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {

				switch( $license_data->error ) {

					case 'expired' :

						$message = sprintf(
							__( 'Your license key expired on %s.' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'revoked' :

						$message = __( 'Your license key has been disabled.' );
						break;

					case 'missing' :

						$message = __( 'Invalid license.' );
						break;

					case 'invalid' :
					case 'site_inactive' :

						$message = __( 'Your license is not active for this URL.' );
						break;

					case 'item_name_mismatch' :

						$message = sprintf( __( 'This appears to be an invalid license key for %s.' ), IEE_PRO_PLUGIN_NAME );
						break;

					case 'no_activations_left':

						$message = __( 'Your license key has reached its activation limit.' );
						break;

					default :

						$message = __( 'An error occurred, please try again.' );
						break;
				}

			}

		}

		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'admin.php?page=' . IEE_PRO_PLUGIN_LICENSE_PAGE );
			$redirect = add_query_arg( array( 'sl_activation' => 'false', 'message' => urlencode( $message ) ), $base_url );

			wp_redirect( $redirect );
			exit();
		}

		// $license_data->license will be either "valid" or "invalid"

		update_option( 'iee_pro_license_status', $license_data->license );
		wp_redirect( admin_url( 'admin.php?page=' . IEE_PRO_PLUGIN_LICENSE_PAGE ) );
		exit();
	}
}
add_action('admin_init', 'iee_pro_activate_license');

// Deactivate License
function iee_pro_deactivate_license() {

	// listen for our activate button to be clicked
	if( isset( $_POST['iee_pro_license_deactivate'] ) ) {

		// run a quick security check
	 	if( ! check_admin_referer( 'iee_pro_nonce', 'iee_pro_nonce' ) )
			return; // get out if we didn't click the Activate button

		// retrieve the license from the database
		$license = trim( get_option( 'iee_pro_license_key' ) );


		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( IEE_PRO_PLUGIN_NAME ), // the name of our product in EDD
			'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( IEE_LICENSE_SITE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

			$base_url = admin_url( 'admin.php?page=' . IEE_PRO_PLUGIN_LICENSE_PAGE );
			$redirect = add_query_arg( array( 'sl_activation' => 'false', 'message' => urlencode( $message ) ), $base_url );

			wp_redirect( $redirect );
			exit();
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if( $license_data->license == 'deactivated' ) {
			delete_option( 'iee_pro_license_status' );
		}

		wp_redirect( admin_url( 'admin.php?page=' . IEE_PRO_PLUGIN_LICENSE_PAGE ) );
		exit();

	}
}
add_action('admin_init', 'iee_pro_deactivate_license');

// Check license is valid or not.
function iee_pro_check_license() {

	global $wp_version;

	$license = trim( get_option( 'iee_pro_license_key' ) );

	$api_params = array(
		'edd_action'=> 'check_license',
		'license' 	=> $license,
		'item_name' => urlencode( IEE_PRO_PLUGIN_NAME ),
		'url'       => home_url()
	);

	// Call the custom API.
	$response = wp_remote_post( IEE_LICENSE_SITE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

	if ( is_wp_error( $response ) )
		return false;

	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	if( $license_data->license == 'valid' ) {
		echo 'valid'; exit;
		// this license is still valid
	} else {
		echo 'invalid'; exit;
		// this license is no longer valid
	}
}

function iee_pro_admin_notices() {
	if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

		switch( $_GET['sl_activation'] ) {

			case 'false':
				$message = urldecode( $_GET['message'] );
				?>
				<div class="error">
					<p><?php echo $message; ?></p>
				</div>
				<?php
				break;

			case 'true':
			default:
				// Developers can put a custom success message here for when activation is successful if they way.
				break;

		}
	}
}
add_action( 'admin_notices', 'iee_pro_admin_notices' );
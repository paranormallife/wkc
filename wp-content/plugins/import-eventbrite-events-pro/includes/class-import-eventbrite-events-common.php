<?php
/**
 * Common functions class for Import Eventbrite Events Pro.
 *
 * @link       http://xylusthemes.com/
 * @since      1.5.0
 *
 * @package    Import_Eventbrite_Events_Pro
 * @subpackage Import_Eventbrite_Events_Pro/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Eventbrite_Events_Pro_Common {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'ife_add_licence_menu' ) );
	}

	/**
	 * Create the Admin submenu and page for license activation
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function ife_add_licence_menu(){

		add_submenu_page( 'eventbrite_event', __( 'License', 'import-eventbrite-events-pro' ), __( 'License', 'import-eventbrite-events-pro' ), 'manage_options', 'iee_license', array( $this, 'iee_licence_page' ) );
	}

	/**
	 * Load License page.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	function iee_licence_page() {
		
		?>
		<div class="wrap iee_admin_panel">
		    <h2><?php esc_html_e( 'Import Eventbrite Events Pro License', 'import-eventbrite-events-pro' ); ?></h2>
		    <div id="poststuff">
		        <div id="post-body" class="metabox-holder columns-2">

		            <div id="postbox-container-1" class="postbox-container">
		            	<?php 
		            	// Sidebar here.
		            	?>
		            </div>
		            <div id="postbox-container-2" class="postbox-container">
		                <div class="import-eventbrite-events-page">

		                	<?php
		                	if( function_exists( 'iee_pro_license_page' ) ){
	                			iee_pro_license_page();
	                		}
			                ?>
		                	<div style="clear: both"></div>
		                </div>

		        </div>
		        
		    </div>
		</div>
		<?php
	}
}
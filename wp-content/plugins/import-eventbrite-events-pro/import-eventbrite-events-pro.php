<?php
/**
 * Plugin Name:       Import Eventbrite Events Pro
 * Plugin URI:        http://xylusthemes.com/plugins/import-eventbrite-events/
 * Description:       Pro Add-on for Import Eventbrite Events which adds some additional functionalities to Free version.
 * Version:           1.5.3
 * Author:            Xylus Themes
 * Author URI:        http://xylusthemes.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       import-eventbrite-events-pro
 * Domain Path:       /languages
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 * @package    Import_Eventbrite_Events_Pro
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! class_exists( 'Import_Eventbrite_Events_Pro' ) ):

/**
* Main Import Eventbrite Events Pro class
*/
class Import_Eventbrite_Events_Pro{
	
	/** Singleton *************************************************************/
	/**
	 * Import_Eventbrite_Events_Pro The one true Import_Eventbrite_Events_Pro.
	 */
	private static $instance;

    /**
     * Main Import Eventbrite Events Pro Instance.
     * 
     * Insure that only one instance of Import_Eventbrite_Events_Pro exists in memory at any one time.
     * Also prevents needing to define globals all over the place.
     *
     * @since 1.0.0
     * @static object $instance
     * @uses Import_Eventbrite_Events_Pro::setup_constants() Setup the constants needed.
     * @uses Import_Eventbrite_Events_Pro::includes() Include the required files.
     * @uses Import_Eventbrite_Events_Pro::laod_textdomain() load the language files.
     * @see run_import_eventbrite_events_pro()
     * @return object| Import Eventbrite Events the one true Import Eventbrite Events.
     */
	public static function instance() {
		if( ! isset( self::$instance ) && ! (self::$instance instanceof Import_Eventbrite_Events_Pro ) ) {
			self::$instance = new Import_Eventbrite_Events_Pro;
			self::$instance->setup_constants();

			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			add_action( 'plugins_loaded', array( self::$instance, 'iee_load_pro_addon_classes' ) );

			if( iee_free_plugin_activated() ){
				self::$instance->includes();

				// register the widget
				add_action( 'widgets_init', array( self::$instance, 'iee_register_upcoming_widget' ) );

				// Before VC Init
				add_action( 'vc_before_init', array( self::$instance, 'iee_vc_before_init_actions' ) );	
			}else{
				add_action( 'admin_notices', array( self::$instance, 'iee_free_activatation_notice' ) );
			}

		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent Import_Eventbrite_Events_Pro from being loaded more than once.
	 *
	 * @since 1.0.0
	 * @see Import_Eventbrite_Events_Pro::instance()
	 * @see run_import_eventbrite_events_pro()
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent Import_Eventbrite_Events_Pro from being cloned.
	 *
	 * @since 1.0.0
	 */
	public function __clone() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'import-eventbrite-events-pro' ), '1.5.3' ); }

	/**
	 * A dummy magic method to prevent Import_Eventbrite_Events_Pro from being unserialized.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'import-eventbrite-events-pro' ), '1.5.3' ); }

	/**
	 * Register Upcoming Events Widget
	 *
	 * @access private
	 * @since 1.3
	 * @return void
	 */
	public function iee_register_upcoming_widget(){
		register_widget( 'Import_Eventbrite_Events_Pro_Upcoming_Widget' );
	}

	/**
	 * Include Visual Composer Custom element class for Eventbrite Events.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	function iee_vc_before_init_actions() {
		// Require VC Element
		require_once IEEPRO_PLUGIN_DIR . 'includes/page-builder/class-iee-vc-eventbrite-events.php';
	}

	/**
	 * Display Notice for install free version of WP Event Aggregator.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	function iee_free_activatation_notice() {
		?>
		<div class="error">
			<p>
				<?php
				printf( __( '<strong>Import Eventbrite Events Pro</strong> requires free version <a href="%s" target="_blank">Import Eventbrite Events</a>. Please <a href="%s" class="thickbox open-plugin-details-modal">Install</a> & Activate it. <a href="%s" target="_blank">More info.</a>', 'import-eventbrite-events-pro' ), 'https://wordpress.org/plugins/import-eventbrite-events/', admin_url( "plugin-install.php?tab=plugin-information&plugin=import-eventbrite-events&TB_iframe=true&width=600&height=550" ), 'http://docs.xylusthemes.com/docs/import-eventbrite-events-plugin/plugin-installation-pro/' );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Setup plugins constants.
	 *
	 * @access private
	 * @since 1.0.0
	 * @return void
	 */
	private function setup_constants() {

		// Plugin version.
		if( ! defined( 'IEEPRO_VERSION' ) ){
			define( 'IEEPRO_VERSION', '1.5.3' );
		}

		// Plugin folder Path.
		if( ! defined( 'IEEPRO_PLUGIN_DIR' ) ){
			define( 'IEEPRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin folder URL.
		if( ! defined( 'IEEPRO_PLUGIN_URL' ) ){
			define( 'IEEPRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin root file.
		if( ! defined( 'IEEPRO_PLUGIN_FILE' ) ){
			define( 'IEEPRO_PLUGIN_FILE', __FILE__ );
		}

	}

	/**
	 * Include required files.
	 *
	 * @access private
	 * @since 1.0.0
	 * @return void
	 */
	private function includes() {

		require_once IEEPRO_PLUGIN_DIR . 'includes/class-import-eventbrite-events-common.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/class-import-eventbrite-events-cron.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/class-import-eventbrite-events-manage-import.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/lib/import-eventbrite-events-license.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/class-import-eventbrite-events-eventbrite.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/class-import-eventbrite-events-widgets.php';
		require_once IEEPRO_PLUGIN_DIR . 'includes/class-iee-backgroud-process.php';
	}

	/**
	 * Loads the plugin language files.
	 * 
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain(){
		load_plugin_textdomain(
			'import-eventbrite-events-pro',
			false,
			basename( dirname( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Loads Pro Addon Classes
	 * 
	 * @access public
	 * @since 1.5.0
	 * @return void
	 */
	public function iee_load_pro_addon_classes(){
		global $iee_events;
		if( !empty( $iee_events ) && iee_free_plugin_activated() ){
			$iee_events->common_pro = new Import_Eventbrite_Events_Pro_Common();
			$iee_events->eventbrite_pro = new Import_Eventbrite_Events_Pro_Eventbrite();
			$iee_events->cron = new Import_Eventbrite_Events_Pro_Cron();
		}
	}

}

endif; // End If class exists check.

/**
 * The main function for that returns Import_Eventbrite_Events_Pro
 *
 * The main function responsible for returning the one true Import_Eventbrite_Events_Pro
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $iee_events = run_import_eventbrite_events_pro(); ?>
 *
 * @since 1.0.0
 * @return object|Import_Eventbrite_Events_Pro The one true Import_Eventbrite_Events_Pro Instance.
 */
function run_import_eventbrite_events_pro() {
	return Import_Eventbrite_Events_Pro::instance();
}

// Get Import_Facebook_Events_Pro Running.
global $iee_events_pro;
$iee_events_pro = run_import_eventbrite_events_pro();

/**
 * Check Free version of Import_Eventbrite_Events installed or not.
 *
 * @since 1.5.0
 * @return boolean
 */
function iee_free_plugin_activated(){
	if( !function_exists( 'is_plugin_active' ) ){
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_plugin_active( 'import-eventbrite-events/import-eventbrite-events.php' ) ) {
		return true;
	}
	return false;
}
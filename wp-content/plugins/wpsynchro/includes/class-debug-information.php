<?php
namespace WPSynchro;

/**
 * Class for debug information
 * @since 1.0.3
 */
class DebugInformation
{

    private $populated = false;
    private $data_to_show = array();
    private $data_to_json_only = array();

    private function populate()
    {
        if ($this->populated) {
            return;
        }

        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get("class.CommonFunctions");
        if (\WPSynchro\WPSynchro::isPremiumVersion()) {
            $licensing = $wpsynchro_container->get("class.Licensing");
        }

        // WP synchro
        $this->data_to_show['WP Synchro plugin version'] = WPSYNCHRO_VERSION;
        $this->data_to_show['WP Synchro db version'] = get_option('wpsynchro_dbversion');
        $this->data_to_show['WP Synchro PRO'] = (\WPSynchro\WPSynchro::isPremiumVersion() ? 'yes' : 'no');
        if (\WPSynchro\WPSynchro::isPremiumVersion()) {
            $this->data_to_show['WP Synchro PRO validated'] = ($licensing->verifyLicense() ? 'yes' : 'no');
        }
        $this->data_to_show['WP Synchro compat MU plugin'] = (strlen(get_option('wpsynchro_muplugin_enabled')) > 0 ? 'yes' : 'no');

        // WP
        global $wp_version;
        $this->data_to_show['WP Version'] = $wp_version;
        $this->data_to_show['WP SAVEQUERIES used'] = (defined('SAVEQUERIES') ? 'yes' : 'no');
        $this->data_to_show['WP Memory limit'] = WP_MEMORY_LIMIT;
        $this->data_to_show['WP Max memory limit'] = WP_MAX_MEMORY_LIMIT;
        $this->data_to_show['WP multisite enabled'] = (is_multisite() ? 'yes' : 'no');

        global $wpdb;
        $this->data_to_show['MySQL Version'] = $wpdb->get_var("SELECT VERSION()");
        // Memory limits
        $this->data_to_show['Memory limit'] = number_format($commonfunctions->convertPHPSizeToBytes(ini_get('memory_limit')), 0, ",", ".") . " bytes";
        $this->data_to_show['Memory usage'] = number_format(memory_get_usage(), 0, ",", ".") . " bytes";
        // PHP
        $this->data_to_show['PHP Version'] = PHP_VERSION;
        $this->data_to_show['PHP max_execution_time'] = intval(ini_get('max_execution_time'));
        $this->data_to_show['PHP post_max_size'] = number_format($commonfunctions->convertPHPSizeToBytes(ini_get('post_max_size')), 0, ",", ".") . " bytes";


        // MYSQL
        $this->data_to_show['MySQL max_allowed_packet'] = number_format($wpdb->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'")->Value, 0, ",", ".") . " bytes";


        // JSON ONLY
        $plugins = get_plugins();
        $this->data_to_json_only['WP Active plugins'] = array();
        foreach ($plugins as $pluginfile => $plugin) {
            if (is_plugin_active($pluginfile)) {
                $this->data_to_json_only['WP Active plugins'][] = $plugin;
            }
        }

        $this->data_to_json_only['WP MU plugins'] = get_mu_plugins();
        $this->data_to_json_only['WP dropins'] = get_dropins();
        $this->data_to_json_only['PHP Extensions'] = get_loaded_extensions();

        $this->populated = true;
    }

    public function getDebugInformationArray()
    {
        $this->populate();
        return $this->data_to_show;
    }

    public function getJSONDebugInformation()
    {
        $this->populate();
        $tmp = array_merge($this->data_to_show, $this->data_to_json_only);
        return json_encode((object) $tmp);
    }
}

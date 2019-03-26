<?php
namespace WPSynchro;

/**
 * Class for common functions
 *
 * @since 1.0.0
 */
class CommonFunctions
{

    /**
     * Generate access key (used in REST access)
     * @since 1.0.0
     */
    public static function generateAccesskey()
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        return $token;
    }

    /**
     * Get transfer token based
     * @since 1.0.0
     */
    public function getTransferToken($accesskey, $jobtoken)
    {

        return sha1($accesskey . $jobtoken);
    }

    /**
     * Validate transfer token 
     * @since 1.0.0
     */
    public function validateTransferToken($token_to_validate)
    {

        // Get current job token
        $jobtoken = "";
        $accesskey = $this->getAccessKey();

        // But first check if is local accesskey    
        if ($token_to_validate == $accesskey) {
            return true;
        }

        // Now check if is valid transfer token
        $current_transfer = get_option("wpsynchro_current_transfer", null);
        if (is_object($current_transfer)) {
            // Transfer exist, so check if it has activity or old
            if ($current_transfer->last_activity > (time() - $current_transfer->lifetime)) {
                // Still existing transfer, so get that token
                $jobtoken = $current_transfer->token;
                // And update last_activity
                $current_transfer->last_activity = time();
                update_option('wpsynchro_current_transfer', $current_transfer, false);
            } else {
                // Too old
                return false;
            }
        } else {
            // Does not exist        
            return false;
        }

        $expected_transfer_token = $this->getTransferToken($accesskey, $jobtoken);
        if ($expected_transfer_token == $token_to_validate) {
            return true;
        }
        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Return this installation access key
     * @since 1.0.0
     */
    public function getAccessKey()
    {
        return get_option('wpsynchro_accesskey', "");
    }

    /**
     * Get DB temp table prefix
     * @since 1.0.0
     */
    public function getDBTempTableName()
    {
        return 'wpsyntmp_';
    }

    /**
     * Get log location
     * @since 1.0.0
     */
    public function getLogLocation()
    {
        return wp_upload_dir()['basedir'] . "/wpsynchro/";
    }

    /**
     * Get log filename
     * @since 1.0.0
     */
    public function getLogFilename($job_id)
    {
        return "runsync_" . $job_id . ".txt";
    }

    /**
     * Verify php/mysql/wp compatability
     * @since 1.0.0
     */
    public function checkEnvCompatability()
    {
        $errors = [];

        // Check PHP version 
        $required_php_version = "5.6";
        if (version_compare(PHP_VERSION, $required_php_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires PHP version %s or higher - Please update your PHP", "wpsynchro"), $required_php_version);
            // @codeCoverageIgnoreEnd
        }

        // Check MySQL version
        global $wpdb;
        $required_mysql_version = "5.5";
        $mysqlversion = $wpdb->get_var("SELECT VERSION()");
        if (version_compare($mysqlversion, $required_mysql_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires MySQL version %s or higher - Please update your MySQL", "wpsynchro"), $required_mysql_version);
            // @codeCoverageIgnoreEnd
        }

        // Check WP version
        global $wp_version;
        $required_wp_version = "4.7";
        if (version_compare($wp_version, $required_wp_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires WordPress version %s or higher - Please update your WordPress", "wpsynchro"), $required_wp_version);
            // @codeCoverageIgnoreEnd
        }

        return $errors;
    }

    /**
     *  Converts a php.ini settings like 500M to convert to bytes     
     *  @since 1.0.0
     */
    public function convertPHPSizeToBytes($sSize)
    {

        $sSuffix = strtoupper(substr($sSize, -1));
        if (!in_array($sSuffix, array('P', 'T', 'G', 'M', 'K'))) {
            return (float)$sSize;
        }
        $iValue = substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
            // Fallthrough intended
            case 'T':
                $iValue *= 1024;
            // Fallthrough intended
            case 'G':
                $iValue *= 1024;
            // Fallthrough intended
            case 'M':
                $iValue *= 1024;
            // Fallthrough intended
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (float)$iValue;
    }

    /**
     *  Check WP Synchro database version and compare with current   
     *  @since 1.0.3
     */
    public function checkDBVersion()
    {
        $dbversion = get_option('wpsynchro_dbversion');

        // If not set yet, just set it and continue with life
        if (!$dbversion || $dbversion == "") {
            $dbversion = 0;          
        }

        // Check if it is same as current
        if ($dbversion == WPSYNCHRO_DB_VERSION) {
            // Puuurfect, all good, so return
            return;
        } else {
            // Database is different than current version
            if ($dbversion > WPSYNCHRO_DB_VERSION) {
                // Its newer? :| 
                return __("WP Synchro database version is newer than the plugin version - Please upgrade plugin to newest version - Continue at own risk", "wpsynchro");
            } else {
                // Its older, so lets upgrade
                $this->handleDBUpgrade($dbversion);
            }
        }
    }

    /**
     *  Handle upgrading of DB versions
     *  @since 1.0.3
     */
    public function handleDBUpgrade($current_version)
    {

        if ($current_version > WPSYNCHRO_DB_VERSION) {
            return false;
        }

        // Version 1 - First DB version, no upgrades needed
        if ($current_version < 1) {
            // nothing to do for first version
        }

        // Version 1 > 2
        if ($current_version < 2) {

            // Enable MU Plugin by default
            update_option('wpsynchro_muplugin_enabled', "yes", true);
        }

        // Set to the db version for this release
        update_option('wpsynchro_dbversion', WPSYNCHRO_DB_VERSION, true);
    }

    /**
     *  Path fix with convert to forward slash and stuff
     *  @since 1.0.3
     */
    public function fixPath($path)
    {
        $path = str_replace("/\\", "/", $path);
        $path = str_replace("\\/", "/", $path);
        $path = str_replace("\\\\", "/", $path);
        $path = str_replace("\\", "/", $path);
        return $path;
    }

    /**
     * Recursively delete files in directory (with max timer)
     * @since 1.0.3
     */
    function removeDirectory($dir, &$start_timer, &$max_execution_time)
    {

        $this->remaining_time_for_delete = $max_execution_time - (microtime(true) - $start_timer);
        if ($this->remaining_time_for_delete < 1) {
            return false;
        }
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $response = $this->removeDirectory($dir . "/" . $object, $start_timer, $max_execution_time);
                        if ($response === false) {
                            return false;
                        }
                    } else {
                        @unlink($dir . "/" . $object);
                    }
                }
            }
            @rmdir($dir);
        }
    }

    /**
     *  Get asset full url
     *  @since 1.0.3
     */
    public function getAssetUrl($asset)
    {
        static $manifest = null;
        if ($manifest === null) {
            $manifest = json_decode(file_get_contents(WPSYNCHRO_PLUGIN_DIR . '/dist/manifest.json'));
        }

        if (isset($manifest->$asset)) {
            return untrailingslashit(WPSYNCHRO_PLUGIN_URL) . $manifest->$asset;
        } else {
            return "";
        }
    }

    /**
     *  Generate valid multipart within a certain size
     *  @since 1.0.3
     */
    public function buildRequest($delimiter, $files, $maxsize, $job_id)
    {

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $logger->setFileName($this->getLogFilename($job_id));

        $request_size = 0;
        $file_overhead = 1500;  // Bytes used beside the binary data - Such as multipart section and file data field
        $request_files = array();
        $request_fields = array();

        foreach ($files as $key => $file) {
            $file = (object) $file;
            $utf_decoded = false;
            if (!file_exists($file->source_file)) {
                $file->source_file = utf8_decode($file->source_file);
                $utf_decoded = true;
            }
            $current_file_size = filesize($file->source_file);
            if (($current_file_size + $request_size + $file_overhead) > $maxsize || (isset($file->partial) && $file->partial == true)) {
                $logger->log("DEBUG", "No space for entire file, will chunk it: " . $file->source_file);
                // Too big, needs to be chunked
                if (($request_size + $file_overhead) >= $maxsize) {
                    // There is no room for more data, so break out      
                    break;
                } else {
                    // There is room for a chunk of this file, but not the whole file          
                    $available_space_for_chunk = $maxsize - ($request_size + $file_overhead);

                    if (isset($file->partial) && $file->partial == true) {
                        // Already chunked, so continue from last position						
                        $already_transferred_bytes = $file->partial_position;
                        $logger->log("DEBUG", "Already chunked, start position: " . $already_transferred_bytes . " and available: " . $available_space_for_chunk);
                        $request_files[$key] = file_get_contents($file->source_file, false, null, $already_transferred_bytes, $available_space_for_chunk);
                        $file->partial = 1;
                        $file->partial_position = $already_transferred_bytes;
                        $added_request_size = strlen($request_files[$key]);
                        $request_size += $added_request_size + $file_overhead;
                        if ($utf_decoded) {
                            $file->source_file = utf8_encode($file->source_file);
                        }
                        $request_fields["file_key_" . $key] = json_encode($file);
                    } else {
                        // First read of chunked part, so start from 0                
                        $logger->log("DEBUG", "First chunk, start position: 0 and available: " . $available_space_for_chunk);
                        $request_files[$key] = file_get_contents($file->source_file, false, null, 0, $available_space_for_chunk);
                        $file->partial = 1;
                        $file->partial_position = 0;
                        $added_request_size = strlen($request_files[$key]);
                        $request_size += $added_request_size + $file_overhead;
                        if ($utf_decoded) {
                            $file->source_file = utf8_encode($file->source_file);
                        }
                        $request_fields["file_key_" . $key] = json_encode($file);
                    }
                }
            } else {
                // File can fit
                $logger->log("DEBUG", "File can be contained whole in request: " . $file->source_file);
                $request_files[$key] = file_get_contents($file->source_file);
                $request_size += $current_file_size + $file_overhead;
                if ($utf_decoded) {
                    $file->source_file = utf8_encode($file->source_file);
                }
                $request_fields["file_key_" . $key] = json_encode($file);
            }
        }


        $request_build = $this->buildMultipartSection($delimiter, $request_fields, $request_files);

        return $request_build;
    }

    /**
     *  Build multipart section for curl POST
     *  @since 1.0.3
     */
    public function buildMultipartSection($delimiter, $fields, $files)
    {

        $data = '';
        $eol = "\r\n";

        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . "\"" . $eol . $eol
                . $content . $eol;
        }


        foreach ($files as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $name . '"' . $eol
                . 'Content-Transfer-Encoding: binary' . $eol
            ;

            $data .= $eol;
            $data .= $content . $eol;
        }
        $data .= "--" . $delimiter . "--" . $eol;

        return $data;
    }

    /**
     *  Cleanup response body data from RemotePost/GETS. Such as remove UTF8 which json_decode pukes over
     *  @since 1.0.3
     */
    public function cleanRemoteJSONData($response_body)
    {
        // Remove UTF8 BOM which json_decode does not like
        if (substr($response_body, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) {
            $response_body = substr($response_body, 3);
        }
        return $response_body;
    }

    /**
     *  Clean up WP Synchro installation (used in setup)
     */
    public function cleanUpPluginInstallation()
    {

        global $wpsynchro_container;
        $synclist = $wpsynchro_container->get("class.SyncList");

        // Setup
        $log_dir = wp_upload_dir()['basedir'] . "/wpsynchro/";
        $db_prefix = "wpsynchro_";
        $dir_prefix = $synclist->tmp_prefix;
        $dir_prefix_length = strlen($dir_prefix);

        // Clean files *.tmp and *.txt
        @array_map('unlink', glob("$log_dir*.log"));
        @array_map('unlink', glob("$log_dir*.txt"));
        @array_map('unlink', glob("$log_dir*.tmp"));

        // Delete from database
        $options_to_keep = array(
            "wpsynchro_license_key",
            "wpsynchro_dbversion",
            "wpsynchro_accesskey",
            "wpsynchro_allowed_methods",
        );

        global $wpdb;
        $wpdb->query("delete FROM " . $wpdb->options . " WHERE option_name like '" . $db_prefix . "%' and option_name not in ('" . implode("','", $options_to_keep) . "') ");
    }
}

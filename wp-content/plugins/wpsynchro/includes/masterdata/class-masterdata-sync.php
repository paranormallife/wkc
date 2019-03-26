<?php
namespace WPSynchro\Masterdata;

/**
 * Class for handling the masterdata of the sync
 *
 * @since 1.0.0
 */
class MasterdataSync
{

    // Base data
    public $allotted_time = 0;
    public $starttime = 0;
    public $installation = null;
    public $job = null;
    public $remote_wpdb = null;
    public $logger = null;

    /**
     *  Handle masterdata step
     *  @since 1.0.3
     */
    public function runMasterdataStep(&$installation, &$job, $allotted_time_for_subjob)
    {
        $this->installation = &$installation;
        $this->job = &$job;
        $this->allotted_time = $allotted_time_for_subjob;
        $this->starttime = microtime(true);

        global $wpsynchro_container;
        $this->logger = $wpsynchro_container->get("class.Logger");

        // Figure out what data is needed
        $data_to_retrieve = array();
        if ($this->installation->sync_database) {
            $data_to_retrieve[] = "dbdetails";
        }
        if ($this->installation->sync_files) {
            $data_to_retrieve[] = "filedetails";
        }

        // Retrieve data
        $metadata_results = array();
        $remainingtime = $this->allotted_time - (microtime(true) - $this->starttime);
        $metadata_results['from'] = $this->retrieveMasterdata('from', $data_to_retrieve, $remainingtime);
        $remainingtime = $this->allotted_time - (microtime(true) - $this->starttime);
        $metadata_results['to'] = $this->retrieveMasterdata('to', $data_to_retrieve, $remainingtime);

        // Run through result and check for errors
        $result = new \stdClass();
        $result->errors = array();

        // Check for errors
        foreach ($metadata_results as $prefix => $masterdata) {
            if (isset($masterdata->errors) && count($masterdata->errors) > 0) {
                $result->errors = array_merge($masterdata->errors, $result->errors);
                $result->success = false;
                return $result;
            }
        }

        // If data->status is set, its a REST error - Probably wrong accesskey
        if (isset($metadata_results['from']->response->data->status) || isset($metadata_results['to']->response->data->status)) {
            if (isset($metadata_results['from']->response->data->status)) {
                $this->logger->log("ERROR", "Masterdata cannot be fetched from the source - Probably wrong access key", $metadata_results['from']);
                $result->errors[] = __("Masterdata cannot be fetched from the source - Check that the access key and url is correct - Check the log file for further details on the error", "wpsynchro");
            } else {
                $this->logger->log("ERROR", "Masterdata cannot be fetched from the target - Probably wrong access key", $metadata_results['to']);
                $result->errors[] = __("Masterdata cannot be fetched from the target - Check that the access key and url is correct - Check the log file for further details on the error", "wpsynchro");
            }
            $result->success = false;
            return $result;
        }

        foreach ($metadata_results as $prefix => $masterdata) {

            $masterdata = $masterdata->response;

            if (in_array("dbdetails", $data_to_retrieve)) {
                if (!isset($masterdata->dbdetails) || $masterdata->dbdetails == null) {
                    $result->errors[] = __("Could not retrieve database info from target:", "wpsynchro") . " " . $prefix;
                    continue;
                }
            }

            // General
            if (isset($masterdata->dbdetails)) {
                $tmp_var = $prefix . '_dbmasterdata';
                $this->job->$tmp_var = $masterdata->dbdetails;
            }

            $mappings = array(
                "_client_home_url" => "client_home_url",
                "_rest_base_url" => "rest_base_url",
                "_wp_options_table" => "wp_options_table",
                "_max_allowed_packet_size" => "max_allowed_packet_size",
                "_max_post_size" => "max_post_size",
                "_memory_limit" => "memory_limit",
                "_upload_max_filesize" => "upload_max_filesize",
                "_max_file_uploads" => "max_file_uploads",
                "_sql_version" => "sql_version",
                "_plugin_version" => "plugin_version",
                "_files_above_webroot_dir" => "files_above_webroot_dir",
                "_files_home_dir" => "files_home_dir",
                "_files_wp_content_dir" => "files_wp_content_dir",
                "_files_wp_dir" => "files_wp_dir",
                "_files_plugin_list" => "files_plugin_list",
                "_files_theme_list" => "files_theme_list",
            );

            foreach ($mappings as $job_key => $masterdata_key) {
                if (!isset($masterdata->$masterdata_key)) {
                    continue;
                }
                $tmp_var = $prefix . $job_key;
                $this->job->$tmp_var = $masterdata->$masterdata_key;

                if (is_array($this->job->$tmp_var) || is_object($this->job->$tmp_var)) {
                    $this->logger->log("DEBUG", "Masterdata (" . $prefix . ") - " . $job_key . ": ", $this->job->$tmp_var);
                } else {
                    $this->logger->log("DEBUG", "Masterdata (" . $prefix . ") - " . $job_key . ": " . $this->job->$tmp_var);
                }
            }
        }

        if (count($result->errors) == 0) {
            // Check that plugin versions are identical on both sides, otherwise raise error
            if ($this->job->from_plugin_version != $this->job->to_plugin_version) {
                $result->errors[] = sprintf(__("WP Synchro plugin versions do not match on both sides. One runs version %s and other runs %s. Make sure they use same version to prevent problems caused by different versions of plugin.", "wpsynchro"), $this->job->from_plugin_version, $this->job->to_plugin_version);
            }

            // Check licensing 
            if (\WPSynchro\WPSynchro::isPremiumVersion()) {
                global $wpsynchro_container;
                $licensing = $wpsynchro_container->get("class.Licensing");
                $licens_sync_result = $licensing->verifyLicenseForSynchronization($this->job->from_client_home_url, $this->job->to_client_home_url);

                if ($licens_sync_result->state == false) {
                    $result->errors = array_merge($result->errors, $licens_sync_result->errors);
                }
            }
        }


        if (count($result->errors) > 0) {
            foreach ($result->errors as $error) {
                $this->logger->log("ERROR", $error);
            }
            $result->success = false;
        } else {
            $result->success = true;
        }

        return $result;
    }

    /**
     *  Retrieve masterdata 
     *  @since 1.0.0
     */
    public function initiateSynchronization($installation, $allottedtime = 30)
    {
        $this->installation = $installation;

        // Init logging
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $result = new \stdClass();
        $result->errors = array();
        $result->token = "";

        // Args
        $args = array(
            'method' => 'POST',
            'timeout' => ceil($allottedtime),
            'redirection' => 2,
            'sslverify' => $this->installation->verify_ssl,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        $baseurl = trailingslashit($this->installation->site_url) . "wp-json/wpsynchro/v1/initiate/";

        $logger->log("DEBUG", "Calling initate remote service with url: " . $baseurl . " and args: ");

        $response_arr = \wp_remote_post($baseurl, $args);

        if (is_wp_error($response_arr)) {
            $result->errors[] = sprintf(__("Failed initializing - WP Error with message: %s", "wpsynchro"), $response_arr->get_error_message());
            $logger->log("CRITICAL", "Failed initializing - WP Error: " . $response_arr->get_error_message());
        } else {
            $body_json = $common->cleanRemoteJSONData(wp_remote_retrieve_body($response_arr));

            if ($response_arr['response']['code'] == 200) {
                $response_body = json_decode($body_json);
                if (isset($response_body->token)) {
                    $result->token = $response_body->token;
                    $logger->log("DEBUG", "Got remote transfer token: " . $result->token);
                } else {
                    $result->errors[] = __("Failed initializing - Could not fetch a initiation token from remote - Maybe its already running?", "wpsynchro");
                    $logger->log("DEBUG", "Failed initializing - Could not fetch a initiation token from remote -  Response body:", $response_body);
                }
            } else if (isset($response_arr['body'])) {
                $response_body = json_decode($response_arr['body']);
                if (isset($response_body->errors) && count($response_body->errors) > 0) {
                    $result->errors = array_merge($result->errors, $response_body->errors);
                    foreach ($response_body->errors as $errormsg) {
                        $logger->log("ERROR", $errormsg);
                    }
                }
            } else {
                $result->errors[] = __("Failed initializing - Did not get a response from remote server", "wpsynchro");
                $logger->log("DEBUG", "Failed initializing - Did not get a response from remote server -  Response body:", $response_body);
            }
        }


        return $result;
    }

    /**
     *  Retrieve masterdata 
     *  @since 1.0.0
     */
    public function retrieveMasterdata($to_or_from = 'from', $slugs_to_retrieve = array(), $allotted_time)
    {
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        $result = new \stdClass();
        $result->errors = array();

        // Generate query string
        $querystring = "";
        foreach ($slugs_to_retrieve as $slug) {
            $querystring .= "&type[]=" . $slug;
        }
        $querystring = trim($querystring, "&");

        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');

        // Args
        $args = array(
            'method' => 'POST',
            'timeout' => ceil($allotted_time),
            'redirection' => 0,
            'sslverify' => $this->installation->verify_ssl,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        // Get webservice url
        if (($this->installation->type == 'pull' && $to_or_from == 'to') || ($this->installation->type == 'push' && $to_or_from == 'from')) {
            $baseurl = rest_url("wpsynchro/v1/masterdata/?" . $querystring . "&token=" . $commonfunctions->getAccessKey());
        } else if (($this->installation->type == 'pull' && $to_or_from == 'from') || ($this->installation->type == 'push' && $to_or_from == 'to')) {
            $transfertoken = $commonfunctions->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $baseurl = trailingslashit($this->installation->site_url) . "wp-json/wpsynchro/v1/masterdata/?" . $querystring . "&token=" . $transfertoken;
        } else {
            $result->errors[] = __("Error in configuration - Create a new job", "wpsynchro");
            return $result;
        }

        $logger->log("DEBUG", "Calling masterdata service on: " . $baseurl . " with intent to user as '" . $to_or_from . "'");

        $response_arr = \wp_remote_get($baseurl, $args);

        if (is_wp_error($response_arr)) {       
            $errormsg = $response_arr->get_error_message();
            if (strpos($errormsg, "cURL error 60") > -1) {
                $result->errors[] = __("Remote or local SSL certificate is not valid or self-signed. To allow non-valid SSL certificates, you need to edit the installation and change it.", "wpsynchro");
                $logger->log("CRITICAL", "Remote or local SSL certificate is not valid or self-signed. ", $response_arr);
            } else {
                $result->errors[] = $errormsg;
                $logger->log("CRITICAL", "Remote service 'clientsyncdatabase' failed with WP error: " . $errormsg, $response_arr);
            }
        } else {
            $body_json = wp_remote_retrieve_body($response_arr);
            $body_json = $commonfunctions->cleanRemoteJSONData($body_json);
            $result->response = json_decode($body_json);
        }

        return $result;
    }
}

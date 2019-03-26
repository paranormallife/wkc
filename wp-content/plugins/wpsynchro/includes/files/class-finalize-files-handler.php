<?php
namespace WPSynchro\Files;

/**
 * Class for handling files finalize
 * @since 1.0.3
 */
class FinalizeFiles
{

    // Data objects 
    public $job = null;
    public $installation = null;
    public $sync_list = null;
    public $remote_post_obj = null;
    public $target_url = null;

    /**
     *  Constructor
     */
    public function __construct(\WPSynchro\RemotePOST $remote_post_obj)
    {
        $this->remote_post_obj = $remote_post_obj;
    }

    /**
     *  Initialize class
     *  @since 1.0.3
     */
    public function init(\WPSynchro\Files\SyncList &$sync_list, \WPSynchro\Installation &$installation, \WPSynchro\Job &$job)
    {

        $this->sync_list = $sync_list;
        $this->installation = $installation;
        $this->job = $job;

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        if ($this->installation->type == 'pull') {
            $this->target_url = $this->job->to_rest_base_url . "wpsynchro/v1/finalize/?token=" . $common->getAccessKey();
        } else if ($this->installation->type == 'push') {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $this->target_url = $this->job->to_rest_base_url . "wpsynchro/v1/finalize/?token=" . $key;
        }
    }

    /**
     * Transfer the rest of the files in file list
     * @since 1.0.3
     */
    public function finalizeFiles($allotted_time)
    {
        $starttime = microtime(true);
        $result = new \stdClass();
        $result->success = false;
        $result->errors = array();


        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("INFO", "Starting file finalize with allotted time: " . $allotted_time);
        $common = $wpsynchro_container->get("class.CommonFunctions");

        if (!$this->job->finalize_file_data_initialised) {

            // Generate the actions that needs to be done
            $random_prefix = uniqid();
            $all_renames = array();
            $delete_when_successful = array();

            // Collect all the needed renames 
            foreach ($this->sync_list->sections as &$section) {
                $all_renames = array_merge($all_renames, $section->finalize_renames);
            }

            // Generate a path for the current file/dir can be moved to temporarily, in case a rollback is required
            foreach ($all_renames as &$rename) {
                $temp_path = trailingslashit(dirname($rename['to'])) . $random_prefix . "-" . basename($rename['to']);
                $rename['temp_to'] = $temp_path;
                $delete_when_successful[] = $rename['temp_to'];
            }

            $this->job->finalize_files_renames = $all_renames;
            $this->job->finalize_files_deletes = $delete_when_successful;
            $this->job->finalize_file_data_initialised = true;
        }

        // Now we have all the work needed, so call the finalize REST service on the target  
        $body = new \stdClass();
        $body->allotted_time = $allotted_time;
        $body->renames = $this->job->finalize_files_renames;
        $body->rollback_sequence = $this->job->finalize_files_rollback;
        $body->delete = $this->job->finalize_files_deletes;

        $args = array(
            'method' => 'POST',
            'timeout' => ceil($allotted_time),
            'redirection' => 2,
            'sslverify' => $this->installation->verify_ssl,
            'body' => json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        // Clear renames, which should be done.
        $this->job->finalize_files_renames = array();

        // Call remote service.
        $response_arr = $this->remote_post_obj->remotePOST($this->target_url, $args);

        if (wp_remote_retrieve_response_code($response_arr) == 200) {
            $body_json = wp_remote_retrieve_body($response_arr);
            $body_json = $common->cleanRemoteJSONData($body_json);
            $body = json_decode($body_json);

            // Handle return data            
            foreach ($body->debug_log as $debuglog) {
                $logger->log("DEBUG", $debuglog);
            }
            foreach ($body->warning_log as $warninglog) {
                $this->job->warnings[] = $warninglog;
                $logger->log("WARNING", $warninglog);
            }
            foreach ($body->error_log as $errorlog) {
                $result->errors[] = $errorlog;
                $logger->log("ERROR", $errorlog);
            }

            $result->completed = $body->completed;
            $result->success = true;
            $this->job->finalize_files_deletes = $body->delete;
        } else {
            $logger->log("CRITICAL", "Error calling finalize REST service - Did not get a 200 response on url: " . $this->target_url, $response_arr);
            $result->errors[] = __("Error calling finalize REST service - Did not get a 200 response", "wpsynchro");
        }

        $endtime = microtime(true);
        $logger->log("INFO", "Completed file finalize run on: " . ($endtime - $starttime) . " seconds");

        return $result;
    }
}

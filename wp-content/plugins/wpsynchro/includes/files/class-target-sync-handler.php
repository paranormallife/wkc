<?php
namespace WPSynchro\Files;

/**
 * Class for handling files synchronization on target
 * @since 1.0.3
 */
class TargetSync
{

    // Data objects   
    public $job = null;
    public $installation = null;
    public $sync_list = null;
    public $remote_post_obj = null;
    public $target_sync_url = null;

    // Files data

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
    }

    /**
     * Target file list processing (compare hash on target, create temp dirs, copy equal files and report back)
     * @since 1.0.3
     */
    public function processWorkChunkOnTarget($allotted_time)
    {
        $errors = array();
        $time_start = microtime(true);

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        // Get a work chunk to send to target
        $body = array();
        $body['allotted_time'] = $allotted_time * 0.8;
        $json_encoded_body = $this->sync_list->getFileChunkForTargetSync($body);

        // Determine URL and key
        if ($this->target_sync_url == null) {

            if ($this->installation->type == 'pull') {
                $this->target_sync_url = $this->job->to_rest_base_url . "wpsynchro/v1/filetargetsync/?token=" . $common->getAccessKey();
            } else if ($this->installation->type == 'push') {
                $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
                $this->target_sync_url = $this->job->to_rest_base_url . "wpsynchro/v1/filetargetsync/?token=" . $key;
            }
        }

        $args = array(
            'method' => 'POST',
            'timeout' => ceil($allotted_time),
            'redirection' => 2,
            'sslverify' => $this->installation->verify_ssl,
            'body' => $json_encoded_body,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        $response_arr = $this->remote_post_obj->remotePOST($this->target_sync_url, $args);
        if (wp_remote_retrieve_response_code($response_arr) == 200) {
            // Handle return data  
            $body_json = wp_remote_retrieve_body($response_arr);
            $body_json = $common->cleanRemoteJSONData($body_json);
            $response_obj = json_decode($body_json);

            $section_work_chunk = (array) $response_obj->work;
        } else {
            $errors[] = sprintf(__("Error during file synchronization - Could not complete target sync request - http error: %d", "wpsynchro"), wp_remote_retrieve_response_code($response_arr));
            return $errors;
        }

        // Update the state of the files
        $this->sync_list->updateFileChunkForTargetSync($section_work_chunk);

        // Throttle up/down in size (megabyte that target needs to target sync (hash, compare, and maybe copy files) within allotted time
        $time_stop = microtime(true);
        $lastrun_time = $time_stop - $time_start;

        // Each run time should keep well within the allotted time
        $max_time = $allotted_time * 0.7;
        $max_post_size = $this->job->to_max_post_size * 0.7;
        $postsize = strlen($json_encoded_body);

        // Throttle max size for target sync (sum of filesize to check/hash/copy)
        if ($lastrun_time >= $max_time || $postsize >= $max_post_size) {
            // Throttle a bit back - Can either be the time or the post size
            $this->job->files_target_sync_throttle_maxsize = $this->job->files_target_sync_throttle_maxsize * 0.9;
        } else {
            $this->job->files_target_sync_throttle_maxsize = $this->job->files_target_sync_throttle_maxsize * 1.05;
        }

        if ($this->job->files_target_sync_throttle_maxsize > $this->job->files_target_sync_throttle_maxsize_max) {
            $this->job->files_target_sync_throttle_maxsize = $this->job->files_target_sync_throttle_maxsize_max;
        }


        return $errors;
    }
}

<?php
namespace WPSynchro\Files;

/**
 * Class for hashing section file lists
 * @since 1.0.3
 */
class HashListHandler
{

    // Data objects   
    public $job = null;
    public $installation = null;
    public $sync_list = null;
    public $remote_post_obj = null;

    /**
     *  Constructor
     *  @since 1.0.3
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
     * Hashing File List
     * @since 1.0.3
     */
    public function hashFilelist($allotted_time)
    {
        $errors = array();
        $time_start = microtime(true);
        $allotted_time -= 1; // Just subtract a bit

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");


        // Make sure files is hashed
        foreach ($this->sync_list->sections as $key => &$section) {
            if (!$section->files_hashing_complete) {
                // File list is not yet hashed         
                $file_list = $this->getFileHashFromSource($section, $allotted_time);
                $hashed_files_this_round = 1;
                foreach ($file_list as $key => $file) {
                    if ($section->file_list[$key]->hash != $file->hash) {
                        $section->file_list[$key]->hash = $file->hash;
                        $hashed_files_this_round++;
                    }
                }
                $logger->log("DEBUG", "Set " . $hashed_files_this_round . " files to hashed this run");
                break;
            }
        }

        $this->sync_list->updateSectionState();


        return $errors;
    }

    /**
     *  Get file list data from source installation
     *  @since 1.0.3
     */
    public function getFileHashFromSource(&$section, $allotted_time)
    {

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $logger = $wpsynchro_container->get("class.Logger");

        // Determine URL and key
        if ($this->installation->type == 'pull') {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/hashfilelist/?token=" . $key;
        } else if ($this->installation->type == 'push') {
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/hashfilelist/?token=" . $common->getAccessKey();
        } else {
            $this->errors[] = __("Unknown installation type", "wpsynchro");
            return;
        }

        // Make sure the post data is within post_max_size defined on the source
        $postmaxsize = $this->job->from_max_post_size - 1000; // - 1000 just to make sure
        // Lets just keep a max of 2mb for these requests
        $max_wanted_size = 2 * 1024 * 1024;
        if ($postmaxsize > $max_wanted_size) {
            $postmaxsize = $max_wanted_size;
        }

        $body = new \stdClass();
        $body->allotted_time = $allotted_time;
        $json_encoded_body = $this->createSectionRequestWithMaxsize($section, $body, $postmaxsize);

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

        $response_arr = $this->remote_post_obj->remotePOST($url, $args);

        if (wp_remote_retrieve_response_code($response_arr) == 200) {
            $body_json = wp_remote_retrieve_body($response_arr);
            $body_json = $common->cleanRemoteJSONData($body_json);
            $body = json_decode($body_json);

            // Handle return data   
            if ( !isset($body->file_list)) {
                $logger->log("ERROR", "Error during file hashing - File list not returned correctly", $response_arr);
            }
            return $body->file_list;
        } else {
            $logger->log("CRITICAL", "Error during file hashing - Could not get data from source", $response_arr);
            $this->errors[] = __("Error during file hashing - Could not get data from source", "wpsynchro");
        }
    }

    /**
     *  Create section request with a max size
     *  @since 1.0.3
     */
    public function createSectionRequestWithMaxsize(&$section, $body, $maxsize)
    {

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        $newsection = new \stdClass();
        $filedata = array();
        $newsection->file_list = &$filedata;
        $body->section = $newsection;

        $available_space = ($maxsize - strlen(json_encode($body))) * 0.8;

        // Lets take 1000 files at a time        
        $filecounter = 1;
        $lastsize = 0;
        foreach ($section->file_list as $key => $file) {
            if ($file->hash == null) {
                $filedata[$key] = $file;
                $filecounter++;
            }

            if ($filecounter % 1000 == 0) {
                // Check for size for each 1000 files
                $json_encoded = json_encode($body);
                $length = strlen($json_encoded);
                $length_added = $length - $lastsize;
                if (($length + $length_added) > $available_space) {
                    // No more space, so just break
                    break;
                }
                // If more space, we take another 1000 files
            }
        }
        if (!isset($json_encoded)) {
            $json_encoded = json_encode($body);
        }

        if (!isset($length)) {
            $length = strlen($json_encoded);
        }
        $logger->log("DEBUG", "Generate file hash request with " . $filecounter . " files and size: " . $length . " bytes with allowed: " . $maxsize);

        return $json_encoded;
    }
}

<?php
namespace WPSynchro\Files;

/**
 * Class for populating section file lists
 * @since 1.0.3
 */
class PopulateListHandler
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
     * Populate File List
     * @since 1.0.3
     */
    public function populateFilelist($allotted_time)
    {
        $errors = array();
        $time_start = microtime(true);
        $allotted_time -= 1; // Just subtract a bit, but should not be a problem here unless ultra slow hosting

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        // Make sure data is populated (aka make sure that our work list is generated and complete) 
        foreach ($this->sync_list->sections as $key => &$section) {
            if (!$section->files_list_complete) {
                // File list is not yet populated
                $file_list = $this->getFileDataFromSource($section, $allotted_time); 
                if (!is_array($file_list)) {
                    $logger->log("CRITICAL", "Section " . $section->name . " returned NULL from getFileDataFromSource method", $file_list);
                    $errors[] = __("Got wrong response from remote service during file list population - Contact support", "wpsynchro");
                    return $errors;
                } else {
                    $section->file_list = $file_list;
                    $section->files_list_complete = true;
                    $logger->log("DEBUG", "Populated section " . $section->name . " with " . count($section->file_list) . " files");
                }

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
    public function getFileDataFromSource(&$section, $allotted_time)
    {

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $logger = $wpsynchro_container->get("class.Logger");

        // Determine URL and key
        if ($this->installation->type == 'pull') {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/populatefilelist/?token=" . $key;
        } else if ($this->installation->type == 'push') {
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/populatefilelist/?token=" . $common->getAccessKey();
        } else {
            $this->errors[] = __("Unknown installation type", "wpsynchro");
            return;
        }

        $body = new \stdClass();
        $body->allotted_time = $allotted_time;
        $body->exclusions = $this->installation->files_exclude_files_match;
        $body->section = $section;
        $json_encoded_body = json_encode($body);

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
            if (!isset($body->file_list)) {
                $logger->log("CRITICAL", sprintf("Error during file population - Did not retrieve file list from remote REST service despite http 200 with url: %s", $url), $args);
                return null;
            }
            if (isset($body->temp_dirs_in_basepath)) {
                $body->temp_dirs_in_basepath = (array) $body->temp_dirs_in_basepath;
                $section->temp_dirs_in_basepath = array_merge($section->temp_dirs_in_basepath, $body->temp_dirs_in_basepath);
            }

            return $body->file_list;
        } else {
            $logger->log("CRITICAL", "Error during file population - Could not get file data from source");
        }
    }
}

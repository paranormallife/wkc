<?php
namespace WPSynchro\Files;

/**
 * Class for handling files transfer from source to target 
 * @since 1.0.3
 */
class TransferFiles
{

    // Data objects 
    public $job = null;
    public $installation = null;
    public $sync_list = null;
    public $remote_post_obj = null;
    public $logger = null;
    public $filetransfer_target_url = null;
    public $getfiles_source_url = null;

    /**
     *  Constructor
     */
    public function __construct(\WPSynchro\RemotePOST $remote_post_obj)
    {
        $this->remote_post_obj = $remote_post_obj;

        global $wpsynchro_container;
        $this->logger = $wpsynchro_container->get("class.Logger");
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
            $this->filetransfer_target_url = $this->job->to_rest_base_url . "wpsynchro/v1/filetransfer/?token=" . $common->getAccessKey();
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $this->getfiles_source_url = $this->job->from_rest_base_url . "wpsynchro/v1/getfiles/?token=" . $key;
        } else if ($this->installation->type == 'push') {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $this->filetransfer_target_url = $this->job->to_rest_base_url . "wpsynchro/v1/filetransfer/?token=" . $key;
            $this->getfiles_source_url = $this->job->from_rest_base_url . "wpsynchro/v1/getfiles/?token=" . $common->getAccessKey();
        }
    }

    /**
     * Transfer the rest of the files in file list
     * @since 1.0.3
     */
    public function transferFiles($remainingtime)
    {
        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();

        // Determine max size for file uploading
        $max_file_size = min($this->job->to_upload_max_filesize, $this->job->from_upload_max_filesize, $this->job->to_max_post_size, $this->job->from_max_post_size) - 500;
        $max_transfer_chunk_size = 5 * 1024 * 1024;    // 5 mb
        if ($max_file_size > $max_transfer_chunk_size) {
            $max_file_size = $max_transfer_chunk_size;
        }

        $max_file_uploads = $this->job->to_max_file_uploads;

        // Get data on files for transfer
        $filesync = $this->sync_list->getFilesToMoveToTarget($max_file_size, $max_file_uploads);

        if (count($filesync) == 0) {
            // All done
            return $result;
        }

        // Determine if we need to pull the files or push them
        if ($this->installation->type == 'push') {
            $transfer_result = $this->pushFiles($filesync, $max_file_size);
            $result->errors = array_merge($result->errors, $transfer_result->errors);
            $result->warnings = array_merge($result->warnings, $transfer_result->warnings);
        } else if ($this->installation->type == 'pull') {
            $transfer_result = $this->pullFiles($filesync);
            $result->errors = array_merge($result->errors, $transfer_result->errors);
            $result->warnings = array_merge($result->warnings, $transfer_result->warnings);
        }

        return $result;
    }

    /**
     *  Push files to target
     *  @since 1.0.3
     */
    public function pushFiles(&$filesync, $max_file_size)
    {

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();

        $boundary = uniqid();
        $delimiter = '-------------' . $boundary . "-" . $boundary;
        $postrequest = $common->buildRequest($delimiter, $filesync, $max_file_size, $this->job->id);      
        $this->logger->log("DEBUG", "Created multipart request with size: " . strlen($postrequest) . " and allowed max size: " . $max_file_size);

        $transfer_result = $this->sendMultipartToTarget($filesync, $postrequest, $delimiter);
        $result->errors = array_merge($result->errors, $transfer_result->errors);
        $result->warnings = array_merge($result->warnings, $transfer_result->warnings);


        return $result;
    }

    /**
     *  Pull files to target
     *  @since 1.0.3
     */
    public function pullFiles(&$filesync)
    {
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();

        $body_request = new \stdClass();
        $body_request->files = $filesync;
        $body_request->job_id = $this->job->id;

        $json_encoded_body = json_encode($body_request);

        $args = array(
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 1,
            'sslverify' => $this->installation->verify_ssl,
            'body' => $json_encoded_body,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        $this->logger->log("DEBUG", "Calling remote service 'getfiles' with request body length: " . strlen($json_encoded_body));

        $response_arr = $this->remote_post_obj->remotePOST($this->getfiles_source_url, $args);

        $responsecode = wp_remote_retrieve_response_code($response_arr);
        if (is_wp_error($response_arr)) {
            $result->errors[] = $response_arr->get_error_message();
            $this->logger->log("ERROR", "Remote service 'getfiles' failed with WP error: " . $response_arr->get_error_message());
        } else if ($responsecode != 200) {
            $result->errors[] = sprintf(__("Got a error response from remote REST service while trying to fetch files. HTTP error: %d", "wpsynchro"), $responsecode);
            $this->logger->log("ERROR", "Got a non-200 response from 'getfiles' - error code: " . $responsecode, $response_arr);
            return $result;
        } else {
            // Multipart response is expected 
            $headers = wp_remote_retrieve_headers($response_arr);
            $contenttype = $headers['content-type'];
            preg_match('/boundary=(.*)$/', $contenttype, $matches);
            $boundary = $matches[1];

            $multipart = $common->cleanRemoteJSONData(wp_remote_retrieve_body($response_arr));

            $transfer_result = $this->sendMultipartToTarget($filesync, $multipart, $boundary);
            $result->errors = array_merge($result->errors, $transfer_result->errors);
            $result->warnings = array_merge($result->warnings, $transfer_result->warnings);

            $this->logger->log("DEBUG", "Got a proper response from 'getfiles' ");
        }

        return $result;
    }

    /**
     *  Pull files to target
     *  @since 1.0.3
     */
    public function sendMultipartToTarget(&$filesync, &$postrequest, $delimiter)
    {

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->filetransfer_target_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postrequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: multipart/form-data; boundary=" . $delimiter,
            "Content-Length: " . strlen($postrequest)
        ));

        $curl_result = curl_exec($ch);

        if (is_string($curl_result)) {
            $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($response_code == 200) {
                // Clean up and decode json
                $curl_result = $common->cleanRemoteJSONData($curl_result);
                $result_from_remote_service = json_decode($curl_result);

                foreach ($result_from_remote_service as $key => $single_result) {
                    if ($single_result->success == true) {
                        if (isset($single_result->partial)) {
                            // Handle partial 
                            $this->sync_list->setFileKeyToCompleted($single_result->section, $key, true, $single_result->partial_position);
                        } else {
                            $this->sync_list->setFileKeyToCompleted($single_result->section, $key);
                        }
                    } else {
                        $result->errors[] = $single_result->error;
                        $this->logger->log("ERROR", "Files: Error in file transfer from remote REST service - Error message:" . $single_result->error);
                    }
                }
            } else {
                $result->errors[] = sprintf(__("Failed to transfer: %s - Remote service whispered: %d", "wpsynchro"), $filesync->file['source_file'], $response_code);
                $this->logger->log("CRITICAL", sprintf("Failed to transfer: %s - Remote service whispered: %d", $filesync->file['source_file'], $response_code));
            }
        } else {
            $result->errors[] = sprintf(__("Failed to transfer: %s because of error: %s", "wpsynchro"), $filesync->file['source_file'], curl_error($ch));
            $this->logger->log("CRITICAL", sprintf("Failed to transfer: %s - because of error: %s", $filesync->file['source_file'], curl_error($ch)));
        }
        curl_close($ch);

        return $result;
    }
}

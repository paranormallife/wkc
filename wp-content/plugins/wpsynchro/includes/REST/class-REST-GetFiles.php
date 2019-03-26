<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "getfiles" - Pulling files from remote
 * @since 1.0.3
 */
class GetFiles
{

    public function service(\WP_REST_Request $request)
    {

        // Get data from request
        $body = $request->get_json_params();

        if (!isset($body['files']) || !isset($body['job_id'])) {
          return new \WP_REST_Response(null, 200);
        }
        
        // Create multipart response
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        
        $boundary = uniqid();
        $delimiter = '-------------' . $boundary . "-" . $boundary;
        $multipart_response = $common->buildRequest($delimiter, $body['files'], (1024 * 1024),$body['job_id']);

        header("Content-Type: multipart/form-data; boundary=" . $delimiter);
        
        echo $multipart_response;

        die();

        
    }
}

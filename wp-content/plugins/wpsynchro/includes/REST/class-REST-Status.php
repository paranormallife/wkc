<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "status"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class RESTStatus
{

    public function service($request)
    {
        // Extract parameters
        $parameters = $request->get_json_params();
        if (isset($parameters['instid'])) {
            $instid = $parameters['instid'];
        } else {
            $instid = '';
        }
        if (isset($parameters['jobid'])) {
            $jobid = $parameters['jobid'];
        } else {
            $jobid = '';
        }

        global $wpsynchro_container;
        $synchronize = $wpsynchro_container->get('class.SynchronizeController');
        $synchronize->setup($instid, $jobid);
        $sync_response = $synchronize->getSynchronizationStatus();

        if (isset($sync_response->errors) && count($sync_response->errors) > 0) {
            $sync_response->should_continue = false;
        } else {
            $sync_response->should_continue = true;
        }

        return new \WP_REST_Response($sync_response, 200);
    }
}

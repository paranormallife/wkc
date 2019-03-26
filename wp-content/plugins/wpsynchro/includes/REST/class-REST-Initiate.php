<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "initate" - Starting a synchronization
 * @since 1.0.0
 */
class RESTInitiate
{

    public function service($request)
    {

        $sync_response = new \stdClass();
        $sync_response->errors = array();
        $token_lifespan = 900;

        // Create a new transfer object
        $transfer = new \stdClass();
        $transfer->token = sha1(openssl_random_pseudo_bytes(30));
        $transfer->last_activity = time();
        $transfer->lifetime = $token_lifespan;
        update_option('wpsynchro_current_transfer', $transfer, false);
        $sync_response->token = $transfer->token;

        if (\WPSynchro\WPSynchro::isPremiumVersion()) {
            global $wpsynchro_container;
            $licensing = $wpsynchro_container->get("class.Licensing");
            $licensecheck = $licensing->verifyLicense();

            if ($licensecheck == false) {                
                $sync_response->errors[] = $licensing->getLicenseErrorMessage();
            }
        }

        if (isset($sync_response->errors) && count($sync_response->errors) > 0) {
            return new \WP_REST_Response($sync_response, 500);
        } else {
            return new \WP_REST_Response($sync_response, 200);
        }
    }
}

<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "downloadlog"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class DownloadLog
{

    public function service($request)
    {

        if (!isset($request['job_id']) || strlen($request['job_id']) == 0) {
            $result = new \StdClass();
            return new WP_REST_Response($result, 400);
        }
        $jobid = $request['job_id'];

        if (!isset($request['inst_id']) || strlen($request['inst_id']) == 0) {
            $result = new \StdClass();
            return new WP_REST_Response($result, 400);
        }
        $instid = $request['inst_id'];

        global $wpsynchro_container;
        $common = $wpsynchro_container->get('class.CommonFunctions');
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        
        $logpath = $common->getLogLocation();
        $filename = $common->getLogFilename($jobid);

        if (file_exists($logpath . $filename)) {
            $logcontents = file_get_contents($logpath . $filename);
            $job_obj = get_option("wpsynchro_" . $instid . "_" . $jobid, "");
            $inst_obj = $inst_factory->retrieveInstallation($instid);
            
            $logcontents .= PHP_EOL . "Installation object:" . PHP_EOL;
            $logcontents .= print_r($inst_obj, true);
            
            $logcontents .= PHP_EOL . "Job object:" . PHP_EOL;
            $logcontents .= print_r($job_obj, true);

            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: public");
            header("Content-Description: File Transfer");
            header("Content-Type: text/plain");
            header("Content-Disposition: attachment; filename=" . $filename);
            header("Content-Length: " . strlen($logcontents));

            echo $logcontents;

            exit();
        } else {
            return new \WP_REST_Response("", 400);
        }
    }
}

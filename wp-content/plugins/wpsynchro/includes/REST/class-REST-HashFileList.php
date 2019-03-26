<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "hashfilelist" - Returns file hashes
 * Call should already be verified by permissions callback
 * @since 1.0.3
 */
class RESTHashFileList
{

    public function service($request)
    {

        $result = new \stdClass();

        // Extract parameters
        $body = $request->get_json_params();
        $section = $body['section'];
        $allotted_time = $body['allotted_time'] * 0.8;
        $starttime = microtime(true);

        // Get time limit in seconds (float)
        $maxexecutiontime = intval(ini_get('max_execution_time'));
        if ($maxexecutiontime == 0 || $maxexecutiontime > 30) {
            // Set max time to 30, just to avoid other stuff cutting it off
            $maxexecutiontime = 30;
        }
        $maxexecutiontime -= 3; // Just for safety
        // Get the smallest, either max execution time on this site or the allotted time by calling php process
        $maxexecutiontime = min($maxexecutiontime, $allotted_time);

        // Hash files for comparing
        $hash_counter = 1;
        foreach ($section['file_list'] as &$file) {

            if ($file['hash'] != null) {
                continue;
            }
            if ($file['size'] == 0) {
                $file['hash'] = 'd41d8cd98f00b204e9800998ecf8427e';
            } else {
                $file['hash'] = md5_file(utf8_decode($file['source_file']));
                $hash_counter++;
            }

            if ($hash_counter % 5 == 0) {
                // Check the time 
                if ((microtime(true) - $starttime) > $maxexecutiontime) {
                    break;
                }
            }
        }


        $result->file_list = $section['file_list'];
        return new \WP_REST_Response($result, 200);
    }
}

<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "FileTargetSync" - Create temp dirs, copy existing files, hash them and report back
 * Call should already be verified by permissions callback
 * @since 1.0.3
 */
class FileTargetSync
{

    public $starttime = 0;
    public $max_execution_time = 0;
    public $transfer = null;
    public $work_chunk = null;

    public function service($request)
    {
        $this->starttime = microtime(true);

        $result = new \stdClass();
        $result->finalize_replacements = array();

        // Extract parameters
        $body = $request->get_json_params();
        $this->work_chunk = $body['work'];

        if (isset($body['allotted_time'])) {
            $allotted_time = $body['allotted_time'];
        } else {
            $allotted_time = 10;
        }

        // Get time limit in seconds (float)
        $this->max_execution_time = intval(ini_get('max_execution_time'));
        if ($this->max_execution_time == 0 || $this->max_execution_time > 30) {
            // Set max time to 30, just to avoid other stuff cutting it off
            $this->max_execution_time = 30;
        }
        // Make sure we choose the lowest value of max_execution_time and the allotted_time by calling php process
        $this->max_execution_time = min($allotted_time, $this->max_execution_time);
        $this->max_execution_time -= 1;

        // Handle temp dirs, if needed
        $tmp_creation_completed = $this->createTempDirs();
        if (!$tmp_creation_completed) {
            $result->work = $this->work_chunk;
            return new \WP_REST_Response($result, 200);
        }

        // Handle the files
        foreach ($this->work_chunk['files'] as &$file) {
            $filename = utf8_decode($file['target_file']);
            $filename_tmp_location = utf8_decode($file['target_tmp_file']);

            if (file_exists($filename)) {
                // File exist, good good, lets check if it identical with the one from source
                $target_hash = md5_file($filename);

                if ($file['hash'] == $target_hash) {
                    // they are identical, so just copy it to tmp location
                    $dirname = dirname($filename_tmp_location);
                    @wp_mkdir_p($dirname);
                    copy($filename, $filename_tmp_location);
                    $file["completed"] = true;
                } else {
                    // They differ, so it needs to be copied                    
                    $file["completed"] = false;
                }
            } else {
                // File doesnt exist, so it needs to be moved             
                $file["completed"] = false;
            }

            $file["target_synced"] = true;

            // Check if we need to break out and return for more coffee
            if ((microtime(true) - $this->starttime) >= $this->max_execution_time) {
                break;
            }
        }

        $result->work = $this->work_chunk;
        return new \WP_REST_Response($result, 200);
    }

    public function createTempDirs()
    {
        if (isset($this->work_chunk['files_temp_dirs_created']) && !$this->work_chunk['files_temp_dirs_created']) {

            global $wpsynchro_container;
            $common = $wpsynchro_container->get("class.CommonFunctions");

            foreach ($this->work_chunk['temp_dirs'] as $tempdir) {
                if (file_exists($tempdir)) {
                    // Delete it
                    $deleteresult = $common->removeDirectory($tempdir, $this->starttime, $this->max_execution_time);
                    if ($deleteresult === false) {
                        return false;
                    }
                }
                @mkdir($tempdir);
            }
            $this->work_chunk['files_temp_dirs_created'] = true;
            return true;
        } else {
            return true;
        }
    }
}

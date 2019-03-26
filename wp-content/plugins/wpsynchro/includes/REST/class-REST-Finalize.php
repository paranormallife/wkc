<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "finalize" - Only used by files finalize at the moment (db uses ClientSyncDatabase)
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class Finalize
{

    public $max_execution_time = 0;
    public $remaining_time_for_delete = 0;
    public $starttime = 0;

    public function service($request)
    {
        $this->starttime = microtime(true);
        $result = new \stdClass();
        $result->success = false;

        // Extract parameters
        $body = $request->get_json_params();
        $renames = $body['renames'];
        $rollback_sequence = $body['rollback_sequence'];
        $delete = $body['delete'];
        $allotted_time = $body['allotted_time'];

        // Get time limit in seconds (float)
        $this->max_execution_time = intval(ini_get('max_execution_time'));
        // Make sure we choose the lowest value of max_execution_time and the allotted_time by calling php process
        $this->max_execution_time = min($allotted_time, $this->max_execution_time);
        $this->max_execution_time -= 1;

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $rollback_needed = false;
        $error_log = array();
        $debug_log = array();
        $warning_log = array();

        $debug_log[] = "Start finalize on target REST service with max execution time: " . $this->max_execution_time;

        // Do renames (and generate rollback sequence)
        foreach ($renames as $key => &$rename) {
            $rename['to'] = $common->fixPath(utf8_decode($rename['to']));
            $rename['temp_to'] = $common->fixPath($rename['temp_to']);
            $rename['from'] = $common->fixPath(utf8_decode($rename['from']));

            // If file/dir already exists, rename it to temporary location
            if (file_exists($rename['to'])) {
                $rename_success = @rename($rename['to'], $rename['temp_to']);
                if ($rename_success === true) {
                    $debug_log[] = "Finalize: Success with rename to temp location: " . $rename['to'] . " to: " . $rename['temp_to'];
                    $rollback_sequence[$rename['temp_to']] = $rename['to'];
                } else {
                    $debug_log[] = "Finalize: Rename failed for existing file/dir to temporary location - Tried from " . $rename['to'] . " to: " . $rename['temp_to'];
                    $warning_log[] = sprintf(__("Finalize: Rename failed for existing %s to temporary location %s - Normally this is a permission problem or another lock on the filesystem. You need to do the rename manually from %s to %s to complete the migration.", "wpsynchro"), $rename['to'], $rename['temp_to'], $rename['from'], $rename['to']);
                    continue;
                }
            }

            // Do the rename of new folder to the correct folder        
            $rename_success = @rename($rename['from'], $rename['to']);
            if ($rename_success === true) {
                $debug_log[] = "Finalize: Success with rename: " . $rename['from'] . " to: " . $rename['to'];
                unset($renames[$key]);
                $rollback_sequence[$rename['to']] = $rename['from'];
                $delete[] = utf8_encode($rename['from']);
            } else {
                $debug_log[] = "Finalize: Rename failed - Tried from " . $rename['from'] . " to: " . $rename['to'];
                $warning_log[] = sprintf(__("Finalize: Could not rename %s to %s - Normally this is a permission problem or another lock on the filesystem. Rename the directory manually to complete the migration.", "wpsynchro"), $rename['from'], $rename['to']);
                continue;
            }
        }

        // All seems well, so remove the old dirs/files
        foreach ($delete as $key => $deletepath) {
            if (!file_exists($deletepath)) {
                $debug_log[] = "Finalize remote REST service: Could not find file/dir that is on delete array, so ignoring the file: " . $deletepath;
                unset($delete[$key]);
                continue;
            }

            $deleted = false;
            if (is_file($deletepath)) {
                unlink($deletepath);
                $deleted = true;
            } else {
                $delete_result = $common->removeDirectory($deletepath, $this->starttime, $this->max_execution_time);
                $debug_log[] = "Finalize remote REST service: Starting deleting: " . $deletepath;
                if ($delete_result === false) {
                    // Delete did not complete within timeframe
                    $debug_log[] = "Finalize remote REST service: Could not complete delete within max time for: " . $deletepath;
                } else {
                    $deleted = true;
                }
            }
            if ($deleted) {
                $debug_log[] = "Finalize remote REST service: Deleted " . $deletepath;
                unset($delete[$key]);
            }
        }

        // When all is deleted, we have completed
        $debug_log[] = "Finalize remote REST service file/dir deleted completed";
        $result->success = true;


        if (count($delete) == 0) {
            // Not more deletes, so we are completed
            $result->completed = true;
        } else {
            $result->completed = false;
        }

        $result->error_log = $error_log;
        $result->warning_log = $warning_log;
        $result->debug_log = $debug_log;
        $result->delete = $delete;

        return new \WP_REST_Response($result, 200);
    }
}

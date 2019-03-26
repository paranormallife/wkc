<?php
namespace WPSynchro\Finalize;

/**
 * Class for handling the finalization of the sync
 *
 * @since 1.0.0
 */
class FinalizeSync
{

    // Base data
    private $job = null;
    private $installation = null;
    private $allotted_time_for_subjob = 10;

    /**
     *  Run finalize method
     *  @since 1.0.0
     */
    public function runFinalize(&$installation, &$job, $allotted_time_for_subjob)
    {
        $this->allotted_time_for_subjob = $allotted_time_for_subjob;
        $this->installation = &$installation;
        $this->job = &$job;

        // Init logging
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("INFO", "Starting finalize - Allotted time: " . $allotted_time_for_subjob);
        $starttime = microtime(true);

        $completed = true;

        $finalize_progress = 10;

        if (count($this->job->errors) == 0 && $this->installation->sync_files) {
            $result_files = $this->finalizefiles();
            $completed = $result_files->completed;
            if ($result_files->success) {
                $finalize_progress += 45;
            } else {
                if (count($result_files->errors) > 0) {
                    $this->job->errors = array_merge($this->job->errors, $result_files->errors);
                } else {
                    $this->job->errors[] = __("Finalizing files failed for unknown reason", "wpsynchro");
                }
            }
            $this->job->save();
        }

        if (count($this->job->errors) == 0 && $this->installation->sync_database) {
            $result_db = $this->finalizeDB();
            if ($result_db->success) {
                $finalize_progress += 45;
            } else {
                if (count($result_db->errors) > 0) {
                    $this->job->errors = array_merge($this->job->errors, $result_db->errors);
                } else {
                    $this->job->errors[] = __("Finalize DB failed for unknown reason", "wpsynchro");
                }         
            }
            $this->job->save();
        }

        $logger->log("INFO", "Completed finalize - used time: " . (microtime(true) - $starttime));

        $this->job->finalize_progress = $finalize_progress;

        if ($completed) {
            // Update progress
            $this->job->finalize_progress = 100;
            $this->job->finalize_completed = true;

            // Update option with counted success times
            $success_count = get_site_option("wpsynchro_success_count", 0);
            $success_count++;
            update_site_option("wpsynchro_success_count", $success_count);
        }
    }

    /**
     *  Finalize Database stuff
     *  @since 1.0.0
     */
    private function finalizeDB()
    {

        global $wpsynchro_container;
        $databasefinalize = $wpsynchro_container->get('class.DatabaseFinalize');
        $finalize_db = $databasefinalize->finalize($this->installation, $this->job, $this->allotted_time_for_subjob);
        return $finalize_db;
    }

    /**
     *  Finalize files
     *  @since 1.0.3
     */
    private function finalizefiles()
    {

        $starttime = microtime(true);

        global $wpsynchro_container;

        $sync_list = $wpsynchro_container->get("class.SyncList");
        $sync_list->init($this->installation, $this->job);

        $finalize_files_handler = $wpsynchro_container->get("class.FinalizeFiles");
        $finalize_files_handler->init($sync_list, $this->installation, $this->job);

        $allotted_time = $this->allotted_time_for_subjob - (microtime(true) - $starttime);
        return $finalize_files_handler->finalizeFiles($allotted_time);
    }

    /**
     *  Calculate completion percent
     *  @since 1.0.0
     */
    private function updateCompletionStatusPercent()
    {
        
    }
}

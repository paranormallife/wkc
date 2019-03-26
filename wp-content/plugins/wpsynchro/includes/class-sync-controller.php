<?php
namespace WPSynchro;

/**
 * Class for controlling the synchronization flow (main controller)
 * Called from REST service, for both the worker thread and the status thread
 *
 * @since 1.0.0
 */
class SynchronizeController
{

    // General data
    public $installation_id = 0;
    public $job_id = 0;
    public $php_maxexecutiontime = 30;
    public $maxexecutiontime = 5;
    public $starttime = 0;
    // Objects
    public $job = null;
    public $installation = null;
    // Errors and warnings
    public $errors = array();
    public $warnings = array();
    public $logger = null;

    /**
     * Setup the data needed for synchronization, needed for both worker and status thread
     * @since 1.0.0
     */
    public function setup($installation_id, $job_id)
    {

        // Set start time
        $this->starttime = microtime(true);

        global $wpsynchro_container;

        $this->installation_id = $installation_id;
        $this->job_id = $job_id;

        // Common
        $common = $wpsynchro_container->get("class.CommonFunctions");
        
        // Init logging    
        $this->logger = $wpsynchro_container->get("class.Logger");
        $this->logger->setFileName($common->getLogFilename($this->job_id));
        
        // Get job data        
        $this->job = $wpsynchro_container->get('class.Job');
        $this->job->load($this->installation_id, $this->job_id);

        // Get installation
        $installationfactory = $wpsynchro_container->get('class.InstallationFactory');
        $this->installation = $installationfactory->retrieveInstallation($this->installation_id);

        // Get time limit in seconds (float)
        $this->php_maxexecutiontime = intval(ini_get('max_execution_time'));
        if ($this->php_maxexecutiontime == 0 || $this->php_maxexecutiontime > 60) {
            // Set max time to 180, just to avoid other stuff cutting it off
            $this->maxexecutiontime = 60;
        } else {
            $this->maxexecutiontime = $this->php_maxexecutiontime;
        }
        $this->maxexecutiontime = $this->maxexecutiontime * 0.8; // Take 20% off to make sure we dont hit the limit   
    }

    /**
     * Run synchronization
     * @since 1.0.0
     */
    public function runSynchronization()
    {
        $result = new \stdClass();

        if ($this->job == null) {
            return null;
        }

        // Handle job locking
        if (isset($this->job->run_lock) && $this->job->run_lock === true) {
            // Ohhh noes, already running
            $errormsg = __('Job is already running or error has happened - Check PHP error logs', 'wpsynchro');
            $result->errors[] = $errormsg;
            $this->logger->log("CRITICAL", $errormsg);
            return $result;
        }

        // Set lock in job
        $this->job->run_lock = true;
        $this->job->run_lock_timer = time();
        $this->job->run_lock_problem_time = time() + ceil($this->php_maxexecutiontime * 1.5); // Status thread will check if this time has passed (aka the synchronization thread has stopped
        $this->job->save();

        // Start jobs
        $lastrun_time = 0;
        while (( microtime(true) - $this->starttime ) < ( $this->maxexecutiontime - $lastrun_time )) {
            $startwhile = microtime(true);

            $allotted_time_for_subjob = (($this->starttime + $this->maxexecutiontime) - microtime(true));
            $this->logger->log("DEBUG", "Running sync controller loop - With subjob allotted time: " . $allotted_time_for_subjob . " seconds");


            if (!$this->job->metadata_initiation_completed) {
                $this->handleInitiationStep($allotted_time_for_subjob);
                break;
            } else if (!$this->job->metadata_completed) {
                // Metadata              
                $this->handleStepMetadata($allotted_time_for_subjob);
                break;
            } else if (!$this->job->database_completed) {
                // Database 
                if ($this->installation->sync_database) {
                    $this->handleStepDatabase($allotted_time_for_subjob);
                } else {
                    $this->job->database_progress = 100;
                    $this->job->database_completed = true;
                }
                break;
            } else if (!$this->job->files_completed) {
                // Files     
                if ($this->installation->sync_files) {
                    $this->handleStepFiles($allotted_time_for_subjob);
                } else {
                    $this->job->files_progress = 100;
                    $this->job->files_completed = true;
                }
                break;
            } else if (!$this->job->finalize_completed) {
                // Finalize         
                $this->handleStepFinalize($allotted_time_for_subjob);
                break;
            } else {
                break;
            }

            $stopwhile = microtime(true);
            $lastrun_time = $stopwhile - $startwhile;
        }

        // Add errors and warnings to job
        $this->job->errors = array_merge($this->job->errors, $this->errors);
        $this->job->warnings = array_merge($this->job->warnings, $this->warnings);

        // Set post run data
        $this->updateCompletedState();
        $this->job->run_lock = false;
        $result->is_completed = $this->job->is_completed;

        // Add errors to return, so we can return a http 500 if somethings is wrong, so block further requests
        $result->errors = $this->job->errors;
        // Add warnings to return
        $result->warnings = $this->job->warnings;

        // save job status before returning  
        $this->job->save();

        return $result;
    }

    /**
     * Handle initiation step
     * @since 1.0.0
     */
    private function handleInitiationStep($allotted_time_for_subjob)
    {
        global $wpsynchro_container;

        // Start metadatalog
        $metadatalog = $wpsynchro_container->get('class.MetadataLog');
        $metadatalog->startSynchronization($this->job_id, $this->installation_id, $this->installation->getOverviewDescription());

        // Get masterdatasync class to initiate sync
        $masterdata = $wpsynchro_container->get('class.MasterdataSync');
        $starttime = microtime(true);
        $this->logger->log("INFO", "Initating synchronization with remote host with allotted time:" . $allotted_time_for_subjob);

        $initiation_response = $masterdata->initiateSynchronization($this->installation, $allotted_time_for_subjob);

        if (isset($initiation_response->errors) && count($initiation_response->errors) > 0) {
            $this->errors = array_merge($initiation_response->errors, $this->errors);
            return;
        }

        // Set token in job
        if (strlen($initiation_response->token) > 20) {
            $this->job->remote_token = $initiation_response->token;
        } else {
            $errormsg = __("Failed initializing - Could not get a valid token from remote server", "wpsynchro");
            $this->logger->log("CRITICAL", $errormsg);
            $this->errors[] = $errormsg;
        }

        $endtime = microtime(true);
        $this->logger->log("INFO", "Initating synchronization completed on: " . ($endtime - $starttime) . " seconds");

        $this->job->metadata_progress = 25;
        $this->job->metadata_initiation_completed = true;
    }

    /**
     * Handle metadata step
     * @since 1.0.0
     */
    private function handleStepMetadata($allotted_time_for_subjob)
    {

        global $wpsynchro_container;
        $masterdata = $wpsynchro_container->get('class.MasterdataSync');
        $starttime = microtime(true);
        $this->logger->log("INFO", "Getting masterdata from source and target with allotted time:" . $allotted_time_for_subjob);

        $masterdata_result = $masterdata->runMasterdataStep($this->installation, $this->job, $allotted_time_for_subjob);

        if ($masterdata_result->success === true) {
            $this->job->metadata_completed = true;
            $this->logger->log("INFO", "Completed masterdata on: " . (microtime(true) - $starttime) . " seconds");
        } else {
            if (isset($masterdata_result->errors) && count($masterdata_result->errors) > 0) {
                $this->errors = array_merge($this->errors, $masterdata_result->errors);
                $this->logger->log("CRITICAL", "Masterdata retrieval failed with errors:");
                foreach ($masterdata_result->errors as $errormsg) {
                    $this->logger->log("ERROR", $errormsg);
                }
            } else {
                $errormsg = __("Could not process masterdata step - Unknown error - Contact support", "wpsynchro");
                $this->logger->log("CRITICAL", $errormsg);
                $this->errors[] = $errormsg;
            }
            $this->job->metadata_completed = false;
        }
    }

    /**
     * Handle database step
     * @since 1.0.0
     */
    private function handleStepDatabase($allotted_time_for_subjob)
    {

        global $wpsynchro_container;
        $databasesync = $wpsynchro_container->get('class.DatabaseSync');
        $databaseresult = $databasesync->runDatabaseSync($this->installation, $this->job, $allotted_time_for_subjob);
    }

    /**
     * Handle files step
     * @since 1.0.0
     */
    private function handleStepFiles($allotted_time_for_subjob)
    {

        global $wpsynchro_container;
        $filessync = $wpsynchro_container->get('class.FilesSync');
        $filessync->runFilesSync($this->installation, $this->job, $allotted_time_for_subjob);
    }

    /**
     * Handle finalize step
     * @since 1.0.0
     */
    private function handleStepFinalize($allotted_time_for_subjob)
    {

        global $wpsynchro_container;
        $finalizesync = $wpsynchro_container->get('class.FinalizeSync');
        $finalizesync->runFinalize($this->installation, $this->job, $allotted_time_for_subjob);
    }

    /**
     * Get synchronization status
     * @since 1.0.0
     */
    public function getSynchronizationStatus()
    {
        if ($this->job == null) {
            return null;
        }

        $result = new \stdClass();
        $result->metadata_progress = $this->job->metadata_progress;
        $result->database_progress = $this->job->database_progress;
        $result->database_progress_description = $this->job->database_progress_description;
        $result->files_progress = $this->job->files_progress;
        $result->files_progress_description = $this->job->files_progress_description;
        $result->finalize_progress = $this->job->finalize_progress;
        $result->is_completed = $this->job->is_completed;


        if ($this->job->run_lock_problem_time < time()) {
            $this->job->errors[] = __("The synchronization process seem to have problems - It may be PHP errors - please check the PHP logs", "wpsynchro");
        }

        $result->errors = $this->job->errors;
        $result->warnings = $this->job->warnings;

        return $result;
    }

    /**
     * Updated completed status
     * @since 1.0.0
     */
    private function updateCompletedState()
    {
        if ($this->job->metadata_completed) {
            $this->job->metadata_progress = 100;
        }
        if ($this->job->database_completed) {
            $this->job->database_progress = 100;
        }
        if ($this->job->files_completed) {
            $this->job->files_progress = 100;
        }
        if ($this->job->finalize_completed) {
            $this->job->finalize_progress = 100;
        }
        if ($this->job->metadata_completed && $this->job->database_completed && $this->job->files_completed && $this->job->finalize_completed) {
            $this->job->is_completed = true;

            global $wpsynchro_container;

            // Start metadatalog
            $metadatalog = $wpsynchro_container->get('class.MetadataLog');
            $metadatalog->stopSynchronization($this->job_id, $this->installation_id);
        }
    }
}

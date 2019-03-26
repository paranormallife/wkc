<?php
namespace WPSynchro\Files;

/**
 * Class for handling files synchronization
 * @since 1.0.3
 */
class FilesSync
{

    // Data objects
    public $job = null;
    public $installation = null;
    public $allotted_time_for_subjob = null;
    public $logger = null;
    public $starttime = 0;
    // Specific task classes
    public $sync_list = null;
    public $populate_list_handler = null;
    public $hash_list_handler = null;
    public $path_handler = null;
    public $file_target_sync = null;
    public $transfer_files_handler = null;
    public $remote_post_obj = null;

    // Files data

    /**
     *  Constructor
     */
    public function __construct(\WPSynchro\RemotePOST $remote_post_obj)
    {
        $this->remote_post_obj = $remote_post_obj;
    }

    /**
     * Initialize needed objects
     * @since 1.0.3
     */
    public function init()
    {

        global $wpsynchro_container;

        // Logger
        $this->logger = $wpsynchro_container->get("class.Logger");

        // File sync list object
        $this->sync_list = $wpsynchro_container->get("class.SyncList");
        $this->sync_list->init($this->installation, $this->job);

        // File populate list handler
        $this->populate_list_handler = $wpsynchro_container->get("class.PopulateListHandler");
        $this->populate_list_handler->init($this->sync_list, $this->installation, $this->job);

        // File hash list handler
        $this->hash_list_handler = $wpsynchro_container->get("class.HashListHandler");
        $this->hash_list_handler->init($this->sync_list, $this->installation, $this->job);

        // Path handler object
        $this->path_handler = $wpsynchro_container->get("class.PathHandler");
        $this->path_handler->init($this->sync_list);

        // Target sync object
        $this->file_target_sync = $wpsynchro_container->get("class.TargetSync");
        $this->file_target_sync->init($this->sync_list, $this->installation, $this->job);

        // TransferFiles object
        $this->transfer_files_handler = $wpsynchro_container->get("class.TransferFiles");
        $this->transfer_files_handler->init($this->sync_list, $this->installation, $this->job);
    }

    /**
     * Handle files step
     * @since 1.0.3
     */
    public function runFilesSync(&$installation, &$job, $allotted_time_for_subjob)
    {
        $this->installation = $installation;
        $this->job = $job;
        $this->allotted_time_for_subjob = $allotted_time_for_subjob;
        $this->starttime = microtime(true);

        // Init 
        $this->init();

        // Prepare result for method
        $result = new \stdClass();

        // Now, do some work
        $lastrun_time = 0;
        while (( microtime(true) - $this->starttime ) < ( $this->allotted_time_for_subjob - $lastrun_time )) {
            $remainingtime = $this->allotted_time_for_subjob - (microtime(true) - $this->starttime) * 0.8;
            $time_start = microtime(true);
            $this->logger->log("DEBUG", "Running files sync loop - With allotted time: " . $allotted_time_for_subjob . " remaining time: " . $remainingtime . " and last run time: " . $lastrun_time);

            // If there is errors, break out
            if (count($this->job->errors) > 0) {
                break;
            }

            // Start processing
            if (!$this->sync_list->all_sections_populated) {
                // Populated from source (list files and hash them)
                $this->setFilesProgressDescription(__("Gather file data from source", "wpsynchro"));
                $this->logger->log("INFO", "Files: Gather file data from source");
                $errorlist = $this->populate_list_handler->populateFilelist($remainingtime);
                $this->job->errors = array_merge($this->job->errors, $errorlist);   
            } else if (!$this->sync_list->all_sections_hashed) {
                // Hash file list on source                
                $this->setFilesProgressDescription(__("Hashing files on source", "wpsynchro") . " - " . $this->sync_list->getFileProgressDescriptionPart());
                $this->logger->log("INFO", "Files: Hashing files on source");
                $errorlist = $this->hash_list_handler->hashFilelist($remainingtime);
                $this->job->errors = array_merge($this->job->errors, $errorlist);          
            } else if (!$this->sync_list->all_sections_path_handled) {                
                // Calculate temp dirs, calculate paths and finalize renames                    
                $this->setFilesProgressDescription(__("Rewriting paths", "wpsynchro"));
                $this->logger->log("INFO", "Files: Path handling");
                $errorlist = $this->path_handler->processFilelist($remainingtime);
                $this->job->errors = array_merge($this->job->errors, $errorlist);
            } else if (!$this->sync_list->all_target_processed) {
                // Process file list on target site (on target: check the hashes, create temp dirs, copy equal files and report back)                
                $this->setFilesProgressDescription(__("Comparing files and copying files", "wpsynchro") . " - " . $this->sync_list->getFileProgressDescriptionPart());
                $this->logger->log("INFO", "Files: Doing target synchronization - " . $this->sync_list->getFileProgressDescriptionPart());
                $errorlist = $this->file_target_sync->processWorkChunkOnTarget($remainingtime);
                $this->job->errors = array_merge($this->job->errors, $errorlist);
            } else if (!$this->sync_list->all_completed) {
                // Proces the rest of files - Copy from source to target         
                $this->setFilesProgressDescription(__("Transferring files", "wpsynchro") . " - " . $this->sync_list->getFileProgressDescriptionPart());
                $this->logger->log("INFO", "Files: Transferring files - " . $this->sync_list->getFileProgressDescriptionPart());
                $transfer_result = $this->transfer_files_handler->transferFiles($remainingtime);
                $this->job->errors = array_merge($this->job->errors, $transfer_result->errors);
                $this->job->warnings = array_merge($this->job->warnings, $transfer_result->warnings);
            } else {
                // Nothing more to do
                break;
            }

            // Make sure we dont pass the max allotted time
            $time_stop = microtime(true);
            $lastrun_time = $time_stop - $time_start;

            // Calculate progress
            $this->calculateProgress();

            // Save status to DB       
            $this->job->save();
        }

        $this->sync_list->saveFileSyncList();

        if (count($this->job->errors) > 0) {
            $result->success = false;
        } else {
            $result->success = true;
        }

        return $result;
    }

    /**
     *  Set a new progress description and save if neeeded
     */
    public function setFilesProgressDescription($desc)
    {
        if ($this->job->files_progress_description != $desc) {
            $this->job->files_progress_description = $desc;
            $this->job->save();
        }
    }

    /**
     * Calculate progress
     * @since 1.0.3
     */
    public function calculateProgress()
    {
        $progress_number = 0;

        // Base number is by section, that score 1% for each populated and 2% hashing        
        foreach ($this->sync_list->sections as &$section) {
            if ($section->files_list_complete) {
                $progress_number++;
            }
            if ($section->files_hashing_complete) {
                $progress_number = $progress_number + 2;
            }
        }

        // When hashing is done, we have two major tasks, target sync first and move the rest of files after      
        $total_size = $this->sync_list->files_total_size;
        if ($total_size > 0) {
            $rest_progress = 100 - $progress_number;
            $size_completed = $this->sync_list->files_completed_size;
            if ($size_completed > 0) {
                $hash_percent_completed = $size_completed / $total_size;
                if ($hash_percent_completed == 1) {
                    $progress_number = 100;
                } else {
                    $progress_number += floor($rest_progress * $hash_percent_completed);
                }
            }
        }

        $this->job->files_progress = $progress_number;

        if ($this->job->files_progress >= 100) {
            $this->job->files_progress = 100;
            $this->job->files_completed = true;
        }
    }
}

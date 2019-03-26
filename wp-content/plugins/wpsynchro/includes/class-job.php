<?php
namespace WPSynchro;

/**
 * Class for handling an instance of a synchronization (aka. one pull or one push)
 *
 * @since 1.0.0
 */
class Job
{

    public $id = '';
    public $installation_id = null;
    // Run lock
    public $run_lock = false;
    public $run_lock_timer = 0;
    public $run_lock_problem_time = 0;
    // Errors and warnings
    public $errors = array();
    public $warnings = array();
    /*
     *  Progress
     */
    public $metadata_initiation_completed = false;
    public $metadata_completed = false;
    public $metadata_progress = 0;
    public $database_completed = false;
    public $database_progress = 0;
    public $database_progress_description = "";
    public $files_completed = false;
    public $files_progress = 0;
    public $files_progress_description = "";
    public $finalize_completed = false;
    public $finalize_progress = 0;
    public $is_completed = false;
    public $first_time_setup_done = false;
    /*
     *  Data from step: Masterdata
     */
    public $remote_token = "";
    // From
    public $from_dbmasterdata = null;
    public $from_client_home_url = null;
    public $from_rest_base_url = null;
    public $from_wp_options_table = null;
    public $from_max_allowed_packet_size = 0;
    public $from_max_post_size = 0;
    public $from_max_file_uploads = 20;
    public $from_upload_max_filesize = 0;
    public $from_memory_limit = 0;
    public $from_sql_version = "";
    public $from_plugin_version = "";
    public $from_files_above_webroot_dir = "";
    public $from_files_home_dir = "";
    public $from_files_wp_content_dir = "";
    public $from_files_wp_dir = "";
    public $from_files_plugin_list = array();
    public $from_files_theme_list = array();
    // to
    public $to_dbmasterdata = null;
    public $to_client_home_url = null;
    public $to_rest_base_url = null;
    public $to_wp_options_table = null;
    public $to_max_allowed_packet_size = 0;
    public $to_max_post_size = 0;
    public $to_max_file_uploads = 20;
    public $to_upload_max_filesize = 0;
    public $to_memory_limit = 0;
    public $to_sql_version = "";
    public $to_plugin_version = "";
    public $to_files_above_webroot_dir = "";
    public $to_files_home_dir = "";
    public $to_files_wp_content_dir = "";
    public $to_files_wp_dir = "";
    public $to_files_plugin_list = array();
    public $to_files_theme_list = array();
    // Data from step: Database
    public $db_first_run_setup = false;
    public $db_rows_per_sync = 500;
    public $db_rows_per_sync_default = 500;                 // 500 rows as default
    public $db_response_size_wanted_default = 500000;       // 500 kb as default
    public $db_response_size_wanted_max = 5000000;      // Can max scale to 5mb, to prevent all sorts of trouble with memory and other stuff
    public $db_throttle_table = "";
    public $db_last_response_length = 0;
    // Data from step: Files
    public $files_sync_list_disklocation = null;
    public $files_target_sync_throttle_maxsize = 1000000; // Default 1MB (its not actual data, but data to be handled on target)
    public $files_target_sync_throttle_maxsize_max = 100000000; // 100MB
    public $files_target_sync_throttle_max_file_count = 500;
    // Finalize data
    public $finalize_file_data_initialised = false;
    public $finalize_files_renames = array();
    public $finalize_files_deletes = array();
    public $finalize_files_rollback = array();
    
    /**
     *  Load data from DB 
     *  @since 1.0.0
     */
    public function load($installation_id, $job_id)
    {
        $this->id = $job_id;
        $this->installation_id = $installation_id;

        $job_option = get_option($this->getJobWPOptionName($installation_id, $job_id), false);
        if ($job_option !== false) {
            foreach ($job_option as $key => $value) {
                $this->$key = $value;
            }

            return true;
        }
        return false;
    }

    /**
     *  Save job to DB
     *  @since 1.0.0
     */
    public function save()
    {
        update_option($this->getJobWPOptionName($this->installation_id, $this->id), (array) $this, false);
    }

    /**
     * Return the WP option name used for job's
     * @since 1.0.0
     */
    public function getJobWPOptionName($installation_id, $job_id)
    {
        return 'wpsynchro_' . $installation_id . '_' . $job_id;
    }
}

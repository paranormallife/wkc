<?php
namespace WPSynchro\Database;

/**
 * Class for handling database finalize
 * @since 1.0.0
 */
class DatabaseFinalize
{

    // Data objects
    public $job = null;
    public $installation = null;
    public $databasesync = null;
    public $logger = null;

    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {
        
    }

    /**
     *  Calculate completion percent
     *  @since 1.0.0
     */
    public function finalize(&$installation, &$job, $allotted_time)
    {

        $this->installation = &$installation;
        $this->job = &$job;

        $result = new \stdClass();
        $result->success = true;
        $result->errors = array();
        $result->warnings = array();

        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $table_prefix = $commonfunctions->getDBTempTableName();
        $this->logger = $wpsynchro_container->get("class.Logger");
        $this->databasesync = $wpsynchro_container->get('class.DatabaseSync');
        $this->databasesync->job = $this->job;
        $this->databasesync->installation = $this->installation;

        $this->logger->log("INFO", "Starting database finalize with allotted time: " . $allotted_time);

        // Handle preserving data
        $sql_queries = array();

        // Preserving data in options table, if it is migrated
        if ($this->installation->include_all_database_tables || in_array($job->to_wp_options_table, $this->installation->only_include_database_table_names)) {
            if ($this->installation->db_preserve_wpsynchro) {
                $delete_from_sql = "delete from `" . $table_prefix . $job->to_wp_options_table . "`  where option_name like 'wpsynchro_%'";
                $insert_into_sql = "insert into `" . $table_prefix . $job->to_wp_options_table . "` (option_name,option_value,autoload) select option_name,option_value,autoload from " . $job->to_wp_options_table . " where option_name like 'wpsynchro_%'";

                $sql_queries[] = $delete_from_sql;
                $this->logger->log("DEBUG", "Add sql statement to delete WP Synchro options: " . $delete_from_sql);
                $sql_queries[] = $insert_into_sql;
                $this->logger->log("DEBUG", "Add sql statement to copy current WP Synchro options to temp table: " . $insert_into_sql);
            }
            if ($this->installation->db_preserve_activeplugins) {
                $delete_from_sql = "delete from `" . $table_prefix . $job->to_wp_options_table . "`  where option_name = 'active_plugins'";
                $insert_into_sql = "insert into `" . $table_prefix . $job->to_wp_options_table . "` (option_name,option_value,autoload) select option_name,option_value,autoload from " . $job->to_wp_options_table . " where option_name = 'active_plugins'";

                $sql_queries[] = $delete_from_sql;
                $this->logger->log("DEBUG", "Add sql statement to delete active plugin setting: " . $delete_from_sql);
                $sql_queries[] = $insert_into_sql;
                $this->logger->log("DEBUG", "Add sql statement to copy current active plugin setting to temp table: " . $insert_into_sql);
            }
        }

        // Retrieve new db tables list from destination
        global $wpsynchro_container;
        $masterdata_obj = $wpsynchro_container->get('class.MasterdataSync');
        $data_to_retrieve = array("dbdetails");
        $masterdata_obj->installation = $this->installation;
        $masterdata_obj->job = $this->job;
        $this->logger->log("DEBUG", "Retrieving new masterdata from target");
        $masterdata = $masterdata_obj->retrieveMasterdata('to', $data_to_retrieve, $allotted_time);

        if (!is_object($masterdata) || !isset($masterdata->response->tmptables_dbdetails)) {
            $result->success = false;
            $result->errors[] = __("Could not retrieve data from remote site for finalizing", "wpsynchro");
            $this->logger->log("CRITICAL", "Could not retrieve data from target site for finalizing");
            return $result;
        }
        $dbtables = $masterdata->response->tmptables_dbdetails;
        $this->logger->log("DEBUG", "Retrieving new masterdata completed");

        // Create lookup array
        $to_table_lookup = array();
        foreach ($dbtables as $to_table) {
            $to_table_lookup[$to_table->name] = $to_table->rows;
        }

        // Run finalize checks     
        foreach ($this->job->from_dbmasterdata as $from_table) {
            $from_rows = $from_table->rows;
            // If its old temp table or other, just ignore
            if (strpos($from_table->name, $table_prefix) > -1) {
                $this->logger->log("DEBUG", "Table " . $from_table->name . " is a old temp table, so ignore");
                continue;
            }

            // Check if table exists on "to", which it should
            if (!isset($to_table_lookup[$table_prefix . $from_table->name])) {
                // Not transferred - Error
                $this->logger->log("CRITICAL", "Table " . $from_table->name . " does not exist on target, but it should. It is not transferred.");
                $result->errors[] = sprintf(__("Finalize: Error in database synchronization for table %s - It is not transferred", "wpsynchro"), $from_table->name);
                break;
            }
            $to_rows = $to_table_lookup[$table_prefix . $from_table->name];

            $finalize_check = $this->checkRowCountCompare($from_table->name, $from_rows, $to_rows);
            if (count($finalize_check->errors) > 0 || count($finalize_check->errors) > 0) {

                $result->errors = array_merge($result->errors, $finalize_check->errors);
                $result->warnings = array_merge($result->warnings, $finalize_check->warnings);
                foreach ($finalize_check->warnings as $warning) {
                    $this->logger->log("WARNING", $warning);
                }
                foreach ($finalize_check->errors as $error) {
                    $this->logger->log("ERROR", $error);
                }
            }
        }

        // Get tables to be renamed
        foreach ($dbtables as $table) {
            if (strpos($table->name, $table_prefix) > -1) {
                $table_new = str_replace($table_prefix, '', $table->name);
                $this->logger->log("DEBUG", "Add drop table in database on " . $table_new);
                $sql_queries[] = 'drop table if exists `' . $table_new . '`';
                $this->logger->log("DEBUG", "Add rename in database from " . $table->name . " to: " . $table_new);
                $sql_queries[] = 'rename table `' . $table->name . '` to `' . $table_new . '`';
            }
        }

        $body = new \stdClass();
        $body->sql_inserts = $sql_queries;
        $body->type = 'finalize'; // For executing sql

        if (count($result->errors) == 0) {
            $this->logger->log("DEBUG", "Calling remote client db service with " . count($body->sql_inserts) . " SQL statements");
            $remoteserviceresult = $this->databasesync->callRemoteClientDBService($body, 'to');
            if (isset($remoteserviceresult->error)) {
                $result->success = false;
                $result->errors[] = $remoteserviceresult->error;
                $this->logger->log("CRITICAL", "Calling remote client db failed, with error: " . $remoteserviceresult->error);
                return $result;
            }
        } else {
            $this->logger->log("CRITICAL", "No need calling remote client db, because we have errors: ", $result->errors);
            $result->success = false;
            return $result;
        }


        return $result;
    }

    /**
     *  Function to help with finalizing database data and checks if rows are with reasonable limits
     *  @since 1.0.0
     */
    public function checkRowCountCompare($from_tablename, $from_rows, $to_rows)
    {

        $margin_for_warning_rows_equal = 5; // 5%        
        $margin_for_error_rows_equal = 10; // 10%

        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();

        // If from has no rows, the to table should also be empty
        if ($from_rows == 0 && $to_rows != 0) {
            $result->errors[] = sprintf(__("Finalize: Error in database synchronization for table %s - It should not contain any rows", "wpsynchro"), $from_tablename);
            return $result;
        }

        // If from has rows, but the to table is empty, could be memory limit hit, exceeding post max size or mysql max_packet_size
        if ($from_rows > 0 && $to_rows == 0) {
            $result->errors[] = sprintf(__("Finalize: Error in database synchronization for table %s - No rows has been transferred, but should contain %d rows. Normally this is because the ressource limits has been hit and the database content is too large. Consult documentation.", "wpsynchro"), $from_tablename, $from_rows);
            return $result;
        }

        // Check that rows approximately equal. Could have been changed a bit while synching, which is okay, but raises a warning if too much. Its okay if it is bigger
        if ($to_rows < ((1 - ($margin_for_error_rows_equal / 100)) * $from_rows)) {
            $result->warnings[] = sprintf(__("Finalize: Warning in database synchronization for table %s - It differs more than %d%% in size, which indicate something is wrong", "wpsynchro"), $from_tablename, $margin_for_warning_rows_equal);
        } else if ($to_rows < ((1 - ($margin_for_warning_rows_equal / 100)) * $from_rows)) {
            $result->warnings[] = sprintf(__("Finalize: Warning in database synchronization for table %s - It differs more than %d%% in size, which indicate something is wrong", "wpsynchro"), $from_tablename, $margin_for_warning_rows_equal);
        }

        return $result;
    }
}

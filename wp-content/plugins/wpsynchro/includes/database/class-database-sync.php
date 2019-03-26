<?php
namespace WPSynchro\Database;

/**
 * Class for handling database synchronization
 * @since 1.0.0
 */
class DatabaseSync
{

    // Data objects
    public $job = null;
    public $installation = null;
    public $logger = null;
    // Table prefix
    public $table_prefix = '';
    // Timers and limits
    public $starttime = 0;
    public $maxexecutiontime = 0;
    // PHP/MySQL limits
    public $max_allowed_packet_length = 0;
    public $max_post_request_length = 0;
    public $memory_limit = 0;
    public $max_time_per_sync = 0;
    public $max_response_length = 0;
    // Search/replaces 
    public $searchreplaces = [];
    public $searchreplace_count = 0;
    // Using mysqli/mysql
    public $use_mysqli = false;

    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {
        if (function_exists('mysqli_connect')) {
            $this->use_mysqli = true;
        }
        global $wpsynchro_container;
        $this->logger = $wpsynchro_container->get("class.Logger");
    }

    /**
     * Start a synchronization chunk - Returns completion percent
     * @since 1.0.0
     */
    public function runDatabaseSync(&$installation, &$job, $allotted_time_for_subjob)
    {
        $this->installation = &$installation;
        $this->job = &$job;
        $this->maxexecutiontime = $allotted_time_for_subjob;
        $this->starttime = microtime(true);

        $this->logger->log("INFO", "Starting database synchronization loop with allotted time: " . $allotted_time_for_subjob);

        // Get common library
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $this->table_prefix = $commonfunctions->getDBTempTableName();

        // Prepare sync data    
        $this->prepareSyncData();

        // Prepare result for method
        $result = new \stdClass();

        // Check preflight errors
        if (count($this->job->errors) > 0) {
            $result->success = false;
            return $result;
        }


        // Now, do some work
        $lastrun_time = 0;
        while (( microtime(true) - $this->starttime ) < ( $this->maxexecutiontime - $lastrun_time )) {

            $nomorework = true;
            foreach ($this->job->from_dbmasterdata as &$table) {
                if (isset($table->is_completed)) {
                    $table->rows = $table->completed_rows;
                } else {
                    // Pre processing throttling stuff
                    $this->handlePreProcessingThrottling($table);

                    // Call proper service to get/send data depending on pull/push
                    $time_retrievedata_start = microtime(true);

                    if ($this->installation->type == 'pull') {
                        $result_from_remote_service = $this->retrieveDataFromRemoteService($table);
                    } else if ($this->installation->type == 'push') {
                        $result_from_remote_service = $this->sendDataToRemoteService($table);
                    }

                    // Handle error from call to remote service
                    if (is_string($result_from_remote_service)) {
                        // Error      
                        $this->job->errors[] = $result_from_remote_service;
                        $result->success = false;
                        return $result;
                    } else {
                        $table->completed_rows += $result_from_remote_service;
                    }

                    if ($table->completed_rows > $table->rows) {
                        $table->rows = $table->completed_rows;
                    }
                    $time_retrievedata_stop = microtime(true);
                    $nomorework = false;

                    // Throttling
                    $lastrun_time = $time_retrievedata_stop - $time_retrievedata_start;
                    $this->handlePostProcessingThrottling($lastrun_time);
                    $this->logger->log("DEBUG", "Lastrun in ms: " . $lastrun_time . " rows throttle: " . $this->job->db_rows_per_sync);
                    // Break out to test if we have time for more
                    break;
                }
            }

            // Recalculate completion and update state in job
            $this->updateCompletionStatusPercent();

            // If no more work, mark as completed
            if ($nomorework) {
                $this->job->database_completed = true;
                break;
            }

            // Save status to DB       
            $this->job->save();
        }


        if (count($this->job->errors) > 0) {
            $result->success = false;
        } else {
            $result->success = true;
        }

        $this->logger->log("INFO", "Ending database synchronization loop with remaining time: " . (microtime(true) - $this->starttime) . " seconds");

        return $result;
    }

    /**
     * Prepare and fetch data for sync
     * @since 1.0.0
     */
    private function prepareSyncData()
    {

        // Determine max time per sync    
        $this->max_time_per_sync = ceil($this->maxexecutiontime / 5);
        if ($this->max_time_per_sync > 10) {
            $this->max_time_per_sync = 10;
        }

        // Check the search/replace's
        $this->searchreplaces = $this->installation->searchreplaces;
        $this->searchreplace_count = count($this->searchreplaces);


        // Remove tables from dbdata, if not all tables should be synced
        if ($this->installation->include_all_database_tables === false) {
            $onlyinclude = $this->installation->only_include_database_table_names;
            $newdbdata = [];
            foreach ($this->job->from_dbmasterdata as $table) {

                if (in_array($table->name, $onlyinclude)) {
                    $newdbdata[] = $table;
                }
            }
            $this->job->from_dbmasterdata = $newdbdata;
        }

        // Set max length limits for POST requests and Max allowed packet to MySQL - Determined from the smallest on the clients - And subtract 1000 bytes for safety distance
        $this->max_allowed_packet_length = min($this->job->from_max_allowed_packet_size, $this->job->to_max_allowed_packet_size);
        $this->max_post_request_length = min($this->job->from_max_post_size, $this->job->to_max_post_size) * 0.9;
        $this->memory_limit = (min($this->job->from_memory_limit, $this->job->to_memory_limit) - memory_get_peak_usage()) * 0.7;

        // Set max allowed packet to smallest of all these numbers
        $this->max_allowed_packet_length = min($this->max_allowed_packet_length, $this->max_post_request_length, $this->memory_limit) * 0.8;


        // Check if first run
        if (!$this->job->db_first_run_setup) {
            $this->createTablesOnRemoteDatabase();
            $this->job->db_first_run_setup = true;
        }
    }

    /**
     *  Handle pre processing throttling of rows based on time per sync
     *  @since 1.0.0
     */
    private function handlePreProcessingThrottling($table)
    {
        // If table is different than last time this ran
        if ($table->name != $this->job->db_throttle_table) {
            $this->job->db_throttle_table = $table->name;
            $this->job->db_rows_per_sync = $this->job->db_rows_per_sync_default;

            // Check if table rows will get to big, so we have to start lower
            if (($this->job->db_rows_per_sync * $table->row_avg_bytes) > $this->job->db_response_size_wanted_default) {
                $this->job->db_rows_per_sync = floor($this->job->db_response_size_wanted_default / $table->row_avg_bytes);
                if ($this->job->db_rows_per_sync == 0) {
                    $this->job->db_rows_per_sync = 1;
                }
            }

            // Check if new table has blobs, so lets start with a lower rows per sync, because they can be big
            if (count($table->binary_columns) > 0) {
                $this->job->db_rows_per_sync = 10;
            }

            $this->logger->log("DEBUG", "New table is started: " . sanitize_text_field($table->name) . " and setting new default rows per sync: " . $this->job->db_rows_per_sync);
        }
    }

    /**
     *  Handle post processing throttling of rows based on time per sync
     *
     *  @since 1.0.0
     */
    private function handlePostProcessingThrottling($lastrun_time)
    {

        // Check if we are too close to max memory (aka handling too large datasets and risking outofmemory) - One time thing per run
        $current_peak = memory_get_peak_usage();
        static $has_backed_off = false;
        if (!$has_backed_off && $current_peak > $this->memory_limit) {
            // Back off a bit
            $has_backed_off = true;
            $new_row_limit = floor($this->job->db_rows_per_sync * 0.70);
            $this->logger->log("WARNING", "Hit memory peak - Current peak: " . $current_peak . " and memory limit: " . $this->memory_limit . " - Backing off from: " . $this->job->db_rows_per_sync . " rows to: " . $new_row_limit . " rows");
            $this->job->db_rows_per_sync = $new_row_limit;
            return;
        }

        // Check that last return response size in bytes does not exceed the max limit
        if ($this->job->db_last_response_length > 0 && $this->job->db_last_response_length > $this->job->db_response_size_wanted_max) {
            // Back off   
            $this->job->db_rows_per_sync = intval($this->job->db_rows_per_sync * 0.80);
            return;
        }


        // Throttle rows per sync
        if ($lastrun_time < $this->max_time_per_sync) {
            // Scale up                    
            $this->job->db_rows_per_sync = ceil($this->job->db_rows_per_sync * 1.05);
        } else {
            // Back off   
            $this->job->db_rows_per_sync = ceil($this->job->db_rows_per_sync * 0.90);
        }
    }

    /**
     *  Send data to remote REST service (used for push)
     *  @since 1.0.0
     */
    private function sendDataToRemoteService(&$table)
    {

        global $wpdb;
        if ($this->installation == null) {
            return 0;
        }

        // Get data from server (to be send to remote)   
        if (strlen($table->primary_key_column) > 0) {
            $sql_stmt = 'select * from `' . $table->name . '` where `' . $table->primary_key_column . '` > ' . $table->last_primary_key . ' order by `' . $table->primary_key_column . '`  limit ' . intval($this->job->db_rows_per_sync);
        } else {
            $sql_stmt = 'select * from `' . $table->name . '` limit ' . $table->completed_rows . ',' . intval($this->job->db_rows_per_sync);
        }

        $data = $wpdb->get_results($sql_stmt);
        $this->logger->log("DEBUG", "Getting data from local DB with SQL query: " . $sql_stmt);

        $rows_fetched = count($data);

        // If rows fetched less than max rows, than mark table as completed
        if ($rows_fetched < $this->job->db_rows_per_sync) {
            $this->logger->log("DEBUG", "Marking table: " . $table->name . " as completed");
            $table->is_completed = true;
        }

        // Generate SQL queries from data
        $sql_inserts = [];
        if ($rows_fetched > 0) {
            $sql_inserts = $this->generateSQLInserts($table, $data, $this->max_allowed_packet_length);
        } else {
            return 0;
        }

        // Create POST request to remote
        $body = new \stdClass();
        $body->type = $this->installation->type;

        foreach ($sql_inserts as $sql_insert) {
            $body->sql_inserts = $sql_insert;
            $result = $this->callRemoteClientDBService($body, 'to');
        }

        // Check for error
        if (isset($result->error)) {
            $this->logger->log("ERROR", $result->error);
            return $result->error;
        }

        return $rows_fetched;
    }

    /**
     *  Call service for executing sql queries
     *  @since 1.0.0
     */
    public function callRemoteClientDBService(&$body, $to_or_from = 'to')
    {

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        if (($body->type == 'finalize' && $this->installation->type == 'pull') || ($body->type == 'pull' && $to_or_from == 'to')) {
            $url = $this->job->to_rest_base_url . "wpsynchro/v1/clientsyncdatabase/?token=" . $common->getAccessKey();
        } else if (($body->type == 'finalize' && $this->installation->type == 'push') || ($body->type == 'push' && $to_or_from == 'to')) {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $url = $this->job->to_rest_base_url . "wpsynchro/v1/clientsyncdatabase/?token=" . $key;
        } else if ($body->type == 'pull' && $to_or_from == 'from') {
            $key = $common->getTransferToken($this->installation->access_key, $this->job->remote_token);
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/clientsyncdatabase/?token=" . $key;
        } else if ($body->type == 'push' && $to_or_from == 'from') {
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/clientsyncdatabase/?token=" . $common->getAccessKey();
        }

        $json_encoded_body = json_encode($body);
        $json_length = strlen($json_encoded_body);

        $args = array(
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 0,
            'sslverify' => $this->installation->verify_ssl,
            'body' => $json_encoded_body,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );

        // result object
        $result = new \stdClass();

        $this->logger->log("DEBUG", "Calling remote service 'clientsyncdatabase' with request body length: " . $json_length);

        $response_arr = \wp_remote_post($url, $args);

        $responsecode = wp_remote_retrieve_response_code($response_arr);
        if (is_wp_error($response_arr)) {
            $result->error = $response_arr->get_error_message();
            $this->logger->log("DEBUG", "Remote service 'clientsyncdatabase' failed with WP error: " . $response_arr->get_error_message());
        } else {
            $body_json = $common->cleanRemoteJSONData(wp_remote_retrieve_body($response_arr));
            $this->job->db_last_response_length = strlen($body_json);

            $response_json = json_decode($body_json);
            $this->logger->log("DEBUG", "Got a proper response from 'clientsyncdatabase' with response length: " . $this->job->db_last_response_length);

            if ($responsecode != 200) {
                if (isset($response_json->error)) {
                    $result->error = $response_json->error;
                    $this->logger->log("ERROR", "Remote service 'clientsyncdatabase' failed with error: " . $response_json->error);
                } else {
                    $errormsg = sprintf(__("Remote/local site responded with HTTP error code %d, which means we can not continue the synchronization. Check log and contact support if this persist.", "wpsynchro"), $responsecode);
                    $this->logger->log("ERROR", $errormsg, $response_arr);
                    $result->error = $errormsg;
                }
                return $result;
            }
        }

        // Check for error from REST service
        if (isset($response_json->error)) {
            $this->logger->log("ERROR", $response_json->error);
            $result->error = $response_json->error;
        }

        // Check for returning data
        if (isset($response_json->data)) {
            $result->data = $response_json->data;
        }

        return $result;
    }

    /**
     *  Retrieve data from remote REST service (used for pull)
     *  @since 1.0.0
     */
    private function retrieveDataFromRemoteService(&$table)
    {

        global $wpdb;

        if ($this->installation == null) {
            return 0;
        }


        $body = new \stdClass();
        $body->table = $table->name;
        $body->last_primary_key = $table->last_primary_key;
        $body->primary_key_column = $table->primary_key_column;
        $body->binary_columns = $table->binary_columns;
        $body->completed_rows = $table->completed_rows;
        $body->max_rows = $this->job->db_rows_per_sync;
        $body->type = $this->installation->type;

        // Call remote service
        $this->logger->log("DEBUG", "Getting data from remote DB with data: " . json_encode($body));
        $remote_result = $this->callRemoteClientDBService($body, 'from');

        // Check for error
        if (isset($remote_result->error)) {
            $error = $remote_result->error;
            $this->logger->log("ERROR", $error);
            return $error;
        }

        $response = $remote_result->data;

        if (is_array($response)) {
            $rows_fetched = count($response);
        } else {
            $rows_fetched = 0;
        }

        // Handle binary data if any, so it can be transferred with json
        if (count($table->binary_columns) > 0) {
            foreach ($response as &$datarow) {
                foreach ($datarow as $col => &$coldata) {
                    if (in_array($col, $table->binary_columns)) {
                        $coldata = base64_decode($coldata);
                    }
                }
            }
        }

        if ($rows_fetched < $this->job->db_rows_per_sync) {
            $this->logger->log("DEBUG", "Marking table: " . $table->name . " as completed");
            $table->is_completed = true;
        }

        // Insert statements
        if ($rows_fetched > 0) {
            $sql_inserts = $this->generateSQLInserts($table, $response, $this->max_allowed_packet_length);

            foreach ($sql_inserts as &$sql_insert) {
                $wpdb->query($sql_insert);
                $wpdb->flush();
            }
        }

        $this->logger->log("DEBUG", "Inserted " . $rows_fetched . " rows into target database");

        return $rows_fetched;
    }

    /**
     *  Generate sql inserts, queued together inside max_packet_allowed gathered from metadata and setup in preparesyncdata method
     *  @since 1.0.0
     */
    private function generateSQLInserts(&$table, &$rows, $max_packet_length)
    {

        $insert_buffer = '';
        $insert_buffer_length = 0;
        $insert_count = 0;
        $insert_count_max = 998;    // Max 1000 inserts per statement, limit in mysql (minus a few such as foreign key check)
        $last_primary_key = 0;
        $inserts_array = array();
        $inserts_array[] = "SET FOREIGN_KEY_CHECKS=0;";

        $sql_insert_prefix = function($tablename, $col_and_val) {
            $cols = array_keys($col_and_val);

            $insert_buffer = 'INSERT INTO `' . $this->table_prefix . $tablename . '` (`' . implode('`,`', $cols) . '`) VALUES ';
            return $insert_buffer;
        };

        foreach ($rows as $row) {
            // If beginning of new buffer
            $col_and_val = get_object_vars($row);

            if ($insert_buffer == '') {
                $insert_buffer = $sql_insert_prefix($table->name, $col_and_val);
                $insert_buffer_length = strlen($insert_buffer);
            }

            $temp_insert_add = '(';
            $error_during_column_handling = false;
            foreach ($col_and_val as $col => $val) {
                if ($col == $table->primary_key_column) {
                    $last_primary_key = $val;
                }

                if (in_array($col, $table->binary_columns)) {
                    $available_memory = $this->memory_limit - memory_get_usage();
                    $val_length = strlen($val);
                    $expected_length = $val_length * 2;
                    if ($expected_length > $available_memory) {
                        $warningsmsg = sprintf(__("Large row with binary column ignored from table: %s - Size of value: %d - Increase memory limit on server", "wpsynchro"), $table->name, $val_length);
                        $this->logger->log("WARNING", $warningsmsg);
                        $this->job->warnings[] = $warningsmsg;
                        $error_during_column_handling = true;
                        break;
                    } else {
                        if (strlen($val) > 0) {
                            $temp_insert_add .= "0x" . bin2hex($val) . ",";
                        } else {
                            $temp_insert_add .= "NULL,";
                        }
                    }
                } else if (is_numeric($val)) {
                    if (strpos($val, 'e') > -1 || strpos($val, 'E') > -1) {
                        $temp_insert_add .= "'" . $this->escape($val) . "',";
                    } else {
                        $temp_insert_add .= $this->escape($val) . ',';
                    }
                } elseif (is_string($val)) {
                    if ($col != 'guid') {
                        $val = $this->handleSearchReplace($val);
                    }
                    $temp_insert_add .= "'" . $this->escape($val) . "',";
                } elseif (is_null($val)) {
                    $temp_insert_add .= 'NULL,';
                }
            }

            if ($error_during_column_handling) {
                continue;
            }

            $temp_insert_add = trim($temp_insert_add, ', ') . '),';
            $tmp_insert_add_length = strlen($temp_insert_add);

            if ($tmp_insert_add_length > $max_packet_length) {
                $warningsmsg = sprintf(__("Large row ignored from table: %s - Size: %d - Increase allowed values on server", "wpsynchro"), $table->name, $tmp_insert_add_length);
                $this->logger->log("WARNING", $warningsmsg);
                $this->job->warnings[] = $warningsmsg;
                continue;
            }

            if (( ( $insert_buffer_length + $tmp_insert_add_length ) < $max_packet_length ) && $insert_count < $insert_count_max) {
                $insert_buffer .= $temp_insert_add;
                $insert_buffer_length += $tmp_insert_add_length;
                $insert_count++;
            } else {
                // Save sql to array
                $insert_buffer = trim($insert_buffer, ', ');
                $inserts_array[] = $insert_buffer;
                // Start from beginning
                $insert_buffer = $sql_insert_prefix($table->name, $col_and_val);
                $insert_buffer .= $temp_insert_add;
                $insert_buffer_length = strlen($insert_buffer);
                $insert_count = 1;
            }
        }
        if (strlen($insert_buffer) > 0 && $insert_count > 0) {
            $insert_buffer = trim($insert_buffer, ', ');
            $inserts_array[] = $insert_buffer;
        }

        $table->last_primary_key = $last_primary_key;

        return $inserts_array;
    }

    /**
     * Handle SQL escape
     * @since 1.0.0
     */
    private function escape($data)
    {
        global $wpdb;

        if ($this->use_mysqli) {
            $escaped = mysqli_real_escape_string($wpdb->__get("dbh"), $data);
        } else {
            // @codeCoverageIgnoreStart
            $escaped = mysql_real_escape_string($data, $wpdb->__get("dbh"));
            // @codeCoverageIgnoreEnd
        }

        return $escaped;
    }

    /**
     * Handle search/replace in data
     * @since 1.0.0
     */
    private function handleSearchReplace($data)
    {

        if ($this->searchreplace_count === 0) {
            return $data;
        }

        // Check data type
        $is_serialized = false;
        if (is_serialized($data)) {
            $is_serialized = true;
        }

        foreach ($this->searchreplaces as $replaces) {
            $data = str_replace($replaces->from, $replaces->to, $data);
        }

        if ($is_serialized) {
            // Fix the serialization
            $data = $this->fixSerializedString($data);
        }

        return $data;
    }

    /**
     * Fix serialization
     * @since 1.0.0
     */
    private function fixSerializedString($str)
    {

        preg_match_all('#s:([0-9]+):"([^;]+)"#', $str, $m);
        foreach ($m[1] as $k => $len) {
            if ($len != strlen($m[2][$k])) {
                $newstr = 's:' . strlen($m[2][$k]) . ':"' . $m[2][$k] . '"';
                $str = str_replace($m[0][$k], $newstr, $str);
            }
        }
        return $str;
    }

    /**
     *  Create tables on remote (and filter out temp tables)
     *  @since 1.0.0
     */
    private function createTablesOnRemoteDatabase()
    {

        global $wpdb;

        // the list of queries to setup tables (and drop temp tables, if any)
        $sql_queries = array();

        // Disable foreign key checks
        $sql_queries[] = "SET FOREIGN_KEY_CHECKS = 0;";

        // Create the temp tables (and drop them if already exists)
        foreach ($this->job->from_dbmasterdata as $table) {

            if (strpos($table->name, $this->table_prefix) === 0) {
                continue;
            }


            $new_table_name = $this->table_prefix . $table->name;
            $table->create_table = str_replace('`' . $table->name . '`', '`' . $new_table_name . '`', $table->create_table);

            // Go through every table name, so see if any is referenced in create statement - Could be a innodb constraint or whatever
            foreach ($this->job->from_dbmasterdata as $inside_table) {
                if ($inside_table->name != $new_table_name) {
                    $new_inside_table_name = $this->table_prefix . $inside_table->name;
                    $table->create_table = str_replace('`' . $inside_table->name . '`', '`' . $new_inside_table_name . '`', $table->create_table);
                }
            }

            // Change name to random in all constraints, if there, to prevent trouble with existing  
            $table->create_table = preg_replace_callback("/CONSTRAINT\s`(\w+)`/", function() {
                return "CONSTRAINT `" . uniqid() . "`";
            }, $table->create_table);

            // Drop if it exists
            $sql_queries[] = 'DROP TABLE IF EXISTS `' . $new_table_name . '`';

            // Adapt create statement according to MySQL version
            $sql_queries[] = $this->adaptCreateStatement($table->create_table, $this->job->from_sql_version, $this->job->to_sql_version);
        }

        if ($this->installation->type == "pull") {

            // Execute the sql queries
            foreach ($sql_queries as $sql_query) {
                $wpdb->query($sql_query);
            }
        } else if ($this->installation->type == "push") {
            // if push, then always call remote service for sql create tables

            $body = new \stdClass();
            $body->sql_inserts = $sql_queries;
            $body->type = $this->installation->type;

            $result = $this->callRemoteClientDBService($body, 'to');

            if (isset($result->error)) {
                $this->job->errors[] = $result->error;
            }
        }
    }

    /**
     *  Change create statements according to MySQL version
     *  @since 1.0.0
     */
    public function adaptCreateStatement($create, $from_db_version, $to_db_version)
    {
        // If same version, all is good
        if (version_compare($from_db_version, $to_db_version) == 0) {
            return $create;
        }

        // Change from unicode 5.2 (520) to "normal" utf8mb4 unicode on MySQL versions before 5.6
        if (version_compare($to_db_version, '5.6', '<')) {
            $create = str_replace('utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', $create);
            $create = str_replace('utf8_unicode_520_ci', 'utf8_unicode_ci', $create);
        }

        return $create;
    }

    /**
     *  Calculate completion percent
     *  @since 1.0.0
     */
    private function updateCompletionStatusPercent()
    {
        if (!isset($this->job->from_dbmasterdata)) {
            return;
        }

        $totalrows = 0;
        $completedrows = 0;
        $percent_completed = 0;
        // Data sizes
        $total_data_size = 0;

        foreach ($this->job->from_dbmasterdata as $table) {
            if (isset($table->rows)) {
                $temp_rows = $table->rows;
            } else {
                $temp_rows = 0;
            }
            if (isset($table->completed_rows)) {
                $temp_completedrows = $table->completed_rows;
            } else {
                $temp_completedrows = 0;
            }
            $totalrows += $temp_rows;
            $completedrows += $temp_completedrows;
            $total_data_size += $table->data_total_bytes;
        }

        if ($totalrows > 0) {
            $percent_completed = floor(( $completedrows / $totalrows ) * 100);
        } else {
            $percent_completed = 100;
        }
        // :)
        if ($percent_completed > 100) {
            $percent_completed = 100;
        }

        $this->job->database_progress = $percent_completed;

        // Update status description
        $current_number = $total_data_size * ($percent_completed / 100);
        $total_number = $total_data_size;
        $one_mb = 1012 * 1024;

        if ($total_number < $one_mb) {
            $total_number = number_format($total_number / 1024, 0, ",", ".") . "kB";
            $current_number = number_format($current_number / 1024, 0, ",", ".") . "kB";
        } else {
            $total_number = number_format($total_number / $one_mb, 1, ",", ".") . "MB";
            $current_number = number_format($current_number / $one_mb, 1, ",", ".") . "MB";
        }

        $completed_desc_rows = number_format($completedrows, 0, ",", ".");
        $total_desc_rows = number_format($totalrows, 0, ",", ".");

        $database_progress_description = sprintf(__("Total data: %s / %s - Total rows: %s / %s", "wpsynchro"), $current_number, $total_number, $completed_desc_rows, $total_desc_rows);
        $this->logger->log("INFO", "Database progress update: " . $database_progress_description);
        $this->job->database_progress_description = $database_progress_description;
    }
}

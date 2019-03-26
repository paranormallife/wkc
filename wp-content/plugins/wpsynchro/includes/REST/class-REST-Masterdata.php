<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "masterdata"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class RESTMasterData
{

    public $numeric_column_types = array("integer", "int", "decimal", "numeric", "float", "double", "real", "dec", "fixed");

    public function service($request)
    {

        global $wpdb;
        $result = new \stdClass();

        // Check php/mysql/wp requirements
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $compat_errors = $commonfunctions->checkEnvCompatability();
        $temp_table_prefix = $commonfunctions->getDBTempTableName();

        if (count($compat_errors) > 0) {
            // @codeCoverageIgnoreStart
            foreach ($compat_errors as &$error) {
                $error = __("Error from remote server:", "wpsynchro") . " " . $error;
            }
            $result->errors = $compat_errors;
            return new \WP_REST_Response($result, 500);
            // @codeCoverageIgnoreEnd
        }

        $parameters = $request->get_params();
        if (isset($parameters['type'])) {
            $type = $parameters['type'];
        } else {
            $type = array();
        }

        /*
         *  Get tables in database
         */
        if (in_array('dbtables', $type)) {
            $tables_sql = $wpdb->get_results('SHOW TABLES');
            $tables = array();
            foreach ($tables_sql as $tb) {
                foreach ($tb as $tablename) {
                    if (strpos($tablename, $temp_table_prefix) === 0) {
                        continue;
                    }

                    $tables[] = $tablename;
                }
            }
            $result->dbtables = $tables;
        }

        /*
         *  Get detailed listing of database tables and sizes
         */
        if (in_array('dbdetails', $type)) {
            $tables_sql = $wpdb->get_results('SHOW TABLE STATUS');
            $tables_details = array();
            $table_tmptables_details = array();
            foreach ($tables_sql as $tb) {

                // Get the actual count on rows, because show table status is not precise
                $exactrows = $wpdb->get_var("select count(*) from `" . $tb->Name . "`");
                $tmp_arr = [];
                $tmp_arr['name'] = $tb->Name;
                $tmp_arr['rows'] = intval($exactrows);
                $tmp_arr['completed_rows'] = 0;
                $tmp_arr['row_avg_bytes'] = $tb->Avg_row_length;
                $tmp_arr['data_total_bytes'] = $tb->Data_length;

                // If temp table, add to seperate array (mostly used in finalize)
                if (strpos($tb->Name, $temp_table_prefix) === 0) {
                    $table_tmptables_details[] = $tmp_arr;
                } else {
                    $tables_details[] = $tmp_arr;
                }
            }

            // Show create table
            foreach ($tables_details as &$tb) {
                $createsql = $wpdb->get_row('show create table `' . $tb['name'] . '`', ARRAY_N);
                $createsql[1] = mb_convert_encoding($createsql[1], 'UTF-8', 'UTF-8');
                $tb['create_table'] = $createsql[1];
            }

            // Get primary key (for faster data fetch)
            foreach ($tables_details as &$tb) {
                $primarysql_key = $wpdb->get_row('SHOW KEYS FROM `' . $tb['name'] . '` WHERE Key_name = "PRIMARY"', ARRAY_N);
                $tb['primary_key_column'] = $primarysql_key[4];

                if (!$this->isPrimaryIndexNumeric($tb['create_table'], $tb['primary_key_column'])) {
                    $tb['primary_key_column'] = "";
                }

                $tb['last_primary_key'] = 0;
            }

            // Check for speciel columns, ex blob's
            foreach ($tables_details as &$tb) {
                $tb['binary_columns'] = $this->extractBlobBinaryColumnsFromSQLCreate($tb['create_table']);
            }

            $result->dbdetails = $tables_details;
            $result->tmptables_dbdetails = $table_tmptables_details;
        }

        /*
         *  Get information needed for files
         */
        if (in_array('filedetails', $type)) {

            $result->files_home_dir = realpath($_SERVER['DOCUMENT_ROOT']);
            // Absolut directory of WordPress root folder
            $result->files_wp_dir = realpath(ABSPATH);
            // Absolut directory of WP_CONTENT folder, or whatever it is called
            $result->files_wp_content_dir = realpath(WP_CONTENT_DIR);
            // One dir above webroot
            $result->files_above_webroot_dir = realpath(dirname($result->files_home_dir));
            // Get plugin list
            $result->files_plugin_list = array();
            if (!function_exists('get_plugins')) {
                require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
            }
            $all_pluginlist = \get_plugins();
            foreach ($all_pluginlist as $pluginslug => $plugindata) {
                $tmp_arr = array();
                $tmp_arr['slug'] = $pluginslug;
                $tmp_arr['name'] = $plugindata['Name'];

                $result->files_plugin_list[] = $tmp_arr;
            }
            // Get theme list
            $result->files_theme_list = array();
            $all_themeslist = \wp_get_themes();
            foreach ($all_themeslist as $themeslug => $wp_theme) {
                $tmp_arr = array();
                $tmp_arr['slug'] = $themeslug;
                $tmp_arr['name'] = $wp_theme->get("Name");

                $result->files_theme_list[] = $tmp_arr;
            }
        }

        /*
         *  Insert standard information on site
         */
        $result->client_home_url = home_url('/');
        $result->rest_base_url = rest_url();
        $result->wp_options_table = $wpdb->options;


        // Get max allowed packet size from sql
        $result->max_allowed_packet_size = (int) $wpdb->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'")->Value;
        // Get max post size
        $result->max_post_size = $commonfunctions->convertPHPSizeToBytes(ini_get('post_max_size'));
        // Get max upload filesize
        $result->upload_max_filesize = $commonfunctions->convertPHPSizeToBytes(ini_get('upload_max_filesize'));
        // Get memory limit
        $result->memory_limit = $commonfunctions->convertPHPSizeToBytes(ini_get('memory_limit'));
        // Get max_file_uploads
        $result->max_file_uploads = (int) ini_get('max_file_uploads');
        // MySQL version
        $result->sql_version = $wpdb->get_var("select VERSION()");
        // WP Synchro plugin version
        $result->plugin_version = WPSYNCHRO_VERSION . " " . (\WPSynchro\WPSynchro::isPremiumVersion() ? 'PRO' : 'FREE');

        return new \WP_REST_Response($result, 200);
    }

    /**
     *  Function to return column that are blobs
     */
    public function extractBlobBinaryColumnsFromSQLCreate($sqlcreate)
    {
        $columns = array();
        $lines = explode("\n", $sqlcreate);
        foreach ($lines as $line) {
            if (strpos(trim($line), "`") != 0) {
                continue;
            }

            $parts = explode("`", $line);
            if (isset($parts[1]) && isset($parts[2])) {
                if (stripos($parts[2], "blob") > -1 || stripos($parts[2], "binary") > -1) {
                    $columns[] = $parts[1];
                }
            }
        }
        return $columns;
    }

    /**
     *  Function to determine if primary index is numeric
     */
    public function isPrimaryIndexNumeric($sqlcreate, $column)
    {
        if ($column == "") {
            return false;
        }

        $lines = explode("\n", $sqlcreate);
        $column = '`' . $column . '`';
        foreach ($lines as $line) {
            if (strpos($line, $column) > -1) {
                $parts = explode("`", $line);
                $col_part = trim($parts[2]);
                $col_parts = explode(" ", $col_part);

                foreach ($this->numeric_column_types as $num_col_type) {
                    if (strpos($col_parts[0], $num_col_type) > -1) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

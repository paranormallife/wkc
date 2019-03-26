<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "clientsyncdatabase" - Execute SQL from remote
 * Call should already be verified by permissions callback
 * @since 1.0.0
 */
class RESTClientSyncDatabase
{

    public function service($request)
    {

        global $wpdb;
        $result = new \stdClass();

        // Extract parameters
        $body = $request->get_json_params();

        if (isset($body['table'])) {
            $table = $body['table'];
        } else {
            $table = '';
        }
        if (isset($body['last_primary_key'])) {
            $last_primary_key = $body['last_primary_key'];
        } else {
            $last_primary_key = 0;
        }
        if (isset($body['primary_key_column'])) {
            $primary_key_column = $body['primary_key_column'];
        } else {
            $primary_key_column = "";
        }
        if (isset($body['binary_columns'])) {
            $binary_columns = $body['binary_columns'];
        } else {
            $binary_columns = array();
        }
        if (isset($body['completed_rows'])) {
            $completed_rows = $body['completed_rows'];
        } else {
            $completed_rows = 0;
        }
        if (isset($body['max_rows'])) {
            $max_rows = $body['max_rows'];
        } else {
            $max_rows = 0;
        }
        if (isset($body['sql_inserts'])) {
            $sql_inserts = $body['sql_inserts'];
        } else {
            $sql_inserts = array();
        }

        $type = $body['type'];

        // Get allowed methods for this site
        $methods_allowed = get_option('wpsynchro_allowed_methods', false);
        if (!$methods_allowed) {
            $methods_allowed = new \stdClass();
            $methods_allowed->pull = false;
            $methods_allowed->push = false;
        }

        // Check the type and if it is allowed
        if ($type == "pull" && !$methods_allowed->pull) {
            $result = new \stdClass();
            $result->error = __("Pulling from this site is not allowed - Change configuration on remote server", "wpsynchro");
            return new \WP_REST_Response($result, 500);
        } else if ($type == "push" && !$methods_allowed->push) {
            $result = new \stdClass();
            $result->error = __("Pushing to this site is not allowed - Change configuration on remote server", "wpsynchro");
            return new \WP_REST_Response($result, 500);
        }

        if ($type == "pull") {
            // Get data
            if (strlen($primary_key_column) > 0) {
                $sql_stmt = 'select * from `' . $table . '` where `' . $primary_key_column . '` > ' . $last_primary_key . ' order by `' . $primary_key_column . '`  limit ' . intval($max_rows);
            } else {
                $sql_stmt = 'select * from `' . $table . '` limit ' . $completed_rows . ',' . intval($max_rows);
            }

            $data = $wpdb->get_results($sql_stmt, ARRAY_A);

            // Handle binary data if any, so it can be transferred with json
            if (count($binary_columns) > 0) {
                foreach ($data as &$datarow) {
                    foreach ($datarow as $col => &$coldata) {
                        if (in_array($col, $binary_columns)) {
                            $coldata = base64_encode($coldata);
                        }
                    }
                }
            }

            // Setup default variables
            $result->data = $data;
            // Send results
            return new \WP_REST_Response($result, 200);
        } elseif ($type == "push") {
            if (is_array($sql_inserts)) {
                foreach ($sql_inserts as $sql_insert) {
                    $result = $wpdb->query($sql_insert);
                }
            } else {
                $result = $wpdb->query($sql_inserts);
            }
        } elseif ($type == "finalize") {
            foreach ($sql_inserts as $sql_insert) {
                $result = $wpdb->query($sql_insert);
            }
        }
        return new \WP_REST_Response($result, 200);
    }
}

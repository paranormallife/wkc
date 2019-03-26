<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "filetransfer" - Receiving files
 * @since 1.0.3
 */
class FileTransfer
{

    public function service(\WP_REST_Request $request)
    {

        // Get data from request

        $fileparam = $request->get_file_params();
        $bodyparam = $request->get_body_params();

        // Setup return array
        $result = array();

        // Errors
        $php_file_upload_errors = array(
            0 => 'There is no error, the file uploaded with success',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        );

        foreach ($fileparam as $file) {
            $file_key = $file['name'];
            $result[$file_key] = array();


            if ($file['error'] > 0) {
                // Something is wrong, so send the error back
                $result[$file_key]['success'] = false;
                $result[$file_key]['error'] = $php_file_upload_errors[$file['error']];
            } else {
                // Seems good
                $filedata = json_decode($bodyparam["file_key_" . $file_key]);
                $result[$file_key]['section'] = $filedata->section;
                
                $filedata->target_tmp_file = utf8_decode($filedata->target_tmp_file);
                $dirname = dirname($filedata->target_tmp_file);
                
                if (wp_mkdir_p($dirname)) {
                    $file_data_position = 0;

                    if (isset($filedata->partial)) {
                        $file_data_position = $filedata->partial_position;
                        $result[$file_key]['partial'] = true;
                        $result[$file_key]['partial_position'] = $file_data_position + filesize($file['tmp_name']);
                    }

                    if ($file_data_position == 0) {
                        $move_success = move_uploaded_file($file['tmp_name'], $filedata->target_tmp_file);
                        if ($move_success) {
                            $result[$file_key]['success'] = true;
                        } else {
                            $result[$file_key]['error'] = sprintf(__("Moving temporary files to %s failed.", "wpsynchro"), $filedata->target_tmp_file);
                            $result[$file_key]['success'] = false;
                        }
                    } else {
                        $file_binary_data = file_get_contents($file['tmp_name']);
                        $file_append_result = file_put_contents($filedata->target_tmp_file, $file_binary_data, FILE_APPEND);
                        if ($file_append_result === false) {
                            $result[$file_key]['error'] = sprintf(__("Appending temporary filedata to %s failed.", "wpsynchro"), $filedata->target_tmp_file);
                            $result[$file_key]['success'] = false;
                        } else {
                            $result[$file_key]['success'] = true;
                        }
                    }
                } else {
                    $result[$file_key]['error'] = sprintf(__("Could not create directory %s on target for file %s.", "wpsynchro"), $dirname, $filedata->target_tmp_file);
                    $result[$file_key]['success'] = false;
                }
            }
        }

        return new \WP_REST_Response($result, 200);
    }
}

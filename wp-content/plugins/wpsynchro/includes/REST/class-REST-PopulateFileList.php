<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "PopulateFileList" - Returns file lists 
 * Call should already be verified by permissions callback
 * @since 1.0.3
 */
class PopulateFileList
{

    public function service($request)
    {

        $result = new \stdClass();

        // Extract parameters
        $body = $request->get_json_params();
        $section = $body['section'];
        $allotted_time = $body['allotted_time'];
        $exclusions = trim($body['exclusions']);

        // Generate exclusion list
        if (strlen($exclusions) > 0) {
            $exclusions_pieces = explode(",", $exclusions);
            foreach ($exclusions_pieces as &$exclusion_piece) {
                $exclusion_piece = preg_quote(trim($exclusion_piece));
            }
            $exclusions_regex = "(" . implode("|", $exclusions_pieces) . ")";
        } else {
            $exclusions_regex = false;
        }

        // Get time limit in seconds (float)
        $maxexecutiontime = intval(ini_get('max_execution_time'));
        if ($maxexecutiontime == 0 || $maxexecutiontime > 30) {
            // Set max time to 30, just to avoid other stuff cutting it off
            $maxexecutiontime = 30;
        }
        $maxexecutiontime -= 3; // Just for safety
        // Get the smallest, either max execution time on this site or the allotted time by calling php process
        $maxexecutiontime = min($maxexecutiontime, $allotted_time);

        $starttime = microtime(true);

        $file_obj_factory = function($name, $size) {
            $file_tmp = array();
            $file_tmp['source_file'] = $name;
            $file_tmp['size'] = $size;
            $file_tmp['hash'] = null;
            $file_tmp['target_synced'] = false;
            $file_tmp['completed'] = false;
            return $file_tmp;
        };

        $paths = $this->getPathByType($section['type'], $section['extra']);

        foreach ($paths as $path) {
            if (is_dir($path)) {
                // is Dir
                $path = realpath($path);

                // When dir, make sure it is found in temp_dirs_in_basepath in section (so finalize renames will be correct)
                $basename = basename($path);
                $section['temp_dirs_in_basepath'][utf8_encode($basename)] = true;

                // Iterate to get the files 
                if ($path !== false && $path != '' && file_exists($path)) {
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $name => $object) {
                        if (!$this->isOnExclusionList($exclusions_regex, $name)) {
                            $name = utf8_encode($name);  
                            $section['file_list'][] = $file_obj_factory($name, $object->getSize());
                        }
                    }
                }
            } else {
                // File  
                if (!$this->isOnExclusionList($exclusions_regex, $path)) {
                    $path = utf8_encode($path);       
                    $section['file_list'][] = $file_obj_factory($path, filesize($path));
                }
            }
        }

        $result->file_list = $section['file_list'];
        $result->temp_dirs_in_basepath = $section['temp_dirs_in_basepath'];
        return new \WP_REST_Response($result, 200);
    }

    /**
     * Check if filename is matched with exclusions
     * @since 1.0.3
     */
    public function isOnExclusionList($exclusion_regex, $filename)
    {

        if (!$exclusion_regex) {
            return false;
        }

        if (preg_match($exclusion_regex, $filename) === 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get actual paths based on type
     * @since 1.0.3
     */
    public function getPathByType($type, $extra)
    {
        $paths = array();
        switch ($type) {
            case "wp_core":
                $paths[] = trailingslashit(ABSPATH) . "wp-admin";
                $paths[] = trailingslashit(ABSPATH) . "wp-includes";
                // Handle php files in webroot
                $phpfiles_in_root = glob(trailingslashit(ABSPATH) . "*.php");
                $paths = array_merge($paths, $phpfiles_in_root);

                break;
            case "plugins":
                if ($extra['include_all']) {
                    $paths[] = WP_PLUGIN_DIR;
                } else {
                    foreach ($extra['chosen'] as $plugin) {
                        $paths[] = realpath(trailingslashit(WP_PLUGIN_DIR) . dirname(trim($plugin)));
                    }
                }
                break;
            case "themes":
                $basetheme_dir = get_theme_root();
                if ($extra['include_all']) {
                    $paths[] = $basetheme_dir;
                } else {
                    foreach ($extra['chosen'] as $theme) {
                        $paths[] = realpath(trailingslashit($basetheme_dir) . trim($theme));
                    }
                }
                break;
            case "uploads":
                $paths[] = realpath(wp_upload_dir()['basedir']);
                break;
            case "relative_webroot":
                $basedir = realpath($_SERVER['DOCUMENT_ROOT']);
                $parts = explode(",", $extra);
                foreach ($parts as $part) {
                    $paths[] = realpath(trailingslashit($basedir) . trim(utf8_decode($part)));
                }

                break;
            case "relative_wpcontent":
                $parts = explode(",", $extra);
                foreach ($parts as $part) {
                    $paths[] = realpath(trailingslashit(WP_CONTENT_DIR) . trim(utf8_decode($part)));
                }
                break;
            case "relative_above_webroot":
                $basedir = dirname(dirname(ABSPATH));
                $parts = explode(",", $extra);
                foreach ($parts as $part) {
                    $paths[] = realpath(trailingslashit($basedir) . trim(utf8_decode($part)));
                }
                break;
        }
        return $paths;
    }
}

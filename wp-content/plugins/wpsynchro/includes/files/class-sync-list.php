<?php
namespace WPSynchro\Files;

/**
 * Class for handling the file sync list used to synchronize files
 * @since 1.0.3
 */
class SyncList
{

    // Base data
    public $job = null;
    public $installation = null;
    public $remote_post_obj = null;
    // File list data
    public $sections = array();
    // Progress counters
    public $tmp_prefix = "wpsyntmp-";
    public $files_total_counter = null;
    public $files_total_size = 0;
    public $files_hashed_counter = 0;
    public $files_completed_counter = 0;
    public $files_completed_size = 0;
    // Progress bool's
    public $initialized = false;                    // Initial values of sections are created
    public $all_sections_populated = false;         // Make a list of files from source that is included in sync
    public $all_sections_hashed = false;            // Make sure all files has a hash value, so we can compare on remote
    public $all_sections_path_handled = false;    // Go through all sections and files to determine what temp dirs to create and the correct pathnames for files on remote
    public $all_target_processed = false;           // Has been processed on target, going through hashes and compare, check which files need actual moving 
    public $all_completed = false;                  // All files have been synced and awaiting finalize rename

    /**
     *  Constructor
     */

    public function __construct(\WPSynchro\RemotePOST $remote_post_obj)
    {
        $this->remote_post_obj = $remote_post_obj;
    }

    /**
     *  Initialize class
     *  @since 1.0.3
     */
    public function init(\WPSynchro\Installation &$installation, \WPSynchro\Job &$job)
    {
        $this->installation = &$installation;
        $this->job = $job;
        $this->loadFileSyncList();
    }

    /**
     *  Setup initial objects and data
     *  @since 1.0.3
     */
    public function setupInitialFileStructure()
    {
        if ($this->initialized === false) {



            // Set default data
            $default_sync_object = function ($type, $name, $extra = null, $temp_dirs = array(), $source_basepath = "", $target_basepath = "") {
                global $wpsynchro_container;
                $common = $wpsynchro_container->get("class.CommonFunctions");

                $tmp_obj = new \stdClass();
                // Section base data
                $tmp_obj->name = $name;
                $tmp_obj->type = $type;
                $tmp_obj->extra = $extra;
                $tmp_obj->temp_dirs_in_basepath = $temp_dirs;
                $tmp_obj->source_basepath = $common->fixPath($source_basepath);
                $tmp_obj->target_basepath = $common->fixPath($target_basepath);
                // Data
                $tmp_obj->file_list = array();
                // Progress
                $tmp_obj->files_list_complete = false;
                $tmp_obj->files_hashing_complete = false;
                $tmp_obj->files_path_handled = false;
                $tmp_obj->files_temp_dirs_created = false;
                $tmp_obj->files_target_synced = false;
                // Renames in finalize
                $tmp_obj->finalize_renames = array();

                return $tmp_obj;
            };

            // WP CORE
            if ($this->installation->files_wordpress_core) {
                $this->sections[] = $default_sync_object("wp_core", __("WordPress Core", "wpsynchro"), null, array("wp-admin" => true, "wp-includes" => true), $this->job->from_files_wp_dir, $this->job->to_files_wp_dir);
            }
            // Plugins
            if ($this->installation->files_wordpress_wpcontent_plugins) {
                $tmp_arr = array();
                $tmp_arr['include_all'] = $this->installation->files_include_all_plugins;
                $tmp_arr['chosen'] = $this->installation->files_only_include_plugins;
                if ($tmp_arr['include_all']) {
                    $this->sections[] = $default_sync_object("plugins", __("Plugins", "wpsynchro"), $tmp_arr, array("plugins" => true), $this->job->from_files_wp_content_dir, $this->job->to_files_wp_content_dir);
                } else {
                    $temp_dirs = array();
                    foreach ($tmp_arr['chosen'] as $temp_dir) {
                        $temp_dirs[dirname($temp_dir)] = true;
                    }
                    $source_basepath = trailingslashit($this->job->from_files_wp_content_dir) . "plugins";
                    $target_basepath = trailingslashit($this->job->to_files_wp_content_dir) . "plugins";
                    $this->sections[] = $default_sync_object("plugins", __("Plugins", "wpsynchro"), $tmp_arr, $temp_dirs, $source_basepath, $target_basepath);
                }
            }
            // Themes
            if ($this->installation->files_wordpress_wpcontent_themes) {
                $tmp_arr = array();
                $tmp_arr['include_all'] = $this->installation->files_include_all_themes;
                $tmp_arr['chosen'] = $this->installation->files_only_include_themes;
                if ($tmp_arr['include_all']) {
                    $this->sections[] = $default_sync_object("themes", __("Themes", "wpsynchro"), $tmp_arr, array("themes" => true), $this->job->from_files_wp_content_dir, $this->job->to_files_wp_content_dir);
                } else {
                    $temp_dirs = array();
                    foreach ($tmp_arr['chosen'] as $temp_dir) {
                        $temp_dirs[$temp_dir] = true;
                    }
                    $source_basepath = trailingslashit($this->job->from_files_wp_content_dir) . "themes";
                    $target_basepath = trailingslashit($this->job->to_files_wp_content_dir) . "themes";
                    $this->sections[] = $default_sync_object("themes", __("Themes", "wpsynchro"), $tmp_arr, $temp_dirs, $source_basepath, $target_basepath);
                }
            }
            // Uploads
            if ($this->installation->files_wordpress_wpcontent_uploads) {
                $tmp_arr = array();
                $tmp_arr['inline'] = $this->installation->files_media_in_current_dir;
                $this->sections[] = $default_sync_object("uploads", __("Uploads/media", "wpsynchro"), $tmp_arr, array("uploads" => true), $this->job->from_files_wp_content_dir, $this->job->to_files_wp_content_dir);
            }
            // Additional files
            if ($this->installation->files_include_relative_webroot) {
                $this->sections[] = $default_sync_object("relative_webroot", __("Additional files/dirs in web root", "wpsynchro"), $this->installation->files_include_relative_webroot, array(), $this->job->from_files_home_dir, $this->job->to_files_home_dir);
            }
            if ($this->installation->files_include_relative_wpcontent) {
                $this->sections[] = $default_sync_object("relative_wpcontent", __("Additional files/dirs in WP Content", "wpsynchro"), $this->installation->files_include_relative_wpcontent, array(), $this->job->from_files_wp_content_dir, $this->job->to_files_wp_content_dir);
            }
            if ($this->installation->files_include_relative_abovewebroot) {
                $this->sections[] = $default_sync_object("relative_above_webroot", __("Additional files/dirs above web root", "wpsynchro"), $this->installation->files_include_relative_abovewebroot, array(), $this->job->from_files_above_webroot_dir, $this->job->to_files_above_webroot_dir);
            }

            global $wpsynchro_container;
            $logger = $wpsynchro_container->get("class.Logger");
            $logger->log("DEBUG", "Section list after init:", $this->sections);

            $this->initialized = true;
        }
    }

    /**
     * Load file sync list
     * @since 1.0.3
     */
    public function loadFileSyncList()
    {
        if ($this->job->files_sync_list_disklocation == null) {
            // Setup initial data
            $this->setupInitialFileStructure();
            $this->saveFileSyncList();
        } else {
            // Load from disk
            $loaded_obj = unserialize(file_get_contents($this->job->files_sync_list_disklocation));
            $this->sections = $loaded_obj->sections;
            $this->initialized = $loaded_obj->initialized;
            $this->all_sections_populated = $loaded_obj->all_sections_populated;
            $this->all_sections_hashed = $loaded_obj->all_sections_hashed;
            $this->all_sections_path_handled = $loaded_obj->all_sections_path_handled;
            $this->all_target_processed = $loaded_obj->all_target_processed;
            $this->files_total_counter = $loaded_obj->files_total_counter;
            $this->files_total_size = $loaded_obj->files_total_size;
            $this->files_hashed_counter = $loaded_obj->files_hashed_counter;
            $this->files_completed_counter = $loaded_obj->files_completed_counter;
            $this->files_completed_size = $loaded_obj->files_completed_size;
        }
    }

    /**
     * Save file sync list
     * @since 1.0.3
     */
    public function saveFileSyncList()
    {

        if ($this->job->files_sync_list_disklocation == null) {
            // Save it for first time
            // Create upload dir if needed
            $upload_dir = wp_upload_dir();
            $tmp_wpsynchro = $upload_dir['basedir'] . '/wpsynchro';
            if (!file_exists($tmp_wpsynchro)) {
                wp_mkdir_p($tmp_wpsynchro);
            }
            // Delete existing .tmp files
            array_map(function($value) {
                @unlink($value);
            }, glob(trailingslashit($tmp_wpsynchro) . "*.tmp"));
            // Write file
            $filename = $tmp_wpsynchro . "/" . uniqid() . ".tmp";
            $filewrite = file_put_contents($filename, serialize($this));
            if ($filewrite === false) {
                $this->errors[] = sprint(__("Error during initialization for file synchronization - Could not write file to %s", "wpsynchro"), $filename);
            } else {
                $this->job->files_sync_list_disklocation = $filename;
            }
        } else {
            // Save it
            $filewrite = file_put_contents($this->job->files_sync_list_disklocation, serialize($this));
            if ($filewrite === false) {
                $this->errors[] = sprint(__("Error during file synchronization - Could not write file to %s", "wpsynchro"), $filename);
            }
        }
    }

    /**
     *  Update current state of this object
     *  @since 1.0.3
     */
    public function updateSectionState()
    {
        $this->files_hashed_counter = 0;

        foreach ($this->sections as &$section) {
      
            $all_hashed = true;
            foreach ($section->file_list as $file) {
                if ($file->hash === null) {
                    $all_hashed = false;
                    break;
                }
                $this->files_hashed_counter++;
            }

            if ($section->files_list_complete && $all_hashed) {
                $section->files_hashing_complete = true;
            }
        }

        $sections_populated = true;
        $sections_hashed = true;
        foreach ($this->sections as &$section) {
            if (!$section->files_list_complete) {
                $sections_populated = false;
            }
            if (!$section->files_hashing_complete) {
                $sections_hashed = false;
            }
        }
        $this->all_sections_populated = $sections_populated;
        $this->all_sections_hashed = $sections_hashed;

        // If all sections populated, then set total counter, if not set
        if ($this->all_sections_populated && $this->files_total_counter == null) {
            $this->files_total_counter = 0;
            foreach ($this->sections as &$section) {
                $this->files_total_counter += count($section->file_list);

                // Get total size of files
                foreach ($section->file_list as &$file) {
                    $this->files_total_size += $file->size;
                }
            }
        }
    }

    /**
     *  Target sync: Get a work chunk of the a section
     *  @since 1.0.3
     */
    public function getFileChunkForTargetSync($body)
    {

        $file_chunk = array();
        $file_chunk['files'] = array();
        $file_chunk['chunk_size'] = 0;

        // Max_post_size
        $max_post_size = $this->job->to_max_post_size * 0.7;
        // Max file count per request
        $max_file_count = $this->job->files_target_sync_throttle_max_file_count;
        $max_file_total_size = $this->job->files_target_sync_throttle_maxsize;

        // Counters
        $test_postsize_counter = 0;
        $currentsize = 0;

        // Scan for work in sections
        foreach ($this->sections as &$section) {
            foreach ($section->file_list as $key => &$file) {
                if ($file->target_synced == true) {
                    continue;
                }

                // File needs target sync
                $file_chunk['files'][$key] = $file;
                $file_chunk['files'][$key]->target_file = $file->target_file;
                $file_chunk['files'][$key]->target_tmp_file = $file->target_tmp_file;
                $currentsize += $file->size;
                $test_postsize_counter++;

                // Recalculate max files, based on current files
                if ($test_postsize_counter % 500 == 0) {
                    $body['work'] = $file_chunk;
                    $json_encoded_body = json_encode($body);
                    $request_length = strlen($json_encoded_body);

                    $requestsize_per_file = ceil($request_length / $test_postsize_counter);
                    $this->job->files_target_sync_throttle_max_file_count = $max_post_size / $requestsize_per_file;
                    if ($this->job->files_target_sync_throttle_max_file_count > 5000) {
                        $this->job->files_target_sync_throttle_max_file_count = 5000;
                    }
                }

                // Check if we have reached the max file count
                if ($test_postsize_counter >= $max_file_count) {
                    // It will 
                    break;
                }

                // Check if the file will make the current array too big
                if ($currentsize >= $max_file_total_size) {
                    // It will 
                    break;
                }
            }
            // There is files in this section, break of, so we only have one section in every chunk
            if (count($file_chunk['files']) > 0) {
                $file_chunk['type'] = $section->type;
                $file_chunk['temp_dirs'] = $section->basepath_translation;
                $file_chunk['files_temp_dirs_created'] = $section->files_temp_dirs_created;
                $file_chunk['chunk_size'] = $currentsize;
                break;
            }
        }

        if ($currentsize == 0) {
            $this->all_target_processed = true;
        }

        $body['work'] = $file_chunk;
        $json_encoded_body = json_encode($body);
        $request_length = strlen($json_encoded_body);

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("INFO", "Target work chunk with files count: " . count($file_chunk['files']) . " and size: " . $file_chunk['chunk_size'] . " and file size max: " . $max_file_total_size . " and request length: " . $request_length . " and entry request max file count: " . $max_file_count . " calculated to new value: " . $this->job->files_target_sync_throttle_max_file_count);

        return $json_encoded_body;
    }

    /**
     *  Target sync: Get a work chunk of the a section
     *  @since 1.0.3
     */
    public function updateFileChunkForTargetSync($file_chunk)
    {
        if (!isset($file_chunk['type'])) {
            return;
        }

        // Find section
        $chunk_section = null;
        foreach ($this->sections as &$section) {
            if ($section->type == $file_chunk['type']) {
                $chunk_section = $section;
                break;
            }
        }

        // Set the flag if temp dirs was created
        if (isset($chunk_section->files_temp_dirs_created)) {
            $chunk_section->files_temp_dirs_created = $file_chunk['files_temp_dirs_created'];
        }

        foreach ($file_chunk['files'] as $key => $file) {
            $chunk_section->file_list[$key] = $file;

            if ($file->completed == true) {
                $this->files_completed_counter++;
                $this->files_completed_size += $chunk_section->file_list[$key]->size;
            }
        }
    }

    /**
     *  File complete sync: Get the next file that needs moving
     *  @since 1.0.3
     */
    public function getFilesToMoveToTarget($max_size, $max_files)
    {
        $files = array();
        $file_counter = 0;
        $file_size_counter = 0;

        // Scan for work in sections
        foreach ($this->sections as &$section) {
            foreach ($section->file_list as $key => $file) {
                if ($file->completed == false) {
                    // File needs moving from source  
                    $file->section = $section->type;
                    $files[$key] = $file;
                    $file_size_counter += $file->size;
                    $file_counter++;
                    if ($file_size_counter >= $max_size) {
                        return $files;
                    }
                    if ($file_counter >= $max_files) {
                        return $files;
                    }
                }
            }
        }

        // Or if nothing left to do
        if (count($files) == 0) {
            $this->all_completed = true;
        }
        return $files;
    }

    /**
     *  File complete sync: Set file key to completed and count up the completed file size
     *  @since 1.0.3
     */
    public function setFileKeyToCompleted($section_type, $file_key, $partial = false, $partial_position = 0)
    {
        //error_log("Call completed with sectiontype:" . $section_type . " and key:" . $file_key . " and partial: " . ($partial ? "yes" : "no") . " and partialposition: " . $partial_position);
        // Find section
        $chunk_section = null;
        foreach ($this->sections as &$section) {
            if ($section->type == $section_type) {
                $chunk_section = $section;
                break;
            }
        }
        if (isset($chunk_section->file_list[$file_key]->completed)) {

            if ($partial) {
                $chunk_section->file_list[$file_key]->partial = true;
                if (!isset($chunk_section->file_list[$file_key]->partial_position)) {
                    $chunk_section->file_list[$file_key]->partial_position = 0;
                }

                $last_partial_position = $chunk_section->file_list[$file_key]->partial_position;

                $chunk_section->file_list[$file_key]->partial_position = $partial_position;

                $bytes_processed = $partial_position - $last_partial_position;
                $this->files_completed_size += $bytes_processed;

                if ($chunk_section->file_list[$file_key]->partial_position < $chunk_section->file_list[$file_key]->size) {
                    return;
                } else {
                    $chunk_section->file_list[$file_key]->completed = true;
                    $this->files_completed_counter++;
                    return;
                }
            }

            $chunk_section->file_list[$file_key]->completed = true;
            $this->files_completed_counter++;
            $this->files_completed_size += $chunk_section->file_list[$file_key]->size;
        }
    }

    /**
     *  Get progress part for file description - Just part with (File: X / Y - Size: Z / V)
     *  @since 1.0.3
     */
    public function getFileProgressDescriptionPart()
    {
        if (!$this->all_sections_hashed) {
            return sprintf(__("(File: %d / %d)","wpsynchro"), $this->files_hashed_counter, $this->files_total_counter);
        }

        $one_mb = 1024 * 1024;
        $completed_size = intval($this->files_completed_size);
        $total_size = intval($this->files_total_size);

        // Show in mb
        $completed_size = number_format($completed_size / $one_mb, 0, ",", ".") . " MB";
        $total_size = number_format($total_size / $one_mb, 0, ",", ".") . " MB";

        return sprintf(__("(File: %d / %d - Size: %s / %s)", "wpsynchro"), $this->files_completed_counter, $this->files_total_counter, $completed_size, $total_size);
    }
}

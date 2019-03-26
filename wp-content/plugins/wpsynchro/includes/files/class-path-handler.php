<?php
namespace WPSynchro\Files;

/**
 * Class for processing paths on file list (we want all paths calculated, with source and destination, which temp dirs to create and correct finalize renames)
 * @since 1.0.3
 */
class PathHandler
{

    // Data objects   
    public $sync_list = null;

    /**
     *  Constructor
     *  @since 1.0.3
     */
    public function __construct()
    {
        
    }

    /**
     *  Initialize class
     *  @since 1.0.3
     */
    public function init(\WPSynchro\Files\SyncList &$sync_list)
    {

        $this->sync_list = $sync_list;
    }

    /**
     * Path processing of file list 
     * @since 1.0.3
     */
    public function processFilelist($allotted_time)
    {
        $errors = array();
        $time_start = microtime(true);
        $allotted_time -= 1; // Just subtract a bit, but should not be a problem here unless ultra slow hosting

        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("DEBUG", "Begin path handling with " . count($this->sync_list->sections) . " sections and allotted time:" . $allotted_time);

        // Go through section
        foreach ($this->sync_list->sections as &$section) {
            if (!$section->files_path_handled) {

                // If section is uploads/media and it is set to inline, remove the tmp prefix and no finalize renames
                $use_tmp_prefix = true;
                $add_finalize_rename = true;
                if ($section->type == "uploads" && $section->extra['inline']) {
                    $use_tmp_prefix = false;
                    $add_finalize_rename = false;
                }

                // Determine temp dirs needed and finalize renames          
                $basepath_translation = array();
                foreach ($section->temp_dirs_in_basepath as $dir => $notused) {
                    // Base path translation to temp dir
                    if ($use_tmp_prefix) {
                        $basepath_translation[$dir] = trailingslashit($section->target_basepath) . $this->sync_list->tmp_prefix . $dir;
                    } 
                    
                    // Finalize renames
                    if ($add_finalize_rename) {
                        $rename = array();
                        $rename['from'] = trailingslashit($section->target_basepath) . $this->sync_list->tmp_prefix . $dir;
                        $rename['to'] = trailingslashit($section->target_basepath) . $dir;                        
                        $section->finalize_renames[] = $rename;
                    }
                }
                $section->basepath_translation = $basepath_translation;

                // Go through filelist and generate a absolute path on target, so target doesnt have to be that smart.. Which we know target is not... That smart.. Ahh whatever, back to code
                foreach ($section->file_list as &$file) {
                    $basepath = str_replace($section->source_basepath, "", $common->fixPath($file->source_file));
                    foreach ($section->basepath_translation as $pathpart => $path) {
                        if (strpos($basepath, $pathpart) === 1) {
                            $basepath_without_dirname = substr($basepath, (1 + strlen($pathpart)));
                            $file->target_tmp_file = $path . $basepath_without_dirname;
                            $file->target_file = $section->target_basepath . $basepath;
                            break;
                        }
                    }
                    // If none of the paths found, then it must be a file in the root of source_basepath
                    if (!isset($file->target_file)) {
                        // Just a file in root
                        $file->target_file = trailingslashit($section->target_basepath) . ltrim($basepath, "/\\");
                        if ($use_tmp_prefix) {
                            $file->target_tmp_file = trailingslashit($section->target_basepath) . $this->sync_list->tmp_prefix . ltrim($basepath, "/\\");
                        } else {
                            $file->target_tmp_file = $file->target_file;
                        }

                        // Make sure it is renamed on finalize
                        if ($add_finalize_rename) {
                            $rename = array();
                            $rename['from'] = $file->target_tmp_file;
                            $rename['to'] = trailingslashit($section->target_basepath) . ltrim($basepath, "/\\");                      
                            $section->finalize_renames[] = $rename;
                        }
                    }
                }

                $section->files_path_handled = true;
            }

            // Check time
            if ((microtime(true) - $time_start) >= $allotted_time) {
                return $errors;
            }
        }

        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("DEBUG", "Completed path handling in " . (microtime(true) - $time_start));    

        // if we get here, all is lovely, so we set the flag indicating that we completed this task
        $this->sync_list->all_sections_path_handled = true;

        return $errors;
    }
}

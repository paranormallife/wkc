<?php
namespace WPSynchro;

/**
 * Class for handling a "sync installation"
 *
 * @since 1.0.0
 */
class Installation
{

    public $id = '';
    public $name = '';
    // Type
    public $type = '';
    // From
    public $site_url = '';
    public $access_key = '';
    public $valid_endpoint = false;
    // General settings    
    public $verify_ssl = true;
    // Data to sync
    public $sync_database = false;
    public $sync_files = false;
    /*
     * Database
     */
    public $db_preserve_wpsynchro = true;
    public $db_preserve_activeplugins = true;
    // Exclusions DB
    public $include_all_database_tables = true;
    public $only_include_database_table_names = [];
    // Search / replaces in db
    public $searchreplaces = [];

    /*
     *  Files
     */
    public $files_wordpress_core = false;
    public $files_wordpress_wpcontent_plugins = false;
    public $files_wordpress_wpcontent_themes = false;
    public $files_wordpress_wpcontent_uploads = false;
    public $files_exclude_files_match = "wp-config.php,node_modules";
    public $files_include_relative_webroot = "";
    public $files_include_relative_wpcontent = "";
    public $files_include_relative_abovewebroot = "";
    // Media
    public $files_media_in_current_dir = true;
    // Plugins
    public $files_include_all_plugins = true;
    public $files_only_include_plugins = [];
    // Themes
    public $files_include_all_themes = true;
    public $files_only_include_themes = [];

    /*
     * Errors
     */
    public $validate_errors = [];

    // Constants
    const SYNC_TYPES = ['pull', 'push'];

    public function __construct()
    {
        
    }

    public function getOverviewDescription()
    {
        $desc = __("Synchronize", "wpsynchro") . " ";
        // Type
        if ($this->type == 'push') {
            $desc .= sprintf(__("<b>from this installation</b> to <b>%s</b> ", "wpsynchro"), $this->site_url) . " ";
        } else {
            $desc .= sprintf(__("<b>from %s</b> to <b>this installation</b>", "wpsynchro"), $this->site_url) . " ";
        }
        // What to sync
        if ($this->sync_database || $this->sync_files) {
            $desc .= __("and synchronizes ", "wpsynchro") . " ";
            $syncs = array();
            if ($this->sync_database) {
                $syncs[] = __('<b>database</b>', 'wpsynchro');
            }
            if ($this->sync_files) {
                $syncs[] = __('<b>files</b>', 'wpsynchro');
            }
            if (empty($syncs)) {
                
            } else {
                $desc .= implode(" " . __('and', 'wpsynchro') . " ", $syncs);
            }
        } else {
            $desc .= __("and synchronizes ", "wpsynchro") . " <b>" . __('nothing', 'wpsynchro') . "</b>";
        }
        $desc .= ". ";

        if (!$this->verify_ssl) {
            $desc .= "<br> - " . __("Self-signed and non-valid SSL certificates allowed", "wpsynchro");
        }

        if ($this->sync_database) {
            $desc .= "<br> - ";
            if ($this->include_all_database_tables) {
                $desc .= __("Database: All database tables will be migrated", "wpsynchro");
            } else {
                $desc .= sprintf(__("Database: Will migrate %d selected tables. ", "wpsynchro"), count($this->only_include_database_table_names));
            }
        }

        if ($this->sync_files) {

            if ($this->files_wordpress_core) {
                $desc .= "<br> - " . __("Files: WordPress core with be migrated", "wpsynchro");
            }
            if ($this->files_wordpress_wpcontent_themes) {
                if ($this->files_include_all_themes) {
                    $desc .= "<br> - " . __("Files: All themes with be migrated", "wpsynchro");
                } else {
                    $desc .= "<br> - " . sprintf(__("Files: %d themes with be migrated", "wpsynchro"), count($this->files_only_include_themes));
                }
            }
            if ($this->files_wordpress_wpcontent_plugins) {
                if ($this->files_include_all_plugins) {
                    $desc .= "<br> - " . __("Files: All plugins with be migrated", "wpsynchro");
                } else {
                    $desc .= "<br> - " . sprintf(__("Files: %d plugins with be migrated", "wpsynchro"), count($this->files_only_include_plugins));
                }
            }
            if ($this->files_wordpress_wpcontent_uploads) {
                if ($this->files_media_in_current_dir) {
                    $desc .= "<br> - " . __("Files: Uploads/media with be migrated in the current directory", "wpsynchro");
                } else {
                    $desc .= "<br> - " . __("Files: Uploads/media with be migrated", "wpsynchro");
                }
            }
            if (strlen($this->files_include_relative_webroot) > 0 || strlen($this->files_include_relative_wpcontent) > 0 || strlen($this->files_include_relative_abovewebroot) > 0) {
                $desc .= "<br> - " . __("Files: Additional files will be migrated", "wpsynchro");
            }
        }

        // check for errors
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            $desc .= "<br><br>";
            foreach ($errors as $error) {
                $desc .= "<b style='color:red;'>" . $error . "</b><br>";
            }
        }


        return $desc;
    }
    /*
     *  Check for errors, also taking pro/free into account
     */

    public function checkErrors()
    {
        $errors = array();
        if (!\WPSynchro\WPSynchro::isPremiumVersion()) {
            if ($this->sync_files == true) {
                $errors[] = __("Files are not allowed to be migrated using FREE version - Upgrade to PRO", "wpsynchro");
            }
        }

        return $errors;
    }

    /**
     *  Check if installation can run, taking PRO/FREE and functionalities into account
     */
    public function canRun()
    {
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            return false;
        } else {
            return true;
        }
    }
}

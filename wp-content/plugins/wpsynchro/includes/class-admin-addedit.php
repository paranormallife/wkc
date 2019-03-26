<?php
namespace WPSynchro;

/**
 * Class for handling what to show when adding or editing a installation in wp-admin
 * @since 1.0.0
 */
class AdminAddEdit
{

    public static function render()
    {
        $instance = new self;
        // Handle post
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $instance->handlePOST();
        }
        $instance->handleGET();
    }

    private function handleGET()
    {

        // Check php/wp/mysql versions
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $compat_errors = $commonfunctions->checkEnvCompatability();

        // Set the id
        if (isset($_REQUEST['syncid'])) {
            $id = sanitize_text_field($_REQUEST['syncid']);
        } else {
            $id = '';
        }

        // Get the data
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        $installation = $inst_factory->retrieveInstallation($id);

        if ($installation == false) {
            $installation = $wpsynchro_container->get('class.Installation');
        }

        // Reset valid endpoint
        $installation->valid_endpoint = false;

        // Localize the script with data
        $adminjsdata = array(
            'instance' => $installation,
            'rest_root' => esc_url_raw(rest_url()),
            'rest_accesskey' => $commonfunctions->getAccessKey(),
            'text_error_gettoken' => __("Could not get token from remote server - Is WP Synchro installed and activated?", "wpsynchro"),
            'text_error_401403' => __("Could not get data from remote service (401/403) - Check access key and website url", "wpsynchro"),
            'text_error_401403_push' => __("Could not get data from local service (401/403) - Maybe something is blocking?", "wpsynchro"),
            'text_error_other' => __("Could not get data from remote service ({0}) - Check access key and website url", "wpsynchro"),
            'text_error_other_push' => __("Could not get data from local service ({0})", "wpsynchro"),
            'text_error_request' => __("No proper response from remote server - Check that website is correct and WP Synchro is activated", "wpsynchro"),
            'text_error_request_push' => __("No proper response from local server - Check that nothing is blocking calls to this server", "wpsynchro"),
            'text_initiate_error_default' => __("Unknown error - Maybe this helps:", "wpsynchro"),
            'text_valid_endpoint_error_no_transfer_token' => __("No proper transfer token to use for safe communication - Try with another browser. Eg. newest Chrome.", "wpsynchro"),
            'text_valid_endpoint_could_not_connect' => __("Could not connect to remote service - Check access key, website url and WP Synchro is activated", "wpsynchro"),
            'text_get_dbtables_error' => __("Could not grab the database tables names from remote", "wpsynchro"),
            'text_get_filedetails_error' => __("Could not grab the file data from remote", "wpsynchro"),
            'text_validate_name_error' => __("Name should be filled out", "wpsynchro"),
            'text_validate_type_error' => __("Type (pull/push) must be chosen", "wpsynchro"),
            'text_validate_endpoint_error' => __("Website or access key is not valid", "wpsynchro"),
        );
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_addedit', $adminjsdata);

        ?>
        <div id="wpsynchro-addedit" class="wrap wpsynchro"  v-cloak>
            <h2>WP Synchro <?php echo ( \WPSynchro\WPSynchro::isPremiumVersion() ? 'PRO' : 'FREE' ); ?> - <?php ( $id > 0 ? _e('Edit installation', 'wpsynchro') : _e('Add installation', 'wpsynchro') ); ?></h2>

            <?php
            if (count($compat_errors) > 0) {
                foreach ($compat_errors as $error) {
                    echo "<b>" . $error . "</b><br>";
                }
                echo "</div>";
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo "<div class='notice notice-success'><p>" . __('Installation is now saved', 'wpsynchro') . " - <a href='" . menu_page_url("wpsynchro_overview", false) . "'>" . __('Go back to overview', 'wpsynchro') . "</a></p></div>";
            } else if (isset($_REQUEST['created'])) {
                echo "<div class='notice notice-success'><p>" . __('Installation is now created', 'wpsynchro') . " - <a href='" . menu_page_url("wpsynchro_overview", false) . "'>" . __('Go back to overview', 'wpsynchro') . "</a></p></div>";
            }

            echo "<p>" . __('Fill out the details of the installation to be synced to chosen location.', 'wpsynchro') . "</p>";

            ?>

            <div>
                <form id="wpsynchro-addedit-form" method="POST" >
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

                    <div class="generalsetup">
                        <div class="sectionheader"><?php _e('General setup', 'wpsynchro'); ?></div>

                        <h3><?php _e('Choose a name', 'wpsynchro'); ?></h3>
                        <div class="option">
                            <div class="optionname">
                                <label for="name"><?php _e('Name', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <input v-model="inst.name" type="text" name="name" id="name" value="" required>
                            </div>
                        </div>

                        <h3><?php _e('Type of synchronization', 'wpsynchro'); ?></h3>
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Push or pull', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.type" type="radio" name="type" value="pull" v-on:click="inst.valid_endpoint = false" /> <?php _e('Pull from remote server to this installation ', 'wpsynchro'); ?></label><br>
                                <label><input v-model="inst.type" type="radio" name="type" value="push" v-on:click="inst.valid_endpoint = false" /> <?php _e('Push this installation to remote server', 'wpsynchro'); ?></label>                                
                            </div>
                        </div>

                        <div v-if="inst.type.length > 0">
                            <h3 v-if="inst.type == 'pull'"><?php _e('Where to pull from', 'wpsynchro'); ?></h3>
                            <h3 v-if="inst.type == 'push'"><?php _e('Where to push to', 'wpsynchro'); ?></h3>  

                            <div class="option">   
                                <div class="optionname">
                                    <label for="website"><?php _e('Website (full url)', 'wpsynchro'); ?></label>
                                </div>
                                <div class="optionvalue">
                                    <input v-model="inst.site_url" :readonly="inst.valid_endpoint" type="text" name="website" id="website" value="" placeholder="https://example.com" required> <span v-if="inst.valid_endpoint" class="validstate">&#10003;</span>
                                </div>
                            </div>
                            <div class="option"> 
                                <div class="optionname">
                                    <label for="accesskey"><?php _e('Access key', 'wpsynchro'); ?></label>
                                </div>
                                <div class="optionvalue">
                                    <input v-model="inst.access_key" :readonly="inst.valid_endpoint" type="text" name="accesskey" id="accesskey" value="" required /> <span v-if="inst.valid_endpoint" class="validstate">&#10003;</span>                                                                      
                                </div>                            
                            </div>
                            <p v-if="site_url_is_insecure" class="sslwarning"><b><?php _e('Beware that the website supplied is not SSL protected and data will NOT be encrypted on transfer!', 'wpsynchro'); ?></b><br><?php _e('Consider using https:// instead if website supports it.', 'wpsynchro'); ?></p>
                            <div class="errors">
                                <ul>
                                    <li v-for="(errormessage, index) in inst.valid_endpoint_errors">{{errormessage}}</li>
                                </ul>
                            </div>
                            <button v-if="!inst.valid_endpoint" v-on:click="initiateVerification"><?php _e('Verify website and access key', 'wpsynchro'); ?></button><div v-show="inst.valid_endpoint_spinner" class="spinner"></div>
                        </div>
                    </div>

                    <div class="generalsettings" v-if="inst.valid_endpoint">
                        <div class="sectionheader"><?php _e('General settings', 'wpsynchro'); ?></div>
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Verify SSL', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.verify_ssl" type="checkbox" name="verify_ssl" id="verify_ssl"  /> <?php _e('Verify SSL certificates - Should be turned off for self-signed certificates', 'wpsynchro'); ?></label><br>

                            </div>
                        </div>                        
                    </div>

                    <div class="datatosync" v-if="inst.valid_endpoint">
                        <div class="sectionheader"><?php _e('Data to synchronize', 'wpsynchro'); ?></div>
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Data to synchronize', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.sync_database" type="checkbox" name="sync_database" id="sync_database" checked="checked" /> <?php _e('Synchronize database', 'wpsynchro'); ?></label><br>
                                <label><input v-model="inst.sync_files" type="checkbox" name="sync_files" id="sync_files"  /> <?php _e('Synchronize files', 'wpsynchro'); ?> <?php echo (!\WPSynchro\WPSynchro::isPremiumVersion() ? "<b>" . __('(PRO version only)', 'wpsynchro') . "</b>" : "" ) ?></label>
                            </div>
                        </div>                        
                    </div>

                    <div class="dbsyncsetup" v-show="inst.valid_endpoint && inst.sync_database">
                        <div class="sectionheader"><?php _e('Database synchronization', 'wpsynchro'); ?></div>
                        <h3><?php _e('Database search/replace', 'wpsynchro'); ?></h3>
                        <p><?php _e('Normally you would want atleast one search/replace, from the old hostname to new hostname.', 'wpsynchro'); ?><br><?php _e('Eg. "https://wpsynchro.com" to "http://wpsynchro.test"', 'wpsynchro'); ?><br><?php _e('Search/replace is done in a case sensitive manner', 'wpsynchro'); ?></p>


                        <div class="searchreplaces" >
                            <div class="searchreplaceheadlines">
                                <div><?php _e('Search', 'wpsynchro'); ?></div>
                                <div><?php _e('Replace', 'wpsynchro'); ?></div>
                            </div>

                            <draggable v-model="inst.searchreplaces"  :options="{handle:'.handle'}">

                                <div class="searchreplace" v-for="(replace, key) in inst.searchreplaces">
                                    <div class="handle dashicons dashicons-move"></div>
                                    <div><input v-model="replace.from" type="text" name="searchreplaces_from[]" /></div>
                                    <div><input v-model="replace.to" type="text" name="searchreplaces_to[]" /></div>
                                    <div v-on:click="$delete(inst.searchreplaces, key)" class="deletereplace dashicons dashicons-trash"></div>
                                </div>
                            </draggable>
                        </div>


                        <button class="addsearchreplace" v-on:click="addSearchReplace()"  type="button"><?php _e('Add replace', 'wpsynchro'); ?></button>

                        <h3><?php _e('Preserve data after synchronization', 'wpsynchro'); ?></h3>                        
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('WPSynchro settings', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.db_preserve_wpsynchro" type="checkbox" name="db_preserve_wpsynchro" id="db_preserve_wpsynchro" checked="checked" /> <?php _e('Preserve WPSynchro settings', 'wpsynchro'); ?> (<?php _e('Recommended', 'wpsynchro'); ?>)</label>
                            </div>
                        </div>
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Active plugins', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.db_preserve_activeplugins" type="checkbox" name="db_preserve_activeplugins" id="db_preserve_activeplugins" checked="checked" /> <?php _e('Preserve active plugins settings', 'wpsynchro'); ?> (<?php _e('Recommended', 'wpsynchro'); ?>)</label>
                            </div>
                        </div>

                        <h3><?php _e('Tables to synchronize', 'wpsynchro'); ?></h3>
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Database tables', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.include_all_database_tables" type="checkbox" name="include_all_database_tables" id="include_all_database_tables" checked="checked" /> <?php _e('Synchronize all database tables', 'wpsynchro'); ?></label><br>
                                <select v-model="inst.only_include_database_table_names" v-if="! inst.include_all_database_tables" id="exclude_db_tables_select" name="only_include_database_table_names[]" multiple>
                                    <option v-for="option in database_info.db_client_tables" v-bind:value="option">
                                        {{ option }}
                                    </option>
                                </select>                             
                            </div>
                        </div>


                    </div>

                    <div class="filessyncsetup" v-show="inst.valid_endpoint && inst.sync_files">
                        <div class="sectionheader"><?php _e('Files synchronization', 'wpsynchro'); ?> <?php echo (!\WPSynchro\WPSynchro::isPremiumVersion() ? "<b>" . __('(PRO version only)', 'wpsynchro') . "</b>" : "" ) ?></div>

                        <h3><?php _e('File migrate mode', 'wpsynchro'); ?></h3>
                        <p><?php _e('Files from source will be compared to those on target before transfer.<br>If files doesnt exist on target or is different, they will be transferred. Files on target that dont exist on source will be deleted.', 'wpsynchro'); ?></p>

                        <h3><?php _e('Files to synchronize', 'wpsynchro'); ?></h3>

                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Choose files to synchronize', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.files_wordpress_core" type="checkbox" name="files_wordpress_core" id="files_wordpress_core" checked="checked" /> <?php _e('WordPress core (wp-includes and wp-admin)', 'wpsynchro'); ?></label><br>                                
                                <label><input v-model="inst.files_wordpress_wpcontent_plugins" v-on:click="inst.files_include_all_plugins = true" type="checkbox" name="files_wordpress_wpcontent_plugins" id="files_wordpress_wpcontent_plugins" checked="checked" /> <?php _e('Plugins (in wp-content)', 'wpsynchro'); ?></label><br>
                                <label><input v-model="inst.files_wordpress_wpcontent_themes" v-on:click="inst.files_include_all_themes = true" type="checkbox" name="files_wordpress_wpcontent_themes" id="files_wordpress_wpcontent_themes" checked="checked" /> <?php _e('Themes (in wp-content)', 'wpsynchro'); ?></label><br>
                                <label><input v-model="inst.files_wordpress_wpcontent_uploads" v-on:click="inst.files_media_in_current_dir = true" type="checkbox" name="files_wordpress_wpcontent_uploads" id="files_wordpress_wpcontent_uploads" checked="checked" /> <?php _e('Uploads (aka media) (in wp-content)', 'wpsynchro'); ?></label>
                            </div>
                        </div>

                        <div v-show="inst.files_wordpress_wpcontent_plugins">
                            <h3><?php _e('Plugins to synchronize', 'wpsynchro'); ?></h3> 
                            <div class="option">
                                <div class="optionname">
                                    <label><?php _e('Plugins', 'wpsynchro'); ?></label>
                                </div>
                                <div class="optionvalue">
                                    <label><input v-model="inst.files_include_all_plugins" type="checkbox" name="files_include_all_plugins" id="files_include_all_plugins" checked="checked" /> <?php _e('Synchronize all plugins', 'wpsynchro'); ?></label><br>
                                    <select v-model="inst.files_only_include_plugins" v-if="! inst.files_include_all_plugins" id="files_only_include_plugins" name="files_only_include_plugins[]" multiple>
                                        <option v-for="option in files_dirs.plugins" v-bind:value="option['slug']">
                                            {{ option['name'] }}
                                        </option>
                                    </select>                             
                                </div>
                            </div>
                        </div>

                        <div v-show="inst.files_wordpress_wpcontent_themes">
                            <h3><?php _e('Themes to synchronize', 'wpsynchro'); ?></h3>                           
                            <div class="option">
                                <div class="optionname">
                                    <label><?php _e('Themes', 'wpsynchro'); ?></label>
                                </div>
                                <div class="optionvalue">
                                    <label><input v-model="inst.files_include_all_themes" type="checkbox" name="files_include_all_themes" id="files_include_all_themes" checked="checked" /> <?php _e('Synchronize all themes', 'wpsynchro'); ?></label><br>
                                    <select v-model="inst.files_only_include_themes" v-if="! inst.files_include_all_themes" id="files_only_include_themes" name="files_only_include_themes[]" multiple>
                                        <option v-for="option in files_dirs.themes" v-bind:value="option['slug']">
                                            {{ option['name'] }}
                                        </option>
                                    </select>                             
                                </div>
                            </div>
                        </div>

                        <div v-show="inst.files_wordpress_wpcontent_uploads">
                            <h3><?php _e('Synchronize uploads/media in current directory', 'wpsynchro'); ?></h3>
                            <p><?php _e('Synchronize directly in the current uploads folder, to prevent copying existing files to a temporary location.', 'wpsynchro'); ?></p>                                                    
                            <div class="option">
                                <div class="optionname">
                                    <label><?php _e('Uploads/media', 'wpsynchro'); ?></label>
                                </div>
                                <div class="optionvalue">
                                    <label><input v-model="inst.files_media_in_current_dir" type="checkbox" name="files_media_in_current_dir" id="files_media_in_current_dir" checked="checked" /> <?php _e('Synchronize in current directory', 'wpsynchro'); ?></label>
                                </div>
                            </div>
                        </div>

                        <h3><?php _e('Additional files or directories to synchronize', 'wpsynchro'); ?></h3>
                        <p>Separate filenames/directories by comma. Ex: wp-config.php,.htaccess,favicon.ico,my-backupdir</p>

                        <div class="option nocenter">
                            <div class="optionname">
                                <label><?php _e('One folder above web root', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.files_include_relative_abovewebroot" type="text" name="include_relative_abovewebroot" id="include_relative_abovewebroot" /></label><br>
                                <small>Relative to: <code>{{ files_dirs.abovewebroot }}</code></small><br>
                                <small><?php _e('Ex. used if you have configuration outside web root, like Roots Bedrock', 'wpsynchro'); ?></small>
                            </div>
                        </div> 
                        <div class="option nocenter">
                            <div class="optionname">
                                <label><?php _e('Relative to webroot', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.files_include_relative_webroot" type="text" name="include_relative_webroot" id="include_relative_webroot" /></label><br>
                                <small>Relative to: <code>{{ files_dirs.webroot }}</code></small>
                            </div>
                        </div>    
                        <div class="option nocenter">
                            <div class="optionname">
                                <label><?php _e('Relative to wp-content', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.files_include_relative_wpcontent" type="text" name="include_relative_wpcontent" id="include_relative_wpcontent" /></label><br>
                                <small>Relative to: <code>{{ files_dirs.wpcontent }}</code></small>
                            </div>
                        </div> 

                        <h3><?php _e('Exclusions', 'wpsynchro'); ?></h3>
                        <p>Exclude files or dirs, separated by comma. Ex: wp-config.php,.htaccess,favicon.ico,my-secret-dir</p>    
                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Exclusions', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.files_exclude_files_match" type="text" name="files_exclude_files_match" id="files_exclude_files_match" /></label>                       
                            </div>
                        </div>

                    </div>

                    <div class="savesetup" v-if="inst.valid_endpoint">
                        <div class="sectionheader"><?php _e('Save installation', 'wpsynchro'); ?></div>
                        <p>
                            <input type="submit" v-if="inst.valid_endpoint" value="<?php _e('Save', 'wpsynchro'); ?>" />
                        </p>
                    </div>

                </form>
            </div>


        </div>
        <?php
    }

    private function handlePOST()
    {
        global $wpsynchro_container;
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        $installation = $wpsynchro_container->get('class.Installation');
        $newly_created = false;

        if (strlen($_POST['id']) > 0) {
            // Existing installation
            $installation->id = $_POST['id'];
        } else {
            // New installation
            $installation->id = uniqid();
            $newly_created = true;
        }
        if (isset($_POST['name'])) {
            $installation->name = sanitize_text_field(trim($_POST['name']));
        } else {
            $installation->name = '';
        }
        if (isset($_POST['type'])) {
            $installation->type = sanitize_text_field($_POST['type']);
        } else {
            $installation->type = '';
        }
        if (isset($_POST['website'])) {
            $installation->site_url = sanitize_text_field(trim($_POST['website'], ',/\\ '));
        } else {
            $installation->site_url = '';
        }
        if (isset($_POST['accesskey'])) {
            $installation->access_key = sanitize_text_field(trim($_POST['accesskey']));
        } else {
            $installation->access_key = '';
        }

        /*
         *  General settings
         */
        $installation->verify_ssl = ( isset($_POST['verify_ssl']) ? true : false );

        /**
         *  Installation sync
         */
        $installation->sync_database = ( isset($_POST['sync_database']) ? true : false );
        $installation->sync_files = ( isset($_POST['sync_files']) ? true : false );

        /*
         * Database save
         */
        $installation->include_all_database_tables = ( isset($_POST['include_all_database_tables']) ? true : false );
        $installation->only_include_database_table_names = ( isset($_POST['only_include_database_table_names']) ? $_POST['only_include_database_table_names'] : array() );
        $installation->db_preserve_wpsynchro = ( isset($_POST['db_preserve_wpsynchro']) ? true : false );
        $installation->db_preserve_activeplugins = ( isset($_POST['db_preserve_activeplugins']) ? true : false );

        if (isset($_POST['searchreplaces_from'])) {
            $searchreplaces_from = $_POST['searchreplaces_from'];
        } else {
            $searchreplaces_from = array();
        }
        if (isset($_POST['searchreplaces_to'])) {
            $searchreplaces_to = $_POST['searchreplaces_to'];
        } else {
            $searchreplaces_to = array();
        }

        $searchreplaces = array();
        for ($i = 0; $i < count($searchreplaces_from); $i++) {
            if (strlen($searchreplaces_from[$i]) > 0 && strlen($searchreplaces_to[$i]) > 0) {
                $tmp_obj = new \stdClass();
                $tmp_obj->to = sanitize_text_field($searchreplaces_to[$i]);
                $tmp_obj->from = sanitize_text_field($searchreplaces_from[$i]);
                $searchreplaces[] = $tmp_obj;
            }
        }
        $installation->searchreplaces = $searchreplaces;

        /*
         * Files save
         */

        $installation->files_wordpress_core = ( isset($_POST['files_wordpress_core']) ? true : false );
        $installation->files_wordpress_wpcontent_plugins = ( isset($_POST['files_wordpress_wpcontent_plugins']) ? true : false );
        $installation->files_wordpress_wpcontent_themes = ( isset($_POST['files_wordpress_wpcontent_themes']) ? true : false );
        $installation->files_wordpress_wpcontent_uploads = ( isset($_POST['files_wordpress_wpcontent_uploads']) ? true : false );

        if (isset($_POST['files_exclude_files_match'])) {
            $installation->files_exclude_files_match = sanitize_text_field($_POST['files_exclude_files_match']);
        } else {
            $installation->files_exclude_files_match = '';
        }
        if (isset($_POST['include_relative_webroot'])) {
            $installation->files_include_relative_webroot = sanitize_text_field($_POST['include_relative_webroot']);
        } else {
            $installation->files_include_relative_webroot = '';
        }
        if (isset($_POST['include_relative_wpcontent'])) {
            $installation->files_include_relative_wpcontent = sanitize_text_field($_POST['include_relative_wpcontent']);
        } else {
            $installation->files_include_relative_wpcontent = '';
        }
        if (isset($_POST['include_relative_abovewebroot'])) {
            $installation->files_include_relative_abovewebroot = sanitize_text_field($_POST['include_relative_abovewebroot']);
        } else {
            $installation->files_include_relative_abovewebroot = '';
        }

        // Files - Media 
        $installation->files_media_in_current_dir = ( isset($_POST['files_media_in_current_dir']) ? true : false );

        // Files - Plugins
        $installation->files_include_all_plugins = ( isset($_POST['files_include_all_plugins']) ? true : false );
        $installation->files_only_include_plugins = ( isset($_POST['files_only_include_plugins']) ? $_POST['files_only_include_plugins'] : array() );

        // Files - Themes
        $installation->files_include_all_themes = ( isset($_POST['files_include_all_themes']) ? true : false );
        $installation->files_only_include_themes = ( isset($_POST['files_only_include_themes']) ? $_POST['files_only_include_themes'] : array() );

        $inst_factory->addInstallation($installation);

        if ($newly_created) {
            $redirurl = add_query_arg('syncid', $installation->id, menu_page_url('wpsynchro_addedit', false));
            $redirurl = add_query_arg('created', '1', $redirurl);
            echo "<script>window.location.replace('" . $redirurl . "');</script>";
        }
    }
}

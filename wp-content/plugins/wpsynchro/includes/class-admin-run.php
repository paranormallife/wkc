<?php
namespace WPSynchro;

/**
 * Class for handling when running a sync
 *
 * @since 1.0.0
 */
class AdminRunSync
{

    public static function render()
    {

        $instance = new self;
        $instance->handleGET();
    }

    private function handleGET()
    {
        global $wpsynchro_container;

        if (isset($_REQUEST['syncid'])) {
            $id = $_REQUEST['syncid'];
        } else {
            $id = "";
        }
        if (isset($_REQUEST['jobid'])) {
            $jobid = $_REQUEST['jobid'];
        } else {
            $jobid = "";
        }

        if (strlen($id) < 1) {
            echo "<div class='notice'><p>" . __('No syncid provided - This should not happen', 'wpsynchro') . '</p></div>';
            return;
        }

        if (strlen($jobid) < 1) {
            echo "<div class='notice'><p>" . __('No jobid provided - This should not happen', 'wpsynchro') . '</p></div>';
            return;
        }

        // Create new job with this sync
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        $jobid = $inst_factory->startInstallationSync($id, $jobid);
        if ($jobid == null) {
            echo "<div class='notice'><p>" . __('Installation not found - This should not happen', 'wpsynchro') . '</p></div>';
            return;
        }
        $installation = $inst_factory->retrieveInstallation($id);

        // Localize the script with data
        $adminjsdata = array(
            'id' => $id,
            'jobid' => $jobid,
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'rest_root' => esc_url_raw(rest_url()),
            'text_ajax_response_error' => __("Could not get data from local service ({0})", "wpsynchro"),
            'text_ajax_request_error' => __("No proper response from local server - Maybe REST service is blocked? This can also be a temporary issue, if the host has issues. Please try again", "wpsynchro"),
            'text_ajax_default_error' => __("Unknown error - Maybe this helps:", "wpsynchro"),
        );
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_run', $adminjsdata);
        $stagecounter = 1;

        ?>
        <div id="wpsynchro-run-sync" class="wrap wpsynchro" v-cloak>
            <h2>WP Synchro <?php echo ( \WPSynchro\WPSynchro::isPremiumVersion() ? 'PRO' : 'FREE' ); ?> - <?php _e('Run synchronization', 'wpsynchro'); ?> <div v-if="overall_spinner" class="spinner"></div></h2>

            <div v-if="!is_completed" class="notice">
                <p><?php _e('Do not navigate away from this page until synchronization is completed - It may break your installation', 'wpsynchro'); ?></p>
            </div>

            <div v-if="is_completed && sync_errors.length == 0"  class="synccompleted">
                <div class="iconpart">&#10003;</div>   
                <div>
                    <p><?php _e('Synchronization completed', 'wpsynchro'); ?></p>
                </div>
            </div>

            <div v-if="sync_errors.length > 0 " class="syncerrors">
                <div class="iconpart">&#9940;</div>   
                <div>
                    <p><b>{{ sync_errors.length }} <?php echo strtoupper(__('Error(s) during synchronization:', 'wpsynchro')); ?></b></p>
                    <ul>
                        <li v-for="error in sync_errors">{{error}}</li>
                    </ul>    
                </div>
            </div>

            <div v-if="sync_warnings.length > 0" class="syncwarnings">
                <div class="iconpart">&#9888;</div>           
                <div>
                    <p><b>{{ sync_warnings.length }} <?php _e('WARNING(S) (synchronization will continue):', 'wpsynchro'); ?></b></p>
                    <ul>
                        <li v-for="warning in sync_warnings">{{warning}}</li>
                    </ul>
                </div>
            </div>
            
            <div class="">
                <p><?php _e('Time elapsed', 'wpsynchro'); ?>: <span>{{ time_from_start.hours }}</span> <?php _e('Hours', 'wpsynchro'); ?> <span>{{ time_from_start.minutes }}</span> <?php _e('Minutes', 'wpsynchro'); ?> <span>{{ time_from_start.seconds }}</span> <?php _e('Seconds', 'wpsynchro'); ?></p>
            </div>

            <h3><?php _e('Stage', 'wpsynchro'); ?> <?php echo $stagecounter++; ?> - <?php _e('Getting metadata', 'wpsynchro'); ?> ({{ metadata_progress }}%)</h3>
            <b-progress v-bind:value="metadata_progress" v-bind:max="100" ></b-progress>

            <?php
            if ($installation->sync_database) {

                ?>
                <h3><?php _e('Stage', 'wpsynchro'); ?> <?php echo $stagecounter++; ?> - <?php echo ($installation->type == 'pull' ? __('Retrieving database', 'wpsynchro') : __('Sending database', 'wpsynchro')); ?> ({{ database_progress }}%) <span class="stagedata" v-if="database_progress_description.length > 0 && database_progress > 0 && database_progress < 100">- {{ database_progress_description  }}</span></h3>
                <b-progress v-bind:value="database_progress" v-bind:max="100" ></b-progress>
                <?php
            }

            if ($installation->sync_files) {

                ?>
                <h3><?php _e('Stage', 'wpsynchro'); ?> <?php echo $stagecounter++; ?> - <?php echo ($installation->type == 'pull' ? __('Retrieving files', 'wpsynchro') : __('Sending files', 'wpsynchro')); ?> ({{ files_progress }}%) <span class="stagedata" v-if="files_progress_description.length > 0 && files_progress < 100">- {{ files_progress_description  }}</span></h3>
                <b-progress v-bind:value="files_progress" v-bind:max="100" ></b-progress>   
                <?php
            }

            ?>

            <h3><?php _e('Stage', 'wpsynchro'); ?> <?php echo $stagecounter++; ?> - <?php _e('Finalizing', 'wpsynchro'); ?> ({{ finalize_progress }}%)</h3>
            <b-progress v-bind:value="finalize_progress" v-bind:max="100" ></b-progress>


        </div>
        <?php
    }
}

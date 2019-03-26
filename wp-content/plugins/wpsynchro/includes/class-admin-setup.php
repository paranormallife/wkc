<?php
namespace WPSynchro;

/**
 * Class for handling what to show when clicking on setup in the menu in wp-admin
 *
 * @since 1.0.0
 */
class AdminSetup
{

    private $show_update_settings_notice = false;
    private $notices = array();

    /**
     *  Called from WP menu to show setup
     *  @since 1.0.0
     */
    public static function render()
    {
        $instance = new self;
        // Handle post
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $instance->handlePOST();
        }
        $instance->handleGET();
    }

    /**
     *  Handle the update of data from setup screen
     *  @since 1.0.0
     */
    private function handlePOST()
    {

        $this->show_update_settings_notice = true;


        // Save access key  
        if (isset($_POST['accesskey'])) {
            $accesskey = sanitize_key($_POST['accesskey']);
        } else {
            $accesskey = "";
        }

        if (strlen($accesskey) > 30) {
            update_option('wpsynchro_accesskey', $accesskey, false);
        }

        // Save methods allowed
        $pull_allowed = ( isset($_POST['allow_pull']) ? true : false );
        $push_allowed = ( isset($_POST['allow_push']) ? true : false );
        $methodsallowed = new \stdClass();
        $methodsallowed->pull = $pull_allowed;
        $methodsallowed->push = $push_allowed;
        update_option('wpsynchro_allowed_methods', $methodsallowed, false);

        // MU plugin enabled
        $mu_plugin_enable = ( isset($_POST['enable_muplugin']) ? true : false );
        global $wpsynchro_container;
        $muplugin_handler = $wpsynchro_container->get("class.MUPluginHandler");

        if ($mu_plugin_enable) {
            $enable_result = $muplugin_handler->enablePlugin();
            if (is_bool($enable_result) && $enable_result == true) {
                update_option('wpsynchro_muplugin_enabled', "yes", true);
            } else {
                $this->notices = array_merge($this->notices,$enable_result);   
            }
        } else {
            $delete_result = $muplugin_handler->disablePlugin();
            if (is_bool($delete_result) && $delete_result == true) {
                delete_option('wpsynchro_muplugin_enabled');
            } else {
                $this->notices = array_merge($this->notices,$delete_result);                
            }
        }
    }

    /**
     *  Show WP Synchro setup screen 
     *  @since 1.0.0
     */
    private function handleGET()
    {
        $accesskey = get_option('wpsynchro_accesskey');
        $methodsallowed = get_option('wpsynchro_allowed_methods');
        if (!$methodsallowed) {
            $methodsallowed = new \stdClass();
            $methodsallowed->pull = false;
            $methodsallowed->push = false;
        }

        $enable_muplugin = get_option('wpsynchro_muplugin_enabled');
        if ($enable_muplugin && strlen($enable_muplugin) > 0) {
            $enable_muplugin = true;
        } else {
            $enable_muplugin = false;
        }

        ?>
        <div class="wrap wpsynchro-setup">
            <h2>WP Synchro <?php echo ( \WPSynchro\WPSynchro::isPremiumVersion() ? 'PRO' : 'FREE' ); ?> - <?php _e('Setup', 'wpsynchro'); ?></h2>
            <p><?php _e('Configure the access key used for accessing this installation from remote. Treat the access key like a password and keep it safe from others.<br>This is also where you choose what methods (pull/push) are allowed when accessing this installation from remote.', 'wpsynchro'); ?></p>

            <?php
            if ($this->show_update_settings_notice) {

                if (count($this->notices) > 0) {

                    ?>
                    <div class="notice notice-error">                       
                        <?php
                        foreach ($this->notices as $notice) {
                            echo '<p>' . $notice . '</p>';
                        }

                        ?>      
                    </div>
                    <?php
                } else {

                    ?>
                    <div class="notice notice-success">
                        <p><?php _e('WP Synchro settings are now updated', 'wpsynchro'); ?></p>
                    </div>
                    <?php
                }
            }

            ?>

            <form id="wpsynchro-setup-form" method="POST" >
                <div class="sectionheader"><?php _e('Configure settings', 'wpsynchro'); ?></div>
                <table class="">
                    <tr>
                        <td><label for="name"><?php _e('Access key', 'wpsynchro'); ?></label></td>
                        <td>
                            <input type="text" name="accesskey" id="wp_synchro_accesskey" value="<?php echo $accesskey; ?>" class="regular-text ltr" readonly><br>
                            <button id="generate_new_access_key" class="wpsynchrobutton"><?php _e('Generate new access key', 'wpsynchro'); ?></button>
                        </td>
                    </tr>
                    <tr><td><p></p></td><td></td></tr>
                    <tr>
                        <td><?php _e('Allowed methods', 'wpsynchro'); ?></td>
                        <td>
                            <label><input type="checkbox" name="allow_pull" id="allow_pull" <?php echo ( $methodsallowed->pull ? ' checked ' : '' ); ?>  /> <?php _e('Allow pull - Allow this site to be downloaded', 'wpsynchro'); ?></label><br>
                            <label><input type="checkbox" name="allow_push" id="allow_push" <?php echo ( $methodsallowed->push ? ' checked ' : '' ); ?> /> <?php _e('Allow push - Allow this site to be overwritten', 'wpsynchro'); ?></label>
                        </td>
                    </tr>
                    <tr><td><p></p></td><td></td></tr>
                    <tr>
                        <td><?php _e('Optimize compatibility', 'wpsynchro'); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_muplugin" id="enable_muplugin" <?php echo ( $enable_muplugin ? ' checked ' : '' ); ?>  /> <?php _e('Enable MU Plugin that disables other plugins/themes on WP Synchro requests (recommended)', 'wpsynchro'); ?></label><br>                         
                        </td>
                    </tr>

                </table>
                <p><input type="submit" value="<?php _e('Save settings', 'wpsynchro'); ?>" /></p>

            </form>

        </div>
        <?php
    }
}

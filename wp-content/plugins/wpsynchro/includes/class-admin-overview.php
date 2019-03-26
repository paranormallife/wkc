<?php
namespace WPSynchro;

/**
 * Class for handling what to show when clicking on the menu in wp-admin
 *
 * @since 1.0.0
 */
class AdminOverview
{

    public static function render()
    {

        $instance = new self;
        $instance->handleGET();
    }

    private function handleGET()
    {
        // Check php/wp/mysql versions
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');

        // Get success count, so we can ask for 5 star review
        $success_count = get_site_option("wpsynchro_success_count", 0);
        $request_review_dismissed = get_site_option("wpsynchro_dismiss_review_request", false);

        // Check for delete
        if (isset($_GET['delete'])) {
            $delete = $_GET['delete'];
        } else {
            $delete = "";
        }

        // If delete
        if (strlen($delete) > 0) {
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
            $inst_factory->deleteInstallation($delete);
        }

        // Check if healthcheck should be run
        $run_healthcheck = false;
        if (\WPSynchro\WPSynchro::isPremiumVersion()) {
            $licensing = $wpsynchro_container->get("class.Licensing");
            if ($licensing->hasProblemWithLicensing()) {
                $run_healthcheck = true;
            }
        }
        // Healthcheck, just run it every week
        if (!$run_healthcheck) {
            $healthcheck_last_success = intval(get_site_option("wpsynchro_healthcheck_timestamp", 0));
            $seconds_in_week = 604800; // 604800 is one week       
            if (($healthcheck_last_success + $seconds_in_week) < time()) {
                $run_healthcheck = true;
            }
        }

        ?>
        <div id="wpsynchro-overview" class="wrap wpsynchro">
            <h2>WP Synchro <?php echo ( \WPSynchro\WPSynchro::isPremiumVersion() ? 'PRO' : 'FREE' ); ?> - <?php _e('Synchronize a WordPress installation', 'wpsynchro'); ?></h2>

            <?php
            if (!\WPSynchro\WPSynchro::isPremiumVersion()) {

                ?>
                <div class="card">
                    <div class="iconcontent">
                        <img src="<?php echo $commonfunctions->getAssetUrl("icon.png"); ?>" width="115" />
                    </div>
                    <div class="cardcontent">
                        <h2><?php _e('Get PRO version now - Includes priority support and access to synchronizing files', 'wpsynchro'); ?></h2>
                        <p><?php _e('Synchronize a WordPress website, with files and database. Get a copy of your customers site in no time or push you newest version of website online.', 'wpsynchro'); ?></p>
                        <a class="" target="_blank" href="https://wpsynchro.com/?utm_source=plugin&utm_medium=banner&utm_campaign=overview"><button class="wpsynchrobutton"><?php _e('GET PRO VERSION', 'wpsynchro'); ?></button></a>
                    </div>
                </div>
                <?php
            }

            // Healthcheck
            if ($run_healthcheck) {

                ?>
                <healthcheck resturl="<?php echo esc_url_raw(get_rest_url(null, 'wpsynchro/v1/healthcheck/')); ?>" restnonce="<?php echo wp_create_nonce('wp_rest'); ?>" ></healthcheck>

                <?php
            }

            if ($success_count >= 10 && !$request_review_dismissed) {
                $dismiss_url = add_query_arg(array('wpsynchro_dismiss_review_request' => 1), admin_url());

                ?>
                <div class="notice notice-success wpsynchro-dismiss-review-request is-dismissible" data-dismiss-url="<?php echo esc_url($dismiss_url); ?>">
                    <p><?php echo sprintf(__("You have used WP Synchro %d times now. We hope you are enjoying it and have saved some time with synchronizations.<br>Give us some love by giving us a review on <a href='%s' target='_blank'>WordPress plugin repository</a>.<br>Thanks for the help.", "wpsynchro"), $success_count, "https://wordpress.org/support/plugin/wpsynchro/reviews/?rate=5#new-post") ?></p>
                </div>
                <?php
            }

            ?>


            <div class="installations">
                <?php
                require_once( 'class-admin-overview-table.php' );
                $table = new AdminOverviewTable();
                $table->prepare_items();

                ?>
                <div class="typefilters addinstallation">                    
                    <?php $table->views(); ?>  
                    <a class="addlink" href="<?php menu_page_url('wpsynchro_addedit', true); ?>"><button class="wpsynchrobutton"><?php _e('Add installation', 'wpsynchro'); ?></button></a>
                </div>
                <form id="syncsetups" method="get">                   
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                    <?php $table->display(); ?>
                </form>

            </div>

        </div>
        <?php
    }
}

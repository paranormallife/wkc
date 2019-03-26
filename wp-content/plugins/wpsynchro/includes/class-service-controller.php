<?php
namespace WPSynchro;

/**
 * Class for setting up the service controller
 *
 * @since 1.0.0
 */
class ServiceController
{

    private $map = array();
    private $singletons = array();

    public function add($identifier, $function)
    {
        $this->map[$identifier] = $function;
    }

    public function get($identifier)
    {
        if (isset($this->singletons[$identifier])) {
            return $this->singletons[$identifier];
        }
        return $this->map[$identifier]();
    }

    public function share($identifier, $function)
    {
        $this->singletons[$identifier] = $function();
    }

    public static function init()
    {

        global $wpsynchro_container;
        $wpsynchro_container = new ServiceController();

        /*
         *  InstallationFactory
         */
        $wpsynchro_container->share(
            'class.InstallationFactory', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-installation-factory.php' );
            return new \WPSynchro\InstallationFactory();
        }
        );

        /*
         *  Installation
         */
        $wpsynchro_container->add(
            'class.Installation', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-installation.php' );
            return new \WPSynchro\Installation();
        }
        );

        /*
         *  Job
         */
        $wpsynchro_container->add(
            'class.Job', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-job.php' );
            return new \WPSynchro\Job();
        }
        );

        /*
         *  MasterdataSync
         */
        $wpsynchro_container->add(
            'class.MasterdataSync', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/masterdata/class-masterdata-sync.php' );
            return new \WPSynchro\Masterdata\MasterdataSync();
        }
        );

        /*
         *  DatabaseSync
         */
        $wpsynchro_container->add(
            'class.DatabaseSync', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/database/class-database-sync.php' );
            return new \WPSynchro\Database\DatabaseSync();
        }
        );

        /*
         *  DatabaseFinalize
         */
        $wpsynchro_container->add(
            'class.DatabaseFinalize', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/database/class-database-finalize.php' );
            return new \WPSynchro\Database\DatabaseFinalize();
        }
        );

        /*
         *  FilesSync
         */
        $wpsynchro_container->add(
            'class.FilesSync', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-files-sync.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\FilesSync(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  SyncList
         */
        $wpsynchro_container->add(
            'class.SyncList', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-sync-list.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\SyncList(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  PopulateListHandler
         */
        $wpsynchro_container->add(
            'class.PopulateListHandler', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-populate-list-handler.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\PopulateListHandler(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  HashListHandler
         */
        $wpsynchro_container->add(
            'class.HashListHandler', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-hash-list-handler.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\HashListHandler(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  PathHandler
         */
        $wpsynchro_container->add(
            'class.PathHandler', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-path-handler.php' );
            return new \WPSynchro\Files\PathHandler();
        }
        );


        /*
         *  TargetSync
         */
        $wpsynchro_container->add(
            'class.TargetSync', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-target-sync-handler.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\TargetSync(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  TransferFiles
         */
        $wpsynchro_container->add(
            'class.TransferFiles', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-transfer-files-handler.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\TransferFiles(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  FinalizeFiles
         */
        $wpsynchro_container->add(
            'class.FinalizeFiles', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/files/class-finalize-files-handler.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Files\FinalizeFiles(new \WPSynchro\WPRemotePOST);
        }
        );

        /*
         *  FinalizeSync
         */
        $wpsynchro_container->add(
            'class.FinalizeSync', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/finalize/class-finalize-sync.php' );
            return new \WPSynchro\Finalize\FinalizeSync();
        }
        );



        /*
         *  SynchronizeController
         */
        $wpsynchro_container->add(
            'class.SynchronizeController', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-sync-controller.php' );
            return new \WPSynchro\SynchronizeController();
        }
        );

        /*
         *  CommonFunctions
         */
        $wpsynchro_container->share(
            'class.CommonFunctions', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-common-functions.php' );
            return new \WPSynchro\CommonFunctions();
        }
        );

        /*
         *  DebugInformation
         */
        $wpsynchro_container->add(
            'class.DebugInformation', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-debug-information.php' );
            return new \WPSynchro\DebugInformation();
        }
        );

        /*
         *  Licensing 
         */
        $wpsynchro_container->add(
            'class.Licensing', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-licensing.php' );
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/interfaces/interface-remote.php' );
            return new \WPSynchro\Licensing(new \WPSynchro\WPRemotePOST);
        }
        );

        /**
         *  UpdateChecker
         */
        $wpsynchro_container->add(
            'class.UpdateChecker', function() {

            if (!class_exists("Puc_v4_Factory")) {
                require dirname(__FILE__) . '/updater/Puc/v4p5/Factory.php';
                require dirname(__FILE__) . '/updater/Puc/v4/Factory.php';
                require dirname(__FILE__) . '/updater/Puc/v4p5/Autoloader.php';
                new \Puc_v4p5_Autoloader();
                \Puc_v4_Factory::addVersion('Plugin_UpdateChecker', 'Puc_v4p5_Plugin_UpdateChecker', '4.5');
            }

            $updatechecker = \Puc_v4_Factory::buildUpdateChecker(
                    'https://wpsynchro.com/update/?action=get_metadata&slug=wpsynchro', WPSYNCHRO_PLUGIN_DIR . 'wpsynchro.php', 'wpsynchro'
            );

            return $updatechecker;
        }
        );

        /**
         *  Logger
         */
        $wpsynchro_container->share(
            'class.Logger', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/logger/class-logger.php' );
            $logpath = wp_upload_dir()['basedir'] . "/wpsynchro/";
            $logger = new \WPSynchro\Logger\FileLogger;
            $logger->setFilePath($logpath);

            return $logger;
        }
        );

        /**
         *  MetadataLog - for saving data on a sync run
         */
        $wpsynchro_container->share(
            'class.MetadataLog', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/class-metadata-log.php' );
            return new \WPSynchro\MetadataLog();
        }
        );

        /**
         *  MU Plugin handler
         */
        $wpsynchro_container->share(
            'class.MUPluginHandler', function() {
            require_once( WPSYNCHRO_PLUGIN_DIR . 'includes/compatibility/class-mu-plugin-handler.php' );
            return new \WPSynchro\Compatibility\MUPluginHandler();
        }
        );
    }
}

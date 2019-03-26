=== WP Synchro - Migrate WordPress database and files ===
Contributors: wpsynchro
Donate link: https://wpsynchro.com/?utm_source=wordpress.org&utm_medium=referral&utm_campaign=donate
Tags: migrate,database,files,media,migration,synchronize,db,export,mysql,move,staging,localhost,local,transfer
Requires at least: 4.7
Tested up to: 5.1
Stable tag: 1.1.0
Requires PHP: 5.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0

Migrate a WordPress site with both database and files. Save time by automating the repetitive task of synchronizing two sites. Made for WP developers.

== Description ==

**WordPress migration plugin for WP developers, by WP developers**

Automating the repetitive task of migrating sites, such as keeping a local development site synchronized with a production site or a staging site in sync with a production site.

**WP Synchro gives you the possibility to:**

*   Pull data from one site to another
*   Push a site to a remote site
*   Search/replace in data (supports serialized data also)
*   Select the database tables you want to move
*   Preserve data on transfer
*   Save synchronization profiles and run again later
*   Synchronize files (including WordPress, media, plugins, themes and more) (PRO version only)
*   Exclusion of files/dirs, to prevent moving node_modules directory and the likes
*   Setup once - Run multiple times - Perfect for development/staging/production environments

We aim to be the defacto standard in migrating sites for developer purposes, being highly configurable and super fast migrations.

**Typical use for WP Synchro:**

 *  Developing websites on local server and wanting to push a website to a live server or staging server
 *  Get a copy of a working production site, with both database and files, to a staging or local site for debugging or development with real data
 *  Generally moving sites from one place to another, even on a firewalled local network

**WP Synchro PRO version:**

Pro version gives you more features, such as synchronizing files, much faster support and no more PRO ads.
Check out how to get PRO version at [WPSynchro.com](https://wpsynchro.com/ "WP Synchro PRO")
We have a 14 day trial waiting for you and 30 day money back guarantee. So why not try the PRO version?

WP Synchro saves you time each and every time you move a website to a new location - With less problems, because it is totally automated.

== Installation ==

**Here is how you get started:**

1. Upload the plugin files to the `/wp-content/plugins/wpsynchro` directory, or install the plugin through the WordPress plugins screen directly
1. Make sure to install the plugin on all the WordPress installations (it is needed on both ends of the synchronizing)
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Choose if data can be overwritten or be downloaded from installation in menu WP Synchro->Setup
1. Add your first installation from WP Synchro overview page and configure it 
1. Run the synchronization 
1. Enjoy
1. Rerun the same migration again next time it is needed and enjoy how easy that was

== Frequently Asked Questions ==

= Do you offer support? =

Yes we do, for both free and PRO version. But PRO version users always get priority support, so support requests for the free version will normally take some time.
Check out how to get PRO version at [WPSynchro.com](https://wpsynchro.com/ "WP Synchro site")

You can contact us at <support@wpsynchro.com> for support. Also check out the "Support" menu in WP Synchro, that provides information needed for the support request.

= Where can i contact you with new ideas and bugs? =

If you have an idea for improving WP Synchro or found a bug in WP Synchro, we would love to hear from you on:
<support@wpsynchro.com>

= Do you support multisite? =

Well, not really at the moment. You can pull database down to your local installation.
But we have not done that much testing on multisite yet, so use it is at own risk.
Let us know if this is important to you, so we can increase the priority of this functionality.
<support@wpsynchro.com>

= What is WP Synchro tested on? =

Currently we test on more than 100 configurations with different WordPress/PHP/Database versions.

WP Synchro is tested on :
 * MySQL 5.5 up to MySQL 8.0 and MariaDB from 10.0 to 10.3.
 * PHP 5.6 up to latest version
 * WordPress from 4.7 to latest version.


== Screenshots ==

1. Shows the overview of plugin, where you start and delete the synchronization jobs
2. Shows the add/edit screen, where you setup or change a synchronization job
3. Shows the setup of the plugin, where you handle the access key and what is allowed on this installation

== Changelog ==

= 1.1.0 =
 * Highlight: Massive performance improvement for all migrations - Now using MU plugin to skip plugins/themes loading for WP Synchro requests
 * Highlight: Self healthcheck that will self-diagnose known troubles on each site
 * Improvement: Make frontend JS more tolerant of intermittent host timeouts
 * Improvement: Support for self-signed certificates
 * Bugfix: Fixed a lot of smaller bugs on database sync
 * Bugfix: Fixed bugs when syncing files with special characters, such as the Danish æøå

= 1.0.5 =
* WordPress 5.0 compatibility
* PHP 7.3 compatibility
* Bugfix: Fixed compatibility with some WordFence plugin tables, that use binary columns for some obscure reason
* Feature: Add index.php to uploads folder for security
* Feature: Add buffering to logger, to increase performance
* Feature: Add timer to run sync window, so elapsed time can be seen
* Feature: Make is possible to rearrange search/replaces in installation configuration
* Feature: Create section in Support menu, to make is possible to clean the database and disk for WP Synchro artifacts
* Feature: Hide license key on the frontend
* Feature: For large file transfer, take the partial progress into account in global progress indicator
* Feature: Introduce Log menu, where synchronization logs can be viewed and downloaded
* Feature: Better error messages when access key is wrong

= 1.0.4 =
* Bugfix: Fixed typo in REST services require that broke synchronization on *nix hosts

= 1.0.3 =
* First release of PRO version with file synchronization! Check out wpsynchro.com
* Added debug page
* Added .pot file for translation
* Added localization for js
* Added details to progress indicators (with data amount and ex. rows for database sync)
* Added verification that both ends of synchronization is same version to prevent crazy stuff
* Added database version, so we can handle that going forward
* Added http warning in add/edit screen
* Changed so first search/replace will be there as default on new
* Simplified readme.txt
* Cleaned up code around synchronization

= 1.0.2 =
* Bugfixing

= 1.0.1 =
* Added MySQL 5.5 support

= 1.0.0 =
* First official release
* Such cool, much wow 




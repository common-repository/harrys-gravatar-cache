=== Harrys Gravatar Cache ===
Contributors: harry-milatz, wpverwalter
Tags:  Gravatar, Avatar, Cache, PHP7
Donate link: http://www.amazon.de/gp/registry/wishlist/38H54YCAQU0LH/ref=cm_wl_rlist_go_o?
Requires at least: 4.2
Tested up to: 5.9
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accelerates the site speed by simply and effective caching Gravatar (Globally Recognized Avatars).

== Description ==

Accelerates the site speed by simply and effective caching Gravatars (Globally Recognized Avatars) so that they are delivered from the own web server and do not need to be reloaded from the Gravatar server.

**NEW feature:** Other Avatars, e.g. from a "social login" plugin for comments are cached in version 1.3.0 and above.

**NEW feature:** Avatars from captured Facebook comments with a plugin like **"Facebook Comments Importer"** are cached in version 1.4.0 and above. The cache have to be emptied once after update.

**NEW feature:** Avatars from the plugin **"Wapuuvatar"** are cached in version 1.4.3 and above.

**NEW feature:** The plugin is now ready for Multisite in version 1.5.0 and above.

**NEW feature:** The plugin is now ready for being used with **"Avatar Manager"** in version 1.5.1 and above.

**NEW feature:** The plugin is now ready for being used with **"Jetpack's Author Widget"** in version 2.0.0 and above.


= Features =

You can:

* change the Gravatar Size
* add a second Gravatar Size
* change the cachetime
* change the option how the Gravatars will be copied to your server
* update the options depending on the server configuration
* build the cache in the backend and see the cached images in all cached sizes
* empty the Cache
* get the size to use for the Gravatars from your template or set the size manually
* You see a statistic how many files are cached and the filesizes of the files.
* change the output file from a JPG-Image to a PNG-Image

= Translations =

* English (US) - [Harry Milatz](https://profiles.wordpress.org/harry-milatz/)
* English (UK) - [Harry Milatz](https://profiles.wordpress.org/harry-milatz/)
* German - [Harry Milatz](https://profiles.wordpress.org/harry-milatz/)
* German (formal) - [Harry Milatz](https://profiles.wordpress.org/harry-milatz/)
* Spanish (Spain) - [WPVerwalter](https://profiles.wordpress.org/wpverwalter/)
* German (Switzerland, Informal) - Pascal Krapf
* German (Switzerland) - Pascal Krapf

Harrys Gravatar Cache now supports WordPress.org language packs. Want to translate Harrys Gravatar Cache? Visit [Harrys Gravatar Cache's WordPress.org translation project](https://translate.wordpress.org/projects/wp-plugins/harrys-gravatar-cache/).

== Installation ==

Just install from your WordPress "Plugins > Add New" screen and all will be well. Manual installation is very straightforward as well:

1. Upload the zip file and unzip it in the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings > Harrys Gravatar Cache Settings' and enable/change the options you want. Generally this means the Size of the Gravatars.

== Frequently Asked Questions ==

Nothing at the moment

== Screenshots ==
1. Settings Page
2. Settings Page 2
3. Build the cache and Check 1
4. Build the cache and Check 2
5. Errorpage: Database
6. Errorpage: Cachefolder doesn't exist
7. Errorpage: Cachefolder has not the correct permissions
8. Errorpage: Cachefolder is not writeable
9. Errorpage: no Copyoption available

== Changelog ==

Requires at least: WordPress Version 4.2

= 2.0.2 =
* update: Translations
* removed: Output support for Internet Explorer 11
* fixed: Plugin options were saved twice in the database if it was previously deactivated and then activated.

= 2.0.1 =
* fixed: The error "PHP Parse error: syntax error, unexpected 'else' (T_ELSE), expecting end of file in /path/to/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php on line 1480" was displayed after the update to version 2.0.0.

= 2.0.0 =
* Code improvement and cleanup as well as minor bug fixes
* changed: administration of the tables in the database, installation routine, update routine, installation routine, database version added as wp_option
* **added: database or filesystem to check the cached images**
* changed: Output support for Internet Explorer 11 set to no, this option will be removed soon
* **added: Create PNG files from JPG files**
* added: If the URL to Gravatar does not contain an image and generates a 403 or 404 error message, the default avatar will be used.
* **added: Build the cache and check the cache and show cached gravatars**
* **added: Support for Jetpack's author widget**
* **added: more files to scan from the newspaper theme for the avatar size.**
* changed: src for retina
* changed: Alpha Blending for PNG files and for PNG files generated from JPG files
* changed: No caching for empty or no avatar, static file is output instead
* improved: All data operations
* improved: Support for the plugin Wapuuvatar
* **added: WordPress standard lazy loading**
* **NOTE: cache will be emptied with this update**

= 1.7.3 =
* **added: More files to scan from the hueman theme for the avatar size.**
* added: Caching Gravatars in the "last comments widget" from the hueman theme.
* fixed: Gravatars from authors not always have been cached in the sidebar using Jetpacks Author widget.

= 1.7.2 =
* fixed: PNGs are not always cached with **imageAlphaBlending** for transparency. *Note:* The gravatar-cache MUST be emptied once after update!

= 1.7.1 =
* changed: "PHP file_exists" has changed to WordPress "$wp_filesystem->exists($target_file)".
* changed: Donate button for PayPal

= 1.7.0 =
* changed: PNGs are now cached with compression.

= 1.6.1 =
* fixed: Gravatars from authors not always have been cached on an authorpage that is not "in_the_loop".

= 1.6.0 =
* added: If a host set the permission of the cachefolder to 0775 by default, this is now supported. If the permissions are not set properly, you can choose between 0755 or 0775.

= 1.5.10 =
* fixed: Wrong checking if "active_theme" is correctly stored in the database -> an error has been shown in the backend **Das Plugin verursachte 243 Zeichen unerwartete Ausgabe während der Aktivierung. Solltest du Fehlermeldungen wie „headers already sent“, Probleme mit der Syndizierung der Feeds oder andere Fehler erhalten, versuche, das Plugin zu deaktivieren oder zu löschen.** and a WordPress-Database-Error in the logfile **WordPress-Datenbank-Fehler Duplicate column name 'active_theme' für ALTER TABLE `wp_harrys_gravatar_cache` ADD `active_theme` TEXT NOT NULL AFTER `copy` von activate_plugin, do_action('activate_harrys-gravatar-cache/harrys-gravatar-cache.php'), WP_Hook->do_action, WP_Hook->apply_filters, call_user_func_array, harrys_gravatar_cache_installation, harrys_gravatar_cache_activation, get_size_gravatar_hgc** when activating the plugin after it was deactivated.
* fixed: PHP Notice: **PHP Notice:  Trying to get property of non-object in /[basedir]/public_html/wp-includes/comment-template.php on line ...** was shown on pages/posts without comments.

= 1.5.9 =
* improved: Getting avatar size from the template.
* fixed: Gravatars from authors are cached "in_the_loop()" when "get_the_author_meta('user_email')" is used doesn't work on post/page. *Thanks to [tchibomann](https://wordpress.org/support/users/tchibomann/) 

= 1.5.8 =
* fixed: **Gravatars from authors are cached "in_the_loop()" when "get_userdatabylogin($author_name)" or "get_userdata(intval($author))" with "theme_locals('about')" is used on a page or post** doesn't work on post/pages without an comment.

= 1.5.7 =
* **added: Gravatars from authors are cached "in_the_loop()" when "get_userdatabylogin($author_name)" or "get_userdata(intval($author))" with "theme_locals('about')" is used on a page or post.**
* **added: Gravatars from comments in the sidebar are cached.**
* **added: More files to scan from the CherryFramework for the avatar size.**
* added: Function in the setting page to get the avatar size from template (again) if the template has been changed. The active theme is stored in the database.

= 1.5.6 =
* fixed: Proof if the "rating" for Gravatars is set. If not set rating to "R" for getting Gravatars.
* **added: Gravatars from Twitter will be cached if a user comments with his profile from Twitter.**
* **added: A fix for caching FB picture and Google picture if the same user posts comments from both accounts in the same page/post with a "social login" plugin.**

= 1.5.5 =
* fixed: One little bug with the button **Try to get the Gravatar size from the template** in version 1.5.4 is fixed.

= 1.5.4 =
* fixed: PHP Error: **Fatal error: Call to undefined function is_plugin_active() in /[basedir]/public_html/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php** could have been shown.
* fixed: Changing the *cache time* was not stored in the database
* changed: Install and uninstall routines.
* added: More internal options to get the Gravatar size from the used template.
* added: Hint on the settings page if the template has changed.
* added: If the URL sheme from the source is without http(s) the gravatars now will be chached.
* added: german (Switzerland, Informal) translations by Pascal Krapf
* added: german (Switzerland) translations by Pascal Krapf

= 1.5.3 =
* fixed: PHP Notice: **PHP Notice: Undefined variable: avatar_size in /[basedir]/public_html/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php on line 238** could have been shown when the plugin can't get the Gravatar size from the template.

= 1.5.2 =
* fixed: PHP Notice: **PHP Notice:  Trying to get property of non-object in /[basedir]/public_html/wp-includes/comment-template.php on line 97** could have been shown in debug.log on posts/pages without a comment.
* fixed: PHP Notice: **PHP Notice:  Undefined offset: 1 in /[basedir]/public_html/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php on line 212** could have been shown when the plugin can't get the Gravatar size from the template.
* fixed: PHP Notice: **PHP Warning:  fclose() expects parameter 1 to be resource, boolean given in /[basedir]/public_html/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php on line 220** could have been shown when the plugin can't get the Gravatar size from the template.
* added: Path **/[path_to_template]/includes/meta.php** to get the Gravatar size from the template.

= 1.5.1 =
* fixed: Serve the Gravatars from a consistent URL if the Gravatar-images have identical contents(e.g. Standard Gravatar), but would be served from different URLs.
* added: Support the plugin **Avatar Manager** to serve the uploaded Gravatar from the user.
* fixed: The message "Header already sent" could have been there at activation.

= 1.5.0 =
* added: multisite-ready
* changed: Output for W3C Validator "Nu Html Checker" to avoid here the output for IE11
* fixed: if WP_DEBUG is turned on, the following PHP notice is seen in wp-content/debug.log file: **PHP Notice: Undefined index: cache_time in /[basedir]/public_html/wp-content/plugins/harrys-gravatar-cache/harrys-gravatar-cache.php on line 563**
* added: Donate buttons for PayPal and Amazon
* update: translations

= 1.4.5 =
* update: spanish translations by [WPVerwalter](https://profiles.wordpress.org/wpverwalter/)
* added: german (formal) translations by [Harry Milatz](https://profiles.wordpress.org/harry-milatz/)
* added: english (UK) translations by Harry Milatz
* added: english (New Zealand) translations by Harry Milatz
* added: english (Australia) translations by Harry Milatz
* added: get colorspaces for **IE11**
* changed: caches PNG now with **imageAlphaBlending** and **imageSaveAlpha** if the source was stored as JPG with the ending png. *Note:* The gravatar-cache MUST be emptied once after update!
* changed: supressed warnings in the log when md5 can't get the hash for an image
* changed: Fallback if gravatar is not cached

= 1.4.4 =
* added: Support for the "old" **IE11** to the img-attribute "srcset".
* added: new options **1 day to 6 days** for the cachetime
* update: translations
* change: behaviour to empty the cachefolder when saving changes

= 1.4.3 =
* added: Caching avatars from the plugin **"Wapuuvatar"**.
* added: ready for **PHP 7**

= 1.4.2 =
* added: Serves now the Gravatars from a consistent URL if the Gravatar-images have identical contents(e.g. Standard Gravatar), but are served from different URLs. So this saves requests.

= 1.4.1 =
* tweaked: Removed loading the translations for WordPress before 2.7 with **$abs_rel_path**.

= 1.4.0 =
* update: Update the fix for the fallback for creating database table
* Code cleanup for some PHP Warnings if 'WP_DEBUG' is set true
* added: Caching avatars from Facebook if from there are comments captured and insert in the database with a plugin like **Facebook Comments Importer**. **Note:** The gravatar-cache MUST be emptied once after update!

= 1.3.9 =
* fixed: Fallback for creating database table (in some cases the database table can not be created when the neccessary options for filling the database table could not be served)

= 1.3.8 =
* fixed: Error changing Gravatar size manually and save this correctly.
* added: Screenshot for error information when the database table is not filled successfully with the neccessary options.
* added: Proof if the "rating" for Gravatars is set. If not set rating to "R" for getting Gravatars.
* changed: proof before calling the function for caching
* update: german and spanish translation.

= 1.3.7 =
* fixed: Display the copy option for cUrl in the settings if cUrl is the only copy option available.
* added: Error information when the database table is not filled successfully with the neccessary options.
* update: german and spanish translation.
* added: screenshots of possible errors

= 1.3.6 =
* added: **Feature to enter Gravatar size manually.**
* update: german and spanish translation.
* changed: how to get the rating of Gravatar.

= 1.3.5 =
* changed: the PHP functions **fopen()**, **file_get_contents()**, **file_put_contents()** and **cUrl** were changed to the "WP Filesystem" functions.
* changed: PHP **cUrl** is not explicitly used anymore, it is contained in "wp_remote_fopen" and will be used if **fopen** won't work.
* Code cleanup

= 1.3.0 =
* **added: Caching of other avatars from plugins for "social login"**
* changed: Fallback to the original avatar img-tag if caching was not successful

= 1.2.1 =
* fixed: issue with the srcset tag in mobile browsers

= 1.2.0 =
* added: disable the caching function in the Wordpress Backend and Frontend for logged in users with admin rights

= 1.1.9 =
* added: filling the alt-Tag with the authorname
* fixed: W3C HTML Validation error for srcset: "&" is now escaped;

= 1.1.8 =
* fixed: missing CSS-classes for Gravatar Hovercards in versions 1.1.6 & 1.1.7
* added: srcset

= 1.1.7 =
* fixed: missing ID for <img> in version 1.1.6

= 1.1.6 =
* fixed: Statistics are only displayed when there is no error
* fixed: Issue with Gravatar Hovercards

= 1.1.5 =
* fixed: errorhandling when the cache folder has not the correct permissions
* updated: translations

= 1.1.4 =
* changed: filepermission of the cached files to 0644
* changed: permissions back from 0750 to 0755 for the cache folder because of on some servers the cached gravatars could not been served and get an 403 error
* added: 44px to the gravatar size

= 1.1.3 =
* fixed: error on some server configurations for the path to the cached file
* changed: Statistics are only displayed when there is no error
* changed: how to recognize if there is an error with the method to get the Gravatar

= 1.1.2 =
* changed: how to get the "wp-content/uploads" folder
* changed: how to get the textdomain folder
* changed: how to get the cache folder and chache folder url
* fixed: database operations -> https://codex.wordpress.org/Data_Validation#Database
* fixed: correcting german translation
* fixed: an error in loading the textdomain on the settings page
* fixed: an error to proof the $_POST['size']

= 1.1.1 =
* fixed: correcting translations
* changed: permissions from 0755 to 0750 for the cache folder
* added: english translation

= 1.1 =
* added: spanish translation by [WPVerwalter](https://profiles.wordpress.org/wpverwalter/)
* changed: how to load textdomain
* changed: how to get the "wp-content/uploads" folder for the files

= 1.0 =
* Release

== Upgrade Notice ==
The current version of this Plugin requires WordPress 4.2 or higher. If you use older version of WordPress, you need to upgrade WordPress first.
<?php
/*
/**
 * Plugin Name: Harrys Gravatar Cache
 * Plugin URI: https://www.all4hardware4u.de
 * Description: Beschleunigt die Website durch simples und effektives Caching von Gravataren (Globally Recognized Avatars), damit diese vom eigenenem Webserver ausgeliefert werden und nicht vom Gravatar-Server nachgeladen werden müssen.
 * Version: 2.0.2
 * Author: Harry Milatz
 * Author URI: https://www.all4hardware4u.de
 * Text Domain: harrys-gravatar-cache
 * Domain Path: /languages
 * License: GPL3

Copyright 2015-2018 Harry Milatz (email : harry@all4hardware4u.de)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>
*/
if (!defined('ABSPATH')) exit; // Verlassen bei direktem Zugriff
require_once( ABSPATH . "wp-includes/pluggable.php" );

// Datenbanktabellen
global $hgc_db_version;
$hgc_db_version = '2.0.2';
global $wpdb;
global $hgc_table;
$hgc_table=$wpdb->prefix.'harrys_gravatar_cache';
global $hgc_store;
$hgc_store=$wpdb->prefix.'harrys_gravatar_store';
global $comment_table;
$comment_table=$wpdb->prefix.'comments';
global $installed_ver;
$installed_ver = get_option( "hgc_db_version" );
// Pfade festlegen
$path_up=wp_upload_dir();
global $cache_url;
$cache_url=$path_up['baseurl']."/gravatar-cache/";
global $path;
$path=$path_up['basedir']."/gravatar-cache/";
global $wp_filesystem;
if (empty($wp_filesystem)) {
	require_once ( ABSPATH . "/wp-admin/includes/file.php" );
	WP_Filesystem();
}
// Umgebung prüfen
if ( !function_exists('is_plugin_active') ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
global $no_hgc;
$no_hgc=NULL;
global $default;
$default=NULL;
$default=get_option('avatar_default');
$show_avatar=NULL;
global $show_avatar;
$show_avatar=get_option('show_avatars');
$hovercard=NULL;
global $hovercard;
$hovercard=get_option( 'gravatar_disable_hovercards' );
if ( $default=="wapuuvatar" && is_plugin_active('wapuuvatar/wapuuvatar.php') || empty($show_avatar) ) {
	$no_hgc=1;
}
// Load translations
load_plugin_textdomain( 'harrys-gravatar-cache', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

/* Install */
$plugin = plugin_basename(__FILE__);
if ( is_admin() || current_user_can('manage_options') ) {
	add_action( 'admin_menu', 'harry_add_pages' );
	add_action( 'admin_init', 'save_settings');
	add_filter("plugin_action_links_$plugin", 'harrys_plugin_settings_link' );
}
if (is_multisite() ) {
	add_action( 'network_admin_menu', 'harry_add_pages' );
	add_action( 'admin_init', 'save_settings');
}

/* Einstellungsseite den Einstellungen hinzufügen */
function harry_add_pages() {
	add_options_page(
	__( 'Harrys Gravatar Cache Settings', 'harrys-gravatar-cache' ),
	__( 'Harrys Gravatar Cache Settings', 'harrys-gravatar-cache' ),
	'manage_options',
	'harrys-gravatar-cache-options',
	'Einstellungen'
	);
}

/* Link zu Einstellungen auf der Pluginseite */
function harrys_plugin_settings_link($links) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=harrys-gravatar-cache-options' ) ) . '">' . __( 'Settings', 'harrys-gravatar-cache' ) . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

// bei Aktivierung des Plugins - für die einzelnen Installationen bei Multi bzw einmal bei Single
function harrys_gravatar_cache_activation() {
	make_folder();
	make_hgc_table();
	hgc_update_check();
	get_copy_options();
	is_writeable_proof();
}

// Tabelle erstellen
function make_hgc_table() {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $hgc_db_version;
	global $default;
	$charset_collate = $wpdb->get_charset_collate();
	$hgc_sql = "CREATE TABLE IF NOT EXISTS $hgc_table (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		size int(5) NOT NULL,
		size_get int(2) NOT NULL,
		size2 int(5) NOT NULL,
		size_get2 int(2) NOT NULL,
		get_option int(2) NOT NULL,
		cache_time int(11) NOT NULL,
		is_writeable int(2) NOT NULL,
		file_get_contents int(2) NOT NULL,
		fopen int(2) NOT NULL,
		curl int(2) NOT NULL,
		copy int(2) NOT NULL,
		active_theme TEXT NOT NULL,
		stored_default TEXT NOT NULL,
		make_png int(2) NOT NULL,
		check_cache int(2) NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	$hgc_sql_store = "CREATE TABLE IF NOT EXISTS $hgc_store (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		md5 varchar(40) NOT NULL,
		filename varchar(40) NOT NULL,
		filetype varchar(5) NOT NULL,
		srcset varchar(5) NOT NULL,
		post_url varchar(255) NOT NULL,
		blank varchar(6) NOT NULL,
		size int(5) NOT NULL,
		date_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $hgc_sql );
	dbDelta( $hgc_sql_store );
	$deprecated = null;
	$autoload = 'no';
	add_option( 'hgc_db_version', $hgc_db_version, $deprecated, $autoload );
// Tabelle mit ersten Dummydaten füllen
	$active_theme_wp=get_option('template');
// Tabelle prüfen
	if ( $wpdb->get_var($wpdb->prepare("SELECT id FROM $hgc_table WHERE id > %d", 1) ) ) {
		$wpdb->query("DELETE FROM $hgc_table WHERE id > 1" );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT ie11 FROM $hgc_table WHERE id = %d", 1) ) ) {
		$wpdb->query( "ALTER TABLE $hgc_table DROP COLUMN ie11" );
	}
	if ( !$wpdb->get_var($wpdb->prepare("SELECT id FROM $hgc_table WHERE id = %d", 1) ) ) {
		$wpdb->insert($hgc_table, array('size' => 67, 'size_get' => 2, 'size2' => 2, 'size_get2' => 0, 'get_option' => 0, 'cache_time' => 40320, 'is_writeable' => 0, 'file_get_contents' => 0, 'fopen' => 0, 'curl' => 0, 'copy' => 0, 'active_theme' => $active_theme_wp, 'stored_default' => $default, 'make_png' => 1, 'check_cache' => 1), array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d'));
		nothing_set();
		empty_cache();
	}
}

// Datenbankupdate
function update_hgc_table() {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $hgc_db_version;
	global $installed_ver;
	global $default;
#werte mit if abfragen, dann dummywerte
	$charset_collate = $wpdb->get_charset_collate();
	if ( $wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) ) ) {
		$size=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT size_get FROM $hgc_table WHERE id = %d", 1) ) ) {
		$size_get=$wpdb->get_var($wpdb->prepare("SELECT size_get FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT size2 FROM $hgc_table WHERE id = %d", 1) ) ) {
		$size2=$wpdb->get_var($wpdb->prepare("SELECT size2 FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT size_get2 FROM $hgc_table WHERE id = %d", 1) ) ) {
		$size_get2=$wpdb->get_var($wpdb->prepare("SELECT size_get2 FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) ) ) {
		$get_option=$wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) ) ) {
		$cache_time=$wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT is_writeable FROM $hgc_table WHERE id = %d", 1) ) ) {
		$is_writeable=$wpdb->get_var($wpdb->prepare("SELECT is_writeable FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT file_get_contents FROM $hgc_table WHERE id = %d", 1) ) ) {
		$file_get_contents=$wpdb->get_var($wpdb->prepare("SELECT file_get_contents FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT fopen FROM $hgc_table WHERE id = %d", 1) ) ) {
		$fopen=$wpdb->get_var($wpdb->prepare("SELECT fopen FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT curl FROM $hgc_table WHERE id = %d", 1) ) ) {
		$curl=$wpdb->get_var($wpdb->prepare("SELECT curl FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT copy FROM $hgc_table WHERE id = %d", 1) ) ) {
		$copy=$wpdb->get_var($wpdb->prepare("SELECT copy FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT active_theme FROM $hgc_table WHERE id = %d", 1) ) ) {
		$stored_theme=$wpdb->get_var($wpdb->prepare("SELECT active_theme FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT make_png FROM $hgc_table WHERE id = %d", 1) ) ) {
		$make_png=$wpdb->get_var($wpdb->prepare("SELECT make_png FROM $hgc_table WHERE id = %d", 1) );
	}
	if ( $wpdb->get_var($wpdb->prepare("SELECT check_cache FROM $hgc_table WHERE id = %d", 1) ) ) {
		$check_cache=$wpdb->get_var($wpdb->prepare("SELECT check_cache FROM $hgc_table WHERE id = %d", 1) );
	}
	$active_theme_wp=get_option('template');
	if (empty($cache_time) || $cache_time<1440 || $cache_time>80640) {$cache_time=40320;}
	if (empty($make_png) || $make_png<1 || $make_png>2) {$make_png=1;}
	if (empty($check_cache) || $check_cache<1 || $check_cache>2) {$check_cache=1;}
	$hgc_sql = "CREATE TABLE $hgc_table (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		size int(5) NOT NULL,
		size_get int(2) NOT NULL,
		size2 int(5) NOT NULL,
		size_get2 int(2) NOT NULL,
		get_option int(2) NOT NULL,
		cache_time int(11) NOT NULL,
		is_writeable int(2) NOT NULL,
		file_get_contents int(2) NOT NULL,
		fopen int(2) NOT NULL,
		curl int(2) NOT NULL,
		copy int(2) NOT NULL,
		active_theme TEXT NOT NULL,
		stored_default TEXT NOT NULL,
		make_png int(2) NOT NULL,
		check_cache int(2) NOT NULL,
		PRIMARY KEY  (id)
	);";
	$hgc_sql_store = "CREATE TABLE $hgc_store (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		md5 varchar(40) NOT NULL,
		filename varchar(40) NOT NULL,
		filetype varchar(5) NOT NULL,
		srcset varchar(5) NOT NULL,
		post_url varchar(255) NOT NULL,
		blank varchar(6) NOT NULL,
		size int(5) NOT NULL,
		date_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	$wpdb->query("DROP TABLE IF EXISTS `{$hgc_table}` ");
	$wpdb->query("DROP TABLE IF EXISTS `{$hgc_store}` ");
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $hgc_sql );
	dbDelta( $hgc_sql_store );
	if (empty($installed_ver)) {
		$deprecated = null;
		$autoload = 'no';
		add_option( 'hgc_db_version', $hgc_db_version, $deprecated, $autoload );
	}
	else {
		update_option( "hgc_db_version", $hgc_db_version );
	}
	if ( !$wpdb->get_var($wpdb->prepare("SELECT id FROM $hgc_table WHERE id = %d", 1) ) ) {
		$wpdb->insert($hgc_table, array('size' => 67, 'size_get' => 2,'size2' => 3, 'size_get2' => 0, 'get_option' => 0, 'cache_time' => 40320, 'is_writeable' => 0, 'file_get_contents' => 0, 'fopen' => 0, 'curl' => 0, 'copy' => 0, 'active_theme' => $active_theme_wp, 'stored_default' => $default, 'make_png' => 1, 'check_cache' => 1), array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d'));
		nothing_set();
	}
	else {
		if ( $wpdb->get_var($wpdb->prepare("SELECT id FROM $hgc_table WHERE id > %d", 1) ) ) {
			$wpdb->query("DELETE FROM $hgc_table WHERE id > 1" );
		}
		if ( $wpdb->get_var($wpdb->prepare("SELECT ie11 FROM $hgc_table WHERE id = %d", 1) ) ) {
			$wpdb->query( "ALTER TABLE $hgc_table DROP COLUMN ie11" );
		}
		$wpdb->update($hgc_table, array('size' => $size, 'size_get' => $size_get, 'size2' => $size, 'size_get2' => $size_get2, 'get_option' => $get_option, 'cache_time' => $cache_time, 'is_writeable' => $is_writeable, 'file_get_contents' => $file_get_contents, 'fopen' => $fopen, 'curl' => $curl, 'copy' => $copy, 'active_theme' => $stored_theme, 'stored_default' => $default, 'make_png' => $make_png, 'check_cache' => $check_cache), array('id' => 1), array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d'));
	}
	empty_cache();
}

function hgc_update_check() {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $hgc_db_version;
	global $installed_ver;
	if ( $installed_ver != $hgc_db_version || empty($installed_ver) ) {
	if (is_multisite() && $networkwide) {
// Multisite / Netzwerk Plugin Installation
// Aktuellen Blog zwischenspeichern
		$current_blog = $wpdb->blogid;
// Durch alle Blogs/Wbesites gehen
		$blogids = $wpdb->get_co1("SELECT blog_id FROM $wpdb->blogs");
		$current_blog = $wpdb->blogid;
// Jeder Webseite die Tabellen in der Datenbank updaten
		foreach ($blogids as $blogid) {
			switch_to_blog($blogid);
			update_hgc_table();
		}
		switch_to_blog($current_blog);
		// einzelne Website
		}
		else {
			update_hgc_table();
		}
	}
}
if ( is_admin() && current_user_can('manage_options') ) {
	add_action( 'plugins_loaded', 'hgc_update_check' );
}

// falls nichts in die Tabelle geschrieben wurde und diese mit Dummywerten gefüllt wurde(bei Neuinstallation oder Update)
function nothing_set() {
	get_size_gravatar_hgc();
	get_copy_options();
	is_writeable_proof();
}

// bei Aktivierung des Plugins - erster Aufruf
function harrys_gravatar_cache_installation($networkwide) {
	global $wpdb;
	global $hgc_table;
	$hg_cache_find = $wpdb->prefix.'harrys-gravatar-cache';
	// Prüfen ob eine Seite schon vorhanden ist und das Plugin bereits installiert
	if ($wpdb->get_var("SHOW TABLES LIKE '$hg_cache_find'") != $hg_cache_find) {
	// Prüfen ob es sich um eine Multisite / Netzwerk Installation handelt
		if (is_multisite() && $networkwide) {
		// Multisite / Netzwerk Plugin Installation
			// Aktuellen Blog zwischenspeichern
			// Array fuer alle aktiven Blogs/der aktiven Website
			$activated = array();
			// Durch alle Blogs/Wbesites gehen und das PlugIn aktivieren
			$blogids = $wpdb->get_co1("SELECT blog_id FROM $wpdb->blogs");
			$current_blog = $wpdb->blogid;
			// Jeder Webseite die Tabellen in der Datenbank einfügen und Aktivierung im Array speichern
			foreach ($blogids as $blogid) {
				switch_to_blog($blogid);
				harrys_gravatar_cache_activation();
				$activated[] = $blogid;
			}
			switch_to_blog($current_blog);
			$plugins = FALSE;
			$plugins = get_site_option('active_plugins');
			if ( $plugins ) {
			// Plugin aktivieren
				$pugins_to_active = array(
					'harrys-gravatar-cache/harrys-gravatar-cache.php'
				);
				foreach ( $pugins_to_active as $plugin ) {
					if ( ! in_array( $plugin, $plugins ) ) {
						array_push( $plugins, $plugin );
						update_site_option( 'active_plugins', $plugins );
					}
				}
			}
		// Normale Plugin Installation / einzelne Website
		}
		else {
			harrys_gravatar_cache_activation();
		}
	}
}
register_activation_hook( __FILE__, 'harrys_gravatar_cache_installation' );

// Wenn eine Website hinzugefügt wird
function add_blog($blog_id) {
	if ( is_plugin_active_for_network( 'harrys-gravatar-cache/harrys-gravatar-cache.php' ) ) {
		switch_to_blog($blog_id);
		// Neuer Webseite die Tabelle in der Datenbank einfügen
		harrys_gravatar_cache_activation();
		restore_current_blog();
	}
}
add_action ( 'wpmu_new_blog', 'add_blog', 99 );

// Wenn eine Website gelöscht wird
function delete_blog($tables) {
	global $wpdb;
	// Tabellen die gelöscht werden sollen
	$tables[] = $wpdb->prefix.'harrys_gravatar_cache';
	$tables[] = $wpdb->prefix.'harrys_gravatar_store';
	return $tables;
}
add_filter ( 'wpmu_drop_tables', 'delete_blog', 99 );

// Uninstall
function harrys_gravatar_cache_uninstall() {
	global $wpdb;
	global $wp_filesystem;
	global $path;
	$hgc_table=$wpdb->prefix.'harrys_gravatar_cache';
	$hgc_store=$wpdb->prefix.'harrys_gravatar_store';
	empty_cache();
	$wp_filesystem->rmdir($path);
	$wpdb->query("DROP TABLE IF EXISTS `{$hgc_table}` ");
	$wpdb->query("DROP TABLE IF EXISTS `{$hgc_store}` ");
	delete_option( "hgc_db_version" );
}

function harrys_gravatar_cache_plugin_uninstall() {
	global $wpdb;  
	// Prüfen ob es sich um eine Multisite / Netzwerk Deinstallation handelt
	if (is_multisite() ) {
		// Multisite / Netzwerk Plugin deinstallation
		$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
		$current_blog = $wpdb->blogid;
		// Von jeder Webseite die Tabelle in der Datenbank löschen
		foreach ($blogids as $blogid) {
			switch_to_blog($blogid);
			harrys_gravatar_cache_uninstall();
		}
		switch_to_blog($current_blog);
	// Normale Plugin deinstallation
	}
	else {
		harrys_gravatar_cache_uninstall();
	}
}
register_uninstall_hook( __FILE__, 'harrys_gravatar_cache_plugin_uninstall' );

/* Funktionen für die Einstellungsseite und Install */
// Cache Directory anlegen
function make_folder() {
	global $wp_filesystem;
	global $path;
	if ( !$wp_filesystem->is_dir($path) ) {
		$wp_filesystem->mkdir($path);
	}
	if ( $wp_filesystem->is_dir($path) ) {
		$pathperm=$wp_filesystem->getchmod($path);
		if ($pathperm!="755" && $pathperm!="775") {
			$wp_filesystem->chmod($path, 0755);
		}
	}
}

//Ordnerberechtigungen korrigieren
function correct_folder() {
	global $wp_filesystem;
	global $path;
	$wp_filesystem->chmod($path, 0755);
}
function correct_folder2() {
	global $wp_filesystem;
	global $path;
	$wp_filesystem->chmod($path, 0775);
}

// Dateien des Templates nach Avatargrösse durchsuchen
function get_size_gravatar_hgc() {
	global $wp_filesystem;
	global $hgc_table;
	global $path;
	$dateinamen=array();
	$template=get_template();
	if ( $template=="CherryFramework" ) {
		if ( is_child_theme() ) {
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/includes/theme-function.php') ) {$dateinamen[]=get_stylesheet_directory().'/includes/theme-function.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/loop/loop-author.php') ) {$dateinamen[]=get_stylesheet_directory().'/loop/loop-author.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/loop/loop-single.php') ) {$dateinamen[]=get_stylesheet_directory().'/loop/loop-single.php';}
		}
		if ( $wp_filesystem->exists(get_template_directory().'/includes/theme-function.php') ) {$dateinamen[]=get_template_directory().'/includes/theme-function.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/loop/loop-author.php') ) {$dateinamen[]=get_template_directory().'/loop/loop-author.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/loop/loop-single.php') ) {$dateinamen[]=get_template_directory().'/loop/loop-single.php';}
	}
	else if ( $template=="hueman" ) {
		if ( is_child_theme() ) {
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/comments.php') ) {$dateinamen[]=get_stylesheet_directory().'/comments.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/functions/widgets/alx-tabs.php') ) {$dateinamen[]=get_stylesheet_directory().'/functions/widgets/alx-tabs.php';}
		}
		if ( $wp_filesystem->exists(get_template_directory().'/comments.php') ) {$dateinamen[]=get_template_directory().'/comments.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/functions/widgets/alx-tabs.php') ) {$dateinamen[]=get_template_directory().'/functions/widgets/alx-tabs.php';}
	}
	else if ( $template=="Newspaper" ) {
		if ( is_child_theme() ) {
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/comments.php') ) {$dateinamen[]=get_stylesheet_directory().'/comments.php';}
		}
		if ( $wp_filesystem->exists(get_template_directory().'/includes/wp_booster/comments.php') ) {$dateinamen[]=get_template_directory().'/includes/wp_booster/comments.php';}
	}
	else {
		if ( is_child_theme() ) {
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/functions.php') ) {$dateinamen[]=get_stylesheet_directory().'/functions.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/lib/functions/template-comments.php') ) {$dateinamen[]=get_stylesheet_directory().'/lib/functions/template-comments.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/single.php') ) {$dateinamen[]=get_stylesheet_directory().'/single.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/comments.php') ) {$dateinamen[]=get_stylesheet_directory().'/comments.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/includes/functions/comments.php') ) {$dateinamen[]=get_stylesheet_directory().'/includes/functions/comments.php';}
			if ( $wp_filesystem->exists(get_stylesheet_directory().'/includes/meta.php') ) {$dateinamen[]=get_stylesheet_directory().'/includes/meta.php';}
		}
		if ( $wp_filesystem->exists(get_template_directory().'/functions.php') ) {$dateinamen[]=get_template_directory().'/functions.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/lib/functions/template-comments.php') ) {$dateinamen[]=get_template_directory().'/lib/functions/template-comments.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/single.php') ) {$dateinamen[]=get_template_directory().'/single.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/comments.php') ) {$dateinamen[]=get_template_directory().'/comments.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/includes/functions/comments.php') ) {$dateinamen[]=get_template_directory().'/includes/functions/comments.php';}
		if ( $wp_filesystem->exists(get_template_directory().'/includes/meta.php') ) {$dateinamen[]=get_template_directory().'/includes/meta.php';}
	}
	$count_datei=count($dateinamen);
	$active_theme_wp=get_option('template');
	if (!empty($count_datei)) {
		$counter_size=0;
		$avatar_size=array();
		foreach($dateinamen as $dateiname) {
			$datei_inhalt=$wp_filesystem->get_contents($dateiname);
			if ($datei_inhalt) {
				preg_match('/avatar_size=(\d*)/',$datei_inhalt,$size);
				if (empty($size[1])) {
					preg_match("/'avatar_size' => (\d*)/",$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match("/'avatar_size'=>(\d*)/",$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match("/'avatar_size'=> (\d*)/",$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match("/'avatar_size' =>(\d*)/",$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/"avatar_size" => (\d*)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/"avatar_size"=>(\d*)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/"avatar_size"=> (\d*)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/"avatar_size" =>(\d*)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/get_avatar\(\D*?(\d*)\D*?\)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match('/get_avatar\(\D*?(\d{1,9})\D*?\)/',$datei_inhalt,$size);
				}
				if (empty($size[1])) {
					preg_match("/hu_avatar_size...(\d*)/",$datei_inhalt,$size);
				}
				if (empty($size)) {
					if (!isset($size[1])) {
						$size[1]=null;
					}
					$size=$size[1];
				}
				if (!empty($size)) {
					$size=$size[1];
				}
				if (is_numeric($size)) {
					$avatar_size[$counter_size]=$size;
					$size_get=1;
					if ($counter_size==1) {
						$size_get2=1;
					}
					$counter_size++;
				}
			}
		}
		if (!isset($avatar_size[0])) {
			$avatar_size[0]=67;
			$size_get=2;
		}
		if (!isset($avatar_size[1])) {
			$avatar_size[1]=0;
			$size_get2=1;
		}
	}
	else {
		$avatar_size[0]=67;
		$size_get=2;
		$avatar_size[1]=0;
		$size_get2=1;
	}
	if (!is_numeric($avatar_size[0])) {
		$avatar_size[0]=67;
		$size_get=2;
	}
	if (!is_numeric($avatar_size[1])) {
		$avatar_size[1]=0;
		$size_get2=1;
	}
	global $wpdb;
	if ($avatar_size[0]==$avatar_size[1]) {
		$avatar_size[1]=0;
	}
	$wpdb->update($hgc_table, array('size' => $avatar_size[0], 'size_get' => $size_get, 'size2' => $avatar_size[1], 'size_get2' => $size_get2, 'active_theme' => $active_theme_wp), array('id' => 1), array('%d', '%d', '%d', '%d', '%s'));
}

// Cache leeren
function empty_cache() {
	global $wpdb;
	global $hgc_store;
	global $hgc_table;
	global $path;
	global $wp_filesystem;
	global $default;
	$dirlist = $wp_filesystem->dirlist($path);
	foreach ( (array) $dirlist as $filename => $fileinfo ) {
		if ( 'f' == $fileinfo['type'] ) {
			$wp_filesystem->delete($path.$fileinfo['name']);
		}
	}
	$wpdb->query("TRUNCATE TABLE $hgc_store");
	$wpdb->update($hgc_table, array('stored_default' => $default), array('id' => 1), array('%s'));
}

// Kopieroption festlegen
function get_copy_options() {
	global $hgc_table;
	global $path;
	global $wp_filesystem;
	$get_option=0;
	$file_get_contents=0;
	$fopen=0;
	$curl=0;
	$copy=0;
	$url=plugin_dir_url('avatar-no.jpg');
	$testfile=$path."/test.png";
	//file_get_contents
	if (ini_get('allow_url_fopen') && function_exists('file_get_contents')) {
	$get_option=1;
	$file_get_contents=1;
	}
	//fopen
	if (ini_get('allow_url_fopen')) {
		if (false===$fh=wp_remote_fopen($url,false)) {
			$fopen=0;
		}
		else {
			$get_option=2;
			$fopen=1;
		}
	}
	//cURL
	if (function_exists('curl_init')) {
		$get_option=3;
		$curl=1;
	}
	//PHP Copy
	@$wp_filesystem->copy($url, $testfile, 0644);
	if ($wp_filesystem->exists($testfile)) {
		$get_option=4;
		$copy=1;
		$wp_filesystem->delete($testfile);
	}
	global $wpdb;
	$wpdb->update($hgc_table, array('get_option' => $get_option, 'file_get_contents' => $file_get_contents, 'fopen' => $fopen, 'curl' => $curl, 'copy' => $copy), array('id' => 1), array('%d', '%d', '%d', '%d', '%d'));
}

// Cacheordner beschreibbar ?
function is_writeable_proof() {
	global $hgc_table;
	global $path;
	$is_writeable=0;
	if (is_writable($path)) {
		$is_writeable=1;
	}
	global $wpdb;
	$wpdb->update($hgc_table, array('is_writeable' => $is_writeable), array('id' => 1), array('%d'));
}

// Standardzeit setzen
function set_time() {
	global $wpdb;
	global $hgc_table;
	$wpdb->update($hgc_table, array('cache_time' => 40320), array('id' => 1), array('%d'));
}

// Standardcheck Cache setzen
function set_check() {
	global $wpdb;
	global $hgc_table;
	$wpdb->update($hgc_table, array('check_cache' => 1), array('id' => 1), array('%d'));
}

//Precache
function pre_cache() {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $comment_table;
	global $cache_url;
	$get_comments=$wpdb->get_results("SELECT comment_post_ID, comment_type, comment_author_email, comment_author_url, comment_date FROM $comment_table WHERE comment_author_email != '' AND comment_approved=1 AND comment_type='comment' ORDER BY comment_ID ASC");
	$precache="hgc_precache";
	$precache2="hgc_precache2";
	$size=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
	$size2=$wpdb->get_var($wpdb->prepare("SELECT size2 FROM $hgc_table WHERE id = %d", 1) );
	foreach($get_comments as $row)
	{
		gravatar_lokal (get_avatar( $row->comment_author_email, $size ), $row->comment_author_email, $row->comment_post_ID, $row->comment_author_url, $precache, $size);
		if ( !empty($size2) && $size2!=0 && $size!=$size2) {
			gravatar_lokal (get_avatar( $row->comment_author_email, $size ), $row->comment_author_email, $row->comment_post_ID, $row->comment_author_url, $precache, $size2);
		}
	}
	if ( is_plugin_active('jetpack/jetpack.php') ) {
		$jp_authors=get_option('widget_authors');
		$jp_authors_size=NULL;
		$jp_authors_all=NULL;
		foreach ($jp_authors as $key => $item1) {
			if (is_array($item1)) {
				$jp_authors_size=$item1['avatar_size'];
			}
		}
		foreach ($jp_authors as $key => $item2) {
			if (is_array($item2)) {
				$jp_authors_all=$item2['all'];
			}
		}
		if ( !empty($jp_authors_size) && $jp_authors_size > 1 ) {
			$authors = get_users( array(
				'fields' => 'all',
				'who' => 'authors',
			) );
			foreach ( $authors as $author ) {
				$r = new WP_Query( array(
					'author'         => $author->ID,
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'no_found_rows'  => true,
					'has_password'   => false,
			) );
				if ( ! $r->have_posts() && (empty($jp_authors_all) || $jp_authors_all==0) ) {
					continue;
				}
				if ( $r->have_posts() ) {
					gravatar_lokal (get_avatar( $author->ID, $jp_authors_size ),     $author->ID,                $author->ID,           get_author_posts_url( $author->ID ), $precache2, $jp_authors_size);
				}
				else if ( $jp_authors_all==1 ) {
					gravatar_lokal (get_avatar( $author->ID, $jp_authors_size ), $author->ID, $author->ID, get_author_posts_url( $author->ID ), $precache2, $jp_authors_size);
				}
				if ( empty($jp_authors_all) || $jp_authors_all==0 ) {
					continue;
				}
			}
		}
	}#Jetpack
}#pre



/* Einstellungsseite */
function Einstellungen() {
	if ( current_user_can( 'manage_options' ) ) { ?>
	<!-- Donation button -->
	<div id="donate" class="wrap">
		<div class="inside">
			<p><?php _e('If you like this plugin, please donate with PayPal or Amazon to support development and maintenance!', 'harrys-gravatar-cache'); ?></p>
			<a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SDYQTEP5C2MP8&item_name=Harry's+Gravatar+Cache&no_note=1&no_shipping=1&rm=2"><img alt="" border="0" src="<?php echo plugins_url('paypal-donate.png', __FILE__) ?>" width="150"></a>
			<br />
			<a target="_blank" href="http://www.amazon.de/gp/registry/wishlist/38H54YCAQU0LH/ref=cm_wl_rlist_go_o?"><img alt="" border="0" src="<?php echo plugins_url('amazon.de-logo.png', __FILE__) ?>" width="150"></a>
		</div>
	</div>
	<div class="wrap">
		<h1>Harrys Gravatar Cache</h1>
		<small><?php _e('Accelerates the site speed by simply and effective caching Gravatar (Globally Recognized Avatars) so that they are delivered from the own web server and do not need to be reloaded from the Gravatar server.','harrys-gravatar-cache')?></small>		  
		<h2><?php _e('Settings','harrys-gravatar-cache'); ?></h2>
	<?php
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $comment_table;
	global $path;
	global $cache_url;
	global $wp_filesystem;
	global $no_hgc;
	global $show_avatar;
	global $default;
	$size=NULL;
	$size_get=NULL;
	$size2=NULL;
	$size_get2=NULL;
	$get_option=NULL;
	$cache_time=NULL;
	$is_writeable=NULL;
	$file_get_contents=NULL;
	$fopen=NULL;
	$curl=NULL;
	$copy=NULL;
	$stored_theme=NULL;
	$make_png=NULL;
	$check_cache=NULL;
	$stored_default=NULL;
	$size=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
	$size2=$wpdb->get_var($wpdb->prepare("SELECT size2 FROM $hgc_table WHERE id = %d", 1) );
	$size_get=$wpdb->get_var($wpdb->prepare("SELECT size_get FROM $hgc_table WHERE id = %d", 1) );
	$size_get2=$wpdb->get_var($wpdb->prepare("SELECT size_get2 FROM $hgc_table WHERE id = %d", 1) );
	$get_option=$wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) );
	$cache_time=$wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) );
	$is_writeable=$wpdb->get_var($wpdb->prepare("SELECT is_writeable FROM $hgc_table WHERE id = %d", 1) );
	$file_get_contents=$wpdb->get_var($wpdb->prepare("SELECT file_get_contents FROM $hgc_table WHERE id = %d", 1) );
	$fopen=$wpdb->get_var($wpdb->prepare("SELECT fopen FROM $hgc_table WHERE id = %d", 1) );
	$curl=$wpdb->get_var($wpdb->prepare("SELECT curl FROM $hgc_table WHERE id = %d", 1) );
	$copy=$wpdb->get_var($wpdb->prepare("SELECT copy FROM $hgc_table WHERE id = %d", 1) );
	$stored_theme=$wpdb->get_var($wpdb->prepare("SELECT active_theme FROM $hgc_table WHERE id = %d", 1) );
	$active_theme_wp=get_option('template');
	$make_png=$wpdb->get_var($wpdb->prepare("SELECT make_png FROM $hgc_table WHERE id = %d", 1) );
	$check_cache=$wpdb->get_var($wpdb->prepare("SELECT check_cache FROM $hgc_table WHERE id = %d", 1) );
	$stored_default=$wpdb->get_var($wpdb->prepare("SELECT stored_default FROM $hgc_table WHERE id = %d", 1) );
	$rating = strtolower(get_option('avatar_rating'));
	if ($get_option==1) {$copy_option="WordPress Filesystem (file_get_contents)";}
	if ($get_option==2) {$copy_option="WordPress Remote Fopen (fopen / cUrl)";}
	if ($get_option==3) {$copy_option="WordPress Remote Fopen (fopen / cUrl)";}
	if ($get_option==4) {$copy_option="PHP copy";}
	$cache_day=0;
	$cache_week=0;
	$copy_available=0;
	if ($cache_time==1440) {$cache_week=0;$cache_day=1;}
	if ($cache_time==2880) {$cache_week=0;$cache_day=2;}
	if ($cache_time==4320) {$cache_week=0;$cache_day=3;}
	if ($cache_time==5760) {$cache_week=0;$cache_day=4;}
	if ($cache_time==7200) {$cache_week=0;$cache_day=5;}
	if ($cache_time==8640) {$cache_week=0;$cache_day=6;}
	if ($cache_time==10080) {$cache_week=1;$cache_day=0;}
	if ($cache_time==20160) {$cache_week=2;$cache_day=0;}
	if ($cache_time==30240) {$cache_week=3;$cache_day=0;}
	if ($cache_time==40320) {$cache_week=4;$cache_day=0;}
	if ($cache_time==50400) {$cache_week=5;$cache_day=0;}
	if ($cache_time==60480) {$cache_week=6;$cache_day=0;}
	if ($cache_time==70560) {$cache_week=7;$cache_day=0;}
	if ($cache_time==80640) {$cache_week=8;$cache_day=0;}
	$database_set_wrong=0;
	if ( (empty($size) || $size<20 || $size>200) || (empty($size_get) || $size_get<1 || $size_get>3) || (empty($get_option) || $get_option>4 || $get_option<1) || $cache_time<1440 || $is_writeable!=1 || (empty($check_cache) || $check_cache<1 || $check_cache>2) ) {$database_set_wrong=1;}
	$path_ok=false;
	$pathperm=$wp_filesystem->getchmod($path);
	if ( $wp_filesystem->is_dir($path) ) {
		$path_ok=1;
	}
	if ($file_get_contents==1 || $fopen==1 || $curl==1 || $copy==1) {$copy_available=1;}
	?>
		<form id="harrys-gravatar-cache" action="" method="post">
			<?php if ($database_set_wrong==1) {?>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<td><font style="font-size:20px;color:red;">
									<?php printf(__('Watch out!! The database table "%1$s" could not be filled with the neccessary options for this plugin!!','harrys-gravatar-cache'),$hgc_table); ?>
								</font><br><br>
								<?php _e('Here is an overview of the options:','harrys-gravatar-cache');?><br>
								<?php _e('Gravatar Size:','harrys-gravatar-cache');?>
								<?php if (empty($size) || $size<20) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number between 20 and 200','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
								<?php _e('How to get the Gravatar Size:','harrys-gravatar-cache');?>
								<?php if (empty($size_get) || $size_get<1 || $size_get>3) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number between 1 and 3','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
								<?php _e('Copy option:','harrys-gravatar-cache');?>
								<?php if (empty($get_option) || $get_option>4 || $get_option<1) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number between 1 and 4','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
								<?php _e('Cache time:','harrys-gravatar-cache');?>
								<?php if (empty($cache_time) || $cache_time<1440) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number in seconds between 1440 and 80640','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
								<?php _e('Cache folder writeable:','harrys-gravatar-cache');?>
								<?php if ($is_writeable!=1) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number 0 or 1','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
								<?php _e('Cache checking:','harrys-gravatar-cache');?>
								<?php if (empty($check_cache) || $check_cache<1 || $check_cache>2) {?><font style="color:red"><?php _e('false or empty','harrys-gravatar-cache');?></font>, <?php _e('should be a number 1 or 2','harrys-gravatar-cache');?><?php } else { ?><font style="color:green">ok</font><?php } ?><br>
							</td>
						</tr>


						<?php if (empty($size) || $size<20 || $size>200 || empty($size_get) || $size_get<1 || $size_get>3 ) { ?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The size for the Gravatars is not set correctly or is not present!!','harrys-gravatar-cache');?>
								</font><br>
							</tr>
							<p>
								<tr valign="top">
								<td>
									<input class="button" type="submit" name="get_size_gravatar_hgc" value="<?php _e('Try to get the Gravatar size from the template','harrys-gravatar-cache'); ?>"/>
								</td>
							</tr>
							</p>
						<?php } ?>

						<?php if ( empty($get_option) || ($get_option>4 || $get_option<1) && $is_writeable==1 && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! NO option for getting the Gravatars is available!!','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please contact your hoster to make one of these functions available:','harrys-gravatar-cache');?>
								<br>"file_get_contents"<br>"fopen"<br>"cUrl"<br>"PHP copy"</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="get_copy_options" value="<?php _e('check the server for available copy options','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if (empty($cache_time) || $cache_time<1440) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The caching time is not set!!','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please press the button to set the default time.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="set_time" value="<?php _e('set default time','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($is_writeable!=1 && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The caching folder is not writeable!!','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please change the permissions to 0755 for the caching folder "','harrys-gravatar-cache');?>
								<font style="color:green;"><strong><?php echo $path; ?></strong></font>
								<?php _e('" and check.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
									<input class="button" type="submit" name="is_writeable" value="<?php _e('check if the caching folder is writeable','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($path_ok!=1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The caching folder is not exist!!','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please press the button to create the caching folder.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="make_folder" value="<?php _e('create folder','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if (empty($check_cache) || $check_cache<1 || $check_cache>2) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The option to check the cached gravatars is not set.','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please press the button to set the default option(database).','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="set_check" value="<?php _e('set default check','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($pathperm!="755" && $pathperm!="775" && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
										<?php _e('Watch out!! The permissions of the caching folder are not correct!!','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please press the button to correct the permissions for the caching folder or change manually the permissions to 0755 or 0775.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="correct_folder" value="<?php _e('correct permissions to 0755','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="correct_folder2" value="<?php _e('correct permissions to 0775','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
						<tr valign="top">
							<td>
								<?php _e('These error(s) can not be solved with the plugin settings. Try to enable debugging and check the log-files. After you found a reason for the issue and eliminate this, try to deactivate and deinstall and after this reinstall the plugin.','harrys-gravatar-cache'); ?>
								<br>
							</td>
						</tr>
						<tr valign="top">
							<td>
								<?php printf(__('The database table "%1$s" should look like this:','harrys-gravatar-cache'),$hgc_table); ?>
								<br>
								<img src="<?php echo plugin_dir_url( __FILE__ );?>database.png" />
							</td>
						</tr>
					</tbody>
				</table>
				<?php wp_nonce_field( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options', false ); ?>
			<?php } else { ?>
				<table class="form-table">
					<tbody>
						<?php if (empty($rating)) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="color:orange;">
										<?php _e('Watch out!! the rating option for Gravatars is empty. It is set to rating "R" for getting the (Gr)Avatars.','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please check the rating on your <a href="options-discussion.php">"Discussion setting page"</a>.','harrys-gravatar-cache');?>
								</td>
							</tr>
						<?php } ?>

						<?php if ($default=="wapuuvatar" && $no_hgc==1 && !empty($show_avatar)) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="color:orange;">
										<?php _e('Watch out!! You choose "Random wapuus everywhere (No gravatars at all)" for the default Gravatars. No caching is required to get the (Gr) avatars.','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please check the default Gravatar on your <a href="options-discussion.php">"Discussion setting page"</a>.','harrys-gravatar-cache');?>
								</td>
							</tr>
						<?php } ?>

						<?php if (empty($show_avatar) && $no_hgc==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="color:orange;">
										<?php _e("Watch out!! You don't want to display any avatars. No caching is required to get the (Gr) avatars.",'harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please check the settings on your <a href="options-discussion.php">"Discussion setting page"</a>.','harrys-gravatar-cache');?>
								</td>
							</tr>
						<?php } ?>

						<?php if ($default!=$stored_default && $default!="wapuuvatar") {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="color:orange;">
										<?php printf(__('Watch out!! You changed the default Gravatar from "%1$s" to "%2$s". You should empty the cache.','harrys-gravatar-cache'),$stored_default, $default); ?>
									</font>
								</td>
							</tr>
						<?php } ?>

						<?php if ($path_ok!=1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
									<?php _e('Watch out!! The caching folder is not exist!!','harrys-gravatar-cache');?>
								</font><br>
								<?php _e('Please press the button to create the caching folder.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="make_folder" value="<?php _e('create folder','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($pathperm!="755" && $pathperm!="775" && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
										<?php _e('Watch out!! The permissions of the caching folder are not correct!!','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please press the button to correct the permissions for the caching folder or change manually the permissions to 0755 or 0775.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="correct_folder" value="<?php _e('correct permissions to 0755','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="correct_folder2" value="<?php _e('correct permissions to 0775','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($is_writeable!=1 && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
										<?php _e('Watch out!! The caching folder is not writeable!!','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please change the permissions to 0755 for the caching folder "','harrys-gravatar-cache');?>
									<font style="color:green;"><strong><?php echo $path; ?></strong></font>
									<?php _e('" and check.','harrys-gravatar-cache');?>
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="is_writeable" value="<?php _e('check if the caching folder is writeable','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ( ($get_option>4 || $get_option<1) && $is_writeable==1 && $path_ok==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<td><font style="font-size:20px;color:red;">
										<?php _e('Watch out!! NO option for getting the Gravatars is available!!','harrys-gravatar-cache');?>
									</font><br>
									<?php _e('Please contact your hoster to make one of these functions available:','harrys-gravatar-cache');?>
									<br>"file_get_contents"<br>"fopen"<br>"cUrl"<br>"PHP copy"
								</td>
							</tr>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="get_copy_options" value="<?php _e('check the server for available copy options','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>
					</tbody>
				</table>
				<hr>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><?php _e('current Gravatar size:','harrys-gravatar-cache'); ?></th><td><?php echo $size; ?> px, <?php if ($size_get==1) {_e('set by template','harrys-gravatar-cache');if ($active_theme_wp!=$stored_theme) {echo "&nbsp;-&nbsp;"; _e('Watch out!! The template has changed!!','harrys-gravatar-cache');}}else if ($size_get==2) {_e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3) {_e('set by you','harrys-gravatar-cache');} ?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('change Gravatar size:','harrys-gravatar-cache'); ?></th>
							<td>
								<select name="size">
									<?php if ($size!=40 && $size!=44 && $size!=67 && $size!=80 && $size!=96 && $size!=120) {?><option value="<?php echo $size; ?>" <?php if ($size_get!=1) echo "selected=selected";?>><?php echo $size; ?> px, <?php if ($size_get==1) {_e('set by template','harrys-gravatar-cache');}else if ($size_get==2) {_e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3) {_e('set by you','harrys-gravatar-cache');} ?></option><?php } ?>
									<option value="40" <?php if ($size==40) echo "selected=selected";?>>40 px<?php if ($size_get==1 && $size==40) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==40) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==40) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="44" <?php if ($size==44) echo "selected=selected";?>>44 px<?php if ($size_get==1 && $size==44) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==44) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==44) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="67" <?php if ($size==67) echo "selected=selected";?>>67 px<?php if ($size_get==1 && $size==67) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==67) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==67) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="80" <?php if ($size==80) echo "selected=selected";?>>80 px<?php if ($size_get==1 && $size==80) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==80) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==80) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="96" <?php if ($size==96) echo "selected=selected";?>>96 px<?php if ($size_get==1 && $size==96) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==96) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==96) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="120" <?php if ($size==120) echo "selected=selected";?>>120 px<?php if ($size_get==1 && $size==120) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get==2 && $size==120) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size==120) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('or enter Gravatar size manually (20-200 px):','harrys-gravatar-cache'); ?></th>
							<td><input pattern="[0-9]{2,4}" style="width:75px" min="20" max="200" step="1" name="size_man" type="number" value="" /> px
							</td>
						</tr>

						<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
						<tr valign="top">
							<th scope="row"><?php _e('second Gravatar size:','harrys-gravatar-cache'); ?>*<br><small>*<?php _e('some themes maybe have a second Gravatar size for the sidebar','harrys-gravatar-cache'); ?></small></th><td><?php echo $size2; ?> px, <?php if ($size_get2==1) {_e('set by template','harrys-gravatar-cache');if ($active_theme_wp!=$stored_theme) {echo "&nbsp;-&nbsp;"; _e('Watch out!! The template has changed!!','harrys-gravatar-cache');}}else if ($size_get2==2) {_e('set by plugin','harrys-gravatar-cache');}else if ($size_get2==3) {_e('set by you','harrys-gravatar-cache');} ?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('change second Gravatar size:','harrys-gravatar-cache'); ?></th>
							<td>
								<select name="size2">
									<?php if ($size2!=0 && $size2!=40 && $size2!=44 && $size2!=67 && $size2!=80 && $size2!=96 && $size2!=120) {?><option value="<?php echo $size2; ?>" <?php if ($size_get2!=1) echo "selected=selected";?>><?php echo $size2; ?> px, <?php if ($size_get2==1) {_e('set by template','harrys-gravatar-cache');}else if ($size_get2==2) {_e('set by plugin','harrys-gravatar-cache');}else if ($size_get2==3) {_e('set by you','harrys-gravatar-cache');} ?></option><?php } ?>
									<option value="0" <?php if ($size2==0) echo "selected=selected";?>>0 px<?php if ($size_get2==1 && $size2==0) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==0) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==0) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="40" <?php if ($size2==40) echo "selected=selected";?>>40 px<?php if ($size_get2==1 && $size2==40) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==40) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==40) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="44" <?php if ($size2==44) echo "selected=selected";?>>44 px<?php if ($size_get2==1 && $size2==44) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==44) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==44) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="67" <?php if ($size2==67) echo "selected=selected";?>>67 px<?php if ($size_get2==1 && $size2==67) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==67) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==67) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="80" <?php if ($size2==80) echo "selected=selected";?>>80 px<?php if ($size_get2==1 && $size2==80) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==80) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==80) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="96" <?php if ($size2==96) echo "selected=selected";?>>96 px<?php if ($size_get2==1 && $size2==96) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==96) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get==3 && $size2==96) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
									<option value="120" <?php if ($size2==120) echo "selected=selected";?>>120 px<?php if ($size_get2==1 && $size2==120) { ?>, <?php _e('set by template','harrys-gravatar-cache');}else if ($size_get2==2 && $size2==120) { ?>, <?php _e('set by plugin','harrys-gravatar-cache');}else if ($size_get2==3 && $size2==120) { ?>, <?php _e('set by you','harrys-gravatar-cache');} ?></option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('or enter second Gravatar size manually (1-200 px):','harrys-gravatar-cache'); ?></th>
							<td><input pattern="[0-9]{1,4}" style="width:75px" min="1" max="200" step="1" name="size_man2" type="number" value="" /> px
							</td>
						</tr>

						<?php if ($size_get!=1 || $active_theme_wp!=$stored_theme || $size_get2!=1) { ?>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="get_size_gravatar_hgc" value="<?php _e('Try to get the Gravatar size from the template','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>

						<?php if ($is_writeable==1 && $path_ok==1 && $copy_available==1) {?>
							<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
							<tr valign="top">
								<th scope="row"><?php _e('your server accepts the following copy options to get the Gravatar:','harrys-gravatar-cache'); ?></th><td><?php if ($file_get_contents==1) {echo "file_get_contents (<strong>wp_filesystem</strong>)<br>";}if ($fopen==1) {echo "fopen (<strong>wp_remote_fopen</strong>)<br>";}if ($curl==1) {echo "cUrl (<strong>wp_remote_fopen</strong>)<br>";}if ($copy==1) {echo "PHP copy<br>";}?></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e('current copy option:','harrys-gravatar-cache'); ?></th><td><?php echo $copy_option; ?></td>
							</tr>
							<?php
							$count_copyoption=0;
							if ($file_get_contents==1 || $fopen==1 || $curl==1 || $copy==1) { $count_copyoption++;}
							if ($count_copyoption>1) {
							?>
								<tr valign="top">
									<th scope="row"><?php _e('change copy option:','harrys-gravatar-cache'); ?></th>
									<td>
										<select name="copy_option">
											<?php if ($file_get_contents==1) {?><option value="1" <?php if ($get_option==1) echo "selected=selected";?>>WordPress Filesystem (file_get_contents)</option><?php } ?>
											<?php if ($fopen==1 || $curl==1) {?><option value="2" <?php if ($get_option==2 || $get_option==3) echo "selected=selected";?>>WordPress Remote Fopen (fopen / cUrl)</option><?php } ?>
											<?php if ($copy==1) {?><option value="4" <?php if ($get_option==4) echo "selected=selected";?>>PHP copy</option><?php } ?>
										</select>
									</td>
								</tr>
							<?php } ?>
							<p>
								<tr valign="top">
									<td>
										<input class="button" type="submit" name="get_copy_options" value="<?php _e('check the server for available copy options','harrys-gravatar-cache'); ?>"/>
									</td>
								</tr>
							</p>
						<?php } ?>
						<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
						<tr valign="top">
							<th scope="row"><?php _e('current Cache time:','harrys-gravatar-cache'); ?></th><td><?php if ($cache_week>0) {echo $cache_week;}if ($cache_day>0) {echo $cache_day;} if ($cache_week<2 && $cache_day==0) {_e(' week','harrys-gravatar-cache');}else if ($cache_week>1 && $cache_day==0) {_e(' weeks','harrys-gravatar-cache');}else if ($cache_day<2 && $cache_week==0) {_e(' day','harrys-gravatar-cache');}else if ($cache_day>1 && $cache_week==0) {_e(' days','harrys-gravatar-cache');}?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('change Cache time:','harrys-gravatar-cache'); ?></th>
							<td>
								<select name="cache-time">
									<option value="1440" <?php if ($cache_time==1440) echo "selected=selected";?>>1<?php _e(' day','harrys-gravatar-cache');?></option>
									<option value="2880" <?php if ($cache_time==2880) echo "selected=selected";?>>2<?php _e(' days','harrys-gravatar-cache');?></option>
									<option value="4320" <?php if ($cache_time==4320) echo "selected=selected";?>>3<?php _e(' days','harrys-gravatar-cache');?></option>
									<option value="5760" <?php if ($cache_time==5760) echo "selected=selected";?>>4<?php _e(' days','harrys-gravatar-cache');?></option>
									<option value="7200" <?php if ($cache_time==7200) echo "selected=selected";?>>5<?php _e(' days','harrys-gravatar-cache');?></option>
									<option value="8640" <?php if ($cache_time==8640) echo "selected=selected";?>>6<?php _e(' days','harrys-gravatar-cache');?></option>
									<option value="10080" <?php if ($cache_time==10080) echo "selected=selected";?>>1<?php _e(' week','harrys-gravatar-cache');?></option>
									<option value="20160" <?php if ($cache_time==20160) echo "selected=selected";?>>2<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="30240" <?php if ($cache_time==30240) echo "selected=selected";?>>3<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="40320" <?php if ($cache_time==40320) echo "selected=selected";?>>4<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="50400" <?php if ($cache_time==50400) echo "selected=selected";?>>5<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="60480" <?php if ($cache_time==60480) echo "selected=selected";?>>6<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="70560" <?php if ($cache_time==70560) echo "selected=selected";?>>7<?php _e(' weeks','harrys-gravatar-cache');?></option>
									<option value="80640" <?php if ($cache_time==80640) echo "selected=selected";?>>8<?php _e(' weeks','harrys-gravatar-cache');?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<hr>
				<h2><?php _e('Other options','harrys-gravatar-cache'); ?></h2>
				<table class="form-table">
					<tbody>
						<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
						<tr valign="top">
							<th scope="row"><small><?php _e('Can reduce the file size but you should compare the file size between JPG and PNG after caching on the server:','harrys-gravatar-cache')?></small></th><td></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('PNG files are generated from JPG files:','harrys-gravatar-cache'); ?></th><td><?php if ($make_png==2) {_e('yes','harrys-gravatar-cache');}else if ($make_png==1) {_e('no','harrys-gravatar-cache');}?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Create PNG files from JPG files?','harrys-gravatar-cache'); ?></th>
							<td>
								<select name="make-png">
									<option value="1" <?php if ($make_png==1) echo "selected=selected";?>><?php _e('no','harrys-gravatar-cache');?></option>
									<option value="2" <?php if ($make_png==2) echo "selected=selected";?>><?php _e('yes','harrys-gravatar-cache');?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
				<table class="form-table">
					<tbody>
						<tr valign="top"><th scope="row"><hr style="border-top: dotted 1px;" /></th></tr>
						<tr valign="top">
							<th scope="row"><small><?php _e('On some sites the checking with the filesystem could be faster:','harrys-gravatar-cache')?></small></th><td></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Whether Gravatar has already been cached is checked via:','harrys-gravatar-cache'); ?></th><td><?php if ($check_cache==1) {_e('Database','harrys-gravatar-cache');}else if ($check_cache==2) {_e('Filesystem','harrys-gravatar-cache');}?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Check whether Gravatar has already been cached with?:','harrys-gravatar-cache'); ?></th>
							<td>
								<select name="check-cache">
									<option value="1" <?php if ($check_cache==1) echo "selected=selected";?>><?php _e('Database','harrys-gravatar-cache');?></option>
									<option value="2" <?php if ($check_cache==2) echo "selected=selected";?>><?php _e('Filesystem','harrys-gravatar-cache');?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<?php wp_nonce_field( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options', false ); ?>
					<input class="button-primary" type="submit" name="harry_gravatar_save" value="<?php _e('Save changes','harrys-gravatar-cache'); ?>"/>
					<input class="button" type="submit" name="harry_gravatar_empty_cache" value="<?php _e('Empty Cache','harrys-gravatar-cache'); ?>"/>
					<?php if ($get_option<5 && $get_option>0 && $is_writeable==1 && $path_ok==1 && ($pathperm=="0755" || $pathperm=="0775") && empty($no_hgc) ) { ?>
						<br /><br />
						<input class="button" id="pre" type="submit" name="harry_gravatar_pre_cache" value="<?php _e('Build the cache & check','harrys-gravatar-cache'); ?>"/>
						<label for="pre"> <small>*<?php _e('to generate the cache complete new, empty the cache:','harrys-gravatar-cache'); ?></small></label>
						<label>
						<input id="pre_empty" type="checkbox" name="harry_gravatar_pre_empty_cache" />
						<?php _e('Empty Cache','harrys-gravatar-cache'); ?></label>
					<?php } ?>
				</p>
				<hr>
				<?php if ($get_option<5 && $get_option>0 && $is_writeable==1 && $path_ok==1 && ($pathperm=="0755" || $pathperm=="0775") ) { ?>

					<div id=precachecheck<?php if ( !isset($_POST['harry_gravatar_pre_cache']) ) { ?> style="display:none"<?php } ?>>
					<?php
					$counter_gravatar=0;
					$precached_db=$wpdb->get_results("SELECT filename, filetype, srcset, date_time FROM $hgc_store WHERE blank != 'blank' ORDER BY size ASC");
					foreach($precached_db as $row)
					{
						$counter_gravatar++;
					}
					if ($counter_gravatar>0) {
					?>
						<h2><?php _e('Build the cache & check the caching','harrys-gravatar-cache'); ?></h2>
						<?php _e('the following different gravatars and sizes','harrys-gravatar-cache'); ?><small>*</small><br>
						<small>*<?php _e('depends on your theme and the size for the Gravatars and the appearance in sidebars with other sizes','harrys-gravatar-cache'); ?></small><br><br>
						<?php
						$counter_gravatar=0;
						$precached_db=$wpdb->get_results("SELECT filename, filetype, srcset, date_time FROM $hgc_store WHERE blank != 'blank' ORDER BY size ASC");
						foreach($precached_db as $row2)
						{
							$counter_gravatar++;
						}
						?>
						<?php if ($counter_gravatar>6) { ?>
							<a href='#' class='lnk_more'><?php printf(__('click to expand all %1$s cached different Gravatars','harrys-gravatar-cache'),$counter_gravatar); ?></a><br><br>
						<?php } ?>
						<table border="0" style="border-collapse: separate;border-spacing: 10px 10px;"><tbody>
							<?php
							$counter_gravatar=0;
							foreach($precached_db as $row3)
							{
								if ($counter_gravatar==0 || is_integer($counter_gravatar/3)) { ?>
									<tr valign="middle">
								<?php } ?> 
								<td align="center"<?php if ($counter_gravatar>5) { ?> class='show_gravatar' <?php } ?>style="<?php if ($counter_gravatar>5) { ?>display:none;<?php } ?>box-shadow:0px 0px 7px 2px rgba(87,87,87,1)">
									<div <?php if ($counter_gravatar>5) { ?>class='show_gravatar' <?php } ?>style='<?php if ($counter_gravatar>5) { ?>display:none;<?php } ?>margin-right:20px;margin-bottom:10px;padding:5px'>
										<?php
										$src1=esc_url($cache_url.$row3->filename.$row3->filetype);
										$size_check = wp_remote_fopen($src1);
										$size_check = getimagesizefromstring($size_check);
										if ($row3->srcset=='yes') {
											$src2=esc_url($cache_url.$row3->filename."_2x".$row3->filetype);
											$size_checkx2 = wp_remote_fopen($src2);
											$size_checkx2 = getimagesizefromstring($size_checkx2);
										} ?>
										<div><?php echo $size_check[0]; ?> px <?php if ($row3->srcset=='yes') { ?> <?php _e('and for','harrys-gravatar-cache'); ?> srcset <?php echo $size_checkx2[0]; ?> px <?php } ?>:</div>
										<img style='margin-right:10px' alt='Gravatar' src='<?php echo $src1; ?>' height='<?php echo $size_check[1]; ?>' width='<?php echo $size_check[0]; ?>' />
										<?php
										if ($row3->srcset=='yes') {
											$src2=$cache_url.$row3->filename."_2x".$row3->filetype;
											?>
											<img alt='Gravatar' src='<?php echo $src2; ?>' height='<?php echo $size_checkx2[1]; ?>' width='<?php echo $size_checkx2[0]; ?>' />
										<?php } ?>
										<div style='clear:both'></div>
										<?php $date = new DateTime($row3->date_time); ?>
										<?php _e('cached on','harrys-gravatar-cache'); ?>: <?php echo $date->format('d.m.y - H:i:s'); ?><br />
									</div>
								</td>
								<?php
								$counter_gravatar++;
								if (is_integer($counter_gravatar/3)) { ?>
									</tr>
								<?php }
							} ?>
						</tbody></table>
						<?php
						if ($counter_gravatar>6) {
						?>
							<div style='clear:both'></div><a href='#' class='lnk_more'><?php printf(__('click to expand all %1$s cached different Gravatars','harrys-gravatar-cache'),$counter_gravatar); ?></a>
						<?php } ?>
						<div style='clear:both'></div>
						<br><br>
						<?php _e('were cached from the following different URLs','harrys-gravatar-cache'); ?>:
						<?php
						$get_urls=$wpdb->get_results("SELECT post_url FROM $hgc_store");
						$proof_url=0;
						foreach($get_urls as $row)
						{
							$permalink_get[$proof_url]=$row->post_url;
							$proof_url++;
						}
						$permalink_check=array_unique ( $permalink_get );
						foreach($permalink_check as $permalink_out)
						{ ?>
							<br><a target='_blank' href='<?php echo $permalink_out; ?>'><?php echo $permalink_out; ?></a>
						<?php } ?>
						<hr>
						<script>
							jQuery(document).on('click', '.lnk_more', function(e){
								e.preventDefault();
								jQuery('.lnk_more').addClass('close_show_gravatar');
								jQuery('.close_show_gravatar').removeClass('lnk_more');
								jQuery('.show_gravatar').show(100);
								jQuery('.close_show_gravatar').html('<?php _e('click to show only the first 6 different cached Gravatars','harrys-gravatar-cache'); ?>');
							});
							jQuery(document).on('click', '.close_show_gravatar', function(e){
								e.preventDefault();
								jQuery('.close_show_gravatar').addClass('lnk_more');
								jQuery('.lnk_more').removeClass('close_show_gravatar');
								jQuery('.show_gravatar').hide(100); 
								jQuery('.lnk_more').html('<?php printf(__('click to expand all %1$s cached different Gravatars','harrys-gravatar-cache'),$counter_gravatar); ?>');
							});
						</script>
					<?php }#if 0 ?>
					</div>
					<h2><?php _e('Statistics','harrys-gravatar-cache'); ?></h2>
					<table id="stats" class="form-table">
						<tbody>
							<?php
							$count=0;
							$filesize=0;
							$dirlist = $wp_filesystem->dirlist($path);
							foreach ( (array) $dirlist as $filename => $fileinfo ) {
								if ( 'f' == $fileinfo['type'] ) {
									$filesize=$filesize+$fileinfo['size'];
									$count++;
								}
							}
							if ($filesize>1024000) {
								$filesize_show=round($filesize/1024/1024, 2);
								$filesize_show=$filesize_show." MBytes";
							}
							else if ($filesize>10240) {
								$filesize_show=round($filesize/1024, 2);
								$filesize_show=$filesize_show." kBytes";
							}
							else {
								$filesize_show=$filesize." Bytes";
							}
							?>
							<tr valign="top">
								<th scope="row"><?php _e('Number of files in cache:','harrys-gravatar-cache'); ?></th><td><?php echo $count; ?></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e('Filesize of cached files:','harrys-gravatar-cache'); ?></th><td><?php echo $filesize_show; ?></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e('Cache URL:','harrys-gravatar-cache'); ?></th><td><?php echo $cache_url; ?></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e('Cache Path:','harrys-gravatar-cache'); ?></th><td><?php echo $path; ?></td>
							</tr>
						</tbody>
					</table>
				<?php } ?>
		<?php } ?>
			</form>
		</div>
<?php } else {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'harrys-gravatar-cache' ) );
	}
}
  
/* Einstellungen speichern */
function save_settings() {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $comment_table;
	global $path;
	global $cache_url;
	if (stripos($_SERVER['REQUEST_URI'],'/options-general.php?page=harrys-gravatar-cache-options')!==FALSE) {
		global $wpdb;
		if ( ( isset($_POST['size']) || isset($_POST['size_man']) || isset($_POST['size2']) || isset($_POST['size_man2']) ) && isset($_POST['harry_gravatar_save']) && !isset($_POST['get_size_gravatar_hgc']) && !isset($_POST['harry_gravatar_empty_cache']) && !isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) && !isset($_POST['get_copy_options']) && !isset($_POST['is_writeable']) && !isset($_POST['make_folder']) && !isset($_POST['set_time']) && !isset($_POST['set_check']) && !isset($_POST['correct_folder']) && !isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$size=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
			$size2=$wpdb->get_var($wpdb->prepare("SELECT size2 FROM $hgc_table WHERE id = %d", 1) );
			if ($size!=$_POST['size'] || $size!=$_POST['size_man']) {
				if ($_POST['size_man']!=0 || !empty($_POST['size_man'])) {
					if ($size!=$_POST['size_man']) {
						$wpdb->update($hgc_table, array('size' => $_POST['size_man'], 'size_get' => '3'), array('id' => 1), array('%d', '%d'));
						empty_cache();
					}
					else {
						$wpdb->update($hgc_table, array('size' => $_POST['size_man']), array('id' => 1), array('%d', '%d'));
					}
				}
				else {
					if ($size!=$_POST['size']) {
						$wpdb->update($hgc_table, array('size' => $_POST['size'], 'size_get' => '3'), array('id' => 1), array('%d', '%d'));
						empty_cache();
					}
					else {
						$wpdb->update($hgc_table, array('size' => $_POST['size']), array('id' => 1), array('%d', '%d'));
					}
				}
			}

			if ($size2!=$_POST['size2'] || $size2!=$_POST['size_man2']) {
				if ($_POST['size_man2']!=0 || !empty($_POST['size_man2'])) {
					if ($size2!=$_POST['size_man2']) {
						$wpdb->update($hgc_table, array('size2' => $_POST['size_man2'], 'size_get2' => '3'), array('id' => 1), array('%d', '%d'));
						empty_cache();
					}
					else {
						$wpdb->update($hgc_table, array('size2' => $_POST['size_man2']), array('id' => 1), array('%d', '%d'));
					}
				}
				else {
					if ($size2!=$_POST['size2']) {
						$wpdb->update($hgc_table, array('size2' => $_POST['size2'], 'size_get2' => '3'), array('id' => 1), array('%d', '%d'));
						empty_cache();
					}
					else {
						$wpdb->update($hgc_table, array('size2' => $_POST['size2']), array('id' => 1), array('%d', '%d'));
					}
				}
			}
		}
		if (isset($_POST['copy_option']) && isset($_POST['harry_gravatar_save']) && !isset($_POST['get_size_gravatar_hgc']) && !isset($_POST['harry_gravatar_empty_cache']) && !isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) && !isset($_POST['get_copy_options']) && !isset($_POST['is_writeable']) && !isset($_POST['make_folder']) && !isset($_POST['set_time']) && !isset($_POST['set_check']) && !isset($_POST['correct_folder']) && !isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$get_option=$wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) );
			if ($get_option!=$_POST['copy_option']) {
				$wpdb->update($hgc_table, array('get_option' => $_POST['copy_option']), array('id' => 1), array('%d'));
			}
		}
		if (isset($_POST['cache-time']) && isset($_POST['harry_gravatar_save']) && !isset($_POST['get_size_gravatar_hgc']) && !isset($_POST['harry_gravatar_empty_cache']) && !isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) && !isset($_POST['get_copy_options']) && !isset($_POST['is_writeable']) && !isset($_POST['make_folder']) && !isset($_POST['set_time']) && !isset($_POST['set_check']) && !isset($_POST['correct_folder']) && !isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$cache_time=$wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) );
			if (!empty($_POST['cache-time'])) {
				if ($cache_time!=$_POST['cache-time']) {
					$wpdb->update($hgc_table, array('cache_time' => $_POST['cache-time']), array('id' => 1), array('%d'));
				}
			}
		}
		if (isset($_POST['make-png']) && isset($_POST['harry_gravatar_save']) && !isset($_POST['get_size_gravatar_hgc']) && !isset($_POST['harry_gravatar_empty_cache']) && !isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) && !isset($_POST['get_copy_options']) && !isset($_POST['is_writeable']) && !isset($_POST['make_folder']) && !isset($_POST['set_time']) && !isset($_POST['set_check']) && !isset($_POST['correct_folder']) && !isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$make_png=$wpdb->get_var($wpdb->prepare("SELECT make_png FROM $hgc_table WHERE id = %d", 1) );
			if (!empty($_POST['make-png'])) {
				if ($make_png!=$_POST['make-png']) {
					$wpdb->update($hgc_table, array('make_png' => $_POST['make-png']), array('id' => 1), array('%d'));
					empty_cache();
				}
			}
		}
		if (isset($_POST['check-cache']) && isset($_POST['harry_gravatar_save']) && !isset($_POST['get_size_gravatar_hgc']) && !isset($_POST['harry_gravatar_empty_cache']) && !isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) && !isset($_POST['get_copy_options']) && !isset($_POST['is_writeable']) && !isset($_POST['make_folder']) && !isset($_POST['set_time']) && !isset($_POST['set_check']) && !isset($_POST['correct_folder']) && !isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$check_cache=$wpdb->get_var($wpdb->prepare("SELECT check_cache FROM $hgc_table WHERE id = %d", 1) );
			if (!empty($_POST['check-cache'])) {
				if ($check_cache!=$_POST['check-cache']) {
					$wpdb->update($hgc_table, array('check_cache' => $_POST['check-cache']), array('id' => 1), array('%d'));
				}
			}
		}
		if (isset($_POST['make_folder'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			make_folder();
			is_writeable_proof();
		}
		if (isset($_POST['set_time'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			set_time();
		}
		if (isset($_POST['set_check'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			set_check();
		}
		if (isset($_POST['correct_folder'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			correct_folder();
			is_writeable_proof();
		}
		if (isset($_POST['correct_folder2'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			correct_folder2();
			is_writeable_proof();
		}
		if (isset($_POST['is_writeable'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			is_writeable_proof();
		}
		if (isset($_POST['get_size_gravatar_hgc'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			$size1=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
			get_size_gravatar_hgc();
			$size1_compare=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
			if ($size1!=$size1_compare) {
				empty_cache();
			}
		}
		if (isset($_POST['get_copy_options'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			get_copy_options();
		}
		if (isset($_POST['harry_gravatar_empty_cache'])) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			empty_cache();
		}
		if ( isset($_POST['harry_gravatar_pre_cache']) && !isset($_POST['harry_gravatar_pre_empty_cache']) ) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			pre_cache();
		}
		if ( isset($_POST['harry_gravatar_pre_cache']) && isset($_POST['harry_gravatar_pre_empty_cache']) ) {
			check_ajax_referer( 'harrys_gravatar_cache_options', 'harrys_gravatar_cache_options' );
			empty_cache();
			pre_cache();
		}
	}
}

/* Caching und return Funktion */
function gravatar_lokal ($avatar, $id_or_email, $size, $default, $alt, $args) {
	global $wpdb;
	global $hgc_table;
	global $hgc_store;
	global $comment_table;
	global $in_comment_loop;
	global $path;
	global $cache_url;
	global $wp_filesystem;
	global $hovercard;
	$hgc_precache=NULL;
	$hgc_precache2=NULL;
	$konst=NULL;
	$blank=NULL;
	$cache_time=$wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) );
	$make_png=$wpdb->get_var($wpdb->prepare("SELECT make_png FROM $hgc_table WHERE id = %d", 1) );
	$check_cache=$wpdb->get_var($wpdb->prepare("SELECT check_cache FROM $hgc_table WHERE id = %d", 1) );
	$sizedb=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
	$no_mail=0;
	if ( current_user_can('manage_options') && $alt=="hgc_precache" ) {
		$comm_post=$size;
		$permalink=get_permalink($comm_post);
		if (empty($args)) {
			$size = $sizedb;
		}
		else {
			$size=$args;
		}
		$comment_author_url=$default;
		$default=get_option('avatar_default');
		$comment_author=$wpdb->get_var($wpdb->prepare("SELECT comment_author_url FROM $comment_table WHERE comment_author_email = '$id_or_email' AND comment_post_ID = %d", $comm_post) );
		if ($default=="wapuuvatar") {
			$hgc_precache="no_hgc_precache";
		}
		else {
			$hgc_precache=$alt;
		}
		$alt=$wpdb->get_var($wpdb->prepare("SELECT comment_author FROM $comment_table WHERE comment_author_email = '$id_or_email' AND comment_post_ID = %d", $comm_post) );
	}

	if ( current_user_can('manage_options') && $alt=="hgc_precache2" ) {
		$permalink=$default;
		$id_or_email=get_the_author_meta( 'user_email', $size );
		$default=get_option('avatar_default');
		if ($default=="wapuuvatar") {
			$hgc_precache2="no_hgc_precache";
		}
		else {
			$hgc_precache2=$alt;
		}
		$alt=get_the_author_meta( 'display_name', $size );#user_nicename unten auch abfragen bei is_numeric bzw bei alt
		if (empty($args)) {
			$size = $sizedb;
		}
		else {
			$size=$args;
		}
	}
	if (empty($size)) {
		$size = $sizedb;
	}
	$size_srcset=$size*2;
	if ( isset($in_comment_loop) || in_the_loop() || is_singular() || is_author() || is_home() || $hgc_precache=="hgc_precache" || $hgc_precache2=="hgc_precache2" ) #is_category()
	{
		preg_match( '/class=\'(.*?)\'/s', $avatar, $css);
		if (!empty($css)) {$css=$css[1];}
		preg_match( '/src=\'(.*?)\'/s', $avatar, $src_proof);
		if (!empty($src_proof)) {$src_proof=$src_proof[1];}
		if (empty($css)) {
			preg_match( '/class=\"(.*?)\"/s', $avatar, $css);
			if (!isset($css[1])) {
				$css[1]=null;
			}
		$css=$css[1];
		}
		if (empty($src_proof)) {
			preg_match( '/src=\"(.*?)\"/s', $avatar, $src_proof);
			$src_proof=$src_proof[1];
		}
		$new_host=1;
		$scheme=parse_url($src_proof, PHP_URL_SCHEME);
		$url_host=parse_url($src_proof, PHP_URL_HOST);
		if ($scheme!='https' && $scheme!='http') {
			$new_host=0;
		}
		$mail=@get_comment_author_email();
		if ( $hgc_precache=="hgc_precache" || $hgc_precache2=="hgc_precache2" || is_numeric($id_or_email) ) {
			$mail=$id_or_email;
		}
		if (is_author()) {
			$mail=@$mail->user_email;
		}
		if (empty($mail)) {
			if (in_the_loop()) {
				$mail=@get_the_author_meta('user_email');
			}
			else if (is_author()) {
				if (isset($_GET['author_name'])) {
					$mail=@get_user_by($author_name,$author_name);
					$mail=$mail->user_email;
				}
				else {
					if (is_numeric($id_or_email)) {
						$mail=@get_userdata($id_or_email);
						$mail=$mail->user_email;
					}
					else if (filter_var($id_or_email, FILTER_VALIDATE_EMAIL)) {
						$mail=$id_or_email;
					}
				}
			}
			else {
				$mail=@get_comment_author_url();
				$no_mail=1;
				if ( is_home() ) {
					$mail=$id_or_email;
					$no_mail=0;
				}
			}
		}
		$author=@get_comment_author();
			//Unterstützung für Avatar Manager
		if (is_plugin_active('avatar-manager/avatar-manager.php') ) {
			$avatar_type="not set";
			if ( is_numeric( $id_or_email ) ) {
				$id = (int) $id_or_email;
				$user = get_userdata( $id );
				if ( $user ) {
					$email = $user->user_email;
				}
			}
			else if ( is_object( $id_or_email ) ) {
				if ( ! empty( $id_or_email->user_id ) ) {
					$id = (int) $id_or_email->user_id;
					$user = get_userdata( $id );
					if ( $user ) {
						$email = $user->user_email;
					}
				}
				else if ( ! empty( $id_or_email->comment_author_email ) ) {
					$email = $id_or_email->comment_author_email;
				}
			}
			else {
				$email = $id_or_email;
				if ( $id = email_exists( $email ) ) {
					$user = get_userdata( $id );
				}
			}
			if ( isset( $user ) ) {
				$avatar_type = $user->avatar_manager_avatar_type;
			}
			if ( $avatar_type == 'custom' ) {
				return $avatar;
			}
		}
		if (empty($alt)) {$alt=$author;}
		$filename=md5( strtolower( $mail ) );
		$cachetime = $cache_time * 60;
		if ( !in_the_loop() && $in_comment_loop == false && ( is_singular() || is_home() ) ) { #Sidebars und home
			$filename=md5( strtolower( $id_or_email ) );
			$alt=$wpdb->get_var($wpdb->prepare("SELECT comment_author FROM $comment_table WHERE comment_author_email = %s", $id_or_email) );
			if (is_numeric($id_or_email) && $hgc_precache2!="hgc_precache2") {
				$user_info=@get_userdata($id_or_email);
				$mail=$user_info->user_email;
				if ( !empty($user_info->first_name) && !empty($user_info->last_name) ) {
					$alt=$user_info->first_name." ".$user_info->last_name;
				}
				else if ( !empty($user_info->display_name) ) {
					$alt=$user_info->display_name;
				}
				else if ( !empty($user_info->nickname) ) {
					$alt=$user_info->nickname;
				}
				$filename=md5( strtolower( $mail ) );
			}
		}
		$rating = strtolower(get_option('avatar_rating'));
		if (empty($rating)) {$rating="r";}
		$retina=0;
		$srcset="no";
		if (strpos($url_host,'gravatar.com')!==false && $no_mail==0) { //von gravatar.com
			if ($new_host==0) {
				$host = '//secure.gravatar.com/avatar/';
			}
			else {
				$host = $scheme.'://'.$url_host.'/avatar/';
			}
			$retina=1;
			$srcset="yes";
			$rating = strtolower(get_option('avatar_rating'));
			if (empty($default)) {$default=get_option('avatar_default');}
			if ($default=="gravatar_default") {$default=NULL;}
			$filename=$filename."-".$size;
			if ( $default=="dwapuuvatar" && is_plugin_active('wapuuvatar/wapuuvatar.php') ) {
				$pic_size="-128.png";
				$pic_sizex2="-256.png";
				if ($size<64) {
					$pic_size="-64.png";
					$pic_sizex2="-128.png";
				}
				if ($size<32) {
					$pic_size="-32.png";
					$pic_sizex2="-64.png";
				}
				$wapuu = substr($src_proof, strripos($src_proof,"dist%2F"));
				$wapuu = substr($wapuu, 7);
				$wapuu = substr($wapuu, 0, -9);
				$wapuu = substr($wapuu, 0, -(strlen($wapuu)-strripos($wapuu,"-")));
				$selected_pic=$wapuu.$pic_size;
				$selected_picx2=$wapuu.$pic_sizex2;
				$random=NULL;
				if (!empty($random)) {
					if ($folder_pic = $wp_filesystem->dirlist(WP_PLUGIN_DIR."/wapuuvatar/dist/")) {
						$count_png=1;
						$allpng=array();
						foreach ( (array) $folder_pic as $file => $fileinfo ) {
							if ( 'f' == $fileinfo['type'] ) {
								if (strpos($fileinfo['name'],$pic_size)) {
									$allpng[$count_png]=$fileinfo['name'];
									$count_png++;
								}
							}
						}
						sort($allpng);
						$count_pngx2=1;
						$allpngx2=array();
						foreach ( (array) $folder_pic as $file => $fileinfo ) {
							if ( 'f' == $fileinfo['type'] ) {
								if (strpos($fileinfo['name'],$pic_sizex2)) {
									$allpngx2[$count_pngx2]=$fileinfo['name'];
									$count_pngx2++;
								}
							}
						}
						sort($allpngx2);
					}
					$counterhier=count($allpng);
					$random_pic=mt_rand(1,$counterhier);
					$selected_pic=$allpng[$random_pic];
					$selected_picx2=$allpngx2[$random_pic];
				}
				$default=plugins_url()."/wapuuvatar/dist/".$selected_pic;
				$defaultx2=plugins_url()."/wapuuvatar/dist/".$selected_picx2;
			}
			else {
				$defaultx2=$default;
			}
			$grav_img = $host.$filename."?s=".$size."&d=".$default."&r=".$rating;
			$srcset_img = $host.$filename."?s=".$size_srcset."&d=".$defaultx2."&r=".$rating;
			if ($hovercard=='enabled') {
				$css=$css." grav-hashed grav-hijack";
			}
		}
		else { //von anderen
			$filename=$filename."-".$size;
			if ($new_host==0) {
				$host = '//';
			}
			else {
				$host=$scheme;
			}
			if ($no_mail==0) { //mit Mail
				$pos = strpos($src_proof, ":");
				$length=strlen ($src_proof);
				$src_proof2 = substr($src_proof, $pos, $length);
				$src_proof2=$host.$src_proof2;
				$url_host2=parse_url($src_proof2, PHP_URL_HOST);
				$pfad=parse_url($src_proof2, PHP_URL_PATH);
				if (strpos($pfad,'_____')!==false && $url_host2=="pbs.twimg.com") { //für Twitter
					$pfad=str_replace("_____","",$pfad);
				}
				if ($url_host2=="scontent.xx.fbcdn.net") { //für Facebook
					$filename.="fb";
					$cachefile=$path.$filename.".png";
					$cachefile_srcset=$path.$filename."_2x.png";
				}
				$query=parse_url($src_proof2, PHP_URL_QUERY);
				$query=str_replace("__","",$query);
				if (!empty($query)) {$query="?".$query;}
				if ($new_host==0) {
					$src_proof2=$url_host2.$pfad.$query;
				}
				else {
					$src_proof2=$host.'://'.$url_host2.$pfad.$query;
				}
				$grav_img = $src_proof2;
				$srcset_img = $src_proof2;
			}
			else { //ohne Mail
				$pos = strpos($mail, ":");
				$length=strlen ($mail);
				$mail2 = substr($mail, $pos, $length);
				$mail2=$host.$mail2;
				$url_host2=parse_url($mail2, PHP_URL_HOST);
				$pfad=parse_url($mail2, PHP_URL_PATH);
				$query=parse_url($mail2, PHP_URL_QUERY);
				if (!empty($query)) {$query=str_replace("__","",$query);}
				if (!empty($query)) {$query="?".$query;}
				if ($url_host2=='www.facebook.com') { //von Facebook
					$url_host2='graph.facebook.com/';
					$pfad.='/picture';
					if ($new_host==0) {
						$mail2=$url_host2.$pfad.$query;
					}
					else {
						$mail2=$host.'://'.$url_host2.$pfad.$query;
					}
				$grav_img = $mail2."-".$size;
				$srcset_img = $mail2."-".$size;
				$filename.="fb";
				$cachefile=$path.$filename.".png";
				$cachefile_srcset=$path.$filename."_2x.png";
				}
				else { //von gravatar.com
					if ($new_host==0) {
						$host = '//secure.gravatar.com/avatar/';
					}
					else {
						$host = $scheme.'://'.$url_host.'/avatar/';
						}
						$grav_img = $host.$filename."?s=".$size."&d=".$default."&r=".$rating;
						$srcset_img = $host.$filename."?s=".$size_srcset."&d=".$default."&r=".$rating;
					}
				}
			}
			if ( $wpdb->get_var($wpdb->prepare("SELECT filename FROM $hgc_store WHERE filename = '%s' ORDER BY filename ASC", $filename)) ) {
				$endung=$wpdb->get_var($wpdb->prepare("SELECT filetype FROM $hgc_store WHERE filename = '%s' ORDER BY filename ASC", $filename));
			}
			else {
				$endung_check = wp_remote_fopen($grav_img);
				$endung_check = getimagesizefromstring($endung_check);
				$blank=NULL;
				$default2=get_option('avatar_default');
				if ($default2=="blank") {
					$filesize_gravatar=0;
					$filesize_gravatar = wp_remote_fopen($host.$filename."?s=50&d=".$default."&r=".$rating);
					$filesize_gravatar=strlen($filesize_gravatar);
					if ($filesize_gravatar==105) {
						$blank=$default2;
					}
				}
				$konst=$endung_check[2];
				if ($konst==1) {$endung=".gif";}
				if ($konst==2) {$endung=".jpg";}
				if ($konst==3) {$endung=".png";}
				if ($konst==6) {$endung=".bmp";}
			}
			if ($make_png==2 && $endung==".jpg") {$endung=".png";}
			$cachefile=$path.$filename.$endung;
			$cachefile_srcset=$path.$filename."_2x".$endung;
			$time1=time() - $cachetime;
			if ( !$wp_filesystem->exists($cachefile) || $time1 > filemtime($cachefile) ) {
				$file_copy=$wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) );
				if ( $wpdb->get_var($wpdb->prepare("SELECT filename FROM $hgc_store WHERE filename LIKE '%s' ORDER BY filename ASC", $filename)) ) {
					$wpdb->delete( $hgc_store, array( 'filename' => $filename ) );
				}
				if ( $wp_filesystem->exists($cachefile) ) {#test
					if ( $time1 > filemtime($cachefile) ) {
						$wp_filesystem->delete($cachefile);
						if ($retina==1) {
							$wp_filesystem->delete($cachefile_srcset);
						}
					}
				}#test
				if ($file_copy==1) { //file_get_contents
					$grav_img=$wp_filesystem->get_contents($grav_img);
					$wp_filesystem->put_contents($cachefile,$grav_img,0644);
					$fileperm=$wp_filesystem->getchmod($cachefile);
					if ($retina==1) {
						$srcset_img=$wp_filesystem->get_contents($srcset_img);
						$wp_filesystem->put_contents($cachefile_srcset,$srcset_img,0644);
						$fileperm_src=$wp_filesystem->getchmod($cachefile_srcset);
					}
				}
				if ($file_copy==2 || $file_copy==3) { //fopen / cUrl
					$fp = wp_remote_fopen($grav_img);
					$wp_filesystem->put_contents($cachefile,$fp,0644);
					$fileperm=$wp_filesystem->getchmod($cachefile);
					if ($retina==1) {
						$fp = wp_remote_fopen($srcset_img);
						$wp_filesystem->put_contents($cachefile_srcset,$fp,0644);
						$fileperm_src=$wp_filesystem->getchmod($cachefile_srcset);
					}
				}
				if ($file_copy==4) { //PHP Copy
					$wp_filesystem->copy($grav_img, $cachefile, 0644);
					$fileperm=$wp_filesystem->getchmod($cachefile);
					if ($retina==1) {
						$wp_filesystem->copy($srcset_img, $cachefile_srcset, 0644);
						$fileperm_src=$wp_filesystem->getchmod($cachefile_srcset);
					}
				}
				if ($fileperm!="644") {
					$wp_filesystem->chmod($cachefile, 0644);
				}
				if ($retina==1) {
					if ($fileperm_src!="644") {
						$wp_filesystem->chmod($cachefile_srcset, 0644);
					}
				}
				if ($konst==2 && $make_png==2) {
					$img_info = wp_remote_fopen($path.$filename.$endung);
					$img_info = getimagesizefromstring($path.$filename.$endung);
					if (!empty($img_info['channels'])) {$farbraum = $img_info['channels'];}
					if (empty($img_info['channels'])) {
						$farbraum=null;
					}
					if ($farbraum==3) {
						$gravatar_bild = @imageCreateFromJpeg($path.$filename.$endung);
						@imageAlphaBlending($gravatar_bild, false);
						@imageSaveAlpha($gravatar_bild, true);
						@imagepng($gravatar_bild,$path.$filename.$endung);
						@imagedestroy($gravatar_bild);
						if ($retina==1) {
							$gravatar_bild = @imageCreateFromJpeg($path.$filename."_2x".$endung);
							@imageAlphaBlending($gravatar_bild, false);
							@imageSaveAlpha($gravatar_bild, true);
							@imagepng($gravatar_bild,$path.$filename."_2x".$endung);
							@imagedestroy($gravatar_bild);
						}
					}
				}
				if ($hgc_precache!="hgc_precache" && $hgc_precache2!="hgc_precache2") {
					$permalink=get_permalink();
				}
				if ( is_numeric($id_or_email) ) {
					$permalink=get_author_posts_url( $id_or_email );
				}
				$md5_db=@md5_file($cachefile);
				if ( !$wpdb->get_var($wpdb->prepare("SELECT md5 FROM $hgc_store WHERE md5 LIKE '%s' ORDER BY md5 ASC", $md5_db)) && !empty($md5_db) ) {
					$src2=esc_url($cache_url.$filename.$endung);
					$srcset_check = wp_remote_fopen($src2);
					$srcset_check = getimagesizefromstring($srcset_check);
					if ($blank=="blank") {
						$wpdb->insert($hgc_store, array('md5' => $md5_db, 'filename' => $filename, 'filetype' => $endung, 'srcset' => $srcset, 'post_url' => $permalink, 'blank' => $blank, 'size' => $srcset_check[1]), array('%s', '%s', '%s', '%s', '%s', '%s', '%d'));
					}
					else {
						$wpdb->insert($hgc_store, array('md5' => $md5_db, 'filename' => $filename, 'filetype' => $endung, 'srcset' => $srcset, 'post_url' => $permalink, 'size' => $srcset_check[1]), array('%s', '%s', '%s', '%s', '%s', '%d'));
					}
				}
				unset($md5_db);
			}##speicherung bis hier
			$md5_check=@md5_file($cachefile);
			if ($wpdb->get_var($wpdb->prepare("SELECT md5 FROM $hgc_store WHERE md5 LIKE '%s' ORDER BY md5 ASC", $md5_check)) && !empty($md5_check) && $check_cache==1 ) {
				$filename=$wpdb->get_var($wpdb->prepare("SELECT filename FROM $hgc_store WHERE md5 = '%s'", $md5_check) );
			}
			else {
				$scan = $wp_filesystem->dirlist($path);
				$thisGravatarCacheDir=rtrim($path,"/")."/";
				foreach ( (array) $scan as $file => $fileinfo ) {
					if ( 'f' == $fileinfo['type'] && is_file($thisGravatarCacheDir.$file) && strpos($file,'_2x') === false ) {
						$md5_array[]=md5_file($thisGravatarCacheDir.$file);
						$file_array[]=$file;
					}
				}
				unset($key);
				$key = array_search($md5_check, $md5_array);
				if ( $key!="" || $key==0 ) {
					$filename=$file_array[$key];
					$filename=str_replace($endung, '', $filename);
				}
			}
			$cachefile_png = $cache_url.$filename.$endung;
			if ($retina==1) {
				$srcset = $cache_url.$filename.'_2x'.$endung;
			}
			else {
				$srcset = $cache_url.$filename.$endung;
			}
			//Fallback falls Caching nicht geklappt hat
			if (!$wp_filesystem->exists($cachefile) && $hgc_precache!="hgc_precache" && $hgc_precache2!="hgc_precache2" ) {
				return $avatar;
			}
			$count=uniqid();
			$id='grav-'.$filename.'-'.$count;
			if ($hgc_precache!="hgc_precache" && $hgc_precache2!="hgc_precache2") {
				$blank=NULL;
				$blank=$wpdb->get_var($wpdb->prepare("SELECT blank FROM $hgc_store WHERE md5 LIKE '%s' ORDER BY md5 ASC", $md5_check));
					if ($blank=="blank") {
						$cachefile_png=plugin_dir_url( __FILE__ )."blank.png";
						$srcset=plugin_dir_url( __FILE__ )."blank.png";
					}
				return "<img id='{$id}' alt='{$alt}' src='{$cachefile_png}' srcset='{$srcset} 2x' class='{$css}' height='{$size}' width='{$size}' loading='lazy' />";
			}
	}
	else {
		return $avatar;
	}
}

/* vor Funktionsaufruf prüfen ob Cache-Ordner vorhanden und beschreibbar */
if ( is_plugin_active('harrys-gravatar-cache/harrys-gravatar-cache.php') ) {
	$path_ok=false;
	if ( $wp_filesystem->is_dir($path) ) {
		$path_ok=1;
	}
	if ( $wpdb->get_var("SHOW TABLES LIKE '$hgc_table'") == $hgc_table ) {
		$is_writeable=$wpdb->get_var($wpdb->prepare("SELECT is_writeable FROM $hgc_table WHERE id = %d", 1) );
		$size=$wpdb->get_var($wpdb->prepare("SELECT size FROM $hgc_table WHERE id = %d", 1) );
		$size_get=$wpdb->get_var($wpdb->prepare("SELECT size_get FROM $hgc_table WHERE id = %d", 1) );
		$get_option=$wpdb->get_var($wpdb->prepare("SELECT get_option FROM $hgc_table WHERE id = %d", 1) );
		$cache_time=$wpdb->get_var($wpdb->prepare("SELECT cache_time FROM $hgc_table WHERE id = %d", 1) );
	}
/* Funktionsaufruf wenn Avatare aktiviert und Cacheordner vorhanden und beschreibbar, size passende Größe, Copyoption verfügbar, Cachetime gesetzt und der User(falls eingeloggt) kein Admin ist */
	if (get_option('show_avatars') && $path_ok==1 && $is_writeable==1 && $size>19 && $size<201 && $size_get>0 && $size_get<4 && $get_option>0 && $get_option<5 && $cache_time>1439 && $cache_time<80641 && empty($no_hgc) && !current_user_can('manage_options')) {
		add_filter('get_avatar', 'gravatar_lokal', 16, 6);
	}
}
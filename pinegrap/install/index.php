<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// If an admin has not specifically requested that error reporting not be set by PineGrap, then
// set error_reporting to what is generally best for PineGrap. Don't show PHP notices, strict,
// and deprecated messages. E_DEPRECATED is only available in newer PHP versions.  We allow
// an admin to disable this by setting SET_ERROR_REPORTING to false in config.php, because in
// PHP 7.2+, PHP is showing more warnings, so an admin might not want PineGrap to control this.
if (!defined('SET_ERROR_REPORTING') or SET_ERROR_REPORTING) {
    if (defined('E_DEPRECATED')) {
        ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    } else {
        ini_set('error_reporting', E_ALL & ~E_NOTICE);
    }
}


ini_set('max_execution_time', '9999');
ini_set('default_charset', 'utf-8');
define('VERSION', true);
// Turn off mysqli error reporting, because we will handle errors manually.
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
// Define a constant so that if an error occurs the error handling function
// will know to output the database error, regardless of what the debug setting is set to,
// because we always want to output the database error if an error happens in this script.
define('INSTALL_OR_UPDATE', true);
$automated_upgrade = false;

// How the automated upgrade was asked for: 'cli' for a cron that runs php directly,
// 'secret' for a cron that fetches the URL with the key from data/config.php, 'session' for
// the redirect that software_update.php sends a signed-in administrator through.
$automated_upgrade_via = '';

// The query string only asks. Whether it gets the upgrade is decided further down, once the
// database is connected and the key or the administrator's session can be checked; until
// then it is an ordinary request, with a session and a token like any other.
$automated_upgrade_requested = false;

// A signed-in administrator who arrives with ?automated_upgrade=true (software_update.php
// sends them here once the software files are updated) gets the upgrade screen, and the
// screen starts by itself and applies one version per request.
$install_autostart = false;

if ((PHP_SAPI === 'cli') && (isset($argv[1])) && ($argv[1] == 'automated_upgrade')) {

	$automated_upgrade = true;

	$automated_upgrade_via = 'cli';

} elseif ((isset($_REQUEST['automated_upgrade'])) && ($_REQUEST['automated_upgrade'] == 'true')) {

	$automated_upgrade_requested = true;

}

if (isset($_SERVER['HTTPS']) &&
    ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $protocol = 'https://';
}
else {
  $protocol = 'http://';
}
define('URL_SCHEME', $protocol);

//if there is no config file, create one.
if (!file_exists(dirname(__FILE__) . '/../data/config.php')) {
    // create data directory if it does not exist
    if (!is_dir(dirname(__FILE__) . '/../data')) {
        mkdir(dirname(__FILE__) . '/../data', 0755, true);
    }
    $file_config = fopen(dirname(__FILE__) . '/../data/config.php','w');
    if ($file_config !== false) {
        fwrite($file_config, '<?php ?>');
        fclose($file_config);
    }
}
require (dirname(__FILE__) . '/../data/config.php');


$output_enforcement ='';
// check if user request a language with url




// dedect user language from list, default is en
function dedect_user_language(){
	$supportedLanguages=['en','tr'];
	$lang = substr(($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2);
	if(!in_array($lang,$supportedLanguages)){
		$lang='en';
	}
	return $lang;
}

if(
	(isset($_GET['local']))
	&& ($_GET['local'] ==='en'
	|| $_GET['local'] ==='tr')
){
	define('DEFAULT_SOFTWARE_LANGUAGE', $_GET['local']);
}else{
	
	//else user is not request we check config defines. if ENFORCEMENT_SOFTWARE_LANGUAGE exits use it
	if( defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && (ENFORCEMENT_SOFTWARE_LANGUAGE !== '') ){
		// config.php may define DEFAULT_SOFTWARE_LANGUAGE as well; do not redefine it.
		if( !defined('DEFAULT_SOFTWARE_LANGUAGE') ){
			define('DEFAULT_SOFTWARE_LANGUAGE', ENFORCEMENT_SOFTWARE_LANGUAGE);
		}
		$output_enforcement = '(' . ENFORCEMENT_SOFTWARE_LANGUAGE . ')';
	}elseif( !defined('DEFAULT_SOFTWARE_LANGUAGE') ){
		// else DEFAULT_SOFTWARE_LANGUAGE is not set up in config, so detect it.
		define('DEFAULT_SOFTWARE_LANGUAGE', dedect_user_language());
	}

}


// While the installation runs it writes every step into a small file, and the install screen reads
// that file to show the steps.  Some servers hold the output of a running script back until it
// finishes, and this way the screen fills up on those servers too.  Nothing here needs the session,
// so this answer never has to wait for the installation to release it.
if ((isset($_REQUEST['install_action'])) && ($_REQUEST['install_action'] == 'progress')) {

	header('Content-Type: application/json; charset=utf-8');

	header('Cache-Control: no-store');

	$progress_id = '';

	if (isset($_REQUEST['progress_id'])) {

		$progress_id = $_REQUEST['progress_id'];

	}

	if (preg_match('/^[a-f0-9]{8,32}$/', $progress_id) != 1) {

		print '{"steps":[],"done":false}';

		exit();

	}

	$progress_file = dirname(__FILE__) . '/../data/temp/install_progress_' . $progress_id . '.json';

	if (!file_exists($progress_file)) {

		print '{"steps":[],"done":false}';

		exit();

	}

	$progress_contents = @file_get_contents($progress_file);

	if ($progress_contents == false) {

		print '{"steps":[],"done":false}';

		exit();

	}

	print $progress_contents;

	exit();

}

// The translation function reads SOFTWARE_LANGUAGE, and that constant is normally set by the site
// itself.  The install screen runs before a site exists, so we set it here.  Without this the whole
// install screen is always in English.
//
// If a site is already installed in the database, then we use the language of that site, because an
// administrator who comes here to restore a backup expects the same language they use every day.
// A language in the URL always wins, so the screen can still be read in another language.
if ((!defined('SOFTWARE_LANGUAGE')) && (!isset($_GET['local'])) && (defined('DB_HOST')) && (DB_HOST != '')) {

	$language_connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

	if ($language_connection != false) {

		$language_result = @mysqli_query($language_connection, "SELECT software_language FROM config LIMIT 1");

		if ($language_result != false) {

			$language_row = @mysqli_fetch_assoc($language_result);

			if ((isset($language_row['software_language'])) && ($language_row['software_language'] != '')) {

				define('SOFTWARE_LANGUAGE', $language_row['software_language']);

			}

		}

		@mysqli_close($language_connection);

	}

}

if (!defined('SOFTWARE_LANGUAGE')) {

	define('SOFTWARE_LANGUAGE', DEFAULT_SOFTWARE_LANGUAGE);

}

function get_software_language_options() {
    $software_language_options          = array();
    $software_language_options['-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('language')) )) . '-']		= '';
    $software_language_options['English']	= 'en';
    $software_language_options['Türkçe']	= 'tr';
    return $software_language_options;
}

if(defined('EDITION')){
	define('EDITION', EDITION);
}else{
	define('EDITION', 'CE');
}
require (dirname(__FILE__) . '/../functions.php');

// The upgrade runner: the version list, the migration files, the schema helpers and the
// loop that applies them.  It lives under includes/ so that it survives the install
// directory being removed from a server.
require (dirname(__FILE__) . '/../includes/migrations/runner.php');

// The LiveSite-era steps are plain functions in one file; they are loaded here so that the
// screen can tell which of those versions touch the database.
install_include_legacy();

// if this script is not being called from an automated upgrade script, then start session
if ($automated_upgrade == false) {

	// If this is a secure request then prepare to start a secure session.
	// We do this so that if a visitor accidentally requests an insecure URL,
	// then their session id is not sent in clear text
	// which would allow their session to be hijacked.
	if (check_if_request_is_secure() == true) {

		ini_set('session.cookie_secure', true);

	}

	// If PHP version is greater or equal to 5.2.0 then
	// set the session cookie so that it is not available through JavaScript.
	// This prevents various hacking methods.
	if (version_compare(PHP_VERSION, '5.2.0', '>=') == true) {

		ini_set('session.cookie_httponly', true);

	}

	session_start();

}

// certain versions of PHP 5.3+ will display a warning if a timezone is not set
// (e.g. date.timezone not being set in the php.ini file),
// so we are going to force a timezone to be set
if (

(ini_get('date.timezone') == false) && (function_exists('date_default_timezone_set') == true)) {

	date_default_timezone_set(@date_default_timezone_get());

}

// PHP+8 depricated//..
// if magic quotes is on, remove slashes from data so our data is clean
if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off"))) {
    $_GET = array_stripslashes($_GET);
    $_POST = array_stripslashes($_POST);
    $_COOKIE = array_stripslashes($_COOKIE);
}

// if this server is on Windows, then path delimiter is a backslash
if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {

	$delimiter = '\\';

	// else this server is not on Windows, so path delimiter is a forward slash
	
}
else {

	$delimiter = '/';

}

$path_parts = explode($delimiter, dirname(__FILE__));

define('SOFTWARE_DIRECTORY', $path_parts[count($path_parts) - 2]);

// prepare escaped version of software directory
define('OUTPUT_SOFTWARE_DIRECTORY', h(SOFTWARE_DIRECTORY));

// get the path by going 3 levels up from the current script request
$url_path = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));

// convert backslashes to forward slashes
// backslashes seem to only appear on Windows when only the root is left (e.g. \).
$url_path = str_replace('\\', '/', $url_path);

// if the path is not the root, then add a slash on the end
if ($url_path != '/') {

	$url_path .= '/';

}

define('PATH', $url_path);

// prepare escaped version of path
define('OUTPUT_PATH', h(PATH));

// If a config file path is not set, then set it to the default which is a path
// inside the software directory. A custom config file path is used when
// an adminstrator wants the config file to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('CONFIG_FILE_PATH') == false) {

	define('CONFIG_FILE_PATH', dirname(__FILE__) . '/../data/config.php');

}

// If a file directory path is not set, then set it to the default which is a path
// inside the software directory. A custom file directory path is used when
// an adminstrator wants the file directory to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('FILE_DIRECTORY_PATH') == false) {

	define('FILE_DIRECTORY_PATH', dirname(__FILE__) . '/../data/files');

}

// If a layout directory path is not set, then set it to the default which is a path
// inside the software directory. A custom layout directory path is used when
// an adminstrator wants the layout directory to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (!defined('LAYOUT_DIRECTORY_PATH')) {

	define('LAYOUT_DIRECTORY_PATH', dirname(__FILE__) . '/../data/layouts');

}

// If an htaccess file path is not set, then set it to the default which is a path
// in the web root. A custom htaccess file path is used when the .htaccess file is
// is located in a separate location from the software.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('HTACCESS_FILE_PATH') == false) {

	// We are using stristr instead of mb_stristr because mb_stristr requires PHP 5.2,
	// and we still have some sites on PHP 5.1 (probably won't cause any utf-8 issue).
	

	// If the web server is IIS then set the htaccess file info to the httpd.ini location.
	if (stristr(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '', 'iis')) {

		define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../../httpd.ini');

		define('HTACCESS_FILE_NAME', 'httpd.ini');

		// Otherwise the web server is Apache, so set the htaccess file info to the .htaccess location.
		
	}
	else {

		define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../../.htaccess');

		define('HTACCESS_FILE_NAME', '.htaccess');

	}

}

// The starter site folders that ship with the software.  These are offered as starter
// sites on the install screen, so they are not mixed in with the site backups that an
// administrator has created on the server.
function get_starter_site_folders() {

	return array(

		'turkish_default' => array('label' => 'Türkçe', 'language' => 'tr'),

		'english_default' => array('label' => 'English', 'language' => 'en')

	);

}

// Returns the starter site folders that actually exist on the server.
function get_available_starter_sites() {

	$directory_path = dirname(__FILE__) . '/../data/backups/';

	$starter_sites = array();

	foreach (get_starter_site_folders() as $folder => $starter_site) {

		if (is_dir($directory_path . $folder)) {

			$starter_site['folder'] = $folder;

			$starter_sites[] = $starter_site;

		}

	}

	return $starter_sites;

}

// Returns the site backups that are on the server, without the starter sites.  Newest first.
function get_site_backups() {

	$directory_path = dirname(__FILE__) . '/../data/backups/';

	$starter_site_folders = get_starter_site_folders();

	$backups = array();

	if (!is_dir($directory_path)) {

		return $backups;

	}

	$entries = @scandir($directory_path);

	if ($entries == false) {

		return $backups;

	}

	foreach ($entries as $entry) {

		if (($entry == '.') || ($entry == '..') || (mb_substr($entry, 0, 1) == '.')) {

			continue;

		}

		if (isset($starter_site_folders[$entry])) {

			continue;

		}

		if (!is_dir($directory_path . $entry)) {

			continue;

		}

		$backups[] = array(

			'folder' => $entry,

			'modified' => @filemtime($directory_path . $entry),

			// A folder can only be installed when it holds a database dump.
			'installable' => file_exists($directory_path . $entry . '/sql.sql')

		);

	}

	// sort the backups so that the newest backup is first
	usort($backups, 'compare_site_backups');

	return $backups;

}

function compare_site_backups($first_backup, $second_backup) {

	if ($first_backup['modified'] == $second_backup['modified']) {

		return strcasecmp($first_backup['folder'], $second_backup['folder']);

	}

	return ($first_backup['modified'] < $second_backup['modified']) ? 1 : -1;

}

// While the software installs we keep a note of every step and how long it took, so the screen
// that comes back can show what actually happened instead of just saying that it worked.
$install_log = array();

$install_started_at = 0;

// While the installation streams, every step is sent to the browser as it happens instead of being
// kept until the end.  The number below is only used for the progress bar.
$install_streaming = false;

$install_expected_steps = 10;

// the file that the screen reads while the installation runs
$install_progress_file = '';

// what the runner is doing right now, and what stopped it; both go into the progress file
$install_progress_running = '';

$install_progress_error = null;

function start_install_log() {

	global $install_started_at;

	$install_started_at = microtime(true);

}

// Prepares the file that the install screen reads while the installation runs.  The screen makes up
// the name, so nobody can read the progress of somebody else without knowing it.
function start_install_progress_file() {

	global $install_progress_file;

	$progress_id = '';

	if (isset($_POST['progress_id'])) {

		$progress_id = $_POST['progress_id'];

	}

	if (preg_match('/^[a-f0-9]{8,32}$/', $progress_id) != 1) {

		return;

	}

	// These belong with the rest of the scratch files rather than loose in data/, where
	// they sat next to config.php and the backups and looked like something that mattered.
	$directory_path = dirname(__FILE__) . '/../data/temp';

	if ((!is_dir($directory_path)) && (!@mkdir($directory_path, 0755, true))) {

		return;

	}

	// remove the files of older installations, so they do not pile up.  The second sweep
	// clears out the old location, for a site that was installed before they moved.
	$old_files = array_merge(
		(array) @glob($directory_path . '/install_progress_*.json'),
		(array) @glob(dirname(__FILE__) . '/../data/install_progress_*.json'));

	foreach ($old_files as $old_file) {

		if ((@filemtime($old_file) + 3600) < time()) {

			@unlink($old_file);

		}

	}

	$install_progress_file = $directory_path . '/install_progress_' . $progress_id . '.json';

	@file_put_contents($install_progress_file, '{"steps":[],"done":false}', LOCK_EX);

}

// Writes everything that has happened so far into that file.
function write_install_progress_file($done = false) {

	global $install_progress_file, $install_log, $install_expected_steps, $install_progress_running, $install_progress_error;

	if ($install_progress_file == '') {

		return;

	}

	$steps = array();

	foreach ($install_log as $index => $step) {

		$percent = (int) round((($index + 1) / $install_expected_steps) * 100);

		if ($percent > 99) {

			$percent = 99;

		}

		$steps[] = array(
			'i' => $index,
			's' => number_format($step['seconds'], 1),
			'l' => $step['label'],
			'd' => $step['detail'],
			't' => $step['state'],
			'p' => $percent
		);

	}

	$progress = array(
		'steps' => $steps,
		'done' => $done,
		'running' => (string) $install_progress_running,
		'error' => $install_progress_error,
		'notes' => function_exists('install_notes') ? install_notes() : array()
	);

	@file_put_contents($install_progress_file, json_encode($progress), LOCK_EX);

}

function add_install_step($label, $detail = '', $state = 'ok') {

	global $install_log, $install_started_at, $install_streaming, $install_expected_steps;

	if ($install_started_at == 0) {

		start_install_log();

	}

	$seconds = microtime(true) - $install_started_at;

	$install_log[] = array(
		'label' => $label,
		'detail' => $detail,
		'state' => $state,
		'seconds' => $seconds
	);

	// the screen reads this file while the installation runs
	write_install_progress_file(false);

	// if the installation is being streamed, then send this step to the browser right away
	if ($install_streaming == true) {

		$percent = (int) round((count($install_log) / $install_expected_steps) * 100);

		if ($percent > 99) {

			$percent = 99;

		}

		print '<script>pg_install_stream_step(' .
			(count($install_log) - 1) . ', ' .
			json_encode(number_format($seconds, 1)) . ', ' .
			json_encode($label) . ', ' .
			json_encode($detail) . ', ' .
			json_encode($state) . ', ' .
			$percent . ');</script>' . "\n";

		flush_install_stream();

	}

}

// Sends whatever has been printed so far to the browser.  Servers like to hold output back, so we
// turn every buffer off before the installation starts and push after every step.
function flush_install_stream() {

	if (ob_get_level() > 0) {

		@ob_flush();

	}

	@flush();

}

// Turns a php.ini size like "8M" into bytes.
function get_install_ini_bytes($value) {

	$value = trim($value);

	if ($value == '') {

		return 0;

	}

	$unit = mb_strtolower(mb_substr($value, -1));

	$number = (float) $value;

	if ($unit == 'g') {

		return (int) ($number * 1024 * 1024 * 1024);

	}

	if ($unit == 'm') {

		return (int) ($number * 1024 * 1024);

	}

	if ($unit == 'k') {

		return (int) ($number * 1024);

	}

	return (int) $number;

}

// The size of a file that this server really accepts.  A big upload is refused by post_max_size
// before it ever reaches us, and that setting is usually the smaller one, so we have to look at
// both of them.  PHP throws the whole request away in that case, which is why an upload that is
// too large used to look like nothing happened at all.
function get_install_upload_limit() {

	$upload_limit = get_install_ini_bytes(ini_get('upload_max_filesize'));

	$post_limit = get_install_ini_bytes(ini_get('post_max_size'));

	if (($post_limit > 0) && (($upload_limit == 0) || ($post_limit < $upload_limit))) {

		return $post_limit;

	}

	return $upload_limit;

}

// Writes a size in a way that a person reads it.
function get_install_size_label($bytes) {

	if ($bytes <= 0) {

		return lang('Unknown');

	}

	if ($bytes >= (1024 * 1024 * 1024)) {

		return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';

	}

	if ($bytes >= (1024 * 1024)) {

		return number_format($bytes / (1024 * 1024), 0) . ' MB';

	}

	return number_format($bytes / 1024, 0) . ' KB';

}

// Runs the checks that we show on the install screen, so the person who is installing can see
// what the server can do before they start.  Each check returns a state of ok, warning or error.
function get_install_system_checks() {

	$checks = array();

	$php_state = 'ok';

	if (version_compare(PHP_VERSION, '7.0.0', '<')) {

		$php_state = 'error';

	}

	$checks[] = array('label' => lang('PHP version'), 'value' => PHP_VERSION, 'state' => $php_state);

	$database_value = lang('Not available');

	$database_state = 'error';

	if (function_exists('mysqli_connect')) {

		$database_value = lang('Available');

		$database_state = 'ok';

		// if we are already connected to a database, then show the server version instead
		if (isset(db::$con) && (db::$con != false)) {

			$server_version = @mysqli_get_server_info(db::$con);

			if ($server_version != '') {

				$database_value = $server_version;

			}

		}

	}

	$checks[] = array('label' => lang('MySQL'), 'value' => $database_value, 'state' => $database_state);

	$checks[] = array(
		'label' => lang('Zip support'),
		'value' => (class_exists('ZipArchive') ? lang('Available') : lang('Not available')),
		'state' => (class_exists('ZipArchive') ? 'ok' : 'warning')
	);

	$image_value = lang('Not available');

	$image_state = 'warning';

	if (extension_loaded('imagick')) {

		$image_value = 'Imagick';

		$image_state = 'ok';

	}
	elseif (extension_loaded('gd')) {

		$image_value = 'GD';

		$image_state = 'ok';

	}

	$checks[] = array('label' => lang('Image engine'), 'value' => $image_value, 'state' => $image_state);

	$upload_setting = ini_get('upload_max_filesize');

	$post_setting = ini_get('post_max_size');

	$upload_detail = 'upload_max_filesize ' . $upload_setting . ' · post_max_size ' . $post_setting;

	$upload_state = 'warning';

	// The smaller of the two is what really counts, and when post_max_size is the smaller one a big
	// upload is thrown away before the script sees it, so we point at the setting that has to change.
	if (get_install_ini_bytes($post_setting) < get_install_ini_bytes($upload_setting)) {

		$upload_state = 'error';

		$upload_detail = lang('post_max_size is smaller than upload_max_filesize, so it decides the limit. Raise both.') . ' ' . $upload_detail;

	}

	$checks[] = array(
		'label' => lang('Upload limit'),
		'value' => get_install_size_label(get_install_upload_limit()),
		'state' => $upload_state,
		'detail' => $upload_detail
	);

	$data_directory_path = dirname(__FILE__) . '/../data';

	$checks[] = array(
		'label' => lang('Write permission'),
		'value' => (is_writable($data_directory_path) ? lang('Available') : lang('Not available')),
		'state' => (is_writable($data_directory_path) ? 'ok' : 'error')
	);

	return $checks;

}

// Reads the changelog file and keeps every version section, so the install screen can show what is
// in the version that is about to be installed and what every upgrade step does.  The sections come
// back newest first, keyed by the version number.
// Drops an entry whose text came out empty - a stray tag, or a heading that
// was mistaken for one.
function filter_install_changelog_entry($entry) {

	return (trim($entry['text']) != '');

}

function get_install_changelog_sections() {

	static $sections = null;

	if ($sections !== null) {

		return $sections;

	}

	$sections = array();

	$file_path = dirname(__FILE__) . '/../changelog.txt';

	if (!file_exists($file_path)) {

		return $sections;

	}

	$contents = @file_get_contents($file_path);

	if ($contents == false) {

		return $sections;

	}

	$contents = str_replace("\r\n", "\n", $contents);

	// version headings are a version number on its own line followed by a line of equal signs
	$matched = preg_match_all('/^[ \t]*([0-9][0-9.]*)[ \t]*\n[ \t]*={5,}[ \t]*$/m', $contents, $matches, PREG_OFFSET_CAPTURE);

	if (($matched == false) || (count($matches[0]) == 0)) {

		return $sections;

	}

	foreach ($matches[0] as $index => $heading) {

		$version = $matches[1][$index][0];

		// preg gives us byte offsets, so we slice with the byte functions here
		$section_start = $heading[1] + strlen($heading[0]);

		$section_end = strlen($contents);

		if (isset($matches[0][$index + 1])) {

			$section_end = $matches[0][$index + 1][1];

		}

		$section = substr($contents, $section_start, $section_end - $section_start);

		$entries = array();

		// A version with many entries is written under topic headings: a line of
		// capitals at the margin over a rule of hyphens.  They are read here so
		// this panel can show the same grouping the file has - and so a heading
		// never ends up glued to the end of the entry above it, which is what
		// the tag-only parse below did with it.
		$group = '';

		$section_lines = explode("\n", $section);

		$entry_tag = '';

		$entry_text = '';

		$entry_group = '';

		foreach ($section_lines as $line_index => $line) {

			$next_line = isset($section_lines[$line_index + 1]) ? $section_lines[$line_index + 1] : '';

			// a topic heading, recognised by the rule of hyphens under it
			if ((preg_match('/^[ \t]{0,4}([A-ZÇĞİÖŞÜ0-9][A-ZÇĞİÖŞÜ0-9 ,:\/&()-]*[A-ZÇĞİÖŞÜ0-9)])[ \t]*$/u', $line, $heading_match))
				&& (preg_match('/^[ \t]{0,4}-{5,}[ \t]*$/', $next_line))) {

				if ($entry_tag != '') {

					$entries[] = array('tag' => $entry_tag, 'text' => trim(preg_replace('/\s+/', ' ', $entry_text)), 'group' => $entry_group);

					$entry_tag = '';

					$entry_text = '';

				}

				$group = trim($heading_match[1]);

				continue;

			}

			// the rule under a heading
			if (preg_match('/^[ \t]{0,4}-{5,}[ \t]*$/', $line)) {

				continue;

			}

			// an entry opens with one or more tags in square brackets
			if (preg_match('/^[ \t]{0,4}\[([^\]\n]+)\][ \t]*(.*)$/', $line, $entry_match)) {

				if ($entry_tag != '') {

					$entries[] = array('tag' => $entry_tag, 'text' => trim(preg_replace('/\s+/', ' ', $entry_text)), 'group' => $entry_group);

				}

				$entry_tag = trim($entry_match[1]);

				$entry_text = $entry_match[2];

				$entry_group = $group;

				continue;

			}

			if (($entry_tag != '') && (trim($line) != '')) {

				$entry_text .= ' ' . trim($line);

			}

		}

		if ($entry_tag != '') {

			$entries[] = array('tag' => $entry_tag, 'text' => trim(preg_replace('/\s+/', ' ', $entry_text)), 'group' => $entry_group);

		}

		$entries = array_values(array_filter($entries, 'filter_install_changelog_entry'));

		if (count($entries) == 0) {

			continue;

		}

		$sections[$version] = array('version' => $version, 'entries' => $entries);

	}

	return $sections;

}

// Returns the newest section of the changelog.
function get_install_changelog() {

	$sections = get_install_changelog_sections();

	if (count($sections) == 0) {

		return false;

	}

	foreach ($sections as $section) {

		return $section;

	}

	return false;

}

// Returns what the changelog says about the database changes of a version, so the upgrade screen can
// tell the administrator what every step does.  Empty when the version does not say anything.
function get_install_schema_note($version_number) {

	$sections = get_install_changelog_sections();

	if (!isset($sections[$version_number])) {

		return '';

	}

	foreach ($sections[$version_number]['entries'] as $entry) {

		$tag = mb_strtoupper($entry['tag']);

		if (($tag == 'ŞEMA') || ($tag == 'SEMA') || ($tag == 'SCHEMA')) {

			return get_install_short_text($entry['text'], 150);

		}

	}

	return '';

}

// Shortens a piece of text for the narrow panels on this screen.
function get_install_short_text($text, $length) {

	if (mb_strlen($text) <= $length) {

		return $text;

	}

	return mb_substr($text, 0, $length) . '…';

}

// Returns the color that we use for a changelog tag.  The tags come from the changelog file, so
// we match on the tags that we write in both languages.
function get_install_changelog_tag_class($tag) {

	$tag = mb_strtoupper($tag);

	if (($tag == 'GÜVENLIK') || ($tag == 'GÜVENLİK') || ($tag == 'SECURITY')) {

		return 'text-bg-danger';

	}

	if (($tag == 'ŞEMA') || ($tag == 'SCHEMA')) {

		return 'text-bg-warning';

	}

	if (($tag == 'DÜZELTME') || ($tag == 'FIX')) {

		return 'text-bg-primary';

	}

	if (($tag == 'HIZ') || ($tag == 'SPEED') || ($tag == 'PERFORMANCE')) {

		return 'text-bg-info';

	}

	return 'text-bg-success';

}

// The install screen is locked when a site exists in the database and nobody has proven that
// they are an administrator of that site.  While it is locked we only output the authentication
// card, because otherwise the names of the backups on the server, the database fields, and the
// submit button would be public information on every site.
define('INSTALL_UNLOCK_SECONDS', 1800);

define('INSTALL_ATTEMPT_LIMIT', 5);

define('INSTALL_ATTEMPT_WINDOW', 900);

function get_install_attempt_file() {

	// Kept with the rest of the scratch files. Nothing here has to survive a tidy-up of
	// data/temp: losing the file only means the attempt counter starts over.
	return dirname(__FILE__) . '/../data/temp/install_attempts.json';

}

// We store a hash of the address instead of the address itself, so the file does not
// become a list of addresses that have visited the install screen.
function get_install_visitor_key() {

	$address = '';

	if (isset($_SERVER['REMOTE_ADDR'])) {

		$address = $_SERVER['REMOTE_ADDR'];

	}

	return md5('pinegrap_install_' . $address);

}

// Reads the failed attempts file and removes everything that is outside of the time window.
function get_install_attempts() {

	$attempts = array();

	$file_path = get_install_attempt_file();

	if (file_exists($file_path)) {

		$contents = @file_get_contents($file_path);

		if ($contents != false) {

			$decoded = @json_decode($contents, true);

			if (is_array($decoded)) {

				$attempts = $decoded;

			}

		}

	}

	// remove attempts that are older than the time window
	foreach ($attempts as $key => $attempt) {

		if ((!isset($attempt['time'])) || (($attempt['time'] + INSTALL_ATTEMPT_WINDOW) < time())) {

			unset($attempts[$key]);

		}

	}

	return $attempts;

}

// Returns the number of seconds that this visitor has to wait before they can try again.
function get_install_attempt_wait() {

	$attempts = get_install_attempts();

	$key = get_install_visitor_key();

	if ((isset($attempts[$key])) && ($attempts[$key]['count'] >= INSTALL_ATTEMPT_LIMIT)) {

		$seconds = ($attempts[$key]['time'] + INSTALL_ATTEMPT_WINDOW) - time();

		if ($seconds > 0) {

			return $seconds;

		}

	}

	return 0;

}

// Returns how many attempts this visitor has left before they are locked out.
function get_install_attempts_remaining() {

	$attempts = get_install_attempts();

	$key = get_install_visitor_key();

	$used = 0;

	if (isset($attempts[$key])) {

		$used = $attempts[$key]['count'];

	}

	$remaining = INSTALL_ATTEMPT_LIMIT - $used;

	if ($remaining < 0) {

		$remaining = 0;

	}

	return $remaining;

}

function add_install_attempt() {

	$attempts = get_install_attempts();

	$key = get_install_visitor_key();

	if (!isset($attempts[$key])) {

		$attempts[$key] = array('count' => 0, 'time' => time());

	}

	$attempts[$key]['count'] = $attempts[$key]['count'] + 1;

	$attempts[$key]['time'] = time();

	write_install_attempts($attempts);

}

function clear_install_attempts() {

	$attempts = get_install_attempts();

	$key = get_install_visitor_key();

	if (isset($attempts[$key])) {

		unset($attempts[$key]);

	}

	write_install_attempts($attempts);

}

function write_install_attempts($attempts) {

	$directory_path = dirname(__FILE__) . '/../data/temp';

	if ((!is_dir($directory_path)) && (!@mkdir($directory_path, 0755, true))) {

		return;

	}

	// The data directory is not reachable from the web, however we still write a file that
	// cannot be executed, and we do not care if the write fails, because the session lock and
	// the password check are what protect the screen.  This file only slows down guessing.
	@file_put_contents(get_install_attempt_file(), json_encode($attempts), LOCK_EX);

}

// Returns true when this session has already authenticated on the install screen and the
// unlock has not expired yet.
function check_install_unlocked() {

	if ((isset($_SESSION['software']['install']['unlocked_until'])) && ($_SESSION['software']['install']['unlocked_until'] > time())) {

		return true;

	}

	return false;

}

function set_install_unlocked() {

	$_SESSION['software']['install']['unlocked_until'] = time() + INSTALL_UNLOCK_SECONDS;

}

// Returns the number of minutes that are left on the unlock, so we can tell the user.
function get_install_unlock_minutes() {

	if (check_install_unlocked() == false) {

		return 0;

	}

	return (int) ceil(($_SESSION['software']['install']['unlocked_until'] - time()) / 60);

}

// If the ENVIRONMENT constant is set to "development", then set the ENVIRONMENT_SUFFIX to "src".
// This allows us to use source files instead of minified files during development.
if (defined('ENVIRONMENT') and ENVIRONMENT == 'development') {

	define('ENVIRONMENT_SUFFIX', 'src');

	// Otherwise the ENVIRONMENT constant is not defined or set to something else, so set the ENVIRONMENT_SUFFIX to "min".
	
}
else {

	define('ENVIRONMENT_SUFFIX', 'min');

}

// if this script is not being called from an automated upgrade script,
// then generate token and add it to session for visitor if it has not already been done in order to prevent CSRF attacks.
if ($automated_upgrade == false) {

	initialize_token();

}

// create liveform object for form handling
include_once (dirname(__FILE__) . '/../liveform.class.php');

$liveform = new liveform('install');

//Version history
$versions = install_get_versions();

$software_version = $versions[count($versions) - 1]['number'];

$software_version_key = count($versions) - 1;

class db {

	public static $con;

}

// if there are database constants in config.php and we can't connect to the database or select the database, then output error
// this is done in order to make sure that someone does not install over an existing site while a database is just having connection issues
if (

(defined('DB_HOST') == true) and (DB_HOST != '') and (!(db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE)))) {

	exit(lang('It appears that a site may already be installed here.  If you want to reinstall please remove all content from the config.php file and then refresh this page.'));

}

// Find out whether a site already exists in this database before anything is sent to the
// browser.  When a site exists, the install screen only shows the authentication card until
// an administrator of that site has authenticated.  The install script is always public, so
// without this the names of the backups on the server, the database fields and the submit
// button would be readable by anyone who requests this directory.
$install_site_exists = false;

$install_locked = false;

if ($automated_upgrade == false) {

	if (

	(defined('DB_HOST') == true) and (DB_HOST != '') and (db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE))) {

		init_mysql_charset();

		// Pin the session sql_mode instead of inheriting the server default, which differs
		// between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
		// so only NO_ENGINE_SUBSTITUTION is kept.
		mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

		$result = @mysqli_query(db::$con, "SHOW TABLES");

		if ($result != false) {

			while ($row = mysqli_fetch_row($result)) {

				if (($row[0] == 'config') || ($row[0] == 'page') || ($row[0] == 'user')) {

					$install_site_exists = true;

					break;

				}

			}

		}

	}

	// A cron asked for the automated upgrade over the web.  It gets it with the key from
	// data/config.php (AUTOMATED_UPGRADE_SECRET, opt-in: no constant, no key path), or with a
	// signed-in administrator's session, which is how software_update.php arrives here.
	// Anything else is refused, and the refusal counts towards the same attempt limit as a
	// wrong password on the lock screen, so the key cannot be guessed in a hurry.
	if ($automated_upgrade_requested == true) {

		$automated_upgrade_secret = isset($_REQUEST['secret']) ? (string) $_REQUEST['secret'] : '';

		if (install_secret_matches($automated_upgrade_secret)) {

			$automated_upgrade = true;

			$automated_upgrade_via = 'secret';

		} elseif (($install_site_exists == true) && (check_if_administrator_is_logged_in() == true)) {

			// The administrator gets the screen rather than a single long request: it starts
			// by itself and applies one version per request, so a server limit can only cut
			// one version short and the screen offers to continue from it.  A form that is
			// posted back to this URL is handled by the form handler as usual.
			$automated_upgrade_via = 'session';

			$install_autostart = ($_SERVER['REQUEST_METHOD'] != 'POST');

		} else {

			add_install_attempt();

			if ($install_site_exists == true) {

				log_activity(lang('An automated upgrade was refused because the key did not match and no administrator was signed in.'), '');

			}

			set_response_code(403);

			header('Content-Type: text/plain; charset=utf-8');

			exit('Forbidden');

		}

	}

	// A site exists, so the screen stays locked until this session proves that it belongs to
	// an administrator.  A user who is already logged in to the control panel is never asked.
	if (($install_site_exists == true) && ($automated_upgrade == false)) {

		if ((check_if_administrator_is_logged_in() == false) && (check_install_unlocked() == false)) {

			$install_locked = true;

		}

	}

}

// The step and backup requests of the upgrade screen expect JSON.  When the session behind
// them has expired they get a short answer that says so, instead of the lock screen's HTML.
if (($install_locked == true) && (isset($_POST['install_action'])) && (in_array($_POST['install_action'], array('upgrade_step', 'backup_database')))) {

	set_response_code(403);

	header('Content-Type: application/json; charset=utf-8');

	print json_encode(array('ok' => false, 'session' => true, 'error' => lang('Sorry, we could not accept your request because it appears that your session expired.')));

	exit();

}

// if the install screen is locked, then handle the unlock form and output the authentication
// screen.  This function never returns.
if ($install_locked == true) {

	output_install_lock_screen();

}

// The install screen can test the database connection before anything is written.  We only answer
// this after the lock, so on a site that already exists it can only be used by an administrator.
if ((isset($_POST['install_action'])) && ($_POST['install_action'] == 'test_database') && ($automated_upgrade == false)) {

	output_install_database_test();

}

// The upgrade screen applies one version per request through this answer, and it can write
// a backup of the database before the first one.  Both are only answered after the lock, so
// on a site that already exists they can only be used by an administrator.
if ((isset($_POST['install_action'])) && ($_POST['install_action'] == 'upgrade_step') && ($automated_upgrade == false)) {

	output_install_upgrade_step($versions);

}

if ((isset($_POST['install_action'])) && ($_POST['install_action'] == 'backup_database') && ($automated_upgrade == false)) {

	output_install_database_backup($versions);

}

// if user has not yet completed install form and this is not being run as part of an automated upgrade, output form
if ((!isset($_POST['submit'])) && ($automated_upgrade == false)) {

	// PHP throws the whole request away when it is larger than post_max_size, so nothing arrives
	// here: no fields, no file, not even the button that was pressed.  Without this the screen just
	// came back empty handed and it looked like the upload did nothing.
	// PHP sets CONTENT_LENGTH to zero when it discards the body, so we go by the fact that a form
	// upload always leaves something behind in $_POST unless the whole request was thrown away.
	if (($_SERVER['REQUEST_METHOD'] == 'POST') && (count($_POST) == 0) && (count($_FILES) == 0) &&
		(isset($_SERVER['CONTENT_TYPE'])) && (stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)) {

		$liveform->mark_error('', lang(array(
			'string' => 'The file you sent is larger than the {var:1} that this server accepts in one request. Copy the backup folder into the backups folder with FTP, or raise post_max_size and upload_max_filesize on the server.',
			'vars' => get_install_size_label(get_install_upload_limit())
		)));

	}

	// If a backup archive was uploaded, then extract it into the backups folder before the form is
	// rendered, so that the new folder is in the list when the form comes back.
	if (isset($_POST['submit_backup_zip'])) {

		// keep everything the visitor already typed in the form
		$liveform->add_fields_to_session();

		process_install_backup_upload($liveform, $install_site_exists);

	}

	// initialize variables
	$upgrade_option = false;

	$output_upgrade_message = '';

	// what the screen tells its script about the upgrade that is ahead
	$database_version = '';

	$upgrade_version_count = 0;

	$upgrade_first_version = '';

	$output_upgrade_button_label = lang('Start the upgrade');

	$output_upgrade_preflight = '';

	// shown when the version in the database cannot be matched to this package
	$output_version_warning = '';
	$output_submit_button_value = 'Install';
	$output_submit_button_label = lang('Start the installation');
	$output_submit_button_process_label = lang('Installing');
	$output_submit_button_icon ='bi-play-fill';

	$output_upgrade = '';

	// The install screen is locked until an administrator authenticates, so the
	// install form does not ask for a login again. Initialised here because the
	// screen is printed on paths that never reach the branch below - a first
	// install into an empty database among them, which is exactly when the
	// installer is used.
	$output_install_authentication = '';

	// if DB_HOST is defined,
	// and a connection can be made to the database,
	// and the database can be selected,
	// then check if software is already installed
	if (

	(defined('DB_HOST') == true) and (db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE))) {

		init_mysql_charset();

		// Pin the session sql_mode instead of inheriting the server default, which differs
		// between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
		// so only NO_ENGINE_SUBSTITUTION is kept.
		mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

		// initialize variable
		$software_installed = false;

		// get all tables in database in order to determine if software is already installed
		$query = "SHOW TABLES";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		while ($row = mysqli_fetch_row($result)) {

			if (($row[0] == 'config') || ($row[0] == 'page') || ($row[0] == 'user')) {

				$software_installed = true;

				break;

			}

		}

		// if software is already installed, then check if upgrade option should be given
		if ($software_installed == true) {

			$database_version = get_database_version();

			// if the database version cannot be found, then it is probably before 4.5.0, so prepare notice
			if ($database_version == false) {

				$output_upgrade_message = lang('It appears that the software is already installed, however the version is less than 4.5.0. The upgrade feature only supports version 4.5.0 and greater. Alternatively, if you want to replace the existing site with a new site, you may complete the install form below.') . ' ';

				// else the database version can be found, so continue
				
			}
			else {

				$database_version_key = get_version_key($database_version, $versions);

				// An administrator sent here after the software files were updated, with a database
				// that is already at this version (a release without schema changes): there is
				// nothing to show, so the update flags are reset and they go straight back to the
				// control panel, as the automated upgrade always did.
				if (($install_autostart == true) && ($database_version_key !== false) && ($database_version_key >= $software_version_key)) {

					install_run_upgrades($versions, $database_version_key);

					header('Location: ../welcome.php');

					exit();

				}

				// The version that the database reports is not in the version list of this package.
				// We must not offer an upgrade here, because the upgrade would then start at the very
				// first version and run every step again on a site that is already up to date.
				if ($database_version_key === false) {

					$output_version_warning = '
					<div class="alert alert-danger d-flex gap-2 mt-3">
						<i class="bi bi-exclamation-octagon-fill"></i>
						<div>' . lang(array(
							'string' => 'The version in the database ({var:1}) is not part of this package, so the upgrade is not offered. Correct the version in the config table, or install the site again.',
							'vars' => h($database_version)
						)) . '</div>
					</div>';

				}

				// if database version key is less than software version key, then offer upgrade option
				if (($database_version_key !== false) && ($database_version_key < $software_version_key)) {

					$upgrade_option = true;

					$output_upgrade_message = lang('Please select whether you want to upgrade the existing site or replace the existing site with a new site.') . ' ';

					// if an install type has not already been selected, then select upgrade by default
					if ($liveform->get_field_value('install_type') != 'install') {
						$liveform->assign_field_value('install_type', 'upgrade');
						$output_submit_button_value = 'Upgrade';
						$output_submit_button_label = lang('Start the upgrade');
						$output_submit_button_process_label = lang('Upgrading');
						$output_submit_button_icon ='bi-arrow-up-circle';

					}

					

					// The install screen is locked until an administrator authenticates, so the upgrade
					// form does not ask for a login again.
					$output_upgrade_authentication = '';

					// Work out what the upgrade will actually do.  A version only touches the database when
					// it has an upgrade function, and the changelog tells us what that change is, so the
					// administrator can read what every step does before starting.
					$output_upgrade_versions = '';

					$upgrade_version_count = 0;

					$upgrade_touches_database = false;

					foreach ($versions as $upgrade_version_key => $upgrade_version) {

						if ($upgrade_version_key <= $database_version_key) {

							continue;

						}

						$upgrade_version_count++;

						if ($upgrade_first_version == '') {

							$upgrade_first_version = $upgrade_version['number'];

						}

						$upgrade_function_name = 'upgrade_to_' . str_replace('.', '_', $upgrade_version['number']);

						$upgrade_version_icon = 'bi-check-lg text-success';

						$upgrade_version_note = lang('no database change');

						if ((install_migration_file($upgrade_version['number']) != '') || (function_exists($upgrade_function_name))) {

							$upgrade_touches_database = true;

							$upgrade_version_icon = 'bi-database text-warning';

							$upgrade_version_note = lang('the database is updated');

							$upgrade_schema_note = get_install_schema_note($upgrade_version['number']);

							if ($upgrade_schema_note != '') {

								$upgrade_version_note = $upgrade_schema_note;

							}

						}

						$output_upgrade_versions .= '
						<div class="d-flex gap-2 py-1 small align-items-start">
							<i class="bi ' . $upgrade_version_icon . '" style="margin-top:.2rem;"></i>
							<div><b>' . h($upgrade_version['number']) . '</b> <span class="text-body-secondary">— ' . h($upgrade_version_note) . '</span></div>
						</div>';

					}

					// What this server looks like before the first step: the privileges of the database
					// user, the large tables the pending versions rewrite, a run that is still going,
					// what the last run left behind, and room for a backup.  Nothing here stops the
					// upgrade; it says what is likely to go wrong while something can still be done.
					$upgrade_preflight = install_preflight($versions, $database_version_key);

					$output_upgrade_preflight_rows = '';

					foreach ($upgrade_preflight['checks'] as $upgrade_check) {

						$upgrade_check_icon = 'bi-check-lg text-success';

						if ($upgrade_check['state'] == 'warning') {

							$upgrade_check_icon = 'bi-exclamation-triangle text-warning';

						} elseif ($upgrade_check['state'] == 'error') {

							$upgrade_check_icon = 'bi-x-lg text-danger';

						} elseif ($upgrade_check['state'] == 'info') {

							$upgrade_check_icon = 'bi-info-circle text-body-secondary';

						}

						$upgrade_check_detail = '';

						if ($upgrade_check['detail'] != '') {

							$upgrade_check_detail = '<div class="small text-body-secondary" style="padding-left:1.6rem;line-height:1.4;">' . h($upgrade_check['detail']) . '</div>';

						}

						$output_upgrade_preflight_rows .= '
						<div class="d-flex align-items-center gap-2 py-1 small">
							<i class="bi ' . $upgrade_check_icon . '"></i>
							<span class="text-nowrap">' . h($upgrade_check['label']) . '</span>
							<span class="ms-auto text-body-secondary font-monospace text-end">' . h($upgrade_check['value']) . '</span>
						</div>' . $upgrade_check_detail;

					}

					// the backup is written from here when the server can do it
					$output_upgrade_backup = '';

					if ($upgrade_preflight['backup_available'] == true) {

						$output_upgrade_backup = '
						<div class="d-flex flex-wrap align-items-center gap-2 mt-2" id="pg_upgrade_backup_row">
							<button type="button" class="btn btn-outline-primary btn-sm" id="pg_upgrade_backup"><i class="bi bi-download me-1"></i>' . lang('Back up the database now') . '</button>
							<span class="small text-body-secondary" id="pg_upgrade_backup_result">' . lang('Writes sql.sql into a pre_upgrade folder under data/backups, which this screen can restore later.') . '</span>
						</div>';

					}

					$output_upgrade_preflight = '
					<div class="mt-3 pt-3 border-top" id="pg_upgrade_preflight">
						<div class="small text-uppercase fw-bold text-body-secondary mb-1">' . lang('Before you start') . '</div>
						' . $output_upgrade_preflight_rows . $output_upgrade_backup . '
					</div>';

					// a run that stopped at one of the pending versions is continued, not started
					if ($upgrade_preflight['last'] !== null) {

						$output_upgrade_button_label = lang('Continue the upgrade');

						$output_submit_button_label = $output_upgrade_button_label;

					}

					// what has been added to the software since the version that is installed here
					$output_upgrade_changelog = '';

					$output_upgrade_changelog_rows = '';

					foreach (get_install_changelog_sections() as $changelog_section) {

						$changelog_section_key = get_version_key($changelog_section['version'], $versions);

						if (($changelog_section_key === false) || ($changelog_section_key <= $database_version_key)) {

							continue;

						}

						$changelog_section_summary = '';

						// A release written under topic headings is summarised by the
						// headings themselves.  One sentence lifted out of sixty entries
						// is not a summary of that release - it is whichever entry
						// happened to be typed first - and the areas it touched are what
						// an operator deciding whether to upgrade actually wants.
						// Counted, not just listed: the four areas the release actually
						// weighs most in are a better answer than the four that happen to
						// come first in the file.
						$changelog_section_groups = array();

						foreach ($changelog_section['entries'] as $changelog_section_entry) {

							$changelog_entry_group = isset($changelog_section_entry['group']) ? trim($changelog_section_entry['group']) : '';

							if ($changelog_entry_group == '') {

								continue;

							}

							if (!isset($changelog_section_groups[$changelog_entry_group])) {

								$changelog_section_groups[$changelog_entry_group] = 0;

							}

							$changelog_section_groups[$changelog_entry_group]++;

						}

						if (count($changelog_section_groups) > 1) {

							arsort($changelog_section_groups);

							$changelog_section_named = array_slice(array_keys($changelog_section_groups), 0, 4);

							// Left in the capitals the file writes them in: mbstring has no
							// Turkish casing, and MB_CASE_TITLE turns "İ" into an "i" with a
							// separate dot above it.
							// Joined with a middot rather than a comma: a heading can carry
							// its own comma ("Oturum, hesap ve güvenlik") and a comma-joined
							// list of those reads as one long run-on.
							$changelog_section_summary = implode(' · ', $changelog_section_named);

							if (count($changelog_section_groups) > count($changelog_section_named)) {

								$changelog_section_summary .= ' ' . lang(array(
									'string' => 'and {var:1} more areas',
									'vars' => (count($changelog_section_groups) - count($changelog_section_named))));

							}

						}

						// A version with no headings - every release before this one -
						// keeps the old summary: its first new-feature entry.
						if ($changelog_section_summary == '') {

							foreach ($changelog_section['entries'] as $changelog_section_entry) {

								$changelog_section_tag = mb_strtoupper($changelog_section_entry['tag']);

								if (($changelog_section_tag == 'YENİ') || ($changelog_section_tag == 'NEW')) {

									$changelog_section_summary = get_install_short_text($changelog_section_entry['text'], 120);

									break;

								}

							}

						}

						if ($changelog_section_summary == '') {

							$changelog_section_summary = get_install_short_text($changelog_section['entries'][0]['text'], 120);

						}

						$output_upgrade_changelog_rows .= '
						<div class="d-flex gap-2 py-2 border-bottom border-1">
							<span class="badge text-bg-primary pg-tag">' . h($changelog_section['version']) . '</span>
							<div class="small">' . h($changelog_section_summary) . '</div>
						</div>';

					}

					if ($output_upgrade_changelog_rows != '') {

						$output_upgrade_changelog = '
						<div class="card mb-4">
							<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								<i class="bi bi-star me-2"></i>' . lang(array('string' => 'Added since {var:1}', 'vars' => $database_version)) . '
							</div>
							<div class="card-body pt-0">
								<div class="pg-changelog">' . $output_upgrade_changelog_rows . '</div>
							</div>
						</div>';

					}

					$output_upgrade_warning = '
					<div class="alert alert-primary d-flex gap-2 mt-3 mb-0">
						<i class="bi bi-info-circle"></i>
						<div>' . lang('This upgrade does not change your database. Your pages, files and settings stay exactly as they are.') . '</div>
					</div>';

					if ($upgrade_touches_database == true) {

						$output_upgrade_warning = '
					<div class="alert alert-warning d-flex gap-2 mt-3 mb-0">
						<i class="bi bi-exclamation-triangle"></i>
						<div>' . lang('This version touches your database. It is recommended that you make a backup from the Backups area of the File Manager before you upgrade.') . '</div>
					</div>';

					}

					$output_upgrade ='
					<div class="col-12" id="pg_upgrade_panel">
						<div class="d-flex flex-wrap align-items-center gap-3 pt-3 pb-2">
							<img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/logo.png" width="78" height="78" alt="Pinegrap" class="pg-install-logo">
							<div class="flex-grow-1" style="min-width:16rem;">
								<h1 class="h3 mb-1">' . lang('A new version is ready') . '</h1>
								<p class="text-body-secondary mb-0">' . lang('The content of your site stays where it is. The upgrade only updates the database schema and the software files.') . '</p>
							</div>
						</div>
						<div class="pg-wizard-grid pg-upgrade-grid">
							<div class="pg-panels">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-arrow-up-circle me-2"></i>' . lang('Software upgrade') . '
									</div>
									<div class="card-body">
										<p class="text-body-secondary">' . lang('Every step between the version on this server and the version of this package is applied in order.') . '</p>
										<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
											<span class="badge rounded-pill text-bg-secondary" style="font-size:.95rem;padding:.4rem .8rem;">' . h($database_version) . '</span>
											<i class="bi bi-arrow-right text-body-secondary"></i>
											<span class="badge rounded-pill text-bg-primary" style="font-size:.95rem;padding:.4rem .8rem;">' . h($software_version) . '</span>
											<span class="small text-body-secondary ms-1">' . lang(array('string' => '{var:1} versions are applied', 'vars' => $upgrade_version_count)) . '</span>
										</div>
										' . $output_upgrade_versions . $output_upgrade_preflight . $output_upgrade_warning . '
										<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" id="pg_upgrade_actions">
											<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/"><i class="bi bi-arrow-left me-1"></i>' . lang('Give up') . '</a>
										</div>
										<div id="pg_upgrade_run"></div>
										' . $liveform->output_field(array(
											'type' => 'radio',
											'name' => 'install_type',
											'id' => 'upgrade',
											'value' => 'upgrade',
											'class' => 'form-check-input d-none'
										)) . '
									</div>
								</div>
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-danger fw-bold">
										<i class="bi bi-exclamation-triangle me-2"></i>' . lang('If you want to reinstall instead') . '
									</div>
									<div class="card-body">
										<div class="pg-source pg-mode-card mb-0" id="pg_install_mode_card">
											<span class="pg-radio"></span>
											<div class="flex-grow-1">
												<div class="fw-semibold">' . lang(array('string'=>'Install version {var:1} and replace existing site','vars'=>$software_version)) . '</div>
												<div class="small text-body-secondary">' . lang('All site data is permanently deleted. This option asks for a written confirmation.') . '</div>
												' . $liveform->output_field(array(
													'type' => 'radio',
													'name' => 'install_type',
													'id' => 'install',
													'value' => 'install',
													'class' => 'radio form-check-input d-none'
												)) . '
											</div>
										</div>
									</div>
								</div>
							</div>
							<aside class="pg-side">' . $output_upgrade_changelog . '</aside>
						</div>
					</div>';
				}

			}


		}

	}




	// initialize variable
	$output_install_option = '';
	// only set for a reinstallation below, but always printed in the form further down
	$reinstallation_verification = '';

	// if there is an upgrade option, then prepare to output install option as a radio button
	if ($upgrade_option != true) {
		//there is no upgrade option, so prepare to output install option as a hidden field
		$output_install_option = $liveform->output_field(array(
			'type' => 'hidden',
			'name' => 'install_type',
			'value' => 'install'
		));
	}

	// The card that asks whether an existing site may be replaced is always part of the form, so the
	// wizard can show it as soon as the database check finds a site.  Until then it stays hidden.
	$reinstallation_verification_class = ' d-none';

	if (!empty($_SESSION['software']['install']['reinstall'])) {

		$reinstallation_verification_class = '';

	}

	$reinstallation_verification ='
		<div class="pg-danger-panel d-flex gap-2 align-items-start' . $reinstallation_verification_class . '" id="pg_reinstall_box">
			<i class="bi bi-exclamation-octagon-fill text-danger"></i>
			<div class="flex-grow-1">
				<div class="fw-semibold">' . lang('A site already exists in this database') . '</div>
				<div class="small text-body-secondary">' . lang('The installation permanently deletes everything that belongs to it: pages, files, styles and products.') . '</div>
				<div class="form-check form-switch mt-2">
					' . $liveform->output_field(array(
						'type' => 'hidden',
						'name' => 'reinstall_software'
					)) . $liveform->output_field(array(
						'type' => 'checkbox',
						'name' => 'reinstall_software',
						'id' => 'reinstall_software',
						'value' => '1',
						'class' => 'form-check-input'
					)) . '
					<label class="form-check-label small" for="reinstall_software">' . lang('Reinstall anyway and delete the data') . '</label>
				</div>
				<div class="small text-danger mt-1 d-none" id="pg_reinstall_message">' . lang('Please verify that you want to reinstall.') . '</div>
			</div>
		</div>';

	// The starter sites that ship with the software are offered separately from the backups that an
	// administrator has made, so the person who is installing can tell the two apart.
	$install_starter_sites = get_available_starter_sites();

	$install_backups = get_site_backups();

	$install_selected_folder = $liveform->get_field_value('install_from_folder');

	$starter_site_folders = get_starter_site_folders();

	// work out which source the form should open on
	$install_source_type = 'starter';

	if (($install_selected_folder != '') && (!isset($starter_site_folders[$install_selected_folder]))) {

		$install_source_type = 'backup';

	}

	// The upload field is only offered when the screen was unlocked, which can only happen when a
	// site exists in the database and an administrator authenticated on the lock screen.  On a
	// server that has no site yet we never offer a public upload.
	$install_upload_allowed = $install_site_exists;

	// prepare the starter sites
	$output_starter_sites = '';

	foreach ($install_starter_sites as $starter_site) {

		$output_starter_site_selected = '';

		if (($install_source_type == 'starter') && (($install_selected_folder == $starter_site['folder']) || ($install_selected_folder == ''))) {

			$output_starter_site_selected = ' selected';

			// only the first matching starter site is selected
			$install_selected_folder = $starter_site['folder'];

		}

		$output_starter_sites .= '
		<div class="pg-source' . $output_starter_site_selected . '" data-source="starter" data-folder="' . h($starter_site['folder']) . '">
			<span class="pg-radio"></span>
			<div class="flex-grow-1">
				<div class="fw-semibold"><i class="bi bi-star me-1 text-primary"></i>' . lang(array('string' => '{var:1} starter site', 'vars' => $starter_site['label'])) . '</div>
				<div class="small text-body-secondary">' . lang('Sample pages, a ready made design and content are installed.') . ' <span class="font-monospace">' . h($starter_site['folder']) . '</span></div>
			</div>
		</div>';

	}

	// prepare the backups that are on the server
	$output_backup_options = '';

	foreach ($install_backups as $backup) {

		$output_backup_selected = '';

		if (($install_source_type == 'backup') && ($install_selected_folder == $backup['folder'])) {

			$output_backup_selected = ' selected="selected"';

		}

		$output_backup_label = $backup['folder'];

		if ($backup['modified'] != false) {

			$output_backup_label .= ' · ' . date('d.m.Y H:i', $backup['modified']);

		}

		if ($backup['installable'] == false) {

			$output_backup_label .= ' · ' . lang('no database file');

		}

		$output_backup_options .= '<option value="' . h($backup['folder']) . '"' . $output_backup_selected . '>' . h($output_backup_label) . '</option>';

	}

	$output_backup_source = '';

	if (count($install_backups) > 0) {

		$output_backup_source = '
		<div class="pg-source' . (($install_source_type == 'backup') ? ' selected' : '') . '" data-source="backup">
			<span class="pg-radio"></span>
			<div class="flex-grow-1">
				<div class="fw-semibold"><i class="bi bi-folder2-open me-1 text-primary"></i>' . lang('A backup on the server') . ' <span class="badge text-bg-secondary">' . count($install_backups) . '</span></div>
				<div class="small text-body-secondary">' . lang('The site is restored exactly as it was when the backup was made.') . '</div>
				<select class="form-select form-select-sm mt-2" id="pg_backup_select">' . $output_backup_options . '</select>
			</div>
		</div>';

	}

	$output_source_warning = '';

	if ((count($install_starter_sites) == 0) && (count($install_backups) == 0)) {

		$output_source_warning = '
		<div class="alert alert-danger small d-flex gap-2">
			<i class="bi bi-exclamation-triangle"></i>
			<div>' . lang('There is nothing in the backups folder to install from. The starter sites are normally in the data/backups folder of the software.') . '</div>
		</div>';

	}

	$output_upload_source = '';

	if ($install_upload_allowed == true) {

		$output_upload_source = '
		<div class="pg-source" data-source="upload">
			<span class="pg-radio"></span>
			<div class="flex-grow-1">
				<div class="fw-semibold"><i class="bi bi-cloud-arrow-up me-1 text-primary"></i>' . lang('Upload a backup from my computer') . '</div>
				<div class="small text-body-secondary">' . lang('The archive is extracted into the backups folder and then appears in the list above.') . '</div>
			</div>
		</div>
		<div class="pg-drop d-none" id="pg_zip_area">
			<div class="mb-2"><i class="bi bi-file-earmark-zip fs-3 text-primary"></i></div>
			<div class="fw-semibold">' . lang('Upload a backup archive') . '</div>
			<div class="small text-body-secondary mb-2">' . lang(array('string' => 'Only .zip files, and the server accepts at most {var:1}.', 'vars' => get_install_size_label(get_install_upload_limit()))) . '</div>
			<input type="file" name="backup_zip" id="backup_zip" accept=".zip,application/zip" class="form-control form-control-sm mb-2" data-limit="' . (int) get_install_upload_limit() . '">
			<div class="alert alert-danger small text-start d-none mb-2" id="pg_zip_too_large"></div>
			<button type="submit" class="btn btn-sm btn-outline-primary" name="submit_backup_zip" value="1" data-loading-content="' . lang('Please Wait') . '"><i class="bi bi-upload me-1"></i>' . lang('Upload and Extract') . '</button>
			<div class="alert alert-warning small text-start mt-3 mb-0 d-flex gap-2">
				<i class="bi bi-shield-check"></i>
				<div>' . lang('The archive has to contain a folder with sql.sql inside it, otherwise nothing is extracted. Paths in the archive are trimmed, so a prepared file cannot write outside of the backups folder.') . '</div>
			</div>
		</div>';

	}
	else {

		$output_upload_source = '
		<div class="alert alert-secondary small d-flex gap-2 mt-3 mb-0">
			<i class="bi bi-info-circle"></i>
			<div>' . lang('To restore a backup that is not on this server yet, copy the backup folder into the backups folder with FTP, or place the backup archive next to the download assistant and run it again.') . '</div>
		</div>';

	}

	// work out which step holds the first error, so the wizard can open that step
	$install_error_step = 0;

	if ($liveform->check_form_errors() == true) {

		$install_error_step = 3;

		if (($liveform->check_field_error('admin_username') == true) || ($liveform->check_field_error('admin_email_address') == true) || ($liveform->check_field_error('admin_confirm_email_address') == true) || ($liveform->check_field_error('admin_password') == true) || ($liveform->check_field_error('admin_confirm_password') == true)) {

			$install_error_step = 4;

		}

		if (($liveform->check_field_error('db_host') == true) || ($liveform->check_field_error('db_username') == true) || ($liveform->check_field_error('db_password') == true) || ($liveform->check_field_error('db_database') == true)) {

			$install_error_step = 3;

		}

		if ($liveform->check_field_error('default_software_language') == true) {

			$install_error_step = 2;

		}

		if ($liveform->check_field_error('install_from_folder') == true) {

			$install_error_step = 1;

		}

	}

	// the question about replacing an existing site belongs to the last step
	if ((!empty($_SESSION['software']['install']['reinstall'])) || ($liveform->check_field_error('reinstall_software') == true)) {

		$install_error_step = 5;

	}

	// let an administrator see that the screen is unlocked and for how long
	$output_unlock_notice = '';

	$install_unlock_minutes = get_install_unlock_minutes();

	if ($install_unlock_minutes > 0) {

		$output_unlock_notice = '<span class="badge rounded-pill text-bg-secondary"><i class="bi bi-unlock-fill me-1"></i>' . lang(array('string' => 'Unlocked for {var:1} more minutes', 'vars' => $install_unlock_minutes)) . '</span>';

	}

	// prepare the server checks
	$output_system_checks = '';

	foreach (get_install_system_checks() as $check) {

		$output_check_icon = 'bi-check-lg text-success';

		if ($check['state'] == 'warning') {

			$output_check_icon = 'bi-exclamation-triangle text-warning';

		}

		if ($check['state'] == 'error') {

			$output_check_icon = 'bi-x-lg text-danger';

		}

		$output_check_detail = '';

		if ((isset($check['detail'])) && ($check['detail'] != '')) {

			$output_check_detail = '<div class="small text-body-secondary" style="padding-left:1.6rem;line-height:1.4;">' . h($check['detail']) . '</div>';

		}

		$output_system_checks .= '
		<div class="d-flex align-items-center gap-2 py-1 small">
			<i class="bi ' . $output_check_icon . '"></i>
			<span>' . h($check['label']) . '</span>
			<span class="ms-auto text-body-secondary font-monospace">' . h($check['value']) . '</span>
		</div>' . $output_check_detail;

	}

	// prepare the release notes for the version that is about to be installed
	$output_changelog = '';

	$install_changelog = get_install_changelog();

	if ($install_changelog != false) {

		$output_changelog_entries = '';

		$changelog_group = '';

		foreach ($install_changelog['entries'] as $changelog_entry) {

			// A release written under topic headings keeps them here too, so the
			// panel reads as the file does rather than as one long column.
			$changelog_entry_group = isset($changelog_entry['group']) ? $changelog_entry['group'] : '';

			if ($changelog_entry_group != $changelog_group) {

				$changelog_group = $changelog_entry_group;

				if ($changelog_group != '') {

					$output_changelog_entries .= '
			<div class="small fw-bold text-uppercase text-body-secondary pt-3 pb-1">' . h($changelog_group) . '</div>';

				}

			}

			// the entries in the file are long, so we shorten them for this panel
			$changelog_entry['text'] = get_install_short_text($changelog_entry['text'], 190);

			$output_changelog_entries .= '
			<div class="d-flex gap-2 py-2 border-bottom border-1">
				<span class="badge ' . get_install_changelog_tag_class($changelog_entry['tag']) . ' pg-tag">' . h($changelog_entry['tag']) . '</span>
				<div class="small">' . h($changelog_entry['text']) . '</div>
			</div>';

		}

		$output_changelog = '
		<div class="card mb-4">
			<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
				<i class="bi bi-star me-2"></i>' . lang('What is new in this version') . '
			</div>
			<div class="card-body pt-0">
				<div class="mb-2"><span class="badge text-bg-primary">' . h($install_changelog['version']) . '</span></div>
				<div class="pg-changelog">' . $output_changelog_entries . '</div>
			</div>
		</div>';

	}

	print

	get_header() . '
	<style>
		.pg-install-logo {
			padding: 7px;
			border-radius: 24px;
			border: 1px solid var(--bs-border-color);
			background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-warning-rgb), .16));
		}
		.pg-wizard-on .pg-step-panel { display: none; }
		.pg-wizard-on .pg-step-panel.active { display: block; }
		.pg-step {
			display: flex;
			gap: .7rem;
			align-items: flex-start;
			width: 100%;
			text-align: start;
			padding: .55rem .7rem;
			border: 1px solid transparent;
			border-radius: 1rem;
			background: transparent;
			color: inherit;
		}
		.pg-step:hover { background: var(--bs-secondary-bg); }
		.pg-step.active { background: var(--bs-secondary-bg); border-color: var(--bs-border-color); }
		.pg-step-dot {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			border: 2px solid var(--bs-border-color);
			background: var(--bs-body-bg);
			display: flex;
			align-items: center;
			justify-content: center;
			flex: none;
			font-size: .78rem;
			font-weight: 700;
		}
		.pg-step.active .pg-step-dot { background: var(--bs-primary); border-color: var(--bs-primary); color: var(--bs-body-bg); }
		.pg-step.done .pg-step-dot { background: var(--bs-success); border-color: var(--bs-success); color: var(--bs-body-bg); }
		.pg-source {
			display: flex;
			gap: .75rem;
			align-items: flex-start;
			padding: .8rem .9rem;
			margin-bottom: .6rem;
			border: 2px solid var(--bs-border-color);
			border-radius: 1rem;
			background: var(--bs-body-bg);
			cursor: pointer;
		}
		.pg-source.selected { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); }
		.pg-radio {
			width: 20px;
			height: 20px;
			margin-top: .15rem;
			border-radius: 50%;
			border: 2px solid var(--bs-border-color);
			flex: none;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.pg-source.selected .pg-radio { border-color: var(--bs-primary); }
		.pg-source.selected .pg-radio:after {
			content: "";
			width: 10px;
			height: 10px;
			border-radius: 50%;
			background: var(--bs-primary);
		}
		.pg-drop {
			border: 2px dashed var(--bs-border-color);
			border-radius: 1rem;
			padding: 1.1rem;
			text-align: center;
			background: var(--bs-body-bg);
		}
		.pg-changelog { max-height: 320px; overflow: auto; }
		.pg-tag { font-size: .62rem; height: fit-content; }
		.pg-review { display: grid; grid-template-columns: 1fr; gap: 0 1.5rem; }
		.pg-review div { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem 0; border-bottom: 1px dashed var(--bs-border-color); }
		@media (min-width: 768px) { .pg-review { grid-template-columns: 1fr 1fr; } }
		main#content.pg-install { max-width: 1280px; }
		.pg-wizard-grid { display: grid; gap: 1.15rem; grid-template-columns: minmax(0, 1fr); }
		@media (min-width: 992px) {
			.pg-wizard-grid { grid-template-columns: 240px minmax(0, 1fr); }
			.pg-side { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 1.15rem; align-items: start; }
		}
		@media (min-width: 1200px) {
			.pg-wizard-grid { grid-template-columns: 250px minmax(0, 1fr) 310px; }
			.pg-side { grid-column: auto; display: block; position: sticky; top: 5rem; }
		}
		/* the upgrade screen has no step rail, so it only has the panel and the side column */
		@media (min-width: 992px) {
			.pg-upgrade-grid { grid-template-columns: minmax(0, 1fr) 310px; }
			.pg-upgrade-grid .pg-side { grid-column: auto; display: block; }
		}
		/* the line only runs between the dots, so it never hangs below the last step */
		.pg-step { position: relative; z-index: 1; }
		.pg-step:not(:last-of-type):after {
			content: "";
			position: absolute;
			left: 1.4rem;
			top: 2.4rem;
			height: calc(100% - 1.6rem);
			width: 2px;
			background: var(--bs-border-color);
			z-index: -1;
		}
		.pg-step.done:not(:last-of-type):after { background: var(--bs-success); opacity: .5; }
		#pg_progress_bar { background: linear-gradient(90deg, var(--pg-logo-color-1), var(--pg-logo-color-2)); }

		/* softer notices, closer to the rest of the screen */
		.pg-install .alert {
			border: 0;
			border-radius: 1rem;
			padding: .7rem .9rem;
			font-size: .85rem;
		}
		.pg-install .alert-primary { background: rgba(var(--bs-primary-rgb), .10); color: var(--bs-body-color); }
		.pg-install .alert-primary i { color: var(--bs-primary); }
		.pg-install .alert-warning { background: rgba(var(--bs-warning-rgb), .13); color: var(--bs-body-color); }
		.pg-install .alert-warning i { color: var(--bs-warning-text-emphasis); }
		.pg-install .alert-danger { background: rgba(var(--bs-danger-rgb), .12); color: var(--bs-body-color); }
		.pg-install .alert-danger i { color: var(--bs-danger); }
		.pg-install .alert-success { background: rgba(var(--bs-success-rgb), .12); color: var(--bs-body-color); }
		.pg-install .alert-success i { color: var(--bs-success); }
		.pg-install .alert-secondary { background: var(--bs-secondary-bg); color: var(--bs-body-color); }

		/* buttons keep the size of their text, so an icon does not make them tall */
		.pg-install .btn i { font-size: 1rem; line-height: 1; }
		.pg-install .btn .material-icons { font-size: 1.1rem; }

		.pg-strength { display: flex; gap: .25rem; margin-top: .4rem; }
		.pg-strength i { height: 4px; flex: 1; border-radius: 999px; background: var(--bs-border-color); }
		.pg-strength i.on { background: var(--bs-success); }

		/* the question about replacing an existing site */
		.pg-danger-panel {
			border: 1px solid rgba(var(--bs-danger-rgb), .35);
			background: rgba(var(--bs-danger-rgb), .08);
			border-radius: 1rem;
			padding: .85rem .95rem;
			margin-top: .9rem;
		}

		.pg-install-console {
			background: #0f1115;
			color: #d7dee8;
			border-radius: 1rem;
			padding: .9rem 1rem;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: .78rem;
			line-height: 1.75;
			max-height: 320px;
			overflow: auto;
		}
		.pg-install-console .pg-time { color: #6b7688; }
		.pg-install-console .pg-ok { color: #4ade80; }
		.pg-install-console .pg-warning { color: #fbbf24; }
		.pg-install-console .pg-error { color: #f87171; }
		.pg-install-console .pg-detail { opacity: .6; }
		#pg_run_bar { background: linear-gradient(90deg, var(--pg-logo-color-1), var(--pg-logo-color-2)); }
	</style>
	<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body d-print-none">
	  	<ul class="navbar-nav me-auto">
			<li class="nav-item"><button onclick="javascript:history.go(-1)" type="button" class="nav-link" title="' . lang('Cancel') . '"  data-loading-content=" "   aria-label="Close"><span class=" material-icons">arrow_back</span></button></li>
	  	</ul>
	  	<ul class="navbar-nav ms-auto">
			<li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
				<button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
				<ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
				</ul>
			</li>
	  	</ul>
	</nav>
  	<main id="content" class="container-xl pg-install">
    	<div class="row">
    	  	<div class="col-12">
    	  	  	' . $liveform->output_errors() . '
    	  	  	' . $liveform->get_warnings() . '
    	  	  	' . $liveform->output_notices() . '
					' . $output_version_warning . '
				<div class="d-flex flex-wrap align-items-center gap-3 pt-3 pb-2" id="pg_install_hero">
					<img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/logo.png" width="78" height="78" alt="Pinegrap" class="pg-install-logo">
					<div class="flex-grow-1" style="min-width:16rem;">
						<h1 class="h3 mb-1" title="' . lang('Installation') . '">' . lang('Pinegrap installation') . '</h1>
						<p class="text-body-secondary mb-0">' . lang('Your site will be online in five short steps. If you already have a backup, you can restore it from the same wizard.') . '</p>
					</div>
					' . $output_unlock_notice . '
					' . (($upgrade_option == true) ? '<button type="button" class="btn btn-sm btn-outline-secondary" id="pg_back_to_upgrade"><i class="bi bi-arrow-left me-1"></i>' . lang('Software upgrade') . '</button>' : '') . '
				</div>
				<div class="d-flex align-items-center gap-3 mb-3" id="pg_progress_row">
					<span class="small text-body-secondary text-nowrap">' . lang('Step') . ' <b id="pg_step_number">1</b> / 5</span>
					<div class="progress flex-grow-1" style="height:7px;"><div class="progress-bar" id="pg_progress_bar" style="width:20%;"></div></div>
					<span class="small text-body-secondary text-nowrap" id="pg_step_title">' . lang('Source') . '</span>
				</div>
				<form method="post" enctype="multipart/form-data" id="install_form" style="margin: 0px">
    	  	  	  	' . get_token_field() . '
					<input type="hidden" name="progress_id" id="progress_id" value="">
    	  	  	  	<div class="row">
    	  	  	  	  	' . $output_upgrade . '
    	  	  	  	  	' . $output_install_option . '
    	  	  	  	</div>
					<div class="row" id="install_fields">
						' . $output_install_authentication . '

						<div class="col-12">
						<div class="pg-wizard-grid">
							<nav class="pg-rail" id="pg_rail">
								<button type="button" class="pg-step active" data-step="1">
									<span class="pg-step-dot">1</span>
									<span><span class="d-block fw-semibold">' . lang('Source') . '</span><span class="d-block small text-body-secondary">' . lang('Starter site, server backup or a zip file') . '</span></span>
								</button>
								<button type="button" class="pg-step" data-step="2">
									<span class="pg-step-dot">2</span>
									<span><span class="d-block fw-semibold">' . lang('Software Language') . '</span><span class="d-block small text-body-secondary">' . lang('Language of the control panel') . '</span></span>
								</button>
								<button type="button" class="pg-step" data-step="3">
									<span class="pg-step-dot">3</span>
									<span><span class="d-block fw-semibold">' . lang('Database') . '</span><span class="d-block small text-body-secondary">' . lang('MySQL connection information') . '</span></span>
								</button>
								<button type="button" class="pg-step" data-step="4">
									<span class="pg-step-dot">4</span>
									<span><span class="d-block fw-semibold">' . lang('Administrator') . '</span><span class="d-block small text-body-secondary">' . lang('The first administrator account') . '</span></span>
								</button>
								<button type="button" class="pg-step" data-step="5">
									<span class="pg-step-dot">5</span>
									<span><span class="d-block fw-semibold">' . lang('Installation') . '</span><span class="d-block small text-body-secondary">' . lang('Summary and installation') . '</span></span>
								</button>
							</nav>

							<div class="pg-panels">

							<div class="pg-step-panel active" data-panel="1">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-box-seam me-2"></i>' . lang('Installation Folder') . '
									</div>
									<div class="card-body">
										<p class="text-body-secondary">' . lang('What should the site be built from? Choose one of the ready made starter sites for a new site.') . '</p>
										' . $liveform->output_field(array(
										'type' => 'hidden',
										'id' => 'install_from_folder',
										'name' => 'install_from_folder'
										)) . '
										' . $output_source_warning . $output_starter_sites . $output_backup_source . $output_upload_source . '
										<div class="text-end mt-3"><button type="button" class="btn btn-primary pg-next">' . lang('Continue') . '<i class="bi bi-arrow-right ms-1"></i></button></div>
									</div>
								</div>
							</div>

							<div class="pg-step-panel" data-panel="2">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-globe2 me-2"></i>' . lang('Software Language') . '
									</div>
									<div class="card-body">
										<div class="row">
											<div class="col-12 col-md-8 my-2">
												<label for="default_software_language" class="form-label">' . lang('Default Software Language') . $output_enforcement . '</label>
												' . $liveform->output_field(array(
												'type' => 'select',
												'id' => 'default_software_language',
												'name' => 'default_software_language',
												'class' => 'form-select',
												'options' => get_software_language_options()
												)) . '
											</div>
											<div class="col-12">
												<div class="alert alert-primary small d-flex gap-2 mb-0">
													<i class="bi bi-info-circle"></i>
													<div>' . lang('This is the language of the control panel. It is separate from the language of the content that gets installed, so the two can be different.') . '</div>
												</div>
											</div>
										</div>
										<div class="d-flex justify-content-between mt-3">
											<button type="button" class="btn btn-outline-secondary pg-previous"><i class="bi bi-arrow-left me-1"></i>' . lang('Back') . '</button>
											<button type="button" class="btn btn-primary pg-next">' . lang('Continue') . '<i class="bi bi-arrow-right ms-1"></i></button>
										</div>
									</div>
								</div>
							</div>

							<div class="pg-step-panel" data-panel="3">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-database me-2"></i>' . lang('MySQL Database') . '
									</div>
									<div class="card-body">
										<p class="text-body-secondary">' . lang('An empty database is enough. You can find this information in the databases section of your hosting panel.') . '</p>
										<div class="row">
											<div class="col-12 col-md-6 my-2">
												<label for="db_host" class="form-label">' . lang('Database Hostname') . '</label>
												<div class="input-group">
													' . $liveform->output_field(array(
													'type' => 'text',
													'name' => 'db_host',
													'id' => 'db_host',
													'class' => 'form-control',
													'value' => 'localhost'
													)) . '
													<div class="input-group-text" title="' . lang('e.g. localhost, mysql.example.com or 192.168.0.1') . '">(?)</div>
												</div>
											</div>
											<div class="col-12 col-md-6 my-2">
												<label for="db_database" class="form-label">' . lang('Database Name') . '</label>
												' . $liveform->output_field(array(
												'type' => 'text',
												'name' => 'db_database',
												'id' => 'db_database',
												'class' => 'form-control',
												'autocomplete' => 'database_name'
												)) . '
											</div>
											<div class="col-12 col-md-6 my-2">
												<label for="db_username" class="form-label">' . lang('Database Username') . '</label>
												' . $liveform->output_field(array(
												'type' => 'text',
												'name' => 'db_username',
												'id' => 'db_username',
												'class' => 'form-control',
												'autocomplete' => 'database_username'
												)) . '
											</div>
											<div class="col-12 col-md-6 my-2">
												<label for="db_password" class="form-label">' . lang('Database Password') . '</label>
												' . $liveform->output_field(array(
												'type' => 'password',
												'name' => 'db_password',
												'id' => 'db_password',
												'class' => 'form-control',
												'autocomplete' => 'new-password'
												)) . '
											</div>
										</div>
										<div class="d-flex flex-wrap align-items-center gap-2 mt-2">
											<button type="button" class="btn btn-sm btn-outline-primary" id="pg_test_database"><i class="bi bi-hdd-network me-1"></i>' . lang('Test the connection') . '</button>
											<span class="small text-body-secondary" id="pg_test_hint">' . lang('You can verify the connection before you start the installation.') . '</span>
										</div>
										<div class="alert small d-none mt-2 mb-0" id="pg_test_result"></div>
										<div class="d-flex justify-content-between mt-3">
											<button type="button" class="btn btn-outline-secondary pg-previous"><i class="bi bi-arrow-left me-1"></i>' . lang('Back') . '</button>
											<button type="button" class="btn btn-primary pg-next">' . lang('Continue') . '<i class="bi bi-arrow-right ms-1"></i></button>
										</div>
									</div>
								</div>
							</div>

							<div class="pg-step-panel" data-panel="4">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-person me-2"></i>' . lang('Administrator User') . '
									</div>
									<div class="card-body">
										<p class="text-body-secondary">' . lang('The first account that manages the site. Password reset e-mails go to this address.') . '</p>
										<div class="row">
											<div class="col-12 col-md-4 my-2">
												<label for="admin_username" class="form-label">' . lang('Username') . '</label>
												' . $liveform->output_field(array(
												'type' => 'text',
												'id' => 'admin_username',
												'name' => 'admin_username',
												'class' => 'form-control',
												'autocomplete' => 'username'
												)) . '
											</div>
											<div class="col-12 col-md-4 my-2">
												<label for="admin_email_address" class="form-label">' . lang('E-mail Address') . '</label>
												' . $liveform->output_field(array(
												'type' => 'text',
												'id' => 'admin_email_address',
												'name' => 'admin_email_address',
												'maxlength'=>'100',
												'inputmode'=>'email',
												'data-inputmask-alias'=>'email',
												'class' => 'form-control',
												'autocomplete' => 'off'
												)) . '
											</div>
											<div class="col-12 col-md-4 my-2">
												<label for="admin_confirm_email_address" class="form-label">' . lang('Confirm E-mail Address') . '</label>
												' . $liveform->output_field(array(
												'type' => 'text',
												'id' => 'admin_confirm_email_address',
												'name' => 'admin_confirm_email_address',
												'maxlength'=>'100',
												'inputmode'=>'email',
												'data-inputmask-alias'=>'email',
												'class' => 'form-control',
												'autocomplete' => 'off'
												)) . '
											</div>
											<div class="col-12 col-md-4 my-2">
												<label for="admin_password" class="form-label">' . lang('Password') . '</label>
												' . $liveform->output_field(array(
												'type' => 'password',
												'id' => 'admin_password',
												'name' => 'admin_password',
												'class' => 'form-control',
												'autocomplete' => 'off'
												)) . '
												<div class="pg-strength" id="pg_strength"><i></i><i></i><i></i><i></i></div>
												<div class="small text-body-secondary" id="pg_strength_text">&nbsp;</div>
											</div>
											<div class="col-12 col-md-4 my-2">
												<label for="admin_confirm_password" class="form-label">' . lang('Confirm Password') . '</label>
												' . $liveform->output_field(array(
												'type' => 'password',
												'id' => 'admin_confirm_password',
												'name' => 'admin_confirm_password',
												'class' => 'form-control',
												'autocomplete' => 'off'
												)) . '
											</div>
										</div>
								<div class="row">
						<div class="col-12">
						  <div class="btn-group justify-content-start">
							  ' . $liveform->output_field(array(
							  'type' => 'checkbox',
							  'id' => 'show_advanced_settings',
							  'name' => 'show_advanced_settings',
							  'class' => 'btn-check collapse-switcher',
							  'data-bs-target' => '#advanced_row'
							  )) . '
							  <label class="btn btn-outline-primary" for="show_advanced_settings"><span class="me-1 material-icons">tune</span>' . lang('Advanced Options') . '</label>
						  </div>
						</div>
						<div class="col-12" >
						  <div class="row collapse" id="advanced_row">
							  <div class="col-12" >
								  <div class="card my-4">
									  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										  ' . lang('System SMTP Setup') . '
									  </div>
									  <div class="card-body">
										  <div class="row">
											  <div class="col-12 col-md-4 my-2">
												  <label for="system_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
												  ' . $liveform->output_field(array(
												  'type' => 'text',
												  'id' => 'system_smtp_hostname',
												  'name' => 'system_smtp_hostname',
												  'class' => 'form-control'
												  )) . ' 
											  </div>
											  <div class="col-12 col-md-2 my-2">
												  <label for="system_smtp_port" class="form-label">' . lang('Port') . '</label>
												  ' . $liveform->output_field(array(
												  'type' => 'number',
												  'id' => 'system_smtp_port',
												  'placeholder' => '587',
												  'name' => 'system_smtp_port',
												  'class' => 'form-control'
												  )) . ' 
											  </div>
											  <div class="col-12 col-md-3 my-2">
												  <label for="system_smtp_username" class="form-label">' . lang('Username') . '</label>
												  ' . $liveform->output_field(array(
												  'type' => 'text',
												  'id' => 'system_smtp_username',
												  'name' => 'system_smtp_username',
												  'class' => 'form-control'
												  )) . ' 
											  </div>
											  <div class="col-12 col-md-3 my-2">
												  <label for="system_smtp_password" class="form-label">' . lang('Password') . '</label>
												  ' . $liveform->output_field(array(
												  'type' => 'password',
												  'id' => 'system_smtp_password',
												  'name' => 'system_smtp_password',
												  'class' => 'form-control'
												  )) . ' 
											  </div>
										  </div>
									  </div>
								  </div>
							  </div>
							  <div class="col-12" >
								  <div class="card my-4">
									  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										  ' . lang('Email Campaign Setup') . '
									  </div>
									  <div class="card-body">
										  <div class="row">
											  <div class="col-12 my-3">
												  <div class="form-check form-switch">
													  ' . $liveform->output_field(array(
													  'type' => 'checkbox',
													  'id' => 'email_campaign_job',
													  'name' => 'email_campaign_job',
													  'class' => 'form-check-input collapse-switcher',
													  'value'=>'true',
													  'data-bs-target' => '#email_campaign_job_row'
													  )) . ' 
													  <label class="form-check-label" for="email_campaign_job">' . lang('Enable Email Campaign Job') . '</label>
												  </div>
												  <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="email_campaign_job_row">
													  <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
													  <div class="popover-body">
														  <div class="row">
															  <div class="col-12 col-lg-6 my-2">
																  <label for="campaign_smtp_hostname" class="form-label">' . lang('Hostname') . '</label>
																  ' . $liveform->output_field(array(
																  'type' => 'text',
																  'id' => 'campaign_smtp_hostname',
																  'name' => 'campaign_smtp_hostname',
																  'class' => 'form-control'
																  )) . ' 
															  </div>
															  <div class="col-12 col-md-6 col-lg-3 my-2">
																  <label for="campaign_smtp_number_of_emails" class="form-label" title="' . lang('Maximum number of Emails each time.') . '">' . lang('Max.') . '(?)</label>
																  ' . $liveform->output_field(array(
																  'type' => 'number',
																  'id' => 'campaign_smtp_number_of_emails',
																  'name' => 'campaign_smtp_number_of_emails',
																  'placeholder' => '25',
																  'class' => 'form-control'
																  )) . ' 
															  </div>
															  <div class="col-12 col-md-6 col-lg-3 my-2">
																  <label for="campaign_smtp_port" class="form-label">' . lang('Port') . '</label>
																  ' . $liveform->output_field(array(
																  'type' => 'number',
																  'id' => 'campaign_smtp_port',
																  'name' => 'campaign_smtp_port',
																  'placeholder' => '587',
																  'class' => 'form-control'
																  )) . ' 
															  </div>
															  <div class="col-12 col-lg-6 my-2">
																  <label for="campaign_smtp_username" class="form-label">' . lang('Username') . '</label>
																  ' . $liveform->output_field(array(
																  'type' => 'text',
																  'id' => 'campaign_smtp_username',
																  'name' => 'campaign_smtp_username',
																  'class' => 'form-control'
																  )) . ' 
															  </div>
															  <div class="col-12 col-lg-6 my-2">
																  <label for="campaign_smtp_password" class="form-label">' . lang('Password') . '</label>
																  ' . $liveform->output_field(array(
																  'type' => 'password',
																  'id' => 'campaign_smtp_password',
																  'name' => 'campaign_smtp_password',
																  'class' => 'form-control'
																  )) . ' 
															  </div>
														  </div>
													  </div>
												  </div>
											  </div>
										  </div>
									  </div>
								  </div>
							  </div>
						  </div>
						</div>
								</div>
										<div class="d-flex justify-content-between mt-3">
											<button type="button" class="btn btn-outline-secondary pg-previous"><i class="bi bi-arrow-left me-1"></i>' . lang('Back') . '</button>
											<button type="button" class="btn btn-primary pg-next">' . lang('Continue') . '<i class="bi bi-arrow-right ms-1"></i></button>
										</div>
									</div>
								</div>
							</div>

							<div class="pg-step-panel" data-panel="5">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-lightning-charge me-2"></i>' . lang('Summary and installation') . '
									</div>
									<div class="card-body">
										<p class="text-body-secondary">' . lang('Everything is ready. Please do not close this window while the installation runs.') . '</p>
										<div class="pg-review small">
											<div><span class="text-body-secondary">' . lang('Installation Folder') . '</span><b id="pg_review_folder">-</b></div>
											<div><span class="text-body-secondary">' . lang('Software Language') . '</span><b id="pg_review_language">-</b></div>
											<div><span class="text-body-secondary">' . lang('MySQL Database') . '</span><b id="pg_review_database">-</b></div>
											<div><span class="text-body-secondary">' . lang('Administrator User') . '</span><b id="pg_review_admin">-</b></div>
											<div><span class="text-body-secondary">' . lang('Version') . '</span><b>' . h($software_version) . '</b></div>
										</div>
										<div class="alert alert-warning small d-flex gap-2 mt-3 mb-0">
											<i class="bi bi-exclamation-triangle"></i>
											<div>' . lang('The installation resets the Pinegrap tables in the database that you entered. If you are restoring an existing site, it is recommended that you make a backup first.') . '</div>
										</div>
										' . $reinstallation_verification . '
										<div class="d-flex flex-wrap align-items-center gap-2 mt-3" id="pg_install_actions">
											<button type="button" class="btn btn-outline-secondary pg-previous"><i class="bi bi-arrow-left me-1"></i>' . lang('Back') . '</button>
										</div>

										<div class="d-none" id="pg_run_area">
											<div class="d-flex align-items-center gap-3 mt-3 mb-2">
												<span class="small text-body-secondary font-monospace" id="pg_run_percent">%0</span>
												<div class="progress flex-grow-1" style="height:7px;"><div class="progress-bar" id="pg_run_bar" style="width:0;"></div></div>
												<span class="small text-body-secondary text-truncate" style="max-width:14rem;" id="pg_run_now">' . lang('Please Wait') . '</span>
											</div>
											<div class="pg-install-console" id="pg_run_console"></div>
											<div id="pg_run_result"></div>
										</div>
									</div>
								</div>
							</div>

							</div>

							<aside class="pg-side">
								<div class="card mb-4">
									<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
										<i class="bi bi-shield-check me-2"></i>' . lang('Server check') . '
									</div>
									<div class="card-body pt-0">
										' . $output_system_checks . '
									</div>
								</div>
								' . $output_changelog . '
							</aside>
						</div>
						</div>

					</div>
    	  	  	  	<div class="buttons navigation text-center" id="install_submit_nav" aria-label="data edit buttons ">
    	  	  	        	<div class=" btn-group flex-wrap justify-content-center">
    	  	  	            	<button type="submit" id="submit" name="submit" value="' . $output_submit_button_value . '" class="btn my-1  btn-success " data-loading-content="' . $output_submit_button_process_label . '"><i class="bi ' . $output_submit_button_icon . ' me-2"></i><span class="btn-text" >' . $output_submit_button_label . '</span></button>
    	  	  	        	</div>
    	  	  	  	</div>
    	  	  	</form>
				<iframe name="pg_install_frame" id="pg_install_frame" title="installation" style="display:none;width:0;height:0;border:0;"></iframe>
    	  	</div>
    	</div>
  	</main>
  	<script type="text/javascript">
	  	function upgrade_install_switch() {
	  		if ($("input[name=\'install_type\']").length > 0) {
				if(	$("#upgrade").is(":checked")) {
					$("#submit").val("Upgrade");
					$("#submit").html("<i class=\'bi bi-arrow-up-circle me-2\'></i><span class=\'btn-text\'>' . escape_javascript($output_upgrade_button_label) . '</span>");
				}
				if(	$("#install").is(":checked")) {
					$("#submit").val("Install");
					$("#submit").html("<i class=\'bi bi-play-fill me-2\'></i><span class=\'btn-text\'>' . lang('Start the installation') . '</span>");
				}
    		}
		}

		// The installation runs in a hidden frame and calls these three functions while it works, so
		// the output appears inside the card on this page.
		var pg_install_started = false;

		var pg_install_finished = false;

		// The upgrade runs one version per request (pg_upgrade_start below).  These come from
		// the server: how many versions are ahead, and whether the screen should start by
		// itself because an administrator was sent here after the files were updated.
		var pg_upgrade_total = ' . (int) $upgrade_version_count . ';

		var pg_upgrade_from = "' . escape_javascript($database_version) . '";

		var pg_upgrade_to = "' . escape_javascript($software_version) . '";

		var pg_upgrade_database = "' . escape_javascript(defined('DB_DATABASE') ? DB_DATABASE . '@' . DB_HOST : '') . '";

		var pg_upgrade_first = "' . escape_javascript($upgrade_first_version) . '";

		var pg_install_autostart = ' . (($install_autostart == true) ? 'true' : 'false') . ';

		// the name of the file that the installation reports into
		function pg_install_make_id() {
			var id = "";
			var characters = "0123456789abcdef";
			for (var index = 0; index < 16; index++) {
				id += characters.charAt(Math.floor(Math.random() * 16));
			}
			return id;
		}

		var pg_install_seen_steps = {};

		var pg_install_poll_timer = null;

		function pg_install_stream_step(index, seconds, label, detail, state, percent) {
			var console_element = document.getElementById("pg_run_console");
			if (!console_element) { return; }
			if (pg_install_seen_steps[index]) { return; }
			pg_install_seen_steps[index] = true;
			var mark = (state === "warning") ? "<span class=\"pg-warning\">&#9650;</span>" : ((state === "error") ? "<span class=\"pg-error\">&#10007;</span>" : "<span class=\"pg-ok\">&#10003;</span>");
			var line = document.createElement("div");
			var safe = document.createElement("span");
			safe.textContent = label;
			var safe_detail = document.createElement("span");
			safe_detail.textContent = detail;
			line.innerHTML = "<span class=\"pg-time\">[" + seconds + " s]</span> " + mark + " " + safe.innerHTML +
				(detail ? " <span class=\"pg-detail\">" + safe_detail.innerHTML + "</span>" : "");
			if (state === "error") { line.className = "pg-error"; }
			console_element.appendChild(line);
			console_element.scrollTop = console_element.scrollHeight;
			document.getElementById("pg_run_bar").style.width = percent + "%";
			document.getElementById("pg_run_percent").textContent = "%" + percent;
			document.getElementById("pg_run_now").textContent = label;
		}

		function pg_install_stream_done(title) {
			if (pg_install_finished === true) { return; }
			pg_install_finished = true;
			if (pg_install_poll_timer) { clearInterval(pg_install_poll_timer); }
			document.getElementById("pg_run_bar").style.width = "100%";
			document.getElementById("pg_run_percent").textContent = "%100";
			document.getElementById("pg_run_now").textContent = title;
		}

		function pg_install_stream_complete(html) {
			pg_install_finished = true;
			if (pg_install_poll_timer) { clearInterval(pg_install_poll_timer); }
			$("#pg_run_result").html(html);
		}

		// The progress file carries the steps, what the runner is doing right now, the notes of
		// every version (which statements were already in place) and, when a step failed, the
		// version, the message and the statement.  "after" runs once with the answer.
		function pg_install_poll(after) {
			$.get(window.location.pathname, {
				install_action: "progress",
				progress_id: $("#progress_id").val(),
				at: new Date().getTime()
			}, null, "json").done(function(answer) {
				if ((!answer) || (!answer.steps)) { if (after) { after(null); } return; }
				$.each(answer.steps, function(index, step) {
					pg_install_stream_step(step.i, step.s, step.l, step.d, step.t, step.p);
				});
				if ((answer.running) && (pg_install_finished !== true)) {
					document.getElementById("pg_run_now").textContent = answer.running;
				}
				if (answer.notes) { pg_install_notes = answer.notes; }
				if (answer.error) {
					pg_install_stream_error(answer.error);
				} else if (answer.done === true) {
					pg_install_stream_done("' . escape_javascript(lang('The installation is complete')) . '");
					if (pg_install_poll_timer) { clearInterval(pg_install_poll_timer); }
				}
				if (after) { after(answer); }
			}).fail(function() {
				if (after) { after(null); }
			});
		}

		var pg_install_notes = {};

		// A failed step: the console already carries the red line, this is the explanation and
		// the way forward.  Every step is safe to repeat, so "try again" simply resubmits.
		function pg_install_stream_error(error) {
			if (pg_install_finished === true) { return; }
			pg_install_finished = true;
			if (pg_install_poll_timer) { clearInterval(pg_install_poll_timer); }
			document.getElementById("pg_run_now").textContent = "";
			if ($("#pg_run_result").children().length > 0) { return; }
			var safe = document.createElement("div");
			safe.textContent = error.message || "";
			var safe_statement = document.createElement("div");
			safe_statement.textContent = error.statement || "";
			var safe_version = document.createElement("span");
			safe_version.textContent = error.version || "";
			var notes = "";
			if ((error.version) && (pg_install_notes[error.version])) {
				var safe_notes = document.createElement("div");
				safe_notes.textContent = pg_install_notes[error.version].join("\n");
				notes = "<pre class=\"small text-body-secondary mt-2 mb-0\" style=\"white-space:pre-wrap;max-height:10rem;overflow:auto;\">" + safe_notes.innerHTML + "</pre>";
			}
			$("#pg_run_result").html(
				"<div class=\"alert alert-danger d-flex gap-2 align-items-start mt-3 mb-0\"><i class=\"bi bi-exclamation-triangle-fill\"></i><div class=\"flex-grow-1\">" +
				"<b>' . escape_javascript(lang('The upgrade stopped at version')) . ' " + safe_version.innerHTML + "</b>" +
				"<div class=\"small mt-1\">" + safe.innerHTML + "</div>" +
				(error.statement ? "<div class=\"small font-monospace text-body-secondary mt-1\">" + safe_statement.innerHTML + "</div>" : "") +
				notes +
				"<div class=\"small mt-2\">' . escape_javascript(lang('The versions before it are recorded, and every step can be run again: start the upgrade once more and it continues from here. If the same statement fails again, the message above says what the database objected to.')) . '</div>" +
				"<div class=\"d-flex gap-2 flex-wrap mt-2\"><button type=\"button\" class=\"btn btn-primary\" onclick=\"pg_install_retry();\"><i class=\"bi bi-arrow-repeat me-1\"></i>' . escape_javascript(lang('Try again')) . '</button></div>" +
				"</div></div>");
		}

		// Runs the same request again.  The server continues from the last version it recorded.
		function pg_install_retry() {
			if (pg_upgrade_total > 0) { pg_upgrade_resume(); return; }
			pg_install_started = false;
			pg_install_finished = false;
			$("#pg_run_result").empty();
			$("#submit").trigger("click");
		}

		// ── The upgrade, one version per request ──────────────────────────────────
		//
		// The screen asks the server for the next version, shows what came back and asks
		// again, until the server says it is done.  The server writes the version number the
		// moment a step returns, so whatever interrupts a request - a timeout, a closed tab,
		// a lost connection - loses at most the one version that was running, and every
		// step is safe to run again.  A long ALTER TABLE therefore lives inside one request
		// of its own rather than in a request that has to carry a hundred of them.
		var pg_upgrade_active = false;

		var pg_upgrade_applied = 0;

		var pg_upgrade_line = 0;

		var pg_upgrade_started_at = 0;

		var pg_upgrade_current = "";

		var pg_upgrade_failures = 0;

		var pg_upgrade_lock_waits = 0;

		var pg_upgrade_timer = null;

		var pg_upgrade_success_html = ' . json_encode('
			<div class="alert alert-success d-flex gap-2 align-items-start mt-3 mb-0">
				<i class="bi bi-check-circle-fill"></i>
				<div class="flex-grow-1">
					<b>' . lang('The upgrade is complete') . '</b> <span class="text-body-secondary">{from} → {to} · {count}</span>
					<div class="d-flex gap-2 flex-wrap mt-2">
						<a class="btn btn-primary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/"><i class="bi bi-arrow-right me-1"></i>' . lang('Control Panel') . '</a>
						<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . '" target="_blank"><i class="bi bi-eye me-1"></i>' . lang('View Site') . '</a>
					</div>
				</div>
			</div>') . ';

		function pg_upgrade_seconds() {
			return ((new Date().getTime() - pg_upgrade_started_at) / 1000).toFixed(1);
		}

		function pg_upgrade_add_line(label, detail, state) {
			var percent = Math.min(99, Math.round((pg_upgrade_applied / Math.max(1, pg_upgrade_total)) * 100));
			pg_install_stream_step(pg_upgrade_line, pg_upgrade_seconds(), label, detail, state, percent);
			pg_upgrade_line++;
		}

		function pg_upgrade_start() {
			if (pg_upgrade_active) { return; }
			pg_upgrade_active = true;
			pg_upgrade_started_at = new Date().getTime();
			pg_upgrade_applied = 0;
			pg_upgrade_line = 0;
			pg_upgrade_current = pg_upgrade_first;
			pg_upgrade_failures = 0;
			pg_upgrade_lock_waits = 0;
			pg_install_finished = false;
			pg_install_seen_steps = {};
			pg_install_notes = {};
			$("#pg_run_console").empty();
			$("#pg_run_result").empty();
			$("#pg_run_bar").css("width", "0");
			$("#pg_run_percent").text("%0");
			$("#pg_run_now").text("' . escape_javascript(lang('Please Wait')) . '");
			$("#pg_run_area").removeClass("d-none");
			$("#pg_upgrade_actions").addClass("d-none");
			$("#pg_upgrade_backup_row").addClass("d-none");
			$("#pg_install_mode_card").closest(".card").addClass("d-none");
			pg_upgrade_add_line("' . escape_javascript(lang('Connected to the database')) . '", pg_upgrade_database, "ok");
			pg_upgrade_add_line("' . escape_javascript(lang('Worked out the version chain')) . '", pg_upgrade_from + " → " + pg_upgrade_to, "ok");
			pg_upgrade_step();
		}

		// After a failure or a lost connection: ask again.  The server continues from the last
		// version it recorded, so the version that stopped is simply run once more.
		function pg_upgrade_resume() {
			if (pg_upgrade_active) { return; }
			pg_upgrade_active = true;
			pg_upgrade_failures = 0;
			pg_upgrade_lock_waits = 0;
			pg_install_finished = false;
			$("#pg_run_result").empty();
			if (pg_upgrade_started_at === 0) { pg_upgrade_start(); pg_upgrade_active = true; return; }
			pg_upgrade_step();
		}

		function pg_upgrade_step() {
			if (pg_upgrade_timer) { clearTimeout(pg_upgrade_timer); pg_upgrade_timer = null; }
			if (pg_upgrade_current) {
				$("#pg_run_now").text("' . escape_javascript(lang('Version')) . ' " + pg_upgrade_current);
			}
			$.ajax({
				url: window.location.pathname,
				type: "POST",
				dataType: "json",
				timeout: 0,
				data: { install_action: "upgrade_step", token: $("input[name=token]").val(), at: new Date().getTime() }
			}).done(function(answer) {
				pg_upgrade_answer(answer);
			}).fail(function(xhr, status) {
				pg_upgrade_failed(xhr, status);
			});
		}

		function pg_upgrade_answer(answer) {
			if ((!answer) || (typeof answer !== "object")) { pg_upgrade_failed(null, "empty"); return; }
			pg_upgrade_failures = 0;
			if (answer.session) { pg_upgrade_session_lost(answer.error); return; }
			if (answer.notes) {
				$.each(answer.notes, function(version, notes) { pg_install_notes[version] = notes; });
			}
			if ((answer.last) && (answer.last.message)) {
				pg_upgrade_add_line("' . escape_javascript(lang('The last run stopped at')) . ' " + answer.last.version,
					answer.last.message + (answer.last.statement ? " — " + answer.last.statement : ""), "warning");
			}
			if (answer.locked) {
				// another request holds the lock: a cron, another tab, or the previous request
				// of this very screen, which the web server gave up on while the server kept going.
				// The lock goes away with that connection, so we simply ask again in a moment.
				pg_upgrade_lock_waits++;
				$("#pg_run_now").text(answer.error || "");
				if (pg_upgrade_lock_waits === 1) {
					pg_upgrade_add_line("' . escape_javascript(lang('Another upgrade is running')) . '", answer.error || "", "warning");
				}
				if (pg_upgrade_lock_waits > 720) {
					pg_upgrade_stop("' . escape_javascript(lang('Another upgrade has held the lock for an hour. Refresh this screen to see where it is.')) . '", true);
					return;
				}
				pg_upgrade_timer = setTimeout(pg_upgrade_step, 5000);
				return;
			}
			if (answer.steps) {
				$.each(answer.steps, function(index, step) {
					pg_upgrade_applied++;
					if (step.touched) {
						pg_upgrade_add_line("' . escape_javascript(lang('Version')) . ' " + step.number, step.detail, "ok");
					}
				});
			}
			if (!answer.ok) {
				pg_upgrade_add_line("' . escape_javascript(lang('Version')) . ' " + (answer.failed || "") + " ' . escape_javascript(lang('failed')) . '",
					(answer.error || "") + (answer.statement ? " — " + answer.statement : ""), "error");
				pg_upgrade_active = false;
				pg_install_stream_error({ version: answer.failed, message: answer.error, statement: answer.statement });
				return;
			}
			if (answer.done) {
				pg_upgrade_active = false;
				pg_upgrade_add_line("' . escape_javascript(lang('The upgrade is complete')) . '", pg_upgrade_to, "ok");
				pg_install_stream_done("' . escape_javascript(lang('The upgrade is complete')) . '");
				var count = "' . escape_javascript(lang('{var:1} versions were applied')) . '".replace("{var:1}", pg_upgrade_applied);
				if (pg_upgrade_applied === 0) { count = "' . escape_javascript(lang('the versions were applied by the other run')) . '"; }
				pg_install_stream_complete(pg_upgrade_success_html.replace("{from}", pg_upgrade_from).replace("{to}", pg_upgrade_to).replace("{count}", count));
				return;
			}
			pg_upgrade_current = answer.next || "";
			pg_upgrade_step();
		}

		// No answer, or one that is not JSON: the request died on the way, or the web server
		// gave up on it while the step kept running.  We ask again a few times - while the
		// server is still busy the lock answers, and when it is done the next version
		// follows - and then leave it to the person with a button that continues.
		function pg_upgrade_failed(xhr, status) {
			if ((xhr) && (xhr.status === 403) && (xhr.responseJSON) && (xhr.responseJSON.session)) {
				pg_upgrade_session_lost(xhr.responseJSON.error);
				return;
			}
			pg_upgrade_failures++;
			var what = "";
			if (status === "parsererror") { what = "' . escape_javascript(lang('the answer could not be read')) . '"; }
			if (status === "timeout") { what = "' . escape_javascript(lang('timeout')) . '"; }
			if ((xhr) && (xhr.status)) { what += (what ? " · " : "") + "HTTP " + xhr.status; }
			if (!what) { what = status || ""; }
			if (pg_upgrade_failures <= 3) {
				if (pg_upgrade_failures === 1) {
					pg_upgrade_add_line("' . escape_javascript(lang('The server did not answer')) . '", what + " · ' . escape_javascript(lang('asking again')) . '", "warning");
				}
				pg_upgrade_timer = setTimeout(pg_upgrade_step, 5000);
				return;
			}
			pg_upgrade_add_line("' . escape_javascript(lang('The server did not answer')) . '", what, "error");
			pg_upgrade_stop("' . escape_javascript(lang('The connection to the server was lost while a version was being applied. The server may still be working on it, or a limit stopped it. The versions before it are recorded, so the upgrade can continue from the same version.')) . '" + (pg_upgrade_current ? " (" + pg_upgrade_current + ", " + what + ")" : ""), false);
		}

		function pg_upgrade_stop(message, refresh_only) {
			pg_upgrade_active = false;
			$("#pg_run_now").text("");
			var safe = document.createElement("div");
			safe.textContent = message;
			$("#pg_run_result").html(
				"<div class=\"alert alert-warning d-flex gap-2 align-items-start mt-3 mb-0\"><i class=\"bi bi-exclamation-triangle-fill\"></i><div class=\"flex-grow-1\">" +
				"<div class=\"small\">" + safe.innerHTML + "</div>" +
				"<div class=\"d-flex gap-2 flex-wrap mt-2\">" +
				(refresh_only ? "" : "<button type=\"button\" class=\"btn btn-primary\" onclick=\"pg_upgrade_resume();\"><i class=\"bi bi-arrow-repeat me-1\"></i>' . escape_javascript(lang('Continue')) . '</button>") +
				"<a class=\"btn btn-outline-secondary\" href=\"" + window.location.pathname + "\"><i class=\"bi bi-arrow-clockwise me-1\"></i>' . escape_javascript(lang('Refresh')) . '</a>" +
				"</div></div></div>");
		}

		function pg_upgrade_session_lost(message) {
			pg_upgrade_active = false;
			$("#pg_run_now").text("");
			var safe = document.createElement("div");
			safe.textContent = message || "";
			$("#pg_run_result").html(
				"<div class=\"alert alert-danger d-flex gap-2 align-items-start mt-3 mb-0\"><i class=\"bi bi-exclamation-triangle-fill\"></i><div class=\"flex-grow-1\">" +
				"<b>' . escape_javascript(lang('Your session expired')) . '</b>" +
				"<div class=\"small mt-1\">" + safe.innerHTML + "</div>" +
				"<div class=\"small mt-1\">' . escape_javascript(lang('The versions that were applied are recorded. Sign in again and open this screen; the upgrade continues from the version that was recorded last.')) . '</div>" +
				"<div class=\"d-flex gap-2 flex-wrap mt-2\"><a class=\"btn btn-primary\" href=\"" + window.location.pathname + "\"><i class=\"bi bi-arrow-clockwise me-1\"></i>' . escape_javascript(lang('Refresh')) . '</a></div>" +
				"</div></div>");
		}

		function pg_install_stream_failed(message) {
			if (pg_install_poll_timer) { clearInterval(pg_install_poll_timer); }
			$("#pg_run_result").html("<div class=\"alert alert-danger d-flex gap-2 mt-3 mb-0\"><i class=\"bi bi-exclamation-triangle-fill\"></i><div>" + message + "</div></div>");
		}

		// The install form is one long form, and the wizard only changes how it is presented.
		// Without JavaScript every step stays visible, so the form still works.
		var pg_install_step = 1;

		var pg_install_step_titles = {
			1: "' . escape_javascript(lang('Source')) . '",
			2: "' . escape_javascript(lang('Software Language')) . '",
			3: "' . escape_javascript(lang('Database')) . '",
			4: "' . escape_javascript(lang('Administrator')) . '",
			5: "' . escape_javascript(lang('Installation')) . '"
		};

		// Every step checks its own fields before the wizard moves on, so nobody lands on the
		// summary with an empty database or administrator.  Going back is always allowed.
		function pg_install_check_step(step) {
			var problems = [];
			$("#install_fields .is-invalid").removeClass("is-invalid");

			if (step === 1) {
				if (!$("#install_from_folder").val()) {
					problems.push("' . escape_javascript(lang('Please choose what the site should be installed from.')) . '");
				}
			}

			if (step === 2) {
				if (!$("#default_software_language").val()) {
					$("#default_software_language").addClass("is-invalid");
					problems.push("' . escape_javascript(lang('Default Language is required.')) . '");
				}
			}

			if (step === 3) {
				var database_fields = {
					db_host: "' . escape_javascript(lang('Database Hostname is required.')) . '",
					db_username: "' . escape_javascript(lang('Database Username is required.')) . '",
					db_database: "' . escape_javascript(lang('Database Name is required.')) . '"
				};
				$.each(database_fields, function(field, message) {
					if (!$.trim($("#" + field).val())) {
						$("#" + field).addClass("is-invalid");
						problems.push(message);
					}
				});
			}

			if (step === 4) {
				var administrator_fields = {
					admin_username: "' . escape_javascript(lang('Username is required.')) . '",
					admin_email_address: "' . escape_javascript(lang('E-mail Address is required.')) . '",
					admin_confirm_email_address: "' . escape_javascript(lang('Confirm E-mail Address is required.')) . '",
					admin_password: "' . escape_javascript(lang('Password is required.')) . '",
					admin_confirm_password: "' . escape_javascript(lang('Confirm Password is required.')) . '"
				};
				$.each(administrator_fields, function(field, message) {
					if (!$.trim($("#" + field).val())) {
						$("#" + field).addClass("is-invalid");
						problems.push(message);
					}
				});
				if ($.trim($("#admin_email_address").val()) && ($.trim($("#admin_email_address").val()) !== $.trim($("#admin_confirm_email_address").val()))) {
					$("#admin_email_address, #admin_confirm_email_address").addClass("is-invalid");
					problems.push("' . escape_javascript(lang('The two administrator e-mail addresses you entered did not match.')) . '");
				}
				if ($("#admin_password").val() && ($("#admin_password").val() !== $("#admin_confirm_password").val())) {
					$("#admin_password, #admin_confirm_password").addClass("is-invalid");
					problems.push("' . escape_javascript(lang('The two administrator passwords you entered did not match.')) . '");
				}
			}

			return problems;
		}

		// As soon as the database step is left we ask the server whether a site is already in that
		// database, so the question about replacing it is asked here instead of after the install
		// button was pressed.
		function pg_install_check_database_state() {
			if ((!$.trim($("#db_host").val())) || (!$.trim($("#db_database").val()))) { return; }
			$.post(window.location.pathname, {
				install_action: "test_database",
				token: $("input[name=token]").val(),
				db_host: $("#db_host").val(),
				db_username: $("#db_username").val(),
				db_password: $("#db_password").val(),
				db_database: $("#db_database").val()
			}, null, "json").done(function(answer) {
				if (answer.state === "warning") {
					$("#pg_reinstall_box").removeClass("d-none");
				} else if (answer.state === "ok") {
					$("#pg_reinstall_box").addClass("d-none");
					$("#reinstall_software").prop("checked", false);
				}
			});
		}

		function pg_install_show_problems(step, problems) {
			var panel = $(".pg-step-panel[data-panel=" + step + "] .card-body");
			panel.find(".pg-step-problem").remove();
			if (problems.length === 0) { return; }
			var list = "";
			$.each(problems, function(index, message) { list += "<div>" + message + "</div>"; });
			panel.prepend("<div class=\"alert alert-danger small d-flex gap-2 pg-step-problem\"><i class=\"bi bi-exclamation-triangle\"></i><div>" + list + "</div></div>");
		}

		function pg_install_set_step(step) {
			if (step < 1) { step = 1; }
			if (step > 5) { step = 5; }

			// walk forward one step at a time, so the first step with a problem is the one we stop on
			if (step > pg_install_step) {
				for (var check = pg_install_step; check < step; check++) {
					var problems = pg_install_check_step(check);
					if (problems.length > 0) {
						pg_install_show_problems(check, problems);
						step = check;
						break;
					}
					pg_install_show_problems(check, []);

					// leaving the database step, so find out what is in that database
					if (check === 3) {
						pg_install_check_database_state();
					}
				}
			}
			pg_install_step = step;
			$(".pg-step-panel").removeClass("active");
			$(".pg-step-panel[data-panel=" + step + "]").addClass("active");
			$(".pg-step").each(function() {
				var number = parseInt($(this).attr("data-step"), 10);
				$(this).toggleClass("active", number === step);
				$(this).toggleClass("done", number < step);
				$(this).find(".pg-step-dot").html((number < step) ? "<i class=\"bi bi-check-lg\"></i>" : number);
			});
			$("#pg_step_number").text(step);
			$("#pg_step_title").text(pg_install_step_titles[step]);
			$("#pg_progress_bar").css("width", (step * 20) + "%");
			$("#install_submit_nav").toggleClass("d-none", step !== 5);
			if (step === 5) { pg_install_update_review(); }
			$("html, body").animate({ scrollTop: 0 }, 200);
		}

		function pg_install_update_review() {
			var folder = $("#install_from_folder").val();
			$("#pg_review_folder").text(folder ? folder : "-");
			$("#pg_review_language").text($("#default_software_language option:selected").text());
			var database = $("#db_database").val();
			var host = $("#db_host").val();
			$("#pg_review_database").text(database ? (database + "@" + host) : "-");
			var administrator = $("#admin_username").val();
			var email = $("#admin_email_address").val();
			$("#pg_review_admin").text(administrator ? (administrator + " · " + email) : "-");
		}

		function pg_install_select_source(element) {
			var source = $(element).attr("data-source");
			$(".pg-source").removeClass("selected");
			$(element).addClass("selected");
			$("#pg_zip_area").toggleClass("d-none", source !== "upload");
			if (source === "starter") {
				$("#install_from_folder").val($(element).attr("data-folder"));
			} else if (source === "backup") {
				$("#install_from_folder").val($("#pg_backup_select").val());
			} else {
				$("#install_from_folder").val("");
			}
		}

		function pg_install_update_mode() {
			var upgrading = ($("#upgrade").length > 0) && ($("#upgrade").is(":checked"));
			$("#install_fields").css("display", "").toggleClass("d-none", upgrading);
			$("#pg_progress_row").toggleClass("d-none", upgrading);
			$("#pg_install_hero").toggleClass("d-none", upgrading);
			$("#pg_upgrade_panel").css("display", "").toggleClass("d-none", !upgrading);
			$("#pg_install_mode_card").toggleClass("selected", !upgrading);
			if (upgrading) {
				$("#install_submit_nav").removeClass("d-none").appendTo("#pg_upgrade_actions");
				$("#pg_run_area").appendTo("#pg_upgrade_run");
			} else {
				$("#install_submit_nav").appendTo("#pg_install_actions");
				$("#pg_run_area").insertAfter("#pg_install_actions");
				pg_install_set_step(pg_install_step);
			}
		}

		$(document).ready(function() {
			upgrade_install_switch();

			$("input[name=\'install_type\']").on("click focus keydown", function(){
				upgrade_install_switch();
				pg_install_update_mode();
			});

			$(".pg-install #install_fields").closest("form").addClass("pg-wizard-on");

			$(".pg-step").on("click", function() {
				pg_install_set_step(parseInt($(this).attr("data-step"), 10));
			});

			$(".pg-next").on("click", function() {
				pg_install_set_step(pg_install_step + 1);
			});

			$(".pg-previous").on("click", function() {
				pg_install_set_step(pg_install_step - 1);
			});

			$("#pg_install_mode_card").on("click", function() {
				$("#install").prop("checked", true);
				upgrade_install_switch();
				pg_install_update_mode();
				$("html, body").animate({ scrollTop: 0 }, 200);
			});

			$("#pg_back_to_upgrade").on("click", function() {
				$("#upgrade").prop("checked", true);
				upgrade_install_switch();
				pg_install_update_mode();
				$("html, body").animate({ scrollTop: 0 }, 200);
			});

			$(".pg-source").not(".pg-mode-card").on("click", function(event) {
				if ($(event.target).is("select, option")) { return; }
				pg_install_select_source(this);
			});

			$("#pg_backup_select").on("change", function() {
				$("#install_from_folder").val($(this).val());
			});

			// If the frame finishes without reporting the end, then something went wrong on the
			// server.  The progress file is read one last time first: a step that failed, or a
			// fatal error the runner caught on shutdown, is written there with its message, and
			// that is far more useful than a pointer to the error log.
			$("#pg_install_frame").on("load", function() {
				if ((pg_install_started === true) && (pg_install_finished === false)) {
					pg_install_poll(function(answer) {
						if ((pg_install_finished === false) && ((!answer) || (!answer.error))) {
							pg_install_stream_failed("' . escape_javascript(lang('The installation stopped before it finished. Please check the server error log.')) . '");
						}
					});
				}
			});

			// a rough idea of how strong the administrator password is
			$("#admin_password").on("input", function() {
				var value = $(this).val();
				var score = 0;
				if (value.length >= 8) { score++; }
				if (value.length >= 12) { score++; }
				if (/[A-Z]/.test(value) && /[a-z]/.test(value)) { score++; }
				if (/[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)) { score++; }
				$("#pg_strength i").each(function(index) {
					$(this).toggleClass("on", index < score);
				});
				var labels = ["", "' . escape_javascript(lang('Weak')) . '", "' . escape_javascript(lang('Fair')) . '", "' . escape_javascript(lang('Good')) . '", "' . escape_javascript(lang('Strong')) . '"];
				$("#pg_strength_text").html(value ? labels[score] : "&nbsp;");
			});

			// hide the question again as soon as it is answered
			$("#reinstall_software").on("change", function() {
				if ($(this).is(":checked")) { $("#pg_reinstall_message").addClass("d-none"); }
			});

			// A file that is larger than the server accepts never reaches the script, so we say so
			// here instead of letting the person wait for a page that comes back unchanged.
			$("#backup_zip").on("change", function() {
				var limit = parseInt($(this).attr("data-limit"), 10);
				var warning = $("#pg_zip_too_large");
				var button = $("button[name=submit_backup_zip]");
				warning.addClass("d-none").text("");
				button.prop("disabled", false);
				if ((!this.files) || (this.files.length === 0) || (!limit)) { return; }
				var file = this.files[0];
				if (file.size > limit) {
					warning.removeClass("d-none").text("' . escape_javascript(lang('This file is larger than the server accepts. Copy the backup folder into the backups folder with FTP instead.')) . '");
					button.prop("disabled", true);
				}
			});

			// test the database connection without writing anything
			$("#pg_test_database").on("click", function() {
				var button = $(this);
				var result = $("#pg_test_result");
				button.prop("disabled", true);
				$("#pg_test_hint").text("' . escape_javascript(lang('Please Wait')) . '");
				result.addClass("d-none");
				$.post(window.location.pathname, {
					install_action: "test_database",
					token: $("input[name=token]").val(),
					db_host: $("#db_host").val(),
					db_username: $("#db_username").val(),
					db_password: $("#db_password").val(),
					db_database: $("#db_database").val()
				}, null, "json").done(function(answer) {
					var style = "alert-danger";
					if (answer.state === "ok") { style = "alert-success"; }
					if (answer.state === "warning") { style = "alert-warning"; }
					result.attr("class", "alert small mt-2 mb-0 " + style).text(answer.message);
				}).fail(function() {
					result.attr("class", "alert small mt-2 mb-0 alert-danger").text("' . escape_javascript(lang('The connection could not be tested. Please try again.')) . '");
				}).always(function() {
					button.prop("disabled", false);
					$("#pg_test_hint").text("");
				});
			});

			// a copy of the database before the first step, written by the server
			$("#pg_upgrade_backup").on("click", function() {
				var button = $(this);
				var result = $("#pg_upgrade_backup_result");
				button.prop("disabled", true);
				$("#submit").prop("disabled", true);
				result.attr("class", "small text-body-secondary").text("' . escape_javascript(lang('The backup is being written. Depending on the size of the database this takes between a few seconds and a few minutes.')) . '");
				$.ajax({
					url: window.location.pathname,
					type: "POST",
					dataType: "json",
					timeout: 0,
					data: { install_action: "backup_database", token: $("input[name=token]").val() }
				}).done(function(answer) {
					if ((answer) && (answer.ok)) {
						result.attr("class", "small text-success").text("' . escape_javascript(lang('The backup is ready')) . ': data/backups/" + answer.folder + "/sql.sql · " + answer.size + " · " + answer.seconds + " s");
					} else {
						result.attr("class", "small text-danger").text(((answer) && (answer.error)) ? answer.error : "' . escape_javascript(lang('The backup could not be written.')) . '");
						button.prop("disabled", false);
					}
				}).fail(function(xhr) {
					var message = "' . escape_javascript(lang('The backup could not be written.')) . '";
					if ((xhr) && (xhr.responseJSON) && (xhr.responseJSON.error)) { message = xhr.responseJSON.error; }
					result.attr("class", "small text-danger").text(message + ((xhr && xhr.status) ? " (HTTP " + xhr.status + ")" : ""));
					button.prop("disabled", false);
				}).always(function() {
					$("#submit").prop("disabled", false);
				});
			});

			// The installation itself runs in a hidden frame and reports every step back to this
			// page, so the output appears right here instead of on another screen.  The upgrade
			// does not: the screen drives it one version per request (pg_upgrade_start).
			$("#submit").on("click", function(event) {
				var upgrading = ($("#upgrade").length > 0) && ($("#upgrade").is(":checked"));

				if ((upgrading) && (pg_upgrade_total > 0)) {
					event.preventDefault();
					pg_upgrade_start();
					return false;
				}

				if ((!upgrading) && (!$("#pg_reinstall_box").hasClass("d-none")) && (!$("#reinstall_software").is(":checked"))) {
					event.preventDefault();
					$("#pg_reinstall_message").removeClass("d-none");
					$("#pg_reinstall_box")[0].scrollIntoView({ behavior: "smooth", block: "center" });
					return false;
				}

				pg_install_started = true;
				pg_install_finished = false;
				pg_install_seen_steps = {};
				$("#pg_run_console").empty();
				$("#pg_run_result").empty();
				$("#pg_run_bar").css("width", "0");
				$("#pg_run_percent").text("%0");
				$("#progress_id").val(pg_install_make_id());
				pg_install_poll_timer = setInterval(pg_install_poll, 700);
				$("#install_form").attr("target", "pg_install_frame");
				$("#pg_run_area").removeClass("d-none");
				if (upgrading) {
					$("#pg_upgrade_actions").addClass("d-none");
					$("#pg_install_mode_card").closest(".card").addClass("d-none");
				} else {
					$("#pg_install_actions").addClass("d-none");
					$("#pg_reinstall_box").addClass("d-none");
					$(".pg-step").addClass("disabled").css("pointer-events", "none");
				}
				return true;
			});

			// make sure the hidden field matches the source that is selected on screen
			if (!$("#install_from_folder").val()) {
				var selected_source = $(".pg-source.selected").first();
				if (selected_source.length > 0) {
					pg_install_select_source(selected_source[0]);
				}
			}

			// If the form came back with an error, then open the step that holds it.
			var error_step = ' . (int) $install_error_step . ';

			pg_install_set_step((error_step > 0) ? error_step : 1);

			pg_install_update_mode();

			// sent here after the software files were updated: start without a click
			if ((pg_install_autostart) && (pg_upgrade_total > 0) && ($("#upgrade").length > 0)) {
				$("#upgrade").prop("checked", true);
				upgrade_install_switch();
				pg_install_update_mode();
				pg_upgrade_start();
			}
		});
  	</script>' .

	get_footer();

	$liveform->remove_form('install');

	$_SESSION['software']['install']['reinstall'] = false;

	// else user has completed install form or this is being run as part of an automated upgrade, so process form
	
}
else {

	$liveform->add_fields_to_session();

	// if software should be upgraded, then upgrade site
	if (($liveform->get_field_value('install_type') == 'upgrade') || ($automated_upgrade == true)) {

		db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

		init_mysql_charset();

		// Pin the session sql_mode instead of inheriting the server default, which differs
		// between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
		// so only NO_ENGINE_SUBSTITUTION is kept.
		mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

		// The install screen locks itself when a site already exists in the database, so by the time
		// this form can be submitted the session is either logged in as an administrator or it has
		// authenticated on the lock screen.  We check it again here, because this is the request that
		// actually changes the site.
		if (($automated_upgrade == false) && (check_if_administrator_is_logged_in() == false) && (check_install_unlocked() == false)) {

			log_activity(lang('access denied to submit installation form because an administrator was not authenticated'), $_SESSION['sessionusername']);

			exit(lang('Please authenticate as an administrator of this site before you install or upgrade.'));

		}

		// The install form carries the session token.  The automated upgrade has no form and is
		// authenticated by its key or by the administrator's session above, so only the form
		// submission is checked: without this an administrator's browser could be made to start
		// the upgrade from a page on another site.
		if ($automated_upgrade == false) {

			$upgrade_session_token = (string) ($_SESSION['software']['token'] ?? '');

			$upgrade_posted_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

			if (($upgrade_session_token === '') || (!hash_equals($upgrade_session_token, $upgrade_posted_token))) {

				log_activity(lang('access denied to submit installation form because visitor\'s session expired or because request might have come from an unauthorized location'), $_SESSION['sessionusername']);

				exit(lang('Sorry, we could not accept your request because it appears that your session expired.'));

			}

		}

		// if an error exists, then return to form
		if ($liveform->check_form_errors() == true) {

			return_to_form();

		}

		$database_version = get_database_version();

		$database_version_key = get_version_key($database_version, $versions);

		// Refuse to upgrade from a version that is not in the version list of this package.  Without
		// this the loop below would start at the first version and run every step again.
		if ($database_version_key === false) {

			$liveform->mark_error('', lang(array(
				'string' => 'The version in the database ({var:1}) is not part of this package, so the upgrade is not offered. Correct the version in the config table, or install the site again.',
				'vars' => $database_version
			)));

			return_to_form();

		}

		// Count what is ahead of us, so the screen can show how far along the upgrade is.  Only the
		// versions that really do something to the database are reported, otherwise a site that is
		// years behind would print hundreds of lines.
		$upgrade_applied_count = 0;

		$upgrade_database_steps = 0;

		foreach ($versions as $version_key => $version) {

			if ($version_key <= $database_version_key) {

				continue;

			}

			$upgrade_applied_count++;

			if ((install_migration_file($version['number']) != '') || (function_exists(install_upgrade_function($version['number'])))) {

				$upgrade_database_steps++;

			}

		}

		$install_expected_steps = $upgrade_database_steps + 3;

		// from here on the page is sent to the browser step by step
		if ($automated_upgrade == false) {

			start_install_stream();

			add_install_step(lang('Connected to the database'), DB_DATABASE . '@' . DB_HOST);

			add_install_step(lang('Worked out the version chain'), $database_version . ' → ' . $software_version);

		}

		// The runner takes a lock, applies every version after the one in the database, writes
		// each number into config.version the moment its step returns, and turns a failing
		// statement into a reported failure instead of a dead page.  Every step is safe to run
		// twice, so after a failure the answer is simply to start the upgrade again.
		$upgrade_result = install_run_upgrades($versions, $database_version_key, array('stream' => ($automated_upgrade == false)));

		if ($upgrade_result['ok'] == false) {

			output_install_upgrade_failure($upgrade_result, $database_version, $automated_upgrade, $automated_upgrade_via);

		}

		if ($automated_upgrade == false) {

			add_install_step(lang('The upgrade is complete'), $software_version);

		}

		// When the upgrade was streamed we answer with a short result, because the screen that
		// started it is still there and only needs the outcome.
		if ($install_streaming == true) {

			finish_install_stream();

			print '
			<div id="pg_stream_result">
				<div class="alert alert-success d-flex gap-2 align-items-start mt-3 mb-0">
					<i class="bi bi-check-circle-fill"></i>
					<div class="flex-grow-1">
						<b>' . lang('The upgrade is complete') . '</b> <span class="text-body-secondary">' . h($database_version) . ' → ' . h($software_version) . ' · ' . lang(array('string' => '{var:1} versions were applied', 'vars' => $upgrade_applied_count)) . '</span>
						<div class="d-flex gap-2 flex-wrap mt-2">
							<a class="btn btn-primary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/"><i class="bi bi-arrow-right me-1"></i>' . lang('Control Panel') . '</a>
							<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . '" target="_blank"><i class="bi bi-eye me-1"></i>' . lang('View Site') . '</a>
						</div>
					</div>
				</div>
			</div>
			<script>pg_install_stream_complete();</script>' . get_footer();

			$liveform->remove_form('install');

			exit();

		}

		// A signed-in administrator who came through software_update.php goes back to the control
		// panel.  A cron (php from the command line, or the URL with the key) gets a plain text
		// summary that reads well in a cron log.
		if ($automated_upgrade == true) {

			if ($automated_upgrade_via == 'session') {

				header('Location: ../welcome.php');

				print 'complete';

			} else {

				header('Content-Type: text/plain; charset=utf-8');

				print 'ok: ' . $database_version . ' -> ' . $software_version . ' (' . count($upgrade_result['applied']) . ' versions applied)' . "\n";

			}

			// else this is not being run from an automated upgrade, so display full confirmation HTML

		}
		else {

			print
			get_header() . '
			<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body d-print-none">
				  <ul class="navbar-nav me-auto">
					<li class="nav-item"><button onclick="javascript:history.go(-1)" type="button" class="nav-link" title="' . lang('Cancel') . '"  data-loading-content=" "   aria-label="Close"><span class=" material-icons">arrow_back</span></button></li>
				  </ul>
				  <ul class="navbar-nav ms-auto">	
					<li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
						<button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
						<ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
							<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
							<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
							<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
						</ul>
					</li>
				  </ul>
			</nav>
			<main id="content" class="container">
			    <div class="row">
			      	<div class="col-12">
			        	<div class="row mb-2  flex-wrap">
			        	    <div class="col-12 col-sm-12 text-center text-md-start">
			        	        <h2 class="d-inline-block ">' . lang('Installation') . '</h2>
			        	    </div>
			        	</div>
			        </div>
					<div class="col-12 col-md-8 offset-md-2">
						<div class="card my-5 border-4">
							<div class="card-body">
								<h4 class="text-success text-center"><span class="material-icons" style="line-height:1em;font-size:4em;">check_circle</span><br/>' . lang(array('string'=>'Congratulations, the software has been upgraded successfully from version {var:1} to {var:2}.','vars'=>array($database_version,$software_version) )) . '</h4>
							</div>
							<div class="card-footer">
								<div class="text-center"><a class="btn" href="../" class="button_primary">' . lang('Continue') . '<span class="ms-1 material-icons">arrow_forward</span></a></div>
							</div>
						</div>
					</div>
			    </div>
			</main>' . get_footer();

			

		}

		$liveform->remove_form('install');

		// else if software should be installed, then install software
		
	}
	elseif (($liveform->get_field_value('install_type') == 'install') || ($liveform->get_field_value('install_type') == '')) {

		// if there are existing database contents in config.php file, then determine if there is an existing site and if we need to authenticate user
		if (defined('DB_HOST') == true) {

			db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

			init_mysql_charset();

			// Pin the session sql_mode instead of inheriting the server default, which differs
			// between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
			// so only NO_ENGINE_SUBSTITUTION is kept.
			mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

			// If the token does not exist in the session,
			// or the passed token does not match the token from the session,
			// then this might be a CSRF attack so log activity and exit with error.
			// We only care about the token for this area of the code (i.e. installs where it appears there is an existing site),
			// because it is the only dangerous area.  We don't want an attacker to be able to use CSRF
			// to overwrite an existing site without the admin's permission.  We don't add CSRF protection to other areas
			// of this script, because we need systems to be able to do fresh installs from a remote location (i.e. our trial system).
			if (

			($_SESSION['software']['token'] == '') ||

			(

			($_POST['token'] != $_SESSION['software']['token']) && ($_GET['token'] != $_SESSION['software']['token']))) {

				log_activity(lang('access denied to submit installation form because visitor\'s session expired or because request might have come from an unauthorized location'), $_SESSION['sessionusername']);

				exit(lang('Sorry, we could not accept your request because it appears that your session expired.'));

			}

			// initialize variable
			$software_installed = false;

			// get all tables in database in order to determine if software is already installed
			$query = "SHOW TABLES";

			$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

			while ($row = mysqli_fetch_row($result)) {

				if (($row[0] == 'config') || ($row[0] == 'page') || ($row[0] == 'user')) {

					$software_installed = true;

					break;

				}

			}

			// A site already exists in this database, so this request has to come from an administrator.
			// The install screen only unlocks after an administrator authenticates, and we verify that
			// again here, because this is the request that replaces the site.
			if (($software_installed == true) && (check_if_administrator_is_logged_in() == false) && (check_install_unlocked() == false)) {

				log_activity(lang('access denied to submit installation form because an administrator was not authenticated'), $_SESSION['sessionusername']);

				exit(lang('Please authenticate as an administrator of this site before you install or upgrade.'));

			}

		}


    	$liveform->validate_required_field('install_from_folder', lang('Install From Folder is required.'));
		
		$liveform->validate_required_field('default_software_language', lang('Default Language is required.'));
	
		$liveform->validate_required_field('db_host', lang('Database Hostname is required.'));

		$liveform->validate_required_field('db_username', lang('Database Username is required.'));

		$liveform->validate_required_field('db_database', lang('Database Name is required.'));

		$liveform->validate_required_field('admin_username', lang('Username is required.'));

		$liveform->validate_required_field('admin_email_address', lang('E-mail Address is required.'));

		$liveform->validate_required_field('admin_confirm_email_address', lang('Confirm E-mail Address is required.'));

		$liveform->validate_required_field('admin_password', lang('Password is required.'));

		$liveform->validate_required_field('admin_confirm_password', lang('Confirm Password is required.'));

		if ($liveform->get_field_value('install_from_folder') != '') {
			$install_directory_path = $liveform->get_field_value('install_from_folder');
		}

		

		// if an error does not exist for the database fields
		if (($liveform->check_field_error('db_host') == false) && ($liveform->check_field_error('db_username') == false) && ($liveform->check_field_error('db_password') == false) && ($liveform->check_field_error('db_database') == false)) {
			
			db::$con = @mysqli_connect($liveform->get_field_value('db_host') , $liveform->get_field_value('db_username') , $liveform->get_field_value('db_password'));

			// if connection is made to database with login information that was supplied
			if (db::$con) {

				// if database cannot be selected
				if (@mysqli_select_db(db::$con, $liveform->get_field_value('db_database')) == false) {

					$liveform->mark_error('db_database', lang('A connection to the MySQL server was successful, however the database name that you entered could not be selected. Please correct the database name. If the database name is correct, then the user might not have correct permissions to access the database. MySQL error') . ': ' . mysqli_error(db::$con));

					// else the database can be selected, so check to see if there is an existing site
					
				}
				else {

					init_mysql_charset();

					// Pin the session sql_mode instead of inheriting the server default, which differs
					// between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
					// so only NO_ENGINE_SUBSTITUTION is kept.
					mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

					// initialize variable
					$software_installed = false;

					// get all tables in database in order to determine if software is already installed in new database
					$query = "SHOW TABLES";

					$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

					while ($row = mysqli_fetch_row($result)) {

						if (($row[0] == 'config') || ($row[0] == 'page') || ($row[0] == 'user')) {

							$software_installed = true;

						}

					}

					// if software is already installed in new database
					if ($software_installed == true) {

						$_SESSION['software']['install']['reinstall'] = true;

						// if the reinstall check box did not appear on the form, then add notice
						if ($liveform->field_in_session('reinstall_software') == false) {

							$liveform->add_notice(lang('A site is already installed in the database that you entered. If you wish to reinstall please check to verify reinstallation.'));

							// else the reinstall check box appeared on the form, so require it
							
						}
						else {

							$liveform->validate_required_field('reinstall_software', lang('Please verify that you want to reinstall.'));

						}

						// else software is not already installed
						
					}
					else {

						$_SESSION['software']['install']['reinstall'] = false;

					}

				}

				// else a connection was not made to database
				
			}
			else {

				$liveform->mark_error('', lang('A connection to the MySQL server failed. Please correct the hostname, username, and/or password.  MySQL error') . ': ' . mysqli_connect_error());

			}

		}
		
		// if there is not already an error for the admin password fields, check to see if admin password and confirm password do not match
		if (($liveform->check_field_error('admin_password') == false) && ($liveform->check_field_error('admin_confirm_password') == false)) {

			if ($liveform->get_field_value('admin_password') != $liveform->get_field_value('admin_confirm_password')) {

				$liveform->mark_error('admin_password', lang('The two administrator passwords you entered did not match.'));

				$liveform->mark_error('admin_confirm_password');

				$liveform->assign_field_value('admin_password', '');

				$liveform->assign_field_value('admin_confirm_password', '');

			}

		}

		// if there is not already an error for the admin e-mail address fields, check to see if admin e-mail address and confirm e-mail adress do not match
		if (($liveform->check_field_error('admin_email_address') == false) && ($liveform->check_field_error('admin_confirm_email_address') == false)) {

			if ($liveform->get_field_value('admin_email_address') != $liveform->get_field_value('admin_confirm_email_address')) {

				$liveform->mark_error('admin_email_address', lang('The two administrator e-mail addresses you entered did not match.'));

				$liveform->mark_error('admin_confirm_email_address');

			}

		}

		// determine if config.php can be written to
		$handle = @fopen(CONFIG_FILE_PATH, 'a');

		// if config.php can be written to, close handle
		if ($handle == true) {

			fclose($handle);

			// else config.php cannot be written to, so mark error
			
		}
		else {

			$liveform->mark_error('config_access', lang(array('string'=>'The system could not write to the config.php file ({var:1}). Please configure the config.php file so it can be written to.  For Unix, set the permissions for the file to 777.  For Windows, give the anonymous web user rights to write to and delete the file.','vars'=> OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/data/config.php')) );

		}

		// create test file for writing in order to determine if files directory can be written to
		$handle = @fopen(FILE_DIRECTORY_PATH . '/test.txt', 'w');

		// if files directory can be written to, then delete test file
		if ($handle == true) {

			fclose($handle);

			unlink(FILE_DIRECTORY_PATH . '/test.txt');

			// else files directory cannot be written to, so mark error
			
		}
		else {

			$liveform->mark_error('files_access', lang(array('string'=>'The system could not write to the files directory ({var:1}). Please configure the files directory so it can be written to.  For Unix, set the permissions for the directory to 777.  For Windows, give the anonymous web user rights to write, delete, and "Delete subfolders and files".','vars'=> OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/data/files')));

		}

		// if there are errors or notices, then send user to previous screen
		if (($liveform->check_form_errors() == true) || ($liveform->check_form_notices() == true)) {

			return_to_form();

		}

		// from here on the page is sent to the browser step by step
		start_install_stream();

		add_install_step(lang('Connected to the database'), $liveform->get_field_value('db_database') . '@' . $liveform->get_field_value('db_host'));

		// If the database has tables in it, then get all system tables and then drop
		// them all before we install new fresh tables.
		// Even though the sql.sql already contains "DROP TABLE IF EXISTS" commands, we
		// do this anyway, because there are some situations where this is still necessary.  For
		// example if an admin is working with a new version that has new tables that the starter
		// template does not contain yet, and the admin tries to re-install, then we need to delete
		// the new existing tables before we install, in order to avoid SQL errors.  This is
		// necessary because this install script will run an update, if necessary, after the install
		// and try to create new tables.
		

		$current_tables = db_values("SHOW TABLES");

		if ($current_tables) {

			$system_tables = get_tables();

			foreach ($system_tables as $table) {

				db("DROP TABLE IF EXISTS `" . $table . "`");

			}

			add_install_step(lang('Removed the tables of the old site'), lang(array('string' => '{var:1} tables', 'vars' => count($system_tables))));

		}

		// Get the MySQL version so we know whether to use utf8mb4 or utf8.
		$mysql_version = preg_replace('#[^0-9\.]#', '', mysqli_get_server_info(db::$con));

		if (version_compare($mysql_version, '5.5.3', '>=') == true) {

			$character_set = 'utf8mb4';

		}
		else {

			$character_set = 'utf8';

		}

		// Update the charset for the db so that when future tables are created,
		// they will have the correct charset.
		db(

		"ALTER DATABASE `" . e($liveform->get_field_value('db_database')) . "`

			CHARACTER SET = " . $character_set . "

			COLLATE = " . $character_set . "_unicode_ci");

		add_install_step(lang('Set the character set'), $character_set . '_unicode_ci');

		// Prepare template sql file
		$database_file = dirname(__FILE__) . '/../data/backups/' . $install_directory_path . '/sql.sql';

		// run all queries from MySQL dump file for template
		$dump_result = parse_mysql_dump($database_file);

		if ($dump_result !== true) {

			$liveform->mark_error('', lang('There was an error while the database was being initialized. Please contact the software provider and include the error that appears next. MySQL error') . ': ' . h($dump_result['error']) . ' — <code>' . h($dump_result['statement']) . '</code>');

			return_to_form();

		}

		add_install_step(lang('Loaded the content of the database'), $install_directory_path . '/sql.sql · ' . lang(array('string' => '{var:1} tables', 'vars' => count(db_values("SHOW TABLES")))));

		// If the MySQL server supports utf8mb4, then convert all tables that were
		// created above from utf8 to utf8mb4.  The sql.sql file has utf8 set by default
		// so that is why this is necessary.
		

		if ($character_set == 'utf8mb4') {

			$tables = db_values("SHOW TABLES");

			foreach ($tables as $table) {

				db(

				"ALTER TABLE `" . $table . "`

					CONVERT TO CHARACTER SET " . $character_set . "

					COLLATE " . $character_set . "_unicode_ci");

				// We were having an issue with MariaDB (and maybe MySQL)
				// where the default character set was not set for tables
				// with only number type columns after the command above was run,
				// so we have to run the following similar command also.
				// We don't know why.
				db(

				"ALTER TABLE `" . $table . "`

					CHARACTER SET " . $character_set . "

					COLLATE " . $character_set . "_unicode_ci");

			}

		}

		// Check database if software_language column exists.
		$query ="SHOW COLUMNS FROM config LIKE 'software_language'";
		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
		// if software_language exist in config we can update it
    	if (mysqli_num_rows($result) != 0) {
			// Update config settings.
			$query ="UPDATE config SET software_language = '" . escape($liveform->get_field_value('default_software_language')) . "'";
			$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
		}else{
			//else software_language not exist, we add column to config
			db("ALTER TABLE config ADD software_language ENUM('en','tr') NOT NULL DEFAULT 'en'");
			// Update config settings.
			$query ="UPDATE config SET software_language = '" . escape($liveform->get_field_value('default_software_language')) . "'";
			$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
		}

		// Update config settings for hostname.
		$query ="UPDATE config SET hostname = '" . escape($_SERVER['HTTP_HOST']) . "'";
		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		
		// if a settings e-mail address was passed, then use it
		if ($liveform->get_field_value('settings_email_address') != '') {

			$settings_email_address = $liveform->get_field_value('settings_email_address');

			// else a settings e-mail address was not passed, so use admin e-mail address
			
		}
		else {

			$settings_email_address = $liveform->get_field_value('admin_email_address');

		}

		// set e-mail address in various places
		

		$query = "UPDATE config SET email_address = '" . escape($settings_email_address) . "' WHERE email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE config SET registration_email_address = '" . escape($settings_email_address) . "' WHERE registration_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE config SET membership_email_address = '" . escape($settings_email_address) . "' WHERE membership_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE config SET ecommerce_email_address = '" . escape($settings_email_address) . "' WHERE ecommerce_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE config SET affiliate_email_address = '" . escape($settings_email_address) . "' WHERE affiliate_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE custom_form_pages SET submitter_email_from_email_address = '" . escape($settings_email_address) . "' WHERE submitter_email_from_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE custom_form_pages SET administrator_email_to_email_address = '" . escape($settings_email_address) . "' WHERE administrator_email_to_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE custom_form_pages SET administrator_email_bcc_email_address = '" . escape($settings_email_address) . "' WHERE administrator_email_bcc_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE page SET comments_administrator_email_to_email_address = '" . escape($settings_email_address) . "' WHERE comments_administrator_email_to_email_address != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE products SET email_bcc = '" . escape($settings_email_address) . "' WHERE email_bcc != ''";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		db("UPDATE email_campaign_profiles SET from_email_address = '" . escape($settings_email_address) . "' WHERE from_email_address != ''");

		db("UPDATE email_campaign_profiles SET reply_email_address = '" . escape($settings_email_address) . "' WHERE reply_email_address != ''");

		db("UPDATE email_campaign_profiles SET bcc_email_address = '" . escape($settings_email_address) . "' WHERE bcc_email_address != ''");

		// add administrator user
		// We are setting the user's start page to the 294 page which is "staff-home".
		$query =

		"INSERT INTO user (

				user_username,

				user_email,

				user_password,

				user_role,

				user_home,

				user_timestamp)

			VALUES (

				'" . escape($liveform->get_field_value('admin_username')) . "',

				'" . escape($liveform->get_field_value('admin_email_address')) . "',

				'" . md5($liveform->get_field_value('admin_password')) . "',

				'0',

				'294',

				UNIX_TIMESTAMP())";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$user_id = mysqli_insert_id(db::$con);

		add_install_step(lang('Created the administrator account'), $liveform->get_field_value('admin_username') . ' · ' . $liveform->get_field_value('admin_email_address'));

		// Check if appmenu_items exist in users
		$query = "SHOW COLUMNS FROM user LIKE 'selected_appmenu_items_array'";		
		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
		// If the column exists, then we can update user with 'default' selected_appmenu_items_array value
		if (mysqli_num_rows($result) != 0) {
			$query = "UPDATE user SET selected_appmenu_items_array = 'default' WHERE user_id = '" . escape($user_id) . "'";
			$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
		}

		// Update last modified site settings info to contain info for this admin user, so that
		// software update check will immediately start sending admin email info to us.
		

		db(

		"UPDATE config

			SET

				last_modified_user_id = '$user_id',

				last_modified_timestamp = UNIX_TIMESTAMP()");

		// set all timestamps to the current timestamp
		

		$query = "UPDATE ad_regions SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE ad_regions SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE ads SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE ads SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE arrival_dates SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		db(

		"UPDATE auto_dialogs

			SET

				created_timestamp = UNIX_TIMESTAMP(),

				last_modified_timestamp = UNIX_TIMESTAMP()");

		$query = "UPDATE calendar_event_locations SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE calendar_event_locations SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE calendars SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE calendars SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE contact_groups SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE contact_groups SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE countries SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE cregion SET cregion_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE currencies SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE currencies SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE dregion SET dregion_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		db("UPDATE email_campaign_profiles SET created_timestamp = UNIX_TIMESTAMP()");

		db("UPDATE email_campaign_profiles SET last_modified_timestamp = UNIX_TIMESTAMP()");

		$query = "UPDATE files SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE folder SET folder_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE form_fields SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		db(

		"UPDATE forms

			SET

				submitted_timestamp = UNIX_TIMESTAMP(),

				last_modified_timestamp = UNIX_TIMESTAMP()");

		$query = "UPDATE key_codes SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE login_regions SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE login_regions SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE menus SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE menus SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE menu_items SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE menu_items SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE offers SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE offer_rules SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE offer_actions SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE order_reports SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE order_reports SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE page SET page_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE pregion SET pregion_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		db("UPDATE product_attributes SET created_timestamp = UNIX_TIMESTAMP()");

		db("UPDATE product_attributes SET last_modified_timestamp = UNIX_TIMESTAMP()");

		$query = "UPDATE products SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE product_groups SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE referral_sources SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE shipping_methods SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE short_links SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE short_links SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE states SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE style SET style_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE tax_zones SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE verified_shipping_addresses SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE verified_shipping_addresses SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE visitor_reports SET created_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE visitor_reports SET last_modified_timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$query = "UPDATE zones SET timestamp = UNIX_TIMESTAMP()";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		// delete all files from files directory
		$handle = opendir(FILE_DIRECTORY_PATH . '/');

		while (false !== ($file = readdir($handle))) {

			if (($file != '.') && ($file != '..')) {

				unlink(FILE_DIRECTORY_PATH . '/' . $file);

			}

		}

		closedir($handle);

		// prepare path to template files
		$template_files_path = dirname(__FILE__) . '/../data/backups/' . $install_directory_path . '/files/';

		$handle = opendir($template_files_path);

		// copy template files to files directory
		while (false !== ($file = readdir($handle))) {

			if (($file != '.') && ($file != '..')) {

				copy($template_files_path . $file, FILE_DIRECTORY_PATH . '/' . $file);

			}

		}

		closedir($handle);

		add_install_step(lang('Copied the files of the site'), lang(array('string' => '{var:1} files', 'vars' => count(array_diff((array) @scandir(FILE_DIRECTORY_PATH), array('.', '..'))))));

		// Deal with layouts now.
		

		// delete all files from layouts directory
		$handle = opendir(LAYOUT_DIRECTORY_PATH . '/');

		while (false !== ($file = readdir($handle))) {

			if (($file != '.') && ($file != '..')) {

				unlink(LAYOUT_DIRECTORY_PATH . '/' . $file);

			}

		}

		closedir($handle);

		// prepare path to template layouts
		$template_layouts_path = dirname(__FILE__) . '/../data/backups/' . $install_directory_path . '/layouts/'; 

		$handle = opendir($template_layouts_path);

		// copy template layouts to layouts directory
		while (false !== ($file = readdir($handle))) {

			if (($file != '.') && ($file != '..')) {

				copy($template_layouts_path . $file, LAYOUT_DIRECTORY_PATH . '/' . $file);

			}

		}

		closedir($handle);

		add_install_step(lang('Copied the design templates'), lang(array('string' => '{var:1} files', 'vars' => count(array_diff((array) @scandir(LAYOUT_DIRECTORY_PATH), array('.', '..'))))));

		// create config.php file
		

		// if a logo URL was supplied, then use it
		if ($liveform->get_field_value('logo_url') != '') {

			$logo_url = "\r\n" . 'define(\'LOGO_URL\', \'' . $liveform->get_field_value('logo_url') . '\');';

			// else a logo URL was not supplied, so we won't add the logo_url line
			
		}
		else {

			$logo_url = '';

		}

		// if software language was supplied, then use it
		$default_software_language = '';
		if ($liveform->get_field_value('default_software_language') != '') {
			$default_software_language = "\r\n" . 'define(\'DEFAULT_SOFTWARE_LANGUAGE\', \'' . $liveform->get_field_value('default_software_language') . '\');';
		}else{
		}

		
		// if a software update check value was supplied, then use it
		if ($liveform->get_field_value('software_update_check') != '') {

			// if true value was passed, then prepare value for config file
			if ($liveform->get_field_value('software_update_check') == 'true') {

				$software_update_check_value = 'TRUE';

				// else a true value was not passed, so prepare different value for config file
				
			}
			else {

				$software_update_check_value = 'FALSE';

			}

			$software_update_check = "\r\n" . 'define(\'SOFTWARE_UPDATE_CHECK\', ' . $software_update_check_value . ');';

			// else a software update check value was not supplied, so we won't add a line for it
			
		}
		else {

			$software_update_check = '';

		}

		$system_smtp ='';
		if ($liveform->get_field_value('system_smtp_hostname') != '') {
			$system_smtp .= "\r\n" . 'define(\'SYSTEM_SMTP_HOSTNAME\', \'' . $liveform->get_field_value('system_smtp_hostname') . '\');';
		}
		if ($liveform->get_field_value('system_smtp_port') != '') {
			$system_smtp .= "\r\n" . 'define(\'SYSTEM_SMTP_PORT\', \'' . $liveform->get_field_value('system_smtp_port') . '\');';
		}
		if ($liveform->get_field_value('system_smtp_username') != '') {
			$system_smtp .= "\r\n" . 'define(\'SYSTEM_SMTP_USERNAME\', \'' . $liveform->get_field_value('system_smtp_username') . '\');';
		}
		if ($liveform->get_field_value('system_smtp_password') != '') {
			$system_smtp .= "\r\n" . 'define(\'SYSTEM_SMTP_PASSWORD\', \'' . $liveform->get_field_value('system_smtp_password') . '\');';
		}
		


		$email_campaign_job ='';
		if ($liveform->get_field_value('email_campaign_job') == 'true') {
			$email_campaign_job .= "\r\n" . 'define(\'EMAIL_CAMPAIGN_JOB\', \'' . $liveform->get_field_value('email_campaign_job') . '\');';
		}
		if ($liveform->get_field_value('campaign_smtp_hostname') != '') {
			$email_campaign_job .= "\r\n" . 'define(\'CAMPAIGN_SMTP_HOSTNAME\', \'' . $liveform->get_field_value('campaign_smtp_hostname') . '\');';
		}
		if ($liveform->get_field_value('campaign_smtp_number_of_emails') != '') {
			$email_campaign_job .= "\r\n" . 'define(\'EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS\', \'' . $liveform->get_field_value('campaign_smtp_number_of_emails') . '\');';
		}
		if ($liveform->get_field_value('campaign_smtp_port') != '') {
			$email_campaign_job .= "\r\n" . 'define(\'CAMPAIGN_SMTP_PORT\', \'' . $liveform->get_field_value('campaign_smtp_port') . '\');';
		}
		if ($liveform->get_field_value('campaign_smtp_username') != '') {
			$email_campaign_job .= "\r\n" . 'define(\'CAMPAIGN_SMTP_USERNAME\', \'' . $liveform->get_field_value('campaign_smtp_username') . '\');';
		}
		if ($liveform->get_field_value('campaign_smtp_password') != '') {
			$email_campaign_job .= "\r\n" . 'define(\'CAMPAIGN_SMTP_PASSWORD\', \'' . $liveform->get_field_value('campaign_smtp_password') . '\');';
		}

			
		// prepare data for config.php file
		$config_data =
		'<?php
// Prevent direct access
if (php_sapi_name() !== "cli" && basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"])) {
    http_response_code(403);
    exit("Forbidden");
}
define(\'DB_HOST\', \'' . $liveform->get_field_value('db_host') . '\');
define(\'DB_USERNAME\', \'' . $liveform->get_field_value('db_username') . '\');
define(\'DB_PASSWORD\', \'' . $liveform->get_field_value('db_password') . '\');
define(\'DB_DATABASE\', \'' . $liveform->get_field_value('db_database') . '\');
define(\'ENCRYPTION_KEY\', \'' . generate_encryption_key() . '\'); // DO NOT MODIFY OR SHARE
// Automated upgrade from a cron job over the web: install/index.php?automated_upgrade=true&secret=<this value>
// Optional. Undefined or shorter than 16 characters means the key path is closed; php from the command line never needs it.
// define(\'AUTOMATED_UPGRADE_SECRET\', \'change-this-to-a-long-random-string\');
define(\'DYNAMIC_REGIONS\', true);
define(\'PHP_REGIONS\', true);' .  $default_software_language . $system_smtp . $logo_url . $software_update_check . $email_campaign_job . '
?>';



		// create data directory if it does not exist (failsafe for FTP deployments)
		if (!is_dir(dirname(CONFIG_FILE_PATH))) {
			mkdir(dirname(CONFIG_FILE_PATH), 0755, true);
		}
		$handle = fopen(CONFIG_FILE_PATH, 'w');
		if ($handle === false) {
			exit(lang('Unable to write to config file. Please check that the data/ directory exists and is writable by the web server.'));
		}
		fwrite($handle, $config_data);
		fclose($handle);

		add_install_step(lang('Wrote the configuration'), 'data/config.php');

		// Generate the redirection file if it does not exist — the software does
		// not work without it.
		//
		// The content is NOT written here. It comes from includes/server_config.php,
		// which the running site also reads: a rule added for a new install has to
		// reach the thousands of sites that already have this file, and the only
		// way that happens is if both ends read one list. See the header of that
		// file. It picks the server and the path itself.
		$server_config_target = pg_server_config_target();

		if (!file_exists($server_config_target['file'])) {
		    @file_put_contents(
		        $server_config_target['file'],
		        pg_server_config_default($server_config_target['server']));
		    add_install_step(lang('Wrote the web server rules'), $server_config_target['name']);
		} else {
		    // A file that is already there is the operator's. Only the blocks it
		    // is missing are added, and nothing that is present is rewritten.
		    $server_config_repair = pg_server_config_repair(false);
		    if (($server_config_repair['status'] == 'success') && $server_config_repair['applied']) {
		        add_install_step(lang('Updated the web server rules'), $server_config_target['name']);
		    }
		}

		// Uncomment RewriteBase when installed in a sub-directory (Apache).
		//
		// This one stays here rather than moving into the block list: it is not a
		// rule that is present or absent, it is a line the default file ships
		// commented out and that only an alias install (http://host/~example/)
		// needs uncommenting. Only the exact commented default is replaced, so an
		// operator who has already set their own RewriteBase keeps it.
		//
		// The IIS half of this used to live here too, rewriting the router action
		// for a sub-directory install with 'pinegrap' typed into the search string
		// — which did nothing at all on a site whose software folder is called
		// anything else. The default now carries PATH and SOFTWARE_DIRECTORY from
		// the start, and a stale target is reported by the System Status check.
		if ((PATH != '/') && ($server_config_target['server'] == 'apache')) {

		    $htaccess_content = @file_get_contents($server_config_target['file']);
		    if ($htaccess_content !== false) {
		        $handle = @fopen($server_config_target['file'], 'w');
		        if ($handle == true) {
		            $htaccess_content = str_replace('#RewriteBase /~example/', 'RewriteBase ' . PATH, $htaccess_content);
		            @fwrite($handle, $htaccess_content);
		            @fclose($handle);
		        }
		    }
		}




		// If the installed version is not the most recent version, then update also.
		// This might happen during development when you install a new site, but it installs
		// a starter template for an old version, because the starter template has not been
		// updated yet.
		

		$installed_version = db("SELECT version FROM config");

		if ($installed_version != $software_version) {

			$installed_version_key = get_version_key($installed_version, $versions);

			// A backup taken from a newer package carries a version this package does not know.
			// Running the whole history against it would be the worst possible answer, so the
			// site is left as restored and the person is told what to do.
			if ($installed_version_key === false) {

				add_install_step(
					lang(array('string' => 'The version of the backup ({var:1}) is not part of this package', 'vars' => $installed_version)),
					lang('The site was restored but not upgraded. Install a package that knows this version, then run the upgrade from this screen.'),
					'warning');

			} else {

				// the versions still to come are reported one by one, so the bar keeps moving
				foreach ($versions as $version_key => $version) {

					if (($version_key > $installed_version_key) && ((install_migration_file($version['number']) != '') || (function_exists(install_upgrade_function($version['number']))))) {

						$install_expected_steps++;

					}

				}

				$upgrade_result = install_run_upgrades($versions, $installed_version_key, array('stream' => true));

				if ($upgrade_result['ok'] == false) {

					output_install_upgrade_failure($upgrade_result, $installed_version, false, '');

				}

			}

		}

		// The administrator row above was written as MD5 because the starter
		// template predates user_password_algo. Now that the upgrades have added
		// the column, store the modern hash right away instead of leaving the
		// MD5 in place until the first sign-in upgrades it.
		if (function_exists('pg_password_store') && install_column_exists('user', 'user_password_algo')) {
			pg_password_store($user_id, $liveform->get_field_value('admin_password'));
		}

		add_install_step(lang('The installation is complete'), $software_version);

		log_activity(lang('The software was installed'), $liveform->get_field_value('admin_username'));

		// If no mail server was entered under the advanced options, then we do not try to send the
		// confirmation, because there is nothing to send it with and the message would only be
		// confusing.  The administrator can set this up later under the email settings.
		$install_email_sent = false;

		// if an e-mail should be sent to the administrator, then send e-mail
		if (($liveform->get_field_value('send_email') != 'false') && ($liveform->get_field_value('system_smtp_hostname') != '')) {

			// prepare confirmation e-mail to administrator
			$to = $liveform->get_field_value('admin_email_address');

			$subject = lang(array('string'=>'Pinegrap: Installation Complete for {var:1}','vars'=>$_SERVER['HTTP_HOST']));
			


			// prepare hidden password
			$hidden_password = '';

			for ($i = 1;$i <= mb_strlen($liveform->get_field_value('admin_password'));$i++) {

				$hidden_password .= '*';

			}

			$body =

			lang('Congratulations, your Pinegrap installation is complete!  You may find your login information below') . ':
' . lang('Email') . ': ' . $liveform->get_field_value('admin_email_address') . '
' . lang('Password') . ': ' . $hidden_password . '
' . lang('Login') . ':
http://' . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/';

			$headers = 'From: noreply@kodpen.com' . "\r\n";

			// send e-mail to administrator
			@mb_send_mail($to, $subject, $body, $headers);

			$install_email_sent = true;

		}

		// turn the steps that we recorded during the installation into a small console output, so
		// the person who installed can see what actually happened and how long it took
		$output_install_log = '';

		foreach ($install_log as $install_step) {

			$output_install_step_detail = '';

			if ($install_step['detail'] != '') {

				$output_install_step_detail = ' <span class="pg-detail">' . h($install_step['detail']) . '</span>';

			}

			$output_install_log .= '<div><span class="pg-time">[' . str_pad(number_format($install_step['seconds'], 1), 5, ' ', STR_PAD_LEFT) . ' s]</span> <span class="pg-ok">&#10003;</span> ' . h($install_step['label']) . $output_install_step_detail . '</div>';

		}

		if ($output_install_log == '') {

			$output_install_log = '<div>' . h(lang('No steps were recorded.')) . '</div>';

		}

		// When the installation was streamed we answer with a short result instead of a whole page,
		// because the wizard is still on screen and only needs the outcome.
		if ($install_streaming == true) {

			$install_total_seconds = 0;

			if (count($install_log) > 0) {

				$install_total_seconds = $install_log[count($install_log) - 1]['seconds'];

			}

			$install_result_details =
				lang(array('string' => '{var:1} seconds', 'vars' => number_format($install_total_seconds, 1))) . ' · ' .
				lang(array('string' => '{var:1} tables', 'vars' => count(db_values("SHOW TABLES")))) . ' · ' .
				lang(array('string' => '{var:1} files', 'vars' => count(array_diff((array) @scandir(FILE_DIRECTORY_PATH), array('.', '..')))));

			$install_result_email = lang('No mail server was entered, so no confirmation e-mail was sent. Features that rely on e-mail (password resets, form notifications and user invitations) work once you set up e-mail under the settings.');

			if ($install_email_sent == true) {

				$install_result_email = lang('A confirmation e-mail has been sent to your e-mail address.  If you do not receive the confirmation e-mail, then e-mail is probably not configured correctly for your website.  There are features that rely on e-mail (i.e. e-mailing pages, creating users, and etc.), so it is important that you configure e-mail to work.');

			}

			finish_install_stream();

			print '
			<div id="pg_stream_result">
				<div class="alert alert-success d-flex gap-2 align-items-start mt-3 mb-0">
					<i class="bi bi-check-circle-fill"></i>
					<div class="flex-grow-1">
						<b>' . lang('The installation is complete') . '</b> <span class="text-body-secondary">' . h($install_result_details) . '</span>
						<div class="d-flex gap-2 flex-wrap mt-2">
							<a class="btn btn-primary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/"><i class="bi bi-arrow-right me-1"></i>' . lang('Control Panel') . '</a>
							<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . '" target="_blank"><i class="bi bi-eye me-1"></i>' . lang('View Site') . '</a>
						</div>
						<div class="small text-body-secondary mt-2">' . $install_result_email . '</div>
						<div class="small text-body-secondary mt-1">' . lang('Your database login information has been stored in the config.php file in the software directory. If you need to change the database login information in the future, you will need to update the config.php file.') . '</div>
					</div>
				</div>
			</div>
			<script>pg_install_stream_complete();</script>' . get_footer();

			$liveform->remove_form('install');

			$_SESSION['software']['install']['reinstall'] = false;

			exit();

		}

		// The page is already open when the installation was streamed, so the header may not be sent
		// a second time.  We only close the live output and print the confirmation into the page
		// that is already there.
		$output_install_page_start = '';

		$output_install_log_card = '';

		if ($install_streaming == true) {

			finish_install_stream();

		}
		else {

			$output_install_page_start =
			get_header() . '
		<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body d-print-none">
			  <ul class="navbar-nav me-auto">
				<li class="nav-item"><button onclick="javascript:history.go(-1)" type="button" class="nav-link" title="' . lang('Cancel') . '"  data-loading-content=" "   aria-label="Close"><span class=" material-icons">arrow_back</span></button></li>
			  </ul>
		</nav>';

			$output_install_log_card = '
					<div class="card mb-4">
						<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
							<i class="bi bi-terminal me-2"></i>' . lang('Installation steps') . '
						</div>
						<div class="card-body">
							<div class="pg-install-console">' . $output_install_log . '</div>
						</div>
					</div>';

		}

		$output_install_email_notice = '
					<div class="alert alert-secondary small d-flex gap-2">
						<i class="bi bi-envelope-exclamation"></i>
						<div>' . lang('No mail server was entered, so no confirmation e-mail was sent. Features that rely on e-mail (password resets, form notifications and user invitations) work once you set up e-mail under the settings.') . '</div>
					</div>';

		if ($install_email_sent == true) {

			$output_install_email_notice = '
					<div class="alert alert-secondary small d-flex gap-2">
						<i class="bi bi-envelope-check"></i>
						<div>' . lang('A confirmation e-mail has been sent to your e-mail address.  If you do not receive the confirmation e-mail, then e-mail is probably not configured correctly for your website.  There are features that rely on e-mail (i.e. e-mailing pages, creating users, and etc.), so it is important that you configure e-mail to work.') . '</div>
					</div>';

		}

		// output confirmation
		print
		$output_install_page_start . '
		<style class="d-none">
			.pg-install-console {
				background: #0f1115;
				color: #d7dee8;
				border-radius: 1rem;
				padding: .9rem 1rem;
				font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
				font-size: .8rem;
				line-height: 1.8;
				max-height: 320px;
				overflow: auto;
			}
			.pg-install-console .pg-time { color: #6b7688; }
			.pg-install-console .pg-ok { color: #4ade80; }
			.pg-install-console .pg-detail { opacity: .6; }
			.pg-install-logo {
				padding: 8px;
				border-radius: 26px;
				border: 1px solid var(--bs-border-color);
				background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-warning-rgb), .16));
			}
		</style>
		<main id="content" class="container">
		    <div class="row">
				<div class="col-12 col-lg-8 offset-lg-2">
					<div class="text-center my-4">
						<img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/logo.png" width="96" height="96" alt="Pinegrap" class="pg-install-logo mb-3">
						<h1 class="h3 text-success"><span class="material-icons align-middle" style="font-size:1.6em;line-height:1;">check_circle</span> ' . lang('Congratulations, the installation is complete!') . '</h1>
						<p class="text-body-secondary mb-0">' . lang(array('string' => 'Pinegrap {var:1} is installed and your administrator account is ready.', 'vars' => $software_version)) . '</p>
					</div>

					<div class="d-flex gap-2 justify-content-center flex-wrap mb-4">
						<a class="btn btn-primary" href="../">' . lang('Control Panel') . '<span class="ms-1 material-icons">arrow_forward</span></a>
						<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . '" target="_blank"><span class="me-1 material-icons">visibility</span>' . lang('View Site') . '</a>
					</div>

					' . $output_install_log_card . '

					<div class="alert alert-primary small d-flex gap-2">
						<i class="bi bi-info-circle"></i>
						<div>' . lang('Your database login information has been stored in the config.php file in the software directory. If you need to change the database login information in the future, you will need to update the config.php file.') . '</div>
					</div>
					' . $output_install_email_notice . '
				</div>
		    </div>
		</main>' . get_footer();

		$liveform->remove_form('install');

		$_SESSION['software']['install']['reinstall'] = false;

	}

}

// Opens the page that shows the installation while it runs.  From here on every step that the
// installer records is sent to the browser as soon as it happens, so the person who is installing
// can watch what the server is doing instead of looking at a blank tab.
function start_install_stream() {

	global $install_streaming;

	start_install_log();

	start_install_progress_file();

	$install_streaming = true;

	// turn off everything that would hold the output back
	@ini_set('zlib.output_compression', 'Off');

	@ini_set('output_buffering', 'Off');

	@ini_set('implicit_flush', '1');

	if (!headers_sent()) {

		header('X-Accel-Buffering: no');

		header('Content-Type: text/html; charset=utf-8');

	}

	while (ob_get_level() > 0) {

		@ob_end_flush();

	}

	@ob_implicit_flush(true);

	print

	get_header() . '
	<style>
		.pg-install-console {
			background: #0f1115;
			color: #d7dee8;
			border-radius: 1rem;
			padding: .9rem 1rem;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: .8rem;
			line-height: 1.8;
			max-height: 360px;
			overflow: auto;
		}
		.pg-install-console .pg-time { color: #6b7688; }
		.pg-install-console .pg-ok { color: #4ade80; }
		.pg-install-console .pg-warning { color: #fbbf24; }
		.pg-install-console .pg-error { color: #f87171; }
		.pg-install-console .pg-detail { opacity: .6; }
		.pg-install-logo {
			padding: 8px;
			border-radius: 26px;
			border: 1px solid var(--bs-border-color);
			background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-warning-rgb), .16));
		}
		#pg_stream_bar { background: linear-gradient(90deg, var(--pg-logo-color-1), var(--pg-logo-color-2)); }
	</style>
	<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body d-print-none">
	  	<ul class="navbar-nav me-auto"></ul>
	</nav>
	<div class="container-xl" style="max-width:960px;" id="pg_stream_area">
		<div class="d-flex flex-wrap align-items-center gap-3 pt-4 pb-2">
			<img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/logo.png" width="72" height="72" alt="Pinegrap" class="pg-install-logo">
			<div class="flex-grow-1" style="min-width:16rem;">
				<h1 class="h4 mb-1" id="pg_stream_title">' . lang('The installation is running') . '</h1>
				<p class="text-body-secondary mb-0" id="pg_stream_text">' . lang('Please do not close this window. Depending on the server this takes between ten seconds and a minute, and the steps are listed when it is done.') . '</p>
			</div>
		</div>
		<div class="d-flex align-items-center gap-3 mb-3">
			<span class="small text-body-secondary font-monospace" id="pg_stream_percent">%0</span>
			<div class="progress flex-grow-1" style="height:7px;"><div class="progress-bar" id="pg_stream_bar" style="width:0;"></div></div>
			<span class="small text-body-secondary text-truncate" style="max-width:16rem;" id="pg_stream_now">' . lang('Please Wait') . '</span>
		</div>
		<div class="pg-install-console mb-4" id="pg_stream_console"></div>
	</div>
	<script type="text/javascript">
		// When the installation runs inside the wizard, every step is handed to that page so the
		// output appears where the person started it.  On its own the page shows the steps itself.
		function pg_install_host() {
			try {
				if ((window.parent) && (window.parent !== window) && (window.parent.pg_install_stream_step)) {
					return window.parent;
				}
			} catch (error) { }
			return null;
		}
		function pg_install_stream_complete() {
			var host = pg_install_host();
			var result = document.getElementById("pg_stream_result");
			if ((host) && (result)) {
				host.pg_install_stream_complete(result.innerHTML);
				result.style.display = "none";
			} else if (result) {
				var area = document.getElementById("pg_stream_area");
				if (area) { area.appendChild(result); }
			}
		}
		function pg_install_retry() {
			var host = pg_install_host();
			if ((host) && (host.pg_install_retry)) { host.pg_install_retry(); return; }
			window.location.href = "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/install/";
		}
		function pg_install_stream_step(index, seconds, label, detail, state, percent) {
			var host = pg_install_host();
			if (host) { host.pg_install_stream_step(index, seconds, label, detail, state, percent); return; }
			var console_element = document.getElementById("pg_stream_console");
			if (!console_element) { return; }
			var mark = (state === "warning") ? "<span class=\"pg-warning\">&#9650;</span>" : ((state === "error") ? "<span class=\"pg-error\">&#10007;</span>" : "<span class=\"pg-ok\">&#10003;</span>");
			var line = document.createElement("div");
			line.innerHTML = "<span class=\"pg-time\">[" + seconds + " s]</span> " + mark + " " +
				pg_install_stream_escape(label) +
				(detail ? " <span class=\"pg-detail\">" + pg_install_stream_escape(detail) + "</span>" : "");
			console_element.appendChild(line);
			console_element.scrollTop = console_element.scrollHeight;
			document.getElementById("pg_stream_bar").style.width = percent + "%";
			document.getElementById("pg_stream_percent").textContent = "%" + percent;
			document.getElementById("pg_stream_now").textContent = label;
		}
		function pg_install_stream_escape(value) {
			var element = document.createElement("span");
			element.textContent = value;
			return element.innerHTML;
		}
		function pg_install_stream_done(title) {
			var host = pg_install_host();
			if (host) { host.pg_install_stream_done(title); return; }
			document.getElementById("pg_stream_bar").style.width = "100%";
			document.getElementById("pg_stream_percent").textContent = "%100";
			document.getElementById("pg_stream_now").textContent = "";
			document.getElementById("pg_stream_title").textContent = title;
			document.getElementById("pg_stream_text").classList.add("d-none");
		}
	</script>
	<!--' . str_repeat(' ', 4096) . '-->
	';

	flush_install_stream();

}

// What a failed or locked upgrade run looks like, on every path that can run one: the
// streamed screen, the plain confirmation page, a cron.  Never returns.
function output_install_upgrade_failure($upgrade_result, $database_version, $automated_upgrade, $automated_upgrade_via) {

	global $install_streaming, $liveform;

	$locked = !empty($upgrade_result['locked']);

	$failed_version = (string) $upgrade_result['failed'];

	$message = (string) $upgrade_result['error'];

	$statement = (string) $upgrade_result['statement'];

	// a cron reads plain text; the exit code says the same thing to a shell
	if ($automated_upgrade == true) {

		if ($automated_upgrade_via == 'session') {

			// the administrator came from the control panel and should see the screen
			header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/');

			exit();

		}

		header('Content-Type: text/plain; charset=utf-8');

		if ($locked) {

			print 'locked: ' . $message . "\n";

		} else {

			print 'error at ' . $failed_version . ': ' . $message . (($statement != '') ? ' :: ' . $statement : '') . "\n";

		}

		exit(1);

	}

	// the streamed answer: the host screen copies pg_stream_result into its own result area
	write_install_progress_file(true);

	if ($locked) {

		$alert = '
			<div class="alert alert-warning d-flex gap-2 align-items-start mt-3 mb-0">
				<i class="bi bi-hourglass-split"></i>
				<div class="flex-grow-1">
					<b>' . lang('Another upgrade is running') . '</b>
					<div class="small">' . h($message) . '</div>
					<div class="d-flex gap-2 flex-wrap mt-2">
						<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/install/"><i class="bi bi-arrow-clockwise me-1"></i>' . lang('Refresh') . '</a>
					</div>
				</div>
			</div>';

	} else {

		$alert = '
			<div class="alert alert-danger d-flex gap-2 align-items-start mt-3 mb-0">
				<i class="bi bi-exclamation-triangle-fill"></i>
				<div class="flex-grow-1">
					<b>' . lang(array('string' => 'The upgrade stopped at version {var:1}', 'vars' => h($failed_version))) . '</b>
					<div class="small mt-1">' . h($message) . '</div>
					' . (($statement != '') ? '<div class="small font-monospace text-body-secondary mt-1">' . h($statement) . '</div>' : '') . '
					<div class="small mt-2">' . lang('The versions before it are recorded, and every step can be run again: start the upgrade once more and it continues from here. If the same statement fails again, the message above says what the database objected to.') . '</div>
					<div class="d-flex gap-2 flex-wrap mt-2">
						<button type="button" class="btn btn-primary" onclick="pg_install_retry();"><i class="bi bi-arrow-repeat me-1"></i>' . lang('Try again') . '</button>
						<a class="btn btn-outline-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/install/"><i class="bi bi-arrow-left me-1"></i>' . lang('Installation') . '</a>
					</div>
				</div>
			</div>';

	}

	if ($install_streaming == true) {

		print '
		<div id="pg_stream_result">' . $alert . '</div>
		<script>pg_install_stream_complete();</script>' . get_footer();

		if (is_object($liveform)) {

			$liveform->remove_form('install');

		}

		exit();

	}

	// no stream: a plain page with the same alert
	print get_header() . '
	<div class="container-xl" style="max-width:960px;">' . $alert . '</div>' . get_footer();

	exit();

}

// Closes the page that was opened above.
function finish_install_stream() {

	write_install_progress_file(true);

	print '<script>pg_install_stream_done(' . json_encode(lang('The installation is complete')) . ');</script>';

	flush_install_stream();

}

// Answers the "test the connection" button on the install screen.  It only reports what the
// installation itself would find, so nothing is written and nothing is remembered.  This function
// prints a small json answer and never returns.
function output_install_database_test() {

	header('Content-Type: application/json; charset=utf-8');

	$response = array('state' => 'error', 'message' => '');

	$posted_token = '';

	if (isset($_POST['token'])) {

		$posted_token = $_POST['token'];

	}

	if (($_SESSION['software']['token'] == '') || ($posted_token != $_SESSION['software']['token'])) {

		$response['message'] = lang('Sorry, we could not accept your request because it appears that your session expired.');

		print json_encode($response);

		exit();

	}

	// A visitor could otherwise use this to look for database servers, so we only allow a handful
	// of tests before the visitor has to come back later.
	if (!isset($_SESSION['software']['install']['test_count'])) {

		$_SESSION['software']['install']['test_count'] = 0;

		$_SESSION['software']['install']['test_time'] = time();

	}

	if (($_SESSION['software']['install']['test_time'] + 600) < time()) {

		$_SESSION['software']['install']['test_count'] = 0;

		$_SESSION['software']['install']['test_time'] = time();

	}

	$_SESSION['software']['install']['test_count'] = $_SESSION['software']['install']['test_count'] + 1;

	if ($_SESSION['software']['install']['test_count'] > 25) {

		$response['message'] = lang('Too many connection tests. Please try again later.');

		print json_encode($response);

		exit();

	}

	$test_host = '';

	if (isset($_POST['db_host'])) {

		$test_host = trim($_POST['db_host']);

	}

	$test_username = '';

	if (isset($_POST['db_username'])) {

		$test_username = trim($_POST['db_username']);

	}

	$test_password = '';

	if (isset($_POST['db_password'])) {

		$test_password = $_POST['db_password'];

	}

	$test_database = '';

	if (isset($_POST['db_database'])) {

		$test_database = trim($_POST['db_database']);

	}

	if (($test_host == '') || ($test_username == '') || ($test_database == '')) {

		$response['message'] = lang('Please enter the hostname, the username and the name of the database first.');

		print json_encode($response);

		exit();

	}

	$connection = @mysqli_connect($test_host, $test_username, $test_password);

	if ($connection == false) {

		$response['message'] = lang('A connection to the MySQL server failed. Please correct the hostname, username, and/or password.  MySQL error') . ': ' . mysqli_connect_error();

		print json_encode($response);

		exit();

	}

	if (@mysqli_select_db($connection, $test_database) == false) {

		$response['message'] = lang('A connection to the MySQL server was successful, however the database name that you entered could not be selected. Please correct the database name. If the database name is correct, then the user might not have correct permissions to access the database. MySQL error') . ': ' . mysqli_error($connection);

		@mysqli_close($connection);

		print json_encode($response);

		exit();

	}

	$server_version = @mysqli_get_server_info($connection);

	// see whether a site is already in there, because that is what the installation would replace
	$site_found = false;

	$table_count = 0;

	$result = @mysqli_query($connection, "SHOW TABLES");

	if ($result != false) {

		while ($row = mysqli_fetch_row($result)) {

			$table_count++;

			if (($row[0] == 'config') || ($row[0] == 'page') || ($row[0] == 'user')) {

				$site_found = true;

			}

		}

	}

	@mysqli_close($connection);

	if ($site_found == true) {

		$response['state'] = 'warning';

		$response['message'] = lang('The connection was successful.') . ' MySQL ' . h($server_version) . ' · ' . lang('A site is already installed in the database that you entered. If you wish to reinstall please check to verify reinstallation.');

	}
	else {

		$response['state'] = 'ok';

		$response['message'] = lang('The connection was successful.') . ' MySQL ' . h($server_version) . ' · ' . (($table_count == 0) ? lang('The database is empty and ready for the installation.') : lang(array('string' => 'The database holds {var:1} tables that do not belong to Pinegrap. They are left alone.', 'vars' => $table_count)));

	}

	print json_encode($response);

	exit();

}

// The token and the administrator behind a request of the upgrade screen.  Both endpoints
// below are only reached after the lock, so on a site that exists the session already
// belongs to an administrator; this is the check that the request that changes the site
// carries the token of that session.  Prints a json answer and exits when it does not.
function check_install_upgrade_request() {

	global $install_site_exists;

	$posted_token = '';

	if (isset($_POST['token'])) {

		$posted_token = $_POST['token'];

	}

	if (($_SESSION['software']['token'] == '') || ($posted_token != $_SESSION['software']['token'])) {

		set_response_code(403);

		print json_encode(array('ok' => false, 'session' => true, 'error' => lang('Sorry, we could not accept your request because it appears that your session expired.')));

		exit();

	}

	if (($install_site_exists != true) || ((check_if_administrator_is_logged_in() == false) && (check_install_unlocked() == false))) {

		set_response_code(403);

		print json_encode(array('ok' => false, 'session' => true, 'error' => lang('Please authenticate as an administrator of this site before you install or upgrade.')));

		exit();

	}

	// PHP holds the session file for the whole request, and a schema step can hold it for
	// minutes.  Nothing below writes to the session, so it is released here: the control
	// panel stays usable in another tab while a version runs, and a second request from
	// the same browser meets the upgrade lock instead of waiting in the dark.
	session_write_close();

}

// Applies the next version and reports it.  The screen calls this again and again until the
// answer says done; the server writes every version number the moment its step returns, so
// a request that is cut short costs one version, and that version is run again.  Prints a
// json answer and never returns.
function output_install_upgrade_step($versions) {

	global $software_version_key;

	header('Content-Type: application/json; charset=utf-8');

	header('Cache-Control: no-store');

	check_install_upgrade_request();

	$database_version = get_database_version();

	$database_version_key = get_version_key($database_version, $versions);

	if ($database_version_key === false) {

		print json_encode(array('ok' => false, 'error' => lang(array(
			'string' => 'The version in the database ({var:1}) is not part of this package, so the upgrade is not offered. Correct the version in the config table, or install the site again.',
			'vars' => $database_version
		))));

		exit();

	}

	$result = install_run_upgrades($versions, $database_version_key, array('one' => true));

	// where the database is now, and how much is left
	$version_now = $database_version;

	if (count($result['applied']) > 0) {

		$version_now = $result['applied'][count($result['applied']) - 1];

	}

	$version_now_key = get_version_key($version_now, $versions);

	$remaining = ($version_now_key === false) ? 0 : ($software_version_key - $version_now_key);

	if ($result['done'] == true) {

		log_activity(lang(array('string' => 'The software was upgraded from version {var:1} to {var:2}.', 'vars' => array($database_version, $version_now))), (isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : ''));

	}

	print json_encode(array(
		'ok' => $result['ok'],
		'locked' => $result['locked'],
		'error' => $result['error'],
		'statement' => $result['statement'],
		'failed' => $result['failed'],
		'applied' => $result['applied'],
		'steps' => $result['steps'],
		'done' => $result['done'],
		'next' => $result['next'],
		'last' => $result['last'],
		'notes' => install_notes(),
		'from' => $database_version,
		'version' => $version_now,
		'remaining' => $remaining
	));

	exit();

}

// Writes a copy of the database into data/backups before the upgrade starts.  Prints a json
// answer and never returns.
function output_install_database_backup($versions) {

	header('Content-Type: application/json; charset=utf-8');

	header('Cache-Control: no-store');

	check_install_upgrade_request();

	$database_version = get_database_version();

	$result = install_backup_database($database_version);

	if ($result['ok'] == true) {

		log_activity(lang('A backup of the database was written before the upgrade') . ': ' . $result['folder'], (isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : ''));

	}

	print json_encode(array(
		'ok' => $result['ok'],
		'error' => $result['error'],
		'folder' => $result['folder'],
		'size' => install_size_label($result['bytes']),
		'seconds' => $result['seconds']
	));

	exit();

}

// Extracts an uploaded backup archive into the backups folder.  This is only offered when a site
// exists in the database, which means that the install screen has been unlocked by an
// administrator, so we never accept an upload from an anonymous visitor.
function process_install_backup_upload($liveform, $install_site_exists) {

	if ($install_site_exists != true) {

		$liveform->mark_error('', lang('A backup can only be uploaded here after an administrator has unlocked this screen.'));

		return;

	}

	$posted_token = '';

	if (isset($_POST['token'])) {

		$posted_token = $_POST['token'];

	}

	if (($_SESSION['software']['token'] == '') || ($posted_token != $_SESSION['software']['token'])) {

		$liveform->mark_error('', lang('Sorry, we could not accept your request because it appears that your session expired.'));

		return;

	}

	if (!class_exists('ZipArchive')) {

		$liveform->mark_error('', lang('Zip support is not available on this server, so the archive cannot be extracted.'));

		return;

	}

	if ((!isset($_FILES['backup_zip'])) || ($_FILES['backup_zip']['name'] == '')) {

		$liveform->mark_error('', lang('Please select a backup archive to upload.'));

		return;

	}

	$upload = $_FILES['backup_zip'];

	// the server refused the upload before it reached us
	if ($upload['error'] != UPLOAD_ERR_OK) {

		if (($upload['error'] == UPLOAD_ERR_INI_SIZE) || ($upload['error'] == UPLOAD_ERR_FORM_SIZE)) {

			$liveform->mark_error('', lang(array(
				'string' => 'The archive is larger than the upload limit of this server, which is {var:1}. Copy the backup folder into the backups folder with FTP instead.',
				'vars' => ini_get('upload_max_filesize')
			)));

		}
		else {

			$liveform->mark_error('', lang('The archive could not be uploaded. Please try again.'));

		}

		return;

	}

	if (!is_uploaded_file($upload['tmp_name'])) {

		$liveform->mark_error('', lang('The archive could not be uploaded. Please try again.'));

		return;

	}

	if (mb_strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) != 'zip') {

		$liveform->mark_error('', lang('Only a .zip archive can be uploaded here.'));

		return;

	}

	$zip = new ZipArchive();

	if ($zip->open($upload['tmp_name']) !== true) {

		$liveform->mark_error('', lang('The archive could not be opened. It might be damaged.'));

		return;

	}

	// Work out where the site backup sits inside the archive.  A backup folder is a folder that
	// holds a database dump, so we look for sql.sql and refuse anything else.  This also stops
	// someone from unpacking a random archive into the backups folder.
	$archive_root = false;

	$entry_count = $zip->numFiles;

	$total_size = 0;

	for ($index = 0; $index < $entry_count; $index++) {

		$entry_name = $zip->getNameIndex($index);

		$entry_name = str_replace('\\', '/', $entry_name);

		$statistics = $zip->statIndex($index);

		if ($statistics != false) {

			$total_size = $total_size + $statistics['size'];

		}

		if ($entry_name == 'sql.sql') {

			$archive_root = '';

		}
		elseif (preg_match('/^([^\/]+)\/sql\.sql$/', $entry_name, $matches)) {

			$archive_root = $matches[1] . '/';

		}

	}

	if ($archive_root === false) {

		$zip->close();

		$liveform->mark_error('', lang('This archive does not look like a site backup, because it does not contain a folder with sql.sql inside it. Nothing was extracted.'));

		return;

	}

	// refuse archives that would fill up the server
	if (($entry_count > 20000) || ($total_size > 2147483648)) {

		$zip->close();

		$liveform->mark_error('', lang('The archive is too large to be extracted here. Copy the backup folder into the backups folder with FTP instead.'));

		return;

	}

	// name the new folder after the folder inside the archive, or after the archive itself
	$folder_name = $archive_root;

	if ($folder_name == '') {

		$folder_name = pathinfo($upload['name'], PATHINFO_FILENAME);

	}

	$folder_name = get_clean_backup_folder_name($folder_name);

	if ($folder_name == '') {

		$folder_name = 'backup';

	}

	$backups_path = dirname(__FILE__) . '/../data/backups/';

	$folder_name = get_unique_backup_folder_name($backups_path, $folder_name);

	$destination_path = $backups_path . $folder_name;

	if (!@mkdir($destination_path, 0755, true)) {

		$zip->close();

		$liveform->mark_error('', lang('The backups folder is not writable, so the archive could not be extracted.'));

		return;

	}

	$extracted_files = 0;

	for ($index = 0; $index < $entry_count; $index++) {

		$entry_name = str_replace('\\', '/', $zip->getNameIndex($index));

		// only take what is inside the backup folder of the archive
		if (($archive_root != '') && (mb_strpos($entry_name, $archive_root) !== 0)) {

			continue;

		}

		$relative_path = mb_substr($entry_name, mb_strlen($archive_root));

		if ($relative_path == '') {

			continue;

		}

		// Rebuild the path from clean parts.  Anything that tries to walk up the tree, and any
		// part that is empty or only dots, is dropped, so the archive can only write inside of
		// the folder that we just created.
		$clean_parts = array();

		foreach (explode('/', $relative_path) as $part) {

			$part = trim($part);

			if (($part == '') || ($part == '.') || ($part == '..')) {

				continue;

			}

			$clean_parts[] = $part;

		}

		if (count($clean_parts) == 0) {

			continue;

		}

		$is_directory = (mb_substr($entry_name, -1) == '/');

		$target_path = $destination_path . '/' . implode('/', $clean_parts);

		if ($is_directory == true) {

			if (!is_dir($target_path)) {

				@mkdir($target_path, 0755, true);

			}

			continue;

		}

		$parent_path = dirname($target_path);

		if (!is_dir($parent_path)) {

			@mkdir($parent_path, 0755, true);

		}

		$contents = $zip->getFromIndex($index);

		if ($contents === false) {

			continue;

		}

		if (@file_put_contents($target_path, $contents) !== false) {

			$extracted_files++;

		}

	}

	$zip->close();

	// The backups folder is already closed to the web, however we also close the new folder, so a
	// server that ignores the parent rule still cannot serve anything from it.
	@file_put_contents($destination_path . '/.htaccess', 'deny from all');

	if ($extracted_files == 0) {

		$liveform->mark_error('', lang('The archive was empty, so nothing was extracted.'));

		return;

	}

	// select the folder that we just extracted
	$liveform->assign_field_value('install_from_folder', $folder_name);

	log_activity(lang(array('string' => 'uploaded a backup archive to the installation screen and extracted it to {var:1}', 'vars' => $folder_name)), $_SESSION['sessionusername']);

	$liveform->add_notice(lang(array(
		'string' => 'The archive was extracted to {var:1} and selected. {var:2} files were extracted.',
		'vars' => array($folder_name, $extracted_files)
	)));

}

// Removes everything from a folder name that we do not want on disk.
function get_clean_backup_folder_name($folder_name) {

	$folder_name = trim(str_replace('\\', '/', $folder_name), '/ ');

	$folder_name = preg_replace('/[^A-Za-z0-9._\- ]/', '', $folder_name);

	$folder_name = trim(preg_replace('/\s+/', ' ', $folder_name));

	$folder_name = trim($folder_name, '.');

	return mb_substr($folder_name, 0, 100);

}

// Adds a number to the folder name when a folder with that name already exists.
function get_unique_backup_folder_name($backups_path, $folder_name) {

	if (!file_exists($backups_path . $folder_name)) {

		return $folder_name;

	}

	$counter = 2;

	while (file_exists($backups_path . $folder_name . '-' . $counter)) {

		$counter++;

		// never loop forever
		if ($counter > 999) {

			break;

		}

	}

	return $folder_name . '-' . $counter;

}

// Outputs the authentication screen that is shown when a site is already installed in the
// database and this session has not authenticated yet.  Nothing about the site is sent to the
// browser here, so the backups on the server stay private.  This function never returns.
function output_install_lock_screen() {

	$error_message = '';

	$wait_seconds = get_install_attempt_wait();

	// if the unlock form was submitted and this visitor is not locked out, then check the login
	if ((isset($_POST['install_unlock'])) && ($wait_seconds == 0)) {

		$posted_token = '';

		if (isset($_POST['token'])) {

			$posted_token = $_POST['token'];

		}

		if (($_SESSION['software']['token'] == '') || ($posted_token != $_SESSION['software']['token'])) {

			$error_message = lang('Sorry, we could not accept your request because it appears that your session expired.');

		}
		else {

			$unlock_username = '';

			if (isset($_POST['install_unlock_username'])) {

				$unlock_username = trim($_POST['install_unlock_username']);

			}

			$unlock_password = '';

			if (isset($_POST['install_unlock_password'])) {

				$unlock_password = $_POST['install_unlock_password'];

			}

			if (($unlock_username == '') || ($unlock_password == '')) {

				$error_message = lang('Please enter the email address and password for an administrator of this site.');

			}
			else {

				// Find an administrator by name, then verify the raw password against
				// the stored hash (legacy MD5, wrapped or modern) in PHP.
				// The algo column is asked for only when it exists: this screen is
				// the one that runs BEFORE the upgrade adds it (pg_user_has_password_algo).
				$unlock_admin = db_item(
					"SELECT " . pg_password_select_columns() . "
						FROM user
						WHERE
							(user_role = 0)
							AND
							(
								(user_username = '" . escape($unlock_username) . "')
								OR (user_email = '" . escape($unlock_username) . "')
							)
						LIMIT 1");

				// if an administrator was found and the password checks out, unlock
				if (is_array($unlock_admin)
					&& isset($unlock_admin['user_id'])
					&& pg_password_verify($unlock_admin['user_id'], $unlock_password, $unlock_admin['user_password'], pg_password_row_algo($unlock_admin))) {

					clear_install_attempts();

					set_install_unlocked();

					log_activity(lang('unlocked the installation screen'), $unlock_username);

					header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/');

					exit();

				}

				// else the login was wrong, so count the attempt and slow the visitor down
				add_install_attempt();

				log_activity(lang('failed to unlock the installation screen'), $unlock_username);

				$wait_seconds = get_install_attempt_wait();

				$error_message = lang('The email address or password that you entered was incorrect.');

			}

		}

	}

	$output_error = '';

	if ($error_message != '') {

		$output_error = '<div class="alert alert-danger d-flex gap-2 py-2 px-3"><i class="bi bi-exclamation-triangle-fill"></i><div>' . h($error_message) . '</div></div>';

	}

	$output_wait = '';

	$output_disabled = '';

	if ($wait_seconds > 0) {

		$output_wait = '<div class="alert alert-warning d-flex gap-2 py-2 px-3"><i class="bi bi-hourglass-split"></i><div>' . lang(array(
			'string' => 'Too many failed attempts. Please try again in {var:1} minutes.',
			'vars' => (int) ceil($wait_seconds / 60)
		)) . '</div></div>';

		$output_disabled = ' disabled';

	}

	print

	get_header() . '
	<style>
		.install-lock-logo {
			padding: 10px;
			border-radius: 32px;
			border: 1px solid var(--bs-border-color);
			background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-warning-rgb), .16));
		}
		.install-lock-ghost {
			height: 54px;
			border-radius: 1.25rem;
			border: 2px solid var(--bs-border-color);
			background: var(--bs-secondary-bg);
		}
		.install-lock-ghosts {
			filter: blur(5px);
			opacity: .5;
			user-select: none;
		}
	</style>
	<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body d-print-none">
		<ul class="navbar-nav ms-auto">
			<li class="nav-item dropdown no-popover" title="' . lang('Software Theme') . '">
				<button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
				<ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
					<li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
				</ul>
			</li>
		</ul>
	</nav>
	<main id="content" class="container py-4 py-md-5">
		<div class="row justify-content-center">
			<div class="col-12" style="max-width:540px;">
				<div class="text-center mb-4">
					<img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/logo.png" width="112" height="112" alt="Pinegrap" class="install-lock-logo mb-3">
					<h1 class="h4 fw-bold mb-2">' . lang('A Pinegrap site is already installed on this server') . '</h1>
					<p class="text-body-secondary mb-0">' . lang('Verify your identity with an administrator account of the existing site to see the installation and restore options.') . '</p>
				</div>
				<div class="card">
					<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
						<i class="bi bi-lock-fill me-2"></i>' . lang('Authentication') . '
					</div>
					<div class="card-body">
						' . $output_error . $output_wait . '
						<form method="post" autocomplete="on">
							' . get_token_field() . '
							<div class="mb-3">
								<label class="form-label" for="install_unlock_username">' . lang('Email') . '</label>
								<input type="text" class="form-control" id="install_unlock_username" name="install_unlock_username" autocomplete="username"' . $output_disabled . '>
							</div>
							<div class="mb-3">
								<label class="form-label" for="install_unlock_password">' . lang('Password') . '</label>
								<input type="password" class="form-control" id="install_unlock_password" name="install_unlock_password" autocomplete="current-password"' . $output_disabled . '>
							</div>
							<button type="submit" class="btn btn-primary w-100" name="install_unlock" value="1"' . $output_disabled . '><i class="bi bi-unlock-fill me-2"></i>' . lang('Unlock') . '</button>
						</form>
						<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 small">
							<a class="link-secondary" href="../forgot_password.php" target="_blank">' . lang('forgot password') . '</a>
							<span class="text-body-secondary">' . lang('Attempts remaining') . ': <b>' . get_install_attempts_remaining() . '</b></span>
						</div>
						<hr>
						<div class="alert alert-primary d-flex gap-2 py-2 px-3 small mb-2">
							<i class="bi bi-shield-lock-fill"></i>
							<div><b>' . lang('Why this screen?') . '</b> ' . lang('The install script is always public. The names of the backups on your server, the database fields and the restore button are not shown to anyone until an administrator has authenticated. The screen stays unlocked for 30 minutes.') . '</div>
						</div>
						<div class="alert alert-warning d-flex gap-2 py-2 px-3 small mb-0">
							<i class="bi bi-clock-history"></i>
							<div>' . lang('If you can sign in to the control panel, this step is never asked, because your session is recognized automatically.') . '</div>
						</div>
					</div>
				</div>
				<div class="install-lock-ghosts mt-4" aria-hidden="true">
					<div class="d-flex gap-3 mb-3"><div class="install-lock-ghost flex-grow-1"></div><div class="install-lock-ghost" style="width:35%;"></div></div>
					<div class="d-flex gap-3 mb-3"><div class="install-lock-ghost flex-grow-1"></div></div>
					<div class="d-flex gap-3 mb-3"><div class="install-lock-ghost" style="width:35%;"></div><div class="install-lock-ghost flex-grow-1"></div></div>
				</div>
				<p class="text-center small text-body-secondary"><i class="bi bi-lock me-1"></i>' . lang('Installation options appear after the screen is unlocked') . '</p>
			</div>
		</div>
	</main>' .

	get_footer();

	exit();

}

function get_header() {
	return output_header_secure(array(
		'title' => lang('Install or Upgrade'),
		'icon' => 'setting'
	));
}

function get_footer() {
	global $software_version;
	return '<div id="footer" class="footer p-3 d-flex flex-wrap justify-content-center justify-content-md-between text-muted d-print-none">
                <h6 class="mx-auto">' . lang(array(
		'string' => 'Pinegrap Content Management System'
	)) . '</h6>
                <h6 class="version_and_copyright mx-auto">v' . $software_version . ' ' . EDITION . ' - <span class="material-icons" >copyright</span>' . date('Y', time()) . '</h6>
            </div>
	</div>
	</div>
    </body>
    </html>';

}

function return_to_form()
 {

	global $install_streaming, $liveform;

	// Once the installation is being streamed the page has already started, so we cannot redirect
	// any more.  We show what went wrong instead and let the visitor go back themselves.  The
	// errors the form collected are printed here, because the screen that started the
	// installation shows this answer and nothing else.
	if (($install_streaming == true) || (headers_sent())) {

		$output_errors = '';

		if (is_object($liveform)) {

			$output_errors = $liveform->output_errors();

		}

		add_install_step(lang('The installation was stopped.'), '', 'error');

		write_install_progress_file(true);

		print '
		<div id="pg_stream_result">
			<div class="container-xl px-0" style="max-width:960px;">
				<div class="alert alert-danger d-flex gap-2 mt-3 mb-2">
					<i class="bi bi-exclamation-triangle-fill"></i>
					<div>' . lang('The installation was stopped. Please go back to the installation screen and check the information that you entered.') . '</div>
				</div>
				' . $output_errors . '
				<a class="btn btn-primary mb-4" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/install/"><span class="me-1 material-icons">arrow_back</span>' . lang('Installation') . '</a>
			</div>
		</div>
		<script>pg_install_stream_complete();</script>' . get_footer();

		exit();

	}

	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/');

	exit();

}

function get_version_key($number, $versions)
 {

	// loop through all versions in order to find the key
	foreach ($versions as $key => $version) {

		// if this is the version, then return the key
		if ($version['number'] == $number) {

			return $key;

		}

	}

	// if we have gotten here, then a key was not found, so return false
	return false;

}

function check_if_administrator_is_logged_in()
 {

	// if a signed-in user id is set in the session, check it belongs to an administrator
	if ((isset($_SESSION['sessionuserid']) == true) && ($_SESSION['sessionuserid'] !== '')) {

		$query =

		"SELECT user_id

			FROM user

			WHERE

				(user_role = '0')

				AND (user_id = '" . (int) $_SESSION['sessionuserid'] . "')";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		// if a user was found, then return true
		if (mysqli_num_rows($result) > 0) {

			return true;

		}

	}

	// ── Upgrade bridge: the previous release's credentials ──────────────────
	//
	// Only while the database is behind the code (install_bridge_open()). In
	// that window this screen is the only way to the upgrade, and whoever
	// arrives holds the PREVIOUS release's proof of identity, in one of two
	// shapes that release used:
	//
	//   session   sessionusername + sessionpassword (the MD5 sign-in stored
	//             server-side) - what software_update.php arrives with right
	//             after the file swap;
	//   cookie    software[username] + software[password] (the old remember-me
	//             pair) - an administrator whose session ended before the
	//             upgrade ran, but who had ticked "remember me".
	//
	// Both are checked the way that release checked them: bare MD5 against
	// user_password, administrator role only. Refusing them locked 2026.4.3
	// sites out of their own upgrade. Once config.version has caught up the
	// bridge closes and this block is dead code; it can go when no supported
	// site is below 2026.4.4.
	if ((!isset($_SESSION['sessionuserid'])) && (install_bridge_open() == true)) {

		$legacy_pairs = array();

		if ((isset($_SESSION['sessionusername'])) && ($_SESSION['sessionusername'] !== '')
			&& (isset($_SESSION['sessionpassword'])) && ($_SESSION['sessionpassword'] !== '')) {

			$legacy_pairs[] = array($_SESSION['sessionusername'], $_SESSION['sessionpassword']);

		}

		if ((isset($_COOKIE['software']['username'])) && ($_COOKIE['software']['username'] !== '')
			&& (isset($_COOKIE['software']['password'])) && ($_COOKIE['software']['password'] !== '')) {

			$legacy_pairs[] = array($_COOKIE['software']['username'], $_COOKIE['software']['password']);

		}

		foreach ($legacy_pairs as $legacy_pair) {

			$legacy_admin_id = db_value(
				"SELECT user_id FROM user
					WHERE (user_role = '0')
						AND (user_username = '" . escape($legacy_pair[0]) . "')
						AND (user_password = '" . escape($legacy_pair[1]) . "')
					LIMIT 1");

			if ($legacy_admin_id) {

				return true;

			}

		}

	}

	// if we got here then an administrator is not logged in, so return false
	return false;

}

// True while the database (config.version) is behind the version this code
// knows (pg_code_version(), the last line of versions.php): the window in
// which the bridge above is the only way in. Asked once per request.
function install_bridge_open() {

	static $open = null;

	if ($open === null) {

		$database_version = (string) db_value("SELECT version FROM config LIMIT 1");

		$code_version = pg_code_version();

		$open = (($database_version !== '') && ($code_version !== '') && (version_compare($database_version, $code_version, '<')));

	}

	return $open;

}

// Runs every statement of a MySQL dump.  The file is read line by line rather than with
// file(), because a backup of a big site does not fit in memory_limit, and the statement
// that fails is returned with the error, so the screen can say what went wrong instead of
// "an error occurred".  Returns true, or array('statement' => ..., 'error' => ...).
function parse_mysql_dump($url)
 {

	global $install_progress_running;

	$handle = @fopen($url, 'r');

	if ($handle === false) {

		return array('statement' => basename($url), 'error' => lang('The file could not be opened.'));

	}

	$query = '';

	$count = 0;

	while (($sql_line = fgets($handle)) !== false) {

		$tsl = trim($sql_line);

		if (($tsl == '') || (mb_substr($tsl, 0, 2) == '--') || (mb_substr($tsl, 0, 1) == '#')) {

			continue;

		}

		$query .= $sql_line;

		if (preg_match("/;\s*$/", $sql_line)) {

			$result = mysqli_query(db::$con, trim($query));

			if (!$result) {

				$failed = preg_replace('/\s+/', ' ', trim(mb_substr($query, 0, 160)));

				fclose($handle);

				return array('statement' => $failed, 'error' => mysqli_error(db::$con));

			}

			$query = '';

			$count++;

			// a heartbeat for the screen, so a long dump does not look like a frozen page
			if (($count % 500) == 0) {

				$install_progress_running = lang(array('string' => 'sql.sql · {var:1} statements', 'vars' => number_format($count)));

				write_install_progress_file(false);

			}

		}

	}

	fclose($handle);

	$install_progress_running = '';

	return true;

}

function get_database_version()
 {

	// Check database if version column exists.
	$query = "SHOW COLUMNS FROM config LIKE 'version'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If the column exists, then we can get the version from the database.
	if (mysqli_num_rows($result) != 0) {

		// Query the database for versions value.
		$query = "SELECT version FROM config";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$row = mysqli_fetch_assoc($result);

		// Return the value from the database.
		return $row['version'];

	}

	// if the email_recipients table does not exist, then the version is too old to detect
	$query = "SHOW TABLES LIKE 'email_recipients'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this version is too old to detect.
	if (mysqli_num_rows($result) == 0) {

		return false;

	}

	// query the database to see if the visitors table exists
	$query = "SHOW TABLES LIKE 'visitors'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// if the table does not exist, then the version is 4.5.0
	if (mysqli_num_rows($result) == 0) {

		return '4.5.0';

	}

	// query the database to see if the po_number column exists in the billing_information_pages table
	$query = "SHOW COLUMNS FROM billing_information_pages LIKE 'po_number'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// if the column does not exist, then the version is 4.5.1
	if (mysqli_num_rows($result) == 0) {

		return '4.5.1';

	}

	// query the database to see if the upsell column exists in the offers table
	$query = "SHOW COLUMNS FROM offers LIKE 'upsell'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// if the column does not exist, then the version is 4.5.2
	if (mysqli_num_rows($result) == 0) {

		return '4.5.2';

	}

	// query the database to see if the contact_groups table exists
	$query = "SHOW TABLES LIKE 'contact_groups'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// if the table does not exist, then the version is 4.5.3
	if (mysqli_num_rows($result) == 0) {

		return '4.5.3';

	}

	// Check if the version is 4.5.5
	// Query the database to see if the custom_field_1 column exists in the orders table.
	$query = "SHOW COLUMNS FROM orders LIKE 'custom_field_1'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If the column does not exist, then the version is 4.5.4
	if (mysqli_num_rows($result) == 0) {

		return '4.5.4';

	}

	// Check if the version is 5.0.0
	// If the calendars table exists, then this is atleast version 5.0.0
	$query = "SHOW TABLES LIKE 'calendars'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 4.5.5
	if (mysqli_num_rows($result) == 0) {

		return '4.5.5';

	}

	// Check if the version is 5.0.2
	// Query the database to see if the url_host column exists in the config table.
	$query = "SHOW COLUMNS FROM config LIKE 'url_host'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are results, then this is version 5.0.0 or 5.0.1
	if (mysqli_num_rows($result) != 0) {

		return '5.0.1';

	}

	// Check if the version is 5.0.3
	// Query the database to see if the type column exists in the contact_groups_email_campaigns_xref table.
	$query = "SHOW COLUMNS FROM contact_groups_email_campaigns_xref LIKE 'type'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.2
	if (mysqli_num_rows($result) == 0) {

		return '5.0.2';

	}

	// Check if the version is 5.0.4
	// Query the database to see if the quiz column exists in the custom_form_pages table.
	$query = "SHOW COLUMNS FROM custom_form_pages LIKE 'quiz'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.3
	if (mysqli_num_rows($result) == 0) {

		return '5.0.3';

	}

	// Check if the version is 5.0.5
	// Query the database to see if the ecommerce_authorizenet_api_login_id column exists in the config table.
	$query = "SHOW COLUMNS FROM config LIKE 'ecommerce_authorizenet_api_login_id'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.4
	if (mysqli_num_rows($result) == 0) {

		return '5.0.4';

	}

	// Check if the version is 5.0.6
	// Query the database to see if the table calendar_event_exceptions exists.
	$query = "SHOW TABLES LIKE 'calendar_event_exceptions'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.5
	if (mysqli_num_rows($result) == 0) {

		return '5.0.5';

	}

	// Check if the version is 5.0.7
	// Query the database to see if the formats table exists.
	$query = "SHOW TABLES LIKE 'formats'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are results, then this is version 5.0.6
	if (mysqli_num_rows($result) != 0) {

		return '5.0.6';

	}

	// Check if the version is 5.0.8
	// Query the database to see if the currencies table exists.
	$query = "SHOW TABLES LIKE 'currencies'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.7
	if (mysqli_num_rows($result) == 0) {

		return '5.0.7';

	}

	// Check if the version is 5.0.9
	// Query the database to see if the express_order_pages table exists.
	$query = "SHOW TABLES LIKE 'express_order_pages'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.8
	if (mysqli_num_rows($result) == 0) {

		return '5.0.8';

	}

	// Check if the version is 5.5.0
	// Query the database to see if the menus table exists.
	$query = "SHOW TABLES LIKE 'menus'";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	// If there are no results, then this is version 5.0.9
	if (mysqli_num_rows($result) == 0) {

		return '5.0.9';

	}

	// If we made it this far without returning anything, something is wrong and we are unable to detect the version.
	return false;

}

function add_content_to_stylesheets($content)
 {

	// get all design stylesheets so that the CSS can be added to them
	$query = "SELECT name FROM files WHERE (design = '1') AND (type = 'css') ORDER BY name ASC";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	$stylesheets = array();

	while ($row = mysqli_fetch_assoc($result)) {

		$stylesheets[] = $row;

	}

	// loop through all stylesheets in order to add content to them
	foreach ($stylesheets as $stylesheet) {

		$stylesheet_path = FILE_DIRECTORY_PATH . '/' . $stylesheet['name'];

		// open stylesheet for appending
		$handle = @fopen($stylesheet_path, 'a');

		// if stylesheet could be opened, then write content
		if ($handle) {

			fwrite($handle, $content);

			fclose($handle);

		}

	}

}

// Create a function that will create a backup of all system themes,
// so if there is a problem from updating system themes, a site can use the backup.
// We create a backup as a custom theme. $version is used in order to give a backup
// a unique name associated with the version that we are updating to.  It is also
// included in the description of the backup file that is created.
function backup_system_themes($version)
 {

	// Get all themes so we can back them up.
	$query =

	"SELECT

			id,

			name,

			folder AS folder_id

		FROM files

		WHERE

			(design = '1')

			AND (type = 'css')";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	$themes = mysqli_fetch_items($result);

	foreach ($themes as $theme) {

		// Check to see if this is a system or custom theme.
		$query = "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $theme['id'] . "'";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$row = mysqli_fetch_row($result);

		// If this is a system theme then back it up.
		if ($row[0] > 0) {

			// Get theme name with and without file extension. We will use this in order to create a backup.
			$theme_name_without_extension = mb_substr($theme['name'], 0, mb_strrpos($theme['name'], '.'));

			$theme_extension = mb_substr($theme['name'], mb_strrpos($theme['name'], '.') + 1);

			// Prepare the backup theme name.
			$backup_theme_name = $theme_name_without_extension . '-backup-pre-v' . $version . '.' . $theme_extension;

			// Check if the backup theme name already exists (unlikely)
			$query = "SELECT COUNT(*) FROM files WHERE name = '" . escape($backup_theme_name) . "'";

			$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

			$row = mysqli_fetch_row($result);

			// If the backup theme name does not exist, then create backup theme.
			if ($row[0] == 0) {

				$query =

				"INSERT INTO files (

						name,

						folder,

						description,

						type,

						size,

						design,

						timestamp)

					VALUES (

						'" . escape($backup_theme_name) . "',

						'" . escape($theme['folder_id']) . "',

						'" . escape('This backup Theme was automatically created during the v' . $version . ' update.') . "',

						'" . escape($theme_extension) . "',

						'" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $theme['name'])) . "',

						'1',

						UNIX_TIMESTAMP())";

				$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

				$backup_theme_id = mysqli_insert_id(db::$con);

				// Create file handle in order to create file for backup theme.
				$handle = @fopen(FILE_DIRECTORY_PATH . '/' . $backup_theme_name, 'w');

				// If the backup theme could be opened for writing, then continue to update it.
				if ($handle == true) {

					// Get content of original theme in order to use it for backup theme.
					$theme_content = @file_get_contents(FILE_DIRECTORY_PATH . '/' . $theme['name']);

					// Update backup theme with the content.
					@fwrite($handle, $theme_content);

					// Close the backup theme.
					@fclose($handle);

				}

			}

		}

	}

}

// Create function that will regenerate CSS for system themes.
// We use this sometimes during an update so themes have CSS for new features.
function update_system_themes()
 {

	// Get all themes so we can update them.
	$query =

	"SELECT

			id,

			name

		FROM files

		WHERE

			(design = '1')

			AND (type = 'css')";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	$themes = mysqli_fetch_items($result);

	// Loop through the themes in order to update them.
	foreach ($themes as $theme) {

		// Check to see if this is a system theme.
		$query = "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $theme['id'] . "'";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$row = mysqli_fetch_row($result);

		// If this is a system theme then update it.
		if ($row[0] > 0) {

			// Get the properties from the database.
			$query =

			"SELECT

					area,

					`row`, # Backticks for reserved word.

					col,

					module,

					property,

					value,

					region_type,

					region_name

				FROM system_theme_css_rules 

				WHERE file_id = '" . $theme['id'] . "'";

			$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

			$rules = mysqli_fetch_items($result);

			$properties = array();

			// Loop through the system theme css rules in order to prepare css properties.
			foreach ($rules as $rule) {

				// If this is an ad region, then set the properties in the ad regions area of the array.
				if ($rule['region_type'] == 'ad') {

					$properties['ad_region'][$rule['region_name']][$rule['module']][$rule['property']] = $rule['value'];

					// Otherwise if this is a menu region, then set the properties in the menu regions area of the array.
					
				}
				else if ($rule['region_type'] == 'menu') {

					$properties['menu_region'][$rule['region_name']][$rule['module']][$rule['property']] = $rule['value'];

					// Otherwise, this is not an ad or menu region, so process rule differently.
					
				}
				else {

					// If there is a row then output the object.
					if ($rule['row'] != 0) {

						$object = 'r' . $rule['row'] . 'c' . $rule['col'];

						// Otherwise there is not a row so set the object as the base object.
						
					}
					else {

						$object = 'base_object';

					}

					// If the module is not blank, then set the module.
					if ($rule['module'] != '') {

						$module = $rule['module'];

						// Otherwise the module is blank, so set the module to the base module
						
					}
					else {

						$module = 'base_module';

					}

					$properties[$rule['area']][$object][$module][$rule['property']] = $rule['value'];

				}

			}

			require_once (dirname(__FILE__) . '/../generate_system_theme_css.php');

			$system_theme_css = generate_system_theme_css($properties);

			$theme_path = FILE_DIRECTORY_PATH . '/' . $theme['name'];

			// Open theme in order to update CSS.
			$handle = @fopen($theme_path, 'w');

			// If theme could be opened, then write content.
			if ($handle) {

				fwrite($handle, $system_theme_css);

				fclose($handle);

			}

		}

	}

}

// Create function that will allow us to add content to the end of custom themes.
// We use this sometimes during an update so custom themes have CSS for new features.
function update_custom_themes($content)
 {

	// Get all themes so we can update them.
	$query =

	"SELECT

			id,

			name

		FROM files

		WHERE

			(design = '1')

			AND (type = 'css')";

	$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

	$themes = mysqli_fetch_items($result);

	// Loop through the themes in order to update them.
	foreach ($themes as $theme) {

		// Check to see if this is a custom theme.
		$query = "SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $theme['id'] . "'";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		$row = mysqli_fetch_row($result);

		// If this is a custom theme then update it.
		if ($row[0] == 0) {

			$theme_path = FILE_DIRECTORY_PATH . '/' . $theme['name'];

			// Open the theme for writing so it can be updated.
			$handle = @fopen($theme_path, 'a');

			// If the theme could be opened for writing, then continue to update it.
			if ($handle == true) {

				@fwrite($handle, $content);

				@fclose($handle);

			}

		}

	}

}

// Returns an array of all PineGrap tables that we use to know which tables in the database we can
// delete when doing a fresh install, so that we don't delete any custom tables.


// Every table the software creates has to be listed here: this is the list a
// reinstall drops. A table missing from it survives the reinstall with its old
// rows and its old structure, and the fresh schema is then built around it -
// which is how a "clean" install ends up carrying another site's statistics.
function get_tables() {

	return array(
		'aclfolder',
		'ad_regions',
		'address_book',
		'ads',
		'affiliate_sign_up_form_pages',
		'allow_new_comments_for_items',
		'api_apps',
		'api_idempotency',
		'api_rate_bucket',
		'api_request_log',
		'api_webhook_queue',
		'api_webhooks',
		'applied_gift_cards',
		'arrival_dates',
		'auth_tokens',
		'auto_dialogs',
		'banned_ip_addresses',
		'billing_information_pages',
		'calendar_event_exceptions',
		'calendar_event_locations',
		'calendar_event_view_pages',
		'calendar_event_views_calendars_xref',
		'calendar_events',
		'calendar_events_calendar_event_locations_xref',
		'calendar_events_calendars_xref',
		'calendar_view_pages',
		'calendar_views_calendars_xref',
		'calendars',
		'catalog_detail_pages',
		'catalog_pages',
		'comments',
		'commissions',
		'config',
		'contact_groups',
		'contact_groups_email_campaigns_xref',
		'contacts',
		'contacts_contact_groups_xref',
		'containers',
		'cookies',
		'countries',
		'cregion',
		'currencies',
		'custom_form_confirmation_pages',
		'custom_form_pages',
		'dregion',
		'email_a_friend_pages',
		'email_campaign_profiles',
		'email_campaigns',
		'email_recipients',
		'erp_account_transactions',
		'erp_accounts',
		'erp_cash_accounts',
		'erp_cash_transactions',
		'erp_document_series',
		'erp_edoc_log',
		'erp_edoc_providers',
		'erp_edoc_queue',
		'erp_export_log',
		'erp_invoice_items',
		'erp_invoices',
		'erp_parasut_log',
		'erp_settlements',
		'erp_waybill_items',
		'erp_waybills',
		'excluded_transit_dates',
		'express_order_pages',
		'files',
		'folder',
		'folder_view_pages',
		'form_data',
		'form_field_options',
		'form_fields',
		'form_signatures',
		'form_item_view_pages',
		'form_list_view_browse_fields',
		'form_list_view_filters',
		'form_list_view_pages',
		'form_view_directories_form_list_views_xref',
		'form_view_directory_pages',
		'forms',
		'gift_cards',
		'key_codes',
		'log',
		'login_regions',
		'marketplace_accounts',
		'marketplace_categories',
		'marketplace_category_attributes',
		'marketplace_category_map',
		'marketplace_order_map',
		'marketplace_product_map',
		'marketplace_sync_queue',
		'menu_items',
		'menus',
		'messages',
		'next_order_number',
		'offer_actions',
		'offer_actions_shipping_methods_xref',
		'offer_conditions',
		'offer_rules',
		'offer_rules_products_xref',
		'offers',
		'offers_offer_actions_xref',
		'opt_in',
		'order_form_pages',
		'order_item_gift_cards',
		'order_items',
		'order_preview_pages',
		'order_receipt_pages',
		'order_report_filters',
		'order_reports',
		'orders',
		'page',
		'photo_gallery_pages',
		'pregion',
		'preview_styles',
		'product_attribute_options',
		'product_attributes',
		'product_groups',
		'product_groups_attributes_xref',
		'products_images_xref',
		'product_groups_images_xref',
		'product_submit_form_fields',
		'products',
		'push_subscriptions',
		'push_queue',
		'products_attributes_xref',
		'products_groups_xref',
		'products_zones_xref',
		'recurring_commission_profiles',
		'referral_sources',
		'remaining_reservation_spots',
		'search_items',
		'search_results_pages',
		'ship_date_adjustments',
		'ship_tos',
		'shipping_address_and_arrival_pages',
		'shipping_cutoffs',
		'shipping_delivery_dates',
		'shipping_method_pages',
		'shipping_methods',
		'shipping_methods_zones_xref',
		'shipping_rates',
		'shipping_tracking_numbers',
		'shopping_cart_pages',
		'short_links',
		'states',
		'style',
		'submitted_form_info',
		'submitted_form_view_stats',
		'submitted_form_views',
		'system_style_cells',
		'system_theme_css_rules',
		'tag_cloud_keywords',
		'tag_cloud_keywords_xref',
		'target_options',
		'tax_zones',
		'tax_zones_countries_xref',
		'tax_zones_states_xref',
		'talks',
		'update_address_book_pages',
		'user',
		'users_ad_regions_xref',
		'users_calendars_xref',
		'users_common_regions_xref',
		'users_contact_groups_xref',
		'users_menus_xref',
		'users_messages_xref',
		'verified_shipping_addresses',
		'visitor_report_filters',
		'visitor_reports',
		'visitors',
		'watchers',
		'zones',
		'zones_countries_xref',
		'zones_states_xref',
		'dashboard',
		'notifications',
		'notification_reads',
		'iyzipay_3ds_state',
		'order_refunds',
		'product_barcodes',
		'shared_components',
		'perf_log',
		'perf_stats',
		'waf_bot_ranges',
		'waf_log',
		'waf_rate',
		'waf_ip_reputation',
		'chat_conversations',
		'chat_messages',
		'cron_runs',
		'recycle_bin',
		'seo_issue',
		'seo_link',
		'visitor_stats_hourly',
		'visitor_content_hourly',
		'local_sale_history',
		'local_sale_history_items',
		'designer_presence',
		'designer_page_lock',
	);

}

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
 *              2016–2025 Kodpen
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
// if this is being run from an automated upgrade process, then remember that
if (

($argv[1] == 'automated_upgrade') || ($_REQUEST['automated_upgrade'] == 'true')) {

	$automated_upgrade = true;

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
	$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
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
	if( defined('ENFORCEMENT_SOFTWARE_LANGUAGE') ){
		define('DEFAULT_SOFTWARE_LANGUAGE', ENFORCEMENT_SOFTWARE_LANGUAGE);
		$output_enforcement = '(' . ENFORCEMENT_SOFTWARE_LANGUAGE . ')';
	}else{
		// else check if there is DEFAULT_SOFTWARE_LANGUAGE setup in config.
		if( defined('DEFAULT_SOFTWARE_LANGUAGE') ){
			define('SOFTWADEFAULT_SOFTWARE_LANGUAGERE_LANGUAGE', DEFAULT_SOFTWARE_LANGUAGE);
		}else{
			define('DEFAULT_SOFTWARE_LANGUAGE', dedect_user_language());
		}
	}

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
	define('EDITION', 'Premium');
}
require (dirname(__FILE__) . '/../functions.php');

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
	if (stristr($_SERVER['SERVER_SOFTWARE'], 'iis')) {

		define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../../httpd.ini');

		define('HTACCESS_FILE_NAME', 'httpd.ini');

		// Otherwise the web server is Apache, so set the htaccess file info to the .htaccess location.
		
	}
	else {

		define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../../.htaccess');

		define('HTACCESS_FILE_NAME', '.htaccess');

	}

}

//Backup Files Directory
function get_backup_options() {
	$directory_path = dirname(__FILE__) . '/../data/backups/';
	foreach (array_diff(scandir($directory_path) , array(
		'..',
		'.'
	)) as $backup_folder) if (is_dir($directory_path . '/' . $backup_folder)) $directory[] = $backup_folder;

	if ($directory) {
		$backup_options = array();
		$backup_options['-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('backup')) )) . '-']	= '';
		foreach ($directory as $backup_folder) {
			$backup_options[$backup_folder] = $backup_folder;
		}
		return $backup_options;
	}
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
$versions = array(
  array('number' => '2017.2'    ),
  array('number' => '2017.2.1'  ),
  array('number' => '2017.2.2'  ),
  array('number' => '2017.2.3'  ),
  array('number' => '2017.2.4'  ),
  array('number' => '2017.2.5'  ),
  array('number' => '2017.2.6'  ),
  array('number' => '2017.2.7'  ),
  array('number' => '2017.2.8'  ),
  array('number' => '2017.2.9'  ),
  array('number' => '2017.2.10' ),
  array('number' => '2017.2.11' ),
  array('number' => '2017.2.12' ),
  array('number' => '2017.2.13' ),
  array('number' => '2019.1'    ),
  array('number' => '2019.1.1'  ),
  array('number' => '2019.1.2'  ),
  array('number' => '2019.1.3'  ),
  array('number' => '2019.1.4'  ),
  array('number' => '2019.1.5'  ),
  array('number' => '2019.1.6'  ),
  array('number' => '2019.1.7'  ),
  array('number' => '2019.1.8'  ),
  array('number' => '2019.1.9'  ),
  array('number' => '2019.1.10' ),
  array('number' => '2019.2'    ),
  array('number' => '2019.2.1'  ),
  array('number' => '2019.2.2'  ),
  array('number' => '2019.2.3'  ),
  array('number' => '2019.2.4'  ),
  array('number' => '2019.2.5'  ),
  array('number' => '2019.2.6'  ),
  array('number' => '2019.2.7'  ),
  array('number' => '2019.2.8'  ),
  array('number' => '2019.2.9'  ),
  array('number' => '2020.1'    ),
  array('number' => '2020.1.1'  ),
  array('number' => '2020.1.2'  ),
  array('number' => '2020.1.3'  ),
  array('number' => '2020.1.4'  ),
  array('number' => '2020.1.5'  ),
  array('number' => '2020.1.6'  ),
  array('number' => '2020.1.7'  ),
  array('number' => '2020.1.8'  ),
  array('number' => '2020.2'    ),
  array('number' => '2020.2.1'  ),
  array('number' => '2020.2.2'  ),
  array('number' => '2020.2.3'  ),
  array('number' => '2020.2.4'  ),
  array('number' => '2020.2.5'  ),
  array('number' => '2020.3'    ),
  array('number' => '2020.3.1'  ),
  array('number' => '2020.3.2'  ),
  array('number' => '2020.3.3'  ),
  array('number' => '2020.3.4'  ),
  array('number' => '2020.4'    ),
  array('number' => '2020.4.1'  ),
  array('number' => '2020.4.2'  ),
  array('number' => '2021.1'    ),
  array('number' => '2021.1.1'  ),
  array('number' => '2021.1.2'  ),
  array('number' => '2021.1.3'  ),
  array('number' => '2021.1.4'  ),
  array('number' => '2021.1.5'  ),
  array('number' => '2021.1.6'  ),
  array('number' => '2021.1.7'  ),
  array('number' => '2021.1.8'  ),
  array('number' => '2021.1.9'  ),
  array('number' => '2021.1.10' ),
  array('number' => '2021.1.11' ),
  array('number' => '2021.1.12' ),
  array('number' => '2021.1.13' ),
  array('number' => '2021.1.14' ),
  array('number' => '2021.2'    ),
  array('number' => '2021.2.1'  ),
  array('number' => '2021.2.2'  ),
  array('number' => '2021.3'    ),
  array('number' => '2021.3.1'  ),
  array('number' => '2021.4'    ),
  array('number' => '2021.4.1'  ),
  array('number' => '2021.4.2'  ),
  array('number' => '2021.4.3'  ),
  array('number' => '2021.4.4'  ),
  array('number' => '2021.4.5'  ),
  array('number' => '2021.4.6'  ),
  array('number' => '2021.4.7'  ),
  array('number' => '2022'      ),
  array('number' => '2022.1'    ),
  array('number' => '2022.1.1'  ),
  array('number' => '2022.1.2'  ),
  array('number' => '2022.1.3'  ),
  array('number' => '2022.1.4'  ),
  array('number' => '2022.1.5'  ),
  array('number' => '2022.1.6'  ),
  array('number' => '2022.1.7'  ),
  array('number' => '2022.1.8'  ),
  array('number' => '2022.1.9'  ),
  array('number' => '2022.2'    ),
  array('number' => '2022.2.1'  ),
  array('number' => '2022.2.2'  ),
  array('number' => '2022.2.3'  ),
  array('number' => '2022.3'    ),
  array('number' => '2022.3.1'  ),
  array('number' => '2022.3.2'  ),
  array('number' => '2022.4'  	),
  array('number' => '2022.4.1'  ),
  array('number' => '2022.4.2'  ),
  array('number' => '2022.4.3'  ),
  array('number' => '2022.4.4'  ),
  array('number' => '2023' 		),
  array('number' => '2023.1'	),
  array('number' => '2023.1.1'	),
  array('number' => '2023.1.2'	),
  array('number' => '2023.2'	),
  array('number' => '2023.2.1'	),
  array('number' => '2023.3'	),
  array('number' => '2023.3.1'	),
  array('number' => '2026'		),
  array('number' => '2026.1'	),
  array('number' => '2026.1.1'	),
  array('number' => '2026.1.2'	),
  array('number' => '2026.1.3'	),
  array('number' => '2026.1.4'	),
  array('number' => '2026.1.5'	),
  array('number' => '2026.1.6'	),
  array('number' => '2026.1.7'	),
  array('number' => '2026.1.8'	),
  array('number' => '2026.1.9'	),
  array('number' => '2026.1.10'	),
  array('number' => '2026.1.11'	),
  array('number' => '2026.1.12'	),
  array('number' => '2026.1.13'	),
  array('number' => '2026.1.14'	),
  array('number' => '2026.1.15'	),
  array('number' => '2026.1.16'	),
  array('number' => '2026.1.17'	),
  array('number' => '2026.1.18'	),
  array('number' => '2026.1.19'	),
  array('number' => '2026.1.20'	),
  array('number' => '2026.1.21'	),
  array('number' => '2026.1.22'	),
  array('number' => '2026.1.23'	),
  array('number' => '2026.1.24'	),
  array('number' => '2026.1.25'	),
  array('number' => '2026.1.26'	),
  array('number' => '2026.1.27'	),
  array('number' => '2026.1.28'	),
  array('number' => '2026.1.29'	),
  array('number' => '2026.2'	),
  array('number' => '2026.2.1'	),
  array('number' => '2026.2.2'	),
  array('number' => '2026.2.3'	),
  array('number' => '2026.2.4'	),
  array('number' => '2026.2.5'	),
  array('number' => '2026.2.6'	),
  array('number' => '2026.2.7'	),
  array('number' => '2026.3'	),
  array('number' => '2026.3.1'	),
  array('number' => '2026.3.2'	),
  array('number' => '2026.3.3'	),
  array('number' => '2026.3.4'	),
  array('number' => '2026.3.5'	),
  array('number' => '2026.3.6'	),
  array('number' => '2026.3.7'	),
  array('number' => '2026.3.8'	),
  array('number' => '2026.4'	),
  array('number' => '2026.4.1'	),
);

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

// if user has not yet completed install form and this is not being run as part of an automated upgrade, output form
if ((!isset($_POST['submit'])) && ($automated_upgrade == false)) {

	// initialize variables
	$upgrade_option = false;

	$output_upgrade_message = '';
	$output_submit_button_value = 'Install';
	$output_submit_button_label = lang('Install');
	$output_submit_button_process_label = lang('Installing');
	$output_submit_button_icon ='done_all';

	$output_upgrade = '';

	// if DB_HOST is defined,
	// and a connection can be made to the database,
	// and the database can be selected,
	// then check if software is already installed
	if (

	(defined('DB_HOST') == true) and (db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE))) {

		init_mysql_charset();

		// Disable MySQL strict mode, because later versions of MySQL enable strict mode by default,
		// and PineGrap is not compatible with strict mode.  This will also remove all other sql modes,
		// however that should be fine.
		mysqli_query(db::$con, "SET SESSION sql_mode = ''");

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

				// if database version key is less than software version key, then offer upgrade option
				if ($database_version_key < $software_version_key) {

					$upgrade_option = true;

					$output_upgrade_message = lang('Please select whether you want to upgrade the existing site or replace the existing site with a new site.') . ' ';

					// if an install type has not already been selected, then select upgrade by default
					if ($liveform->get_field_value('install_type') != 'install') {
						$liveform->assign_field_value('install_type', 'upgrade');
						$output_submit_button_value = 'Upgrade';
						$output_submit_button_label = lang('Upgrade');
						$output_submit_button_process_label = lang('Upgrading');
						$output_submit_button_icon ='system_update_alt';

					}

					

					// initialize variables
					$output_upgrade_authentication = '';

					// if user is not logged in or is not an administrator, then display authentication rows
					if (check_if_administrator_is_logged_in() == false) {

						$output_upgrade_authentication ='
						<div class="col-12 col-lg-8">
							<h5>' . lang('Authentication') . '</h5>
							<div class="row">
								<div class="col-12 col-md-6 my-1">
									<label class="form-label" for="upgrade_authentication_username">' . lang('Email') . '</label>
									' . $liveform->output_field(array(
										'type' => 'text',
										'class' => 'form-control',
										'id' => 'upgrade_authentication_username',
										'name' => 'upgrade_authentication_username'
									)) . '
								</div>
								<div class="col-12 col-md-6 my-1">
									<label class="form-label" for="upgrade_authentication_password">' . lang('Password') . '</label>
									' . $liveform->output_field(array(
										'type' => 'password',
										'class' => 'form-control',
										'id' => 'upgrade_authentication_password',
										'name' => 'upgrade_authentication_password'
									)) . '
								</div>
								<div class="col-12 col-md-12 my-1">
									<p class="form-text">' . lang(array('string'=>'Please enter the email address and password for an administrator for the existing site. If you cannot remember your login information you can use the {var:1} feature.','vars'=>'<a class="link-secondary" href="../forgot_password.php" target="_blank">' . lang('forgot password') . '</a>')) . '</p>
								</div>
							</div>
						</div>';
					}

					$output_upgrade ='
					<div class="col-12">
						<div class="form-check" aria-labelledby="Upgrade">
							' . $liveform->output_field(array(
								'type' => 'radio',
								'name' => 'install_type',
								'id' => 'upgrade',
								'value' => 'upgrade',
								'class' => 'form-check-input collapse-switcher',
								'data-bs-target'=>'#upgrade_fields',
								'data-toggle'=>'tab'
							)) . '
							<label for="upgrade"  class="form-check-label">' . lang(array('string'=>'Upgrade from version {var:1} to {var:2}','vars'=>array($database_version,$software_version) )) . '</label>
						</div>
						<div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="upgrade_fields">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row">
                                    <div class="col-12 col-lg">
										<div class="alert alert-secondary">
											<h5 class="alert-heading">' . lang('Instructions') . '</h5>
											<p>' . lang('You must create a backup of the database and the software before upgrading. If custom changes have been made to your software or database, then you should consult with the software provider before upgrading.') . '</p>
										</div>
                                    </div>
									' . $output_upgrade_authentication . '
                                </div>
                            </div>
                        </div>
						<div class="form-check " aria-labelledby="Install">
							' . $liveform->output_field(array(
								'type' => 'radio',
								'name' => 'install_type',
								'id' => 'install',
								'value' => 'install',
								'class' => 'radio form-check-input collapse-switcher',
								'data-bs-target'=>'#install_fields',
								'data-toggle'=>'tab'
							)) . '
							<label for="install"  class="form-check-label">' . lang(array('string'=>'Install version {var:1} and replace existing site','vars'=>$software_version)) . '</label>
						</div>
					</div>';
				}

			}

			$output_install_authentication = '';

			// if user is not logged in or is not an administrator, then display authentication rows
			if (check_if_administrator_is_logged_in() == false) {
				$output_install_authentication ='
				<div class="col-12">
              		<div class="card my-4">
              		  	<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
              		  	  ' . lang('Authentication') . '
              		  	</div>
              		  	<div class="card-body">
							<div class="row">
								<div class="col-12 col-md-4 my-1">
									<label class="form-label" for="install_authentication_username">' . lang('Email') . '</label>
									' . $liveform->output_field(array(
										'type' => 'text',
										'class' => 'form-control',
										'id' => 'install_authentication_username',
										'name' => 'install_authentication_username'
									)) . '
								</div>
								<div class="col-12 col-md-4 my-1">
									<label class="form-label" for="install_authentication_password">' . lang('Password') . '</label>
									' . $liveform->output_field(array(
										'type' => 'password',
										'class' => 'form-control',
										'id' => 'install_authentication_password',
										'name' => 'install_authentication_password'
									)) . '
								</div>
								<div class="col-12 col-md-8 my-1">
									<p class="form-text">' . lang(array('string'=>'Please enter the email address and password for an administrator for the existing site. If you cannot remember your login information you can use the {var:1} feature, or you can delete and recreate the MySQL database and try again.','vars'=>'<a class="link-secondary" href="../forgot_password.php" target="_blank">' . lang('forgot password') . '</a>')) . '</p>
								</div>
							</div>
						</div>
					</div>
				</div>';

			}

		}

	}




	// initialize variable
	$output_install_option = '';
	// if there is an upgrade option, then prepare to output install option as a radio button
	if ($upgrade_option != true) {
		//there is no upgrade option, so prepare to output install option as a hidden field
		$output_install_option = $liveform->output_field(array(
			'type' => 'hidden',
			'name' => 'install_type',
			'value' => 'install'
		));
	}

	if ($_SESSION['software']['install']['reinstall'] == true) {
		$reinstallation_verification ='
		<div class="col-12">
        	<div class="card my-4">
        	  	<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
        	  	  ' . lang('Reinstallation Verification') . '
        	  	</div>
        	  	<div class="card-body">
					<div class="row">
						<div class="col-12">
							<div class="alert alert-danger">
								<p>' . lang('Existing data has been found in the database. Please verify that you want to reinstall the software. All site data will be permanently deleted. This includes all updates you have made since the last installation (i.e. styles, pages, files, products, and etc.). If you wish to continue, please check to verify reinstallation.') . '</p>
							</div>
						</div>
						<div class="col-s12 my-2">
							<div class="form-check">
								' . $liveform->output_field(array(
									'type' => 'hidden',
									'name' => 'reinstall_software'
								)) . $liveform->output_field(array(
									'type' => 'checkbox',
									'name' => 'reinstall_software',
									'id' => 'reinstall_software',
									'value' => '1',
									'class' => 'checkbox form-check-input'
								)) . '
							  	<label class="form-check-label" for="reinstall_software">' . lang('Reinstall') . ' (' . lang('all data will be permanently deleted') .')</label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>';

	}

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
  	<script type="text/javascript">
	  	function upgrade_install_switch() {
	  		if ($("input.collapse-switcher").length > 0) {
				if(	$("#upgrade").is(":checked")) { 
					$("#submit").val("Upgrade");
					$("#submit").html("<span class=\'material-icons me-2\'>system_update_alt</span><span class=\'btn-text\'>' . lang('Upgrade') . '</span>");
				}
				if(	$("#install").is(":checked")) { 
					$("#submit").val("Install");
					$("#submit").html("<span class=\'material-icons me-2\'>done_all</span><span class=\'btn-text\'>' . lang('Install') . '</span>");
				}
    		}
		}

		$(document).ready(function() {
			upgrade_install_switch();

			$("input[name=\'install_type\']").on("click focus keydown", function(){
				upgrade_install_switch();
			});
		});
  	</script>
  	<main id="content" class="container">
    	<div class="row">
    	  	<div class="col-12">
    	  	  	' . $liveform->output_errors() . '
    	  	  	' . $liveform->get_warnings() . '
    	  	  	' . $liveform->output_notices() . '
    	  	  	<div class="row mb-2  flex-wrap">
    	  	  	    <div class="col-12 col-sm-12 text-center text-md-start">
    	  	  	        <h2 class="d-inline-block " data-bs-content="' . lang('Install or upgrade the software.') . '" title="' . lang('Installation') . '">' . lang('Installation') . '</h2>
    	  	  	    </div>
    	  	  	</div>
				<form method="post" style="margin: 0px">
    	  	  	  	' . get_token_field() . '
    	  	  	  	<div class="row">
    	  	  	  	  	' . $output_upgrade . '
    	  	  	  	  	' . $output_install_option . '
    	  	  	  	</div>
					<div class="row" id="install_fields">
						' . $reinstallation_verification . '
						' . $output_install_authentication . '
						<div class="col-12 col-md-6">
						  <div class="card my-4">
							  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								  ' . lang('Installation Folder') . '
							  </div>
							  <div class="card-body">
								  <div class="row">
									  <div class="col-12 my-2">
										  <label for="install_from_folder" class="form-label">' . lang('Install From Folder') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'select',
										  'id' => 'install_from_folder',
										  'name' => 'install_from_folder',
										  'class' => 'form-select',
										  'options' => get_backup_options()
										  )) . '
									  </div>
								  </div>
							  </div>
						  </div>
						</div>
						<div class="col-12 col-md-6">
						  <div class="card my-4">
							  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								  ' . lang('Language') . '
							  </div>
							  <div class="card-body">
								  <div class="row">
									  <div class="col-12 my-2">
										  <label for="default_software_language" class="form-label">' . lang('Default Software Language') . $output_enforcement . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'select',
										  'id' => 'default_software_language',
										  'name' => 'default_software_language',
										  'class' => 'form-select',
										  'options' => get_software_language_options()
										  )) . '
									  </div>
								  </div>
							  </div>
						  </div>
						</div>
						<div class="col-12">
						  <div class="card my-4">
							  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								  ' . lang('MySQL Database') . '
							  </div>
							  <div class="card-body">
								  <div class="row">
									  <div class="col-12 col-md-6 col-lg-3 my-2">
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
									  <div class="col-12 col-md-6 col-lg-3 my-2">
										  <label for="db_username" class="form-label">' . lang('Database Username') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'text',
										  'name' => 'db_username',
										  'id' => 'db_username',
										  'class' => 'form-control',
										  )) . '
									  </div>
									  <div class="col-12 col-md-6 col-lg-3 my-2">
										  <label for="db_password" class="form-label">' . lang('Database Password') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'password',
										  'name' => 'db_password',
										  'id' => 'db_password',
										  'class' => 'form-control',
										  )) . '
									  </div>
									  <div class="col-12 col-md-6 col-lg-3 my-2">
										  <label for="db_database" class="form-label">' . lang('Database Name') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'text',
										  'name' => 'db_database',
										  'id' => 'db_database',
										  'class' => 'form-control',
										  )) . '
									  </div>
								  </div>
							  </div>
						  </div>
						</div>
						<div class="col-12">
						  <div class="card my-4">
							  <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								  ' . lang('Administrator User') . '
							  </div>
							  <div class="card-body">
								  <div class="row">
									  <div class="alert alert-primary">' . lang('Please enter information for your new administrator user account.') . '</div>
									  <div class="col-12 col-md-4 my-2">
										  <label for="admin_username" class="form-label">' . lang('Username') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'text',
										  'id' => 'admin_username',
										  'name' => 'admin_username',
										  'class' => 'form-control'
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
										  'class' => 'form-control'
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
										  'class' => 'form-control'
										  )) . '
									  </div>
									  <div class="col-12 col-md-4 my-2">
										  <label for="admin_password" class="form-label">' . lang('Password') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'password',
										  'id' => 'admin_password',
										  'name' => 'admin_password',
										  'class' => 'form-control'
										  )) . '
									  </div>
									  <div class="col-12 col-md-4 my-2">
										  <label for="admin_confirm_password" class="form-label">' . lang('Confirm Password') . '</label>
										  ' . $liveform->output_field(array(
										  'type' => 'password',
										  'id' => 'admin_confirm_password',
										  'name' => 'admin_confirm_password',
										  'class' => 'form-control'
										  )) . '
									  </div>
								  </div>
							  </div>
						  </div>
						</div>
						<div class="col-12">
						  <div class="btn-group justify-content-end">
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
    	  	  	  	<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
    	  	  	    	<div class="container">
    	  	  	        	<div class=" btn-group flex-wrap justify-content-center">
    	  	  	            	<button type="submit" id="submit" name="submit" value="' . $output_submit_button_value . '" class="btn my-1  btn-success " data-loading-content="' . $output_submit_button_process_label . '"><span class="material-icons me-2">' . $output_submit_button_icon . '</span><span class="btn-text" >' . $output_submit_button_label . '</span></button>
    	  	  	        	</div>
    	  	  	    	</div>
    	  	  	  	</nav>
    	  	  	</form>
    	  	</div>
    	</div>
  	</main>' .

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

		// Disable MySQL strict mode, because later versions of MySQL enable strict mode by default,
		// and PineGrap is not compatible with strict mode.  This will also remove all other sql modes,
		// however that should be fine.
		mysqli_query(db::$con, "SET SESSION sql_mode = ''");

		// if this is not running from an automated upgrade and an administrator is not logged in, then validate username and password fields
		if (($automated_upgrade == false) && (check_if_administrator_is_logged_in() == false)) {

			$liveform->validate_required_field('upgrade_authentication_username', lang('Email is required.'));

			$liveform->validate_required_field('upgrade_authentication_password', lang('Password is required.'));

			// if there is not already an error
			if ($liveform->check_form_errors() == false) {

				// try to find user from username that was entered
				$query =

				"SELECT user_id

					FROM user

					WHERE

						(user_role = 0)

						AND

						(

							(user_username = '" . escape($liveform->get_field_value('upgrade_authentication_username')) . "')

							OR (user_email = '" . escape($liveform->get_field_value('upgrade_authentication_username')) . "')

						)

					LIMIT 1";

				$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

				// if a user was not found, prepare error
				if (mysqli_num_rows($result) == 0) {

					$liveform->mark_error('upgrade_authentication_username', lang('An administrator user could not be found for the email address or username that you supplied.') );

					// else a user was found, so check password
					
				}
				else {

					// try to find user from username and password that were entered
					$query =

					"SELECT user_id

						FROM user

						WHERE

							(user_role = 0)

							AND

							(

								(user_username = '" . escape($liveform->get_field_value('upgrade_authentication_username')) . "')

								OR (user_email = '" . escape($liveform->get_field_value('upgrade_authentication_username')) . "')

							)

							AND (user_password = '" . md5($liveform->get_field_value('upgrade_authentication_password')) . "')

						LIMIT 1";

					$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

					// if a user was not found, prepare error
					if (mysqli_num_rows($result) == 0) {

						$liveform->mark_error('upgrade_authentication_password', lang('The password you entered was incorrect. Please remember that passwords are case sensitive.'));

						$liveform->assign_field_value('upgrade_authentication_password', '');

					}

				}

			}

		}

		// if an error exists, then return to form
		if ($liveform->check_form_errors() == true) {

			return_to_form();

		}

		// Get MySQL version in order to determine if we should set the engine
		// for new tables that we create.  The engine property is not supported
		// in old MySQL version so we need to make sure that we don't add it
		// in order to avoid a query error during udpdate.
		$mysql_version = db_value("SELECT VERSION()");

		$mysql_version_parts = explode('.', $mysql_version);

		$mysql_major_version = $mysql_version_parts[0];

		$mysql_minor_version = $mysql_version_parts[1];

		// If the MySQL version is at least 4.1 then prepare engine value.
		// Engine support was actually added in MySQL 4.0.18, however we
		// don't want to deal with checking the maintenance version, so we are just
		// going to require 4.1 and higher.  No one but us is using earlier versions anyway.
		// We define it as a constant so that we can have access to it
		// in all update functions below.
		if (

		(

		($mysql_major_version == 4) && ($mysql_minor_version >= 1)) || ($mysql_major_version >= 5)) {

			define('ENGINE', ' ENGINE=MyISAM');

			// Otherwise MySQL version is before 4.1, so do not include engine property
			
		}
		else {

			define('ENGINE', '');

		}

		$database_version = get_database_version();

		$database_version_key = get_version_key($database_version, $versions);

		// get 5.5.0 version key, so we can determine if we should update version in database
		$version_key_5_5_0 = get_version_key('5.5.0', $versions);

		// loop through all versions
		foreach ($versions as $version_key => $version) {

			// if version is greater than database version, then run upgrade for this version
			if ($version_key > $database_version_key) {

				$function_name = 'upgrade_to_' . str_replace('.', '_', $version['number']);

				// If there is a function for this version, then run function.
				// Some versions do not need any db updates, so there might not be a function.
				if (function_exists($function_name)) {

					$function_name();

				}

				// if version is 5.5.0 or later, then update version in database
				if ($version_key >= $version_key_5_5_0) {

					$query = "UPDATE config SET version = '" . $version['number'] . "'";
					$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

				}

			}

		}

		// reset flag so that the site does not indicate that there is a software update available anymore
		$query = "UPDATE config SET software_update_available = '0'";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		// Reset software update check so that it will check again, so site will get new messages
		// for new version if there are any.
		db("UPDATE config SET last_software_update_check_timestamp = ''");

		// if this is being run from an automated upgrade, then display non-HTML message
		if ($automated_upgrade == true) {

			header('Location: ../welcome.php');

			print 'complete';

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

			// Disable MySQL strict mode, because later versions of MySQL enable strict mode by default,
			// and PineGrap is not compatible with strict mode.  This will also remove all other sql modes,
			// however that should be fine.
			mysqli_query(db::$con, "SET SESSION sql_mode = ''");

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

			// if software is already installed and this user is not already logged in as an administrator, then validate authentication fields
			if (($software_installed == true) && (check_if_administrator_is_logged_in() == false)) {

				$liveform->validate_required_field('install_authentication_username', lang('Email is required.'));

				$liveform->validate_required_field('install_authentication_password', lang('Password is required.'));

				// if there is not already an error
				if ($liveform->check_form_errors() == false) {

					// try to find user from username that was entered
					$query =

					"SELECT user_id

						FROM user

						WHERE

							(user_role = 0)

							AND

							(

								(user_username = '" . escape($liveform->get_field_value('install_authentication_username')) . "')

								OR (user_email = '" . escape($liveform->get_field_value('install_authentication_username')) . "')

							)

						LIMIT 1";

					$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

					// if a user was not found, prepare error
					if (mysqli_num_rows($result) == 0) {

						$liveform->mark_error('install_authentication_username', lang('An administrator user could not be found for the email address or username that you supplied.'));

						// else a user was found, so check password
						
					}
					else {

						// try to find user from username and password that were entered
						$query =

						"SELECT user_id

							FROM user

							WHERE

								(user_role = 0)

								AND

								(

									(user_username = '" . escape($liveform->get_field_value('install_authentication_username')) . "')

									OR (user_email = '" . escape($liveform->get_field_value('install_authentication_username')) . "')

								)

								AND (user_password = '" . md5($liveform->get_field_value('install_authentication_password')) . "')

							LIMIT 1";

						$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

						// if a user was not found, prepare error
						if (mysqli_num_rows($result) == 0) {

							$liveform->mark_error('install_authentication_password', lang('The password you entered was incorrect. Please remember that passwords are case sensitive.'));

							$liveform->assign_field_value('install_authentication_password', '');

						}

					}

				}

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

					// Disable MySQL strict mode, because later versions of MySQL enable strict mode by default,
					// and PineGrap is not compatible with strict mode.  This will also remove all other sql modes,
					// however that should be fine.
					mysqli_query(db::$con, "SET SESSION sql_mode = ''");

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

		// Prepare template sql file
		$database_file = dirname(__FILE__) . '/../data/backups/' . $install_directory_path . '/sql.sql';

		// run all queries from MySQL dump file for template
		if (parse_mysql_dump($database_file) == false) {

			$liveform->mark_error('', lang('There was an error while the database was being initialized. Please contact the software provider and include the error that appears next. MySQL error') . ': ' . mysqli_error(db::$con));

			return_to_form();

		}

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
			
		// generate redirection files if doesnt exist.
		// software do not work properly without them.
		// if Apache is being used (not IIS), then check if .htaccess file exists, if not generate.
		if (stristr($_SERVER['SERVER_SOFTWARE'], 'iis') === false && stristr($_SERVER['SERVER_SOFTWARE'], 'nginx') === false) {
		    if (defined('HTACCESS_FILE_PATH') && !file_exists(HTACCESS_FILE_PATH)) {
		        file_put_contents(HTACCESS_FILE_PATH,'# The following rules are used by Pinegrap.
			
		RewriteEngine on
			
		# When the system is accessed from a sub-directory with an Apache alias
		# (e.g. http://192.168.0.1/~example/), then you might need to uncomment the line
		# below and update it to point to where the system is installed.  The system
		# will attempt to automatically set the correct value for the line below during
		# installation.  You might need to comment out the line below once you launch
		# your site at a permanent URL without a sub-directory (e.g. http://www.example.com).
			
		#RewriteBase /~example/
			
		# The following lines redirect all requests to the Pinegrap router,
		# except for when an actual file or directory exists for the request.
			
		RewriteCond %{REQUEST_FILENAME} !-f
		RewriteCond %{REQUEST_FILENAME} !-d
		RewriteRule . pinegrap/router.php [L]');
		    }
		// else if IIS is being used, then check if web.config file exists, if not generate.
		} else if (stristr($_SERVER['SERVER_SOFTWARE'], 'iis') == true) {
		
		    if (!file_exists(dirname(__FILE__) . '/../../web.config')) {
		        file_put_contents(dirname(__FILE__) . '/../../web.config','<?xml version="1.0" encoding="UTF-8"?>
		<configuration>
		    <system.webServer>
		        <defaultDocument>
		            <files>
		                <clear />
		                <add value="index.php" />
		                <add value="index.htm" />
		                <add value="index.html" />
		            </files>
		        </defaultDocument>
		        <rewrite>
		            <rules>
		                <!--
		                    Blocks every direct request under data/, the same way the
		                    nginx sample below does.

		                    data/.htaccess says "deny from all", but that is an Apache
		                    file and IIS ignores it, so without this rule the folder is
		                    readable over HTTP: config.php, the database backups, and
		                    every uploaded file in data/files. Uploads take their
		                    filename from the browser, so a .php file landing there
		                    would be executed.

		                    Parts of it look protected by accident. A .sql answers 404
		                    only because IIS has no MIME mapping for that extension,
		                    which is not a security control; anything with a mapping
		                    (.json, .txt, .xml, .zip) is served.

		                    Written as a rewrite rule rather than <security>, because
		                    <security><authorization> is locked at server level on many
		                    hosts and a web.config using it makes every request under
		                    that path fail with 500.19. The rewrite module is already a
		                    hard requirement here — the rule below depends on it.
		                -->
		                <rule name="Block direct access to data" stopProcessing="true">
		                    <match url="^' . ltrim(PATH, '/') . 'data/" ignoreCase="true" />
		                    <action type="CustomResponse" statusCode="403" statusDescription="Forbidden" />
		                </rule>
		                <rule name="Pinegrap Rule" stopProcessing="true">
		                    <match url=".*" /> 
		                    <conditions> 
		                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" /> 
		                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" /> 
		                    </conditions> 
		                    <action type="Rewrite" url="' . PATH . 'pinegrap/router.php" />
		                </rule> 
		            </rules> 
		        </rewrite>
		    </system.webServer>
		</configuration>');
		    }
		
		// else if Nginx is being used, generate nginx.conf.sample
		} else if (stristr($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
		
		    $nginx_conf = '# PineGrap Nginx sample config
		# Add this inside your server { } block
		
		# Deny access to data directory
		location ^~ ' . PATH . 'data/ {
		    deny all;
		    return 403;
		}
		
		# Route all requests through router.php if file/folder not found
		location ' . PATH . ' {
		    try_files $uri $uri/ ' . PATH . 'pinegrap/router.php;
		}
		';
		    $nginx_conf_path = dirname(__FILE__) . '/../../nginx.conf.sample';
		    if (!file_exists($nginx_conf_path)) {
		        file_put_contents($nginx_conf_path, $nginx_conf);
		    }
		}
		
		// Update RewriteBase in .htaccess if installed in sub-directory (Apache)
		if ((PATH != '/') && (stristr($_SERVER['SERVER_SOFTWARE'], 'iis') === false) && (stristr($_SERVER['SERVER_SOFTWARE'], 'nginx') === false)) {
		
		    $htaccess_content = @file_get_contents(HTACCESS_FILE_PATH);
		    if ($htaccess_content !== false) {
		        $handle = @fopen(HTACCESS_FILE_PATH, 'w');
		        if ($handle == true) {
		            $htaccess_content = str_replace('#RewriteBase /~example/', 'RewriteBase ' . PATH, $htaccess_content);
		            @fwrite($handle, $htaccess_content);
		            @fclose($handle);
		        }
		    }
		
		// Update rule action in web.config if installed in sub-directory (IIS)
		} elseif ((PATH != '/') && (stristr($_SERVER['SERVER_SOFTWARE'], 'iis') == true)) {
		
		    $webconfig_content = @file_get_contents(dirname(__FILE__) . '/../../web.config');
		    if ($webconfig_content !== false) {
		        $handle = @fopen(dirname(__FILE__) . '/../../web.config', 'w');
		        if ($handle == true) {
		            $webconfig_content = str_replace('<action type="Rewrite" url="/pinegrap/router.php" />', '<action type="Rewrite" url="' . PATH . 'pinegrap/router.php" />', $webconfig_content);
		            @fwrite($handle, $webconfig_content);
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

			// Get MySQL version in order to determine if we should set the engine
			// for new tables that we create.  The engine property is not supported
			// in old MySQL version so we need to make sure that we don't add it
			// in order to avoid a query error during udpdate.
			$mysql_version = db_value("SELECT VERSION()");

			$mysql_version_parts = explode('.', $mysql_version);

			$mysql_major_version = $mysql_version_parts[0];

			$mysql_minor_version = $mysql_version_parts[1];

			// If the MySQL version is at least 4.1 then prepare engine value.
			// Engine support was actually added in MySQL 4.0.18, however we
			// don't want to deal with checking the maintenance version, so we are just
			// going to require 4.1 and higher.  No one but us is using earlier versions anyway.
			// We define it as a constant so that we can have access to it
			// in all update functions below.
			if (

			(

			($mysql_major_version == 4) && ($mysql_minor_version >= 1)) || ($mysql_major_version >= 5)) {

				define('ENGINE', ' ENGINE=MyISAM');

				// Otherwise MySQL version is before 4.1, so do not include engine property
				
			}
			else {

				define('ENGINE', '');

			}

			$installed_version_key = get_version_key($installed_version, $versions);

			// Loop through all the versions in order to determine which we need to update to.
			foreach ($versions as $version_key => $version) {

				// If this version is greater than the installed version, then run update for this
				// version.
				if ($version_key > $installed_version_key) {

					$function_name = 'upgrade_to_' . str_replace('.', '_', $version['number']);

					// If there is a function for this version, then run function.
					// Some versions do not need any db updates, so there might not be a function.
					if (function_exists($function_name)) {

						$function_name();

					}

					db("UPDATE config SET version = '" . $version['number'] . "'");

				}

			}

		}

		log_activity(lang('The software was installed'), $liveform->get_field_value('admin_username'));

		// if an e-mail should be sent to the administrator, then send e-mail
		if ($liveform->get_field_value('send_email') != 'false') {

			// prepare confirmation e-mail to administrator
			$to = $liveform->get_field_value('admin_email_address');

			$subject = lang(array('string'=>'Pinegrap: Installation Complete for {var:1}','vars'=>$_SERVER['HTTP_HOST']));
			


			// prepare hidden password
			for ($i = 1;$i <= mb_strlen($liveform->get_field_value('admin_password'));$i++) {

				$hidden_password .= '*';

			}

			$body =

			lang('Congratulations, your Pinegrap installation is complete!  You may find your login information below') . ':
' . lang('Email') . ': ' . $liveform->get_field_value('admin_email_address') . '
' . lang('Password') . ': ' . $hidden_password . '
' . lang('Login') . ':
http://' . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/';

			$headers .= 'From: noreply@kodpen.com' . "\r\n";

			// send e-mail to administrator
			@mb_send_mail($to, $subject, $body, $headers);

		}

		// output confirmation
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

							<h4 class="text-success text-center"><span class="material-icons" style="line-height:1em;font-size:4em;">check_circle</span><br/>' . lang('Congratulations, the installation is complete!') . '</h4>
							<p>' . lang('Your database login information has been stored in the config.php file in the software directory. If you need to change the database login information in the future, you will need to update the config.php file.') . '</p>
							<p>' . lang('A confirmation e-mail has been sent to your e-mail address.  If you do not receive the confirmation e-mail, then e-mail is probably not configured correctly for your website.  There are features that rely on e-mail (i.e. e-mailing pages, creating users, and etc.), so it is important that you configure e-mail to work.') . '</p>
						</div>
						<div class="card-footer">
							<div class="text-center"><a class="btn" href="../" class="button_primary">' . lang('Continue') . '<span class="ms-1 material-icons">arrow_forward</span></a></div>
						</div>
					</div>
				</div>
		    </div>
		</main>' . get_footer();

		$liveform->remove_form('install');

		$_SESSION['software']['install']['reinstall'] = false;

	}

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

	header('Location: http://' . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/');

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

	// if a username and password are set in the session, then continue to check if username and password are valid and if user is an administrator
	if ((isset($_SESSION['sessionusername']) == true) && (isset($_SESSION['sessionpassword']) == true)) {

		$query =

		"SELECT user_id

			FROM user

			WHERE

				(user_role = '0')

				AND (user_username = '" . escape($_SESSION['sessionusername']) . "')

				AND (user_password = '" . escape($_SESSION['sessionpassword']) . "')";

		$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));

		// if a user was found, then return true
		if (mysqli_num_rows($result) > 0) {

			return true;

		}

	}

	// if we got here then an administrator is not logged in, so return false
	return false;

}

function parse_mysql_dump($url)
 {

	$file_content = file($url);


	$query = '';
	

	foreach ($file_content as $sql_line) {
		
		$tsl = trim($sql_line);

		if (($tsl != '') && (mb_substr($tsl, 0, 2) != '--') && (mb_substr($tsl, 0, 1) != '#')) {
			
			$query .= $sql_line;
			
			if (preg_match("/;\s*$/", $sql_line)) {
				
				$result = mysqli_query(db::$con, trim($query));
				
				if (!$result) {

					return false;

				}
				
				$query = '';

			}

		}


	}
	
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


function get_tables() {

	return array(
		'aclfolder',
		'ad_regions',
		'address_book',
		'ads',
		'affiliate_sign_up_form_pages',
		'allow_new_comments_for_items',
		'applied_gift_cards',
		'arrival_dates',
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
		'excluded_transit_dates',
		'express_order_pages',
		'files',
		'folder',
		'folder_view_pages',
		'form_data',
		'form_field_options',
		'form_fields',
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
		'menu_items',
		'menus',
		'messages',
		'next_order_number',
		'offer_actions',
		'offer_actions_shipping_methods_xref',
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
		'custom_apps',
		'notifications',
		'iyzipay_3ds_state',
		'order_refunds',
		'product_barcodes',
		'shared_components',
		'perf_log',
		'waf_log',
		'waf_rate',
		'waf_ip_reputation',
		'waf_log',
	);

}

// Add custom shipping form support to express order


function upgrade_to_2017_2_1() {

	db("ALTER TABLE express_order_pages ADD shipping_form TINYINT UNSIGNED NOT NULL DEFAULT 0");

	db("ALTER TABLE express_order_pages DROP shipping_address_and_arrival_page_id");

	// Add new form type property to fields because we will need to distiguish between shipping
	// and billing fields for express order
	db("ALTER TABLE form_fields ADD form_type ENUM('', 'custom', 'product', 'shipping', 'billing') NOT NULL DEFAULT ''");

	db("ALTER TABLE form_fields ADD INDEX form_type (form_type)");

	// Get all fields in order to set form type
	

	$fields = db_items(

	"SELECT

			form_fields.id,

			form_fields.product_id,

			page.page_type

		FROM form_fields

		LEFT JOIN page ON form_fields.page_id = page.page_id

		ORDER BY form_fields.id");

	foreach ($fields as $field) {

		$field['form_type'] = '';

		if ($field['page_type'] == 'custom form') {

			$field['form_type'] = 'custom';

		}
		else if ($field['page_type'] == 'shipping address and arrival') {

			$field['form_type'] = 'shipping';

		}
		else if (

		$field['page_type'] == 'billing information' or $field['page_type'] == 'express order') {

			$field['form_type'] = 'billing';

		}
		else if ($field['product_id']) {

			$field['form_type'] = 'product';

		}

		db(

		"UPDATE form_fields

			SET form_type = '" . $field['form_type'] . "'

			WHERE id = '" . $field['id'] . "'");

	}

}

// Update forgot password feature to send token link in email instead of temp password.


function upgrade_to_2017_2_2() {

	// Try to find a change random password page, so we can update field names
	$page_id = db("SELECT page_id FROM page WHERE page_type = 'change random password' LIMIT 1");

	// Change page_type enum from change random password to set password for in page table
	db("ALTER TABLE page CHANGE page_type page_type ENUM( 'standard', 'change password', 

		'set password', 'email a friend', 'error', 'folder view', 'forgot password', 'login', 

		'logout', 'photo gallery', 'membership confirmation', 'membership entrance', 'my account', 

		'my account profile', 'email preferences', 'view order', 'update address book', 'custom form', 

		'custom form confirmation', 'form list view', 'form item view', 'form view directory', 

		'calendar view', 'calendar event view', 'catalog', 'catalog detail', 'express order', 

		'order form', 'shopping cart', 'shipping address and arrival', 'shipping method', 

		'billing information', 'order preview', 'order receipt', 'registration confirmation', 

		'registration entrance', 'search results', 'affiliate sign up form', 

		'affiliate sign up confirmation', 'affiliate welcome') NOT NULL DEFAULT 'standard'");

	// Find root folder to assign to set-pass page
	$root_folder = db("SELECT folder_id FROM folder WHERE folder_parent = '0' LIMIT 1");

	// If page id was found, rename change random password to set-pass
	// And change folder to root
	if ($page_id != '') {

		db(

		"UPDATE page SET 

				page_type = 'set password', 

				page_name = 'set-pass', 

				page_folder = '" . $root_folder . "' 

			WHERE page_id = '" . $page_id . "' LIMIT 1");

	}

	// If a change random password page was found and there is a custom layout, then update custom layout
	if ($page_id and file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {

		// Get the custom layout content
		$content = file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');

		// If a custom layout file was found, then continue to update it
		if ($content) {

			// Backup old file
			copy(

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php',

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.bak.php');

			// Remove password verify
			$content = str_replace('<input type="password" name="new_password_verify" id="new_password_verify" placeholder="Confirm New Password*">', '', $content);

			// Update custom layout
			file_put_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php', $content);

		}

	}

	// Try to find a forgot password page, so we can update button to send email
	$page_id = db("SELECT page_id FROM page WHERE page_type = 'forgot password' LIMIT 1");

	// If a forgot password page was found and there is a custom layout, then update custom layout
	if ($page_id and file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {

		// Get the custom layout content
		$content = file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');

		// If a custom layout file was found, then continue to update it
		if ($content) {

			// Backup old file
			copy(

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php',

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.bak.php');

			$new_label = 'Send Email';

			// Search for two common old labels and replace with new label.
			$content = str_replace('Email Temporary Password', $new_label, $content);

			$content = str_replace('Send Password', $new_label, $content);

			// Update custom layout
			file_put_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php', $content);

		}

	}

	// Alter table to handle token to be emailed for reset password. We purposely allow NULL for
	// the token column, so that we can use a UNIQUE index. Most of the users won't have a token
	// at any given time, but MySQL allows a UNIQUE index for multiple NULL records (does not allow
	// that for empty string).
	db(

	"ALTER TABLE user

		DROP user_random_password,

		ADD token VARCHAR(64),

		ADD token_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD UNIQUE (token)");

}

// Add feature to allow the commerce manager to set whether the key code or offer code should be
// reported on, for each key code.


function upgrade_to_2017_2_3() {

	db("ALTER TABLE key_codes ADD report ENUM('key_code', 'offer_code') NOT NULL DEFAULT 'key_code'");

	// Update existing key codes so that report is set to offer code for single-use key codes,
	// because that was the previous way that we used to determine if an offer code should be
	// reported on.
	db("UPDATE key_codes SET report = 'offer_code' WHERE single_use = '1'");

}

// Add real-time delivery date feature.


function upgrade_to_2017_2_4() {

	// Add new real-time rate column because the service column is now going to be used for both
	// real-time rates and delivery dates.
	db("ALTER TABLE shipping_methods ADD realtime_rate TINYINT UNSIGNED NOT NULL DEFAULT 0");

	db("UPDATE shipping_methods SET realtime_rate = '1' WHERE service != ''");

	db("ALTER TABLE shipping_methods CHANGE service service ENUM('', 'usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT ''");

	db("ALTER TABLE shipping_rates CHANGE service service ENUM('usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT 'usps_priority'");

	db(

	"ALTER TABLE config

		ADD ups TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD fedex TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD fedex_key VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_password VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_account VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_meter VARCHAR(100) NOT NULL DEFAULT ''");

	// Enable new ups check box if site was using UPS.
	db("UPDATE config SET ups = '1' WHERE ups_key != ''");

	// Create delivery date cache table in order to minimize requests to carriers.
	db(

	"CREATE TABLE shipping_delivery_dates (

			id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

			service ENUM('usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT 'usps_priority',

			zip_code VARCHAR(50) NOT NULL DEFAULT '',

			ship_date DATE NOT NULL DEFAULT '0000-00-00',

			delivery_date DATE NOT NULL DEFAULT '0000-00-00',

			timestamp INT UNSIGNED NOT NULL DEFAULT 0,

			INDEX combination (service, zip_code, ship_date),

			INDEX timestamp (timestamp)

		)" . ENGINE);

}

// Add handling features in order to determine, more precisely, when a shipment is shipped out.


function upgrade_to_2017_2_5() {

	db(

	"ALTER TABLE shipping_methods

		ADD handle_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_mon TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_tue TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_wed TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_thu TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_fri TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_sat TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_sun TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_mon TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_tue TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_wed TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_thu TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_fri TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_sat TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_sun TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD end_of_day TIME NOT NULL DEFAULT '00:00:00'");

	// Update existing shipping methods so weekdays are enabled for both handling and shipping.
	db(

	"UPDATE shipping_methods SET

			handle_mon = '1',

			handle_tue = '1',

			handle_wed = '1',

			handle_thu = '1',

			handle_fri = '1',

			ship_mon = '1',

			ship_tue = '1',

			ship_wed = '1',

			ship_thu = '1',

			ship_fri = '1'");

}

// Add feature to allow only certain countries to require zip code.  Also, adding indexes to increase
// performance.


function upgrade_to_2017_2_6() {

	db(

	"ALTER TABLE countries

		ADD zip_code_required TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX code (code),

		ADD INDEX default_selected (default_selected)");

	// Update certain countries so that zip code is required.
	// Source: https://www.ups.com/worldshiphelp/WS16/ENU/AppHelp/Codes/Countries_Territories_Requiring_Postal_Codes.htm
	

	$zip_code_required_countries = array(
		'DZ',
		'AR',
		'AM',
		'AU',
		'AT',
		'AZ',
		'A2',
		'BD',
		'BY',
		'BE',
		'BA',
		'BR',
		'BN',
		'BG',
		'CA',
		'IC',
		'CN',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EN',
		'EE',
		'FO',
		'FI',
		'FR',
		'GE',
		'DE',
		'GR',
		'GL',
		'GU',
		'GG',
		'HO',
		'HU',
		'IN',
		'ID',
		'IL',
		'IT',
		'JP',
		'JE',
		'KZ',
		'KR',
		'KO',
		'KG',
		'LV',
		'LI',
		'LT',
		'LU',
		'MK',
		'MG',
		'M3',
		'MY',
		'MH',
		'MQ',
		'YT',
		'MX',
		'MN',
		'ME',
		'NL',
		'NZ',
		'NB',
		'NO',
		'PK',
		'PH',
		'PL',
		'PO',
		'PT',
		'PR',
		'RE',
		'RU',
		'SA',
		'SF',
		'CS',
		'SG',
		'SK',
		'SI',
		'ZA',
		'ES',
		'LK',
		'NT',
		'SX',
		'UV',
		'VL',
		'SE',
		'CH',
		'TW',
		'TJ',
		'TH',
		'TU',
		'TN',
		'TR',
		'TM',
		'VI',
		'UA',
		'GB',
		'US',
		'UY',
		'UZ',
		'VA',
		'VN',
		'WL',
		'YA'
	);

	foreach ($zip_code_required_countries as $country) {

		db("UPDATE countries SET zip_code_required = '1' WHERE code = '" . e($country) . "'");

	}

}

// Add index for order reference code in order to improve performance.  When an order is created
// a lookup is done in order to check that the new reference code is unique.  Previously, when a
// site had millions of orders, that lookup could become slow.  That might have caused the customer
// to experience a 1-2 second delay after adding an item to the cart.


function upgrade_to_2017_2_7() {

	// Get orders that do not have a reference code, in order to add a reference code, so that we
	// can add a unique index further below.  We noticed that one site had a bunch of orders
	// with no reference code.  We are not sure why.
	$orders = db_items("SELECT id FROM orders WHERE reference_code = ''");

	if ($orders) {

		// Add a regular index temporarily for performance reasons.
		db("ALTER TABLE orders ADD INDEX reference_code (reference_code)");

		// Loop through the orders in order to insert a reference code.
		foreach ($orders as $order) {

			db(

			"UPDATE orders SET reference_code = '" . e(generate_order_reference_code()) . "'

				WHERE id = '" . e($order['id']) . "'");

		}

		// Remove the regular index, because we don't need it anymore and we want to add a unique
		// index below.
		db("ALTER TABLE orders DROP INDEX reference_code");

	}

	// Now, add the unique index that we want.
	db("ALTER TABLE orders ADD UNIQUE reference_code (reference_code)");

}

// Add order shipped auto campaign feature


function upgrade_to_2017_2_8() {

	db(

	"ALTER TABLE email_campaign_profiles

		ADD purpose ENUM('commercial', 'transactional') NOT NULL DEFAULT 'commercial',

		CHANGE action action ENUM('calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent', 'order_abandoned', 'order_completed', 'order_shipped', 'product_ordered') NOT NULL DEFAULT 'calendar_event_reserved'");

	db(

	"ALTER TABLE email_campaigns

		ADD purpose ENUM('commercial', 'transactional') NOT NULL DEFAULT 'commercial',

		CHANGE action action ENUM('', 'calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent', 'gift_card_ordered', 'order_abandoned', 'order_completed', 'order_shipped', 'product_ordered') NOT NULL DEFAULT ''");

	db("UPDATE email_campaigns SET purpose = 'transactional' WHERE action = 'gift_card_ordered'");

}

// Add feature to allow ul class for menu to be set.


function upgrade_to_2017_2_9() {

	db("ALTER TABLE menus ADD class VARCHAR(255) NOT NULL DEFAULT ''");

}

// Add notes feature to key codes.


function upgrade_to_2017_2_10() {

	db("ALTER TABLE key_codes ADD notes MEDIUMTEXT NOT NULL DEFAULT ''");

}

// Add MailChimp feature to sync products and orders.


function upgrade_to_2017_2_11() {

	db("ALTER TABLE config

		ADD mailchimp TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_key VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_list_id VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_store_id VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_sync_running TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_limit INT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_automation TINYINT UNSIGNED NOT NULL DEFAULT 0");

	// Set defaults so job will sync the past 3 years of orders, and will sync a max of 200 orders
	// each time it runs.
	db("UPDATE config SET mailchimp_sync_days = '1095', mailchimp_sync_limit = '200'");

	db("ALTER TABLE orders

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_error TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

	// If there is not already an index for billing_email_address, then add one.  We noticed that
	// a few sites had customizations where there was already an index for that column.
	// The index for the billing email address is necessary because we need to look up the total
	// number of orders and total revenue for a customer, in order to send it to MailChimp.
	if (!db("SHOW INDEX FROM orders WHERE Column_name = 'billing_email_address'")) {

		db("ALTER TABLE orders ADD INDEX billing_email_address (billing_email_address)");

	}

	db("ALTER TABLE product_groups

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX timestamp (timestamp),

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

	db("ALTER TABLE products

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

}

// Add feature to allow multiple products for offer rules.


function upgrade_to_2017_2_12() {

	db("

		CREATE TABLE offer_rules_products_xref

		(

			offer_rule_id INT UNSIGNED NOT NULL DEFAULT 0,

			product_id INT UNSIGNED NOT NULL DEFAULT 0,

			INDEX offer_rule_id (offer_rule_id),

			INDEX product_id (product_id)

		)" . ENGINE);

	// Get existing offer rules that have a required product in order to move info to new table.
	$offer_rules = db_items("

		SELECT id, required_product_id FROM offer_rules WHERE required_product_id != '0'");

	// If there are offer rules, the move info to new table.
	if ($offer_rules) {

		foreach ($offer_rules as $offer_rule) {

			db("

				INSERT INTO offer_rules_products_xref (

					offer_rule_id,

					product_id)

				VALUES (

					'" . e($offer_rule['id']) . "',

					'" . e($offer_rule['required_product_id']) . "')");

		}

	}

	// Remove the old product column that is no longer necessary.
	db("ALTER TABLE offer_rules DROP required_product_id");

}

// Add custom layout support for form item views.


function upgrade_to_2017_2_13() {

	// We have to run the query below because even though we did not support custom layout for form
	// item views in the past, a form item view might have been set to custom in the DB, if it was
	// duplicated from another page.  After the update, we want all form item views to have a
	// system layout, like before.
	

	db("UPDATE page SET layout_type = 'system', layout_modified = '0'

		WHERE page_type = 'form item view'");

}

//subsription key
// Add language option and default theme option for software.
//Theme is Depricated, we are using browser based light/dark theme now.
function upgrade_to_2019_1_1() {

	db("ALTER TABLE config
	ADD subscription_key VARCHAR(256) NOT NULL DEFAULT '',
	ADD software_theme ENUM('coloron','darkon','lighton') NOT NULL DEFAULT 'coloron'");

}

// Add developer pass pin to All Users (default pin:0000)
//Developers define to config.php to page name and an unlock pin to lock pages.
//If page name define like below, users redirect to developer_lock.php.
//Software ask's pin code to unlock and access to page.
//If user enter pin  correct, software redirect user to page try to access and no redirect developer_lock.php until Pin code is change.
function upgrade_to_2019_1_2() {

	db("ALTER TABLE user ADD user_devpasspin VARCHAR(4) NOT NULL DEFAULT '0000'");

}

// Here we integrate payment gateway iyzipay api key and secret key
function upgrade_to_2019_1_3() {

	db("ALTER TABLE config 

		ADD ecommerce_iyzipay_api_key VARCHAR(255) NOT NULL DEFAULT '',

		ADD ecommerce_iyzipay_secret_key VARCHAR(255) NOT NULL DEFAULT ''");

}

// Than update gateway Select and include iyzipay option
function upgrade_to_2019_1_4() {

	db("ALTER TABLE config CHANGE ecommerce_payment_gateway ecommerce_payment_gateway ENUM('', 'Authorize.Net', 'ClearCommerce', 'First Data Global Gateway', 'PayPal Payflow Pro', 'PayPal Payments Pro', 'Sage', 'Stripe','Iyzipay') NOT NULL DEFAULT ''");

}

function upgrade_to_2019_1_5() {

	db("ALTER TABLE config ADD ecommerce_iyzipay_installment ENUM('1', '2', '3', '6', '9', '12') NOT NULL DEFAULT '1'");

}

function upgrade_to_2019_1_6() {

	db("ALTER TABLE config ADD ecommerce_iyzipay_threeds TINYINT UNSIGNED NOT NULL DEFAULT 0");

}

function upgrade_to_2019_2_3() {

	db("ALTER TABLE config ADD time_format ENUM('twelve_hours', 'twenty_four_hours') NOT NULL DEFAULT 'twelve_hours'");

}

//The Future lets you upload/select multiple images and use it in your product detail page both product and product group.
function upgrade_to_2020_1_1() {

	db("

	   CREATE TABLE products_images_xref

	   (

		   product INT UNSIGNED NOT NULL DEFAULT 0,

		   file_name VARCHAR(255) DEFAULT ''

	   )" . ENGINE);

	db("

	   CREATE TABLE product_groups_images_xref

	   (

		   product_group INT UNSIGNED NOT NULL DEFAULT 0,

		   file_name VARCHAR(255) DEFAULT ''

	   )" . ENGINE);

}

function upgrade_to_2020_1_5() {

	//this update contains some updates installment options for submit order,order checkout and view order pages to show instalment prices and amounts
	//payment installment is if there is installment and how many installments
	db("ALTER TABLE orders ADD payment_installment INT UNSIGNED NOT NULL DEFAULT 1");

	//and installment charge is increase of installments charges.
	db("ALTER TABLE orders ADD installment_charges INT UNSIGNED NOT NULL DEFAULT 0");

	//it is not out of stock message, it is for new out of stock products fallow method. if this is return to 1 from submit_order page than this product, displayed on out of stock page and welcome page
	db("ALTER TABLE products

	ADD out_of_stock INT UNSIGNED NOT NULL DEFAULT 0,

	ADD out_of_stock_timestamp INT UNSIGNED NOT NULL DEFAULT 0");

}

// who is online.
function upgrade_to_2020_1_6() {

	db("ALTER TABLE user ADD user_online_timestamp INT UNSIGNED NOT NULL DEFAULT 0");

}

//default product code
//this is product image code template for Add Product pages.
//after product image code loop developed and products support multiple image selection this example may help users to add faster products
function upgrade_to_2020_1_7() {

	db("ALTER TABLE config ADD product_image_code_template TEXT");

}

function upgrade_to_2020_2_3() {

	db("ALTER TABLE config MODIFY subscription_key VARCHAR(256)");

}
// Dashboard widget screen options for the welcome page
function upgrade_to_2021_1_3() {
	// Create dashboard table with main configuration
	db("
		CREATE TABLE dashboard (
			main_weather_location VARCHAR(255) DEFAULT 'london'
		)" . ENGINE
	);

	// Add visual configuration columns
	db("ALTER TABLE dashboard ADD bg_image VARCHAR(256) NOT NULL DEFAULT 'bg_metapolis'");
	db("ALTER TABLE dashboard ADD widget_themes ENUM('blur_one', 'blur_two', 'blur_three') NOT NULL DEFAULT 'blur_one'");
}

//The Future lets you widget activate/deactivate or order them.
function upgrade_to_2021_1_8() {

	//insert into dashboard order_widget default all widgets active and default order.
	db("ALTER TABLE dashboard ADD order_widgets VARCHAR(256) NOT NULL DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19'");

}

// Yahoo weather api integration
function upgrade_to_2021_1_9() {
	db("ALTER TABLE dashboard ADD weather_app_id VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE dashboard ADD weather_key VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE dashboard ADD weather_secret VARCHAR(256) DEFAULT ''");
}

// Note Widget
function upgrade_to_2021_1_11() {
	db("ALTER TABLE dashboard ADD notes_widget_data TEXT");
	$query = "UPDATE dashboard
            SET
            notes_widget_data = 'PGgxPkNvbW1vbiBub3RlIGFyZWEgZm9yIHNpdGUgPHN0cm9uZyBzdHlsZT0iY29sb3I6IHJnYigxMDIsIDE4NSwgMTAyKTsiPmFkbWluaXN0cmF0b3JzPC9zdHJvbmc+PC9oMT48cD48YnI+PC9wPjxwPjxzdHJvbmc+YWRkPC9zdHJvbmc+IG9yIDxzdHJvbmc+ZWRpdDwvc3Ryb25nPiBub3RlcyBoZXJlLjwvcD48cD5vciBsaXN0cyBsaWtlOjwvcD48b2w+PGxpPkxpc3QgY29udGVudDwvbGk+PGxpPkFub3RoZXIgbGlzdCBjb250ZW50PC9saT48L29sPjx1bD48bGk+U3ViIGxpc3QgY29udGVudDwvbGk+PGxpPkFub3RoZXIgc3ViIGxpc3QgY29udGVudDwvbGk+PC91bD4='
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}
function upgrade_to_2021_1_12() {
	db("ALTER TABLE dashboard CHANGE widget_themes widget_themes ENUM('blur_one', 'blur_two','blur_three','classic')");
}
function upgrade_to_2021_1_14() {
	db("ALTER TABLE config ADD custom_css TEXT");
	$query = "UPDATE config
            SET
            custom_css = '/* Custom Stylesheet that can overwrite backend CSS files */'
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

function upgrade_to_2021_3() {
	db("ALTER TABLE user ADD secret_key VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE user ADD secret_key_iv VARBINARY(32) DEFAULT ''");
}
// Custom Applications allow updating website data with a REST API type method.
function upgrade_to_2021_4_1() {

	db("
  CREATE TABLE custom_apps
  (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
  )" . ENGINE);

	db("ALTER TABLE custom_apps ADD create_user_id INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE custom_apps ADD name VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD type VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD method ENUM('POST', 'GET')");
	db("ALTER TABLE custom_apps ADD api_key VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD api_key_iv VARBINARY(32) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD timestamp INT UNSIGNED NOT NULL DEFAULT 0");
}
//software gained pin pages to menu features with this upgrade.
function upgrade_to_2021_4_3() {
	db("ALTER TABLE user ADD selected_appmenu_items_array TEXT");
	$query = "UPDATE user
            SET
            selected_appmenu_items_array = 'default'
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}
//Notification system is included in the software. Site activities will now generate notifications.
function upgrade_to_2021_4_4() {

	db("
  CREATE TABLE notifications
  (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
  )" . ENGINE);

	db("ALTER TABLE notifications ADD action VARCHAR(100) DEFAULT ''");
  	db("ALTER TABLE notifications ADD type VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD title VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE notifications ADD product_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD form_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD order_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD order_total VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD user VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD readed INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE notifications ADD timestamp INT UNSIGNED NOT NULL DEFAULT 0");
}
//Notifications gained link and comment tracking features with this upgrade.
function upgrade_to_2021_4_7() {
	db("ALTER TABLE notifications ADD comment_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD send_to VARCHAR(500) DEFAULT ''");
}
// we add media support to rss fields.
function upgrade_to_2022_1() {
	db("ALTER TABLE form_fields CHANGE rss_field rss_field ENUM('category', 'title','description','media')");
}
// we add image connect to contact support.
// if textbox or textarea its save in image and use form data
// if its file upload input, use file id.
function upgrade_to_2022_1_1() {
	db("ALTER TABLE contacts ADD image TEXT");
	db("ALTER TABLE contacts ADD file_id INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE form_fields CHANGE contact_field contact_field ENUM('','salutation','first_name','last_name','suffix','nickname','company','title','department','office_location','business_address_1','business_address_2','business_city','business_state','business_country','business_zip_code','business_phone','business_fax','home_address_1','home_address_2','home_city','home_state','home_country','home_zip_code','home_phone','home_fax','mobile_phone','email_address','website','lead_source','opt_in','description','affiliate_name','image')");
}

// google strutured data is manage by site managers
// default is disabled
function upgrade_to_2022_1_2() {
	db("ALTER TABLE config
	ADD strutured_data TINYINT UNSIGNED NOT NULL DEFAULT 0");
}
// we add option to control visual effects
// defaul enabled
function upgrade_to_2022_1_7() {
	db("ALTER TABLE config
	ADD advanced_visual_effects INT UNSIGNED NOT NULL DEFAULT 1");
}
//auto backup feature is initialize in this upgrade.
//cron job or with any request can create a simple backup or update last auto backup.
// last_software_auto_backup using for check last auto backup date, because auto backup can run only once a day for performance and security reasons.
function upgrade_to_2022_1_8() {
	db("ALTER TABLE config
	ADD last_software_auto_backup INT UNSIGNED NOT NULL DEFAULT 0");
}
//We used to store software subscription information with "subscription_key" but now it will be stored as "subscription_id". (10 digit a-z 1-9)
//In addition, the software license key will be stored with the "subscription_key". (16 digit 1-9)
function upgrade_to_2022_1_9() {
	$query = "UPDATE config SET subscription_id = subscription_key, subscription_key = ''";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

}

// We remove default values for these columns, because mysql new releases do not support default for TEXT.
// new upgrades do not contain default values but old upgraded sites may contain defaults.
function upgrade_to_2022_2() {
	db("ALTER TABLE config ALTER custom_css  DROP DEFAULT");
	db("ALTER TABLE dashboard ALTER notes_widget_data  DROP DEFAULT");
	db("ALTER TABLE user ALTER selected_appmenu_items_array  DROP DEFAULT");
}

// local_sale_history table log all local orders with barcode scanned sales.
// We record online sales and local sales separately.
// also we create a table for local sale items and connect them with local sale id
function upgrade_to_2022_2_1() {
	db(" CREATE TABLE local_sale_history ( id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY )" . ENGINE);
  	db("ALTER TABLE local_sale_history ADD sale_date INT UNSIGNED NOT NULL DEFAULT 0");
	db("CREATE TABLE local_sale_history_items (
		id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		local_sale_id INT UNSIGNED NOT NULL DEFAULT 0, 
		product_id INT UNSIGNED NOT NULL DEFAULT 0,
		product_name varchar(100) NOT NULL DEFAULT '',
		quantity INT UNSIGNED NOT NULL DEFAULT 0,
		price INT UNSIGNED NOT NULL DEFAULT 0,
		tax INT UNSIGNED NOT NULL DEFAULT 0
	)" . ENGINE);
}


// a new menu and pinning menu has been created.
// pin menu items from the old build cannot fit in the new pin area. So we reset the menu and select the most functional links.
// Users will be able to pin links up to a certain number if they wish.
function upgrade_to_2022_2_2() {
	db("UPDATE user SET selected_appmenu_items_array = 'default' WHERE selected_appmenu_items_array != 'default'");
}

//enable parasut configuration.
// this is a e-invoice website used at Turkey.
//you can merge order and contact data at parasut.com with 2 excel file exported from [all orders] page.
function upgrade_to_2022_3_2() {
	db("ALTER TABLE config ADD parasut_tc_in_field ENUM('do not use', 'custom_field_1', 'custom_field_2') NOT NULL DEFAULT 'do not use'");
	db("ALTER TABLE config ADD enable_parasut INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE orders ADD parasut_exported INT UNSIGNED NOT NULL DEFAULT 0");
}

function upgrade_to_2023_1() {
	db("ALTER TABLE config ADD enable_iyzipay_protected_currency INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD iyzipay_protected_currency_code VARCHAR(255) NOT NULL DEFAULT ''");

}

function upgrade_to_2023_2_1() {
	db("ALTER TABLE comments ADD rating TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE page ADD comments_rating TINYINT UNSIGNED NOT NULL DEFAULT 0");
}




function upgrade_to_2026_1() {
	db("ALTER TABLE short_links ADD file_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE short_links MODIFY destination_type ENUM('page', 'product_group', 'product', 'url', 'file') NOT NULL DEFAULT 'page'");
	db("ALTER TABLE config ADD indexnow_key VARCHAR(256) NOT NULL DEFAULT ''");
	db("CREATE TABLE iyzipay_3ds_state (
    	id INT AUTO_INCREMENT PRIMARY KEY,
    	conversation_id VARCHAR(64) NOT NULL,
    	payment_id VARCHAR(64) DEFAULT NULL,
    	order_id INT NOT NULL,
    	subtotal_cents INT NOT NULL,
    	discount_cents INT DEFAULT 0,
    	installment_charge_cents INT DEFAULT 0,
    	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    	INDEX (conversation_id),
    	INDEX (payment_id),
    	INDEX (order_id)
	)" . ENGINE);
	db("ALTER TABLE config ADD ecommerce_troy TINYINT(4) NOT NULL DEFAULT 1");
	db("ALTER TABLE user MODIFY user_devpasspin VARCHAR(255) NOT NULL DEFAULT ''");
	db("ALTER TABLE custom_apps ADD permissions JSON NOT NULL");
}

function upgrade_to_2026_1_1() {
	// Bot filtering: allowed bots list + block unknown bots toggle.
	$default_bots = "googlebot\nbingbot\nslurp\nduckduckbot\nbaiduspider\nyandexbot\nsogou\nexabot\nfacebot\nia_archiver";
	db("ALTER TABLE config ADD allowed_bots TEXT DEFAULT NULL");
	db("ALTER TABLE config ADD block_unknown_bots TINYINT(1) NOT NULL DEFAULT 0");
	db("UPDATE config SET allowed_bots = '" . escape($default_bots) . "'");
}

function upgrade_to_2026_1_2() {
	// Add composite index on visitors table to speed up the 30-minute deduplication
	// query that runs on every new session (get_page.php).
	db("ALTER TABLE visitors ADD INDEX idx_dedup (ip_address, stop_timestamp)");
}

function upgrade_to_2026_1_3() {
	// Add hash columns for fast, single-query API authentication (no decrypt loops).
	// user.secret_key_hash     → WHERE lookup instead of decrypt-all-users
	// custom_apps.api_key_hash → WHERE lookup instead of decrypt-all-apps

	db("ALTER TABLE user ADD COLUMN secret_key_hash VARCHAR(64) DEFAULT NULL");
	db("ALTER TABLE user ADD INDEX idx_secret_key_hash (secret_key_hash)");

	db("ALTER TABLE custom_apps ADD COLUMN api_key_hash VARCHAR(64) DEFAULT NULL");
	db("ALTER TABLE custom_apps ADD INDEX idx_api_key_hash (api_key_hash)");

	// Backfill users: compute HMAC of each plaintext secret key.
	$users = db_items("SELECT user_id, secret_key, secret_key_iv FROM user WHERE secret_key != '' AND secret_key IS NOT NULL");
	foreach ($users as $row) {
		$plain = decode_ssl_keys($row['secret_key'], $row['secret_key_iv']);
		if ($plain === '') continue;
		$hash = hash_hmac('sha256', $plain, ENCRYPTION_KEY);
		db("UPDATE user SET secret_key_hash = '" . escape($hash) . "' WHERE user_id = " . (int)$row['user_id']);
	}

	// Backfill apps: compute HMAC of each plaintext API key.
	$apps = db_items("SELECT id, api_key, api_key_iv FROM custom_apps WHERE api_key != '' AND api_key IS NOT NULL");
	foreach ($apps as $row) {
		$plain = decode_ssl_keys($row['api_key'], $row['api_key_iv']);
		if ($plain === '') continue;
		$hash = hash_hmac('sha256', $plain, ENCRYPTION_KEY);
		db("UPDATE custom_apps SET api_key_hash = '" . escape($hash) . "' WHERE id = " . (int)$row['id']);
	}
}

function upgrade_to_2026_1_4() {
	// Add type column to orders to distinguish online vs. local (in-store) orders.
	// Default is 'online' so all existing orders are automatically classified correctly.
	db("ALTER TABLE orders ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'online'");
	db("ALTER TABLE orders ADD INDEX idx_type (type)");
}

function upgrade_to_2026_1_5() {
	// local_sale_history and local_sale_history_items are no longer used.
	// Local sales are now tracked via orders.type = 'local'.
	db("DROP TABLE IF EXISTS local_sale_history_items");
	db("DROP TABLE IF EXISTS local_sale_history");
}

function upgrade_to_2026_1_6() {
	// Expand log_ip from varchar(15) to varchar(45) to support IPv6 addresses.
	// IPv4 max: 15 chars (e.g. 123.123.123.123)
	// IPv6 max: 39 chars (e.g. 2001:0db8:85a3:0000:0000:8a2e:0370:7334)
	// varchar(45) also covers IPv4-mapped IPv6 (e.g. ::ffff:192.168.1.1 = 45 chars max)
	db("ALTER TABLE log MODIFY COLUMN log_ip varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL");
	db("ALTER TABLE config ADD COLUMN ecommerce_show_product_images TINYINT DEFAULT 1");
}


function upgrade_to_2026_1_11() {
	// Add class column to menu_items to allow custom CSS classes on individual menu items.
	db("ALTER TABLE menu_items ADD COLUMN class VARCHAR(255) DEFAULT NULL");

	// Add active_item_class column to menus to allow configurable active item class per menu.
	db("ALTER TABLE menus ADD COLUMN active_item_class VARCHAR(255) DEFAULT NULL");
}

function upgrade_to_2026_1_12() {
	// Add new social networking service columns.
	// Replaces defunct AddThis (closed 2023) and Google+1 (closed 2019)
	// with modern URL-based share services.
	db("ALTER TABLE config ADD COLUMN social_networking_whatsapp TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD COLUMN social_networking_telegram TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD COLUMN social_networking_pinterest TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD COLUMN social_networking_reddit TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD COLUMN social_networking_email TINYINT UNSIGNED NOT NULL DEFAULT 0");
}

function upgrade_to_2026_1_13() {
	// Add Pay with Iyzico express checkout toggle.
	// Enables the "İyzico ile Öde" button on the order form,
	// redirecting customers to Iyzico for payment (similar to 3DS flow).
	db("ALTER TABLE config ADD COLUMN ecommerce_pay_with_iyzico TINYINT UNSIGNED NOT NULL DEFAULT 0");
}
function upgrade_to_2026_1_14() {
	// Add 'canceled' status to orders, track refunded amounts, and create refund log table.
	db("ALTER TABLE orders MODIFY COLUMN status ENUM('incomplete','complete','exported','canceled') NOT NULL DEFAULT 'incomplete'");
	db("ALTER TABLE orders ADD COLUMN refunded_amount INT NOT NULL DEFAULT 0");
	db("CREATE TABLE order_refunds (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		order_id INT UNSIGNED NOT NULL,
		amount_cents INT NOT NULL,
		refund_type VARCHAR(20) NOT NULL DEFAULT 'refund',
		transaction_id VARCHAR(255) DEFAULT NULL,
		notes TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL,
		INDEX idx_order_refunds_order_id (order_id)
	)" . ENGINE);

}

function upgrade_to_2026_1_15() {
	// Store the full base total (subtotal + tax + shipping, before installment) in 3DS state.
	// Without this, the total restored on 3DS return was missing tax/shipping.
	db("ALTER TABLE iyzipay_3ds_state ADD COLUMN base_total_cents INT NOT NULL DEFAULT 0");
}

function upgrade_to_2026_1_16() {
	// Barcode feature: per-product barcode storage and label designer settings.
	db("CREATE TABLE  product_barcodes (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id  INT UNSIGNED NOT NULL,
		barcode     VARCHAR(100) NOT NULL,
		barcode_type VARCHAR(20) NOT NULL DEFAULT 'CODE128',
		created_at  DATETIME NOT NULL,
		updated_at  DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY uq_barcode  (barcode),
		INDEX idx_product_id   (product_id)
	)" . ENGINE);

	// Config columns: enable toggle, default type, label dimensions, template JSON.
	db("ALTER TABLE config ADD COLUMN barcode_enabled TINYINT NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD COLUMN barcode_default_type VARCHAR(20) NOT NULL DEFAULT 'CODE128'");
	db("ALTER TABLE config ADD COLUMN barcode_label_width SMALLINT NOT NULL DEFAULT 60");
	db("ALTER TABLE config ADD COLUMN barcode_label_height SMALLINT NOT NULL DEFAULT 40");
	db("ALTER TABLE config ADD COLUMN barcode_label_template TEXT");
}



function upgrade_to_2026_1_17() {
	// Allow multiple barcodes per product (drop the unique-per-product constraint
	// that was accidentally included in 2026.1.16). Each barcode value stays globally unique.
	// ALTER IGNORE is used so the statement is silently skipped if the key doesn't exist.

	// Drop the unique-per-product constraint only if it exists (sites upgrading
	// from before 2026.1.15 never had it, so the DROP would fail without this guard).
	$uq_product_exists = db_value("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'product_barcodes' AND index_name = 'uq_product'");
	if ($uq_product_exists) {
		db("ALTER TABLE product_barcodes DROP INDEX uq_product");
	}
	// Parasut API V4 direct integration.
	// Adds OAuth2 credential storage to config and API object tracking to orders.
	// The existing enable_parasut toggle now also activates direct API calls
	// (e-fatura / e-irsaliye) in addition to the legacy Excel export.

	// Config: API credentials and sandbox flag
	db("ALTER TABLE config ADD parasut_client_id VARCHAR(255) NOT NULL DEFAULT ''");
	db("ALTER TABLE config ADD parasut_client_secret VARCHAR(255) NOT NULL DEFAULT ''");
	db("ALTER TABLE config ADD parasut_username VARCHAR(255) NOT NULL DEFAULT ''");
	db("ALTER TABLE config ADD parasut_password VARCHAR(255) NOT NULL DEFAULT ''");
	db("ALTER TABLE config ADD parasut_company_id VARCHAR(50) NOT NULL DEFAULT ''");
	db("ALTER TABLE config ADD parasut_use_sandbox TINYINT NOT NULL DEFAULT 0");

	// Orders: track the Parasut objects created via API for each order
	db("ALTER TABLE orders ADD parasut_contact_id VARCHAR(50) NOT NULL DEFAULT ''");
	db("ALTER TABLE orders ADD parasut_invoice_id VARCHAR(50) NOT NULL DEFAULT ''");
	db("ALTER TABLE orders ADD parasut_shipment_id VARCHAR(50) NOT NULL DEFAULT ''");	
	
	// Parasut contact linking and Turkish tax fields for contacts.
	// parasut_contact_id: links a Pinegrap contact to its counterpart in Parasut (synced on first invoice).
	// tax_number: VKN (companies) or TCKN (individuals) — required by Parasut for proper invoicing.
	// tax_office: Vergi dairesi — required for company invoices in Turkey.
	db("ALTER TABLE contacts ADD parasut_contact_id VARCHAR(50) NOT NULL DEFAULT ''");
	db("ALTER TABLE contacts ADD tax_number VARCHAR(20) NOT NULL DEFAULT ''");
	db("ALTER TABLE contacts ADD tax_office VARCHAR(100) NOT NULL DEFAULT ''");    
	
	// Extend parasut_tc_in_field ENUM to support the native contacts.tax_number column.
    db("ALTER TABLE config MODIFY parasut_tc_in_field ENUM('do not use', 'custom_field_1', 'custom_field_2', 'tax_number') NOT NULL DEFAULT 'do not use'");
}

function upgrade_to_2026_1_18() {
    // Add default Parasut product/service ID for invoice line items.
    // Parasut requires each sales_invoice_detail to reference a product/service entity.
    db("ALTER TABLE config ADD parasut_default_product_id VARCHAR(50) NOT NULL DEFAULT ''");
    // Store each product's Parasut counterpart ID so we don't recreate it on every invoice.
    db("ALTER TABLE products ADD parasut_product_id VARCHAR(50) NOT NULL DEFAULT ''");
    // Default warehouse (stock location) ID for e-irsaliye shipment document details.
    // Auto-fetched from Parasut on first use; override in settings for multi-warehouse setups.
    db("ALTER TABLE config ADD parasut_default_warehouse_id VARCHAR(50) NOT NULL DEFAULT ''");
}



function upgrade_to_2026_1_21() {
    // Visual Pinegrap Editor — tree storage + new layout type value.
    // style_tree_json holds the JSON tree edited in Visual Pinegrap Editor; style_code is always
    // the sole render source (generated from the tree on save).
    db("ALTER TABLE style ADD COLUMN style_tree_json LONGTEXT DEFAULT NULL");
    db("ALTER TABLE style MODIFY COLUMN style_layout ENUM('','one_column','one_column_email','one_column_mobile','two_column_sidebar_left','two_column_sidebar_right','three_column_sidebar_left','visual_designer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");

    // Shared Component Library — reusable tree nodes referenced across visual-designer styles.
    // A shared_ref node in any style's tree_json points to a row here by id (never by name).
    // name is UNIQUE; duplicate names are silently suffixed ([1], [2]...) at insert time.
    //
    // New-install only: the feature ships with this version, so there is no legacy data to
    // migrate. The placeholder-only invariant (shared_ref.children === []) is enforced at
    // every write site — JS save-path sanitize, server-side save_system_style sanitize, and
    // the marker-emitting _render_tree_node — so inline-expanded shared content can never
    // land in the DB.
    //
    // Note: TEXT/LONGTEXT columns cannot have a DEFAULT value in MySQL strict mode.
    // Only VARCHAR, INT, etc. can have DEFAULT ''. Omit DEFAULT for TEXT/LONGTEXT.
    db("CREATE TABLE IF NOT EXISTS shared_components (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        tree_json   LONGTEXT NOT NULL,
        tree_hash   VARCHAR(64) NOT NULL DEFAULT '',
        category    VARCHAR(100) NOT NULL DEFAULT '',
        created_by  INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uk_name (name),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function upgrade_to_2026_1_22() {
    // System widget support for shared components.
    // A shared component becomes a "system widget" when it has a "system_region_config" JSON:
    //   { "regionType": "form_list|catalog|...", "source_page": "blog",
    //     "templates": { "search": "...", "browse": "...", "item": "...", "pagination": "..." } }
    // At render time, _expand_shared_refs() detects this field and emits a
    // <!--pg-system-widget:ID--> marker instead of rendering the static tree.
    // get_page_content.php then calls _expand_system_widgets() which runs the appropriate
    // dynamic renderer (get_form_list_view.php, catalog renderer, etc.) using the compiled
    // templates. URL param namespace: sw{id}_query, sw{id}_browse_field_id, etc.
    db("ALTER TABLE shared_components ADD COLUMN system_region_config LONGTEXT DEFAULT NULL");
}

function upgrade_to_2026_1_24() {
    // Per-page custom CSS, JS, and font imports for system-layout pages (Visual Pinegrap Editor).
    // CSS and JS are stored inline (MEDIUMTEXT) and injected into the rendered page.
    // Fonts stores a JSON array of Google Fonts / @import rules.
    db("ALTER TABLE page ADD COLUMN page_custom_css  MEDIUMTEXT DEFAULT NULL");
    db("ALTER TABLE page ADD COLUMN page_custom_js   MEDIUMTEXT DEFAULT NULL");
    db("ALTER TABLE page ADD COLUMN page_custom_fonts TEXT DEFAULT NULL");
}

function upgrade_to_2026_1_25() {
    // view_files / toolbar performance: cache expensive per-image computations
    // directly on the files row so list pages don't have to recompute them.
    //
    // - image_width / image_height : cached getimagesize() result. Avoids a disk
    //   stat + JPEG/PNG header decode per image row in view_files.php (lines
    //   ~410). Especially painful on OneDrive / network-mounted dev folders.
    //
    // - optimization_percent : cached calculate_optimizable_percent() result.
    //   The original function fully decodes + recompresses every unoptimized
    //   image just to display a "%" badge. With this column we compute it
    //   once and reuse it; recompute is triggered only when the user runs
    //   optimize.php or the file row is replaced.
    //
    // All three columns are nullable. NULL means "not computed yet" — the
    // first view_files render after upgrade fills them in lazily and persists
    // the result, so subsequent loads are O(1) per row.
    db("ALTER TABLE files ADD COLUMN image_width SMALLINT UNSIGNED DEFAULT NULL");
    db("ALTER TABLE files ADD COLUMN image_height SMALLINT UNSIGNED DEFAULT NULL");
    db("ALTER TABLE files ADD COLUMN optimization_percent TINYINT UNSIGNED DEFAULT NULL");
}

function upgrade_to_2026_1_23() {
    // Performance monitor — per-request runtime metrics for admin diagnostics.
    // Written via register_shutdown_function() (after fastcgi_finish_request when available)
    // so the request-path overhead is one row insert + a probabilistic retention sweep.
    //
    // duration_ms / cpu_*_ms are integers (sub-ms noise is below sampling resolution anyway).
    // peak_memory_kb stores memory_get_peak_usage(true) / 1024 so 32-bit hosts stay safe.
    // request_url uses VARCHAR(512) instead of 2083 to keep the index size sane on utf8mb4.
    db("CREATE TABLE IF NOT EXISTS perf_log (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_url     VARCHAR(512) NOT NULL DEFAULT '',
        script_name     VARCHAR(255) NOT NULL DEFAULT '',
        area            VARCHAR(16) NOT NULL DEFAULT 'frontend',
        method          VARCHAR(8) NOT NULL DEFAULT 'GET',
        http_status     SMALLINT UNSIGNED NOT NULL DEFAULT 200,
        duration_ms     INT UNSIGNED NOT NULL DEFAULT 0,
        peak_memory_kb  INT UNSIGNED NOT NULL DEFAULT 0,
        cpu_user_ms     INT UNSIGNED NOT NULL DEFAULT 0,
        cpu_system_ms   INT UNSIGNED NOT NULL DEFAULT 0,
        user_id         INT UNSIGNED NOT NULL DEFAULT 0,
        is_ajax         TINYINT(1) NOT NULL DEFAULT 0,
        log_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_duration (duration_ms),
        INDEX idx_peak_memory (peak_memory_kb),
        INDEX idx_script (script_name),
        INDEX idx_area_timestamp (area, log_timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function upgrade_to_2026_1_26() {
    // ── Order cancellation feature ──────────────────────────────────────
    // Customer-facing self-service cancel from the order_view widget.
    // Refund (when payment was Iyzipay / PayPal) is INTENTIONALLY manual:
    // the operator decides whether to refund based on policy + gateway
    // rules. We only mark the order as cancelled in the DB.
    //
    // Schema changes:
    //   1. Extend orders.status enum with 'cancelled'. Some legacy installs
    //      had 'incomplete' / 'complete' / 'exported' only — the cancel
    //      endpoint UPDATE would silently store '' on those (enum default-
    //      fallback) without this ALTER. Done as MODIFY COLUMN so existing
    //      rows keep their value.
    //   2. cancelled_at  — unix timestamp when status flipped to cancelled.
    //                       NULL when the order has never been cancelled.
    //   3. cancelled_by  — user_id (admin) OR 0 (customer self-service).
    //                       Lets reports distinguish "operator cancelled X"
    //                       from "customer cancelled themselves".
    //   4. cancellation_reason — visitor-typed text (optional, 500 chars).
    //                            Defensive against future GDPR / dispute logs.
    db("ALTER TABLE orders MODIFY COLUMN status enum('incomplete','complete','exported','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incomplete'");
    db("ALTER TABLE orders ADD COLUMN cancelled_at INT(10) UNSIGNED DEFAULT NULL");
    db("ALTER TABLE orders ADD COLUMN cancelled_by INT(10) UNSIGNED NOT NULL DEFAULT 0");
    db("ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(500) NOT NULL DEFAULT ''");
    db("ALTER TABLE orders ADD INDEX idx_cancelled_at (cancelled_at)");
}

function upgrade_to_2026_1_27() {
    // ── Refund-status tracking on orders ────────────────────────────────
    // Companion to 2026.1.26 (cancel feature). When the operator opts in
    // to automated Iyzipay refunds via ECOMMERCE_ORDER_CANCEL_AUTO_REFUND,
    // we record the outcome here so reports and the order_view widget can
    // distinguish "still owed to the customer" from "already refunded" or
    // "needs manual intervention".
    //
    // Enum values:
    //   ''                  — default / N/A (no cancellation has touched
    //                          this order yet).
    //   'pending'           — cancellation completed, refund attempt
    //                          queued but not yet acknowledged.
    //   'refunded'          — gateway confirmed the refund succeeded.
    //   'failed'            — gateway rejected the refund.
    //   'manual_required'   — auto-refund disabled, or payment method
    //                          isn't a refundable gateway, or auto-refund
    //                          threw an exception — operator must process
    //                          the refund in the gateway dashboard.
    db("ALTER TABLE orders ADD COLUMN refund_status ENUM('','pending','refunded','failed','manual_required') NOT NULL DEFAULT ''");
    db("ALTER TABLE orders ADD COLUMN refunded_at INT(10) UNSIGNED DEFAULT NULL");
    db("ALTER TABLE orders ADD COLUMN refund_reference VARCHAR(255) NOT NULL DEFAULT ''");
    db("ALTER TABLE orders ADD INDEX idx_refund_status (refund_status)");
}

function upgrade_to_2026_1_28() {
    // ── Cart "Save for later" infrastructure ────────────────────────────
    // Adds a saved_for_later flag on order_items so a cart row can be
    // hidden from the active cart without being lost. Feature is opt-in
    // via ECOMMERCE_SAVE_FOR_LATER config define — the column is created
    // unconditionally so the migration is reversible-safe and the runtime
    // gate stays in PHP (no per-install schema branching).
    //
    // saved_at — unix timestamp; lets future UI sort "Recently saved" or
    //            prune stale wishlist rows on a cron.
    db("ALTER TABLE order_items ADD COLUMN saved_for_later TINYINT(1) NOT NULL DEFAULT 0");
    db("ALTER TABLE order_items ADD COLUMN saved_at INT(10) UNSIGNED DEFAULT NULL");
    db("ALTER TABLE order_items ADD INDEX idx_saved_for_later (saved_for_later)");
}

function upgrade_to_2026_2_3() {
    // ── Repair the 'canceled' / 'cancelled' status split ─────────────────
    //
    // History of the bug this fixes:
    //   * An earlier upgrade added 'canceled' (one L) to the orders.status
    //     enum. The iyzico cancel flow on view_order.php wrote that value.
    //   * upgrade_to_2026_1_26() then redefined the enum as
    //     ('incomplete','complete','exported','cancelled') — two L's — and in
    //     doing so REMOVED 'canceled' from the allowed set.
    //
    // Two consequences on installs that had already used the old flow:
    //   1. MODIFY COLUMN coerced every existing 'canceled' row to '' (MySQL's
    //      out-of-range enum fallback) — those orders lost their status.
    //   2. Every later "cancel" from view_order.php kept writing 'canceled',
    //      which is now invalid, so it also landed as ''. In strict mode the
    //      UPDATE failed outright. Either way the order never got cancelled.
    //
    // The PHP side is fixed (all cancellation now runs through
    // process_order_cancellation(), which writes 'cancelled'). This migration
    // repairs the rows that were already damaged.
    //
    // Recovery rule — cancelled_at is the only surviving evidence:
    //   * cancelled_at set   → the row really was cancelled  → 'cancelled'
    //   * cancelled_at empty → status was clobbered by the ALTER, and we
    //                          cannot tell whether it had been exported, so
    //                          we restore the safe, reversible value
    //                          'complete'. An operator can re-export or
    //                          re-cancel from the admin screens.
    //
    // Guarded by a column probe because cancelled_at only exists once
    // upgrade_to_2026_1_26() has run.
    $has_cancelled_at = db_item("SHOW COLUMNS FROM orders LIKE 'cancelled_at'");

    if ($has_cancelled_at) {
        db("UPDATE orders
            SET status = 'cancelled'
            WHERE status = ''
              AND cancelled_at IS NOT NULL
              AND cancelled_at > 0");
    }

    // Anything still blank predates the cancellation columns entirely.
    db("UPDATE orders SET status = 'complete' WHERE status = ''");
}

function upgrade_to_2026_2_4() {
    // ── Web Application Firewall ─────────────────────────────────────────
    //
    // Ships DISABLED. Once an operator turns it on, it defaults to 'monitor',
    // which records what it would have blocked without blocking anything.
    // A firewall that starts rejecting traffic the moment it is installed is
    // how a storefront loses a day of orders.

    db("ALTER TABLE config ADD waf_enabled TINYINT(1) NOT NULL DEFAULT 0");
    db("ALTER TABLE config ADD waf_mode ENUM('monitor', 'block') NOT NULL DEFAULT 'monitor'");
    db("ALTER TABLE config ADD waf_sensitivity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'");
    db("ALTER TABLE config ADD waf_signature_scan TINYINT(1) NOT NULL DEFAULT 1");
    db("ALTER TABLE config ADD waf_rate_limit TINYINT(1) NOT NULL DEFAULT 1");
    db("ALTER TABLE config ADD waf_rate_limit_requests SMALLINT UNSIGNED NOT NULL DEFAULT 300");
    db("ALTER TABLE config ADD waf_rate_limit_sensitive SMALLINT UNSIGNED NOT NULL DEFAULT 30");
    db("ALTER TABLE config ADD waf_auto_ban TINYINT(1) NOT NULL DEFAULT 1");
    db("ALTER TABLE config ADD waf_auto_ban_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 5");
    db("ALTER TABLE config ADD waf_auto_ban_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60");
    db("ALTER TABLE config ADD waf_block_attack_tools TINYINT(1) NOT NULL DEFAULT 1");
    db("ALTER TABLE config ADD waf_verify_bots TINYINT(1) NOT NULL DEFAULT 1");
    db("ALTER TABLE config ADD waf_trusted_proxies TEXT DEFAULT NULL");
    db("ALTER TABLE config ADD waf_exclusions TEXT DEFAULT NULL");
    db("ALTER TABLE config ADD waf_blocked_agents TEXT DEFAULT NULL");
    db("ALTER TABLE config ADD waf_log_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30");

    // Last third-party WAF/CDN seen in front of this site (Cloudflare, Sucuri,
    // Akamai...). Recorded from real request headers and surfaced in Settings
    // so the operator knows whether this firewall is their first or second
    // layer of defence.
    db("ALTER TABLE config ADD waf_external_provider VARCHAR(64) NOT NULL DEFAULT ''");
    db("ALTER TABLE config ADD waf_external_seen INT UNSIGNED NOT NULL DEFAULT 0");

    // ── Event log ────────────────────────────────────────────────────────
    // 'action' records what the firewall DID, not what it saw: in monitor
    // mode an attack is stored as 'would-block'. That distinction is the
    // whole point of monitor mode.
    db("CREATE TABLE IF NOT EXISTS waf_log (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ip_address    VARCHAR(45) NOT NULL DEFAULT '',
        action        VARCHAR(16) NOT NULL DEFAULT 'log',
        rule_id       VARCHAR(40) NOT NULL DEFAULT '',
        category      VARCHAR(32) NOT NULL DEFAULT '',
        score         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        method        VARCHAR(8) NOT NULL DEFAULT '',
        request_url   VARCHAR(512) NOT NULL DEFAULT '',
        target        VARCHAR(64) NOT NULL DEFAULT '',
        matched       VARCHAR(255) NOT NULL DEFAULT '',
        user_agent    VARCHAR(255) NOT NULL DEFAULT '',
        user_id       INT UNSIGNED NOT NULL DEFAULT 0,
        log_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_ip (ip_address),
        INDEX idx_action_timestamp (action, log_timestamp),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Rate limit buckets ───────────────────────────────────────────────
    // bucket_key is the PRIMARY KEY so the counter can be a single atomic
    // INSERT ... ON DUPLICATE KEY UPDATE. Two simultaneous requests from one
    // IP therefore cannot both read the same count and both write count+1.
    db("CREATE TABLE IF NOT EXISTS waf_rate (
        bucket_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        window_start INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Bot verification cache ───────────────────────────────────────────
    // Reverse DNS is the only way to tell the real Googlebot from anyone who
    // typed "Googlebot" into their user agent, and it costs real wall-clock
    // time. Verdicts are cached for a week.
    db("CREATE TABLE IF NOT EXISTS waf_ip_reputation (
        ip_address VARCHAR(45) NOT NULL,
        bot_token  VARCHAR(40) NOT NULL DEFAULT '',
        verdict    ENUM('verified', 'spoofed', 'unknown') NOT NULL DEFAULT 'unknown',
        host_name  VARCHAR(255) NOT NULL DEFAULT '',
        checked_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (ip_address, bot_token),
        INDEX idx_checked (checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Extend the existing IP ban table ─────────────────────────────────
    //
    // banned_ip_addresses already existed but held only permanent IPv4 block
    // entries. Three additions:
    //   list_type  — the same table now also holds the ALLOW list, so an
    //                operator can exempt their own office from every rule.
    //   source     — separates operator entries from automatic bans, which
    //                matters because settings.php rewrites the manual list
    //                wholesale and must not wipe automatic bans.
    //   expires_at — automatic bans always expire. A permanent ban placed by
    //                a rule is how a customer's whole office gets locked out
    //                with nobody knowing why.
    db("ALTER TABLE banned_ip_addresses ADD list_type ENUM('block', 'allow') NOT NULL DEFAULT 'block'");
    db("ALTER TABLE banned_ip_addresses ADD source ENUM('manual', 'auto') NOT NULL DEFAULT 'manual'");
    db("ALTER TABLE banned_ip_addresses ADD note VARCHAR(255) NOT NULL DEFAULT ''");
    db("ALTER TABLE banned_ip_addresses ADD expires_at INT UNSIGNED NOT NULL DEFAULT 0");
    db("ALTER TABLE banned_ip_addresses ADD created_at INT UNSIGNED NOT NULL DEFAULT 0");
    db("ALTER TABLE banned_ip_addresses ADD hit_count INT UNSIGNED NOT NULL DEFAULT 0");
    db("ALTER TABLE banned_ip_addresses ADD INDEX idx_list_type (list_type)");
    db("ALTER TABLE banned_ip_addresses ADD INDEX idx_expires (expires_at)");

    // Existing rows are operator-placed permanent blocks; label them as such.
    db("UPDATE banned_ip_addresses
        SET list_type = 'block', source = 'manual', created_at = UNIX_TIMESTAMP()
        WHERE created_at = 0");
}

function upgrade_to_2026_2_5() {
    // ── Record the visitor's user agent ──────────────────────────────────
    //
    // The visitors table stored the address, referrer and landing page but
    // never the user agent, which made it impossible to answer the one
    // question that matters when the counter disagrees with Google
    // Analytics: which client produced these visits?
    //
    // The prefix index is what makes "group the last day's visits by client"
    // affordable on a table that can take six figures of rows per day.
    db("ALTER TABLE visitors ADD user_agent VARCHAR(255) NOT NULL DEFAULT ''");
    db("ALTER TABLE visitors ADD INDEX idx_user_agent (user_agent(64))");
}

function upgrade_to_2026_2_6() {
    // ── Firewall log: aggregate instead of one row per request ───────────
    //
    // As shipped in 2026.2.4 the log wrote one row per event. A single burst
    // of automated traffic produced thousands of rows carrying identical
    // information, and during a sustained attack the logging became a bigger
    // load problem than the attack — the firewall DoSing its own database.
    //
    // Events are now folded into a five-minute bucket keyed on
    // (address, rule, action, category, path). The same attack repeated ten
    // thousand times is one row with hit_count = 10000, and the write is a
    // single INSERT ... ON DUPLICATE KEY UPDATE either way, so the table
    // stops growing under load instead of growing fastest exactly when it
    // can least afford to.
    //
    // The table is dropped rather than altered. It is one version old and
    // holds nothing but test noise, and adding a UNIQUE key to rows that all
    // share an empty event_key would fail outright on duplicates.
    db("DROP TABLE IF EXISTS waf_log");

    db("CREATE TABLE waf_log (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        event_key     CHAR(40) NOT NULL DEFAULT '',
        window_start  INT UNSIGNED NOT NULL DEFAULT 0,
        hit_count     INT UNSIGNED NOT NULL DEFAULT 1,
        ip_address    VARCHAR(45) NOT NULL DEFAULT '',
        action        VARCHAR(16) NOT NULL DEFAULT 'log',
        rule_id       VARCHAR(40) NOT NULL DEFAULT '',
        category      VARCHAR(32) NOT NULL DEFAULT '',
        score         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        method        VARCHAR(8) NOT NULL DEFAULT '',
        request_url   VARCHAR(512) NOT NULL DEFAULT '',
        target        VARCHAR(64) NOT NULL DEFAULT '',
        matched       VARCHAR(255) NOT NULL DEFAULT '',
        user_agent    VARCHAR(255) NOT NULL DEFAULT '',
        user_id       INT UNSIGNED NOT NULL DEFAULT 0,
        log_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
        last_seen     INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_event (event_key, window_start),
        INDEX idx_timestamp (log_timestamp),
        INDEX idx_last_seen (last_seen),
        INDEX idx_ip (ip_address),
        INDEX idx_action_timestamp (action, log_timestamp),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Hard ceiling on stored rows. Time-based retention alone cannot bound
    // the table: a big enough attack fills it inside the retention window.
    // The cap is what actually guarantees the log has a maximum size.
    db("ALTER TABLE config ADD waf_log_max_rows INT UNSIGNED NOT NULL DEFAULT 20000");

    // 30 days was too generous for a table that can take thousands of rows a
    // minute. Aggregation makes 14 days cheap, and the cap backstops it.
    db("ALTER TABLE config MODIFY waf_log_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 14");
    db("UPDATE config SET waf_log_retention_days = 14 WHERE waf_log_retention_days = 30");
}

function upgrade_to_2026_2_7() {
    // ── IPv6 addresses were being truncated on ban ───────────────────────
    //
    // banned_ip_addresses.ip_address predates IPv6 in this codebase and was
    // sized for a dotted-quad — fifteen characters, exactly the width of
    // 255.255.255.255. An IPv6 address written into it lost everything past
    // the fifteenth character, which produced a compounding failure:
    //
    //   1. waf_auto_ban() looked for an existing row using the FULL address
    //      and found none, because what was stored was the truncated stub.
    //   2. It inserted, and MySQL truncated again.
    //   3. waf_ip_matches() compared the full address against the stub and
    //      returned false, so the ban was never actually enforced.
    //   4. The same client re-offended, and step 1 repeated.
    //
    // The visible symptom was dozens of identical ban rows for one address
    // while that address carried on browsing untouched. Both halves of the
    // bug — the duplicates and the ban doing nothing — are this one column.
    db("ALTER TABLE banned_ip_addresses MODIFY ip_address VARCHAR(45) NOT NULL DEFAULT ''");

    // Automatic bans are disposable by design (an hour by default) and any
    // written before the widening may be truncated stubs that can never match
    // anything. Drop them all; a client that is still attacking earns a new,
    // correct ban within minutes. Operator entries are left alone.
    db("DELETE FROM banned_ip_addresses WHERE source = 'auto'");

    // Collapse any remaining duplicates so the unique key below can be added.
    // Keeps the highest id — the most recently written row.
    db("DELETE b FROM banned_ip_addresses b
        INNER JOIN (
            SELECT ip_address, list_type, source, MAX(id) AS keep_id
            FROM banned_ip_addresses
            GROUP BY ip_address, list_type, source
            HAVING COUNT(*) > 1
        ) d
        ON  b.ip_address = d.ip_address
        AND b.list_type  = d.list_type
        AND b.source     = d.source
        AND b.id        <> d.keep_id");

    // Make duplication structurally impossible rather than relying on the
    // application to check first. With this key in place waf_auto_ban() can
    // be a single atomic INSERT ... ON DUPLICATE KEY UPDATE, which is also
    // race-free: two simultaneous requests from one attacker cannot both find
    // "no existing row" and both insert.
    db("ALTER TABLE banned_ip_addresses ADD UNIQUE KEY uniq_entry (ip_address, list_type, source)");
}

function upgrade_to_2026_3_1() {
    // ── Firewall: store the reference shown to the blocked visitor ────────
    //
    // The block page printed a code and told the visitor to quote it to the
    // site owner. Nothing wrote that code anywhere, so the owner could not
    // look it up — the page's entire purpose was defeated.
    db("ALTER TABLE waf_log ADD reference VARCHAR(16) NOT NULL DEFAULT '' AFTER user_id");
    db("ALTER TABLE waf_log ADD INDEX idx_reference (reference)");

    // ── Performance log: drop indexes nothing reads ──────────────────────
    //
    // Every request pays to maintain each index on this table. Checked
    // against the report's own queries:
    //
    //   idx_peak_memory  the memory report orders a derived table, never the
    //                    base column                              -> unused
    //   idx_script       the grouping key is a CASE expression, not the bare
    //                    column                                   -> unused
    //   idx_duration     the percentile query is already filtered by
    //                    log_timestamp, so the optimiser takes that index
    //                                                             -> redundant
    //
    // idx_timestamp and idx_area_timestamp carry every real query and stay.
    // Removing three of five secondary indexes takes roughly half the write
    // cost off a table that is written on every single page view.
    $perf_exists = db_item("SHOW TABLES LIKE 'perf_log'");

    if ($perf_exists) {
        if (db_item("SHOW INDEX FROM perf_log WHERE Key_name = 'idx_peak_memory'")) {
            db("ALTER TABLE perf_log DROP INDEX idx_peak_memory");
        }

        if (db_item("SHOW INDEX FROM perf_log WHERE Key_name = 'idx_script'")) {
            db("ALTER TABLE perf_log DROP INDEX idx_script");
        }

        if (db_item("SHOW INDEX FROM perf_log WHERE Key_name = 'idx_duration'")) {
            db("ALTER TABLE perf_log DROP INDEX idx_duration");
        }
    }
}

function upgrade_to_2026_3_2() {
    // ── Performance monitor: summarise instead of hoarding ───────────────
    //
    // One site reached 1,612,330 rows in perf_log. At that size the report
    // page became the slowest thing on the whole site — 40 seconds to open —
    // because the percentile query walked 1.5 million rows every time it was
    // viewed. Meanwhile every visitor request was inserting into that same
    // table, and the retention sweep was locking rows in it.
    //
    // The fix follows what the numbers actually showed: average 48 ms, p95
    // 241 ms, worst case 101 seconds. The middle of that distribution is
    // healthy and carries no information. Only the tail is worth storing at
    // full detail.
    //
    //   perf_stats  every request, folded into an hourly bucket per page.
    //               One INSERT ... ON DUPLICATE KEY UPDATE, and the table
    //               stops growing with traffic.
    //   perf_log    only requests slower than the threshold, now carrying the
    //               address, user agent and query string so a 101-second
    //               request can actually be investigated.
    db("CREATE TABLE IF NOT EXISTS perf_stats (
        bucket_key   CHAR(40) NOT NULL,
        hour_start   INT UNSIGNED NOT NULL,
        label        VARCHAR(255) NOT NULL DEFAULT '',
        area         VARCHAR(16) NOT NULL DEFAULT 'frontend',
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        slow_hits    INT UNSIGNED NOT NULL DEFAULT 0,
        total_ms     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        min_ms       INT UNSIGNED NOT NULL DEFAULT 0,
        max_ms       INT UNSIGNED NOT NULL DEFAULT 0,
        total_kb     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        max_kb       INT UNSIGNED NOT NULL DEFAULT 0,
        total_cpu_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (bucket_key, hour_start),
        INDEX idx_hour (hour_start),
        INDEX idx_area_hour (area, hour_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Context for the slow rows. Without these a 101-second entry says only
    // that something was slow, not whether it was a scraper hammering a
    // filtered catalogue or a real customer on a product page.
    $perf_exists = db_item("SHOW TABLES LIKE 'perf_log'");

    if ($perf_exists) {
        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'ip_address'")) {
            db("ALTER TABLE perf_log ADD ip_address VARCHAR(45) NOT NULL DEFAULT ''");
        }

        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'user_agent'")) {
            db("ALTER TABLE perf_log ADD user_agent VARCHAR(255) NOT NULL DEFAULT ''");
        }

        // The query string was deliberately stripped before, to keep grouping
        // cardinality down. That reasoning no longer applies: grouping happens
        // in perf_stats now, and on a slow row the parameters are usually the
        // whole explanation.
        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'query_string'")) {
            db("ALTER TABLE perf_log ADD query_string VARCHAR(512) NOT NULL DEFAULT ''");
        }

        // The existing rows are one-per-request records of ordinary traffic —
        // the exact data this change stops collecting. Keeping them would
        // leave the table huge and the new slow-request list meaningless,
        // since every fast request would still be in it.
        db("TRUNCATE perf_log");
    }
}

function upgrade_to_2026_3_3() {
    // Performance monitor on/off, in Site Settings rather than config.php.
    //
    // Defaults to on: the monitor is how a slow page gets noticed at all, and
    // after the summary rewrite it costs a fraction of a millisecond per
    // request — on PHP-FPM, after the response has already been sent, so the
    // visitor waits for none of it.
    db("ALTER TABLE config ADD perf_monitor TINYINT(1) NOT NULL DEFAULT 1");

    // Discard whatever the summary already holds.
    //
    // Before the sanity gate was added, a request whose start time came back
    // as zero produced a duration of roughly 1.7 trillion milliseconds. MySQL
    // clamped it to the unsigned ceiling without complaint, and because a
    // summary row accumulates rather than replaces, that one measurement made
    // its bucket's average permanently meaningless — one install reported an
    // average of 595,400,352,033 ms.
    //
    // There is no way to tell a poisoned bucket from a healthy one after the
    // fact, and the table refills within the hour, so the honest move is to
    // start again.
    if (db_item("SHOW TABLES LIKE 'perf_stats'")) {
        db("TRUNCATE perf_stats");
    }
}

function upgrade_to_2026_3_4() {
    // ── Visitor reporting: aggregate at write time ───────────────────────
    //
    // Two separate faults, one root cause.
    //
    // 1. update_visitor_page_data() only ever wrote landing_page_name, and
    //    only on a visitor's first page. Everything after that incremented a
    //    counter and was otherwise discarded. Worse, the name reaching it had
    //    already had its slug stripped (get_page.php:92 for catalog detail,
    //    :122 for form item view), so every product recorded as 'urun-detay'
    //    and every article as 'blog-gorunum'. The question "which article was
    //    read at 3pm" had no answer anywhere in the database.
    //
    // 2. Every report counted raw visitor rows with HOUR(FROM_UNIXTIME(...))
    //    groupings. At 100,000-200,000 visits a day that is millions of rows
    //    per month, scanned and sorted into a temporary table on each load.
    //
    // Both are fixed by counting when the view happens rather than
    // reconstructing it later. This is the shape waf_log took in 2026.2.6: a
    // bucket key plus INSERT ... ON DUPLICATE KEY UPDATE, so a repeated event
    // increments a counter instead of adding a row.
    //
    // No visitor data is removed or altered. The `visitors` table keeps every
    // column and every row, and view_visitor_report.php's advanced filters
    // continue to read it directly.

    // Read the ceiling BEFORE the tables exist.
    //
    // pg_visitor_rollup_ready() starts returning true the moment
    // visitor_content_hourly appears, and live counting begins from that
    // instant. Rows at or below this id were therefore written while nothing
    // was counting, and are the backfill's job; rows above it are counted
    // live. Reading the ceiling first means the two ranges cannot overlap.
    // The handful of page views that land between this read and the CREATE
    // are missed rather than double-counted, which is the right way round to
    // be wrong.
    $max_visitor_id = (int) db_value("SELECT MAX(id) FROM visitors");

    // Site-wide totals: 24 rows per day, 8,760 a year. This is what the
    // dashboard's three traffic panels read instead of the visitors table.
    db("CREATE TABLE IF NOT EXISTS visitor_stats_hourly (
            stat_date    DATE NOT NULL,
            stat_hour    TINYINT UNSIGNED NOT NULL,
            new_visitors INT UNSIGNED NOT NULL DEFAULT 0,
            page_views   INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (stat_date, stat_hour)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Per-content totals: one row per (hour, page, item).
    //
    // item_type/item_id hold the primary key of what was actually shown, not
    // its title. A renamed product then renames throughout the report history
    // instead of leaving stale copies of the old title in old rows.
    //
    // The unique key is a sha1 of the bucket rather than the columns
    // themselves. A composite key over page_name would run to roughly 780
    // bytes in utf8mb4, past the 767-byte per-column index limit on MySQL 5.6
    // with COMPACT row format. waf_log's event_key solves the same problem
    // the same way.
    db("CREATE TABLE IF NOT EXISTS visitor_content_hourly (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bucket_key CHAR(40) NOT NULL,
            stat_date  DATE NOT NULL,
            stat_hour  TINYINT UNSIGNED NOT NULL,
            page_id    INT UNSIGNED NOT NULL DEFAULT 0,
            page_name  VARCHAR(100) NOT NULL DEFAULT '',
            item_type  VARCHAR(20) NOT NULL DEFAULT '',
            item_id    INT UNSIGNED NOT NULL DEFAULT 0,
            views      INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bucket (bucket_key),
            KEY idx_date_hour (stat_date, stat_hour),
            KEY idx_date_views (stat_date, views),
            KEY idx_item (item_type, item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Backfill bookkeeping. Kept in config so the work survives a killed
    // request: this software runs on dozens of server types and the ones with
    // a hard FastCGI or proxy timeout will cut a long upgrade off mid-flight
    // no matter what ini_set('max_execution_time') says.
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_max_id'")) {
        db("ALTER TABLE config ADD visitor_rollup_max_id INT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_cursor'")) {
        db("ALTER TABLE config ADD visitor_rollup_cursor INT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_done'")) {
        db("ALTER TABLE config ADD visitor_rollup_done TINYINT(1) NOT NULL DEFAULT 0");
    }

    db("UPDATE config SET
            visitor_rollup_max_id = '" . $max_visitor_id . "',
            visitor_rollup_cursor = 0,
            visitor_rollup_done   = '" . ($max_visitor_id > 0 ? 0 : 1) . "'");
}

function upgrade_to_2026_3_5() {
    // ── Backfill the rollups from existing visitor rows ──────────────────
    //
    // Read-only with respect to `visitors`: this reads rows and writes
    // summaries elsewhere. Nothing in the source table is modified.
    //
    // Runs against a time budget and stores its position, because it cannot
    // assume it will be allowed to finish. Whatever is left over is picked up
    // a slice at a time when an administrator opens the dashboard, so the
    // work completes without anyone having to babysit a long-running page.
    // Third argument forces the table probe to run again: 2026.3.4 created
    // these tables a moment ago, in this same request.
    if (function_exists('pg_visitor_backfill_step')) {
        pg_visitor_backfill_step(20, 20000, true);
    }
}

function upgrade_to_2026_3_6() {
    // ── visitors: MyISAM to InnoDB ───────────────────────────────────────
    //
    // MyISAM locks whole tables. update_visitor_page_data() runs an UPDATE on
    // this table for every page view, and an UPDATE takes an exclusive table
    // lock, so at this traffic level page views already serialise against one
    // another. Add a reporting query holding a read lock and every visitor on
    // the site queues behind it — which is why the dashboard being open made
    // the site slow.
    //
    // MyISAM's concurrent-insert optimisation does not rescue this: it only
    // applies while the table has no gaps, and a table under constant UPDATE
    // always has gaps.
    //
    // The other reason is recovery. An unclean shutdown leaves a MyISAM table
    // of this size needing REPAIR TABLE, which can run for hours with the
    // table unwritable throughout. InnoDB recovers from its log on startup.
    //
    // Safe to re-run: if the engine is already InnoDB this does nothing, so a
    // request killed part-way through simply repeats the ALTER next time.
    // Checked rather than assumed because ALTER TABLE ... ENGINE cannot be
    // resumed and is expensive to start over.
    //
    // Only `visitors` is converted. search_items carries the FULLTEXT indexes
    // that get_search_results.php queries with MATCH ... AGAINST, and older
    // MySQL supports FULLTEXT on MyISAM only.
    $status = db_item("SHOW TABLE STATUS LIKE 'visitors'");

    if (is_array($status) && isset($status['Engine']) && strtolower($status['Engine']) !== 'innodb') {
        db("ALTER TABLE visitors ENGINE=InnoDB");
    }
}

function upgrade_to_2026_3_7() {
    // ── Repair: the home page counted as two pages ───────────────────────
    //
    // Data repair only, no schema change. Same class of problem as 2026.1.29.
    //
    // A site's root is one page recorded under several names — '', '/',
    // 'index.php', and a legacy 'example.com/'. 2026.3.5's backfill grouped
    // by whatever string it found, so those became separate rows from the
    // ones carrying the home page's real name. The dashboard then listed the
    // home page twice, once under its own name and once as "Homepage", with
    // its traffic divided between the two entries.
    //
    // Rows recorded live were never affected: get_page.php resolves the home
    // page before tracking runs, so it always writes the real page name.
    //
    // Merges rather than deletes, so no view is lost. Counts fold into the
    // correct bucket and only the stray rows go.
    if (!db_item("SHOW TABLES LIKE 'visitor_content_hourly'")) {
        return;
    }

    $home = db_item("SELECT page_id, page_name FROM page WHERE page_home = 'yes' ORDER BY page_id LIMIT 1");

    if (!is_array($home) || empty($home['page_id'])) {
        return;
    }

    $home_id   = (int) $home['page_id'];
    $home_name = trim($home['page_name']);
    $aliases   = "'', '/', 'index.php', 'example.com/'";

    // One row per hour at most, so this is a small set even on a busy site
    // with years of history. Done in PHP rather than as a self-referencing
    // INSERT ... SELECT with ON DUPLICATE KEY UPDATE, which behaves
    // differently across MySQL versions when the source and target are the
    // same table.
    $strays = db_items(
        "SELECT id, stat_date, stat_hour, item_type, item_id, views
         FROM visitor_content_hourly
         WHERE page_name IN ($aliases) AND page_id <> '$home_id'"
    );

    if (!is_array($strays)) {
        return;
    }

    foreach ($strays as $stray) {

        $bucket_key = sha1(
            $stray['stat_date'] . '|' . (int) $stray['stat_hour'] . '|' . $home_id
            . '|' . $stray['item_type'] . '|' . (int) $stray['item_id']
        );

        db("INSERT INTO visitor_content_hourly
                (bucket_key, stat_date, stat_hour, page_id, page_name, item_type, item_id, views)
            VALUES
                ('" . e($bucket_key) . "', '" . e($stray['stat_date']) . "', " . (int) $stray['stat_hour'] . ",
                 $home_id, '" . e($home_name) . "', '" . e($stray['item_type']) . "', " . (int) $stray['item_id'] . ",
                 " . (int) $stray['views'] . ")
            ON DUPLICATE KEY UPDATE views = views + VALUES(views)");

        db("DELETE FROM visitor_content_hourly WHERE id = '" . (int) $stray['id'] . "'");
    }
}

function upgrade_to_2026_3_8() {
    // ── Rebuild the visitor rollups from `visitors` ──────────────────────
    //
    // The first cut of the backfill had two faults that only show up once
    // real traffic runs through it.
    //
    // 1. The two summary tables counted different things. Site totals summed
    //    visitors.page_views while the per-content table used COUNT(*), which
    //    counts sessions. The dashboard reads the hour's total from one and
    //    the busiest item from the other, so it printed "29 page views" with
    //    "home-1 · 1" underneath.
    //
    // 2. visitors.page_views keeps climbing after the rollup starts counting
    //    live. A session already open at the cutover had its views recorded
    //    live AND summed again when the backfill reached its row, inflating
    //    every hour that straddled the upgrade.
    //
    // Both are fixed in the writer, but the numbers already stored were
    // produced by the old one and cannot be corrected in place — a bucket
    // holds a single total with no record of which half came from where. So
    // the derived tables are dropped and rebuilt from `visitors`, which has
    // been the authority all along and is not touched here.
    //
    // Cost of the rebuild: item-level detail collected since the upgrade is
    // re-derived from landing_page_name, so it returns to page level. Only
    // the summaries lose that; the raw table never had it to begin with, and
    // page views recorded from now on carry their item as normal.
    if (!db_item("SHOW TABLES LIKE 'visitor_content_hourly'")) {
        return;
    }

    db("TRUNCATE visitor_stats_hourly");
    db("TRUNCATE visitor_content_hourly");

    // Re-read the ceiling. Everything up to this id now comes from `visitors`
    // in one consistent pass, so there is no boundary for the two counting
    // methods to disagree across.
    $max_visitor_id = (int) db_value("SELECT MAX(id) FROM visitors");

    db("UPDATE config SET
            visitor_rollup_max_id = '" . $max_visitor_id . "',
            visitor_rollup_cursor = 0,
            visitor_rollup_done   = '" . ($max_visitor_id > 0 ? 0 : 1) . "'");

    if (function_exists('pg_visitor_backfill_step')) {
        pg_visitor_backfill_step(20, 20000, true);
    }
}

function upgrade_to_2026_4() {
    // ── Variant sets can own a product form ──────────────────────────────
    //
    // A product form is a set of form_fields rows keyed by product_id. That
    // model assumes one product per form, which breaks down the moment a
    // product comes in nine colour/size combinations: the operator would have
    // to draw the same form nine times.
    //
    // The v2 screens keep the runtime model exactly as it is — every product
    // still owns its own rows, so the catalog, the cart and submit_order.php
    // are untouched — and add a template above it:
    //
    //   form_type = 'product_group', product_group_id = <group>, product_id = 0
    //       the template, edited once
    //   form_type = 'product', product_id = <product>, template_field_id > 0
    //       a copy generated from that template
    //   form_type = 'product', product_id = <product>, template_field_id = 0
    //       a field added to one variant by hand, and left alone when the
    //       template is re-applied
    //
    // Existing rows all fall in the last category, which is why both new
    // columns default to 0 and nothing needs backfilling.
    $columns = array(
        'product_group_id'  => "ALTER TABLE form_fields ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
        'template_field_id' => "ALTER TABLE form_fields ADD COLUMN template_field_id INT UNSIGNED NOT NULL DEFAULT 0",
    );

    foreach ($columns as $column => $sql) {
        $exists = db_item("SHOW COLUMNS FROM form_fields LIKE '" . $column . "'");
        if (!$exists) {
            db($sql);
        }
    }

    // Both indexes serve a query that runs on every template edit: "the
    // template rows of this group" and "the copies made from this template
    // row". Without them each apply is a full scan of a table that grows with
    // every product in the catalog.
    $indexes = db_items("SHOW INDEX FROM form_fields");
    $existing_indexes = array();

    foreach ($indexes as $index) {
        $existing_indexes[$index['Key_name']] = TRUE;
    }

    if (!isset($existing_indexes['idx_product_group'])) {
        db("ALTER TABLE form_fields ADD INDEX idx_product_group (product_group_id)");
    }

    if (!isset($existing_indexes['idx_template_field'])) {
        db("ALTER TABLE form_fields ADD INDEX idx_template_field (template_field_id)");
    }

    // form_field_options and target_options carry a denormalised owner column
    // so bulk deletes can find their rows without a join, and add_field.php /
    // edit_field.php write it generically from $form_type_identifier_id. Both
    // tables need the new column or a template field with options cannot be
    // saved at all.
    $xref_columns = array(
        'form_field_options' => "ALTER TABLE form_field_options ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
        'target_options'     => "ALTER TABLE target_options ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
    );

    foreach ($xref_columns as $table => $sql) {
        $exists = db_item("SHOW COLUMNS FROM " . $table . " LIKE 'product_group_id'");
        if (!$exists) {
            db($sql);
        }
    }

    // The form's own settings live on the group, mirroring the four columns a
    // product carries. Storing them on one "owner" variant instead would lose
    // them the day that variant is deleted.
    $group_columns = array(
        'form'                    => "ALTER TABLE product_groups ADD COLUMN form TINYINT(1) NOT NULL DEFAULT 0",
        'form_name'               => "ALTER TABLE product_groups ADD COLUMN form_name VARCHAR(100) NOT NULL DEFAULT ''",
        'form_label_column_width' => "ALTER TABLE product_groups ADD COLUMN form_label_column_width VARCHAR(3) NOT NULL DEFAULT ''",
        'form_quantity_type'      => "ALTER TABLE product_groups ADD COLUMN form_quantity_type VARCHAR(30) NOT NULL DEFAULT ''",
    );

    foreach ($group_columns as $column => $sql) {
        $exists = db_item("SHOW COLUMNS FROM product_groups LIKE '" . $column . "'");
        if (!$exists) {
            db($sql);
        }
    }
}

function upgrade_to_2026_4_1() {

    // Daily rollup for article views.
    //
    // submitted_form_views held one row per view. On a site serving 200,000
    // views a day it had reached eight million rows and 1.1 GB -- 73% of the
    // whole database, 946 MB of it index. Being MyISAM, every article view took
    // an exclusive lock on the entire table while four B-trees were updated,
    // and every other request touching it queued behind that lock. Measured on
    // the affected site, requests spent 84% of their wall clock waiting rather
    // than computing, at every hour of the day.
    //
    // Two of those four indexes could never be used at all: `submitted_form_id`
    // repeated the leading column of the composite index, and `page_id` had a
    // cardinality of six across eight million rows.
    //
    // All of it existed to answer one question on one administrator screen --
    // how many views each article drew in the last N days. The counter readers
    // see comes from submitted_form_info.number_of_views and is untouched.
    //
    // No secondary index here, deliberately. The retention sweep and the
    // delete-by-page paths scan, but they scan a few thousand rows; paying for
    // an index on every write to save that is the trade that produced the table
    // this one replaces.
    db("CREATE TABLE IF NOT EXISTS submitted_form_view_stats (
        submitted_form_id INT UNSIGNED NOT NULL DEFAULT 0,
        page_id           INT UNSIGNED NOT NULL DEFAULT 0,
        view_date         DATE         NOT NULL DEFAULT '0000-00-00',
        views             INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (submitted_form_id, page_id, view_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bookkeeping for an interruptible backfill.
    $config_columns = array(
        'sfv_rollup_cutover' => "ALTER TABLE config ADD sfv_rollup_cutover INT UNSIGNED NOT NULL DEFAULT 0",
        'sfv_rollup_cursor'  => "ALTER TABLE config ADD sfv_rollup_cursor INT UNSIGNED NOT NULL DEFAULT 0",
        'sfv_rollup_done'    => "ALTER TABLE config ADD sfv_rollup_done TINYINT(1) NOT NULL DEFAULT 0",
    );

    foreach ($config_columns as $column => $sql) {
        if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
            db($sql);
        }
    }

    // install/index.php never loads init.php, so the session time zone MySQL
    // would otherwise use is the server default. The live writer buckets with
    // CURDATE() and the backfill with DATE(FROM_UNIXTIME(...)); if the two
    // clocks disagree, history and new traffic land on different days and meet
    // at a seam the width of the offset.
    if (function_exists('pg_sync_mysql_timezone')) {
        pg_sync_mysql_timezone();
    }

    // The ceiling is fixed before anything is written, so the live writer and
    // the backfill can never cover the same second twice. Views recorded from
    // this moment go to the rollup; the backfill owns everything before it.
    $cutover = time();

    $oldest = 0;
    if (db_item("SHOW TABLES LIKE 'submitted_form_views'")) {
        // Served by the `timestamp` index, so this is a lookup, not a scan of
        // eight million rows.
        $oldest = (int) db_value("SELECT MIN(timestamp) FROM submitted_form_views WHERE timestamp > 0");
    }

    if ($oldest > 0) {
        // Start on a day boundary so every chunk maps to exactly one bucket.
        $cursor = strtotime(date('Y-m-d', $oldest));

        db("UPDATE config SET
                sfv_rollup_cutover = '" . (int) $cutover . "',
                sfv_rollup_cursor  = '" . (int) $cursor . "',
                sfv_rollup_done    = 0");
    } else {
        // Nothing to summarise: a fresh install, or a site whose legacy table
        // was already retired.
        db("UPDATE config SET
                sfv_rollup_cutover = '" . (int) $cutover . "',
                sfv_rollup_cursor  = '" . (int) $cutover . "',
                sfv_rollup_done    = 1");
    }

    // Spend a bounded slice here; the form view directory screen carries the
    // rest a few seconds at a time. Split across page loads rather than run to
    // completion because IIS FastCGI and nginx end a long request on their own
    // schedule, and the version number is written only after this returns -- an
    // upgrade killed midway starts over.
    //
    // $recheck bypasses the readiness probe's static cache: the table was
    // created moments ago in this same request, and a "no" cached before that
    // would make the backfill skip itself and report success.
    if (function_exists('pg_sfv_backfill_step')) {
        pg_sfv_backfill_step(20, true);
    }
}

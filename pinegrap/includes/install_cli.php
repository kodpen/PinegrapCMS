<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The installation from the command line:
 *
 *   php pinegrap/install/index.php install
 *
 * installs a starter site into an empty database, as the installation screen
 * does, with what the screen asks for taken from environment variables
 * (install_cli_variables()). The install service of docker/docker-compose.yml
 * runs it, so that `docker compose up` ends with a site; a host can run it
 * the same way.
 *
 * The installation itself is the screen's own code in install/index.php:
 * install_cli_begin() fills in the fields the screen would post, and the
 * installer prints its steps as lines of text instead of the page. Only a
 * starter site is installed, and only into a database without a site: a
 * site that is already there is left alone, so the container that runs this
 * on every start can run it again. Restoring a backup stays on the screen,
 * which shows what the backup holds before it replaces anything.
 *
 * What it prints is a log for the person who runs it, in the plain form of
 * the automated upgrade's cron answer: the steps, then one line that starts
 * with "ok:", "skipped:" or "error:". The exit status is 0 when the site was
 * installed or was already there, 1 when the installation failed and 2 when
 * a variable is missing or wrong.
 *
 * Loaded by install/index.php before the software's functions, so it uses
 * PHP and nothing of the software. Every function starts with install_cli_.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('INSTALL_OR_UPDATE') && !defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// The variables that are read, in the order the usage text lists them, with what each one is.
function install_cli_variables() {

	return array(
		'PG_INSTALL_DB_HOST' => 'database server (required)',
		'PG_INSTALL_DB_NAME' => 'database name (required)',
		'PG_INSTALL_DB_USER' => 'database user (required)',
		'PG_INSTALL_DB_PASSWORD' => 'database password',
		'PG_INSTALL_ADMIN_EMAIL' => 'e-mail address of the administrator (required)',
		'PG_INSTALL_ADMIN_PASSWORD' => 'password of the administrator (required)',
		'PG_INSTALL_ADMIN_USERNAME' => 'username of the administrator (admin)',
		'PG_INSTALL_STARTER' => 'starter site: english_default or turkish_default (english_default)',
		'PG_INSTALL_LANGUAGE' => 'software language: en or tr (the starter site\'s)',
		'PG_INSTALL_HOSTNAME' => 'host name the site answers at, with :port when it is not 80 or 443 (localhost)',
		'PG_INSTALL_PATH' => 'URL path of the site (/)',
		'PG_INSTALL_WEB_SERVER' => 'apache, nginx or iis: which server rules file is written (found from the web root)'
	);

}

// Reads the variables into the fields of the installation form.
//
// $env holds the variables that are set; an empty one counts as not set. $defaults holds the
// values to use for a variable that is not set (the database of a data/config.php that names
// one). $starter_sites maps each starter site folder there is to its language.
//
// Returns array(
//   'fields'   => the form fields, as the installation screen posts them
//   'settings' => starter, language, hostname, path, server_software (the SERVER_SOFTWARE
//                 the request would carry, '' to find the server from the web root)
//   'problems' => one sentence per variable that is missing or wrong; nothing is installed
//                 while there is one
// )
function install_cli_read($env, $defaults, $starter_sites) {

	$value = function ($name, $fallback = '') use ($env, $defaults) {

		if ((isset($env[$name])) && (trim((string) $env[$name]) !== '')) {

			return trim((string) $env[$name]);

		}

		if ((isset($defaults[$name])) && (trim((string) $defaults[$name]) !== '')) {

			return trim((string) $defaults[$name]);

		}

		return $fallback;

	};

	$problems = array();

	foreach (array('PG_INSTALL_DB_HOST', 'PG_INSTALL_DB_NAME', 'PG_INSTALL_DB_USER', 'PG_INSTALL_ADMIN_EMAIL', 'PG_INSTALL_ADMIN_PASSWORD') as $name) {

		if ($value($name) === '') {

			$problems[] = $name . ' is not set.';

		}

	}

	$starter = $value('PG_INSTALL_STARTER', 'english_default');

	if (!isset($starter_sites[$starter])) {

		$problems[] = 'PG_INSTALL_STARTER is ' . $starter . '; the starter sites here are ' . ((count($starter_sites) > 0) ? implode(', ', array_keys($starter_sites)) : 'missing from data/backups') . '.';

	}

	$language = $value('PG_INSTALL_LANGUAGE', isset($starter_sites[$starter]) ? $starter_sites[$starter] : 'en');

	if (!in_array($language, array('en', 'tr'), true)) {

		$problems[] = 'PG_INSTALL_LANGUAGE must be en or tr.';

	}

	$hostname = $value('PG_INSTALL_HOSTNAME', 'localhost');

	if (!install_cli_hostname_valid($hostname)) {

		$problems[] = 'PG_INSTALL_HOSTNAME must be a host name with an optional :port, such as localhost:8080.';

	}

	$path = install_cli_path($value('PG_INSTALL_PATH', '/'));

	if ($path === '') {

		$problems[] = 'PG_INSTALL_PATH must be a URL path such as / or /shop/.';

	}

	// What the request's SERVER_SOFTWARE would say, which is how the installer picks the
	// file it writes the server rules into (includes/server_config.php).
	$servers = array('apache' => 'Apache', 'nginx' => 'nginx', 'iis' => 'Microsoft-IIS');

	$server = strtolower($value('PG_INSTALL_WEB_SERVER'));

	if (($server !== '') && (!isset($servers[$server]))) {

		$problems[] = 'PG_INSTALL_WEB_SERVER must be apache, nginx or iis.';

	}

	$email = $value('PG_INSTALL_ADMIN_EMAIL');

	// The installation screen leaves the format to the browser's field; here there is no
	// browser, and the address becomes the site's own e-mail address and the fallback
	// recipient of the operations alerts. A missing one is named above.
	if (($email !== '') && (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {

		$problems[] = 'PG_INSTALL_ADMIN_EMAIL must be an e-mail address, such as admin@example.com.';

	}

	$password = $value('PG_INSTALL_ADMIN_PASSWORD');

	return array(
		'fields' => array(
			'submit' => '1',
			'install_type' => 'install',
			'install_from_folder' => $starter,
			'default_software_language' => $language,
			// As they are, quotes and backslashes included: the installer writes them into
			// data/config.php with pg_config_install_content(), as it does the screen's.
			'db_host' => $value('PG_INSTALL_DB_HOST'),
			'db_username' => $value('PG_INSTALL_DB_USER'),
			'db_password' => $value('PG_INSTALL_DB_PASSWORD'),
			'db_database' => $value('PG_INSTALL_DB_NAME'),
			'admin_username' => $value('PG_INSTALL_ADMIN_USERNAME', 'admin'),
			'admin_email_address' => $email,
			'admin_confirm_email_address' => $email,
			'admin_password' => $password,
			'admin_confirm_password' => $password,
			'send_email' => 'false'
		),
		'settings' => array(
			'starter' => $starter,
			'language' => $language,
			'hostname' => $hostname,
			'path' => $path,
			'server_software' => isset($servers[$server]) ? $servers[$server] : ''
		),
		'problems' => $problems
	);

}

// A host name, an IPv4 address or a bracketed IPv6 address, with an optional port: what a
// browser sends in the Host header, and what the installer keeps as the site's address.
function install_cli_hostname_valid($hostname) {

	return (preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?)(?::[0-9]{1,5})?$/D', (string) $hostname) == 1);

}

// The URL path of the site as PATH holds it: "/" at the root, "/shop/" in a folder. '' when
// the value is not a plain path (a dot segment, a character a URL path would encode).
function install_cli_path($path) {

	$segments = array();

	foreach (explode('/', (string) $path) as $segment) {

		if ($segment === '') {

			continue;

		}

		if (($segment === '.') || ($segment === '..') || (preg_match('/^[A-Za-z0-9._~-]+$/D', $segment) != 1)) {

			return '';

		}

		$segments[] = $segment;

	}

	return (count($segments) > 0) ? '/' . implode('/', $segments) . '/' : '/';

}

// A message of the installer as one line of plain text: the screen's messages may carry
// markup and entities.
function install_cli_text($message) {

	$text = preg_replace('/<br\s*\/?>/i', ' ', (string) $message);

	$text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');

	return trim(preg_replace('/\s+/u', ' ', $text));

}

// One step of the installation, as the screen's console shows it: the seconds since the start,
// then what was done and the detail.
function install_cli_step_line($seconds, $label, $detail = '', $state = 'ok') {

	$line = '[' . str_pad(number_format((float) $seconds, 1, '.', ''), 5, ' ', STR_PAD_LEFT) . ' s] ';

	if (($state === 'warning') || ($state === 'error')) {

		$line .= $state . ': ';

	}

	$line .= install_cli_text($label);

	$detail = install_cli_text($detail);

	if ($detail !== '') {

		$line .= ': ' . $detail;

	}

	return $line;

}

// What the command is for and the variables it reads.
function install_cli_usage() {

	$usage = 'usage: php pinegrap/install/index.php install' . "\n\n" .
		'Installs a starter site into an empty database, with what the installation screen asks for' . "\n" .
		'taken from these environment variables:' . "\n\n";

	foreach (install_cli_variables() as $name => $description) {

		$usage .= '  ' . str_pad($name, 27) . $description . "\n";

	}

	return $usage;

}

function install_cli_print($line) {

	if (defined('STDOUT')) {

		fwrite(STDOUT, $line . "\n");

	} else {

		print $line . "\n";

	}

}

// Starts the installation from the command line: reads the variables and fills in what the
// installation screen's request would carry. A variable that is missing or wrong is kept for
// install_cli_check(). $starter_sites as for install_cli_read(). Returns the settings.
function install_cli_begin($starter_sites) {

	global $install_cli_reported, $install_cli_problems;

	$env = array();

	foreach (array_keys(install_cli_variables()) as $name) {

		$env_value = getenv($name);

		if ($env_value !== false) {

			$env[$name] = $env_value;

		}

	}

	// A data/config.php that names the database (written by hand before the installation)
	// stands in for the database variables, as it fills in the screen's database fields.
	$defaults = array();

	foreach (array('PG_INSTALL_DB_HOST' => 'DB_HOST', 'PG_INSTALL_DB_NAME' => 'DB_DATABASE', 'PG_INSTALL_DB_USER' => 'DB_USERNAME', 'PG_INSTALL_DB_PASSWORD' => 'DB_PASSWORD') as $name => $constant) {

		if (defined($constant)) {

			$defaults[$name] = (string) constant($constant);

		}

	}

	$read = install_cli_read($env, $defaults, $starter_sites);

	$install_cli_problems = $read['problems'];

	$settings = $read['settings'];

	// The request the screen would send: the form's fields, the site's address, and the
	// language of the starter site for the screen's own words.
	$_POST = $read['fields'];

	$_REQUEST = $read['fields'];

	$_GET = array('local' => $settings['language']);

	$_COOKIE = array();

	$_FILES = array();

	// No session: everything the form keeps in one lives in this process.
	$_SESSION = array();

	$_SERVER['REQUEST_METHOD'] = 'POST';

	$_SERVER['HTTP_HOST'] = $settings['hostname'];

	// The installer finds the site's URL path three folders above the script's.
	$_SERVER['SCRIPT_NAME'] = (($settings['path'] !== '') ? $settings['path'] : '/') . basename(dirname(dirname(__FILE__))) . '/install/index.php';

	if ($settings['server_software'] !== '') {

		$_SERVER['SERVER_SOFTWARE'] = $settings['server_software'];

	}

	$install_cli_reported = false;

	register_shutdown_function('install_cli_shutdown');

	return $settings;

}

// Stops with the usage text when a variable is missing or wrong. Asked once the installer
// knows that no site is there yet: a site that is already installed is reported as such,
// whatever the variables say.
function install_cli_check() {

	global $install_cli_reported, $install_cli_problems;

	if (count((array) $install_cli_problems) == 0) {

		return;

	}

	install_cli_print(rtrim(install_cli_usage()));

	install_cli_print('');

	foreach ((array) $install_cli_problems as $problem) {

		install_cli_print('error: ' . $problem);

	}

	$install_cli_reported = true;

	exit(2);

}

// The last line and the exit status, for an end the installer saw coming.
function install_cli_finish($outcome, $text, $status) {

	global $install_cli_reported;

	$install_cli_reported = true;

	install_cli_print($outcome . ': ' . $text);

	exit($status);

}

// What the installation form collected before it stopped, then the failure.
function install_cli_fail($messages) {

	foreach ((array) $messages as $message) {

		$message = install_cli_text($message);

		if ($message !== '') {

			install_cli_print($message);

		}

	}

	install_cli_finish('error', 'the installation was stopped; nothing was installed.', 1);

}

// An installer check that stops the request with a message of its own (exit('...')), or PHP
// itself, ends the process without a last line and with status 0. That is turned into a
// failure here: from a further shutdown function, so the upgrade runner's own, which reports a
// fatal error as a failed version, runs first, and an exit in this one would cut it off.
function install_cli_shutdown() {

	global $install_cli_reported;

	if ($install_cli_reported == true) {

		return;

	}

	register_shutdown_function(function () {

		install_cli_print('');

		install_cli_print('error: the installation stopped before it finished; the lines above say where.');

		exit(1);

	});

}

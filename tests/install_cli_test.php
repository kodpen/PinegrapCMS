<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the installation from the command line (includes/install_cli.php):
 * how the PG_INSTALL_* variables become the fields of the installation form,
 * which values are refused before anything is installed, and how a step of
 * the installer is printed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/install_cli.php');

// config_file_test_values_of(): runs a config.php in a separate process.
require_once(__DIR__ . '/config_file_test.php');

// The starter sites as install/index.php hands them over: folder => language.
function install_cli_test_starters()
{
	return array('turkish_default' => 'tr', 'english_default' => 'en');
}

// The variables a complete installation needs.
function install_cli_test_env()
{
	return array(
		'PG_INSTALL_DB_HOST' => 'db',
		'PG_INSTALL_DB_NAME' => 'pinegrap',
		'PG_INSTALL_DB_USER' => 'pinegrap',
		'PG_INSTALL_DB_PASSWORD' => 'secret',
		'PG_INSTALL_ADMIN_EMAIL' => 'admin@example.test',
		'PG_INSTALL_ADMIN_PASSWORD' => 'Admin-pass-1',
	);
}

// The fields are the ones the installation screen posts for a new site, the confirmation
// fields repeat the e-mail address and the password, and no confirmation e-mail is sent.
function test_install_cli_read_fills_the_form_fields()
{
	$read = install_cli_read(install_cli_test_env(), array(), install_cli_test_starters());

	pg_assert_same(array(), $read['problems'], 'no problems');

	pg_assert_same(array(
		'submit' => '1',
		'install_type' => 'install',
		'install_from_folder' => 'english_default',
		'default_software_language' => 'en',
		'db_host' => 'db',
		'db_username' => 'pinegrap',
		'db_password' => 'secret',
		'db_database' => 'pinegrap',
		'admin_username' => 'admin',
		'admin_email_address' => 'admin@example.test',
		'admin_confirm_email_address' => 'admin@example.test',
		'admin_password' => 'Admin-pass-1',
		'admin_confirm_password' => 'Admin-pass-1',
		'send_email' => 'false',
	), $read['fields']);

	pg_assert_same(array(
		'starter' => 'english_default',
		'language' => 'en',
		'hostname' => 'localhost',
		'path' => '/',
		'server_software' => '',
	), $read['settings']);
}

// Each required variable is named when it is missing; an empty one counts as missing.
function test_install_cli_read_names_missing_variables()
{
	$read = install_cli_read(array('PG_INSTALL_DB_HOST' => '  ', 'PG_INSTALL_DB_PASSWORD' => 'x'), array(), install_cli_test_starters());

	pg_assert_same(array(
		'PG_INSTALL_DB_HOST is not set.',
		'PG_INSTALL_DB_NAME is not set.',
		'PG_INSTALL_DB_USER is not set.',
		'PG_INSTALL_ADMIN_EMAIL is not set.',
		'PG_INSTALL_ADMIN_PASSWORD is not set.',
	), $read['problems']);
}

// An administrator e-mail address that is not one is refused before anything is installed:
// it becomes the site's own address and the fallback recipient of the operations alerts.
function test_install_cli_read_refuses_an_admin_email_that_is_not_one()
{
	$env = install_cli_test_env();
	$env['PG_INSTALL_ADMIN_EMAIL'] = 'not-an-email';

	pg_assert_same(array('PG_INSTALL_ADMIN_EMAIL must be an e-mail address, such as admin@example.com.'), install_cli_read($env, array(), install_cli_test_starters())['problems']);

	$env['PG_INSTALL_ADMIN_EMAIL'] = 'yönetici@örnek.test';
	pg_assert_same(1, count(install_cli_read($env, array(), install_cli_test_starters())['problems']), 'what the browser field refuses is refused here too');

	$env['PG_INSTALL_ADMIN_EMAIL'] = 'Admin.Name+site@example.co.uk';
	pg_assert_same(array(), install_cli_read($env, array(), install_cli_test_starters())['problems'], 'an ordinary address');
}

// The installer writes the site's path next to its host name, so a job run from the command
// line before the site is first opened (CLI PATH comes from config.path) links to the site,
// not to the starter dump's /dev/.
function test_install_writes_the_site_path()
{
	$installer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_contains('UPDATE config SET hostname = \'" . escape($_SERVER[\'HTTP_HOST\']) . "\', path = \'" . escape(PATH) . "\'', $installer, 'hostname and path in one update');
}

// A data/config.php that names the database fills in the database variables that are not set;
// a variable that is set wins.
function test_install_cli_read_takes_the_database_from_the_config()
{
	$env = install_cli_test_env();

	unset($env['PG_INSTALL_DB_HOST'], $env['PG_INSTALL_DB_NAME'], $env['PG_INSTALL_DB_PASSWORD']);

	$read = install_cli_read($env, array(
		'PG_INSTALL_DB_HOST' => 'localhost',
		'PG_INSTALL_DB_NAME' => 'site',
		'PG_INSTALL_DB_USER' => 'config-user',
		'PG_INSTALL_DB_PASSWORD' => 'config-pass',
	), install_cli_test_starters());

	pg_assert_same(array(), $read['problems'], 'no problems');
	pg_assert_same('localhost', $read['fields']['db_host'], 'host from the config');
	pg_assert_same('site', $read['fields']['db_database'], 'name from the config');
	pg_assert_same('pinegrap', $read['fields']['db_username'], 'user from the variable');
	pg_assert_same('config-pass', $read['fields']['db_password'], 'password from the config');
}

// A quote, a backslash or a closing tag in a database value is taken as it is: the installer writes the
// values into data/config.php with pg_config_install_content(), which reads them back unchanged.
function test_install_cli_read_takes_quotes_in_database_values()
{
	$fields = array(
		'PG_INSTALL_DB_HOST' => 'db_host',
		'PG_INSTALL_DB_NAME' => 'db_database',
		'PG_INSTALL_DB_USER' => 'db_username',
		'PG_INSTALL_DB_PASSWORD' => 'db_password'
	);

	foreach ($fields as $name => $field) {

		foreach (array("it's", 'back\\slash', 'ends\\', 'x?>y') as $value) {

			$env = install_cli_test_env();

			$env[$name] = $value;

			$read = install_cli_read($env, array(), install_cli_test_starters());

			pg_assert_same(array(), $read['problems'], $name . ' = ' . $value);
			pg_assert_same($value, $read['fields'][$field], $name . ' = ' . $value . ' in the form');

		}

	}

	// The administrator's values go into queries through escape(), not into the file.
	$env = install_cli_test_env();

	$env['PG_INSTALL_ADMIN_PASSWORD'] = "o'neil\\1";

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same(array(), $read['problems'], 'quote in the administrator password');
	pg_assert_same("o'neil\\1", $read['fields']['admin_password']);
}

// The fields the command line fills in become a data/config.php that defines the database the
// variables named, quotes and backslashes included.
function test_install_cli_fields_write_a_config_that_runs()
{
	$env = install_cli_test_env();

	$env['PG_INSTALL_DB_HOST'] = "db'host";
	$env['PG_INSTALL_DB_USER'] = 'user\\';
	$env['PG_INSTALL_DB_PASSWORD'] = "p'a\\ss?>\\";
	$env['PG_INSTALL_DB_NAME'] = 'site';

	$fields = install_cli_read($env, array(), install_cli_test_starters())['fields'];

	$content = pg_config_install_content(function ($name) use ($fields) {
		return isset($fields[$name]) ? $fields[$name] : '';
	}, '0123456789abcdef0123456789abcdef');

	pg_assert_same(array(
		'DB_HOST' => "db'host",
		'DB_USERNAME' => 'user\\',
		'DB_PASSWORD' => "p'a\\ss?>\\",
		'DB_DATABASE' => 'site',
		'DEFAULT_SOFTWARE_LANGUAGE' => 'en',
	), config_file_test_values_of($content, array('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DEFAULT_SOFTWARE_LANGUAGE'), 'command line'));
}

// The starter site must be one that is there, and the language follows it unless it is set.
function test_install_cli_read_starter_and_language()
{
	$env = install_cli_test_env();

	$env['PG_INSTALL_STARTER'] = 'turkish_default';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same(array(), $read['problems'], 'no problems');
	pg_assert_same('turkish_default', $read['fields']['install_from_folder']);
	pg_assert_same('tr', $read['fields']['default_software_language'], 'language of the starter site');

	$env['PG_INSTALL_LANGUAGE'] = 'en';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same('en', $read['fields']['default_software_language'], 'language set');

	$env['PG_INSTALL_LANGUAGE'] = 'de';

	$env['PG_INSTALL_STARTER'] = 'my_backup';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same(array(
		'PG_INSTALL_STARTER is my_backup; the starter sites here are turkish_default, english_default.',
		'PG_INSTALL_LANGUAGE must be en or tr.',
	), $read['problems']);

	$read = install_cli_read(install_cli_test_env(), array(), array());

	pg_assert_same(array('PG_INSTALL_STARTER is english_default; the starter sites here are missing from data/backups.'), $read['problems'], 'no starter sites');
}

// The address, the path and the web server go into the settings, as the request would carry them.
function test_install_cli_read_address_and_server()
{
	$env = install_cli_test_env();

	$env['PG_INSTALL_HOSTNAME'] = 'localhost:8080';

	$env['PG_INSTALL_PATH'] = 'shop';

	$env['PG_INSTALL_WEB_SERVER'] = 'NGINX';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same(array(), $read['problems'], 'no problems');
	pg_assert_same('localhost:8080', $read['settings']['hostname']);
	pg_assert_same('/shop/', $read['settings']['path']);
	pg_assert_same('nginx', $read['settings']['server_software']);

	$env['PG_INSTALL_WEB_SERVER'] = 'iis';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same('Microsoft-IIS', $read['settings']['server_software'], 'iis');

	$env['PG_INSTALL_HOSTNAME'] = 'example.com/admin';

	$env['PG_INSTALL_PATH'] = '/a/../b';

	$env['PG_INSTALL_WEB_SERVER'] = 'caddy';

	$read = install_cli_read($env, array(), install_cli_test_starters());

	pg_assert_same(array(
		'PG_INSTALL_HOSTNAME must be a host name with an optional :port, such as localhost:8080.',
		'PG_INSTALL_PATH must be a URL path such as / or /shop/.',
		'PG_INSTALL_WEB_SERVER must be apache, nginx or iis.',
	), $read['problems']);
}

function test_install_cli_hostname_valid()
{
	foreach (array('localhost', 'localhost:8080', 'example.com', 'shop.example.com.tr', '127.0.0.1:8089', '[::1]:8080', '[2001:db8::1]', 'a') as $hostname) {
		pg_assert_true(install_cli_hostname_valid($hostname), $hostname);
	}

	foreach (array('', 'example.com/', 'user@example.com', 'example.com:', 'example.com:123456', '-example.com', 'example.com-', 'exa mple.com', "example.com\n", 'http://example.com') as $hostname) {
		pg_assert_false(install_cli_hostname_valid($hostname), var_export($hostname, true));
	}
}

function test_install_cli_path()
{
	pg_assert_same('/', install_cli_path(''), 'empty');
	pg_assert_same('/', install_cli_path('/'), 'root');
	pg_assert_same('/', install_cli_path('//'), 'slashes only');
	pg_assert_same('/shop/', install_cli_path('/shop'), 'no trailing slash');
	pg_assert_same('/shop/', install_cli_path('shop/'), 'no leading slash');
	pg_assert_same('/a/b-c_d.e~f/', install_cli_path('/a//b-c_d.e~f/'), 'segments');

	foreach (array('/..', '/a/./b', '/a b/', '/ürün/', '/a%20b/', '/a?b', '/a#b', '\\a', "/shop\n") as $path) {
		pg_assert_same('', install_cli_path($path), $path);
	}
}

// The screen's messages carry markup and entities; the command line prints one line of text.
function test_install_cli_text()
{
	pg_assert_same('The site was not changed: it is <kept> & "safe".', install_cli_text("The site was <b>not</b> changed:<br>it is &lt;kept&gt; &amp; &quot;safe&quot;."));
	pg_assert_same('a b c', install_cli_text("  a\n\tb  <br/>c "));
	pg_assert_same('', install_cli_text(''));
}

// A step as the screen's console prints it: seconds, the label, then the detail.
function test_install_cli_step_line()
{
	pg_assert_same('[  0.0 s] Connected to the database: pinegrap@db', install_cli_step_line(0.01, 'Connected to the database', 'pinegrap@db'));
	pg_assert_same('[ 12.3 s] Wrote the configuration', install_cli_step_line(12.34, 'Wrote the configuration'));
	pg_assert_same('[123.5 s] warning: No files: kept', install_cli_step_line(123.45, 'No files', 'kept', 'warning'));
	pg_assert_same('[1234.0 s] error: Version 2026.11.0 failed: Table x — ALTER TABLE x', install_cli_step_line(1234, 'Version 2026.11.0 failed', 'Table x — ALTER TABLE x', 'error'));
}

// The usage text names every variable that is read.
function test_install_cli_usage_lists_every_variable()
{
	$usage = install_cli_usage();

	pg_assert_contains('php pinegrap/install/index.php install', $usage);

	foreach (array_keys(install_cli_variables()) as $name) {
		pg_assert_contains('  ' . $name . ' ', $usage, $name);
	}

	pg_assert_same(12, count(install_cli_variables()), 'variables');
}

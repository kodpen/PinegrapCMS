<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Security Scan's rules on accounts and settings
 * (includes/fn/security_scan_accounts.php): a new Administrator or Designer
 * account, a new application key, a core folder anyone may write to, risky
 * PHP settings and the PHP security fixes, each as a sighting of the finding
 * model, and the reason drawn from a finding's detail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The sightings' kind => place => severity, for short comparisons.
function _test_security_accounts_map(array $sightings)
{
	$map = array();

	foreach ($sightings as $sighting) {
		$map[$sighting['kind'] . ' ' . $sighting['place']] = $sighting['severity'];
	}

	ksort($map);

	return $map;
}

function test_security_accounts_new_staff_accounts_inside_the_window()
{
	$now = 1800000000;
	$day = 86400;
	$users = array(
		array('user_id' => '1', 'user_role' => '0', 'user_created' => (string) ($now - 400 * $day)),
		array('user_id' => '7', 'user_role' => '0', 'user_created' => (string) ($now - 2 * $day)),
		array('user_id' => '8', 'user_role' => '1', 'user_created' => (string) ($now - 29 * $day)),
		array('user_id' => '9', 'user_role' => '2', 'user_created' => (string) ($now - $day)),
		array('user_id' => '10', 'user_role' => '3', 'user_created' => (string) $now),
		array('user_id' => '11', 'user_role' => '1', 'user_created' => '0'),
		array('user_id' => '12', 'user_role' => '1', 'user_created' => (string) ($now - 31 * $day)),
	);

	pg_assert_same(array(
		'account_new_admin user:7' => PG_SECURITY_SEVERITY_HIGH,
		'account_new_designer user:8' => PG_SECURITY_SEVERITY_MEDIUM,
	), _test_security_accounts_map(pg_security_accounts_sightings($users, array(), $now)));
}

function test_security_accounts_new_account_detail_holds_no_person()
{
	$now = 1800000000;
	$sightings = pg_security_accounts_sightings(array(
		array('user_id' => '7', 'user_role' => '0', 'user_created' => (string) ($now - 60), 'user_username' => 'mallory', 'user_email' => 'm@example.com'),
	), array(), $now);

	pg_assert_same(1, count($sightings));
	pg_assert_same(array('created' => $now - 60), json_decode($sightings[0]['detail'], true));
}

function test_security_accounts_new_active_application_keys()
{
	$now = 1800000000;
	$day = 86400;
	$apps = array(
		array('id' => '3', 'name' => 'Sync', 'kind' => 'server', 'status' => 'active', 'created_timestamp' => (string) ($now - $day)),
		array('id' => '4', 'name' => 'Old', 'kind' => 'server', 'status' => 'active', 'created_timestamp' => (string) ($now - 90 * $day)),
		array('id' => '5', 'name' => 'Off', 'kind' => 'server', 'status' => 'disabled', 'created_timestamp' => (string) $now),
		array('id' => '6', 'name' => 'Phone', 'kind' => 'device', 'status' => 'active', 'created_timestamp' => (string) $now),
		array('id' => '8', 'name' => 'Gone', 'kind' => 'server', 'status' => 'revoked', 'created_timestamp' => (string) $now),
	);
	$sightings = pg_security_accounts_sightings(array(), $apps, $now);

	pg_assert_same(array('api_key_new api_app:3' => PG_SECURITY_SEVERITY_MEDIUM), _test_security_accounts_map($sightings));
	pg_assert_same(array('created' => $now - $day, 'name' => 'Sync'), json_decode($sightings[0]['detail'], true));
}

// A clean environment: nothing to report.
function _test_security_settings_clean_env()
{
	return array(
		'windows' => false,
		'allow_url_include' => '0',
		'display_errors' => '0',
		'php_version' => '8.3.36',
		'php2shell' => 'fixed',
		'php2shell_fixed_in' => '8.3.36',
		'folders' => array('' => 0755, 'includes' => 0755, 'includes/fn' => 0750),
	);
}

function test_security_settings_clean_site_reports_nothing()
{
	pg_assert_same(array(), pg_security_settings_sightings(_test_security_settings_clean_env()));

	$env = _test_security_settings_clean_env();
	$env['php2shell'] = 'likely_fixed';
	pg_assert_same(array(), pg_security_settings_sightings($env), 'likely fixed counts as passed, as on System Status');
}

function test_security_settings_world_writable_core_folders()
{
	$env = _test_security_settings_clean_env();
	$env['folders'] = array('' => 0777, 'includes' => 0775, 'includes/fn' => 0757, 'install' => false);

	pg_assert_same(array(
		'core_folder_writable includes/fn' => PG_SECURITY_SEVERITY_HIGH,
		'core_folder_writable setting:software_folder' => PG_SECURITY_SEVERITY_HIGH,
	), _test_security_accounts_map(pg_security_settings_sightings($env)));

	$env['windows'] = true;
	pg_assert_same(array(), pg_security_settings_sightings($env), 'Windows has no mode bits to read');
}

function test_security_settings_risky_php_settings()
{
	$env = _test_security_settings_clean_env();
	$env['allow_url_include'] = '1';
	$env['display_errors'] = 'On';

	pg_assert_same(array(
		'php_allow_url_include setting:allow_url_include' => PG_SECURITY_SEVERITY_HIGH,
		'php_display_errors setting:display_errors' => PG_SECURITY_SEVERITY_LOW,
	), _test_security_accounts_map(pg_security_settings_sightings($env)));

	// display_errors to the error stream does not reach a visitor.
	$env['display_errors'] = 'stderr';
	$env['allow_url_include'] = 'Off';
	pg_assert_same(array(), pg_security_settings_sightings($env));
}

function test_security_settings_php_security_fixes_joined_in()
{
	$env = _test_security_settings_clean_env();
	$env['php_version'] = '8.3.6';
	$env['php2shell'] = 'missing';
	$sightings = pg_security_settings_sightings($env);

	pg_assert_same(array('php_security_fixes setting:php_version' => PG_SECURITY_SEVERITY_CRITICAL), _test_security_accounts_map($sightings));
	pg_assert_same(array('version' => '8.3.6', 'state' => 'missing', 'fixed_in' => '8.3.36'), json_decode($sightings[0]['detail'], true));

	$env['php2shell'] = 'unknown';
	pg_assert_same(array('php_security_fixes setting:php_version' => PG_SECURITY_SEVERITY_MEDIUM), _test_security_accounts_map(pg_security_settings_sightings($env)));
}

function test_security_settings_truthy_ini_values()
{
	pg_assert_true(pg_security_ini_on('1'));
	pg_assert_true(pg_security_ini_on('On'));
	pg_assert_true(pg_security_ini_on('yes'));
	pg_assert_true(pg_security_ini_on('TRUE'));
	pg_assert_false(pg_security_ini_on('0'));
	pg_assert_false(pg_security_ini_on('Off'));
	pg_assert_false(pg_security_ini_on(''));
	pg_assert_false(pg_security_ini_on(false), 'a setting PHP does not know');
}

function test_security_settings_folder_world_writable_bit()
{
	pg_assert_true(pg_security_mode_world_writable(0777));
	pg_assert_true(pg_security_mode_world_writable(040757));
	pg_assert_false(pg_security_mode_world_writable(0775));
	pg_assert_false(pg_security_mode_world_writable(false));
}

function test_security_accounts_rules_are_known_kinds()
{
	foreach (pg_security_accounts_kinds() as $kind) {
		pg_assert_true(pg_security_kind_valid($kind), $kind);
		pg_assert_true(pg_security_accounts_reason($kind, '{}') !== '', $kind . ' has a reason');
		pg_assert_true(pg_security_accounts_advice($kind) !== '', $kind . ' has advice');
	}

	pg_assert_same('', pg_security_accounts_reason('upload_php', '{}'), 'not a rule of this module');
}

function test_security_accounts_reason_reads_the_detail()
{
	$reason = pg_security_accounts_reason('php_security_fixes', json_encode(array('version' => '8.3.6', 'state' => 'missing', 'fixed_in' => '8.3.36')));
	pg_assert_contains('8.3.6', $reason);
	pg_assert_contains('8.3.36', $reason);

	$reason = pg_security_accounts_reason('api_key_new', json_encode(array('created' => 1800000000, 'name' => '<b>Sync</b>')));
	pg_assert_contains('<b>Sync</b>', $reason, 'the screen escapes; the reason stays plain text');
}

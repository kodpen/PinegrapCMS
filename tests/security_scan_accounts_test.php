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

// One run of the staff role tracker: the users it reads, the stored map
// (null before the first run) and now.
function _test_security_staff_roles($users, $stored, $now)
{
	$result = pg_security_staff_roles_track($users, $stored, $now);
	pg_assert_true(isset($result['sightings'], $result['map']) && is_array($result['sightings']) && is_array($result['map']));

	return $result;
}

function test_security_staff_roles_first_run_reports_nobody()
{
	$now = 1800000000;
	$users = array(
		array('user_id' => '1', 'user_role' => '0', 'user_created' => '0'),
		array('user_id' => '4', 'user_role' => '1', 'user_created' => (string) ($now - 900 * 86400)),
		array('user_id' => '5', 'user_role' => '2', 'user_created' => '0'),
	);
	$result = _test_security_staff_roles($users, null, $now);

	pg_assert_same(array(), $result['sightings']);
	pg_assert_same(array(
		'1' => array('role' => 0, 'since' => 0),
		'4' => array('role' => 1, 'since' => 0),
	), $result['map']);
}

function test_security_staff_roles_raise_from_manager_or_user()
{
	$now = 1800000000;
	$stored = array('1' => array('role' => 0, 'since' => 0));
	$users = array(
		array('user_id' => '1', 'user_role' => '0', 'user_created' => '0'),
		array('user_id' => '6', 'user_role' => '0', 'user_created' => (string) ($now - 500 * 86400)),
		array('user_id' => '7', 'user_role' => '1', 'user_created' => (string) ($now - 500 * 86400)),
	);
	$result = _test_security_staff_roles($users, $stored, $now);

	pg_assert_same(array(
		'account_new_admin user:6' => PG_SECURITY_SEVERITY_HIGH,
		'account_new_designer user:7' => PG_SECURITY_SEVERITY_MEDIUM,
	), _test_security_accounts_map($result['sightings']));
	pg_assert_same(array('raised' => $now), json_decode($result['sightings'][0]['detail'], true));
	pg_assert_same(array('role' => 0, 'since' => $now), $result['map']['6']);
	pg_assert_same(array('role' => 0, 'since' => 0), $result['map']['1']);
}

function test_security_staff_roles_designer_to_administrator()
{
	$now = 1800000000;
	$stored = array('3' => array('role' => 1, 'since' => 0));
	$result = _test_security_staff_roles(array(array('user_id' => '3', 'user_role' => '0', 'user_created' => '0')), $stored, $now);

	pg_assert_same(array('account_new_admin user:3' => PG_SECURITY_SEVERITY_HIGH), _test_security_accounts_map($result['sightings']));
	pg_assert_same(array('role' => 0, 'since' => $now), $result['map']['3']);

	// Lowered back to Designer: the time is kept, so it is still a sighting
	// (of the Designer kind) until the window ends.
	$later = $now + 86400;
	$result = _test_security_staff_roles(array(array('user_id' => '3', 'user_role' => '1', 'user_created' => '0')), $result['map'], $later);
	pg_assert_same(array('role' => 1, 'since' => $now), $result['map']['3']);
	pg_assert_same(array('account_new_designer user:3' => PG_SECURITY_SEVERITY_MEDIUM), _test_security_accounts_map($result['sightings']));
}

function test_security_staff_roles_inserted_with_no_creation_time_between_scans()
{
	$now = 1800000000;
	$first = _test_security_staff_roles(array(array('user_id' => '1', 'user_role' => '0', 'user_created' => '0')), null, $now - 86400);
	$users = array(
		array('user_id' => '1', 'user_role' => '0', 'user_created' => '0'),
		array('user_id' => '42', 'user_role' => '0', 'user_created' => '0'),
	);
	$result = _test_security_staff_roles($users, $first['map'], $now);

	pg_assert_same(array('account_new_admin user:42' => PG_SECURITY_SEVERITY_HIGH), _test_security_accounts_map($result['sightings']));
	pg_assert_same(array('raised' => $now), json_decode($result['sightings'][0]['detail'], true));
}

function test_security_staff_roles_lowered_then_raised_again()
{
	$now = 1800000000;
	$day = 86400;
	$stored = array('9' => array('role' => 0, 'since' => 0));

	$lowered = _test_security_staff_roles(array(array('user_id' => '9', 'user_role' => '2', 'user_created' => '0')), $stored, $now);
	pg_assert_same(array(), $lowered['sightings']);
	pg_assert_same(array(), $lowered['map']);

	$raised = _test_security_staff_roles(array(array('user_id' => '9', 'user_role' => '0', 'user_created' => '0')), $lowered['map'], $now + $day);
	pg_assert_same(array('account_new_admin user:9' => PG_SECURITY_SEVERITY_HIGH), _test_security_accounts_map($raised['sightings']));
	pg_assert_same(array('role' => 0, 'since' => $now + $day), $raised['map']['9']);
}

function test_security_staff_roles_deleted_account_is_dropped()
{
	$now = 1800000000;
	$stored = array(
		'1' => array('role' => 0, 'since' => 0),
		'8' => array('role' => 1, 'since' => $now - 86400),
	);
	$result = _test_security_staff_roles(array(array('user_id' => '1', 'user_role' => '0', 'user_created' => '0')), $stored, $now);

	pg_assert_same(array('1' => array('role' => 0, 'since' => 0)), $result['map']);
	pg_assert_same(array(), $result['sightings']);
}

function test_security_staff_roles_gone_after_the_window()
{
	$now = 1800000000;
	$day = 86400;
	$stored = array('6' => array('role' => 0, 'since' => $now));
	$users = array(array('user_id' => '6', 'user_role' => '0', 'user_created' => '0'));

	pg_assert_same(1, count(_test_security_staff_roles($users, $stored, $now + 29 * $day)['sightings']));

	$result = _test_security_staff_roles($users, $stored, $now + 31 * $day);
	pg_assert_same(array(), $result['sightings']);
	pg_assert_same(array('role' => 0, 'since' => $now), $result['map']['6']);
}

function test_security_staff_roles_new_account_left_to_the_created_rule()
{
	$now = 1800000000;
	$stored = array('1' => array('role' => 0, 'since' => 0));
	$users = array(
		array('user_id' => '1', 'user_role' => '0', 'user_created' => '0'),
		array('user_id' => '7', 'user_role' => '0', 'user_created' => (string) ($now - 3600)),
	);
	$result = _test_security_staff_roles($users, $stored, $now);

	pg_assert_same(array(), $result['sightings'], 'user_created already gives the sighting');
	pg_assert_same(array('role' => 0, 'since' => $now), $result['map']['7']);
}

function test_security_staff_roles_map_holds_no_person()
{
	$now = 1800000000;
	$users = array(array('user_id' => '7', 'user_role' => '0', 'user_created' => '0', 'user_username' => 'mallory', 'user_email' => 'm@example.com'));
	$result = _test_security_staff_roles($users, array(), $now);

	pg_assert_same(array('7' => array('role' => 0, 'since' => $now)), $result['map']);
	pg_assert_same(array('raised' => $now), json_decode($result['sightings'][0]['detail'], true));
}

function test_security_staff_roles_stored_text()
{
	pg_assert_same(null, pg_security_staff_roles_decode(''));
	pg_assert_same(array(), pg_security_staff_roles_decode('[]'));

	$map = array('1' => array('role' => 0, 'since' => 0), '12' => array('role' => 1, 'since' => 1800000000));
	pg_assert_same($map, pg_security_staff_roles_decode(pg_security_staff_roles_encode($map)));
	pg_assert_same('{}', pg_security_staff_roles_encode(array()));
}

function test_security_accounts_raised_reason_and_advice()
{
	$detail = json_encode(array('raised' => 1800000000));

	pg_assert_contains(date('Y-m-d', 1800000000), pg_security_accounts_reason('account_new_admin', $detail));
	pg_assert_contains('became an Administrator', pg_security_accounts_reason('account_new_admin', $detail));
	pg_assert_contains('became a Designer', pg_security_accounts_reason('account_new_designer', $detail));
	pg_assert_contains('gave this account its role', pg_security_accounts_advice('account_new_admin', $detail));
	pg_assert_contains('made this account', pg_security_accounts_advice('account_new_admin', json_encode(array('created' => 1800000000))));
}

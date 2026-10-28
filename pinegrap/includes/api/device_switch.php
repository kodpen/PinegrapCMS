<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The "Team devices" switch of the Application API settings card: signing in
// from staff phones turned on or off with one control, without making a team
// devices application by hand in Application Access.
//
// On, every save makes sure one device application is active and holds the
// ceiling below: the oldest active one keeps its settings and gains the
// ceiling's scopes that are valid now, or the oldest stopped one is started
// again, or a "Team devices" application is made. Module scopes are valid only
// while their module is on (api_scopes_normalise()), so a site that turns the
// Workspace on later gains workspace:write and tasks:write on its next save.
//
// Off, every active device application is disabled. The rows, their scopes and
// the devices' sessions are kept; a disabled application refuses every device
// call (api_device_app_check()), and turning the switch on again lets them
// through.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(__FILE__) . '/keys.php');
require_once(dirname(__FILE__) . '/scopes.php');

// What a team device needs: its Workspace and tasks, the orders, contacts and
// products it shows, and the site information. Each person still gets only
// what their own account may do (api_device_scopes()).
function api_device_switch_ceiling() {

	return api_scopes_normalise(array(
		'workspace:write', 'tasks:write', 'orders:read', 'meta:read', 'customers:read', 'products:read'
	));

}

// Whether a device application lets a sign-in through at $now: active and not
// past its expiry, as api_device_app_check() asks.
function api_device_switch_app_usable($app, $now) {

	return is_array($app)
		&& (isset($app['status']) && ($app['status'] === 'active'))
		&& !(((int)$app['expires_at'] > 0) && ((int)$app['expires_at'] < (int)$now));

}

// Whether the switch shows on: a device application is usable now. $apps are
// the device applications, any order.
function api_device_switch_is_on(array $apps, $now) {

	foreach ($apps as $app) {

		if (api_device_switch_app_usable($app, $now)) {

			return true;

		}

	}

	return false;

}

// The writes one save of the switch needs, worked out without a database.
//
// $apps are the device applications (id, status, expires_at, scopes as stored
// JSON), $on the posted switch, $ceiling api_device_switch_ceiling(). Answers a
// list of steps:
//
//   array('op' => 'create', 'scopes' => array(...))
//   array('op' => 'update', 'id' => int, 'status' => 'active'|'disabled',
//         'clear_expiry' => bool, 'scopes' => array(...))
//
// Only an application that changes gets a step, so a save that changes nothing
// writes nothing and leaves no audit line.
function api_device_switch_plan(array $apps, $on, array $ceiling, $now) {

	usort($apps, function ($a, $b) {
		return (int)$a['id'] - (int)$b['id'];
	});

	$steps = array();

	if (!$on) {

		foreach ($apps as $app) {

			if (isset($app['status']) && ($app['status'] === 'active')) {

				$steps[] = array(
					'op'           => 'update',
					'id'           => (int)$app['id'],
					'status'       => 'disabled',
					'clear_expiry' => false,
					'scopes'       => api_device_switch_stored_scopes($app)
				);

			}

		}

		return $steps;

	}

	// The one that already lets devices in, else the oldest of the rest.
	$chosen = null;

	foreach ($apps as $app) {

		if (api_device_switch_app_usable($app, $now)) {

			$chosen = $app;

			break;

		}

	}

	if (($chosen === null) && !empty($apps)) {

		$chosen = $apps[0];

	}

	if ($chosen === null) {

		return array(array('op' => 'create', 'scopes' => api_device_switch_merge(array(), $ceiling)));

	}

	$stored = api_device_switch_stored_scopes($chosen);

	$scopes = api_device_switch_merge($stored, $ceiling);

	$usable = api_device_switch_app_usable($chosen, $now);

	if ($usable && ($scopes === $stored)) {

		return array();

	}

	return array(array(
		'op'           => 'update',
		'id'           => (int)$chosen['id'],
		'status'       => 'active',
		'clear_expiry' => !$usable && ((int)$chosen['expires_at'] > 0) && ((int)$chosen['expires_at'] < (int)$now),
		'scopes'       => $scopes
	));

}

// An application's stored scopes as a sorted list.
function api_device_switch_stored_scopes($app) {

	$scopes = json_decode(isset($app['scopes']) ? (string)$app['scopes'] : '', true);

	$scopes = is_array($scopes) ? array_values(array_unique(array_map('strval', $scopes))) : array();

	sort($scopes);

	return $scopes;

}

// The stored scopes with the ceiling added: nothing the operator chose is
// taken away.
function api_device_switch_merge(array $stored, array $ceiling) {

	$scopes = array_values(array_unique(array_merge($stored, $ceiling)));

	sort($scopes);

	return $scopes;

}

// The device applications, or an empty list before api_apps has its kind
// column.
function api_device_switch_apps() {

	$rows = db_items("SELECT id, name, status, expires_at, scopes FROM api_apps WHERE kind = 'device' ORDER BY id ASC");

	return is_array($rows) ? $rows : array();

}

// Whether the switch shows on today.
function api_device_switch_state() {

	return api_device_switch_is_on(api_device_switch_apps(), time());

}

// Saves the switch: runs api_device_switch_plan() against the stored rows,
// with an audit line and a Site Log line in $username's name for each
// application it makes or changes. $owner_id owns an application it makes.
function api_device_switch_save($on, $owner_id, $username) {

	$apps = api_device_switch_apps();

	$names = array();

	foreach ($apps as $app) {

		$names[(int)$app['id']] = (string)$app['name'];

	}

	$steps = api_device_switch_plan($apps, (bool)$on, api_device_switch_ceiling(), time());

	foreach ($steps as $step) {

		if ($step['op'] === 'create') {

			api_app_create(lang('Team devices'), '', $step['scopes'], (int)$owner_id, false, (string)$username, 'device');

			continue;

		}

		$id = (int)$step['id'];

		$audit_before = pg_audit_fetch('api_apps', $id);

		db("UPDATE api_apps SET
				status = '" . (($step['status'] === 'active') ? 'active' : 'disabled') . "',
				scopes = '" . escape(json_encode($step['scopes'])) . "',"
				. ($step['clear_expiry'] ? " expires_at = '0'," : '') . "
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "' AND kind = 'device'");

		pg_audit_record('update', 'api_apps', $id, $audit_before);

		log_activity(lang(array(
			'string' => 'API application ({var:1}) was changed.',
			'vars'   => isset($names[$id]) ? $names[$id] : (string)$id
		)), (string)$username);

	}

}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// People signed in to the API from their own device.
//
// An application key and secret belong to a server. A phone in a staff
// member's pocket is a different kind of caller on both counts: it cannot keep
// a secret - whatever ships inside an app can be read back out of it - and it
// should not act as whoever happened to create the key. It acts as the person
// who signed in on it, with that person's rights, and it can be taken away from
// them without touching anybody else's.
//
// So a device holds two tokens and no secret. The access token is sent on every
// call and lives an hour, which bounds what a copied token is worth. The
// refresh token only ever travels to /auth/refresh and is replaced each time it
// is used; the one it replaced is remembered for a moment, so an app whose
// connection dropped between the request and the answer is not signed out, and
// recognised afterwards, when seeing it again means somebody else holds a copy.
//
// Neither token is stored. The rows keep their HMACs, keyed with the site's
// ENCRYPTION_KEY exactly like an application secret (includes/api/keys.php), so
// a copy of the database alone does not sign anybody in.
//
// The operator's control is an application of the 'device' kind. Its status is
// the switch for signing in from devices at all, its scopes are the ceiling of
// what any device may reach, and its rate limit is applied to each device on
// its own.
//
// Loaded by the API entry point and, for the revocations, by the panel screens
// that end a person's sessions - which is why it is gated on init rather than
// on the API constant, and why those functions use the shared db helpers.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

// How long each token lives, in seconds.
function api_device_access_lifetime() {

	return 3600;

}

function api_device_refresh_lifetime() {

	return 30 * 86400;

}

// How long the refresh token that was just replaced keeps working. Long enough
// for a retry after a dropped connection, short enough that a stolen copy used
// later is caught rather than honoured.
function api_device_refresh_grace() {

	return 60;

}

// The most devices one person may keep signed in through one application. The
// least recently used one gives way to a new sign-in rather than the sign-in
// being refused: a person replacing their phone should not have to find the
// old one first.
function api_device_limit_per_user() {

	return 20;

}

// Whether the tables are there. Files land before the schema does.
function api_devices_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = ((int)db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'api_devices'") > 0);

	}

	return $ready;

}

// A fresh token. The prefix says on sight which of the two it is - in a log, a
// crash report, a support message - and gives secret scanners something to
// match.
function api_device_token_new($kind) {

	return (($kind === 'refresh') ? 'pgrt_' : 'pgat_') . bin2hex(random_bytes(24));

}

// The application that devices sign in through.
//
// A client that names its key gets that application, provided it is a device
// application. A client that names none - a general mobile app that only knows
// the site's address - gets the site's device application; with more than one,
// the oldest active one, so the answer does not change when a second is added
// for testing.
function api_device_app($client_key = '') {

	$client_key = trim((string)$client_key);

	$where = ($client_key !== '')
		? "api_key = '" . escape($client_key) . "'"
		: "status = 'active'";

	return api_row("SELECT id, name, api_key, owner_user_id, scopes, ip_allowlist,
			status, expires_at, rate_limit_per_min, kind
		FROM api_apps
		WHERE kind = 'device' AND " . $where . "
		ORDER BY id ASC
		LIMIT 1");

}

// Whether a device application may be used right now. Answers and exits when it
// may not.
function api_device_app_check($app) {

	if (($app === null) || ($app['status'] !== 'active')) {

		api_fail(403, 'device_signin_unavailable', lang('Signing in from a device is not switched on for this site.'));

	}

	if (((int)$app['expires_at'] > 0) && ((int)$app['expires_at'] < time())) {

		api_fail(403, 'application_expired', lang('This application key has expired.'));

	}

	api_check_ip_allowlist($app);

}

// What a device may do: the application's ceiling narrowed to what the person
// may delegate today, recomputed on every call so a demotion takes effect on
// the next request. Two adjustments on top of that:
//
//   * webhooks:manage never. A subscription sends the site's events to an
//     address; that is set up once by an integration, not by a phone.
//   * account:read and account:write always. They cover the person's own
//     notifications, devices and push registration, which is what signing in
//     from a device is for in the first place.
function api_device_scopes($app, $owner) {

	$granted = json_decode((string)$app['scopes'], true);

	$scopes = api_effective_scopes(is_array($granted) ? $granted : array(), $owner);

	$scopes = array_values(array_diff($scopes, array('webhooks:manage')));

	foreach (array('account:read', 'account:write') as $scope) {

		if (!in_array($scope, $scopes, true)) {

			$scopes[] = $scope;

		}

	}

	sort($scopes);

	return $scopes;

}

// Whether an account has anything a device could be used for: some right of
// its own beyond the site information and its own account. A member of a
// membership site is a row in the same table and signs in with the same form,
// and has no business holding a staff token.
function api_device_account_has_rights($owner) {

	$own = array_diff(api_owner_scopes($owner), array('meta:read', 'account:read', 'account:write'));

	return !empty($own);

}

// Signs a device in: writes its row and hands back both tokens. The only moment
// either token exists in the clear.
function api_device_issue($app, $user_id, $name, $platform, $app_version) {

	$access  = api_device_token_new('access');
	$refresh = api_device_token_new('refresh');

	$now = time();

	$access_expires  = $now + api_device_access_lifetime();
	$refresh_expires = $now + api_device_refresh_lifetime();

	// Room for this one first, so the count never passes the limit.
	$keep = api_device_limit_per_user() - 1;

	$older = api_rows("SELECT id FROM api_devices
		WHERE user_id = '" . (int)$user_id . "' AND app_id = '" . (int)$app['id'] . "'
		ORDER BY GREATEST(last_used_timestamp, created_timestamp) DESC, id DESC
		LIMIT 1000");

	foreach (array_slice($older, $keep) as $row) {

		api_device_revoke((int)$row['id']);

	}

	api_exec("INSERT INTO api_devices
		(app_id, user_id, name, platform, app_version, access_hash, access_expires,
		 refresh_hash, refresh_expires, created_timestamp, last_used_timestamp, last_used_ip)
		VALUES (
			'" . (int)$app['id'] . "',
			'" . (int)$user_id . "',
			'" . escape(mb_substr((string)$name, 0, 100)) . "',
			'" . escape(mb_substr((string)$platform, 0, 20)) . "',
			'" . escape(mb_substr((string)$app_version, 0, 40)) . "',
			'" . escape(api_secret_hash($access)) . "',
			'" . $access_expires . "',
			'" . escape(api_secret_hash($refresh)) . "',
			'" . $refresh_expires . "',
			'" . $now . "',
			'" . $now . "',
			'" . escape(mb_substr(api_client_ip(), 0, 45)) . "'
		)");

	return array(
		'device_id'       => api_insert_id(),
		'access_token'    => $access,
		'access_expires'  => $access_expires,
		'refresh_token'   => $refresh,
		'refresh_expires' => $refresh_expires
	);

}

// Exchanges a refresh token for a new pair. Answers and exits when the token is
// not one that may be exchanged.
//
// The token is looked up among the current ones first. Found among the
// previous ones instead, it was already exchanged: within the grace period that
// is the same app retrying and it is exchanged again; after it, somebody else
// is holding a copy, and the device is ended so that neither copy works.
function api_device_rotate($refresh_token) {

	$hash = api_secret_hash(trim((string)$refresh_token));

	$row = api_row("SELECT * FROM api_devices WHERE refresh_hash = '" . escape($hash) . "' LIMIT 1");

	if ($row === null) {

		$replayed = api_row("SELECT id, user_id, refresh_rotated_at FROM api_devices
			WHERE refresh_prev_hash = '" . escape($hash) . "' LIMIT 1");

		if ($replayed === null) {

			api_fail(401, 'unauthorized', lang('The refresh token is not valid. Sign in again.'));

		}

		if ((time() - (int)$replayed['refresh_rotated_at']) > api_device_refresh_grace()) {

			api_device_revoke((int)$replayed['id']);

			log_activity(lang(array(
				'string' => 'a device was signed out because a used refresh token was presented again (device {var:1})',
				'vars'   => (int)$replayed['id']
			)), (string)api_value("SELECT user_username FROM user WHERE user_id = '" . (int)$replayed['user_id'] . "' LIMIT 1"));

			api_fail(401, 'unauthorized', lang('The refresh token is not valid. Sign in again.'));

		}

		$row = api_row("SELECT * FROM api_devices WHERE id = '" . (int)$replayed['id'] . "' LIMIT 1");

		if ($row === null) {

			api_fail(401, 'unauthorized', lang('The refresh token is not valid. Sign in again.'));

		}

	} elseif ((int)$row['refresh_expires'] < time()) {

		api_device_revoke((int)$row['id']);

		api_fail(401, 'unauthorized', lang('The device has been signed out for too long. Sign in again.'));

	}

	$access  = api_device_token_new('access');
	$refresh = api_device_token_new('refresh');

	$now = time();

	$access_expires  = $now + api_device_access_lifetime();
	$refresh_expires = $now + api_device_refresh_lifetime();

	// The hash the caller just presented becomes the previous one. On a retry
	// within the grace period that is the same hash again, which is right: it
	// is still the one this app last held.
	api_exec("UPDATE api_devices SET
			access_hash = '" . escape(api_secret_hash($access)) . "',
			access_expires = '" . $access_expires . "',
			refresh_prev_hash = '" . escape($hash) . "',
			refresh_hash = '" . escape(api_secret_hash($refresh)) . "',
			refresh_expires = '" . $refresh_expires . "',
			refresh_rotated_at = '" . $now . "',
			last_used_timestamp = '" . $now . "',
			last_used_ip = '" . escape(mb_substr(api_client_ip(), 0, 45)) . "'
		WHERE id = '" . (int)$row['id'] . "'");

	$row['access_expires']  = $access_expires;
	$row['refresh_expires'] = $refresh_expires;

	return array(
		'row'             => $row,
		'access_token'    => $access,
		'access_expires'  => $access_expires,
		'refresh_token'   => $refresh,
		'refresh_expires' => $refresh_expires
	);

}

// A call made with an access token. Returns the application in the shape every
// handler already reads - owner, scopes, rate limit - with the person who
// signed in as its owner, or answers and exits.
function api_device_authenticate($token) {

	if (!api_devices_ready()) {

		api_fail(401, 'unauthorized', lang('Invalid credentials.'));

	}

	$row = api_row("SELECT * FROM api_devices
		WHERE access_hash = '" . escape(api_secret_hash($token)) . "' LIMIT 1");

	if ($row === null) {

		// Not told apart from a token that was never issued, for the same reason
		// a wrong secret is not told apart from an unknown key.
		//
		// Unlike a wrong secret it is not reported to the firewall. A token is
		// 192 random bits, so there is no guessing to stop, and the usual way
		// to arrive here is a phone whose device was signed out from the panel
		// and has not noticed yet - counted as offences, one confused app would
		// get its whole office's address banned.
		api_fail(401, 'unauthorized', lang('The access token is not valid. Renew it, or sign in again.'));

	}

	if ((int)$row['access_expires'] < time()) {

		// Expected an hour after every sign-in, so not an offence: the client
		// renews and carries on.
		api_fail(401, 'token_expired', lang('The access token has expired. Renew it with the refresh token.'));

	}

	$app = api_row("SELECT id, name, api_key, owner_user_id, scopes, ip_allowlist,
			status, expires_at, rate_limit_per_min, kind
		FROM api_apps
		WHERE id = '" . (int)$row['app_id'] . "' AND kind = 'device'
		LIMIT 1");

	api_device_app_check($app);

	// Known from here on, so a refusal below still logs under the application.
	api_current_app($app);

	$owner = api_load_owner((int)$row['user_id']);

	if ($owner === null) {

		api_fail(403, 'owner_unavailable', lang('The account that signed in on this device is no longer available.'));

	}

	if (!api_device_account_has_rights($owner)) {

		api_fail(403, 'forbidden', lang('This account has no rights that can be used from a device.'));

	}

	// Last use, at most once a minute: the row is read on every call, and
	// writing it on every call as well would double the cost of a busy phone
	// for a figure nobody reads to the second.
	if ((time() - (int)$row['last_used_timestamp']) >= 60) {

		api_exec("UPDATE api_devices SET
				last_used_timestamp = UNIX_TIMESTAMP(),
				last_used_ip = '" . escape(mb_substr(api_client_ip(), 0, 45)) . "'
			WHERE id = '" . (int)$row['id'] . "'");

	}

	$scopes = api_device_scopes($app, $owner);

	// The name every activity line and audit row uses for the caller. The
	// person comes first because that is who did it; the device says from
	// where.
	$app['name'] = mb_substr($owner['username'] . ' (' . (($row['name'] !== '') ? $row['name'] : $app['name']) . ')', 0, 100);

	$app['owner']         = $owner;
	$app['owner_user_id'] = $owner['id'];
	$app['scopes']        = $scopes;
	$app['device']        = $row;

	api_current_app($app);

	api_current_scopes($scopes);

	return $app;

}

// The calls one device made this minute, against its application's ceiling.
// The same single upsert as the application bucket: see ratelimit.php.
function api_device_rate_hits($device_id) {

	$window = time() - (time() % 60);

	api_exec("INSERT INTO api_device_rate (device_id, window_start, hits)
		VALUES ('" . (int)$device_id . "', '" . $window . "', 1)
		ON DUPLICATE KEY UPDATE hits = hits + 1");

	return array(
		'window' => $window,
		'hits'   => (int)api_value("SELECT hits FROM api_device_rate
			WHERE device_id = '" . (int)$device_id . "' AND window_start = '" . $window . "'")
	);

}

// The device a call was made from, or null when it was made with an
// application key.
function api_current_device() {

	$app = api_current_app();

	return (is_array($app) && isset($app['device']) && is_array($app['device'])) ? $app['device'] : null;

}

// One device as the API shows it.
function api_device_present($row, $current_id = 0) {

	return array(
		'id'             => (int)$row['id'],
		'name'           => (string)$row['name'],
		'platform'       => (string)$row['platform'],
		'app_version'    => (string)$row['app_version'],
		'current'        => ((int)$row['id'] === (int)$current_id),
		'application'    => array(
			'id'   => (int)$row['app_id'],
			'name' => isset($row['app_name']) ? (string)$row['app_name'] : ''
		),
		'created_at'     => api_time($row['created_timestamp']),
		'last_used_at'   => api_time($row['last_used_timestamp']),
		'last_used_ip'   => (string)$row['last_used_ip'],
		'signed_in_until' => api_time($row['refresh_expires'])
	);

}

function api_device_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string',
		'platform'        => 'string',
		'app_version'     => 'string',
		'current'         => 'boolean',
		'application'     => array('id' => 'integer', 'name' => 'string'),
		'created_at'      => 'string',
		'last_used_at'    => 'string?',
		'last_used_ip'    => 'string',
		'signed_in_until' => 'string?'
	);

}

/* ---------------------------------------------------------------------------
   Ending sessions. These run from panel screens as well as from the API, so
   they use the shared helpers and ask for the table first.
   --------------------------------------------------------------------------- */

// Ends one device: its row, its rate counters and the push registration it
// made, so a phone that was signed out stops being woken as well.
function api_device_revoke($device_id) {

	$device_id = (int)$device_id;

	if (($device_id < 1) || !api_devices_ready()) {

		return;

	}

	db("DELETE FROM api_devices WHERE id = '" . $device_id . "'");

	db("DELETE FROM api_device_rate WHERE device_id = '" . $device_id . "'");

	api_device_forget_push('api:' . $device_id);

}

// Every device a person holds - called wherever the panel ends all of their
// sessions: a password change or reset, "sign out everywhere", a deleted or
// blocked account.
function api_devices_revoke_user($user_id) {

	$user_id = (int)$user_id;

	if (($user_id < 1) || !api_devices_ready()) {

		return;

	}

	foreach (db_values("SELECT id FROM api_devices WHERE user_id = '" . $user_id . "'") as $device_id) {

		api_device_revoke((int)$device_id);

	}

}

// Every device signed in through one application - when it is deleted.
function api_devices_revoke_app($app_id) {

	$app_id = (int)$app_id;

	if (($app_id < 1) || !api_devices_ready()) {

		return;

	}

	foreach (db_values("SELECT id FROM api_devices WHERE app_id = '" . $app_id . "'") as $device_id) {

		api_device_revoke((int)$device_id);

	}

}

function api_device_forget_push($selector) {

	if (!defined('PG_FUNCTIONS_DIR')) {

		return;

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

	if (!pg_push_schema_available() || !pg_push_has_selector()) {

		return;

	}

	db("DELETE FROM push_subscriptions WHERE auth_selector = '" . escape($selector) . "'");

}

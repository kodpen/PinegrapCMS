<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Pairing a team device with a code instead of a password.
//
// A person signed in to the panel shows a code on device_pairing.php as a QR;
// the team app scans it and sends it to POST /auth/pair, which answers with a
// device session exactly as POST /auth/login does. That lets an account that
// signs in with a passkey or Google, and has no password to type, use the app,
// and keeps a password off a phone's keyboard.
//
// A code is "pgpc_" and 48 hex characters from random_bytes(). Only its HMAC
// is stored (api_secret_hash(), keyed with ENCRYPTION_KEY like the device
// tokens), so the table alone signs nobody in. A code lives five minutes, works
// once, and is bound to the person who made it and to the device application
// that was active then. Making a new code deletes the person's unused ones.
//
// Loaded by the API entry point and by the panel screens that end a person's
// sessions, like devices.php, so it is gated on init.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

// How long a code may be exchanged, in seconds.
function api_pairing_lifetime() {

	return 300;

}

// How many codes one person may make in an hour, and how many failed
// exchanges one address may send in fifteen minutes.
function api_pairing_limits() {

	return array(
		'codes_per_hour'   => 10,
		'failures'         => 10,
		'failure_window'   => 900
	);

}

// Whether the table is there. Files land before the schema does.
function api_pairing_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = ((int)db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'api_device_pairings'") > 0);

	}

	return $ready;

}

// A fresh code. The prefix says on sight what it is, in a log or a support
// message, and gives secret scanners something to match.
function api_pairing_code_new() {

	return 'pgpc_' . bin2hex(random_bytes(24));

}

// Whether a string has the shape of a code: anything else is refused before
// it is hashed or looked up.
function api_pairing_code_shape_ok($code) {

	return is_string($code) && (preg_match('/^pgpc_[0-9a-f]{48}$/', $code) === 1);

}

// What the QR holds. Not a URL, so a phone's camera app opens nothing; the
// team app reads the API base and the code from it.
function api_pairing_payload($api_base, $code) {

	return json_encode(array(
		'pinegrap' => 'pair',
		'v'        => 1,
		'api_base' => (string)$api_base,
		'code'     => (string)$code
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}

// Whether a stored code may be exchanged at $now for the device application
// $app_id: not used, not past its time, made for that application.
function api_pairing_usable($row, $app_id, $now) {

	return is_array($row)
		&& ((int)$row['used_timestamp'] === 0)
		&& ((int)$row['expires_timestamp'] > (int)$now)
		&& ((int)$row['app_id'] === (int)$app_id)
		&& ((int)$row['user_id'] > 0);

}

// The device application a code is made for: the one api_device_app('')
// signs a device in through when no key is named, read with the shared
// helpers so the panel screen can ask without the API's own db layer.
function api_pairing_device_app() {

	$app = db_item("SELECT id, name, status, expires_at FROM api_apps
		WHERE kind = 'device' AND status = 'active'
		ORDER BY id ASC
		LIMIT 1");

	return is_array($app) ? $app : null;

}

// Why a code cannot be made for this person right now, as a sentence for the
// screen, or '' when it can. $impersonating is the panel's "signed in as
// another user"; $app is api_device_app(''); $has_rights is
// api_device_account_has_rights() of the person.
function api_pairing_refusal($impersonating, $app, $has_rights, $now) {

	if ($impersonating) {

		return lang('A device pairing code cannot be made while signed in as another user.');

	}

	if (!is_array($app) || ($app['status'] !== 'active')
		|| (((int)$app['expires_at'] > 0) && ((int)$app['expires_at'] < (int)$now))) {

		return lang('Signing in from a device is not switched on for this site.');

	}

	if (!$has_rights) {

		return lang('This account has no rights that can be used from a device.');

	}

	return '';

}

// Makes a code for a person and answers array('code', 'expires') with the
// plain code, which exists only here; null when the person has made too many
// this hour or the row could not be written. Unused codes of the person go,
// and so does any row a day old, so the table stays small without a job.
function api_pairing_create($app_id, $user_id, $ip) {

	$app_id  = (int)$app_id;
	$user_id = (int)$user_id;

	if (($app_id < 1) || ($user_id < 1) || !api_pairing_ready()) {

		return null;

	}

	$limits = api_pairing_limits();

	if (function_exists('waf_rate_record')
		&& (waf_rate_record('u:' . $user_id, 'pairc', 3600) > $limits['codes_per_hour'])) {

		return null;

	}

	db("DELETE FROM api_device_pairings
		WHERE (user_id = '" . $user_id . "' AND used_timestamp = 0)
		OR created_timestamp < " . (time() - 86400));

	$code = api_pairing_code_new();

	$expires = time() + api_pairing_lifetime();

	$written = db("INSERT INTO api_device_pairings
		(app_id, user_id, code_hash, created_timestamp, expires_timestamp, created_ip)
		VALUES (
			'" . $app_id . "',
			'" . $user_id . "',
			'" . escape(api_secret_hash($code)) . "',
			UNIX_TIMESTAMP(),
			'" . $expires . "',
			'" . escape(substr((string)$ip, 0, 45)) . "'
		)");

	return ($written === false) ? null : array('code' => $code, 'expires' => $expires);

}

// Takes back the person's unused codes: Cancel on the screen.
function api_pairing_cancel($user_id) {

	$user_id = (int)$user_id;

	if (($user_id < 1) || !api_pairing_ready()) {

		return;

	}

	db("DELETE FROM api_device_pairings WHERE user_id = '" . $user_id . "' AND used_timestamp = 0");

}

// The stored row of a code, or null for a code of the wrong shape or none.
function api_pairing_find($code) {

	if (!api_pairing_code_shape_ok($code) || !api_pairing_ready()) {

		return null;

	}

	return db_item("SELECT id, app_id, user_id, used_timestamp, expires_timestamp
		FROM api_device_pairings
		WHERE code_hash = '" . escape(api_secret_hash($code)) . "'
		LIMIT 1") ?: null;

}

// Marks a code used, once: the conditional UPDATE lets only one of two
// exchanges that arrive together through. Answers whether this call won.
function api_pairing_consume($row) {

	db("UPDATE api_device_pairings SET used_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . (int)$row['id'] . "'
		AND used_timestamp = 0
		AND expires_timestamp > UNIX_TIMESTAMP()");

	return (mysqli_affected_rows(db::$con) === 1);

}

// Notes the device session an exchange made.
function api_pairing_note_device($row, $device_id) {

	db("UPDATE api_device_pairings SET device_id = '" . (int)$device_id . "'
		WHERE id = '" . (int)$row['id'] . "'");

}

// Pending codes go with the sessions: when a person's devices are signed out
// (a new password, "sign out everywhere") or an application is deleted.
function api_pairings_forget_user($user_id) {

	api_pairing_cancel($user_id);

}

function api_pairings_forget_app($app_id) {

	$app_id = (int)$app_id;

	if (($app_id < 1) || !api_pairing_ready()) {

		return;

	}

	db("DELETE FROM api_device_pairings WHERE app_id = '" . $app_id . "' AND used_timestamp = 0");

}

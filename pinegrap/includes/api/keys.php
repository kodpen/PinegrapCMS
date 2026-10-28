<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// How an application's credentials are made and stored. Shared by the public
// endpoint, which verifies them, and the panel, which issues them.
//
// The key is public and is stored in the clear: it identifies the application,
// it is indexed, and it is printed on screen. The secret is what authenticates
// and is never stored in a form it can be read back from - the panel shows it
// once, at the moment it is made, and afterwards can only show the last four
// characters.
//
// The hash is an HMAC keyed with ENCRYPTION_KEY rather than a slow password
// hash. That is a deliberate trade: the secret is 44 random characters, so
// guessing it is not a thing that happens, and a marketplace sync makes
// thousands of calls where a deliberately slow hash would be felt on every one.
// The keying matters - a stolen database without the config file cannot be
// searched for a matching secret.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Prefixes exist so that a key found in a log file, a repository or a support
// message is recognisable on sight, and so that automated secret scanners have
// something to match.
function api_key_prefix() {

	return 'pg_live_';

}

function api_secret_prefix() {

	return 'pgsk_';

}

function api_generate_key() {

	return api_key_prefix() . get_random_string(array(
		'type'   => 'lowercase_letters_and_numbers',
		'length' => 32
	));

}

// A key for the documentation screen's temporary credential. The prefix is what
// tells it apart on sight - in a log, in a support message - from a key that
// belongs to a real integration.
function api_generate_test_key() {

	return 'pg_test_' . get_random_string(array(
		'type'   => 'lowercase_letters_and_numbers',
		'length' => 32
	));

}

function api_generate_secret() {

	return api_secret_prefix() . get_random_string(array(
		'type'   => 'letters_and_numbers',
		'length' => 44
	));

}

// Deterministic, so a supplied secret can be looked up and compared without
// storing anything reversible.
//
// Note that this is keyed with ENCRYPTION_KEY: replacing that key from the
// settings screen invalidates every stored API secret, exactly as it already
// invalidates the other keyed material on the site. Applications then need a
// fresh secret issued.
function api_secret_hash($secret) {

	return hash_hmac('sha256', (string)$secret, ENCRYPTION_KEY);

}

// The tail the panel shows in place of a secret it can no longer read.
function api_secret_hint($secret) {

	return substr((string)$secret, -4);

}

// Whether a stored secret hash takes the hash of a supplied secret. An empty
// stored hash takes nothing, an empty supplied hash included, which
// hash_equals() alone would call equal: a 'server' key made without a
// secret (api_app_create() with $with_secret false) and a previous secret
// that was cleared open nothing.
function api_secret_hash_accepts($stored_hash, $supplied_hash) {

	$stored_hash   = (string)$stored_hash;
	$supplied_hash = (string)$supplied_hash;

	return ($stored_hash !== '') && ($supplied_hash !== '') && hash_equals($stored_hash, $supplied_hash);

}

// Whether an application row has any way to sign in at $now. A 'device' or
// 'oauth' application signs people in with tokens (includes/api/devices.php,
// includes/oauth/); every other row only with its key and a secret
// (api_secret_matches()), so it needs a stored secret hash, or the previous
// one while its grace runs. Pinegrap AI's key, made without a secret, answers
// false: it is an identity and a set of scopes inside the site, never a way
// in. The status is not looked at: a disabled row is refused later anyway.
function api_app_can_sign_in(array $app, $now) {

	$kind = isset($app['kind']) ? (string)$app['kind'] : 'server';

	if (($kind === 'device') || ($kind === 'oauth')) {

		return true;

	}

	if (isset($app['api_secret_hash']) && ((string)$app['api_secret_hash'] !== '')) {

		return true;

	}

	return isset($app['api_secret_prev_hash'], $app['api_secret_prev_expires'])
		&& ((string)$app['api_secret_prev_hash'] !== '')
		&& ((int)$app['api_secret_prev_expires'] > (int)$now);

}

// Whether a new secret may be issued for an application row: only for a
// 'server' key that already has one. A 'device' or 'oauth' application has
// none to replace. A 'server' key made without a secret (api_app_create()
// with $with_secret false, Pinegrap AI's) stays without one: it is an
// identity inside the site, and a secret would turn it into a way in carrying
// its owner's rights for whoever issued it, Application Access being open to
// roles 0-2. Both places that issue a secret ask this: Application Access's
// rotation and api_app_rotate_secret().
function api_app_secret_can_rotate(array $app) {

	$kind = isset($app['kind']) ? (string)$app['kind'] : 'server';

	return ($kind === 'server') && isset($app['api_secret_hash']) && ((string)$app['api_secret_hash'] !== '');

}

// Whether Application Access refuses an application row to this editor. The
// screen is open to roles 0-2, and a key calls the API with its owner's
// rights as the cap, so an editor may not save, rotate, delete or touch the
// webhooks and devices of a row whose owner outranks them (a lower user_role
// number: administrator 0, Designer 1, Manager 2). Equal rank is allowed. A
// row whose owner was deleted (NULL from the LEFT JOIN) is not locked: its
// key answers owner_unavailable, so it carries no rights and may be cleared.
function api_app_row_locked($owner_role, $editor_role) {

	if ($owner_role === null || (string)$owner_role === '') {

		return false;

	}

	return (int)$owner_role < (int)$editor_role;

}

// The kind an application row is made as, from a posted value: a team devices
// application ('device', includes/api/devices.php) or an OAuth application
// ('oauth', includes/oauth/), anything else the column's default, 'server'.
function api_app_kind($kind) {

	return (is_string($kind) && in_array($kind, array('device', 'oauth'), true)) ? $kind : 'server';

}

// Whether a new row of $kind is given a secret. A 'device' or 'oauth'
// application never is: the app it is typed into cannot keep one, and people
// sign in through it with their own accounts. A 'server' row only when the
// caller asks: Pinegrap AI's key is made without one.
function api_app_issues_secret($kind, $with_secret) {

	return ($kind === 'server') && (bool)$with_secret;

}

// Makes an application key: Application Access's "New application", and the
// assistants' cards in the Workspace settings, which always make 'server'
// keys. $kind goes through api_app_kind(). $owner_id owns the key and is
// recorded as the one who made it; its scopes are capped by that account's
// rights on every call (api_effective_scopes()).
//
// With $with_secret a 'server' row gets a secret, and only its hash and its
// last four characters are stored; without one the key cannot sign in at all,
// since api_secret_matches() needs a stored hash (api_app_issues_secret()).
// The plain secret exists only in the answer: the caller shows it once and
// keeps it nowhere. The audit line masks api_key and api_secret_* by name
// (pg_audit_secret_key()), and the Site Log line names the application only.
// $username is whose name that line carries.
//
// Answers array('id' => int, 'key' => string, 'secret' => string, empty
// without a secret, 'kind' => string), or null when the row could not be
// written.
function api_app_create($name, $description, array $scopes, $owner_id, $with_secret, $username, $kind = 'server') {

	require_once(dirname(__FILE__) . '/scopes.php');

	$kind = api_app_kind($kind);

	$key    = api_generate_key();
	$secret = api_app_issues_secret($kind, $with_secret) ? api_generate_secret() : '';

	$written = db("INSERT INTO api_apps
		(name, description, api_key, api_secret_hash, api_secret_hint, owner_user_id,
		 scopes, status, rate_limit_per_min, kind, created_user_id, created_timestamp, updated_timestamp)
		VALUES (
			'" . escape((string)$name) . "',
			'" . escape((string)$description) . "',
			'" . escape($key) . "',
			'" . escape(($secret !== '') ? api_secret_hash($secret) : '') . "',
			'" . escape(($secret !== '') ? api_secret_hint($secret) : '') . "',
			'" . (int)$owner_id . "',
			'" . escape(json_encode(api_scopes_normalise($scopes))) . "',
			'active',
			'120',
			'" . $kind . "',
			'" . (int)$owner_id . "',
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP()
		)");

	$id = (int)mysqli_insert_id(db::$con);

	if (($written === false) || ($id <= 0)) {

		return null;

	}

	pg_audit_record('create', 'api_apps', $id);

	log_activity(lang(array(
		'string' => 'API application ({var:1}) was created.',
		'vars'   => (string)$name
	)), (string)$username);

	return array('id' => $id, 'key' => $key, 'secret' => $secret, 'kind' => $kind);

}

// Issues a new secret for a 'server' application that has one. Any other kind
// has none to replace, and a key made without a secret is never given one
// (api_app_secret_can_rotate()): both are refused. With $grace_seconds the
// outgoing secret keeps answering that long, the way Application Access
// rotates. With 0 it stops at once, and so does a previous secret still inside
// an earlier rotation's grace: api_secret_matches() then accepts only the new
// one. Application Access gives 24 hours, Claude's "Reset secret" none.
//
// The plain secret exists only in the answer, as with api_app_create().
// Answers array('name', 'key', 'secret'), or null when there is no such
// application, it may not be given a secret, or the row was not written.
function api_app_rotate_secret($app_id, $grace_seconds, $username) {

	$app_id = (int)$app_id;

	$grace_seconds = max(0, (int)$grace_seconds);

	$existing = ($app_id > 0)
		? db_item("SELECT id, name, api_key, api_secret_hash, kind FROM api_apps
			WHERE id = '" . $app_id . "' AND kind = 'server' LIMIT 1")
		: null;

	if (!$existing || !api_app_secret_can_rotate($existing)) {

		return null;

	}

	$secret = api_generate_secret();

	$audit_before = pg_audit_fetch('api_apps', $app_id);

	$previous = ($grace_seconds > 0)
		? "api_secret_prev_hash = '" . escape($existing['api_secret_hash']) . "',
			api_secret_prev_expires = UNIX_TIMESTAMP() + " . $grace_seconds
		: "api_secret_prev_hash = '',
			api_secret_prev_expires = 0";

	$written = db("UPDATE api_apps SET
			" . $previous . ",
			api_secret_hash = '" . escape(api_secret_hash($secret)) . "',
			api_secret_hint = '" . escape(api_secret_hint($secret)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $app_id . "' AND kind = 'server' AND api_secret_hash <> ''");

	// No row matched: the secret in hand was stored nowhere and must not be shown.
	if (($written === false) || (mysqli_affected_rows(db::$con) < 1)) {

		return null;

	}

	pg_audit_record('rotate', 'api_apps', $app_id, $audit_before);

	log_activity(lang(array(
		'string' => 'A new secret was issued for the API application ({var:1}).',
		'vars'   => $existing['name']
	)), (string)$username);

	return array('name' => (string)$existing['name'], 'key' => (string)$existing['api_key'], 'secret' => $secret);

}

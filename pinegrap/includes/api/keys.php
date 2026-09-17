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

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The OAuth 2.1 rules that need no database: which redirect addresses a
 * client may register, how a presented one is matched against them, PKCE,
 * and the scope string. Kept apart from the server so they can be tested on
 * their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED') && !defined('PG_OAUTH_ENTRY')) {
	exit;
}

// RFC 4648 section 5, without padding: what PKCE and the tokens are written in.
function pg_oauth_base64url($binary) {

	return rtrim(strtr(base64_encode((string)$binary), '+/', '-_'), '=');

}

// The loopback names a native application may listen on. RFC 8252 7.3 lets
// such an application pick its port at the moment it signs in, so these are
// the only hosts where the port is not part of the match.
function pg_oauth_is_loopback_host($host) {

	$host = strtolower(trim((string)$host, '[]'));

	return in_array($host, array('127.0.0.1', '::1', 'localhost'), true);

}

// Whether an address may be registered as a redirect target.
//
// https to any host; plain http only to the machine the client runs on; and a
// private-use scheme for a native app, which RFC 8252 7.1 asks to be a
// reversed domain name - the dot is what tells com.example.app:/cb apart from
// javascript: or data:, which must never be a place a code is sent. No
// fragment (RFC 6749 3.1.2) and no user name in front of the host, which is
// how https://site.com@evil.com/ reads as the site at a glance.
function pg_oauth_redirect_uri_is_acceptable($uri) {

	if (!is_string($uri) || ($uri === '') || (strlen($uri) > 1000)) {

		return false;

	}

	if (preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {

		return false;

	}

	$parts = parse_url($uri);

	if (!is_array($parts) || !isset($parts['scheme'])) {

		return false;

	}

	if (isset($parts['fragment']) || (strpos($uri, '#') !== false)) {

		return false;

	}

	$scheme = strtolower($parts['scheme']);

	if (($scheme === 'https') || ($scheme === 'http')) {

		if (!isset($parts['host']) || ($parts['host'] === '') || isset($parts['user']) || isset($parts['pass'])) {

			return false;

		}

		if ($scheme === 'http') {

			return pg_oauth_is_loopback_host($parts['host']);

		}

		return true;

	}

	return (preg_match('/^[a-z][a-z0-9+\-]*(\.[a-z0-9+\-]+)+$/', $scheme) === 1);

}

// Whether the redirect address on a request is one the client registered.
//
// Compared as strings, exactly (OAuth 2.1 section 2.3.1): no prefix matching,
// no normalising, nothing that would let a path or a query string the client
// never registered through. The one exception is the port of a loopback http
// address, for the reason given above.
function pg_oauth_redirect_uri_matches($registered, $presented) {

	if (!is_array($registered) || !is_string($presented) || ($presented === '')) {

		return false;

	}

	foreach ($registered as $uri) {

		if (is_string($uri) && ($uri === $presented)) {

			return true;

		}

	}

	$presented_parts = parse_url($presented);

	if (!is_array($presented_parts)
		|| (strtolower(isset($presented_parts['scheme']) ? $presented_parts['scheme'] : '') !== 'http')
		|| !isset($presented_parts['host'])
		|| !pg_oauth_is_loopback_host($presented_parts['host'])) {

		return false;

	}

	$strip_port = function ($parts) {

		return strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
			. (isset($parts['path']) ? $parts['path'] : '')
			. (isset($parts['query']) ? '?' . $parts['query'] : '');

	};

	$presented_bare = $strip_port($presented_parts);

	foreach ($registered as $uri) {

		$parts = is_string($uri) ? parse_url($uri) : false;

		if (is_array($parts)
			&& isset($parts['scheme'], $parts['host'])
			&& (strtolower($parts['scheme']) === 'http')
			&& pg_oauth_is_loopback_host($parts['host'])
			&& ($strip_port($parts) === $presented_bare)) {

			return true;

		}

	}

	return false;

}

// A code_challenge for S256 is the base64url form of a SHA-256 digest: 43
// characters, nothing else.
function pg_oauth_challenge_is_valid($challenge) {

	return is_string($challenge) && (preg_match('/^[A-Za-z0-9_\-]{43}$/', $challenge) === 1);

}

// Whether the verifier sent to the token endpoint is the one the challenge was
// made from (RFC 7636 4.6). Only S256: the plain method hands the secret over
// on the front channel and OAuth 2.1 drops it.
function pg_oauth_pkce_verify($verifier, $challenge) {

	if (!is_string($verifier) || (preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier) !== 1)) {

		return false;

	}

	if (!pg_oauth_challenge_is_valid($challenge)) {

		return false;

	}

	return hash_equals($challenge, pg_oauth_base64url(hash('sha256', $verifier, true)));

}

// The scope parameter as a list: space separated, each value once, in the
// order given. The API's scopes are lower case, so the comparison is too.
function pg_oauth_scope_parse($scope) {

	if (!is_string($scope)) {

		return array();

	}

	$list = array();

	foreach (preg_split('/ +/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) as $value) {

		$value = strtolower($value);

		if (!in_array($value, $list, true)) {

			$list[] = $value;

		}

	}

	return $list;

}

function pg_oauth_scope_string($scopes) {

	return is_array($scopes) ? implode(' ', array_values($scopes)) : '';

}

// The redirect address with the answer added to its query string, keeping any
// query it was registered with.
function pg_oauth_redirect_with($redirect_uri, $params) {

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

	if ($query === '') {

		return $redirect_uri;

	}

	return $redirect_uri . ((strpos($redirect_uri, '?') === false) ? '?' : '&') . $query;

}

// A resource indicator (RFC 8707 2): an absolute address with no fragment.
// Trailing slashes are not significant to anybody comparing them by eye, so
// they are not significant here either.
function pg_oauth_resource_normalise($resource) {

	if (!is_string($resource)) {

		return '';

	}

	$resource = trim($resource);

	if (($resource === '') || (strpos($resource, '#') !== false)) {

		return '';

	}

	$parts = parse_url($resource);

	if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {

		return '';

	}

	return rtrim($resource, '/');

}

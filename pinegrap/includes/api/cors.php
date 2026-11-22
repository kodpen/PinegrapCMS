<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The browser origins that may call the external API.
//
// The list is one config_text row, one origin per line, edited on Application
// Access. While it is empty the API answers as it always did: no cross-origin
// header at all. A listed origin gets its preflight answered and its own
// origin back in Access-Control-Allow-Origin with Vary: Origin - never "*",
// never Access-Control-Allow-Credentials, because such a caller signs with its
// bearer token, not with a cookie. The token routes (POST /auth/*) refuse a
// browser origin that is not listed, so a page elsewhere cannot drive a
// sign-in through a visitor's browser.
//
// Pure helpers first; api_cors_origins() reads the stored list.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The config_text name the list is kept under.
function api_cors_setting_name() {

	return 'api_cors_origins';

}

// The fixed origins the native app shells load their pages from: Capacitor on
// iOS, Ionic's older iOS shell, Tauri, and Capacitor on Android with its older
// http scheme. Capacitor on Android today is https://localhost, an https origin
// like any other.
function api_cors_native_origins() {

	return array('capacitor://localhost', 'ionic://localhost', 'tauri://localhost', 'http://localhost');

}

// An origin in the one spelling the list keeps: lower case, https with a host
// and a port only when it is not 443, or one of the native shells' fixed
// origins. '' for anything else (a path, a user, a wildcard, plain http, "null").
function api_cors_origin_normalize($origin) {

	if (!is_string($origin)) {

		return '';

	}

	$origin = strtolower(trim($origin));

	if ($origin === '') {

		return '';

	}

	if (in_array($origin, api_cors_native_origins(), true)) {

		return $origin;

	}

	// A host is labels of letters, digits and hyphens joined by dots; an IPv6
	// literal in brackets is allowed too.
	if (!preg_match('~^https://([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*|\[[0-9a-f:.]+\])(?::([0-9]{1,5}))?$~', $origin, $match)) {

		return '';

	}

	$host = $match[1];

	$port = isset($match[2]) ? $match[2] : '';

	if ($port !== '') {

		$number = (int)$port;

		if ($number < 1 || $number > 65535) {

			return '';

		}

		if ($number !== 443) {

			$host .= ':' . $number;

		}

	}

	return 'https://' . $host;

}

// The origins of a typed text - one per line, or separated by commas or
// spaces - normalised and without repeats, and the entries that are not an
// origin as they were typed.
function api_cors_origins_parse($text) {

	$origins = array();

	$invalid = array();

	foreach (preg_split('~[\s,]+~', (string)$text, -1, PREG_SPLIT_NO_EMPTY) as $entry) {

		$origin = api_cors_origin_normalize($entry);

		if ($origin === '') {

			$invalid[] = $entry;

		} elseif (!in_array($origin, $origins, true)) {

			$origins[] = $origin;

		}

	}

	return array('origins' => $origins, 'invalid' => $invalid);

}

// The list as it is stored and shown in the field: one origin per line.
function api_cors_origins_text($origins) {

	return implode("\n", (array)$origins);

}

// The listed origin a request's Origin header names, or ''.
function api_cors_origin_match($origin, $list) {

	$origin = api_cors_origin_normalize($origin);

	if ($origin === '') {

		return '';

	}

	return in_array($origin, (array)$list, true) ? $origin : '';

}

// The headers an answer to a listed origin carries; the preflight also names
// the methods and request headers the API reads. Nothing for an unlisted one.
// The application key and secret headers are left out on purpose: a page
// signs with a bearer token.
function api_cors_headers($origin, $list, $preflight) {

	$match = api_cors_origin_match($origin, $list);

	if ($match === '') {

		return array();

	}

	$headers = array(
		'Access-Control-Allow-Origin' => $match,
		'Vary'                        => 'Origin'
	);

	if ($preflight) {

		$headers['Access-Control-Allow-Methods'] = 'GET, HEAD, POST, PUT, PATCH, DELETE';

		$headers['Access-Control-Allow-Headers'] = 'Authorization, Content-Type, Accept-Language, If-None-Match, If-Match, If-Range, Range, Idempotency-Key, X-Dry-Run, X-HTTP-Method-Override, X-Requested-With';

		$headers['Access-Control-Max-Age'] = '600';

	}

	return $headers;

}

// Whether a token route (a 'public' row of api_schema(): POST /auth/login,
// /auth/pair, /auth/refresh, /auth/logout) refuses this call: it came from a
// browser - it carries an Origin - that is not listed. A server or a native
// client that sends no Origin passes as before.
function api_cors_token_route_refused($route, $origin, $list) {

	if (!is_array($route) || empty($route['public'])) {

		return false;

	}

	if (!is_string($origin) || trim($origin) === '') {

		return false;

	}

	return api_cors_origin_match($origin, $list) === '';

}

// The stored list, normalised again on the way out so a hand-edited row can
// never widen it.
function api_cors_origins() {

	static $origins = null;

	if ($origins === null) {

		$parsed = api_cors_origins_parse(function_exists('pg_config_text') ? pg_config_text(api_cors_setting_name()) : '');

		$origins = $parsed['origins'];

	}

	return $origins;

}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The OAuth 2.1 rules that need no database: which redirect addresses a
 * client may register, how a presented one is matched against them, PKCE,
 * the scope string and the client metadata documents a client may identify
 * itself with. Kept apart from the server so they can be tested on their own.
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

// Schemes a redirect address may never use. Each would hand the code to
// something other than the application that asked for it: a script run in the
// page, a file or a blob on the machine, or another kind of program - mail,
// telephone, a socket, an Android intent.
function pg_oauth_forbidden_redirect_schemes() {

	return array('javascript', 'vbscript', 'data', 'file', 'blob', 'about', 'filesystem',
		'view-source', 'jar', 'ftp', 'ws', 'wss', 'mailto', 'tel', 'sms', 'intent');

}

// Whether an address may be registered as a redirect target.
//
// https to any host; plain http only to the machine the client runs on; and a
// private-use scheme for an application on the person's own device. RFC 8252
// 7.1 asks for that scheme to be a reversed domain name (com.example.app:/cb),
// but the desktop applications people connect use their product's name -
// cursor://, vscode://, kiro:// - so a single word is accepted too. What
// keeps the code safe either way is PKCE: another program that claimed the
// same scheme did not start the request and does not hold the verifier. No
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

	if ((preg_match('/^[a-z][a-z0-9+.\-]*$/', $scheme) !== 1)
		|| in_array($scheme, pg_oauth_forbidden_redirect_schemes(), true)
		|| isset($parts['user']) || isset($parts['pass'])) {

		return false;

	}

	// Something after the colon: a scheme on its own names no place.
	return (trim((string)substr($uri, strlen($parts['scheme']) + 1), '/') !== '');

}

// What is left of a loopback http address once the parts that do not matter
// are taken away - the port, which the application picks when it signs in
// (RFC 8252 7.3), and which of the three loopback names it was written with,
// all of which reach the same machine: a client that registered 127.0.0.1
// and listens on localhost is the same client. An empty path is the root
// (RFC 3986 6.2.3). Null for any other address.
function pg_oauth_loopback_rest($uri) {

	$parts = is_string($uri) ? parse_url($uri) : false;

	if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
		|| (strtolower($parts['scheme']) !== 'http')
		|| !pg_oauth_is_loopback_host($parts['host'])
		|| isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {

		return null;

	}

	$path = isset($parts['path']) ? $parts['path'] : '';

	return (($path === '') ? '/' : $path) . (isset($parts['query']) ? '?' . $parts['query'] : '');

}

// Whether the redirect address on a request is one the client registered.
//
// Compared as strings, exactly (OAuth 2.1 section 2.3.1): no prefix matching,
// no normalising, nothing that would let a path or a query string the client
// never registered through. The one exception is a loopback http address,
// compared by its path and query alone (pg_oauth_loopback_rest()).
function pg_oauth_redirect_uri_matches($registered, $presented) {

	if (!is_array($registered) || !is_string($presented) || ($presented === '')) {

		return false;

	}

	foreach ($registered as $uri) {

		if (is_string($uri) && ($uri === $presented)) {

			return true;

		}

	}

	$presented_rest = pg_oauth_loopback_rest($presented);

	if ($presented_rest === null) {

		return false;

	}

	foreach ($registered as $uri) {

		if (pg_oauth_loopback_rest($uri) === $presented_rest) {

			return true;

		}

	}

	return false;

}

// Where an authorization answer goes, as the consent screen names it:
// array('kind' => ..., 'text' => ...). 'web' is an https address, named by its
// host. 'device' is a loopback address - a program on the person's own
// computer - named by its host and port. 'app' is a private-use scheme, named
// by the scheme and host, because the scheme is what says which application
// receives it.
function pg_oauth_redirect_destination($uri) {

	$parts = is_string($uri) ? parse_url($uri) : false;

	if (!is_array($parts) || !isset($parts['scheme'])) {

		return array('kind' => 'web', 'text' => (string)$uri);

	}

	$scheme = strtolower($parts['scheme']);

	$host = isset($parts['host']) ? strtolower($parts['host']) : '';

	if (pg_oauth_loopback_rest($uri) !== null) {

		return array('kind' => 'device', 'text' => $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : ''));

	}

	if (($scheme === 'https') || ($scheme === 'http')) {

		return array('kind' => 'web', 'text' => $host);

	}

	return array(
		'kind' => 'app',
		'text' => ($host !== '')
			? $scheme . '://' . $host
			: mb_substr($scheme . ':' . (isset($parts['path']) ? $parts['path'] : ''), 0, 80)
	);

}

// Whether an authorize request's state can be carried through the consent
// screen and handed back. It is returned exactly as it came (RFC 6749 4.1.2):
// a client signs or encrypts it and compares it whole, so a state cut to fit
// fails its check as surely as a forged one. Signed states run to a kilobyte
// or two; a longer one is refused rather than shortened.
function pg_oauth_state_max() {

	return 4096;

}

function pg_oauth_state_is_acceptable($state) {

	return is_string($state) && (strlen($state) <= pg_oauth_state_max());

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

// The scopes an authorization request asks for, as the consent screen offers
// them: $known is every scope the site has, $available what this person may
// hand over. Values the site has no scope of - openid, offline_access, a
// client's own word for "everything" - are passed over (RFC 6749 3.3 lets the
// server ignore part of a request), so a client that adds them to real ones
// connects with the real ones, and one that asks for nothing this site knows
// is treated like one that asked for nothing: everything available is listed
// and the person chooses. Empty when every real scope asked for is out of
// this person's reach.
function pg_oauth_scope_requested($scope, $known, $available) {

	$available = is_array($available) ? array_values($available) : array();

	$asked = array_intersect(pg_oauth_scope_parse($scope), is_array($known) ? $known : array());

	if (empty($asked)) {

		return $available;

	}

	return array_values(array_intersect($available, $asked));

}

// The consent screen's presets, in the order shown: key => label (English,
// lang() at the screen) and the scopes it ticks. $groups is api_scope_groups(),
// $all the scopes "All" ticks (api_assistant_scopes() without the reads it
// leaves out). Every preset ticks writing in the Workspace and the Tasks, so
// the application can always propose a record change as a card
// (workspace.changes.propose); no preset ticks any other write, which the
// person ticks by hand. A group the site lacks (a module switched off) adds
// nothing.
function pg_oauth_consent_presets(array $groups, array $all) {

	$scopes = function (array $keys, array $halves) use ($groups) {

		$list = array();

		foreach ($keys as $key) {

			foreach ($halves as $half) {

				if (isset($groups[$key][$half]) && ((string)$groups[$key][$half] !== '')) {

					$list[] = (string)$groups[$key][$half];

				}

			}

		}

		return $list;

	};

	$base = $scopes(array('workspace', 'tasks'), array('read', 'write'));

	$reads = function (array $keys) use ($scopes) {

		return $scopes($keys, array('read'));

	};

	return array(
		'workspace'  => array('label' => 'Workspace', 'scopes' => $base),
		'store'      => array('label' => 'Store', 'scopes' => array_merge($base, $reads(pg_oauth_consent_store_groups()))),
		'accounting' => array('label' => 'Accounting', 'scopes' => array_merge($base, $reads(array('erp', 'erp_cash')))),
		'all'        => array('label' => 'All', 'scopes' => array_values(array_unique(array_merge($base, $all))))
	);

}

// The scope groups the store preset reads.
function pg_oauth_consent_store_groups() {

	return array('products', 'orders', 'customers', 'offers', 'comments', 'commissions', 'campaigns', 'contact_groups');

}

// The preset the screen opens with, and the one a request names when it is
// one of $presets (pg_oauth_consent_presets()).
function pg_oauth_consent_preset_key($requested, array $presets) {

	$requested = is_string($requested) ? $requested : '';

	return isset($presets[$requested]) ? $requested : 'all';

}

// The boxes ticked on the consent screen: what the preset ticks of what the
// application asked for ($asked), in $asked's order.
function pg_oauth_consent_ticked(array $asked, array $preset_scopes) {

	return array_values(array_intersect($asked, $preset_scopes));

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

// The largest client metadata document read, in bytes. The document holds a
// name, a few addresses and a handful of flags; the draft that defines it
// suggests 5 KB, and anything much bigger is not a client describing itself.
function pg_oauth_metadata_document_limit() {

	return 5120;

}

// Whether a client_id is the address of a client metadata document
// (draft-ietf-oauth-client-id-metadata-document 3): https, with a path, and
// none of the parts that make two spellings of an address differ or hide
// where it points - no fragment, no user name, no dot segments. A query
// string is allowed but not encouraged by the draft, and not needed by any
// client, so it is refused too.
function pg_oauth_client_id_is_url($client_id) {

	if (!is_string($client_id) || (strlen($client_id) > 255) || (stripos($client_id, 'https://') !== 0)) {

		return false;

	}

	if (preg_match('/[^\x21-\x7E]/', $client_id) === 1) {

		return false;

	}

	$parts = parse_url($client_id);

	if (!is_array($parts) || !isset($parts['host']) || ($parts['host'] === '')
		|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
		|| isset($parts['fragment']) || (strpos($client_id, '#') !== false) || (strpos($client_id, '?') !== false)) {

		return false;

	}

	$path = isset($parts['path']) ? $parts['path'] : '';

	if (($path === '') || ($path === '/')) {

		return false;

	}

	foreach (explode('/', $path) as $segment) {

		if (($segment === '.') || ($segment === '..')) {

			return false;

		}

	}

	return true;

}

// Reads a client metadata document fetched from $client_id. Answers
// array('ok' => bool, 'error' => string, 'client' => array) where client
// holds what the site keeps: name, client_uri, redirect_uris, software_id,
// software_version.
//
// The document must name the address it was read from as its client_id -
// otherwise any page could pass itself off as another client. It describes a
// public client: a shared secret cannot be published at a public address, so
// a document carrying one, or asking for a secret-based method, is refused.
// Errors are English and ASCII: they end up in an OAuth error_description.
function pg_oauth_metadata_document_client($client_id, $json) {

	$fail = function ($error) {

		return array('ok' => false, 'error' => $error, 'client' => array());

	};

	if (!is_string($json) || ($json === '') || (strlen($json) > pg_oauth_metadata_document_limit())) {

		return $fail('The client metadata document is empty or larger than 5 KB.');

	}

	$document = json_decode($json, true);

	if (!is_array($document) || (array_keys($document) === range(0, count($document) - 1))) {

		return $fail('The client metadata document is not a JSON object.');

	}

	if (!isset($document['client_id']) || !is_string($document['client_id']) || ($document['client_id'] !== $client_id)) {

		return $fail('The client metadata document does not name its own address as client_id.');

	}

	if (isset($document['client_secret']) || isset($document['client_secret_expires_at'])) {

		return $fail('A client metadata document cannot carry a client secret.');

	}

	$method = isset($document['token_endpoint_auth_method']) ? $document['token_endpoint_auth_method'] : 'none';

	if ($method !== 'none') {

		return $fail('Only token_endpoint_auth_method none is supported for a client metadata document.');

	}

	if (isset($document['grant_types'])
		&& (!is_array($document['grant_types']) || !in_array('authorization_code', $document['grant_types'], true))) {

		return $fail('The client metadata document does not allow the authorization_code grant.');

	}

	if (isset($document['response_types'])
		&& (!is_array($document['response_types']) || !in_array('code', $document['response_types'], true))) {

		return $fail('The client metadata document does not allow response_type code.');

	}

	if (!isset($document['redirect_uris']) || !is_array($document['redirect_uris'])
		|| empty($document['redirect_uris']) || (count($document['redirect_uris']) > 10)) {

		return $fail('The client metadata document must list between one and ten redirect_uris.');

	}

	$redirect_uris = array();

	foreach ($document['redirect_uris'] as $uri) {

		if (!pg_oauth_redirect_uri_is_acceptable($uri)) {

			return $fail('A redirect_uri in the client metadata document is not acceptable.');

		}

		$redirect_uris[] = $uri;

	}

	$text = function ($name, $length) use ($document) {

		if (!isset($document[$name]) || !is_string($document[$name])) {

			return '';

		}

		return mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $document[$name])), 0, $length);

	};

	$client_uri = $text('client_uri', 255);

	if (($client_uri !== '') && (stripos($client_uri, 'https://') !== 0)) {

		$client_uri = '';

	}

	return array(
		'ok'     => true,
		'error'  => '',
		'client' => array(
			'name'             => $text('client_name', 100),
			'client_uri'       => $client_uri,
			'redirect_uris'    => array_values(array_unique($redirect_uris)),
			'software_id'      => $text('software_id', 100),
			'software_version' => $text('software_version', 40)
		)
	);

}

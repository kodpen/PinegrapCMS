<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Outgoing HTTP, for addresses the operator supplied.
//
// This is the half of the API that reaches out rather than being reached, and it
// is the dangerous direction: the address comes from a form, and the request is
// made by the server, from inside the network, with whatever the server can see.
// Pointed at 169.254.169.254 or at 127.0.0.1 it becomes a way to read a cloud
// instance's credentials or to knock on an internal service that was never meant
// to be public. So the host is resolved first, the address is checked, and curl
// is then pinned to that exact address - resolving again inside curl would leave
// a window where the name answers something else the second time.
//
// Redirects are not followed for the same reason: a redirect is a second address
// that no check has seen.
//
// The webhook dispatcher uses this today; the marketplace connectors will use
// the same one, which is why the retry classification lives here rather than in
// the webhook code.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

// Addresses the software will not call, whatever the operator typed.
function api_http_address_is_allowed($ip) {

	if (filter_var($ip, FILTER_VALIDATE_IP) === false) {

		return false;

	}

	// Loopback, private ranges and the reserved blocks - which is where link
	// local, and with it the cloud metadata address, lives.
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

}

// Answers array('ok' => bool, 'ip' => string, 'error' => string).
//
// $allow_http lets a caller that reads public pages rather than sending
// credentials (the site importer) accept plain http; the address check is the
// same either way.
function api_http_check_url($url, $allow_http = false) {

	$parts = @parse_url($url);

	if (!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])) {

		return array('ok' => false, 'ip' => '', 'error' => 'The address is not a valid URL.');

	}

	$scheme = strtolower($parts['scheme']);

	if ($scheme !== 'https' && $scheme !== 'http') {

		return array('ok' => false, 'ip' => '', 'error' => 'Only http and https addresses are called.');

	}

	// Plain http carries the signature, the payload and any credentials in the
	// clear. It is allowed only where the operator has said the network is
	// theirs, which is a setting for a webhook to a machine on the same rack -
	// never for a marketplace on the public internet.
	if ($scheme === 'http' && !$allow_http && (!defined('API_WEBHOOK_ALLOW_HTTP') || API_WEBHOOK_ALLOW_HTTP !== true)) {

		return array('ok' => false, 'ip' => '', 'error' => 'Outgoing addresses must be https.');

	}

	$host = $parts['host'];

	// A bare address needs no lookup, and gethostbyname() would hand it back
	// unchanged anyway.
	$ip = (filter_var($host, FILTER_VALIDATE_IP) !== false) ? $host : gethostbyname($host);

	if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) {

		return array('ok' => false, 'ip' => '', 'error' => 'The address does not resolve.');

	}

	if (!api_http_address_is_allowed($ip)) {

		return array('ok' => false, 'ip' => $ip, 'error' => 'That address is inside this network and is not called.');

	}

	return array('ok' => true, 'ip' => $ip, 'error' => '');

}

// One POST. Answers like api_http_request().
//
// Kept as its own name because the webhook dispatcher reads that way, and
// because a webhook is always a POST with a signed body.
function api_http_post($url, $body, $headers = array(), $timeout = 10) {

	return api_http_request('POST', $url, array(
		'body'    => $body,
		'headers' => $headers,
		'timeout' => $timeout,
		'agent'   => 'webhook'
	));

}

// Any method, for the connectors.
//
// A marketplace is a conversation rather than a delivery: the catalogue is read
// with GET, an order is approved with PUT, and the answer to a push is fetched
// afterwards with a second call. Options:
//
//   body      string, sent as-is; omitted entirely for GET
//   headers   list of "Name: value" strings
//   timeout   seconds, whole request
//   max_body  bytes of the response to keep. The default suits an error
//             message; a connector reading a result page raises it, because
//             truncating that answer loses which SKU failed.
//   agent     the word after the version in the user agent, for the receiver's
//             log
//   response_headers
//             true to have the answer's headers back as 'headers', by lower-
//             case name (the last one of a repeated name wins). An MCP server
//             says in them which kind of body it sent and which session the
//             client is in.
//   complete  a callable given the body received so far, after each piece
//             curl hands over; true ends the transfer there. For a server
//             that keeps a stream open after it has sent what was asked (an
//             MCP server's event stream): the answer is then a success with
//             the body read so far and the status and headers already
//             received, not a wait until the server closes or the timeout.
//             curl reports the stop as error 23 (a write refused), which is
//             not passed on. Without it the body is read as before.
//
// Answers array('ok', 'status', 'body', 'error', 'errno', 'duration_ms'), and
// 'headers' with response_headers. errno is curl's error number when the
// request failed in curl (28 is a timeout), 0 otherwise - also for an address
// refused before curl ran, whose reason is in error alone.
//
// Everything the webhook client refuses, this refuses: an address inside the
// network, a redirect to an address nothing checked, an unverified certificate.
function api_http_request($method, $url, $options = array()) {

	$started = microtime(true);

	$result = array('ok' => false, 'status' => 0, 'body' => '', 'error' => '', 'errno' => 0, 'duration_ms' => 0);

	if (!empty($options['response_headers'])) {

		$result['headers'] = array();

	}

	$method   = strtoupper((string)$method);
	$body     = isset($options['body'])     ? $options['body']     : null;
	$headers  = isset($options['headers'])  ? $options['headers']  : array();
	$timeout  = isset($options['timeout'])  ? (int)$options['timeout']  : 10;
	$max_body = isset($options['max_body']) ? (int)$options['max_body'] : 1000;
	$agent    = isset($options['agent'])    ? (string)$options['agent']  : 'outbound';

	$check = api_http_check_url($url);

	if (!$check['ok']) {

		$result['error'] = $check['error'];

		return $result;

	}

	if (!function_exists('curl_init')) {

		$result['error'] = 'curl is not available on this server.';

		return $result;

	}

	$parts = parse_url($url);

	$port = isset($parts['port'])
		? (int)$parts['port']
		: (strtolower($parts['scheme']) === 'https' ? 443 : 80);

	$handle = curl_init($url);

	curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);

	if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {

		curl_setopt($handle, CURLOPT_POSTFIELDS, $body);

	}

	curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

	// The certificate is verified, and a server whose CA store is not on the
	// default search path says where one is in data/config.php - the same
	// setting the update channel uses. There is deliberately no insecure
	// fallback: a delivery whose receiver cannot be identified is a delivery to
	// whoever answered, and this client is what carries webhooks, push and the
	// keys to the shop's marketplace accounts.
	curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

	if ((defined('CURL_CA_BUNDLE')) && (CURL_CA_BUNDLE !== '') && (is_file(CURL_CA_BUNDLE))) {
		curl_setopt($handle, CURLOPT_CAINFO, CURL_CA_BUNDLE);
	}

	curl_setopt($handle, CURLOPT_USERAGENT,
		'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+' . $agent . ')');

	// The address that was checked is the address that is called.
	curl_setopt($handle, CURLOPT_RESOLVE, array($parts['host'] . ':' . $port . ':' . $check['ip']));

	$received = array();

	if (!empty($options['response_headers'])) {

		curl_setopt($handle, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$received) {

			// A status line starts the headers over: an interim answer (100
			// Continue) has headers of its own that are not the answer's.
			if (stripos($line, 'HTTP/') === 0) {

				$received = array();

			} elseif (strpos($line, ':') !== false) {

				list($name, $value) = explode(':', $line, 2);

				$received[strtolower(trim($name))] = trim($value);

			}

			return strlen($line);

		});

	}

	// The complete option reads the body through a write function instead of
	// curl's own buffer: set after CURLOPT_RETURNTRANSFER, it takes the body
	// over, and curl_exec() then answers true rather than the body.
	$complete = (isset($options['complete']) && is_callable($options['complete'])) ? $options['complete'] : null;

	$streamed = '';

	$stopped = false;

	if ($complete !== null) {

		curl_setopt($handle, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$streamed, &$stopped, $complete) {

			return api_http_take_chunk($streamed, $stopped, $chunk, $complete);

		});

	}

	$response = curl_exec($handle);

	// Read to the end, or stopped on purpose: either way the body is what the
	// write function kept, and the status is the one already received.
	if (($complete !== null) && (($response !== false) || api_http_stop_is_success($stopped, curl_errno($handle)))) {

		$response = $streamed;

	}

	if ($response === false) {

		// "unable to get local issuer certificate" on its own sends the operator
		// looking at the receiver. The hint names the cause that is actually on
		// this server.
		$result['errno'] = (int)curl_errno($handle);

		$result['error'] = curl_error($handle)
			. (function_exists('pg_curl_tls_hint') ? pg_curl_tls_hint($result['errno']) : '');

	} else {

		$result['status'] = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);

		$result['body'] = ($max_body > 0) ? substr((string)$response, 0, $max_body) : (string)$response;

		$result['ok'] = ($result['status'] >= 200 && $result['status'] < 300);

	}

	curl_close($handle);

	if (!empty($options['response_headers'])) {

		$result['headers'] = $received;

	}

	$result['duration_ms'] = (int)round((microtime(true) - $started) * 1000);

	return $result;

}

// One piece of a body read with the complete option: kept in $buffer, then
// the count curl is told was taken. The piece's length goes on; 0, once
// $complete says the body is complete, is a short count, on which curl ends
// the transfer with error 23 and $stopped is set. An empty piece (an empty
// body) asks nothing.
function api_http_take_chunk(&$buffer, &$stopped, $chunk, $complete) {

	$chunk = (string)$chunk;

	$buffer .= $chunk;

	if (($chunk === '') || !call_user_func($complete, $buffer)) {

		return strlen($chunk);

	}

	$stopped = true;

	return 0;

}

// Whether a transfer curl failed is the stop the complete option asked for:
// error 23 (CURLE_WRITE_ERROR) after the write function refused a piece. Any
// other error, or a write error with no stop, stays a failure.
function api_http_stop_is_success($stopped, $errno) {

	return ($stopped === true) && ((int)$errno === 23);

}

// Whether a failed attempt is worth repeating.
//
// A refusal the receiver meant - 400, 404, 410 - will be refused again in an
// hour, and retrying it for a day only fills their log and ours. A timeout, a
// connection error, a 429 or anything in the 500s is the receiver having a
// moment, which is exactly what a retry is for.
function api_http_should_retry($outcome) {

	if ($outcome['ok']) {

		return false;

	}

	if ($outcome['status'] === 0) {

		return true;

	}

	if ($outcome['status'] === 408 || $outcome['status'] === 429) {

		return true;

	}

	return ($outcome['status'] >= 500);

}

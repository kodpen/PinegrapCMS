<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shared pieces of tools/ws_post.php and tools/ws_wait.php: the settings an
 * agent passes in its environment, the HTTP call to a site's external API
 * with an application key, and the pure helpers (options, matching, ETag)
 * the tests read.
 *
 * Environment:
 *   PG_WS_SITE     the site's API address, ending in /integration.php
 *                  (https://example.com/pinegrap/integration.php)
 *   PG_WS_KEY      the application's key
 *   PG_WS_SECRET   the application's secret
 *   PG_WS_CHANNEL  the channel id, when --channel is not given
 *
 * The key and the secret go only into the Authorization header (Basic, as
 * the site's application keys are read); they are never printed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

define('WS_CLIENT_EXIT_OK', 0);
define('WS_CLIENT_EXIT_HTTP', 1);
define('WS_CLIENT_EXIT_USAGE', 2);
define('WS_CLIENT_EXIT_TIMEOUT', 3);

/**
 * Reads --name=value and --flag options; everything else is a positional
 * argument. "--" ends the options.
 *
 * @param string[] $argv without the script name
 * @return array{0: array<string,string|bool>, 1: string[]}
 */
function ws_client_options($argv)
{
	$options = array();
	$rest = array();
	$done = false;

	foreach ($argv as $arg) {
		$arg = (string) $arg;

		if (!$done && $arg === '--') {
			$done = true;
			continue;
		}

		if (!$done && strncmp($arg, '--', 2) === 0 && strlen($arg) > 2) {
			$eq = strpos($arg, '=');

			if ($eq === false) {
				$options[substr($arg, 2)] = true;
			} else {
				$options[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
			}

			continue;
		}

		$rest[] = $arg;
	}

	return array($options, $rest);
}

/**
 * The connection settings from the environment and the options. missing
 * names the environment variables that are empty.
 *
 * @param array<string,string|bool> $options
 * @param array<string,string> $env
 * @return array{site: string, key: string, secret: string, channel: int, missing: string[]}
 */
function ws_client_settings($options, $env)
{
	$get = function ($name) use ($env) {
		return isset($env[$name]) ? trim((string) $env[$name]) : '';
	};

	$site = rtrim($get('PG_WS_SITE'), '/');
	$channel = isset($options['channel']) && is_string($options['channel']) ? $options['channel'] : $get('PG_WS_CHANNEL');
	$out = array(
		'site'    => $site,
		'key'     => $get('PG_WS_KEY'),
		'secret'  => $get('PG_WS_SECRET'),
		'channel' => ctype_digit((string) $channel) ? (int) $channel : 0,
		'missing' => array(),
	);

	foreach (array('PG_WS_SITE' => 'site', 'PG_WS_KEY' => 'key', 'PG_WS_SECRET' => 'secret') as $name => $field) {
		if ($out[$field] === '') {
			$out['missing'][] = $name;
		}
	}

	if ($out['channel'] < 1) {
		$out['missing'][] = 'PG_WS_CHANNEL';
	}

	// Plain http would send the secret in the clear; only a local test site
	// may use it.
	if ($site !== '' && !preg_match('#^https://#', $site) && !preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?/#', $site . '/')) {
		$out['missing'][] = 'PG_WS_SITE (https)';
	}

	return $out;
}

/**
 * The Authorization header of an application key.
 *
 * @param string $key
 * @param string $secret
 * @return string
 */
function ws_client_auth_header($key, $secret)
{
	return 'Authorization: Basic ' . base64_encode($key . ':' . $secret);
}

/**
 * The ETag of a response's raw header block, '' when none.
 *
 * @param string $headers
 * @return string
 */
function ws_client_etag($headers)
{
	if (preg_match('/^etag:\s*(.+?)\s*$/mi', (string) $headers, $m)) {
		return $m[1];
	}

	return '';
}

/**
 * Whether a message (as the API gives it) matches the wanted rule. Every
 * rule given must hold: prefix (the text starts with it, case-sensitive),
 * contains (the text holds it, case-insensitive), regex (a PCRE with its
 * delimiters). A deleted or hidden message never matches; nor one with an
 * id at or below $after_id.
 *
 * @param array<string,mixed> $message
 * @param array{prefix?: string, contains?: string, regex?: string} $rule
 * @param int $after_id
 * @return bool
 */
function ws_client_message_matches($message, $rule, $after_id)
{
	if (!is_array($message) || (int) ($message['id'] ?? 0) <= $after_id) {
		return false;
	}

	if (!empty($message['deleted']) || !empty($message['hidden'])) {
		return false;
	}

	$text = (string) ($message['text'] ?? '');

	if (isset($rule['prefix']) && $rule['prefix'] !== '' && strncmp($text, $rule['prefix'], strlen($rule['prefix'])) !== 0) {
		return false;
	}

	if (isset($rule['contains']) && $rule['contains'] !== '' && stripos($text, $rule['contains']) === false) {
		return false;
	}

	if (isset($rule['regex']) && $rule['regex'] !== '' && !preg_match($rule['regex'], $text)) {
		return false;
	}

	return true;
}

/**
 * The highest message id in a list, $floor when the list is empty.
 *
 * @param array<int,array<string,mixed>> $messages
 * @param int $floor
 * @return int
 */
function ws_client_max_id($messages, $floor)
{
	foreach ((array) $messages as $message) {
		if (is_array($message) && (int) ($message['id'] ?? 0) > $floor) {
			$floor = (int) $message['id'];
		}
	}

	return $floor;
}

/**
 * One call to the site's API.
 *
 * @param string $method GET or POST
 * @param string $url
 * @param array{site: string, key: string, secret: string} $settings
 * @param array<string,mixed>|null $body JSON body for POST
 * @param string[] $headers extra header lines
 * @return array{status: int, headers: string, body: string, error: string}
 */
function ws_client_request($method, $url, $settings, $body = null, $headers = array())
{
	$out = array('status' => 0, 'headers' => '', 'body' => '', 'error' => '');

	if (!function_exists('curl_init')) {
		$out['error'] = 'the PHP curl extension is missing';
		return $out;
	}

	$lines = array_merge(array(
		ws_client_auth_header($settings['key'], $settings['secret']),
		'Accept: application/json',
		'User-Agent: pinegrap-ws-tools',
	), $headers);

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

	if ($body !== null) {
		$lines[] = 'Content-Type: application/json';
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	curl_setopt($ch, CURLOPT_HTTPHEADER, $lines);

	$raw = curl_exec($ch);

	if ($raw === false) {
		$out['error'] = curl_error($ch);
		curl_close($ch);
		return $out;
	}

	$size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$out['status'] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$out['headers'] = substr((string) $raw, 0, $size);
	$out['body'] = (string) substr((string) $raw, $size);
	curl_close($ch);

	return $out;
}

/**
 * A short, secret-free line about a failed call, for stderr.
 *
 * @param array{status: int, body: string, error: string} $answer
 * @return string
 */
function ws_client_failure($answer)
{
	if ($answer['error'] !== '') {
		return 'network: ' . $answer['error'];
	}

	$json = json_decode($answer['body'], true);
	$code = is_array($json) && isset($json['error']['code']) ? (string) $json['error']['code'] : '';

	return 'HTTP ' . $answer['status'] . ($code !== '' ? ' ' . $code : '');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Waits until a message matching a rule is written in a Workspace channel,
 * then prints it as one JSON line and exits. It is the poll door of an agent
 * that has no inbound address: it asks GET /workspace/sync?channel_id= every
 * few seconds with the previous cursor and the previous ETag in
 * If-None-Match, so a quiet channel costs a 304 with no body.
 *
 * Usage:  php tools/ws_wait.php [--channel=ID] [--prefix=TEXT]
 *                               [--contains=TEXT] [--regex=/…/]
 *                               [--after-id=N] [--timeout=SECONDS]
 *                               [--interval=SECONDS]
 * Exit:   0 a message matched (printed on stdout); 1 the site refused or the
 *         channel can no longer be read; 2 usage or missing settings;
 *         3 the timeout passed with no match.
 *
 * Only messages written after the first answer count, unless --after-id
 * names an earlier id: the first answer carries the channel's newest 50,
 * which are the past. Settings come from the environment
 * (tools/ws_client.php). --timeout defaults to 600, --interval to 5 (at
 * least 2).
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

require_once(__DIR__ . '/ws_client.php');

/**
 * The matching rule, the waiting limits and an error line from the options.
 *
 * @param array<string,string|bool> $options
 * @return array{rule: array<string,string>, after_id: int, timeout: int, interval: int, error: string}
 */
function ws_wait_plan($options)
{
	$out = array('rule' => array(), 'after_id' => -1, 'timeout' => 600, 'interval' => 5, 'error' => '');

	foreach (array('prefix', 'contains', 'regex') as $name) {
		if (isset($options[$name]) && is_string($options[$name]) && $options[$name] !== '') {
			$out['rule'][$name] = $options[$name];
		}
	}

	if (isset($out['rule']['regex']) && @preg_match($out['rule']['regex'], '') === false) {
		$out['error'] = 'the regex is not a valid PCRE pattern with delimiters';
	}

	foreach (array('after-id' => 'after_id', 'timeout' => 'timeout', 'interval' => 'interval') as $name => $field) {
		if (!isset($options[$name])) {
			continue;
		}

		if (!is_string($options[$name]) || !ctype_digit($options[$name])) {
			$out['error'] = '--' . $name . ' must be a whole number';
			continue;
		}

		$out[$field] = (int) $options[$name];
	}

	$out['interval'] = max(2, $out['interval']);

	return $out;
}

/**
 * The first matching message of a sync answer, null when none.
 *
 * @param array<string,mixed> $sync
 * @param array<string,string> $rule
 * @param int $after_id
 * @return array<string,mixed>|null
 */
function ws_wait_pick($sync, $rule, $after_id)
{
	$messages = isset($sync['messages']) && is_array($sync['messages']) ? $sync['messages'] : array();

	usort($messages, function ($a, $b) {
		return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
	});

	foreach ($messages as $message) {
		if (ws_client_message_matches($message, $rule, $after_id)) {
			return $message;
		}
	}

	return null;
}

function ws_wait_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$settings = ws_client_settings($options, getenv());
	$plan = ws_wait_plan($options);

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_wait: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($plan['error'] !== '' || $rest) {
		fwrite(STDERR, 'ws_wait: ' . ($plan['error'] !== '' ? $plan['error'] : 'unexpected argument ' . $rest[0]) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$deadline = time() + $plan['timeout'];
	$cursor = '';
	$etag = '';
	$after_id = $plan['after_id'];
	$first = true;

	while (true) {
		$url = $settings['site'] . '/workspace/sync?channel_id=' . $settings['channel'] . ($cursor !== '' ? '&cursor=' . rawurlencode($cursor) : '');
		$answer = ws_client_request('GET', $url, $settings, null, $etag !== '' ? array('If-None-Match: ' . $etag) : array());

		if ($answer['status'] === 200) {
			$sync = json_decode($answer['body'], true);

			if (!is_array($sync)) {
				fwrite(STDERR, "ws_wait: the answer is not JSON\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			if (!empty($sync['gone'])) {
				fwrite(STDERR, "ws_wait: the channel can no longer be read\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			// The first answer holds the channel's newest messages: the past,
			// unless --after-id asked to look at part of it.
			if ($first && $after_id < 0) {
				$after_id = ws_client_max_id($sync['messages'] ?? array(), 0);
			}

			$first = false;
			$match = ws_wait_pick($sync, $plan['rule'], max(0, $after_id));

			if ($match !== null) {
				fwrite(STDOUT, json_encode($match, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
				return WS_CLIENT_EXIT_OK;
			}

			$after_id = ws_client_max_id($sync['messages'] ?? array(), max(0, $after_id));
			$cursor = (string) ($sync['cursor'] ?? '');
			$etag = ws_client_etag($answer['headers']);

			// More new messages wait: ask again at once.
			if (!empty($sync['more'])) {
				continue;
			}
		} elseif ($answer['status'] !== 304) {
			// A network blip or a busy server is waited out; a refusal is not.
			if ($answer['status'] === 401 || $answer['status'] === 403 || $answer['status'] === 404 || $answer['status'] === 400) {
				fwrite(STDERR, 'ws_wait: ' . ws_client_failure($answer) . "\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			fwrite(STDERR, 'ws_wait: ' . ws_client_failure($answer) . ", trying again\n");
		}

		if (time() + $plan['interval'] > $deadline) {
			fwrite(STDERR, "ws_wait: no matching message before the timeout\n");
			return WS_CLIENT_EXIT_TIMEOUT;
		}

		sleep($plan['interval']);
	}
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_wait_main(array_slice($argv, 1)));
}

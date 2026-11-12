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
 *                               [--from=person|app] [--mention=APP_ID]
 *                               [--changed=ID[,ID…]]
 *                               [--after-id=N] [--timeout=SECONDS]
 *                               [--interval=SECONDS]
 *         php tools/ws_wait.php --requests [--timeout=SECONDS]
 *                               [--interval=SECONDS]
 * Exit:   0 a message matched, or a request is queued (printed on stdout);
 *         1 the site refused or the channel can no longer be read; 2 usage
 *         or missing settings; 3 the timeout passed with no match.
 *
 * --requests waits on the application's own request queue instead of a
 * channel's text: it asks GET /workspace/agent-requests every --interval
 * seconds (15 by default, at least 5) and prints the oldest queued request
 * as one JSON line (request_id, message_id, channel_id, channel, wake,
 * wake_kind, requested_by, text: the first 500 characters of the words, a
 * wake's note when it has no message). An answer to the agent's message and
 * a scheduled wake make a request without naming the agent, so only this
 * mode sees them. It needs no channel and takes no text rule or --changed.
 * A request not yet claimed wakes it again at once each time it starts.
 *
 * Only messages written after the first answer count, unless --after-id
 * names an earlier id: the first answer carries the channel's newest 50,
 * which are the past. --from=person wakes only on a person's message (an
 * application's line, CI's included, never), --from=app only on an
 * application's; --mention=N only on a message tagging application N
 * (<@app:N>). --changed waits for one of the named messages to change - a
 * checklist item ticked, an edit - and prints it as it is now, its checks
 * included; with --changed alone new messages do not wake, with a rule as
 * well either does. Settings come from the environment
 * (tools/ws_client.php). --timeout defaults to 600, --interval to 5 (at
 * least 2) for a channel's text.
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
 * @return array{rule: array<string,string>, changed: int[], requests: bool, after_id: int, timeout: int, interval: int, error: string}
 */
function ws_wait_plan($options)
{
	$requests = !empty($options['requests']);
	$out = array('rule' => array(), 'changed' => array(), 'requests' => $requests, 'after_id' => -1, 'timeout' => 600, 'interval' => $requests ? 15 : 5, 'error' => '');

	foreach (array('prefix', 'contains', 'regex', 'from', 'mention') as $name) {
		if (isset($options[$name]) && is_string($options[$name]) && $options[$name] !== '') {
			$out['rule'][$name] = $options[$name];
		}
	}

	if (isset($out['rule']['from']) && !in_array($out['rule']['from'], array('person', 'app'), true)) {
		$out['error'] = '--from must be person or app';
	}

	if (isset($out['rule']['mention']) && (!ctype_digit($out['rule']['mention']) || (int) $out['rule']['mention'] < 1)) {
		$out['error'] = '--mention must be an application id';
	}

	if (isset($options['changed'])) {
		$ids = is_string($options['changed']) ? explode(',', $options['changed']) : array('');

		foreach ($ids as $id) {
			$id = trim($id);

			if (!ctype_digit($id) || (int) $id < 1) {
				$out['error'] = '--changed must be message ids separated by commas';
				break;
			}

			$out['changed'][] = (int) $id;
		}

		$out['changed'] = array_values(array_unique($out['changed']));
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

	// The request queue is read as a whole on every ask (no ETag), so it is
	// asked less often than a channel.
	$out['interval'] = max($requests ? 5 : 2, $out['interval']);

	if ($requests && ($out['rule'] || isset($options['changed']) || isset($options['after-id']))) {
		$out['error'] = '--requests takes no text rule, --changed or --after-id';
	}

	if ($requests && !is_bool($options['requests'])) {
		$out['error'] = '--requests takes no value';
	}

	return $out;
}

/**
 * The first matching message of a sync answer, null when none: one of the
 * watched ids among changed first, then a new message matching the rule.
 * With watched ids and no rule, new messages match nothing.
 *
 * @param array<string,mixed> $sync
 * @param array<string,string> $rule
 * @param int $after_id
 * @param int[] $changed message ids watched for a change
 * @return array<string,mixed>|null
 */
function ws_wait_pick($sync, $rule, $after_id, $changed = array())
{
	if ($changed) {
		foreach ((isset($sync['changed']) && is_array($sync['changed'])) ? $sync['changed'] : array() as $message) {
			if (is_array($message) && in_array((int) ($message['id'] ?? 0), $changed, true)) {
				return $message;
			}
		}

		if (!$rule) {
			return null;
		}
	}

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

/**
 * The request a waiter on the queue wakes on, null when none: the oldest
 * (smallest id) of the queued ones in a GET /workspace/agent-requests
 * answer. A request already handed out (sent, running) is someone's work.
 *
 * @param mixed $list the decoded answer, {data: [...], page: {...}}
 * @return array<string,mixed>|null
 */
function ws_wait_request_pick($list)
{
	$found = null;

	foreach ((is_array($list) && isset($list['data']) && is_array($list['data'])) ? $list['data'] : array() as $row) {
		if (!is_array($row) || ($row['status'] ?? '') !== 'queued' || (int) ($row['id'] ?? 0) < 1) {
			continue;
		}

		if ($found === null || (int) $row['id'] < (int) $found['id']) {
			$found = $row;
		}
	}

	return $found;
}

/**
 * The line printed for a queued request: what the session needs to decide
 * whether to take it. The words are the message's plain text (its tags as
 * names), or a wake's note when no message made it; at most 500 characters,
 * never cut inside one.
 *
 * @param array<string,mixed> $row a row of GET /workspace/agent-requests
 * @return array<string,mixed>
 */
function ws_wait_request_line($row)
{
	$message = (isset($row['message']) && is_array($row['message'])) ? $row['message'] : array();
	$channel = (isset($row['channel']) && is_array($row['channel'])) ? $row['channel'] : array();
	$wake = (isset($row['wake']) && is_array($row['wake'])) ? $row['wake'] : array();
	$by = (isset($row['requested_by']) && is_array($row['requested_by'])) ? $row['requested_by'] : array();
	$words = (string) ($message['text'] ?? '');

	if ($words === '') {
		$words = (string) ($wake['note'] ?? '');
	}

	// The u modifier counts characters; text that is not UTF-8 is cut by bytes.
	$words = preg_match('/^.{0,500}/su', $words, $m) ? $m[0] : substr($words, 0, 500);

	return array(
		'request_id'   => (int) ($row['id'] ?? 0),
		'message_id'   => (int) ($message['id'] ?? 0),
		'channel_id'   => (int) ($channel['id'] ?? 0),
		'channel'      => (string) ($channel['name'] ?? ''),
		'wake'         => (string) ($wake['kind'] ?? '') !== '',
		'wake_kind'    => (string) ($wake['kind'] ?? ''),
		'requested_by' => array('user_id' => isset($by['user_id']) ? (int) $by['user_id'] : null, 'name' => (string) ($by['name'] ?? '')),
		'text'         => $words,
	);
}

/**
 * Waits on the application's request queue (--requests).
 *
 * @param array<string,mixed> $settings
 * @param array<string,mixed> $plan
 * @return int exit code
 */
function ws_wait_requests($settings, $plan)
{
	$deadline = time() + $plan['timeout'];

	while (true) {
		$answer = ws_client_request('GET', $settings['site'] . '/workspace/agent-requests', $settings, null, array());

		if ($answer['status'] === 200) {
			$list = json_decode($answer['body'], true);

			if (!is_array($list)) {
				fwrite(STDERR, "ws_wait: the answer is not JSON\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			$row = ws_wait_request_pick($list);

			if ($row !== null) {
				fwrite(STDOUT, json_encode(ws_wait_request_line($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
				return WS_CLIENT_EXIT_OK;
			}
		} elseif ($answer['status'] === 401 || $answer['status'] === 403 || $answer['status'] === 404 || $answer['status'] === 400) {
			fwrite(STDERR, 'ws_wait: ' . ws_client_failure($answer) . "\n");
			return WS_CLIENT_EXIT_HTTP;
		} else {
			// A network blip or a busy server is waited out.
			fwrite(STDERR, 'ws_wait: ' . ws_client_failure($answer) . ", trying again\n");
		}

		if (time() + $plan['interval'] > $deadline) {
			fwrite(STDERR, "ws_wait: no queued request before the timeout\n");
			return WS_CLIENT_EXIT_TIMEOUT;
		}

		sleep($plan['interval']);
	}
}

function ws_wait_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$plan = ws_wait_plan($options);
	$settings = ws_client_settings($options, getenv(), !$plan['requests']);

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_wait: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($plan['error'] !== '' || $rest) {
		fwrite(STDERR, 'ws_wait: ' . ($plan['error'] !== '' ? $plan['error'] : 'unexpected argument ' . $rest[0]) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($plan['requests']) {
		return ws_wait_requests($settings, $plan);
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
			$match = ws_wait_pick($sync, $plan['rule'], max(0, $after_id), $plan['changed']);

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

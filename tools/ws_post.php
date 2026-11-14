<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes one message into a Workspace channel through a site's external API
 * with an application key (POST /workspace/channels/{id}/messages). An agent
 * or a CI step uses it to say "[ci] …" or "[ready] …" in an agent channel.
 * With --file it posts one file with the text as its message
 * (POST /workspace/channels/{id}/files, the file as the form part "file"),
 * so a screenshot reaches the channel itself rather than as a link.
 *
 * Usage:  php tools/ws_post.php [--channel=ID] [--kind=message|note|decision]
 *                               [--parent=ID] [--idempotency-key=K]
 *                               [--skip-missing] "text"
 *         php tools/ws_post.php --file=PATH [--channel=ID] [--parent=ID]
 *                               [--idempotency-key=K] [--skip-missing] ["text"]
 *         The text may also come on stdin ("-", or no text argument without
 *         --file). A file goes alone with its text: one file a call, and a
 *         set of screenshots is one message each, answered under the first
 *         with --parent. A refusal (size, kind) prints the API's message.
 * Exit:   0 written (or skipped with --skip-missing while settings are
 *         missing); 1 the site refused; 2 usage or missing settings; 3 no
 *         answer twice, so the message may or may not be written: stderr
 *         names the Idempotency-Key, and the caller repeats with
 *         --idempotency-key=<key>, never without, so it is written once.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET, PG_WS_CHANNEL. --parent answers a message of the
 * channel, as Reply does in the panel: the decision that closes a checklist
 * question answers the question. The answer's message id is printed on
 * stdout; nothing secret is printed.
 *
 * Every post carries an Idempotency-Key: the given one, or one made for the
 * run. A call without an answer (network error or timeout, a gateway's 502
 * or 504) may still have been written, so it is repeated once with the same
 * key; a 409 idempotency_in_progress (the first call is still running) waits
 * for its Retry-After and asks once more.
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
 * The request body of a post, or an error line.
 *
 * @param string $text
 * @param array<string,string|bool> $options
 * @return array{body: array<string,string|int>|null, error: string}
 */
function ws_post_body($text, $options)
{
	$text = trim((string) $text);
	$kind = isset($options['kind']) && is_string($options['kind']) ? $options['kind'] : 'message';

	if ($text === '') {
		return array('body' => null, 'error' => 'no text to post');
	}

	// The route's own limit (max_length 4000).
	if (function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') > 4000 : strlen($text) > 4000) {
		return array('body' => null, 'error' => 'the text is longer than 4000 characters');
	}

	if (!in_array($kind, array('message', 'note', 'decision'), true)) {
		return array('body' => null, 'error' => 'kind must be message, note or decision');
	}

	$body = array('text' => $text);

	if ($kind !== 'message') {
		$body['kind'] = $kind;
	}

	return ws_post_parent($body, $options);
}

/**
 * Adds --parent to a body as parent_id, or refuses it.
 *
 * @param array<string,string|int> $body
 * @param array<string,string|bool> $options
 * @return array{body: array<string,string|int>|null, error: string}
 */
function ws_post_parent($body, $options)
{
	if (isset($options['parent'])) {
		if (!is_string($options['parent']) || !ctype_digit($options['parent']) || (int) $options['parent'] < 1) {
			return array('body' => null, 'error' => '--parent must be a message id');
		}

		$body['parent_id'] = (int) $options['parent'];
	}

	return array('body' => $body, 'error' => '');
}

/**
 * The form fields of a file post (text, parent_id) and the file's path, or
 * an error line. The text may be empty; the site checks the file's size and
 * kind and answers a refusal with its own message.
 *
 * @param string $text
 * @param array<string,string|bool> $options with file set
 * @return array{body: array<string,string|int>|null, file: string, error: string}
 */
function ws_post_file_body($text, $options)
{
	$path = isset($options['file']) && is_string($options['file']) ? $options['file'] : '';
	$text = trim((string) $text);

	if ($path === '') {
		return array('body' => null, 'file' => '', 'error' => '--file must name a file');
	}

	if (!is_file($path) || !is_readable($path)) {
		return array('body' => null, 'file' => '', 'error' => 'cannot read the file ' . $path);
	}

	if (isset($options['kind']) && $options['kind'] !== 'message') {
		return array('body' => null, 'file' => '', 'error' => '--kind cannot go with --file');
	}

	if (function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') > 4000 : strlen($text) > 4000) {
		return array('body' => null, 'file' => '', 'error' => 'the text is longer than 4000 characters');
	}

	$built = ws_post_parent($text !== '' ? array('text' => $text) : array(), $options);

	return array('body' => $built['body'], 'file' => $built['body'] === null ? '' : $path, 'error' => $built['error']);
}

/**
 * The Idempotency-Key the tool makes for a run without --idempotency-key.
 *
 * @param string $bytes random bytes
 * @return string
 */
function ws_post_own_key($bytes)
{
	return 'ws-post-' . bin2hex((string) $bytes);
}

/**
 * Whether a call got no answer of the site: it may have been written, and
 * only a repeat with the same key tells. A gateway's 502 or 504 is a timeout
 * in front of the site, which may still be running the write.
 *
 * @param array{status: int, body: string, error: string} $answer
 * @return bool
 */
function ws_post_no_answer($answer)
{
	return in_array((int) $answer['status'], array(0, 502, 504), true);
}

/**
 * Whether the site is still running an earlier call with the same key.
 *
 * @param array{status: int, body: string, error: string} $answer
 * @return bool
 */
function ws_post_in_progress($answer)
{
	if ((int) $answer['status'] !== 409) {
		return false;
	}

	$json = json_decode((string) $answer['body'], true);

	return is_array($json) && isset($json['error']['code']) && $json['error']['code'] === 'idempotency_in_progress';
}

/**
 * Seconds to wait before asking again, from a Retry-After header: 1 to 10,
 * 1 when the header is missing.
 *
 * @param string $headers raw header block
 * @return int
 */
function ws_post_retry_after($headers)
{
	if (preg_match('/^retry-after:\s*(\d+)\s*$/mi', (string) $headers, $m)) {
		return max(1, min(10, (int) $m[1]));
	}

	return 1;
}

/**
 * Sends a post with its key: once more after a call without an answer, and
 * once more after a wait while the site is still running an earlier call.
 * no_answer is true when the last call left the outcome unknown.
 *
 * @param callable $send returns the answer of one call (ws_client_request())
 * @param callable $sleep takes seconds
 * @return array{answer: array{status: int, headers: string, body: string, error: string}, no_answer: bool, calls: int}
 */
function ws_post_send($send, $sleep)
{
	$answer = $send();
	$calls = 1;

	if (ws_post_no_answer($answer)) {
		$answer = $send();
		$calls++;
	}

	if (ws_post_in_progress($answer)) {
		$sleep(ws_post_retry_after($answer['headers']));
		$answer = $send();
		$calls++;
	}

	return array(
		'answer'    => $answer,
		'no_answer' => ws_post_no_answer($answer) || ws_post_in_progress($answer),
		'calls'     => $calls,
	);
}

function ws_post_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$settings = ws_client_settings($options, getenv());

	if ($settings['missing']) {
		if (!empty($options['skip-missing'])) {
			fwrite(STDOUT, 'ws_post: skipped, not set: ' . implode(', ', $settings['missing']) . "\n");
			return WS_CLIENT_EXIT_OK;
		}

		fwrite(STDERR, 'ws_post: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$file = isset($options['file']);
	$text = $rest ? implode(' ', $rest) : ($file ? '' : '-');

	if ($text === '-') {
		$text = (string) stream_get_contents(STDIN);
	}

	$built = $file ? ws_post_file_body($text, $options) : ws_post_body($text, $options);

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_post: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$headers = ws_client_idempotency_headers($options);

	if (!$headers) {
		$headers = ws_client_idempotency_headers(array('idempotency-key' => ws_post_own_key(random_bytes(12))));
	}

	$key = substr($headers[0], strlen('Idempotency-Key: '));
	$url = $settings['site'] . '/workspace/channels/' . $settings['channel'] . ($file ? '/files' : '/messages');
	$body = $built['body'];

	if ($file && !class_exists('CURLFile')) {
		fwrite(STDERR, "ws_post: the PHP curl extension is missing\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	if ($file) {
		$path = $built['file'];
		$mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
		$body['file'] = new CURLFile($path, $mime !== '' ? $mime : 'application/octet-stream', basename($path));
	}

	$sent = ws_post_send(function () use ($url, $settings, $body, $headers, $file) {
		return ws_client_request('POST', $url, $settings, $body, $headers, $file);
	}, function ($seconds) {
		sleep($seconds);
	});
	$answer = $sent['answer'];

	if ($sent['no_answer']) {
		fwrite(STDERR, 'ws_post: no answer (' . ws_client_failure($answer) . '), the message may be written; repeat with --idempotency-key=' . $key . "\n");
		return WS_CLIENT_EXIT_TIMEOUT;
	}

	if ($answer['status'] < 200 || $answer['status'] >= 300) {
		fwrite(STDERR, 'ws_post: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$json = json_decode($answer['body'], true);
	fwrite(STDOUT, (is_array($json) && isset($json['id']) ? (int) $json['id'] : 0) . "\n");

	return WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_post_main(array_slice($argv, 1)));
}

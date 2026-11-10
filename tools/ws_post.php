<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes one message into a Workspace channel through a site's external API
 * with an application key (POST /workspace/channels/{id}/messages). An agent
 * or a CI step uses it to say "[ci] …" or "[ready] …" in an agent channel.
 *
 * Usage:  php tools/ws_post.php [--channel=ID] [--kind=message|note|decision]
 *                               [--parent=ID] [--idempotency-key=K]
 *                               [--skip-missing] "text"
 *         The text may also come on stdin ("-" or no text argument).
 * Exit:   0 written (or skipped with --skip-missing while settings are
 *         missing); 1 the site refused or could not be reached; 2 usage or
 *         missing settings.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET, PG_WS_CHANNEL. --parent answers a message of the
 * channel, as Reply does in the panel: the decision that closes a checklist
 * question answers the question. The answer's message id is printed on
 * stdout; nothing secret is printed.
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

	if (isset($options['parent'])) {
		if (!is_string($options['parent']) || !ctype_digit($options['parent']) || (int) $options['parent'] < 1) {
			return array('body' => null, 'error' => '--parent must be a message id');
		}

		$body['parent_id'] = (int) $options['parent'];
	}

	return array('body' => $body, 'error' => '');
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

	$text = $rest ? implode(' ', $rest) : '-';

	if ($text === '-') {
		$text = (string) stream_get_contents(STDIN);
	}

	$built = ws_post_body($text, $options);

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_post: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$headers = array();

	if (isset($options['idempotency-key']) && is_string($options['idempotency-key']) && $options['idempotency-key'] !== '') {
		$headers[] = 'Idempotency-Key: ' . substr(preg_replace('/[^A-Za-z0-9._:-]/', '-', $options['idempotency-key']), 0, 64);
	}

	$url = $settings['site'] . '/workspace/channels/' . $settings['channel'] . '/messages';
	$answer = ws_client_request('POST', $url, $settings, $built['body'], $headers);

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

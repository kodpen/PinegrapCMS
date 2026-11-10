<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Asks a poll in a Workspace channel and closes it, through a site's
 * external API with an application key. An agent asks a question with one
 * answer as a poll and closes it itself once it has read the vote.
 *
 * Usage:  php tools/ws_poll.php ask [--channel=ID] [--idempotency-key=K]
 *                               "question" "option 1" "option 2" [...]
 *         php tools/ws_poll.php close POLL_ID
 *         ask: the question first, then two to ten options, the recommended
 *         one first (POST /workspace/channels/{id}/polls). One answer per
 *         voter and voters named: multiple and anonymous are not sent.
 *         Prints "<message id> <poll id>".
 *         close: closes a poll this application asked
 *         (POST /workspace/polls/{id}/close); needs no channel. Prints the
 *         poll's result_message_id: the decision of a clear winner, or the
 *         system line of a tie or of no vote.
 * Exit:   0 done; 1 the site refused (403 for another's poll) or could not
 *         be reached; 2 usage or missing settings.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET, PG_WS_CHANNEL (ask only). Nothing secret is
 * printed.
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
 * The subcommand and its arguments, or an error line.
 *
 * @param string[] $rest the positional arguments
 * @return array{command: string, args: string[], error: string}
 */
function ws_poll_command($rest)
{
	$command = $rest ? (string) $rest[0] : '';
	$args = array_slice($rest, 1);

	if ($command === '') {
		return array('command' => '', 'args' => array(), 'error' => 'no command: ask or close');
	}

	if ($command !== 'ask' && $command !== 'close') {
		return array('command' => '', 'args' => array(), 'error' => 'unknown command ' . $command . ': ask or close');
	}

	if ($command === 'close' && count($args) !== 1) {
		return array('command' => '', 'args' => array(), 'error' => 'close takes one poll id');
	}

	return array('command' => $command, 'args' => $args, 'error' => '');
}

/**
 * A poll id from the command line; 0 when it is not a whole number of at
 * least 1.
 *
 * @param string $arg
 * @return int
 */
function ws_poll_id($arg)
{
	$arg = (string) $arg;

	return $arg !== '' && ctype_digit($arg) && (int) $arg >= 1 ? (int) $arg : 0;
}

/**
 * The connection settings of a subcommand: close works on a poll id, so a
 * missing channel does not stop it.
 *
 * @param string $command ask or close
 * @param array<string,string|bool> $options
 * @param array<string,string> $env
 * @return array{site: string, key: string, secret: string, channel: int, missing: string[]}
 */
function ws_poll_settings($command, $options, $env)
{
	$settings = ws_client_settings($options, $env);

	if ($command === 'close') {
		$settings['missing'] = array_values(array_diff($settings['missing'], array('PG_WS_CHANNEL')));
	}

	return $settings;
}

/**
 * The text length the route counts (characters, not bytes).
 *
 * @param string $text
 * @return int
 */
function ws_poll_length($text)
{
	return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

/**
 * The request body of an ask, or an error line. Options are trimmed, blank
 * and repeated ones left out, the order kept.
 *
 * @param string $question
 * @param string[] $options
 * @return array{body: array{question: string, options: string[]}|null, error: string}
 */
function ws_poll_ask_body($question, $options)
{
	$question = trim((string) $question);

	if ($question === '') {
		return array('body' => null, 'error' => 'no question to ask');
	}

	// The route's own limits: question max_length 4000, options two to ten,
	// each up to 255 characters.
	if (ws_poll_length($question) > 4000) {
		return array('body' => null, 'error' => 'the question is longer than 4000 characters');
	}

	$list = array();

	foreach ($options as $option) {
		$option = trim((string) $option);

		if ($option === '' || in_array($option, $list, true)) {
			continue;
		}

		if (ws_poll_length($option) > 255) {
			return array('body' => null, 'error' => 'an option is longer than 255 characters');
		}

		$list[] = $option;
	}

	if (count($list) < 2 || count($list) > 10) {
		return array('body' => null, 'error' => 'a poll takes two to ten different options');
	}

	return array('body' => array('question' => $question, 'options' => $list), 'error' => '');
}

function ws_poll_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$parsed = ws_poll_command($rest);

	if ($parsed['error'] !== '') {
		fwrite(STDERR, 'ws_poll: ' . $parsed['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($parsed['command'] === 'ask') {
		$built = ws_poll_ask_body($parsed['args'] ? $parsed['args'][0] : '', array_slice($parsed['args'], 1));
		$poll = 0;
	} else {
		$built = array('body' => array(), 'error' => '');
		$poll = ws_poll_id($parsed['args'][0]);

		if ($poll < 1) {
			$built = array('body' => null, 'error' => 'the poll id must be a whole number of at least 1');
		}
	}

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_poll: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = ws_poll_settings($parsed['command'], $options, getenv());

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_poll: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($parsed['command'] === 'ask') {
		$url = $settings['site'] . '/workspace/channels/' . $settings['channel'] . '/polls';
		$answer = ws_client_request('POST', $url, $settings, $built['body'], ws_client_idempotency_headers($options));
	} else {
		$url = $settings['site'] . '/workspace/polls/' . $poll . '/close';
		$answer = ws_client_request('POST', $url, $settings, null);
	}

	if ($answer['status'] < 200 || $answer['status'] >= 300) {
		fwrite(STDERR, 'ws_poll: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$json = json_decode($answer['body'], true);
	$found = is_array($json) && isset($json['poll']) && is_array($json['poll']) ? $json['poll'] : array();

	if ($parsed['command'] === 'ask') {
		$message = is_array($json) && isset($json['id']) ? (int) $json['id'] : 0;
		fwrite(STDOUT, $message . ' ' . (isset($found['id']) ? (int) $found['id'] : 0) . "\n");
	} else {
		fwrite(STDOUT, (isset($found['result_message_id']) ? (int) $found['result_message_id'] : 0) . "\n");
	}

	return WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_poll_main(array_slice($argv, 1)));
}

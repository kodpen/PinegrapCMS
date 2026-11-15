<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Asks a poll in a Workspace channel, takes it back, asks it again and
 * closes it, through a site's external API with an application key. A poll
 * an application asks is an agent question: the first answer of a person it
 * asks settles and closes it and wakes the asking agent.
 *
 * Usage:  php tools/ws_poll.php ask [--channel=ID] [--idempotency-key=K]
 *                               [--recommend] [--other[=LABEL]] [--multiple]
 *                               "question" "option 1" "option 2" [...]
 *         php tools/ws_poll.php again POLL_ID [--recommend] [--other[=LABEL]]
 *                               [--multiple] "question" "option 1" [...]
 *         php tools/ws_poll.php withdraw POLL_ID
 *         php tools/ws_poll.php close POLL_ID
 *         ask: the question first, then two to ten options, the recommended
 *         one first (POST /workspace/channels/{id}/polls). An option written
 *         "label :: description" carries a line under its label.
 *         --recommend marks the first option Recommended; --other adds the
 *         "Other" row the person answers in their own words (LABEL, else
 *         "Other"); --multiple takes several answers. Voters are named.
 *         Prints "<message id> <poll id>".
 *         again: the same body for a poll asked again
 *         (POST /workspace/polls/{id}/again); an open one is taken back by
 *         it. Prints "<message id> <poll id>" of the new poll.
 *         withdraw: takes back a poll this application asked
 *         (POST /workspace/polls/{id}/withdraw). Prints the poll's message id.
 *         close: closes a poll this application asked
 *         (POST /workspace/polls/{id}/close); needs no channel. Prints the
 *         poll's result_message_id. An agent question closes itself when it
 *         is answered and is refused here (exit 1).
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
	$known = array('ask', 'again', 'withdraw', 'close');

	if ($command === '') {
		return array('command' => '', 'args' => array(), 'error' => 'no command: ' . implode(', ', $known));
	}

	if (!in_array($command, $known, true)) {
		return array('command' => '', 'args' => array(), 'error' => 'unknown command ' . $command . ': ' . implode(', ', $known));
	}

	if (($command === 'close' || $command === 'withdraw') && count($args) !== 1) {
		return array('command' => '', 'args' => array(), 'error' => $command . ' takes one poll id');
	}

	if ($command === 'again' && count($args) < 2) {
		return array('command' => '', 'args' => array(), 'error' => 'again takes a poll id, the question and its options');
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
 * The connection settings of a subcommand: all but ask work on a poll id,
 * so a missing channel does not stop them.
 *
 * @param string $command ask, again, withdraw or close
 * @param array<string,string|bool> $options
 * @param array<string,string> $env
 * @return array{site: string, key: string, secret: string, channel: int, missing: string[]}
 */
function ws_poll_settings($command, $options, $env)
{
	$settings = ws_client_settings($options, $env);

	if ($command !== 'ask') {
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
 * One option as the command line writes it: "label :: description" gives
 * the line under the label. Pure.
 *
 * @param string $option
 * @return array{label: string, description: string}
 */
function ws_poll_option_split($option)
{
	$parts = explode('::', (string) $option, 2);

	return array('label' => trim($parts[0]), 'description' => isset($parts[1]) ? trim($parts[1]) : '');
}

/**
 * The request body of an ask (and of an ask-again), or an error line.
 * Options are trimmed, blank and repeated labels left out, the order kept.
 * An option stays a plain text unless it carries a description or the
 * Recommended mark; the "Other" row is an object of its own at the end.
 *
 * @param string $question
 * @param string[] $options
 * @param array<string,string|bool> $flags recommend, other (true or a label), multiple
 * @return array{body: array|null, error: string}
 */
function ws_poll_ask_body($question, $options, $flags = array())
{
	$question = trim((string) $question);

	if ($question === '') {
		return array('body' => null, 'error' => 'no question to ask');
	}

	// The route's own limits: question max_length 4000, options two to ten
	// (the "Other" row among the ten), each label up to 255 characters, a
	// description up to 500.
	if (ws_poll_length($question) > 4000) {
		return array('body' => null, 'error' => 'the question is longer than 4000 characters');
	}

	$other = isset($flags['other']) && $flags['other'] !== false;
	$labels = array();
	$list = array();

	foreach ($options as $option) {
		$option = ws_poll_option_split($option);

		if ($option['label'] === '' || in_array($option['label'], $labels, true)) {
			continue;
		}

		if (ws_poll_length($option['label']) > 255) {
			return array('body' => null, 'error' => 'an option is longer than 255 characters');
		}

		if (ws_poll_length($option['description']) > 500) {
			return array('body' => null, 'error' => 'a description is longer than 500 characters');
		}

		$labels[] = $option['label'];
		$list[] = $option;
	}

	if (count($list) < 2 || count($list) + ($other ? 1 : 0) > 10) {
		return array('body' => null, 'error' => 'a poll takes two to ten different options, the "Other" row among the ten');
	}

	$sent = array();

	foreach ($list as $index => $option) {
		$recommended = ($index === 0) && !empty($flags['recommend']);

		if ($option['description'] === '' && !$recommended) {
			$sent[] = $option['label'];
			continue;
		}

		$item = array('label' => $option['label']);

		if ($option['description'] !== '') {
			$item['description'] = $option['description'];
		}

		if ($recommended) {
			$item['recommended'] = true;
		}

		$sent[] = $item;
	}

	if ($other) {
		$label = is_string($flags['other']) ? trim($flags['other']) : '';
		$sent[] = array('label' => ($label !== '') ? $label : 'Other', 'other' => true);
	}

	$body = array('question' => $question, 'options' => $sent);

	if (!empty($flags['multiple'])) {
		$body['multiple'] = true;
	}

	return array('body' => $body, 'error' => '');
}

/**
 * The ask flags from the command line options. Pure.
 *
 * @param array<string,string|bool> $options
 * @return array<string,string|bool>
 */
function ws_poll_flags($options)
{
	return array(
		'recommend' => !empty($options['recommend']),
		'other'     => isset($options['other']) ? $options['other'] : false,
		'multiple'  => !empty($options['multiple']),
	);
}

function ws_poll_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$parsed = ws_poll_command($rest);

	if ($parsed['error'] !== '') {
		fwrite(STDERR, 'ws_poll: ' . $parsed['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$command = $parsed['command'];
	$poll = 0;

	if ($command === 'ask') {
		$built = ws_poll_ask_body($parsed['args'] ? $parsed['args'][0] : '', array_slice($parsed['args'], 1), ws_poll_flags($options));
	} else {
		$poll = ws_poll_id($parsed['args'][0]);
		$built = ($command === 'again')
			? ws_poll_ask_body(isset($parsed['args'][1]) ? $parsed['args'][1] : '', array_slice($parsed['args'], 2), ws_poll_flags($options))
			: array('body' => array(), 'error' => '');

		if ($poll < 1) {
			$built = array('body' => null, 'error' => 'the poll id must be a whole number of at least 1');
		}
	}

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_poll: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = ws_poll_settings($command, $options, getenv());

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_poll: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($command === 'ask') {
		$url = $settings['site'] . '/workspace/channels/' . $settings['channel'] . '/polls';
		$answer = ws_client_request('POST', $url, $settings, $built['body'], ws_client_idempotency_headers($options));
	} elseif ($command === 'again') {
		$url = $settings['site'] . '/workspace/polls/' . $poll . '/again';
		$answer = ws_client_request('POST', $url, $settings, $built['body'], ws_client_idempotency_headers($options));
	} else {
		$url = $settings['site'] . '/workspace/polls/' . $poll . '/' . $command;
		$answer = ws_client_request('POST', $url, $settings, null);
	}

	if ($answer['status'] < 200 || $answer['status'] >= 300) {
		fwrite(STDERR, 'ws_poll: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$json = json_decode($answer['body'], true);
	$found = is_array($json) && isset($json['poll']) && is_array($json['poll']) ? $json['poll'] : array();

	$message = is_array($json) && isset($json['id']) ? (int) $json['id'] : 0;

	if ($command === 'ask' || $command === 'again') {
		fwrite(STDOUT, $message . ' ' . (isset($found['id']) ? (int) $found['id'] : 0) . "\n");
	} elseif ($command === 'withdraw') {
		fwrite(STDOUT, $message . "\n");
	} else {
		fwrite(STDOUT, (isset($found['result_message_id']) ? (int) $found['result_message_id'] : 0) . "\n");
	}

	return WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_poll_main(array_slice($argv, 1)));
}

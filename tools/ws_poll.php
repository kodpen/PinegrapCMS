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
 *                               [--file=PATH]
 *                               "question" "option 1" "option 2" [...]
 *         php tools/ws_poll.php again POLL_ID [--recommend] [--other[=LABEL]]
 *                               [--multiple] "question" "option 1" [...]
 *         php tools/ws_poll.php withdraw POLL_ID
 *         php tools/ws_poll.php close POLL_ID
 *         php tools/ws_poll.php --help
 *         ask: the question first, then two to ten options, the recommended
 *         one first (POST /workspace/channels/{id}/polls). An option written
 *         "label :: description" carries a line under its label; one ending
 *         in ":: #N" ("label :: #N", "label :: description :: #N") shows the
 *         picture posted as message N in the poll's channel inside the
 *         option's own box. The tool reads message N with the same key
 *         (GET /workspace/channels/{id}/messages) and sends its file id;
 *         a message that is not a picture there stops it (exit 1).
 *         A question about a picture, a file or an earlier message names
 *         it with <#message:N> in its text, which the person opens.
 *         --file=PATH sends one picture of the question's own with it
 *         (content_base64 and name): an image (JPG, PNG, GIF, WebP, AVIF or
 *         BMP) the question's message carries. A file that is missing or
 *         cannot be read stops the tool with exit 1 before any call.
 *         --recommend marks the first option Recommended; --other adds the
 *         "Other" row the person answers in their own words (LABEL, else
 *         "Other"); --multiple takes several answers. Voters are named.
 *         Prints "<message id> <poll id>".
 *         again: the same body for a poll asked again
 *         (POST /workspace/polls/{id}/again); an open one is taken back by
 *         it. The old question's picture goes with it unless --file sends
 *         a new one. A picture needs the poll's channel (PG_WS_CHANNEL or
 *         --channel). Prints "<message id> <poll id>" of the new poll.
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
 * PG_WS_KEY, PG_WS_SECRET, PG_WS_CHANNEL (ask, and again with a picture).
 * Nothing secret is
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
 * The usage lines, printed with --help and after a command error. Pure.
 *
 * @return string
 */
function ws_poll_usage()
{
	return "Usage: php tools/ws_poll.php ask [--channel=ID] [--idempotency-key=K] [--recommend]\n"
		. "                                [--other[=LABEL]] [--multiple] [--file=PATH] \"question\" \"option\" \"option\" [...]\n"
		. "       php tools/ws_poll.php again POLL_ID [same flags] \"question\" \"option\" \"option\" [...]\n"
		. "       php tools/ws_poll.php withdraw POLL_ID\n"
		. "       php tools/ws_poll.php close POLL_ID\n"
		. "An option: \"label\", \"label :: description\", \"label :: #N\" or \"label :: description :: #N\",\n"
		. "where N is the message number of a picture posted in the poll's channel; it shows in the\n"
		. "option's own box (again with a picture needs PG_WS_CHANNEL or --channel).\n"
		. "--file=PATH sends a picture of the question's own with it (JPG, PNG, GIF, WebP, AVIF or BMP);\n"
		. "a missing or unreadable file stops with exit 1 before any call. Asked again, the old\n"
		. "question's picture goes with it unless --file sends a new one.\n"
		. "A question about a picture, a file or an earlier message names it with <#message:N> in its\n"
		. "text; the person opens that message from the tag.\n";
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
 * so a missing channel does not stop them, unless an option's picture has
 * to be read from the channel.
 *
 * @param string $command ask, again, withdraw or close
 * @param array<string,string|bool> $options
 * @param array<string,string> $env
 * @param bool $pictures an option carries a picture by its message number
 * @return array{site: string, key: string, secret: string, channel: int, missing: string[]}
 */
function ws_poll_settings($command, $options, $env, $pictures = false)
{
	$settings = ws_client_settings($options, $env);

	if ($command !== 'ask' && !$pictures) {
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
 * the line under the label, and a last part "#N" the picture posted as
 * message N ("label :: #N", "label :: description :: #N"). Pure.
 *
 * @param string $option
 * @return array{label: string, description: string, picture: int}
 */
function ws_poll_option_split($option)
{
	$parts = explode('::', (string) $option, 2);
	$rest = isset($parts[1]) ? trim($parts[1]) : '';
	$picture = 0;

	if (preg_match('/^#(\d+)$/', $rest, $match)) {
		$picture = (int) $match[1];
		$rest = '';
	} elseif (preg_match('/^(.*?)\s*::\s*#(\d+)$/s', $rest, $match)) {
		$picture = (int) $match[2];
		$rest = trim($match[1]);
	}

	return array('label' => trim($parts[0]), 'description' => $rest, 'picture' => $picture);
}

/**
 * The message numbers of the pictures the options name, each once, in
 * order. Pure.
 *
 * @param string[] $options
 * @return int[]
 */
function ws_poll_picture_messages($options)
{
	$ids = array();

	foreach ((array) $options as $option) {
		$picture = ws_poll_option_split($option)['picture'];

		if ($picture > 0 && !in_array($picture, $ids, true)) {
			$ids[] = $picture;
		}
	}

	return $ids;
}

/**
 * The file id of the picture message $message_id carries, from the answer
 * of GET /workspace/channels/{id}/messages?since_id=<id - 1>&limit=1, or an
 * error line. Pure.
 *
 * @param array<string,mixed>|null $json the decoded answer
 * @param int $message_id
 * @return array{file_id: int, error: string}
 */
function ws_poll_picture_file_id($json, $message_id)
{
	$rows = is_array($json) && isset($json['data']) && is_array($json['data']) ? $json['data'] : array();

	foreach ($rows as $row) {
		if (!is_array($row) || (int) ($row['id'] ?? 0) !== (int) $message_id) {
			continue;
		}

		$file = isset($row['file']) && is_array($row['file']) ? $row['file'] : array();

		if ((int) ($file['id'] ?? 0) < 1 || (string) ($file['kind'] ?? '') !== 'image') {
			return array('file_id' => 0, 'error' => 'message ' . (int) $message_id . ' carries no picture');
		}

		return array('file_id' => (int) $file['id'], 'error' => '');
	}

	return array('file_id' => 0, 'error' => 'message ' . (int) $message_id . ' is not in the channel');
}

/**
 * The question's own picture --file=PATH names, read for the request: its
 * name and its bytes base64 encoded, or an error line when the file is
 * missing, is no file or cannot be read.
 *
 * @param string $path
 * @return array{name: string, content_base64: string, error: string}
 */
function ws_poll_file_read($path)
{
	$path = (string) $path;

	if ($path === '' || !is_file($path)) {
		return array('name' => '', 'content_base64' => '', 'error' => 'no file at ' . ($path === '' ? '(empty --file)' : $path));
	}

	$bytes = is_readable($path) ? @file_get_contents($path) : false;

	if ($bytes === false) {
		return array('name' => '', 'content_base64' => '', 'error' => 'cannot read ' . $path);
	}

	return array('name' => basename($path), 'content_base64' => base64_encode($bytes), 'error' => '');
}

/**
 * The request body of an ask (and of an ask-again), or an error line.
 * Options are trimmed, blank and repeated labels left out, the order kept.
 * An option stays a plain text unless it carries a description or the
 * Recommended mark or a picture; the "Other" row is an object of its own
 * at the end.
 *
 * @param string $question
 * @param string[] $options
 * @param array<string,mixed> $flags recommend, other (true or a label),
 *        multiple, pictures (message number => file id), file (the
 *        question's own picture: name and content_base64)
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

		$file_id = 0;

		if ($option['picture'] > 0) {
			$file_id = isset($flags['pictures'][$option['picture']]) ? (int) $flags['pictures'][$option['picture']] : 0;

			if ($file_id < 1) {
				return array('body' => null, 'error' => 'the picture of message ' . $option['picture'] . ' is not known');
			}
		}

		if ($option['description'] === '' && !$recommended && $file_id === 0) {
			$sent[] = $option['label'];
			continue;
		}

		$item = array('label' => $option['label']);

		if ($option['description'] !== '') {
			$item['description'] = $option['description'];
		}

		if ($file_id > 0) {
			$item['file_id'] = $file_id;
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

	if (isset($flags['file']['content_base64']) && $flags['file']['content_base64'] !== '') {
		$body['name'] = (string) $flags['file']['name'];
		$body['content_base64'] = (string) $flags['file']['content_base64'];
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

	if (!empty($options['help'])) {
		fwrite(STDOUT, ws_poll_usage());
		return WS_CLIENT_EXIT_OK;
	}

	$parsed = ws_poll_command($rest);

	if ($parsed['error'] !== '') {
		fwrite(STDERR, 'ws_poll: ' . $parsed['error'] . "\n" . ws_poll_usage());
		return WS_CLIENT_EXIT_USAGE;
	}

	$command = $parsed['command'];
	$poll = 0;
	$question = '';
	$choices = array();

	if ($command === 'ask') {
		$question = $parsed['args'] ? $parsed['args'][0] : '';
		$choices = array_slice($parsed['args'], 1);
	} elseif ($command === 'again') {
		$question = isset($parsed['args'][1]) ? $parsed['args'][1] : '';
		$choices = array_slice($parsed['args'], 2);
	}

	// Each picture stands for itself while the body is checked; the real
	// file ids are read from the channel once the settings are known.
	$pictures = ws_poll_picture_messages($choices);
	$flags = ws_poll_flags($options);
	$flags['pictures'] = $pictures ? array_combine($pictures, $pictures) : array();

	// The question's own picture is read before any call: a file that is
	// missing or cannot be read stops here (exit 1), nothing asked.
	if (isset($options['file']) && ($command === 'ask' || $command === 'again')) {
		$file = ws_poll_file_read(is_string($options['file']) ? $options['file'] : '');

		if ($file['error'] !== '') {
			fwrite(STDERR, 'ws_poll: ' . $file['error'] . "\n");
			return WS_CLIENT_EXIT_HTTP;
		}

		$flags['file'] = $file;
	}

	if ($command === 'ask') {
		$built = ws_poll_ask_body($question, $choices, $flags);
	} else {
		$poll = ws_poll_id($parsed['args'][0]);
		$built = ($command === 'again')
			? ws_poll_ask_body($question, $choices, $flags)
			: array('body' => array(), 'error' => '');

		if ($poll < 1) {
			$built = array('body' => null, 'error' => 'the poll id must be a whole number of at least 1');
		}
	}

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_poll: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = ws_poll_settings($command, $options, getenv(), (bool) $pictures);

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_poll: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	if ($pictures) {
		$flags['pictures'] = array();

		foreach ($pictures as $picture) {
			$url = $settings['site'] . '/workspace/channels/' . $settings['channel'] . '/messages?since_id=' . ($picture - 1) . '&limit=1';
			$answer = ws_client_request('GET', $url, $settings, null);

			if ($answer['status'] < 200 || $answer['status'] >= 300) {
				fwrite(STDERR, 'ws_poll: picture of message ' . $picture . ': ' . ws_client_failure($answer) . "\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			$found = ws_poll_picture_file_id(json_decode($answer['body'], true), $picture);

			if ($found['error'] !== '') {
				fwrite(STDERR, 'ws_poll: ' . $found['error'] . "\n");
				return WS_CLIENT_EXIT_HTTP;
			}

			$flags['pictures'][$picture] = $found['file_id'];
		}

		$built = ws_poll_ask_body($question, $choices, $flags);
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

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes, reads and changes a Workspace note shared in a channel through a
 * site's external API with an application key, so a session keeps a plan or
 * a long explanation as one note changed in place rather than as new
 * messages.
 *
 * Usage:  php tools/ws_note.php new --file=PATH [--title=T] [--channel=ID]
 *                                   [--channel-edit] [--idempotency-key=K]
 *         php tools/ws_note.php read <note id> [--file=PATH]
 *         php tools/ws_note.php update [<note id>] --file=PATH [--title=T]
 *                                      [--revision=N] [--idempotency-key=K]
 *
 *   new     POST /workspace/channels/{id}/notes: the note and its card in the
 *           channel in one move. --channel-edit lets whoever may post in the
 *           channel change it. Prints "note=<id> revision=<n> message=<id>".
 *   read    GET /workspace/notes/{id}: the body on stdout, or into --file;
 *           "note=<id> revision=<n>" on stderr (stdout with --file).
 *   update  POST /workspace/notes/{id} with the whole body of --file and the
 *           revision it was read at. Prints "note=<id> revision=<n>".
 *
 * The base: new and read with --file keep the note id and its revision
 * beside the file, in PATH.ws_note (JSON). update reads them from there when
 * neither the id nor --revision is given, and keeps the new revision after
 * a write; so read --file, edit the file, update --file. With no revision
 * known, update refuses: it never writes over a note blind.
 *
 * Exit:   0 written (or read); 1 the site refused, or gave no answer
 *         (stderr names the Idempotency-Key to repeat with); 2 usage or
 *         missing settings. A 409 (the note changed since that revision)
 *         says so, writes nothing on the site or beside the file, and exits
 *         1: read the note again, merge, update again.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET, and PG_WS_CHANNEL for new when --channel is not
 * given. Nothing secret is printed.
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

// The site's limits (WS_NOTE_MAX and the title's max_length).
define('WS_NOTE_TOOL_MAX', 60000);
define('WS_NOTE_TOOL_TITLE_MAX', 200);

/**
 * The usage text.
 *
 * @return string
 */
function ws_note_usage()
{
	return "Usage: php tools/ws_note.php new --file=PATH [--title=T] [--channel=ID] [--channel-edit] [--idempotency-key=K]\n"
		. "       php tools/ws_note.php read <note id> [--file=PATH]\n"
		. "       php tools/ws_note.php update [<note id>] --file=PATH [--title=T] [--revision=N] [--idempotency-key=K]\n"
		. "A file of \"-\" is stdin. read --file and new keep the note id and revision in PATH.ws_note; update reads them from there.\n";
}

/**
 * The path that keeps a file's note id and revision.
 *
 * @param string $file
 * @return string
 */
function ws_note_base_path($file)
{
	return $file . '.ws_note';
}

/**
 * The note id and revision kept beside a file, from that file's JSON text.
 * Zeros when it is missing or unreadable.
 *
 * @param string|false $json
 * @return array{note_id: int, revision: int}
 */
function ws_note_base_parse($json)
{
	$data = is_string($json) ? json_decode($json, true) : null;

	return array(
		'note_id'  => is_array($data) && isset($data['note_id']) && is_int($data['note_id']) && $data['note_id'] > 0 ? $data['note_id'] : 0,
		'revision' => is_array($data) && isset($data['revision']) && is_int($data['revision']) && $data['revision'] > 0 ? $data['revision'] : 0,
	);
}

/**
 * The JSON text kept beside a file.
 *
 * @param int $note_id
 * @param int $revision
 * @return string
 */
function ws_note_base_json($note_id, $revision)
{
	return json_encode(array('note_id' => (int) $note_id, 'revision' => (int) $revision)) . "\n";
}

/**
 * Whether a string is a positive whole number.
 *
 * @param mixed $value
 * @return bool
 */
function ws_note_positive($value)
{
	return is_string($value) && ctype_digit($value) && (int) $value > 0;
}

/**
 * Reads the command line into a plan of what to do, or an error line. Pure:
 * the base kept beside the file comes in as $base (ws_note_base_parse()).
 *
 * @param string[] $argv without the script name
 * @param array{note_id: int, revision: int} $base
 * @return array{command: string, note_id: int, revision: int, file: string, title: string|null, channel_edit: bool, error: string}
 */
function ws_note_parse($argv, $base = array('note_id' => 0, 'revision' => 0))
{
	list($options, $rest) = ws_client_options($argv);
	$out = array('command' => '', 'note_id' => 0, 'revision' => 0, 'file' => '', 'title' => null, 'channel_edit' => false, 'error' => '');
	$fail = function ($error) use ($out) {
		$out['error'] = $error;
		return $out;
	};

	$command = $rest ? array_shift($rest) : '';

	if (!in_array($command, array('new', 'read', 'update'), true)) {
		return $fail($command === '' ? 'name a command: new, read or update' : 'unknown command ' . $command);
	}

	$out['command'] = $command;

	if (isset($options['file'])) {
		if (!is_string($options['file']) || $options['file'] === '') {
			return $fail('--file must name a file');
		}

		$out['file'] = $options['file'];
	}

	if (isset($options['title'])) {
		if (!is_string($options['title'])) {
			return $fail('--title needs a value');
		}

		if ($command === 'read') {
			return $fail('--title goes with new or update');
		}

		$title = trim($options['title']);

		if ((function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title)) > WS_NOTE_TOOL_TITLE_MAX) {
			return $fail('the title is longer than ' . WS_NOTE_TOOL_TITLE_MAX . ' characters');
		}

		$out['title'] = $title;
	}

	if (isset($options['channel-edit'])) {
		if ($command !== 'new') {
			return $fail('--channel-edit goes with new');
		}

		$out['channel_edit'] = true;
	}

	if ($command === 'new') {
		if ($rest) {
			return $fail('new takes no note id');
		}

		if ($out['file'] === '') {
			return $fail('new needs --file');
		}

		return $out;
	}

	if (count($rest) > 1) {
		return $fail('one note id at most');
	}

	if ($rest) {
		if (!ws_note_positive($rest[0])) {
			return $fail('the note id must be a number');
		}

		$out['note_id'] = (int) $rest[0];
	}

	if ($command === 'read') {
		if ($out['note_id'] < 1) {
			return $fail('read needs the note id');
		}

		if ($out['file'] === '-') {
			return $fail('read writes to stdout without --file');
		}

		return $out;
	}

	if ($out['file'] === '') {
		return $fail('update needs --file');
	}

	// The base kept beside the file counts only for the note it was kept for.
	$kept = ($out['note_id'] === 0 || $out['note_id'] === (int) $base['note_id']) ? $base : array('note_id' => 0, 'revision' => 0);

	if ($out['note_id'] === 0) {
		$out['note_id'] = (int) $kept['note_id'];
	}

	if (isset($options['revision'])) {
		if (!ws_note_positive($options['revision'])) {
			return $fail('--revision must be a number');
		}

		$out['revision'] = (int) $options['revision'];
	} else {
		$out['revision'] = (int) $kept['revision'];
	}

	if ($out['note_id'] < 1) {
		return $fail('update needs the note id, or a file read or written with this tool');
	}

	if ($out['revision'] < 1) {
		return $fail('update needs the revision the note was read at: --revision, or read --file first');
	}

	return $out;
}

/**
 * The request body of new or update, or an error line.
 *
 * @param array{command: string, revision: int, title: string|null, channel_edit: bool} $plan
 * @param string $text the file's text
 * @return array{body: array<string,mixed>|null, error: string}
 */
function ws_note_body($plan, $text)
{
	$text = (string) $text;

	if (trim($text) === '') {
		return array('body' => null, 'error' => 'the file is empty');
	}

	if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > WS_NOTE_TOOL_MAX) {
		return array('body' => null, 'error' => 'the text is longer than ' . WS_NOTE_TOOL_MAX . ' characters');
	}

	$body = array('body' => $text);

	if ($plan['title'] !== null && $plan['title'] !== '') {
		$body['title'] = $plan['title'];
	}

	if ($plan['command'] === 'new') {
		if ($plan['channel_edit']) {
			$body['channel_edit'] = true;
		}
	} else {
		$body['revision'] = (int) $plan['revision'];
	}

	return array('body' => $body, 'error' => '');
}

/**
 * Whether the site refused a change because the note changed meanwhile.
 *
 * @param array{status: int, body: string} $answer
 * @return bool
 */
function ws_note_conflict($answer)
{
	if ((int) $answer['status'] !== 409) {
		return false;
	}

	$json = json_decode((string) $answer['body'], true);

	return is_array($json) && isset($json['error']['code']) && $json['error']['code'] === 'changed_meanwhile';
}

/**
 * The line a 409 prints: the revision sent, the site's own words on who
 * changed it, and what to do.
 *
 * @param array{status: int, body: string} $answer
 * @param int $note_id
 * @param int $revision
 * @return string
 */
function ws_note_conflict_line($answer, $note_id, $revision)
{
	$json = json_decode((string) $answer['body'], true);
	$message = is_array($json) && isset($json['error']['message']) && is_string($json['error']['message']) ? trim((string) preg_replace('/\s+/', ' ', $json['error']['message'])) : '';

	return 'note ' . (int) $note_id . ' changed since revision ' . (int) $revision
		. ($message !== '' ? ' (' . (strlen($message) > 300 ? substr($message, 0, 300) . '...' : $message) . ')' : '')
		. '; nothing was written: read it again, merge, update again';
}

/**
 * The exit code of a write's answer: 0 written, 1 refused or no answer.
 *
 * @param array{status: int} $answer
 * @return int
 */
function ws_note_exit($answer)
{
	$status = (int) $answer['status'];

	return ($status >= 200 && $status < 300) ? WS_CLIENT_EXIT_OK : WS_CLIENT_EXIT_HTTP;
}

/**
 * The line a written or read note prints.
 *
 * @param array<string,mixed>|null $json the API's answer
 * @param string $command
 * @return array{note_id: int, revision: int, line: string}
 */
function ws_note_result($json, $command)
{
	$json = is_array($json) ? $json : array();
	$note_id = (int) ($command === 'new' ? ($json['note_id'] ?? 0) : ($json['id'] ?? 0));
	$revision = (int) ($json['revision'] ?? 0);
	$line = 'note=' . $note_id . ' revision=' . $revision;

	if ($command === 'new') {
		$line .= ' message=' . (int) ($json['message_id'] ?? 0);
	}

	return array('note_id' => $note_id, 'revision' => $revision, 'line' => $line);
}

/**
 * Sends a write once more after a call without an answer, with the same
 * Idempotency-Key, so it is written at most once.
 *
 * @param callable $send returns the answer of one call (ws_client_request())
 * @return array{status: int, headers: string, body: string, error: string}
 */
function ws_note_send($send)
{
	$answer = $send();

	if (in_array((int) $answer['status'], array(0, 502, 504), true)) {
		$answer = $send();
	}

	return $answer;
}

function ws_note_main($argv)
{
	list($options) = ws_client_options($argv);
	$file = isset($options['file']) && is_string($options['file']) ? $options['file'] : '';
	$base = array('note_id' => 0, 'revision' => 0);

	if ($file !== '' && $file !== '-' && is_file(ws_note_base_path($file))) {
		$base = ws_note_base_parse(@file_get_contents(ws_note_base_path($file)));
	}

	$plan = ws_note_parse($argv, $base);

	if ($plan['error'] !== '') {
		fwrite(STDERR, 'ws_note: ' . $plan['error'] . "\n" . ws_note_usage());
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = ws_client_settings($options, getenv(), $plan['command'] === 'new');

	if ($settings['missing']) {
		fwrite(STDERR, 'ws_note: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$keep = $plan['file'] !== '' && $plan['file'] !== '-';

	if ($plan['command'] === 'read') {
		$answer = ws_client_request('GET', $settings['site'] . '/workspace/notes/' . $plan['note_id'], $settings);

		if (ws_note_exit($answer) !== WS_CLIENT_EXIT_OK) {
			fwrite(STDERR, 'ws_note: ' . ws_client_failure($answer) . "\n");
			return WS_CLIENT_EXIT_HTTP;
		}

		$json = json_decode($answer['body'], true);
		$result = ws_note_result($json, 'read');
		$text = is_array($json) && isset($json['body']) && is_string($json['body']) ? $json['body'] : '';

		if (!$keep) {
			fwrite(STDOUT, $text);
			fwrite(STDERR, $result['line'] . "\n");
			return WS_CLIENT_EXIT_OK;
		}

		if (@file_put_contents($plan['file'], $text) === false || @file_put_contents(ws_note_base_path($plan['file']), ws_note_base_json($result['note_id'], $result['revision'])) === false) {
			fwrite(STDERR, 'ws_note: cannot write ' . $plan['file'] . "\n");
			return WS_CLIENT_EXIT_USAGE;
		}

		fwrite(STDOUT, $result['line'] . "\n");
		return WS_CLIENT_EXIT_OK;
	}

	if ($plan['file'] !== '-' && (!is_file($plan['file']) || !is_readable($plan['file']))) {
		fwrite(STDERR, 'ws_note: cannot read the file ' . $plan['file'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$text = (string) ($plan['file'] === '-' ? stream_get_contents(STDIN) : file_get_contents($plan['file']));
	$built = ws_note_body($plan, $text);

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_note: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$headers = ws_client_idempotency_headers($options);

	if (!$headers) {
		$headers = ws_client_idempotency_headers(array('idempotency-key' => 'ws-note-' . bin2hex(random_bytes(12))));
	}

	$key = substr($headers[0], strlen('Idempotency-Key: '));
	$url = $settings['site'] . ($plan['command'] === 'new'
		? '/workspace/channels/' . $settings['channel'] . '/notes'
		: '/workspace/notes/' . $plan['note_id']);
	$body = $built['body'];

	$answer = ws_note_send(function () use ($url, $settings, $body, $headers) {
		return ws_client_request('POST', $url, $settings, $body, $headers);
	});

	if (ws_note_conflict($answer)) {
		fwrite(STDERR, 'ws_note: ' . ws_note_conflict_line($answer, $plan['note_id'], $plan['revision']) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	if (in_array((int) $answer['status'], array(0, 502, 504), true)) {
		fwrite(STDERR, 'ws_note: no answer (' . ws_client_failure($answer) . '), the note may be written; repeat with --idempotency-key=' . $key . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	if (ws_note_exit($answer) !== WS_CLIENT_EXIT_OK) {
		fwrite(STDERR, 'ws_note: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$result = ws_note_result(json_decode($answer['body'], true), $plan['command']);

	if ($keep && @file_put_contents(ws_note_base_path($plan['file']), ws_note_base_json($result['note_id'], $result['revision'])) === false) {
		fwrite(STDERR, 'ws_note: written, but cannot keep the revision beside ' . $plan['file'] . "\n");
	}

	fwrite(STDOUT, $result['line'] . "\n");

	return WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_note_main(array_slice($argv, 1)));
}

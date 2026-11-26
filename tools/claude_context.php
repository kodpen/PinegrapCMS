<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reads the context a Claude Code session uses from its own transcript:
 * the newest .jsonl of the working directory's project folder under
 * ~/.claude/projects/, its last assistant message's usage, the input,
 * cache creation and cache read tokens together. It reads only the tokens
 * used; the window's size is never guessed.
 *
 * Usage:  php tools/claude_context.php [--cwd=DIR]
 *         Run from the folder the session started in (or name it with
 *         --cwd). CLAUDE_CONFIG_DIR, when set, replaces ~/.claude.
 * Output: the used tokens as one whole number; nothing when no transcript
 *         or no usage is found.
 * Exit:   0 found; 1 nothing found.
 *
 * tools/ws_profile.php --context=auto sends what this reads.
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

/**
 * The folder Claude Code keeps a working directory's transcripts in: every
 * character but a letter or a digit becomes a dash.
 *
 * @param string $cwd
 * @return string
 */
function claude_context_folder_name($cwd)
{
	$cwd = (string) $cwd;

	if (strlen($cwd) > 1) {
		$cwd = rtrim($cwd, '/\\');
	}

	return (string) preg_replace('/[^A-Za-z0-9]/', '-', $cwd);
}

/**
 * The used tokens of a transcript's lines: the last assistant message of
 * the session itself (not a sub-agent's, not a synthetic one) whose usage
 * counts tokens, its input, cache creation and cache read tokens together.
 *
 * @param string[] $lines
 * @return int|null null when no line carries a usage
 */
function claude_context_used($lines)
{
	$used = null;

	foreach ($lines as $line) {
		$line = trim((string) $line);

		// A cheap test first: most lines carry no usage at all.
		if ($line === '' || strpos($line, '"usage"') === false) {
			continue;
		}

		$entry = json_decode($line, true);

		if (!is_array($entry) || !isset($entry['type']) || $entry['type'] !== 'assistant' || !empty($entry['isSidechain'])) {
			continue;
		}

		$message = isset($entry['message']) && is_array($entry['message']) ? $entry['message'] : array();

		if (isset($message['model']) && $message['model'] === '<synthetic>') {
			continue;
		}

		if (!isset($message['usage']) || !is_array($message['usage'])) {
			continue;
		}

		$sum = 0;

		foreach (array('input_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens') as $key) {
			if (isset($message['usage'][$key]) && is_int($message['usage'][$key]) && $message['usage'][$key] > 0) {
				$sum += $message['usage'][$key];
			}
		}

		if ($sum > 0) {
			$used = $sum;
		}
	}

	return $used;
}

/**
 * The newest of the given files.
 *
 * @param array<string,int> $files path => modification time
 * @return string|null
 */
function claude_context_newest($files)
{
	$newest = null;
	$newest_time = null;

	foreach ($files as $path => $time) {
		if ($newest === null || $time > $newest_time) {
			$newest = (string) $path;
			$newest_time = $time;
		}
	}

	return $newest;
}

/**
 * The used tokens in a transcript file, read from its end: a window of the
 * file's tail grows until a usage is found or the whole file is read.
 *
 * @param string $path
 * @return int|null
 */
function claude_context_file_used($path)
{
	$size = @filesize($path);
	$handle = @fopen($path, 'rb');

	if ($size === false || $handle === false) {
		return null;
	}

	$window = 1048576;
	$used = null;

	while (true) {
		$offset = max(0, $size - $window);
		fseek($handle, $offset);
		$chunk = (string) stream_get_contents($handle);
		$lines = explode("\n", $chunk);

		// The first line of a window that starts mid-file is cut.
		if ($offset > 0) {
			array_shift($lines);
		}

		$used = claude_context_used($lines);

		if ($used !== null || $offset === 0) {
			break;
		}

		$window *= 4;
	}

	fclose($handle);

	return $used;
}

/**
 * The used tokens of the newest transcript of a working directory.
 *
 * @param string $config_dir Claude Code's folder (~/.claude)
 * @param string $cwd        the folder the session started in
 * @return int|null
 */
function claude_context_read($config_dir, $cwd)
{
	$folder = rtrim((string) $config_dir, '/') . '/projects/' . claude_context_folder_name($cwd);
	$files = array();

	foreach (glob($folder . '/*.jsonl') ?: array() as $path) {
		$time = @filemtime($path);

		if ($time !== false && is_file($path)) {
			$files[$path] = $time;
		}
	}

	$newest = claude_context_newest($files);

	return $newest === null ? null : claude_context_file_used($newest);
}

/**
 * The used tokens of the session running in this environment.
 *
 * @param string|null $cwd the session's folder; the current one when null
 * @return int|null
 */
function claude_context_current($cwd = null)
{
	$config_dir = getenv('CLAUDE_CONFIG_DIR');

	if (!is_string($config_dir) || $config_dir === '') {
		$home = getenv('HOME');

		if (!is_string($home) || $home === '') {
			return null;
		}

		$config_dir = $home . '/.claude';
	}

	if ($cwd === null || $cwd === '') {
		$cwd = getcwd();
	}

	return is_string($cwd) && $cwd !== '' ? claude_context_read($config_dir, $cwd) : null;
}

function claude_context_main($argv)
{
	$cwd = null;

	foreach ($argv as $arg) {
		if (strncmp((string) $arg, '--cwd=', 6) === 0) {
			$cwd = substr((string) $arg, 6);
		}
	}

	$used = claude_context_current($cwd);

	if ($used === null) {
		return 1;
	}

	fwrite(STDOUT, $used . "\n");

	return 0;
}

// Run only when called itself, not when tools/ws_profile.php loads it.
if (!defined('PG_TEST_RUNNER') && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	exit(claude_context_main(array_slice($argv, 1)));
}

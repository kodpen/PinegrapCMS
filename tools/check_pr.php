<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guards on what a branch carries, run by CI on every pull request to
 * development (.github/workflows/php-checks.yml) and by tools/pre_push.sh
 * before a push:
 * - no commit on the branch carries a Co-Authored-By: or Claude-Session:
 *   trailer;
 * - every commit's author is Erdal Güral <erdaltyy@gmail.com>, and so is its
 *   committer, or GitHub <noreply@github.com>: GitHub writes that committer
 *   on the commits it makes for the signed-in account (a merge from the web
 *   UI or "Update branch", the web editor, the contents API), whose author is
 *   then that account's identity and is judged as any other;
 * - the branch does not change pinegrap/changelog.txt, which only the merging
 *   session writes;
 * - the branch does not change pinegrap/data/config.php in any way: its
 *   tracked content is <?php ?>, and a site's or sandbox's connection details
 *   and encryption key must never reach the repository;
 * - the branch does not change the memory lists tools/memory_index.php
 *   generates (docs/memory/README.md, each folder's README.md and INDEX.md,
 *   workflow/DECISIONS.md, core/fn-modules.md): the landing writes them
 *   (tools/merge_train.php), so two branches never conflict on them. A
 *   folder README's hand-written key rules, between its two marker lines,
 *   may change;
 * - the branch does not change a bundle tools/build_js.php or
 *   tools/build_css.php writes (the .min.js and .min.css files of
 *   pg_js_bundles() and pg_css_bundles()): the landing builds them, so two
 *   branches never conflict on them;
 * - more than PG_CHECK_PR_SIZE_LIMIT product lines (changed lines under
 *   pinegrap/, minified bundles and language files not counted) prints a
 *   warning, never a failure.
 * A merge train (merge-train/*), development and main skip the author,
 * changelog, memory list, bundle and size checks: they carry the commits of other branches,
 * Dependabot's among them; --identity-exempt does the same for a pull
 * request Dependabot opened. The trailer and config.php checks apply to every
 * branch.
 *
 * Usage:  php tools/check_pr.php <base> <head> [--branch=<name>]
 *             [--identity-exempt]
 *         The branch's commits are those in <head> that <base> does not
 *         reach; its changes are those since their merge base.
 * Exit:   0 when no check failed (a size warning included); 1 otherwise.
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

define('PG_CHECK_PR_IDENTITY', 'Erdal Güral <erdaltyy@gmail.com>');
define('PG_CHECK_PR_GITHUB', 'GitHub <noreply@github.com>');
define('PG_CHECK_PR_SIZE_LIMIT', 1000);

// The records `git log --format=%H%x1f%an%x1f%ae%x1f%cn%x1f%ce%x1f%B%x1e`
// printed, as a list of array('id', 'author', 'committer', 'message'), each
// identity as "Name <email>".
function check_pr_parse_log($output)
{
	$commits = array();

	foreach (explode("\x1e", (string) $output) as $record) {
		$record = ltrim($record, "\r\n");

		if ($record === '') {
			continue;
		}

		$fields = explode("\x1f", $record, 6);

		if (count($fields) < 6) {
			continue;
		}

		$commits[] = array(
			'id' => $fields[0],
			'author' => $fields[1] . ' <' . $fields[2] . '>',
			'committer' => $fields[3] . ' <' . $fields[4] . '>',
			'message' => $fields[5],
		);
	}

	return $commits;
}

// Whether a branch carries the commits of other branches and so skips the
// author, changelog and size checks.
function check_pr_branch_exempt($branch)
{
	$branch = (string) $branch;

	return ($branch === 'development') || ($branch === 'main') || (strpos($branch, 'merge-train/') === 0);
}

// What is wrong with one commit, as a list of messages; the author and
// committer only when $check_identity is true.
function check_pr_commit_problems(array $commit, $check_identity)
{
	$problems = array();

	if (preg_match_all('/^[ \t]*(Co-Authored-By|Claude-Session)[ \t]*:/im', $commit['message'], $matches)) {
		foreach (array_unique(array_map('strtolower', $matches[1])) as $trailer) {
			$problems[] = 'carries a ' . (($trailer === 'claude-session') ? 'Claude-Session:' : 'Co-Authored-By:') . ' trailer';
		}
	}

	if ($check_identity) {
		if ($commit['author'] !== PG_CHECK_PR_IDENTITY) {
			$problems[] = 'author is ' . $commit['author'] . ', not ' . PG_CHECK_PR_IDENTITY;
		}

		if (($commit['committer'] !== PG_CHECK_PR_IDENTITY) && ($commit['committer'] !== PG_CHECK_PR_GITHUB)) {
			$problems[] = 'committer is ' . $commit['committer'] . ', not ' . PG_CHECK_PR_IDENTITY;
		}
	}

	return $problems;
}

// What `git diff --numstat -z --no-renames` printed, as path => changed
// lines (added plus deleted; 0 for a binary file).
function check_pr_parse_numstat($output)
{
	$files = array();

	foreach (explode("\0", (string) $output) as $record) {
		$record = ltrim($record, "\r\n");
		$fields = explode("\t", $record, 3);

		if (count($fields) < 3) {
			continue;
		}

		$files[$fields[2]] = (ctype_digit($fields[0]) ? (int) $fields[0] : 0) + (ctype_digit($fields[1]) ? (int) $fields[1] : 0);
	}

	return $files;
}

// What is wrong with the files a change touches (path => changed lines, from
// check_pr_parse_numstat()), as a list of messages. pinegrap/data/config.php
// fails on every branch: the diff is taken with --no-renames, so a rename to
// or from it, a deletion and a mode change all list its path.
// pinegrap/changelog.txt fails only when $exempt is false.
function check_pr_file_problems(array $files, $exempt)
{
	$problems = array();

	if (!$exempt && isset($files['pinegrap/changelog.txt'])) {
		$problems[] = 'the branch changes pinegrap/changelog.txt; name the [NEW] line or the [SCHEME] item in the PR body instead (CLAUDE.md, Working mode 7)';
	}

	if (isset($files['pinegrap/data/config.php'])) {
		$problems[] = 'the branch changes pinegrap/data/config.php, whose tracked content stays <?php ?>; restore it with git checkout origin/development -- pinegrap/data/config.php';
	}

	return $problems;
}

// Whether a path is a memory list tools/memory_index.php generates.
function check_pr_memory_list($path)
{
	$path = (string) $path;

	if (in_array($path, array('docs/memory/README.md', 'docs/memory/workflow/DECISIONS.md', 'docs/memory/core/fn-modules.md'), true)) {
		return true;
	}

	return (bool) preg_match('#^docs/memory/[^/]+/(README|INDEX)\.md$#', $path);
}

// A folder README with its key rules block emptied, so that two copies
// compare equal when only the hand-written rules differ; null for null (the
// file is absent on that side).
function check_pr_without_key_rules($content)
{
	if ($content === null) {
		return null;
	}

	return preg_replace('/(<!-- key-rules:start -->).*?(<!-- key-rules:end -->)/s', '$1$2', str_replace("\r", '', (string) $content));
}

// What is wrong with a branch's change to a generated memory list, given its
// content where the branch left development and at the branch's head (null
// when absent), or '' when nothing is.
function check_pr_memory_list_problem($path, $before, $after, $merge_base)
{
	if (check_pr_without_key_rules($before) === check_pr_without_key_rules($after)) {
		return '';
	}

	return "the branch changes $path, which the landing generates (tools/memory_index.php, run by tools/merge_train.php); "
		. 'restore it with git checkout ' . substr((string) $merge_base, 0, 12) . ' -- ' . $path
		. (preg_match('#/README\.md$#', (string) $path) ? ' and write a key rules change again by hand' : '')
		. '; php tools/memory_index.php --files checks the memory files (docs/memory/workflow/merge-train-tool.md)';
}

// What is wrong with a branch's changes to the generated bundles: one message
// per path of $files that is one of $bundles (paths relative to the
// repository root), naming the commit to restore it from.
function check_pr_bundle_problems(array $files, array $bundles, $merge_base)
{
	$problems = array();

	foreach (array_keys($files) as $path) {
		if (in_array((string) $path, $bundles, true)) {
			$problems[] = "the branch changes $path, which the landing builds (tools/build_js.php, tools/build_css.php, run by tools/merge_train.php); "
				. 'restore it with git checkout ' . substr((string) $merge_base, 0, 12) . ' -- ' . $path
				. '; build it to try the change, but do not commit it (docs/memory/core/js-bundles.md)';
		}
	}

	return $problems;
}

// The bundles of the checked-out tree, relative to the repository root, from
// the lists the build tools read. functions.php loads the modules under
// includes/fn/ without a connection, as tools/build_js.php does.
function check_pr_bundles()
{
	$software = dirname(__DIR__) . '/pinegrap';

	if (!is_file($software . '/functions.php')) {
		return array();
	}

	require_once($software . '/functions.php');
	$bundles = array();

	foreach (array('pg_js_bundles', 'pg_css_bundles') as $list) {
		if (function_exists($list)) {
			foreach (array_keys($list()) as $bundle) {
				$bundles[] = 'pinegrap/' . $bundle;
			}
		}
	}

	return $bundles;
}

// The product lines of a change: changed lines in files under pinegrap/,
// minified bundles and the language files under includes/local/ not counted.
function check_pr_product_lines(array $files)
{
	$lines = 0;

	foreach ($files as $path => $changed) {
		$path = (string) $path;

		if ((strpos($path, 'pinegrap/') !== 0) || (strpos($path, 'pinegrap/includes/local/') === 0) || (substr($path, -7) === '.min.js')) {
			continue;
		}

		$lines += $changed;
	}

	return $lines;
}

// One argument quoted for a Windows command line that starts a program
// without cmd.exe, as the program's C runtime splits the line back into
// arguments: in double quotes, with a double quote escaped by a backslash and
// the backslashes before it, or before the closing quote, doubled. % and !
// stay as they are: escapeshellarg() turns them and " into spaces for cmd.exe.
function check_pr_windows_arg($arg)
{
	return '"' . preg_replace_callback('/(\\\\*)("|\z)/', function ($match) {
		return $match[1] . $match[1] . (($match[2] === '"') ? '\\"' : '');
	}, (string) $arg) . '"';
}

// Runs git in the current directory and returns its stdout, or false when it
// fails (its stderr then goes to ours). On Windows git starts without cmd.exe
// (bypass_shell), which would expand the % signs of a --format, so its
// arguments are quoted for git itself; the argument list form of proc_open()
// that would do this needs PHP 7.4.
function check_pr_git(array $args)
{
	$quote = (DIRECTORY_SEPARATOR === '\\') ? 'check_pr_windows_arg' : 'escapeshellarg';
	$command = 'git ' . implode(' ', array_map($quote, $args));
	$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, null, array('bypass_shell' => true));

	if (!is_resource($process)) {
		return false;
	}

	$out = (string) stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$err = (string) stream_get_contents($pipes[2]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0) {
		fwrite(STDERR, $err);

		return false;
	}

	return $out;
}

// A file's content at a commit, or null when the commit has no such file.
function check_pr_git_file($commit, $path)
{
	$listed = check_pr_git(array('ls-tree', '--name-only', $commit, '--', $path));

	if (($listed === false) || (trim($listed) === '')) {
		return null;
	}

	$content = check_pr_git(array('show', $commit . ':' . $path));

	return ($content === false) ? null : $content;
}

// The test runner loads this file for the functions above.
if (defined('PG_TEST_RUNNER')) {
	return;
}

$positional = array();
$branch = '';
$identity_exempt = false;

foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--branch=') === 0) {
		$branch = substr($argument, 9);
	} elseif ($argument === '--identity-exempt') {
		$identity_exempt = true;
	} elseif (($argument !== '') && ($argument[0] !== '-')) {
		$positional[] = $argument;
	} else {
		fwrite(STDERR, "Unknown option: $argument\n");
		exit(1);
	}
}

if (count($positional) !== 2) {
	fwrite(STDERR, "Usage: php tools/check_pr.php <base> <head> [--branch=<name>] [--identity-exempt]\n");
	exit(1);
}

list($base, $head) = $positional;

$annotate = (getenv('GITHUB_ACTIONS') === 'true');

// One failure line; on GitHub Actions an error annotation.
$fail = function ($message) use ($annotate) {
	fwrite(STDOUT, ($annotate ? '::error title=PR guard::' : 'FAILED: ') . $message . "\n");
};

$merge_base = check_pr_git(array('merge-base', $base, $head));

if ($merge_base === false) {
	fwrite(STDERR, "No merge base between $base and $head\n");
	exit(1);
}

$merge_base = trim($merge_base);
$exempt = check_pr_branch_exempt($branch);
$check_identity = !$exempt && !$identity_exempt;
$failed = false;

$log = check_pr_git(array('log', '--format=%H%x1f%an%x1f%ae%x1f%cn%x1f%ce%x1f%B%x1e', $base . '..' . $head));

if ($log === false) {
	fwrite(STDERR, "git log $base..$head failed\n");
	exit(1);
}

$commits = check_pr_parse_log($log);

foreach ($commits as $commit) {
	foreach (check_pr_commit_problems($commit, $check_identity) as $problem) {
		$fail('commit ' . substr($commit['id'], 0, 10) . ' ' . $problem . ' (CLAUDE.md, Fixed rules 2)');
		$failed = true;
	}
}

$numstat = check_pr_git(array('-c', 'core.quotePath=false', 'diff', '--numstat', '-z', '--no-renames', $merge_base, $head, '--'));

if ($numstat === false) {
	fwrite(STDERR, "git diff $merge_base $head failed\n");
	exit(1);
}

$files = check_pr_parse_numstat($numstat);

foreach (check_pr_file_problems($files, $exempt || $identity_exempt) as $problem) {
	$fail($problem);
	$failed = true;
}

if (!$exempt && !$identity_exempt) {
	foreach (array_keys($files) as $path) {
		if (!check_pr_memory_list($path)) {
			continue;
		}

		$problem = check_pr_memory_list_problem($path, check_pr_git_file($merge_base, $path), check_pr_git_file($head, $path), $merge_base);

		if ($problem !== '') {
			$fail($problem);
			$failed = true;
		}
	}

	$touches_min = false;

	foreach (array_keys($files) as $path) {
		$touches_min = $touches_min || (bool) preg_match('/\.min\.(js|css)$/', (string) $path);
	}

	foreach ($touches_min ? check_pr_bundle_problems($files, check_pr_bundles(), $merge_base) : array() as $problem) {
		$fail($problem);
		$failed = true;
	}

	$lines = check_pr_product_lines($files);

	if ($lines > PG_CHECK_PR_SIZE_LIMIT) {
		$message = $lines . ' product lines changed, above about ' . PG_CHECK_PR_SIZE_LIMIT . ' (CLAUDE.md, Working mode 5); a bigger change is a plan cut into steps';
		fwrite(STDOUT, ($annotate ? '::warning title=PR size::' : 'WARNING: ') . $message . "\n");
	}
}

if ($failed) {
	exit(1);
}

fwrite(STDOUT, count($commits) . ' commit(s) checked' . ($check_identity ? '' : ' (trailers only)') . ".\n");
exit(0);

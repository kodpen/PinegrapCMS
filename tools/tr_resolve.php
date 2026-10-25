<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Settles merge conflicts in language files from the versions git keeps, so
 * nobody resolves a language file by hand.
 *
 * During a merge it takes every conflicted file under
 * pinegrap/includes/local/:
 * - a shard in <lang>/, or the single <lang>.json of a language with no
 *   folder of shards: starts from the development side and adds the other
 *   side's own edits: the keys it added, the values it changed (its value
 *   wins when both sides changed one key) and the keys it removed. A shard is
 *   written in key order; the single file keeps development's lines and gets
 *   the branch's new keys at its end.
 * - the old single <lang>.json of a language that is kept as shards (one
 *   side still edits the file, the other removed it for the shards): that
 *   side's edits since the merge base go onto the shards and the single file
 *   is removed, never written again.
 * Every file it writes or removes is staged. A side that deleted a shard, or
 * a version that is not a JSON object, leaves the file to be settled by hand.
 *
 * Git runs through proc_open() without cmd.exe and without shell
 * redirection, so the tool works the same on Windows and Linux; a git command
 * that fails stops the file it was for, and is never read as a missing
 * version.
 *
 * Usage:  php tools/tr_resolve.php [--development=theirs|ours]
 *             theirs (default): merging development into a branch;
 *             ours: merging a branch into development or a merge train.
 *         php tools/tr_resolve.php --legacy=<ref> [--lang=tr]
 *             outside a conflict: puts what <ref> changed in
 *             includes/local/<lang>.json since its merge base with HEAD onto
 *             the shards, stages them and removes the single file.
 *
 * Exit:   0 when every conflicted language file was settled; 1 otherwise.
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

require_once(dirname(__DIR__) . '/pinegrap/functions.php');

// A language code as it may name a file under includes/local/.
define('TR_RESOLVE_CODE', '[a-z]{2,3}(?:-[A-Za-z]{2,4})?');

// Runs git with $args in $repository and returns array('status', 'out',
// 'err'): the exit code (-1 when git could not be started), stdout and
// stderr. From PHP 7.4 the array form starts git directly; before that the
// quoted line goes to git directly on Windows (bypass_shell) and through
// /bin/sh elsewhere, with no redirection in it either way. stderr goes to a
// temporary file rather than a second pipe, so a long stdout (a whole
// language file) cannot block on it.
function tr_resolve_git($repository, array $args)
{
	$command = array_merge(array('git', '-C', (string) $repository), array_map('strval', $args));

	if (PHP_VERSION_ID < 70400) {
		$command = implode(' ', array_map('escapeshellarg', $command));
	}

	$error_file = tempnam(sys_get_temp_dir(), 'trr');
	$error_target = ($error_file !== false) ? $error_file : ((DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null');

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('file', $error_target, 'w'),
	);

	$process = @proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true, 'blocking_pipes' => true));

	if (!is_resource($process)) {
		if ($error_file !== false) {
			@unlink($error_file);
		}

		return array('status' => -1, 'out' => '', 'err' => 'git could not be started');
	}

	fclose($pipes[0]);
	$out = (string) stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$status = proc_close($process);

	$err = '';

	if ($error_file !== false) {
		$err = trim((string) @file_get_contents($error_file));
		@unlink($error_file);
	}

	return array('status' => (int) $status, 'out' => $out, 'err' => $err);
}

// The unmerged entries `git ls-files -u -z` printed: path => array(stage =>
// object id), stage 1 the merge base, 2 ours, 3 theirs. A missing stage is a
// side that deleted the file (or, for 1, a file both sides added).
function tr_resolve_unmerged($output)
{
	$entries = array();

	foreach (explode("\0", (string) $output) as $record) {
		if (preg_match('/^[0-7]{6} ([0-9a-f]{40,64}) ([123])\t(.+)$/s', $record, $match)) {
			$entries[$match[3]][(int) $match[2]] = $match[1];
		}
	}

	return $entries;
}

// The language file $path names: array('code' => language code, 'shard' =>
// shard name, or null for the single <code>.json); null for any other path.
function tr_resolve_language_path($path)
{
	if (preg_match('#^pinegrap/includes/local/(' . TR_RESOLVE_CODE . ')/([^/]+)\.json$#', (string) $path, $match)) {
		return array('code' => $match[1], 'shard' => $match[2]);
	}

	if (preg_match('#^pinegrap/includes/local/(' . TR_RESOLVE_CODE . ')\.json$#', (string) $path, $match)) {
		return array('code' => $match[1], 'shard' => null);
	}

	return null;
}

// What to do with one conflicted language file. $stages: the stages git holds
// for it (keys 1, 2, 3); $sharded: the file is a shard; $folder: its language
// has a folder of shards in the working tree. Returns array('action' =>
// 'merge'|'move'|'skip', 'side' => 2|3 the side that still has the single
// file (move), 'reason' => why (skip)).
function tr_resolve_plan(array $stages, $sharded, $folder)
{
	$ours = isset($stages[2]);
	$theirs = isset($stages[3]);

	if (!$sharded && $folder) {
		if (!isset($stages[1])) {
			return array('action' => 'skip', 'reason' => 'the single file has no merge base version to tell its edits from');
		}

		if ($ours === $theirs) {
			return array('action' => 'skip', 'reason' => 'both sides still have the single file beside the shards');
		}

		return array('action' => 'move', 'side' => $ours ? 2 : 3);
	}

	if (!$ours || !$theirs) {
		return array('action' => 'skip', 'reason' => ($ours ? 'theirs' : 'ours') . ' deleted the file');
	}

	return array('action' => 'merge');
}

// A language map from a version's text; null when it is not a JSON object.
function tr_resolve_map($text)
{
	$text = (string) $text;
	$map = json_decode($text, true);

	if (!is_array($map) || (ltrim($text) === '') || (ltrim($text)[0] !== '{')) {
		return null;
	}

	return $map;
}

// A single file's edits put onto the shards: $base and $side are the file at
// the merge base and on the side that edited it, $texts the shards as the
// working tree has them (name => text). The shards' own edits stay; the
// side's added keys, changed values and removed keys go in (pg_lang_merge3()).
// Returns array('write' => name => text of each shard that is new or changes,
// 'remove' => names of shards left with no key, 'added', 'changed',
// 'removed' => how many keys), or array('error' => text) when a shard is not
// a JSON object (a conflict still in it) or no key would be left.
function tr_resolve_onto_shards(array $base, array $side, array $texts)
{
	$current = array();

	foreach ($texts as $name => $text) {
		$map = tr_resolve_map($text);

		if ($map === null) {
			return array('error' => $name . '.json is not a JSON object (still in conflict?)');
		}

		foreach ($map as $key => $value) {
			$current[(string) $key] = $value;
		}
	}

	$merged = pg_lang_merge3($base, $side, $current);

	if (count($merged) === 0) {
		return array('error' => 'no key would be left');
	}

	$shards = pg_lang_split($merged);
	$write = array();
	$remove = array();

	foreach ($shards as $name => $map) {
		$text = pg_lang_file_encode($map);

		if (!isset($texts[(string) $name]) || ((string) $texts[(string) $name] !== $text)) {
			$write[(string) $name] = $text;
		}
	}

	foreach ($texts as $name => $text) {
		if (!isset($shards[(string) $name])) {
			$remove[] = (string) $name;
		}
	}

	$changed = 0;

	foreach ($merged as $key => $value) {
		if (array_key_exists($key, $current) && ((string) $current[$key] !== (string) $value)) {
			$changed++;
		}
	}

	return array(
		'write' => $write,
		'remove' => $remove,
		'added' => count(array_diff_key($merged, $current)),
		'changed' => $changed,
		'removed' => count(array_diff_key($current, $merged)),
	);
}

// The shards of a folder as the working tree has them, name => text; null
// when it holds none.
function tr_resolve_read_shards($folder)
{
	$files = glob(rtrim((string) $folder, '/\\') . '/*.json');

	if (!is_array($files) || (count($files) === 0)) {
		return null;
	}

	sort($files, SORT_STRING);
	$texts = array();

	foreach ($files as $file) {
		$texts[basename($file, '.json')] = (string) file_get_contents($file);
	}

	return $texts;
}

// The text of an object git holds, or array('error' => text) when it cannot
// be read.
function tr_resolve_object($repository, $id)
{
	$result = tr_resolve_git($repository, array('cat-file', 'blob', $id));

	if ($result['status'] !== 0) {
		return array('error' => 'git cat-file blob ' . $id . ' failed: ' . $result['err']);
	}

	return $result['out'];
}

// The text of $path at revision $revision; null when the revision has no such
// file, array('error' => text) when git fails (an unknown revision).
function tr_resolve_file_at($repository, $revision, $path)
{
	$result = tr_resolve_git($repository, array('ls-tree', '-z', $revision, '--', $path));

	if ($result['status'] !== 0) {
		return array('error' => 'git ls-tree ' . $revision . ' failed: ' . $result['err']);
	}

	if (!preg_match('/^[0-7]{6} blob ([0-9a-f]{40,64})\t/', $result['out'], $match)) {
		return null;
	}

	return tr_resolve_object($repository, $match[1]);
}

// Puts a single file's edits onto its language's shards (tr_resolve_onto_shards()),
// writes and stages them and removes the single file from the working tree
// and the index. Returns a line for the report, or array('error' => text).
function tr_resolve_move($repository, $code, array $base, array $side)
{
	$folder = $repository . '/pinegrap/includes/local/' . $code;
	$relative_folder = 'pinegrap/includes/local/' . $code;
	$single = 'pinegrap/includes/local/' . $code . '.json';

	$texts = tr_resolve_read_shards($folder);

	if ($texts === null) {
		return array('error' => 'no shards in ' . $relative_folder . '/');
	}

	$plan = tr_resolve_onto_shards($base, $side, $texts);

	if (isset($plan['error'])) {
		return $plan;
	}

	$written = array();
	$removed = array($single);

	foreach ($plan['write'] as $name => $text) {
		if (!pg_lang_write_file($folder . '/' . $name . '.json', $text)) {
			return array('error' => 'could not write ' . $relative_folder . '/' . $name . '.json');
		}

		$written[] = $relative_folder . '/' . $name . '.json';
	}

	foreach ($plan['remove'] as $name) {
		if (!@unlink($folder . '/' . $name . '.json')) {
			return array('error' => 'could not remove ' . $relative_folder . '/' . $name . '.json');
		}

		$removed[] = $relative_folder . '/' . $name . '.json';
	}

	// The single file as the working tree or the index (any stage) holds it.
	$listed = tr_resolve_git($repository, array('ls-files', '-s', '--', $single));
	$had_single = is_file($repository . '/' . $single) || (trim($listed['out']) !== '');

	if (is_file($repository . '/' . $single) && !@unlink($repository . '/' . $single)) {
		return array('error' => 'could not remove ' . $single);
	}

	// git rm --cached also drops the unmerged entries of a conflicted file;
	// --ignore-unmatch passes over one the index does not hold.
	if (count($written) > 0) {
		$staged = tr_resolve_git($repository, array_merge(array('add', '--'), $written));

		if ($staged['status'] !== 0) {
			return array('error' => 'git add failed: ' . $staged['err']);
		}
	}

	$unstaged = tr_resolve_git($repository, array_merge(array('rm', '-q', '--cached', '--ignore-unmatch', '--'), $removed));

	if ($unstaged['status'] !== 0) {
		return array('error' => 'git rm --cached failed: ' . $unstaged['err']);
	}

	return $plan['added'] . ' key(s) added, ' . $plan['changed'] . ' changed, ' . $plan['removed'] . ' removed in '
		. (count($written) + count($plan['remove'])) . ' shard(s)' . ($had_single ? '; ' . $single . ' removed' : '');
}

// The test runner loads this file for the functions above.
if (defined('PG_TEST_RUNNER')) {
	return;
}

$repository = dirname(__DIR__);
$development = 'theirs';
$legacy = '';
$lang = 'tr';

foreach (array_slice($argv, 1) as $argument) {
	if (($argument === '--development=theirs') || ($argument === '--development=ours')) {
		$development = substr($argument, 14);
	} elseif (strpos($argument, '--legacy=') === 0) {
		$legacy = substr($argument, 9);
	} elseif (strpos($argument, '--lang=') === 0) {
		$lang = substr($argument, 7);
	} else {
		fwrite(STDERR, "Unknown option: $argument\n");
		exit(1);
	}
}

if (!preg_match('/^' . TR_RESOLVE_CODE . '$/', $lang)) {
	fwrite(STDERR, "Not a language code: $lang\n");
	exit(1);
}

if ($legacy !== '') {

	// git would read a ref starting with a dash as an option.
	if ($legacy[0] === '-') {
		fwrite(STDERR, "Not a ref: $legacy\n");
		exit(1);
	}

	$path = 'pinegrap/includes/local/' . $lang . '.json';

	$merge_base = tr_resolve_git($repository, array('merge-base', 'HEAD', $legacy));

	if ($merge_base['status'] === 1) {
		fwrite(STDERR, "No merge base between HEAD and $legacy\n");
		exit(1);
	}

	if ($merge_base['status'] !== 0) {
		fwrite(STDERR, "git merge-base HEAD $legacy failed: " . $merge_base['err'] . "\n");
		exit(1);
	}

	$merge_base = trim($merge_base['out']);

	$base_text = tr_resolve_file_at($repository, $merge_base, $path);
	$side_text = tr_resolve_file_at($repository, $legacy, $path);

	foreach (array($base_text, $side_text) as $text) {
		if (is_array($text)) {
			fwrite(STDERR, $text['error'] . "\n");
			exit(1);
		}
	}

	if ($side_text === null) {
		echo "$legacy has no $path: nothing to put onto the shards.\n";
		exit(0);
	}

	if ($base_text === null) {
		fwrite(STDERR, "The merge base of HEAD and $legacy has no $path to tell its edits from.\n");
		exit(1);
	}

	$base = tr_resolve_map($base_text);
	$side = tr_resolve_map($side_text);

	if (($base === null) || ($side === null)) {
		fwrite(STDERR, "$path is not a JSON object at the merge base or at $legacy\n");
		exit(1);
	}

	$moved = tr_resolve_move($repository, $lang, $base, $side);

	if (is_array($moved)) {
		fwrite(STDERR, 'FAILED   ' . $path . ': ' . $moved['error'] . "\n");
		exit(1);
	}

	echo 'MOVED    ' . $legacy . "'s edits of " . $path . ': ' . $moved . "\n";
	exit(0);

}

$listed = tr_resolve_git($repository, array('ls-files', '-u', '-z'));

if ($listed['status'] !== 0) {
	fwrite(STDERR, 'git ls-files -u failed: ' . $listed['err'] . "\n");
	exit(1);
}

$failed = 0;
$settled = 0;
$moves = array();
$skipped_codes = array();

foreach (tr_resolve_unmerged($listed['out']) as $path => $stages) {

	$file = tr_resolve_language_path($path);

	if ($file === null) {
		continue;
	}

	$sharded = ($file['shard'] !== null);
	$folder = (tr_resolve_read_shards($repository . '/pinegrap/includes/local/' . $file['code']) !== null);
	$plan = tr_resolve_plan($stages, $sharded, $folder);

	if ($plan['action'] === 'skip') {
		echo 'SKIPPED  ' . $path . ': ' . $plan['reason'] . '; settle it by hand.' . "\n";
		$skipped_codes[$file['code']] = true;
		$failed++;
		continue;
	}

	// Each version git holds. Only the merge base may be missing here (both
	// sides added the file): an empty map.
	$maps = array();
	$broken = '';

	foreach (array(1, 2, 3) as $stage) {
		if (!isset($stages[$stage])) {
			$maps[$stage] = array();
			continue;
		}

		$text = tr_resolve_object($repository, $stages[$stage]);

		if (is_array($text)) {
			$broken = $text['error'];
			break;
		}

		$maps[$stage] = tr_resolve_map($text);

		if ($maps[$stage] === null) {
			$broken = 'stage ' . $stage . ' is not a JSON object';
			break;
		}
	}

	if ($broken !== '') {
		echo 'SKIPPED  ' . $path . ': ' . $broken . '; settle it by hand.' . "\n";
		$skipped_codes[$file['code']] = true;
		$failed++;
		continue;
	}

	if ($plan['action'] === 'move') {
		// After the shards: they must be settled before edits go onto them.
		$moves[$path] = array('code' => $file['code'], 'base' => $maps[1], 'side' => $maps[$plan['side']], 'side_name' => ($plan['side'] === 2) ? 'ours' : 'theirs');
		continue;
	}

	$dev = ($development === 'ours') ? $maps[2] : $maps[3];
	$branch = ($development === 'ours') ? $maps[3] : $maps[2];

	$merged = pg_lang_merge3($maps[1], $branch, $dev);

	if (count($merged) === 0) {
		echo 'SKIPPED  ' . $path . ': no key would be left; settle it by hand.' . "\n";
		$skipped_codes[$file['code']] = true;
		$failed++;
		continue;
	}

	if (!pg_lang_write_file($repository . '/' . $path, pg_lang_file_encode($merged, $sharded))) {
		fwrite(STDERR, 'Could not write ' . $repository . '/' . $path . "\n");
		exit(1);
	}

	$staged = tr_resolve_git($repository, array('add', '--', $path));

	if ($staged['status'] !== 0) {
		echo 'FAILED   ' . $path . ': written, but git add failed: ' . $staged['err'] . "\n";
		$failed++;
		continue;
	}

	echo 'SETTLED  ' . $path . ' (' . count($merged) . ' key(s))' . "\n";
	$settled++;

}

foreach ($moves as $path => $move) {

	if (isset($skipped_codes[$move['code']])) {
		echo 'SKIPPED  ' . $path . ': a shard of ' . $move['code'] . ' is still in conflict; settle it first.' . "\n";
		$failed++;
		continue;
	}

	$moved = tr_resolve_move($repository, $move['code'], $move['base'], $move['side']);

	if (is_array($moved)) {
		echo 'SKIPPED  ' . $path . ': ' . $moved['error'] . '; settle it by hand.' . "\n";
		$failed++;
		continue;
	}

	echo 'MOVED    ' . $path . ' (edited by ' . $move['side_name'] . '): ' . $moved . "\n";
	$settled++;

}

// A single file left beside its language's shards is never read (the folder
// wins) and check_lang.php refuses it: no success while one is there.
$singles = glob($repository . '/pinegrap/includes/local/*.json');

foreach (is_array($singles) ? $singles : array() as $single) {
	$code = basename($single, '.json');

	if (preg_match('/^' . TR_RESOLVE_CODE . '$/', $code) && (tr_resolve_read_shards(dirname($single) . '/' . $code) !== null)) {
		echo 'LEFT     pinegrap/includes/local/' . $code . '.json beside the shards in pinegrap/includes/local/' . $code . '/; its edits belong in the shards.' . "\n";
		$failed++;
	}
}

echo $settled . ' language file(s) settled.' . "\n";
exit($failed > 0 ? 1 : 0);

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Settles merge conflicts in language files from the three versions git
 * keeps, so nobody resolves tr.json by hand.
 *
 * During a merge it takes every conflicted file under
 * pinegrap/includes/local/ (the single <lang>.json or a shard in <lang>/),
 * starts from the development side and adds the other side's own edits:
 * the keys it added, the values it changed (its value wins when both sides
 * changed one key) and the keys it removed. A shard is written in key order;
 * the single file keeps development's lines and gets the branch's new keys at
 * its end. Each settled file is staged.
 *
 * Usage:  php tools/tr_resolve.php [--development=theirs|ours]
 *             theirs (default): merging development into a branch;
 *             ours: merging a branch into development or a merge train.
 *         php tools/tr_resolve.php --legacy=<ref> [--lang=tr]
 *             once the single file became shards: re-applies what <ref>
 *             changed in includes/local/<lang>.json since its merge base with
 *             HEAD onto the shards (then git rm the old file if git kept it).
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

if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $lang)) {
	fwrite(STDERR, "Not a language code: $lang\n");
	exit(1);
}

// The text of a blob git can name ($spec such as ':2:path' or 'ref:path'),
// or null when there is none.
function tr_resolve_blob($repository, $spec)
{
	exec('git -C ' . escapeshellarg($repository) . ' cat-file -e ' . escapeshellarg($spec) . ' 2>/dev/null', $output, $status);

	if ($status !== 0) {
		return null;
	}

	return (string) shell_exec('git -C ' . escapeshellarg($repository) . ' show ' . escapeshellarg($spec));
}

// A language map from a blob's text: array() for a missing blob, null for a
// blob that is not a JSON object.
function tr_resolve_map($text)
{
	if ($text === null) {
		return array();
	}

	$map = json_decode($text, true);

	return is_array($map) ? $map : null;
}

if ($legacy !== '') {

	$folder = $repository . '/pinegrap/includes/local/' . $lang;
	$path = 'pinegrap/includes/local/' . $lang . '.json';

	$shards = pg_lang_read_folder($folder);

	if ($shards === null) {
		fwrite(STDERR, "No shards in pinegrap/includes/local/$lang/: --legacy is for after the conversion.\n");
		exit(1);
	}

	$merge_base = trim((string) shell_exec('git -C ' . escapeshellarg($repository) . ' merge-base HEAD ' . escapeshellarg($legacy) . ' 2>/dev/null'));

	if ($merge_base === '') {
		fwrite(STDERR, "No merge base between HEAD and $legacy\n");
		exit(1);
	}

	$base = tr_resolve_map(tr_resolve_blob($repository, $merge_base . ':' . $path));
	$branch = tr_resolve_map(tr_resolve_blob($repository, $legacy . ':' . $path));

	if (($base === null) || ($branch === null)) {
		fwrite(STDERR, "$path is not valid JSON at the merge base or at $legacy\n");
		exit(1);
	}

	$merged = pg_lang_merge3($base, $branch, $shards);

	foreach (pg_lang_split($merged) as $name => $shard) {
		$file = $folder . '/' . $name . '.json';
		$text = pg_lang_file_encode($shard);

		if (!is_file($file) || ((string) file_get_contents($file) !== $text)) {
			if (!pg_lang_write_file($file, $text)) {
				fwrite(STDERR, 'Could not write ' . $file . "\n");
				exit(1);
			}
		}
	}

	echo 'Re-applied ' . $legacy . "'s edits of $path onto pinegrap/includes/local/$lang/ (" . (count($merged) - count($shards)) . " key(s) more).\n";
	exit(0);

}

$conflicted = array_filter(array_map('trim', explode("\n", (string) shell_exec('git -C ' . escapeshellarg($repository) . ' diff --name-only --diff-filter=U'))));

$failed = 0;
$settled = 0;

foreach ($conflicted as $path) {

	if (!preg_match('#^pinegrap/includes/local/(?:([a-z]{2,3}(?:-[A-Za-z]{2,4})?)/)?[^/]+\.json$#', $path, $match)) {
		continue;
	}

	$sharded = isset($match[1]) && ($match[1] !== '');

	$base = tr_resolve_map(tr_resolve_blob($repository, ':1:' . $path));
	$ours = tr_resolve_map(tr_resolve_blob($repository, ':2:' . $path));
	$theirs = tr_resolve_map(tr_resolve_blob($repository, ':3:' . $path));

	if (($base === null) || ($ours === null) || ($theirs === null)) {
		echo 'SKIPPED  ' . $path . ': one side is not valid JSON; settle it by hand.' . "\n";
		$failed++;
		continue;
	}

	$dev = ($development === 'ours') ? $ours : $theirs;
	$branch = ($development === 'ours') ? $theirs : $ours;

	$merged = pg_lang_merge3($base, $branch, $dev);

	if (!pg_lang_write_file($repository . '/' . $path, pg_lang_file_encode($merged, $sharded))) {
		fwrite(STDERR, 'Could not write ' . $repository . '/' . $path . "\n");
		exit(1);
	}

	exec('git -C ' . escapeshellarg($repository) . ' add ' . escapeshellarg($path), $output, $status);

	echo 'SETTLED  ' . $path . ' (' . count($merged) . ' key(s))' . "\n";
	$settled++;

}

echo $settled . ' language file(s) settled.' . "\n";
exit($failed > 0 ? 1 : 0);

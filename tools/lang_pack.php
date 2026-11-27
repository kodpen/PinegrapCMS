<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reads a language pack beside the Turkish wording, the full list of keys:
 * what is left to translate, what is wrong, and how far each shard is.
 * Writing stays with tools/tr_add.php --lang=<code> --from=<file.json>.
 *
 * Usage:  php tools/lang_pack.php <code> --missing [--shard=<x>] [--limit=<n>]
 *         php tools/lang_pack.php <code> --check
 *         php tools/lang_pack.php <code> --count
 *
 * --missing prints, as a JSON array, each key Turkish has and the language
 * lacks: {"key": English, "tr": its Turkish}, in key order; --shard keeps
 * one shard (a … z, 0, _), --limit the first n. --check prints each key
 * Turkish does not have and each wording whose placeholders, tags, entities
 * or end spaces differ from its key's. --count prints the figures a shard.
 * A language with no wording yet reads as empty.
 *
 * Exit:   0; with --check, 1 when a problem is printed. 1 on a wrong call.
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

$usage = "Usage: php tools/lang_pack.php <code> --missing [--shard=<x>] [--limit=<n>] | --check | --count\n";

$code = '';
$mode = '';
$shard = '';
$limit = 0;

foreach (array_slice($argv, 1) as $argument) {
	if (in_array($argument, array('--missing', '--check', '--count'), true)) {
		if ($mode !== '') {
			fwrite(STDERR, "One of --missing, --check and --count at a time.\n" . $usage);
			exit(1);
		}
		$mode = substr($argument, 2);
	} elseif (strpos($argument, '--shard=') === 0) {
		$shard = substr($argument, 8);
	} elseif (strpos($argument, '--limit=') === 0) {
		$limit = substr($argument, 8);
	} elseif (strpos($argument, '--') === 0) {
		fwrite(STDERR, "Unknown option: $argument\n" . $usage);
		exit(1);
	} elseif ($code === '') {
		$code = $argument;
	} else {
		fwrite(STDERR, "One language code only.\n" . $usage);
		exit(1);
	}
}

if (($code === '') || ($mode === '')) {
	fwrite(STDERR, $usage);
	exit(1);
}

if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)) {
	fwrite(STDERR, "Not a language code: $code\n");
	exit(1);
}

if (($code === 'tr') || ($code === 'en')) {
	fwrite(STDERR, "Turkish is the full list the packs are read against, and English is the keys themselves.\n");
	exit(1);
}

if (($shard !== '') && !preg_match('/^([a-z]|0|_)$/', $shard)) {
	fwrite(STDERR, "Not a shard: $shard (a … z, 0 or _)\n");
	exit(1);
}

if (!preg_match('/^\d*$/', (string) $limit)) {
	fwrite(STDERR, "Not a number: --limit=$limit\n");
	exit(1);
}

if (($mode !== 'missing') && (($shard !== '') || ($limit !== 0))) {
	fwrite(STDERR, "--shard and --limit go with --missing.\n");
	exit(1);
}

$local = dirname(__DIR__) . '/pinegrap/includes/local';

// Read from the files, never through pg_lang_map()'s cache in data/temp.
$turkish = pg_lang_read_folder($local . '/tr');

if (!is_array($turkish) || (count($turkish) === 0)) {
	fwrite(STDERR, "The Turkish wording (includes/local/tr/) holds no shard.\n");
	exit(1);
}

$language = pg_lang_read_folder($local . '/' . $code);

if (!is_array($language)) {
	$single = $local . '/' . $code . '.json';
	$language = is_file($single) ? json_decode((string) file_get_contents($single), true) : array();
}

if (!is_array($language)) {
	fwrite(STDERR, "includes/local/$code.json is not valid JSON.\n");
	exit(1);
}

if ($mode === 'missing') {
	echo json_encode(pg_lang_pack_missing($turkish, $language, $shard, (int) $limit), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if ($mode === 'check') {
	$problems = pg_lang_pack_problems($turkish, $language);

	foreach ($problems as $problem) {
		echo $problem . "\n";
	}

	echo count($problems) . ' problem(s) in ' . count($language) . ' key(s) of ' . $code . ".\n";
	exit(count($problems) > 0 ? 1 : 0);
}

$counts = pg_lang_pack_counts($turkish, $language);
$total = array('turkish' => 0, 'translated' => 0, 'missing' => 0, 'extra' => 0);

echo str_pad('shard', 7) . str_pad('turkish', 9, ' ', STR_PAD_LEFT) . str_pad('translated', 12, ' ', STR_PAD_LEFT) . str_pad('missing', 9, ' ', STR_PAD_LEFT) . str_pad('extra', 7, ' ', STR_PAD_LEFT) . str_pad('done', 7, ' ', STR_PAD_LEFT) . "\n";

$row = function ($name, array $figures) {
	$done = ($figures['turkish'] > 0) ? round(100 * $figures['translated'] / $figures['turkish']) . '%' : '-';

	return str_pad($name, 7)
		. str_pad((string) $figures['turkish'], 9, ' ', STR_PAD_LEFT)
		. str_pad((string) $figures['translated'], 12, ' ', STR_PAD_LEFT)
		. str_pad((string) $figures['missing'], 9, ' ', STR_PAD_LEFT)
		. str_pad((string) $figures['extra'], 7, ' ', STR_PAD_LEFT)
		. str_pad($done, 7, ' ', STR_PAD_LEFT) . "\n";
};

foreach ($counts as $name => $figures) {
	echo $row($name, $figures);

	foreach ($total as $kind => $sum) {
		$total[$kind] = $sum + $figures[$kind];
	}
}

echo $row('all', $total);
exit(0);

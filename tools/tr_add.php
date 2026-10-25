<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Adds translation keys to a language without hand-editing its file.
 *
 * When the language is kept as shards (pinegrap/includes/local/<lang>/),
 * each key goes into its shard at its sorted place. Otherwise it goes at the
 * end of the single pinegrap/includes/local/<lang>.json, whose other lines
 * stay as they are. A key already there with the same value is skipped; with
 * another value it is refused unless --replace.
 *
 * Usage:  php tools/tr_add.php [--lang=tr] [--replace] "English" "Türkçe" ["English" "Türkçe" ...]
 *         php tools/tr_add.php [--lang=tr] [--replace] --from=<file.json>
 *         php tools/tr_add.php [--lang=tr] --normalize
 *
 * --from reads a JSON object of key => value pairs. --normalize rewrites the
 * language in its exact form: every key in its own shard, in order.
 *
 * Exit:   0 when every key was added, replaced or already there; 1 otherwise.
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

$lang = 'tr';
$replace = false;
$normalize = false;
$from = '';
$words = array();

foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--lang=') === 0) {
		$lang = substr($argument, 7);
	} elseif ($argument === '--replace') {
		$replace = true;
	} elseif ($argument === '--normalize') {
		$normalize = true;
	} elseif (strpos($argument, '--from=') === 0) {
		$from = substr($argument, 7);
	} elseif (strpos($argument, '--') === 0) {
		fwrite(STDERR, "Unknown option: $argument\n");
		exit(1);
	} else {
		$words[] = $argument;
	}
}

if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $lang)) {
	fwrite(STDERR, "Not a language code: $lang\n");
	exit(1);
}

if ((count($words) % 2) !== 0) {
	fwrite(STDERR, "Keys and values come in pairs: \"English\" \"Translation\".\n");
	exit(1);
}

$pairs = array();

for ($i = 0; $i < count($words); $i += 2) {
	$pairs[$words[$i]] = $words[$i + 1];
}

if ($from !== '') {
	$from_map = is_file($from) ? json_decode((string) file_get_contents($from), true) : null;

	if (!is_array($from_map)) {
		fwrite(STDERR, "Not a JSON object of key => value pairs: $from\n");
		exit(1);
	}

	foreach ($from_map as $key => $value) {
		$pairs[(string) $key] = (string) $value;
	}
}

if (!$normalize && (count($pairs) === 0)) {
	fwrite(STDERR, "Nothing to add. Usage: php tools/tr_add.php [--lang=tr] [--replace] \"English\" \"Translation\"\n");
	exit(1);
}

$local = dirname(__DIR__) . '/pinegrap/includes/local';
$folder = $local . '/' . $lang;
$file = $local . '/' . $lang . '.json';

$report = array('added' => array(), 'replaced' => array(), 'skipped' => array(), 'refused' => array());

if (is_dir($folder)) {

	// Shards: each key into its own file, every file in its exact form.
	$all = pg_lang_read_folder($folder);
	$all = is_array($all) ? $all : array();

	$result = pg_lang_add($all, $pairs, $replace);

	foreach ($report as $kind => $keys) {
		$report[$kind] = $result[$kind];
	}

	$shards = pg_lang_split($result['map']);
	$existing = glob($folder . '/*.json');
	$existing = is_array($existing) ? $existing : array();

	foreach ($shards as $name => $shard) {
		$path = $folder . '/' . $name . '.json';
		$text = pg_lang_file_encode($shard);

		if (!is_file($path) || ((string) file_get_contents($path) !== $text)) {
			if (!pg_lang_write_file($path, $text)) {
				fwrite(STDERR, 'Could not write ' . $path . "\n");
				exit(1);
			}
		}
	}

	if ($normalize) {
		foreach ($existing as $path) {
			if (!isset($shards[basename($path, '.json')])) {
				unlink($path);
			}
		}
	}

} elseif (is_file($file)) {

	// The single file: new keys at its end, every other line where it was.
	$text = (string) file_get_contents($file);
	$map = json_decode($text, true);

	if (!is_array($map)) {
		fwrite(STDERR, "Not valid JSON: $file\n");
		exit(1);
	}

	if (!$normalize && ($text !== pg_lang_file_encode($map, false))) {
		fwrite(STDERR, "$file is not in its exact form, so writing it would move other lines. Run php tools/tr_add.php --lang=$lang --normalize first, on its own commit.\n");
		exit(1);
	}

	$result = pg_lang_add($map, $pairs, $replace);

	foreach ($report as $kind => $keys) {
		$report[$kind] = $result[$kind];
	}

	$new_text = pg_lang_file_encode($result['map'], false);

	if ($new_text !== $text) {
		if (!pg_lang_write_file($file, $new_text)) {
			fwrite(STDERR, 'Could not write ' . $file . "\n");
			exit(1);
		}
	}

} else {
	fwrite(STDERR, "No language file: neither $folder/ nor $file\n");
	exit(1);
}

echo count($report['added']) . ' added, ' . count($report['replaced']) . ' replaced, ' . count($report['skipped']) . ' already there.' . "\n";

foreach ($report['refused'] as $key) {
	echo 'REFUSED  already there with another value (pass --replace to change it): ' . $key . "\n";
}

exit(count($report['refused']) > 0 ? 1 : 0);

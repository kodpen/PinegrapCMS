<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish search of panel lists searched in the browser:
 * pgListTurkishMatch() in backend.src.js, run under Node, finds a row when
 * every search word is found either as DataTables finds it or folded with
 * window.pgFoldTR (I, İ, ı and i are one letter), so "YILDIZ" and "yildiz"
 * find "Yıldız" and "isik" finds "Işık"; words in any order and quoted
 * phrases keep working.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The matcher of backend.src.js: window.pgFoldTR, then from the Turkish fold
// to the function that installs the matcher on a table.
function pg_list_turkish_search_test_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$fold_start = strpos($source, 'window.pgFoldTR = function');
	$fold_end = strpos($source, "};\n", (int) $fold_start);
	$start = strpos($source, 'function pgListTurkishFold(');
	$end = strpos($source, 'function pgListTurkishSearch(', (int) $start);

	if ($fold_start === false || $fold_end === false || $start === false || $end === false) {
		return '';
	}

	return 'var window = {};' . "\n" . substr($source, $fold_start, $fold_end + 3 - $fold_start)
		. substr($source, $start, $end - $start);
}

// Runs the cases with Node and returns their JSON; null without Node,
// array('error' => output) when the script fails.
function pg_list_turkish_search_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec')) {
		return $result = null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtr');

	if ($file === false) {
		return $result = null;
	}

	$cases = array(
		array('YILDIZ', 'Can Yıldız'),
		array('YALÇIN', 'Yalçın'),
		array('IPHONE', 'iPhone'),
		array('iphone', 'iPhone'),
		array('DEMİR', 'Demir'),
		array('demir', 'Demir'),
		array('ışık', 'Işık'),
		array('IŞIK', 'Işık'),
		array('yildiz', 'Yıldız'),
		array('YILDIZ can', 'Can Yıldız'),
		array('can YILDIZ', 'Can Yıldız'),
		array('"can yıl"', 'Can Yıldız'),
		array('"yıldız can"', 'Can Yıldız'),
		array('YILDIZ ahmet', 'Can Yıldız'),
		array('', 'Can Yıldız'),
		array('isik', 'Işık'),
		array('İSİM', 'isim'),
		array('ismail', 'İsmail'),
		array('yildiz !can', 'Can Yıldız'),
	);

	$script = 'var PG_SOURCE = ' . json_encode(pg_list_turkish_search_test_source()) . ";\n"
		. 'var CASES = ' . json_encode($cases) . ";\n"
		. "var match = new Function(PG_SOURCE + '\\nreturn pgListTurkishMatch;')();\n"
		. "console.log(JSON.stringify(CASES.map(function (c) { return match(c[0], c[1]); })));\n";

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	if ($code !== 0 || !is_array($json)) {
		return $result = array('error' => $text);
	}

	return $result = $json;
}

function test_list_turkish_client_search_matcher()
{
	pg_assert_true(pg_list_turkish_search_test_source() !== '', 'matcher found in backend.src.js');

	$r = pg_list_turkish_search_test_run();

	if ($r === null) {
		return;
	}

	if (isset($r['error'])) {
		pg_assert_same('', $r['error'], 'matcher under Node');
		return;
	}

	pg_assert_same(true, $r[0], 'YILDIZ finds Can Yıldız');
	pg_assert_same(true, $r[1], 'YALÇIN finds Yalçın');
	pg_assert_same(true, $r[2], 'IPHONE finds iPhone');
	pg_assert_same(true, $r[3], 'iphone finds iPhone');
	pg_assert_same(true, $r[4], 'DEMİR finds Demir');
	pg_assert_same(true, $r[5], 'demir finds Demir');
	pg_assert_same(true, $r[6], 'ışık finds Işık');
	pg_assert_same(true, $r[7], 'IŞIK finds Işık');
	pg_assert_same(true, $r[8], 'yildiz finds Yıldız: I, İ, ı and i are one letter');
	pg_assert_same(true, $r[9], 'two words, row order');
	pg_assert_same(true, $r[10], 'two words, other order');
	pg_assert_same(true, $r[11], 'quoted phrase found as a phrase');
	pg_assert_same(false, $r[12], 'quoted phrase in the wrong order is not found');
	pg_assert_same(false, $r[13], 'every word must be found');
	pg_assert_same(true, $r[14], 'an empty search finds every row');
	pg_assert_same(true, $r[15], 'isik finds Işık');
	pg_assert_same(true, $r[16], 'İSİM finds isim');
	pg_assert_same(true, $r[17], 'ismail finds İsmail');
	pg_assert_same(false, $r[18], 'a !word found folded leaves the row out');
}

// The matcher is installed only on Turkish panels and never on a list paged
// on the server; the search box keeps the typed text for the list state.
function test_list_turkish_client_search_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains('function pgListTurkishSearch(', $source, 'installer exists');
	pg_assert_contains("/^tr/i.test(String(software_system_language", $source, 'only on a Turkish panel');
	pg_assert_contains('&& !options.serverSide) {' . "\n" . '            pgListTurkishSearch(datatable);', $source, 'not on a list paged on the server');
	pg_assert_contains('search: String(datatable.search())', $source, 'the list state keeps the typed text');
}

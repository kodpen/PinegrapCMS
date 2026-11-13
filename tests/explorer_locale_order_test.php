<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File and Catalog Manager list rows in the panel language's order and
 * find words written with İ, I, ı or i alike (view_folders.php): one fold
 * helper for the bulk-rename find, the same plus accents dropped for the
 * search, type-ahead and picker filters (ü finds u, #9021), one
 * Intl.Collator for the text sort keys, and the Shared view's folder
 * groups ordered by pg_geo_label_compare(). The browser script is pinned
 * by its source; the PHP compare is run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

function explorer_locale_order_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// The body of one function in the screen's script, up to the next function
// declared at the same indent.
function explorer_locale_order_function($name)
{
	$source = explorer_locale_order_source();
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

// The fold lowers with the Turkish rules and then writes ı as i, so İ, I, ı
// and i meet, and no character grows into two (the rename slices by index).
function test_explorer_fold_makes_dotted_and_dotless_i_equal()
{
	$fold = explorer_locale_order_function('foldText');

	pg_assert_true($fold !== '', 'foldText() is found in view_folders.php');
	pg_assert_contains("toLocaleLowerCase('tr')", $fold);
	pg_assert_contains("replace(/\\u0131/g, 'i')", $fold);
	pg_assert_true(strpos($fold, 'normalize(') === false, 'no normalisation that changes the length');
}

// Every matcher reads a fold; none lowers on its own any more. The searches
// read searchFold() (accents dropped too); the bulk-rename find keeps
// foldText() alone, so an "s" does not replace a "ş".
function test_explorer_matchers_use_the_fold()
{
	$folds = array(
		'visibleItems' => 'searchFold(',
		'typeAheadJump' => 'searchFold(',
		'renderTransferTargets' => 'searchFold(',
		'renderWsSendChannels' => 'searchFold(',
		'bulkRenameReplace' => 'foldText(',
	);

	foreach ($folds as $name => $fold) {
		$body = explorer_locale_order_function($name);

		pg_assert_true($body !== '', $name . '() is found');
		pg_assert_contains($fold, $body, $name . '() folds through ' . $fold . ')');
		pg_assert_true(strpos($body, '.toLowerCase()') === false, $name . '() does not use toLowerCase()');
		pg_assert_true(strpos($body, "toLocaleLowerCase('tr')") === false, $name . '() does not use the bare Turkish fold');
	}

	pg_assert_false(strpos(explorer_locale_order_function('bulkRenameReplace'), 'searchFold(') !== false, 'the rename find does not drop accents');
}

// A search typed with ü, ç, ş, ğ or ö finds the ASCII name the file was
// stored under (#9021), and no character grows or shrinks. Run in Node;
// skipped where Node cannot run.
function test_explorer_search_fold_drops_accents()
{
	$fold = explorer_locale_order_function('foldText');
	$search = explorer_locale_order_function('searchFold');

	pg_assert_true($search !== '', 'searchFold() is found');
	pg_assert_contains('return foldText(text).replace(', $search, 'searchFold() starts from foldText()');

	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	// The two functions and the lines between them (the letter table).
	$source = explorer_locale_order_source();
	$start = strpos($source, "\n    function foldText(");
	$end = strpos($source, "\n    function ", strpos($source, "\n    function searchFold(") + 1);
	$script = substr($source, $start, $end - $start) . "\n"
		. 'var words = ["Türkçe", "İĞÜŞÖÇ", "ığüş", "Straße", "Ørsted", "Łódź", "Crème brûlée", "Việt", "naïve", "×÷"];'
		. 'process.stdout.write(JSON.stringify(words.map(function (w) { return [searchFold(w), searchFold(w).length === w.length, foldText(w)]; })));';

	$file = tempnam(sys_get_temp_dir(), 'pgfold') . '.js';
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $out));

	$result = json_decode(implode("\n", $out), true);

	pg_assert_same(array('turkce', true, 'türkçe'), $result[0], 'Türkçe');
	pg_assert_same(array('igusoc', true, 'iğüşöç'), $result[1], 'Turkish capitals');
	pg_assert_same(array('igus', true, 'iğüş'), $result[2], 'dotless i and accents');
	pg_assert_same(array('straße', true, 'straße'), $result[3], 'ß stays one letter');
	pg_assert_same('orsted', $result[4][0], 'ø');
	pg_assert_same('lodz', $result[5][0], 'ł and the accents');
	pg_assert_same('creme brulee', $result[6][0], 'French');
	pg_assert_same(array('viet', true), array($result[7][0], $result[7][1]), 'Latin Extended Additional');
	pg_assert_same('naive', $result[8][0], 'diaeresis');
	pg_assert_same('×÷', $result[9][0], 'signs are not letters');
	pg_assert_true(strpos($fold, 'normalize(') === false, 'foldText() itself is unchanged');
}

// Text keys are compared with the panel language's collator, numbers as
// numbers; without Intl the old code-unit order stays.
function test_explorer_sort_compares_text_with_a_collator()
{
	$sort = explorer_locale_order_function('sortItems');
	$collator = explorer_locale_order_function('textCollator');

	pg_assert_true($sort !== '', 'sortItems() is found');
	pg_assert_contains('textCollator()', $sort);
	pg_assert_true(strpos($sort, "(a.name || '').toLowerCase()") === false, 'sortItems() no longer lowercases its keys up front');
	pg_assert_contains("typeof va === 'string'", $sort, 'only string keys go to the collator');
	pg_assert_contains('new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: \'base\' })', $collator);
	pg_assert_contains("typeof Intl !== 'undefined'", $collator, 'falls back without Intl');
}

// The Shared view's groups read Çalışanlar after Belgeler, not after Zeytin.
function test_explorer_shared_groups_follow_the_language_order()
{
	$groups = array(array('path' => 'Zeytin'), array('path' => 'Çalışanlar'), array('path' => 'belgeler'), array('path' => 'Özel / Notlar'));

	usort($groups, 'pg_explorer_compare_shared_groups');

	$paths = array_map(function ($group) { return $group['path']; }, $groups);

	if (class_exists('Collator')) {
		pg_assert_same(array('belgeler', 'Çalışanlar', 'Özel / Notlar', 'Zeytin'), $paths);
	} else {
		pg_assert_same('belgeler', $paths[0], 'case does not matter');
	}

	pg_assert_same(0, pg_explorer_compare_shared_groups(array('path' => 'A'), array('path' => 'A')));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File and Catalog Manager list rows in the panel language's order and
 * find words written with İ, I, ı or i alike (view_folders.php): one fold
 * helper for the search, type-ahead, picker filters and bulk-rename find,
 * one Intl.Collator for the text sort keys, and the Shared view's folder
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

// Every matcher reads the fold; none lowers on its own any more.
function test_explorer_matchers_use_the_fold()
{
	foreach (array('visibleItems', 'typeAheadJump', 'renderTransferTargets', 'renderWsSendChannels', 'bulkRenameReplace') as $name) {
		$body = explorer_locale_order_function($name);

		pg_assert_true($body !== '', $name . '() is found');
		pg_assert_contains('foldText(', $body, $name . '() folds through foldText()');
		pg_assert_true(strpos($body, '.toLowerCase()') === false, $name . '() does not use toLowerCase()');
		pg_assert_true(strpos($body, "toLocaleLowerCase('tr')") === false, $name . '() does not use the bare Turkish fold');
	}
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

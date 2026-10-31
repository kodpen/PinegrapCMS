<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel lists sorted in PHP order names the way the panel language does
 * (pg_geo_label_compare()), not by bytes: Edit Product Group and Featured &
 * New Items (pg_list_sort_by_order_and_name()), the Site Log merged with
 * the error log (view_log.php), the Workspace folder picker
 * (ws_library_folder_name_compare()) and the OneNote import's notebooks
 * and sections (ws_note_onenote_notebooks()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/library.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_onenote.php');

// Sort order first ('' counts as 0, as the product group form saves it),
// then the name in the panel language's order.
function test_panel_list_product_items_sort_by_order_then_name()
{
	$items = array(
		array('name' => 'Zeytin', 'sort_order' => ''),
		array('name' => 'Şal', 'sort_order' => '0'),
		array('name' => 'Last', 'sort_order' => '5'),
		array('name' => 'Çanta', 'sort_order' => 0),
		array('name' => 'armut', 'sort_order' => ''),
		array('name' => 'First', 'sort_order' => '-1'),
	);

	$names = array_column(pg_list_sort_by_order_and_name($items), 'name');

	pg_assert_same('First', $names[0], 'a lower sort order comes first');
	pg_assert_same('Last', $names[5], 'a higher sort order comes last');
	pg_assert_same('armut', $names[1], 'case does not matter');

	if (class_exists('Collator')) {
		pg_assert_same(array('First', 'armut', 'Çanta', 'Şal', 'Zeytin', 'Last'), $names);
	}
}

// Both screens call the helper; the byte-order array_multisort() is gone.
function test_panel_list_product_screens_use_the_helper()
{
	foreach (array('edit_product_group.php', 'edit_featured_and_new_items.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_list_sort_by_order_and_name($items)', $source, $file);
		pg_assert_true(strpos($source, 'array_multisort(') === false, $file . ' no longer sorts by bytes');
	}
}

// The merged Site Log compares text through the panel language's order.
function test_panel_list_site_log_merge_uses_the_language_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_log.php');

	pg_assert_contains('$result = pg_geo_label_compare((string) $a_value, (string) $b_value);', $source);
	pg_assert_true(strpos($source, 'strcasecmp((string) $a_value') === false, 'no strcasecmp left in the merge sort');
}

// Numbers by value and Turkish letters in place; strnatcasecmp() without intl.
function test_panel_list_workspace_folder_names_keep_number_order()
{
	$names = array('Klasör 10', 'Zeytin', 'Çalışma', 'klasör 2', 'Özel');
	usort($names, 'ws_library_folder_name_compare');

	if (class_exists('Collator')) {
		pg_assert_same(array('Çalışma', 'klasör 2', 'Klasör 10', 'Özel', 'Zeytin'), $names);
	} else {
		pg_assert_same(array('klasör 2', 'Klasör 10'), array_slice($names, 0, 2), 'natural order, case aside');
	}

	pg_assert_true(ws_library_folder_name_compare('Klasör 2', 'Klasör 10') < 0, '2 before 10');
	pg_assert_same(0, ws_library_folder_name_compare('Arşiv', 'Arşiv'));
}

// The OneNote import lists notebooks and their sections in the language's order.
function test_panel_list_onenote_notebooks_in_language_order()
{
	$section = function ($id, $name, $book, $book_name) {
		return array('id' => $id, 'displayName' => $name, 'parentNotebook' => array('id' => $book, 'displayName' => $book_name));
	};

	$notebooks = ws_note_onenote_notebooks(array(
		$section('s1', 'Zaman', 'b1', 'Zeytin'),
		$section('s2', 'Özel', 'b1', 'Zeytin'),
		$section('s3', 'alfa', 'b1', 'Zeytin'),
		$section('s4', 'Bir', 'b2', 'Çalışma'),
	));

	pg_assert_same('alfa', $notebooks[array_search('Zeytin', array_column($notebooks, 'name'))]['sections'][0]['name'], 'case does not matter');

	if (class_exists('Collator')) {
		pg_assert_same(array('Çalışma', 'Zeytin'), array_column($notebooks, 'name'));
		pg_assert_same(array('alfa', 'Özel', 'Zaman'), array_column($notebooks[1]['sections'], 'name'));
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shared components and system widgets panel of the Visual
 * Page Editor's design list: the PHP names of the widget kinds
 * (pg_sw_type_labels()) mirror the editor's SW_TYPES list, kind for kind and
 * key for key, so a row reads the same on both screens; the bulk delete
 * (shared_component/delete_many) takes any number of rows, keeps the ones
 * still placed unless forced, and cuts its IN lists into slices.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// kind => English key of SW_TYPES in style_designer.js.
function _test_components_js_types()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, 'var SW_TYPES = [');
	$end = $start === false ? false : strpos($js, '];', $start);
	if ($start === false || $end === false) {
		return array();
	}
	preg_match_all("/\\{\\s*type:\\s*'([a-z_]+)',\\s*label:\\s*_sdT\\('((?:[^'\\\\]|\\\\.)*)'\\)/", substr($js, $start, $end - $start), $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $row) {
		$out[$row[1]] = $row[2];
	}
	return $out;
}

// kind => English key of pg_sw_type_labels() in includes/fn/widgets.php.
function _test_components_php_types()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($php, 'function pg_sw_type_labels()');
	$end = $start === false ? false : strpos($php, "\n}", $start);
	if ($start === false || $end === false) {
		return array();
	}
	preg_match_all("/'([a-z_]+)'\\s*=>\\s*lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", substr($php, $start, $end - $start), $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $row) {
		$out[$row[1]] = $row[2];
	}
	return $out;
}

function test_designer_components_type_labels_cover_every_kind()
{
	$js = _test_components_js_types();
	pg_assert_true(count($js) > 0, 'SW_TYPES is found in style_designer.js');
	pg_assert_same(array_keys($js), array_keys(pg_sw_type_labels()), 'same kinds, same order');
}

function test_designer_components_type_labels_use_the_editor_keys()
{
	$js = _test_components_js_types();
	pg_assert_same($js, _test_components_php_types(), 'each kind is named with the key the editor uses');
}

function test_designer_components_delete_ids_are_cleaned_without_a_cap()
{
	pg_assert_same(array(4, 7, 9), pg_shared_component_delete_ids(array('4', 7, 4, 0, -2, 'x', 9, array(3), null)), 'positive, once each, in order');
	pg_assert_same(array(12), pg_shared_component_delete_ids('12'), 'a single value');
	pg_assert_same(array(), pg_shared_component_delete_ids(null), 'nothing');
	pg_assert_same(3000, count(pg_shared_component_delete_ids(range(1, 3000))), 'no upper bound');
}

function test_designer_components_id_slices_hold_at_most_500()
{
	pg_assert_same(array(), pg_shared_component_id_slices(array()), 'no ids, no statement');
	pg_assert_same(array('4,7,9'), pg_shared_component_id_slices(array(4, '7', 9)), 'one short list');
	$slices = pg_shared_component_id_slices(range(1, 1201));
	pg_assert_same(3, count($slices), '1201 ids: three statements');
	pg_assert_same(500, count(explode(',', $slices[0])), 'first slice full');
	pg_assert_same('1001,1002', substr($slices[2], 0, 9), 'third slice starts where the second stopped');
	pg_assert_same(201, count(explode(',', $slices[2])), 'last slice holds the rest');
	pg_assert_same(range(1, 1201), array_map('intval', explode(',', implode(',', $slices))), 'every id once, in order');
}

function test_designer_components_delete_split_keeps_placed_rows_unless_forced()
{
	$names = array(1 => 'Header', 2 => 'Cart', 3 => 'Footer [1]');
	$usage = array(1 => array(), 2 => array(array('page_id' => 5, 'page_name' => 'home', 'style_id' => 1, 'style_name' => 'Shop', 'binned' => false)), 3 => array());

	$plain = pg_shared_component_delete_split(array(1, 2, 3, 8), $names, $usage, false);
	pg_assert_same(array(1, 3), $plain['deleted'], 'unused rows go; a row already gone is skipped');
	pg_assert_same(1, count($plain['kept']), 'the placed row stays');
	pg_assert_same(2, $plain['kept'][0]['id'], 'kept id');
	pg_assert_same('Cart', $plain['kept'][0]['name'], 'kept name');
	pg_assert_same($usage[2], $plain['kept'][0]['usage'], 'kept with where it is placed');

	$forced = pg_shared_component_delete_split(array(1, 2, 3, 8), $names, $usage, true);
	pg_assert_same(array(1, 2, 3), $forced['deleted'], 'force deletes the placed row too');
	pg_assert_same(array(), $forced['kept'], 'nothing kept');
}

function test_designer_components_delete_split_takes_thousands()
{
	$ids   = range(1, 4000);
	$names = array();
	$usage = array();
	foreach ($ids as $id) {
		$names[$id] = 'Widget ' . $id;
		$usage[$id] = ($id % 1000 === 0) ? array(array('page_id' => $id)) : array();
	}
	$split = pg_shared_component_delete_split($ids, $names, $usage, false);
	pg_assert_same(3996, count($split['deleted']), 'all unused rows in one call');
	pg_assert_same(array(1000, 2000, 3000, 4000), array_map(function ($k) { return $k['id']; }, $split['kept']), 'the four placed ones kept');
}

function test_designer_components_home_sends_no_cap()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/shared_component.php');
	pg_assert_false(strpos($php, 'Select at most 500') !== false, 'delete_many refuses no number of rows');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_home.js');
	pg_assert_true(strpos($js, "sub_action: 'delete_many', ids: ids") !== false, 'the home sends every ticked id in one request');
}

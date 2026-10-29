<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's own date and time fields on the panel's one
 * date-and-time control (pg_fields.js): the page schedule boxes of Page
 * Settings keep their datetime-local store (Y-m-d\TH:i) behind the control,
 * and the value box of a list filter row takes the hook of its field's type
 * (native date, datetime-local or time store) and is drawn when the rows
 * are bound.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_datetime_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of one function of style_designer.js, from its line to the next
// function declared at the same indent.
function designer_datetime_js_function($name)
{
	$src = designer_datetime_source('assets/js/style_designer.src.js');
	if (!preg_match('/\n(    function ' . preg_quote($name, '/') . '\(.*?)\n    (?:function |var |\/\/)/s', $src, $m)) {
		return '';
	}
	return $m[1];
}

function test_designer_page_schedule_boxes_on_the_datetime_control()
{
	$src = designer_datetime_source('includes/designer_screen.php');

	foreach (array('pg_page_publish_at' => 'page_publish_at', 'pg_page_unpublish_at' => 'page_unpublish_at') as $id => $key) {
		$found = preg_match('/<input type="datetime-local" id="' . $id . '"[^>]*>/', $src, $m);
		pg_assert_same(1, $found, $id . ' stays a datetime-local box (store Y-m-d\TH:i)');
		$tag = $found ? $m[0] : '';
		pg_assert_true(strpos($tag, ' data-pg-datetime ') !== false || strpos($tag, ' data-pg-datetime>') !== false, $id . ' carries the data-pg-datetime hook');
		pg_assert_true(strpos($tag, 'data-page-key="' . $key . '"') !== false, $id . ' still writes ' . $key);
	}

	$picker = strpos($src, 'get_date_picker_format()');
	$fields = strpos($src, 'pg_fields_script()');
	pg_assert_true($picker !== false && $fields !== false && $picker < $fields, 'the designer prints get_date_picker_format() before pg_fields_script()');
}

function test_designer_schedule_values_written_by_script_are_drawn()
{
	$sync = designer_datetime_js_function('_pgSyncScheduleFields');
	pg_assert_true($sync !== '', '_pgSyncScheduleFields() found');
	pg_assert_true(strpos($sync, '_sdDateFieldRefresh(pub)') !== false && strpos($sync, '_sdDateFieldRefresh(unpub)') !== false, 'the schedule boxes are redrawn after their value and disabled state are written');
	pg_assert_true(strpos(designer_datetime_js_function('_pgLoadPageIntoFields'), '_sdDateFieldRefresh(el)') !== false, 'a page loaded into the fields redraws a hooked date box');

	$refresh = designer_datetime_js_function('_sdDateFieldRefresh');
	pg_assert_true(strpos($refresh, 'window.pgFields.refresh(el)') !== false, '_sdDateFieldRefresh() goes through pgFields.refresh()');
	pg_assert_same(false, strpos($refresh, 'change'), '_sdDateFieldRefresh() fires no change');
}

function test_designer_filter_value_box_takes_its_types_hook()
{
	$src = designer_datetime_source('assets/js/style_designer.src.js');

	pg_assert_same(1, preg_match("/var inputType = \\(type === 'date'\\) \\? 'date' : \\(type === 'date and time'\\) \\? 'datetime-local' : \\(type === 'time'\\) \\? 'time' : 'text';/", $src), 'the value box keeps its native type: the store stays ISO');
	pg_assert_same(1, preg_match("/var valueHook = \\{ date: ' data-pg-date', 'datetime-local': ' data-pg-datetime', time: ' data-pg-time' \\}\\[inputType\\] \\|\\| '';/", $src), 'each native type gets its hook, text none');
	pg_assert_same(1, preg_match('/sd-sw-filter-value" data-sw-id="\' \+ sid \+ \'"\' \+ valueHook \+ \'/', $src), 'the value box prints the hook');
	pg_assert_same(1, preg_match('/_swFilterEls\.forEach\(function \(el\) \{\s*if \(window\.pgFields\) window\.pgFields\.init\(el\);/', $src), 'each filter row is drawn by pgFields.init() when it is bound');
	pg_assert_true(strpos($src, "q('.sd-sw-filter-value').type === 'datetime-local' && val) val = val.replace('T', ' ');") !== false, 'a date and time filter still saves Y-m-d H:i');
}

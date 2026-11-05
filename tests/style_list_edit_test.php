<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Page Styles list's quick edit
 * (includes/fn/style_list_edit.php): every one-line value the custom style
 * screen changes and the list shows is a field, a system style stays text,
 * the value each field opens with, what the screen's check gets for a
 * change and the answer's cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/custom_style_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/style_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $style_edit_test_name => $style_edit_test_value) {
	if (!defined($style_edit_test_name)) {
		define($style_edit_test_name, $style_edit_test_value);
	}
}

function pg_test_style_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'name' => 'Home <wide>', 'type' => 'custom', 'theme_name' => 'site.css', 'collection' => 'b',
		'layout_type' => 'custom', 'last_modified_timestamp' => '1700000000', 'last_modified_username' => 'admin',
	), $over);
}

// Every value the custom style screen changes on one line and the list
// shows is an editable column with a cell field; the counts, the theme,
// Last Modified and the designer button stay text. A new column fails here
// until it is one or the other.
function test_style_quick_every_screen_value_is_a_field()
{
	$text = array('folders', 'pages', 'theme', 'last_modified', 'designer');
	$fields = pg_style_quick_edit_columns();
	$cells = pg_style_list_cells(pg_test_style_quick_row(), $fields, 2, 3, true);

	foreach (pg_style_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_style_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_style_quick_fields_follow_the_screen()
{
	$fields = pg_style_quick_edit_columns();
	$choices = pg_custom_style_form_choices();

	pg_assert_same(pg_name_max_length(), $fields['name']['max'], 'the name fits the column');
	pg_assert_true($fields['name']['required'], 'the name is required');
	pg_assert_same($choices['collection'], array_map('strval', array_keys($fields['collection']['options'])), 'the collections the screen accepts');
	pg_assert_same($choices['layout_type'], array_map('strval', array_keys($fields['layout_type']['options'])), 'the layout overrides the screen accepts, none first');
}

function test_style_quick_system_style_stays_text()
{
	$cells = pg_style_list_cells(pg_test_style_quick_row(array('type' => 'system')), pg_style_quick_edit_columns(), 0, 0, true);

	foreach ($cells as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' of a system style is text');
	}

	pg_assert_contains('href="edit_system_style.php?id=7"', $cells['name']['html'], 'a system style opens its own screen');
	pg_assert_same('', $cells['designer']['html'], 'no classic designer button for a system style');
	pg_assert_false(pg_style_quick_cell_editable(array('type' => 'system')), 'a system style is not changed here');
	pg_assert_true(pg_style_quick_cell_editable(array('type' => 'custom')), 'a custom style is');
}

function test_style_quick_cells()
{
	$cells = pg_style_list_cells(pg_test_style_quick_row(), pg_style_quick_edit_columns(), 2, 3, true);

	pg_assert_contains('href="edit_custom_style.php?id=7"', $cells['name']['html'], 'the name links to the screen');
	pg_assert_contains('Home &lt;wide&gt;', $cells['name']['html'], 'the name is escaped');
	pg_assert_same('Home <wide>', $cells['name']['attributes']['data-pg-edit-value'], 'the name opens as stored');
	pg_assert_same('B', $cells['collection']['html'], 'the collection letter');
	pg_assert_same('b', $cells['collection']['attributes']['data-pg-edit-value'], 'the collection opens as stored');
	pg_assert_same('custom', $cells['layout_type']['attributes']['data-pg-edit-value'], 'the override opens as stored');
	pg_assert_same(3, (int) $cells['pages']['attributes']['data-pg-sort-value'], 'pages sort by their count');
	pg_assert_contains('classic=1', $cells['designer']['html'], 'a designer opens the style in the visual designer');

	$viewer = pg_style_list_cells(pg_test_style_quick_row(), pg_style_quick_edit_columns(), 0, 0, false);
	pg_assert_same('', $viewer['designer']['html'], 'no designer button without the right');
}

function test_style_quick_value_falls_back_like_the_screen()
{
	pg_assert_same('a', pg_style_quick_value('collection', array('collection' => '')), 'an empty collection opens as A');
	pg_assert_same('', pg_style_quick_value('layout_type', array('layout_type' => 'odd')), 'an unknown override opens as none');
	pg_assert_same('x', pg_style_quick_value('name', array('name' => 'x')), 'the name as stored');
}

function test_style_quick_post_changes_one_value()
{
	$stored = array('style_name' => 'Home', 'style_code' => '<system></system>', 'collection' => 'a', 'layout_type' => '', 'social_networking_position' => 'top_left');

	$post = pg_style_quick_post($stored, 'collection', 'b');
	pg_assert_same('b', $post['collection'], 'the changed value');
	pg_assert_same('Home', $post['name'], 'the stored name');
	pg_assert_same('<system></system>', $post['code'], 'the stored code is kept');
	pg_assert_same('top_left', $post['social_networking_position'], 'the stored position is kept');

	$values = pg_custom_style_form_values(pg_style_quick_post($stored, 'name', '  New  '), true);
	pg_assert_same('New', $values['style_name'], 'the screen trims the name');
	pg_assert_same('<system></system>', $values['style_code'], 'the code is written back unchanged');
	pg_assert_same('top_left', $values['social_networking_position'], 'the position is written back unchanged');

	pg_assert_same('Home', pg_style_quick_post($stored, 'style_code', 'x')['name'], 'an unknown field changes nothing');
	pg_assert_same('<system></system>', pg_style_quick_post($stored, 'code', 'x')['code'], 'the code is not a quick edit field');
}

function test_style_quick_name_refusal()
{
	$lookup = function ($sql, $values) {
		return (strpos($sql, 'COUNT') !== false) ? (($values[0] === 'Taken') ? 1 : 0) : 'Home';
	};

	pg_assert_contains('required', pg_custom_style_form_name_refusal('', 7, $lookup), 'an empty name');
	pg_assert_contains('already in use', pg_custom_style_form_name_refusal('Taken', 7, $lookup), 'a taken name');
	pg_assert_contains('at most', pg_custom_style_form_name_refusal(str_repeat('a', 101), 7, $lookup), 'a long name');
	pg_assert_same('', pg_custom_style_form_name_refusal('Home', 7, $lookup), 'the unchanged name');
	pg_assert_same('', pg_custom_style_form_name_refusal('Free', 7, $lookup), 'a free name');
}

function test_style_quick_saved_answer()
{
	$cells = pg_style_list_cells(pg_test_style_quick_row(), pg_style_quick_edit_columns(), 2, 3, true);
	$answer = pg_style_quick_saved($cells, 'collection');

	pg_assert_true($answer['ok'], 'a success');
	pg_assert_same('b', $answer['value'], 'the value the field opens with next');
	pg_assert_same('B', $answer['html'], 'the cell drawn');
	pg_assert_same(array('name', 'layout_type', 'last_modified'), array_keys($answer['cells']), 'the cells a change redraws');
}

function test_style_list_screen_uses_the_shared_code()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_styles.php');
	$request = strpos($source, 'pg_list_edit_requested()');

	pg_assert_true($request !== false, 'the list answers quick edits');
	pg_assert_true($request < strpos($source, "validate_area_access(\$user, 'designer')"), 'before the access check, so a refusal is JSON');
	pg_assert_contains("(int) \$user['role'] <= 1", $source, 'the custom style screen\'s rights');
	pg_assert_contains("'edit' => array('url' => 'view_styles.php'", $source, 'the table has quick edit');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/style_list_edit.php');
	pg_assert_contains('pg_custom_style_form_values(', $list, 'the screen\'s values');
	pg_assert_contains('pg_custom_style_form_name_refusal(', $list, 'the screen\'s name check');
	pg_assert_contains('pg_custom_style_form_update(', $list, 'the screen\'s save');
}

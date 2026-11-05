<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Themes list's quick edit (includes/fn/theme_list_edit.php):
 * every one-line value the theme screen changes is a field, the value each
 * field opens with, the form values the theme screen's check gets for a
 * change and the answer that redraws the theme an activation replaced.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/theme_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $theme_edit_test_name => $theme_edit_test_value) {
	if (!defined($theme_edit_test_name)) {
		define($theme_edit_test_name, $theme_edit_test_value);
	}
}

function pg_test_theme_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '12', 'name' => 'site.css', 'description' => "Main\ntheme", 'folder' => '3', 'folder_name' => 'Design',
		'activated_desktop_theme' => '1', 'activated_mobile_theme' => '0', 'timestamp' => '1700000000',
		'user_username' => 'admin', 'access_control_type' => 'public',
	), $over);
}

function pg_test_theme_quick_fields()
{
	return pg_theme_quick_edit_columns(array(3 => 'Design', 4 => "\u{00A0}\u{00A0}Themes"), array(3 => 'public'));
}

// Every value the theme screen changes is an editable column with a cell
// field (the description over several lines); Last Modified stays text. A new column fails here until it is one or the other.
function test_theme_quick_every_screen_value_is_a_field()
{
	$text = array('last_modified');
	$fields = pg_test_theme_quick_fields();
	$cells = pg_theme_list_cells(pg_test_theme_quick_row(), $fields);

	foreach (pg_theme_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable'], $fields[$key]), $key . ' is a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell carries its field');
	}

	foreach (array_keys($fields) as $field) {
		pg_assert_true(isset(pg_theme_list_columns(true)[$field]), $field . ' has a column');
	}

	foreach (pg_theme_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}

	foreach (pg_theme_list_cells(pg_test_theme_quick_row(), array()) as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' is text without fields');
	}
}

function test_theme_quick_values_and_cells()
{
	$fields = pg_test_theme_quick_fields();
	$row = pg_test_theme_quick_row();
	$cells = pg_theme_list_cells($row, $fields);

	pg_assert_same('site.css', pg_theme_quick_value('name', $row));
	pg_assert_same('3', pg_theme_quick_value('folder', $row));
	pg_assert_same('1', pg_theme_quick_value('activated_desktop', $row));
	pg_assert_same('0', pg_theme_quick_value('activated_mobile', $row));

	pg_assert_contains('edit_theme_file.php?id=12', $cells['name']['html']);
	pg_assert_contains('task_alt', $cells['activated_desktop']['html']);
	pg_assert_same('1', $cells['activated_desktop']['attributes']['data-pg-sort-value']);
	pg_assert_same('', $cells['activated_mobile']['html']);
	pg_assert_same('0', $cells['activated_mobile']['attributes']['data-pg-sort-value']);
	pg_assert_contains('public', $cells['folder']['class']);
	pg_assert_contains('Main<br />', $cells['description']['html']);

	// A folder the picker does not offer stays text.
	pg_assert_false(isset(pg_theme_list_cells(pg_test_theme_quick_row(array('folder' => '99')), $fields)['folder']['attributes']['data-pg-edit']));

	// Yes and No are the switches' values; any other folder is refused.
	pg_assert_true(pg_list_edit_parse($fields['activated_mobile'], '1')['ok']);
	pg_assert_false(pg_list_edit_parse($fields['activated_mobile'], '2')['ok']);
	pg_assert_true(pg_list_edit_parse($fields['folder'], '4')['ok']);
	pg_assert_false(pg_list_edit_parse($fields['folder'], '99')['ok']);
}

function test_theme_quick_form_values_change_one_field()
{
	$row = pg_test_theme_quick_row();
	$values = pg_theme_quick_form_values($row, 'activated_mobile', '1');

	pg_assert_same(array(
		'id' => '12', 'name' => 'site.css', 'folder' => '3', 'description' => "Main\ntheme",
		'activated_desktop_theme' => '1', 'activated_mobile_theme' => '1',
	), $values);

	pg_assert_same('0', pg_theme_quick_form_values($row, 'activated_desktop', '0')['activated_desktop_theme']);
	pg_assert_same('new.css', pg_theme_quick_form_values($row, 'name', 'new.css')['name']);
	pg_assert_same('4', pg_theme_quick_form_values($row, 'folder', '4')['folder']);

	pg_assert_same("New\nlines", pg_theme_quick_form_values($row, 'description', "New\nlines")['description']);

	// An unknown field changes nothing.
	pg_assert_same(pg_theme_quick_form_values($row, 'name', 'site.css'), pg_theme_quick_form_values($row, 'other', 'x'));
}

function test_theme_quick_saved_redraws_the_replaced_theme()
{
	$fields = pg_test_theme_quick_fields();
	$answer = pg_theme_quick_saved(
		pg_theme_list_cells(pg_test_theme_quick_row(array('activated_mobile_theme' => '1')), $fields),
		'activated_mobile',
		array(15 => pg_theme_list_cells(pg_test_theme_quick_row(array('id' => '15', 'activated_mobile_theme' => '0')), $fields))
	);

	pg_assert_true($answer['ok']);
	pg_assert_same('1', $answer['value']);
	pg_assert_false(isset($answer['cells']['activated_mobile']));
	pg_assert_true(isset($answer['cells']['last_modified']));
	pg_assert_same('0', $answer['rows']['15']['activated_mobile']['value']);
	pg_assert_same('', $answer['rows']['15']['activated_mobile']['html']);

	pg_assert_false(isset(pg_theme_quick_saved(pg_theme_list_cells(pg_test_theme_quick_row(), $fields), 'name')['rows']));
}

// The screen and the list share the theme screen's check and save.
function test_theme_quick_shares_the_screen_save()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_file.php');
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/theme_list_edit.php');
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_themes.php');

	foreach (array($screen, $list) as $source) {
		pg_assert_contains('pg_theme_file_form_validate(', $source);
		pg_assert_contains('pg_theme_file_form_save(', $source);
	}

	pg_assert_contains("return (int) \$user['role'] <= 1;", $view, 'the theme screen rights');
	pg_assert_true(strpos($view, 'pg_list_edit_request(') < strpos($view, "validate_area_access(\$user, 'designer');"), 'answered in JSON before the screen');
}

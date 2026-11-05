<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Design Files list's quick edit
 * (includes/fn/design_file_list_edit.php): every one-line value the design
 * file screen changes and the list shows is a field, the value each field
 * opens with, what the screen's check and save get for a change, the
 * screen's name check and the answer's cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_file_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $design_file_edit_test_name => $design_file_edit_test_value) {
	if (!defined($design_file_edit_test_name)) {
		define($design_file_edit_test_name, $design_file_edit_test_value);
	}
}

function pg_test_design_file_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '21', 'name' => 'site.css', 'folder' => '3', 'folder_name' => 'Design', 'folder_archived' => '0',
		'description' => "Main\nstyles", 'type' => 'css', 'size' => '2048', 'optimized' => '0', 'theme' => '1',
		'user_username' => 'admin', 'timestamp' => '1700000000', 'design' => '1',
	), $over);
}

function pg_test_design_file_quick_fields()
{
	return pg_design_file_quick_edit_columns(array(3 => 'Design', 4 => "\u{00A0}\u{00A0}Fonts"), array(3 => 'public'));
}

function pg_test_design_file_quick_cells(array $over = array())
{
	return pg_design_file_list_cells(pg_test_design_file_quick_row($over), pg_test_design_file_quick_fields(), false, '/pinegrap/view_design_files.php');
}

// Every one-line value the design file screen changes and the list shows
// is an editable column with a cell field; the rest follow the file or
// are several lines. A new column fails here until it is one or the other.
function test_design_file_quick_every_screen_value_is_a_field()
{
	$text = array('thumbnail', 'type', 'size', 'optimized', 'description', 'last_modified', 'actions');
	$fields = pg_test_design_file_quick_fields();
	$built = pg_test_design_file_quick_cells();

	foreach (pg_design_file_list_columns(true) as $key => $column) {
		pg_assert_true(isset($built['cells'][$key]), $key . ' has a cell');

		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($built['cells'][$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $built['cells'][$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_design_file_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_design_file_quick_cells()
{
	$built = pg_test_design_file_quick_cells();
	$cells = $built['cells'];

	pg_assert_same('site.css', $cells['name']['attributes']['data-pg-edit-value'], 'the name opens as stored');
	pg_assert_same('3', $cells['folder']['attributes']['data-pg-edit-value'], 'the folder opens as its id');
	pg_assert_contains('edit_design_file.php?id=21&amp;send_to=%2Fpinegrap%2Fview_design_files.php', $cells['name']['html'], 'the name links to the screen and back');
	pg_assert_same('css', $cells['type']['html'], 'the type is the extension');
	pg_assert_contains('download="site.css"', $cells['actions']['html'], 'the download names the file');
	pg_assert_false($built['image'], 'a CSS file is no image');

	$bin = pg_test_design_file_quick_cells(array('folder' => '9'));
	pg_assert_false(isset($bin['cells']['folder']['attributes']['data-pg-edit']), 'a folder the picker does not offer stays text');
	pg_assert_same('name', $bin['cells']['name']['attributes']['data-pg-edit'], 'its name still changes');
}

function test_design_file_quick_post_changes_one_value()
{
	$file = pg_test_design_file_quick_row();

	$post = pg_design_file_quick_post($file, 'folder', '4');
	pg_assert_same('4', $post['folder'], 'the changed folder');
	pg_assert_same('site.css', $post['name'], 'the stored name');
	pg_assert_same("Main\nstyles", $post['description'], 'the description is written back unchanged');
	pg_assert_same('1', $post['design'], 'the file stays a design file');
	pg_assert_same('1', $post['theme'], 'the Theme switch as stored');

	pg_assert_same('', pg_design_file_quick_post(pg_test_design_file_quick_row(array('theme' => '0')), 'name', 'x.css')['theme'], 'an unchecked Theme switch posts nothing');
	pg_assert_same('new.css', pg_design_file_quick_post($file, 'name', '  new.css ')['name'], 'the name is trimmed');
	pg_assert_same("Main\nstyles", pg_design_file_quick_post($file, 'description', 'x')['description'], 'the description is not a quick edit field');
}

function test_design_file_form_refusal()
{
	$free = function ($name, $id) {
		return $name !== 'taken.css';
	};

	pg_assert_contains('enter a name', pg_design_file_form_refusal('', 'site.css', 21, $free), 'a blank name');
	pg_assert_contains('already exists', pg_design_file_form_refusal('taken.css', 'site.css', 21, $free), 'a taken name');
	pg_assert_true(pg_design_file_form_refusal('run.php', 'site.css', 21, $free) !== '', 'a name the server would run');
	pg_assert_same('', pg_design_file_form_refusal('site.css', 'site.css', 21, $free), 'the unchanged name');
	pg_assert_same('', pg_design_file_form_refusal('other.css', 'site.css', 21, $free), 'a free name');
}

function test_design_file_quick_saved_answer()
{
	$built = pg_test_design_file_quick_cells();
	$answer = pg_design_file_quick_saved($built['cells'], 'name');

	pg_assert_true($answer['ok'], 'a success');
	pg_assert_same('site.css', $answer['value'], 'the value the field opens with next');
	pg_assert_false(isset($answer['cells']['name']), 'the edited cell is the answer itself');
	pg_assert_same('3', $answer['cells']['folder']['value'], 'the folder keeps its value');
	pg_assert_true(isset($answer['cells']['type'], $answer['cells']['actions'], $answer['cells']['last_modified']), 'a rename redraws the type, the links and Last Modified');
}

function test_design_file_list_screen_uses_the_shared_code()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_design_files.php');
	$request = strpos($source, 'pg_list_edit_requested()');

	pg_assert_true($request !== false, 'the list answers quick edits');
	pg_assert_true($request < strpos($source, "validate_area_access(\$user, 'designer')"), 'before the access check, so a refusal is JSON');
	pg_assert_contains("(int) \$user['role'] <= 1", $source, 'the design file screen\'s rights');
	pg_assert_contains("'edit' => array('url' => 'view_design_files.php'", $source, 'the table has quick edit');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/design_file_list_edit.php');
	pg_assert_contains('pg_design_file_form_refusal(', $list, 'the screen\'s name check');
	pg_assert_contains('pg_design_file_form_save(', $list, 'the screen\'s save');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_design_file.php');
	pg_assert_contains('pg_design_file_form_refusal($name_posted, $stored_name, $id)', $screen, 'the screen checks with the shared code');
	pg_assert_contains("pg_design_file_form_save(\$id, \$stored_name, \$name, \$_POST, (int) \$user['id'])", $screen, 'and saves with it');
}

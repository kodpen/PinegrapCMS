<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the custom page style form shared by the create and the edit
 * screens (includes/forms/custom_style_form.php): a new style's defaults,
 * the selected options, the cards, the saved columns, and that both
 * screens use the shared form.
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

function test_custom_style_form_defaults()
{
	$defaults = pg_custom_style_form_defaults();

	pg_assert_same('', $defaults['style_name'], 'a new style has no name');
	pg_assert_contains('<system></system>', $defaults['style_code'], 'the skeleton holds the system region');
	pg_assert_same('a', $defaults['collection'], 'collection A');
	pg_assert_same('', $defaults['layout_type'], 'no layout override');
	pg_assert_same('bottom_left', $defaults['social_networking_position'], 'social networking bottom left');
}

function test_custom_style_form_social_networking_position()
{
	$select = pg_custom_style_form_social_networking_position('top_right');

	pg_assert_contains('<option value="top_right" selected="selected">', $select, 'the stored position is selected');
	pg_assert_same(1, substr_count($select, 'selected="selected"'), 'only one position is selected');
	pg_assert_same(5, substr_count($select, '<option '), 'five positions');
	pg_assert_same(0, substr_count(pg_custom_style_form_social_networking_position(''), 'selected="selected"'), 'nothing selected without a position');
}

function test_custom_style_form_cards()
{
	$new = pg_custom_style_form_cards(pg_custom_style_form_defaults(), true);
	pg_assert_contains('value="a" checked="checked" class="radio"', $new, 'collection A on create');
	pg_assert_contains('<option value="bottom_left" selected="selected">', $new, 'bottom left on create');
	pg_assert_contains('&lt;system&gt;&lt;/system&gt;', $new, 'the skeleton is escaped into the editor');

	$stored = array('style_name' => 'Home "wide"', 'style_code' => '<p>', 'collection' => 'b', 'layout_type' => 'custom', 'social_networking_position' => 'disabled');
	$edit = pg_custom_style_form_cards($stored, false);
	pg_assert_contains('value="Home &quot;wide&quot;" name="name"', $edit, 'the stored name is escaped');
	pg_assert_contains('value="b" checked="checked" class="radio"', $edit, 'collection B');
	pg_assert_false(strpos($edit, 'value="a" checked') !== false, 'collection A not checked');
	pg_assert_contains('<option value="custom" selected="selected">', $edit, 'the layout override');
	pg_assert_false(strpos($edit, 'social_networking_position') !== false, 'no position list while social networking is off');
}

function test_custom_style_form_values()
{
	$post = array('name' => '  Home  ', 'code' => '<html>', 'social_networking_position' => 'top_left', 'collection' => 'b', 'layout_type' => 'system', 'id' => '4');

	pg_assert_same(
		array('style_name' => 'Home', 'style_code' => '<html>', 'social_networking_position' => 'top_left', 'collection' => 'b', 'layout_type' => 'system'),
		pg_custom_style_form_values($post, true),
		'the trimmed name and the columns in save order'
	);
	pg_assert_same(
		array('style_name' => 'Home', 'style_code' => '<html>', 'collection' => 'b', 'layout_type' => 'system'),
		pg_custom_style_form_values($post, false),
		'no position while social networking is off'
	);
}

function test_custom_style_form_screens_use_shared_form()
{
	foreach (array('add_custom_style.php', 'edit_custom_style.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/custom_style_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_custom_style_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_custom_style_form_values($_POST, SOCIAL_NETWORKING == TRUE)', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' keeps the designer gate');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Override Layout Type') !== false, $file . ' keeps no copy of the cards');
	}
}

function test_custom_style_screens_refuse_a_blank_name()
{
	$queries = array('add_custom_style.php' => 'INSERT INTO style', 'edit_custom_style.php' => 'pg_custom_style_form_update(');

	foreach ($queries as $file => $query) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$check = strpos($source, "if (\$name === '') {");
		pg_assert_true($check !== false, $file . ' refuses a name of only spaces');
		pg_assert_true($check < strpos($source, $query), $file . ' refuses it before the save');
		pg_assert_true(strpos($source, "lang('Name is required.')", $check) !== false, $file . ' says the name is required');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_custom_style.php');
	pg_assert_contains('<button type="submit" name="submit_delete" value="Delete" formnovalidate', $edit, 'Delete is not blocked by an empty required box');
	pg_assert_true(strpos($edit, "if (\$name === '') {") > strpos($edit, 'DELETE FROM style WHERE style_id = ?'), 'Delete is never refused for a blank name');
}

function test_custom_style_form_values_keep_to_the_lists()
{
	$post = array('name' => 'Home', 'code' => '', 'social_networking_position' => 'middle', 'collection' => 'z', 'layout_type' => 'wide');
	$values = pg_custom_style_form_values($post, true);

	pg_assert_same('a', $values['collection'], 'an unknown collection falls back to A');
	pg_assert_same('', $values['layout_type'], 'an unknown layout type is no override');
	pg_assert_same('bottom_left', $values['social_networking_position'], 'an unknown position is bottom left');

	$values = pg_custom_style_form_values(array('name' => array('x'), 'code' => array('y'), 'collection' => array('b')), true);
	pg_assert_same('', $values['style_name'], 'a name sent as a list is no name');
	pg_assert_same('', $values['style_code'], 'code sent as a list is no code');
	pg_assert_same('a', $values['collection'], 'a missing or listed collection falls back to A');
	pg_assert_same('bottom_left', $values['social_networking_position'], 'a missing position is bottom left');

	foreach (array('top_left', 'top_right', 'bottom_left', 'bottom_right', 'disabled') as $position) {
		pg_assert_same($position, pg_custom_style_form_choice('social_networking_position', $position), $position . ' is kept');
	}
	pg_assert_same('b', pg_custom_style_form_choice('collection', 'b'), 'B is kept');
	pg_assert_same('custom', pg_custom_style_form_choice('layout_type', 'custom'), 'custom is kept');
}

function test_custom_style_form_cards_blank_collection_and_layout_label()
{
	$stored = array('style_name' => 'x', 'style_code' => '', 'collection' => '', 'layout_type' => '', 'social_networking_position' => 'bottom_left');
	$cards = pg_custom_style_form_cards($stored, false);

	pg_assert_contains('value="a" checked="checked" class="radio"', $cards, 'an empty collection shows A');
	pg_assert_false(strpos($cards, 'value="b" checked') !== false, 'an empty collection does not show B');
	pg_assert_contains('<option value="" selected="selected">' . lang('No override (the page type layout)') . '</option>', $cards, 'the no-override option has a label');
	pg_assert_false(strpos($cards, '<option value=""></option>') !== false, 'no blank option');
}

function test_custom_style_form_name_error_under_the_input()
{
	$cards = pg_custom_style_form_cards(pg_custom_style_form_defaults(), false, 'Taken <b>');

	pg_assert_contains('is-invalid" aria-invalid="true" aria-describedby="name_error"', $cards, 'the input is marked invalid');
	pg_assert_contains('<div id="name_error" class="invalid-feedback d-block">Taken &lt;b&gt;</div>', $cards, 'the escaped text under the input');
	pg_assert_false(strpos(pg_custom_style_form_cards(pg_custom_style_form_defaults(), false), 'name_error') !== false, 'no error without a refusal');
}

function test_style_layout_type_label()
{
	pg_assert_same(lang('System'), pg_style_layout_type_label('system'), 'system');
	pg_assert_same(lang('Custom'), pg_style_layout_type_label('custom'), 'custom');
	pg_assert_same('', pg_style_layout_type_label(''), 'no override');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/style_list_edit.php');
	pg_assert_contains("h(pg_style_layout_type_label(\$style['layout_type']))", $list, 'the list column is translated');
	pg_assert_false(strpos($list, "ucwords(\$style['layout_type'])") !== false, 'no raw enum in the list');
}

function test_style_edit_screen()
{
	pg_assert_same('custom', pg_style_edit_screen('custom', ''), 'a custom style');
	pg_assert_same('system', pg_style_edit_screen('system', ''), 'a system style');
	pg_assert_same('system', pg_style_edit_screen('system', 'visual_designer'), 'a visual design');
	pg_assert_same('system', pg_style_edit_screen('custom', 'visual_designer'), 'a visual design whatever its type');
	pg_assert_same('', pg_style_edit_screen('', ''), 'neither');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_custom_style.php');
	$guard = strpos($edit, "if (\$edit_screen !== 'custom') {");
	pg_assert_true($guard !== false, 'the edit screen checks the style kind');
	pg_assert_true($guard < strpos($edit, 'if (!$_POST) {'), 'before the form or a save');
	pg_assert_contains("/edit_system_style.php?id=' . \$id", $edit, 'a design goes to its own screen');
}

function test_style_name_taken()
{
	$calls = array();
	$lookup = function ($sql, $values) use (&$calls) {
		$calls[] = array($sql, $values);
		if (strpos($sql, 'SELECT style_name') === 0) {
			return 'Old';
		}
		return in_array('Kafe', $values, true) ? 1 : 0;
	};

	pg_assert_false(pg_style_name_taken('', null, $lookup), 'an empty name is not checked');
	pg_assert_same(0, count($calls), 'no query for an empty name');
	pg_assert_true(pg_style_name_taken('Kafe', null, $lookup), 'create: a design name is taken');
	pg_assert_false(pg_style_name_taken('New', null, $lookup), 'create: a free name');
	pg_assert_false(pg_style_name_taken('Old', 5, $lookup), 'edit: the unchanged name is not checked');
	pg_assert_true(pg_style_name_taken('Kafe', 5, $lookup), 'edit: a rename to a taken name');
	pg_assert_same(array('Kafe', '5'), $calls[count($calls) - 1][1], 'edit leaves its own id out');
}

function test_custom_style_screens_refuse_a_taken_or_long_name()
{
	pg_assert_contains('can be at most', pg_region_form_name_refusal(str_repeat('ş', 101), false), 'over 100 characters');
	pg_assert_same('', pg_region_form_name_refusal(str_repeat('ş', 100), false), '100 characters fit');

	foreach (array('add_custom_style.php' => 'pg_region_form_name_refusal($name, pg_style_name_taken($name))', 'edit_custom_style.php' => 'pg_custom_style_form_name_refusal($name, $id)') as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($call, $source, $file . ' refuses a taken or long name');
		pg_assert_contains("mark_error('name', \$name_error)", $source, $file . ' shows it under the name');
		pg_assert_contains(', $name_error)', $source, $file . ' draws the error');
	}
}

function test_style_duplicate_name_fits_the_column()
{
	pg_assert_same(100, pg_unique_name_limit('style'), 'style.style_name is varchar(100)');

	$long = 'pg-test uzun ' . str_repeat('ş', 87);
	$copy = pg_bracket_suffix_name($long, pg_unique_name_limit('style'));
	pg_assert_same(100, mb_strlen($copy, 'UTF-8'), 'the copy fits the column');
	pg_assert_true($copy !== $long, 'the copy differs from its source');
}

function test_delete_styles_reads_the_selection_safely()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_styles.php');

	pg_assert_contains("(isset(\$_POST['styles']) && is_array(\$_POST['styles'])) ? \$_POST['styles'] : array()", $source, 'a missing or single value selects nothing');
	pg_assert_false(strpos($source, "if (!\$_POST['styles'])") !== false, 'no unchecked key read');
	pg_assert_false(strpos($source, "foreach (\$_POST['styles']") !== false, 'no loop over the raw post');
}

function test_view_system_style_source_returns_to_the_style()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_system_style_source.php');

	pg_assert_contains("\$edit_style_path = 'edit_system_style.php?id=' . \$id;", $source, 'the style edit screen is the way back');
	pg_assert_contains("pg_safe_back_url(\$liveform->get_field_value('send_to'), \$edit_style_path)", $source, 'Cancel goes back to the style');
	pg_assert_contains("PATH . SOFTWARE_DIRECTORY . '/' . \$edit_style_path", $source, 'Save & Return goes back to the style');
	pg_assert_false(strpos($source, "'view_styles.php'") !== false, 'not to the list without designs');
}

function test_custom_style_form_wide_layout()
{
	$new = pg_custom_style_form_cards(pg_custom_style_form_defaults(), true);
	$form = strpos($new, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status = strpos($new, 'col-12 lg:col-4 2xl:col-3 order-2');
	pg_assert_true($form !== false && $status !== false, 'a form column and a status column');
	pg_assert_true($form < $status, 'the status column comes after the form column');
	pg_assert_true(strpos($new, 'id="code"') < $status, 'the code editor is in the form column');
	pg_assert_true(strpos($new, 'id="layout_type"') > $status, 'the options are in the status column');
	pg_assert_contains('position-sticky" style="top:3.3rem;"', $new, 'the status box sticks under the top bar');
	pg_assert_false(strpos($new, lang('Summary')) !== false, 'no Summary on create');

	$edit = pg_custom_style_form_cards(pg_custom_style_form_defaults(), false, '', pg_custom_style_form_edit_status_rows(2, 7, 'today <b>x</b>'));
	pg_assert_contains(lang('Summary'), $edit, 'a Summary on edit');
	pg_assert_contains('<dt class="fw-normal">' . h(lang('Folders')) . '</dt>', $edit, 'the folders row');
	pg_assert_contains('>7</dd>', $edit, 'the page count');
	pg_assert_contains('today <b>x</b></dd>', $edit, 'the last change as given');

	$layout = pg_custom_style_form_layout('<!--cards-->', '<button>Go</button>');
	pg_assert_true(strpos($layout, '<!--cards-->') < strpos($layout, 'col-12 order-3 position-sticky'), 'the save bar after the columns');
	pg_assert_contains('<button>Go</button>', $layout, 'the buttons in the bar');

	foreach (array('add_custom_style.php', 'edit_custom_style.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_custom_style_form_layout(', $source, $file . ' uses the shared layout');
	}
	$edit_source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_custom_style.php');
	pg_assert_contains('pg_custom_style_form_edit_status_rows($folders_using_style, $pages_using_style,', $edit_source, 'the edit screen fills the Summary');
}

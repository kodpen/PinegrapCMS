<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the folder form shared by the create and the edit screens
 * (includes/forms/folder_form.php): both modes render, the values a save
 * writes, and that both screens use the shared form while keeping their
 * own queries.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/folder_form.php');

function test_folder_form_create_mode_renders()
{
	$html = pg_folder_form_markup(array(
		'name_field' => '<input id="name" name="name" data-liveform="1">',
		'folder_options' => '<option value="1">Root</option>',
		'access_options' => '<option value="public">Public</option>',
		'token_field' => '<input type="hidden" name="token" value="t">',
	));

	pg_assert_contains(lang('New Folder Information'), $html, 'create card title');
	pg_assert_contains('data-liveform="1"', $html, 'the liveform name field is used');
	pg_assert_contains('<select class="form-control" id="folder" name="folder"><option value="1">Root</option></select>', $html, 'parent row shown');
	pg_assert_contains('name="token" value="t"', $html, 'token printed');
	pg_assert_contains('id="send_to"', $html, 'send_to printed');
	pg_assert_contains(lang('Default') . ' (' . lang('inherit') . ')', $html, 'a user sees the inherited style label');
	pg_assert_false(strpos($html, 'checked="checked"') !== false, 'archive off on create');
	pg_assert_false(strpos($html, 'id="style"') !== false, 'no style select without one passed in');
}

function test_folder_form_edit_mode_renders()
{
	$html = pg_folder_form_markup(array(
		'edit' => true,
		'name' => 'News & "Blog"',
		'order' => '5',
		'archived' => true,
		'show_parent' => false,
		'style_field' => '<select id="style" name="style"></select>',
		'mobile_style_field' => '<select id="mobile_style_id" name="mobile_style_id"></select>',
	));

	pg_assert_contains(lang('Folder Information'), $html, 'edit card title');
	pg_assert_false(strpos($html, lang('New Folder Information')) !== false, 'no create title on edit');
	pg_assert_contains('value="News &amp; &quot;Blog&quot;"', $html, 'stored name escaped in the plain input');
	pg_assert_contains('value="5" type="text" name="order"', $html, 'stored order');
	pg_assert_contains('value="1" checked="checked"', $html, 'archive on');
	pg_assert_false(strpos($html, 'id="folder"') !== false, 'root folder hides the parent row');
	pg_assert_contains('<select id="style" name="style">', $html, 'style select');
	pg_assert_contains('<select id="mobile_style_id" name="mobile_style_id">', $html, 'mobile style select');
}

function test_folder_form_values_read_the_posted_fields()
{
	pg_assert_same(
		array('News', '3', 'private', '1', array('4', '0')),
		pg_folder_form_values(array('name' => ' News ', 'order' => '3', 'access_control_type' => 'private', 'folder_archived' => '1', 'style' => '4', 'mobile_style_id' => '0'), 1),
		'all fields for a manager or above');
	pg_assert_same(
		array('News', '', '', '', array()),
		pg_folder_form_values(array('name' => 'News', 'style' => '4'), 3),
		'a user sets no styles');
	pg_assert_same(array('', '', '', '', array('', '')), pg_folder_form_values(array('name' => array('x'), 'order' => array(1)), 2), 'arrays and missing fields are empty');
}

function test_folder_form_screens_use_shared_form()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/folder_form.php');
	pg_assert_false(strpos($form, 'db_q(') !== false, 'the form runs no query');

	$screens = array(
		'add_folder.php' => array("'name_field' => \$liveform_add_folder->output_field(", 'INSERT INTO folder'),
		'edit_folder.php' => array("'exclude' => \$folder_id", 'UPDATE folder'),
	);

	foreach ($screens as $file => $expect) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/folder_form.php');", $source, $file . ' loads the form');
		pg_assert_contains('pg_folder_form(array(', $source, $file . ' draws the shared form');
		pg_assert_contains('= pg_folder_form_values($_POST, $user[\'role\']);', $source, $file . ' reads the shared values');
		pg_assert_contains($expect[0], $source, $file . ' passes its own input');
		pg_assert_contains($expect[1], $source, $file . ' keeps its own query');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Folder Access Control Type') !== false, $file . ' keeps no copy of the form');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_folder.php');
	pg_assert_contains('duplicate_folder.php', $edit, 'edit keeps Duplicate');
	pg_assert_contains('function change_level(', $edit, 'edit keeps the level change');
	pg_assert_contains('A folder cannot be moved into itself', $edit, 'edit keeps the cycle walk');
}

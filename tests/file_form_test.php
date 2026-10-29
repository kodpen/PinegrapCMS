<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file upload and edit screens (add_file.php, edit_file.php)
 * drawing their File Access Control and Description cards through the
 * design file cards (includes/forms/design_file_form.php): the unchecked
 * Design File switch, the optional rows, and that both screens call the
 * cards while keeping the dropzone, rename, delete and their own queries.
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

function test_file_form_design_switch_checked_or_not()
{
	$html = pg_design_file_form_access_card('', array('design' => true, 'design_checked' => false));
	pg_assert_contains('id="design" name="design" value="1">', $html, 'unchecked Design File switch for a plain file');

	$html = pg_design_file_form_access_card('', array('design' => true, 'design_checked' => true));
	pg_assert_contains('id="design" name="design" value="1" checked="checked">', $html, 'checked Design File switch for a design file');

	$html = pg_design_file_form_access_card('', array('design' => false, 'design_checked' => true));
	pg_assert_false(strpos($html, 'id="design"') !== false, 'no Design File switch below the designer role');
}

function test_file_form_screens_use_the_cards()
{
	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_file.php');
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_file.php');

	foreach (array('add_file.php' => $add, 'edit_file.php' => $edit) as $screen => $source) {
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');", $source, $screen . ' loads the cards');
		pg_assert_false(strpos($source, 'id="description"') !== false, $screen . ' draws no description box itself');
		pg_assert_false(strpos($source, 'id="folder"') !== false, $screen . ' draws no folder select itself');
		pg_assert_false(strpos($source, 'id="design"') !== false, $screen . ' draws no Design File switch itself');
	}

	pg_assert_contains("pg_design_file_form_access_card(select_folder(\$folder_id, 0), array('design' => (\$user['role'] <= 1), 'design_checked' => false))", $add, 'upload folder card');
	pg_assert_contains('pg_design_file_form_description_card()', $add, 'upload description card');
	pg_assert_contains('class="dropzone"', $add, 'dropzone stays in the upload screen');

	pg_assert_contains("pg_design_file_form_access_card(select_folder(\$row['folder']), array('design' => (\$user['role'] <= 1), 'design_checked' => (\$design == 1)))", $edit, 'edit folder card');
	pg_assert_contains("pg_design_file_form_description_card(\$row['description'])", $edit, 'edit description card');
	pg_assert_contains('id="name" class="form-control', $edit, 'rename stays in the edit screen');
	pg_assert_contains('db_q("DELETE FROM files WHERE id = ?"', $edit, 'delete stays in the edit screen');
}

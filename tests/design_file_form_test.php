<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design file cards shared by the upload and the edit screens
 * (includes/forms/design_file_form.php): both cards render, the optional
 * Design File and Theme rows, and that both screens use the cards while
 * keeping their own queries and guards.
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

function test_design_file_form_access_card_upload()
{
	$html = pg_design_file_form_access_card('<option value="3">Root</option>');

	pg_assert_contains(lang('File Access Control'), $html, 'card title');
	pg_assert_contains('<select class="form-control" id="folder" name="folder"><option value="3">Root</option></select>', $html, 'folder select with the passed options');
	pg_assert_false(strpos($html, 'id="design"') !== false, 'no Design File switch on upload');
	pg_assert_false(strpos($html, 'id="theme"') !== false, 'no Theme switch on upload');
}

function test_design_file_form_access_card_optional_rows()
{
	$html = pg_design_file_form_access_card('', array('design' => true));

	pg_assert_contains('id="design" name="design" value="1" checked="checked"', $html, 'Design File switch on');
	pg_assert_false(strpos($html, 'id="theme"') !== false, 'no Theme switch without a CSS file');

	$html = pg_design_file_form_access_card('', array('design' => true, 'theme' => array('checked' => true, 'disabled' => false)));
	pg_assert_contains('id="theme" name="theme" value="1" checked="checked">', $html, 'Theme switch on');

	$html = pg_design_file_form_access_card('', array('theme' => array('checked' => false, 'disabled' => true)));
	pg_assert_contains('id="theme" name="theme" value="1" disabled="disabled">', $html, 'system theme locks the switch');

	$html = pg_design_file_form_access_card('', array('extra_rows' => '<div id="extra"></div>'));
	pg_assert_contains('<div id="extra"></div>', $html, 'extra rows printed');
}

function test_design_file_form_description_card()
{
	$html = pg_design_file_form_description_card('', true);
	pg_assert_contains('name="description" rows="1"></textarea>', $html, 'empty one-row box on upload');

	$html = pg_design_file_form_description_card('A <b>"logo"</b>');
	pg_assert_contains('name="description" style="min-height:85px;">A &lt;b&gt;&quot;logo&quot;&lt;/b&gt;</textarea>', $html, 'stored description escaped');
}

function test_design_file_form_screens_use_the_cards()
{
	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_design_file.php');
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_design_file.php');

	foreach (array('add_design_file.php' => $add, 'edit_design_file.php' => $edit) as $screen => $source) {
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');", $source, $screen . ' loads the cards');
		pg_assert_false(strpos($source, 'id="description"') !== false, $screen . ' draws no description box itself');
	}

	pg_assert_contains("pg_design_file_form_access_card(select_folder(isset(\$_GET['id']) ? \$_GET['id'] : '', 0))", $add, 'upload folder card');
	pg_assert_contains("pg_design_file_form_description_card('', true)", $add, 'upload description card');
	pg_assert_contains('class="dropzone"', $add, 'dropzone stays in the upload screen');

	pg_assert_contains("pg_design_file_form_access_card(select_folder(\$file_folder), array('design' => true, 'theme' => \$theme_row))", $edit, 'edit folder card');
	pg_assert_contains('pg_design_file_form_description_card($file_description)', $edit, 'edit description card');
	pg_assert_contains('name="delete" value="Delete"', $edit, 'delete stays in the edit screen');
}

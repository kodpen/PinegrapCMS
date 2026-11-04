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

function test_design_file_form_layout_columns()
{
	$html = pg_design_file_form_layout('<i id="f"></i>', '<i id="s"></i>', '<b id="b"></b>');

	$form = strpos($html, 'class="col-12 lg:col-8 2xl:col-9 order-1"');
	$status = strpos($html, 'class="col-12 lg:col-4 2xl:col-3 order-2"');
	$bar = strpos($html, 'class="col-12 order-3 position-sticky"');

	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar');
	pg_assert_true($form < strpos($html, 'id="f"') && strpos($html, 'id="f"') < $status, 'form cards in the form column');
	pg_assert_true($status < strpos($html, 'id="s"') && strpos($html, 'id="s"') < $bar, 'status cards in the status column, written after the form column');
	pg_assert_contains('class="position-sticky" style="top:3.3rem;"', $html, 'the status box sticks below the top bar');
	pg_assert_true(strpos($html, 'id="b"') > $bar, 'buttons in the save bar');
}

function test_design_file_form_summary_card()
{
	$html = pg_design_file_form_summary_card('<img id="t">', array('File Size' => '2 KB', 'Access' => '<span>Public</span>'));

	pg_assert_contains(lang('Summary'), $html, 'card title');
	pg_assert_true(strpos($html, 'id="t"') < strpos($html, '<dl'), 'top markup above the rows');
	pg_assert_contains('<dd class="mb-0 fw-bold text-end text-break"><span>Public</span></dd>', $html, 'value printed as HTML');
	pg_assert_true(substr_count($html, 'border-bottom') === 1, 'a line under every row but the last');
}

function test_design_file_form_screens_use_the_layout()
{
	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_design_file.php');
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_design_file.php');

	foreach (array('add_design_file.php' => $add, 'edit_design_file.php' => $edit) as $screen => $source) {
		pg_assert_contains('pg_design_file_form_layout(', $source, $screen . ' draws the wide layout');
		pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, $screen . ' keeps no save bar of its own');
	}

	pg_assert_contains('pg_design_file_form_summary_card($output_thumbnail, $summary_rows)', $edit, 'edit Summary with the thumbnail');
	pg_assert_contains("\$summary_rows[lang('Access')]", $edit, 'edit Summary names the access');
}

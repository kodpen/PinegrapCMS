<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the names of fields on the content edit screens: every
 * <label for> points at a field the screen has, card headings are not labels,
 * code fields carry a name, and the code editor hands that name to its
 * content element.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function content_edit_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_content_edit_names_card_headings_are_not_labels()
{
	$paths = array(
		'add_page.php',
		'edit_page.php',
		'includes/forms/page_form.php',
		'includes/forms/short_link_form.php',
		'edit_form_item_view.php',
		'edit_form_list_view.php',
	);

	foreach ($paths as $path) {
		$source = content_edit_names_source($path);
		pg_assert_false((bool) preg_match('/<label[^>]*class="card-header/', $source), $path . ': a card heading is a <label>');
	}
}

function test_content_edit_names_label_targets()
{
	$pins = array(
		'includes/forms/page_form.php' => array('<label for="page_type" class="form-label'),
		'includes/forms/short_link_form.php' => array('<label for="destination_type" class="form-label'),
		'includes/forms/menu_form.php' => array('<label for="second_level_popup_position" class="form-label">\' . lang(\'Then Expand Menu\')'),
		'includes/forms/login_region_form.php' => array(
			'<label for="not_logged_in_header" class="form-label">',
			'<label for="not_logged_in_footer" class="form-label">',
			'<label for="logged_in_header" class="form-label">',
			'<label for="logged_in_footer" class="form-label">',
		),
		'includes/forms/custom_style_form.php' => array('<label for="code" class="form-label">\' . lang(\'HTML Page with embedded Tags\')'),
		'add_page.php' => array('<label for="name" class="form-label">\' . lang(\'Page Name\')'),
		'edit_page.php' => array('<label for="name" class="form-label">\' . lang(\'Page Name\')'),
	);

	foreach ($pins as $path => $needles) {
		$source = content_edit_names_source($path);
		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}

	pg_assert_false(strpos(content_edit_names_source('includes/forms/login_region_form.php'), '<label for="name" class="form-label">\' . lang(\'Header\')') !== false, 'login region Header label points at #name');
	pg_assert_false(strpos(content_edit_names_source('includes/forms/menu_form.php'), 'for=""') !== false, 'menu form has a label for nothing');
}

function test_content_edit_names_one_label_per_name_field()
{
	// The required marker goes to the first label[for=name]: the field's own label.
	foreach (array('add_page.php', 'edit_page.php', 'includes/forms/short_link_form.php') as $path) {
		$source = content_edit_names_source($path);
		pg_assert_same(1, substr_count($source, 'for="name"'), $path . ': labels on #name');
		pg_assert_false((bool) preg_match('/<label[^>]*material-icons[^>]*>public</', $source), $path . ': the public ligature is a label');
		// Every one shows its address pattern as form text (field_hint_visible_test.php).
		pg_assert_true((bool) preg_match('/<span class="input-group-text material-icons" aria-hidden="true">public</', $source), $path . ': the ligature is hidden');
	}
}

function test_content_edit_names_menu_effect_group()
{
	$source = content_edit_names_source('includes/forms/menu_form.php');
	pg_assert_contains('role="radiogroup" aria-labelledby="effect_label"', $source, 'Submenu Effect group');
	pg_assert_contains('id="effect_label"', $source, 'Submenu Effect heading');
	pg_assert_false(strpos($source, 'alt="img"') !== false, 'menu effect pictures read as img');
	// The opaque light pictures are inverted in the dark theme and drawn at
	// their own width.
	pg_assert_contains('html[data-bs-theme="dark"] .pg-menu-effect-picture { filter: invert(1); }', $source, 'menu effect pictures in the dark theme');
	pg_assert_same(3, substr_count($source, 'class="pg-menu-effect-picture"'), 'every menu effect picture carries the class');
	pg_assert_contains('menu_effect_popup.png" width="71" height="75" alt=""', $source, 'popup picture size');
	pg_assert_contains('menu_effect_accordion.png" width="56" height="75" alt=""', $source, 'accordion picture size');
	pg_assert_contains('menu_effect_none.png" width="56" height="75" alt=""', $source, 'none picture size');
}

function test_content_edit_names_code_fields_named()
{
	$pins = array(
		'edit_form_item_view.php' => array('<textarea id="layout" name="layout" aria-label="\' . h(lang(\'Layout\')) . \'"'),
		'edit_form_list_view.php' => array(
			'<textarea id="header_content" name="header_content" aria-label="\' . h(lang(\'Header\')) . \'"',
			'<textarea id="layout" name="layout" aria-label="\' . h(lang(\'Layout\')) . \'"',
			'<textarea id="footer_content" name="footer_content" aria-label="\' . h(lang(\'Footer\')) . \'"',
		),
		'includes/forms/field_form.php' => array('<textarea name="options" id="options" aria-label="\' . h(lang(\'Value Options\')) . \'"'),
		'edit_files.php' => array('<select class="form-control" id="optimize_mode" aria-label="\' . h(lang(\'Optimize Images\')) . \'"'),
	);
	foreach ($pins as $path => $needles) {
		$source = content_edit_names_source($path);
		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}
}

function test_content_edit_names_code_editor_takes_the_name()
{
	$source = content_edit_names_source('assets/js/pg_code_editor.js');
	pg_assert_contains("if (name) attrs['aria-label'] = name;", $source, 'attach() names the content element');
	pg_assert_contains('window.PgCM.view.EditorView.contentAttributes.of(attrs)', $source, 'through its content attributes');
	pg_assert_contains('function pgCeTextareaName(textarea)', $source, 'name reader');
	pg_assert_contains("getAttribute('aria-labelledby')", $source, 'aria-labelledby is read');
	pg_assert_contains('textarea.labels', $source, 'the label is read');
}

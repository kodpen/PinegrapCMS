<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layout editor screen (edit_page_layout.php) on the wide
 * edit layout: the code card in the form column, the Summary in the status
 * column, Save and Regenerate in the shared sticky save bar, no bar on a
 * hosted site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_edit_page_layout_uses_the_wide_layout()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page_layout.php');

	$gate = strpos($source, "validate_area_access(\$user, 'designer');");
	$form = strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');");
	pg_assert_true(($gate !== false) && ($form !== false) && ($gate < $form), 'the shared form loads after the designer gate');

	pg_assert_contains('pg_design_file_form_layout(', $source, 'draws the wide layout');
	pg_assert_contains("pg_design_file_form_summary_card('', \$summary_rows)", $source, 'Summary card in the status column');
	pg_assert_false(strpos($source, 'id="button_bar"') !== false, 'the buttons left the toolbar');

	foreach (array('Page', 'Page Type', 'Style', 'Layout File', 'Access') as $label) {
		pg_assert_contains("lang('" . $label . "')", $source, 'Summary row ' . $label);
	}

	// The script finds the buttons by id; a hosted site draws none.
	pg_assert_contains('id="pg_layout_save"', $source, 'keeps the Save button');
	pg_assert_contains('id="pg_layout_regenerate"', $source, 'keeps the Regenerate button');
	pg_assert_contains("if (!\$read_only) {\n    \$output_buttons = '", $source, 'buttons only when editable');
}

function test_design_file_form_layout_without_buttons_draws_no_bar()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');

	$without = pg_design_file_form_layout('<p>form</p>', '<p>status</p>', '');
	pg_assert_contains('lg:col-8 2xl:col-9 order-1', $without, 'form column');
	pg_assert_contains('lg:col-4 2xl:col-3 order-2', $without, 'status column');
	pg_assert_false(strpos($without, 'buttons navigation') !== false, 'no empty save bar');

	$with = pg_design_file_form_layout('<p>form</p>', '<p>status</p>', '<button>Save</button>');
	pg_assert_contains('buttons navigation', $with, 'the save bar with buttons');
	pg_assert_contains('<button>Save</button>', $with, 'the buttons in the bar');
}

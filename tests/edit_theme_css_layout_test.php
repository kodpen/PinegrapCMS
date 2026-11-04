<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Edit CSS screen (edit_theme_css.php) on the wide edit
 * layout: the code card in the form column, the Summary in the status
 * column, the buttons in the shared sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_edit_theme_css_uses_the_wide_layout()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_css.php');

	$gate = strpos($source, "validate_area_access(\$user, 'designer');");
	$form = strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');");
	pg_assert_true(($gate !== false) && ($form !== false) && ($gate < $form), 'the shared form loads after the designer gate');

	pg_assert_contains('pg_design_file_form_layout(', $source, 'draws the wide layout');
	pg_assert_contains("pg_design_file_form_summary_card('', \$summary_rows)", $source, 'Summary card in the status column');
	pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, 'keeps no save bar of its own');

	foreach (array('File Name', 'Folder', 'File Size', 'Theme', 'Access', 'Last Modified') as $label) {
		pg_assert_contains("lang('" . $label . "')", $source, 'Summary row ' . $label);
	}

	// No size for a file missing from the disk: filesize() would warn.
	pg_assert_contains("if (\$output_file_missing === '') {", $source, 'size only for a file on the disk');

	foreach (array('submit_save_and_return', 'submit_save', 'submit_duplicate') as $button) {
		pg_assert_contains('id="' . $button . '"', $source, 'keeps ' . $button);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source scans of the theme screens and Private Label Branding: Edit Theme
 * and Private Label draw cards with a label for every field, Edit Theme's
 * theme tools sit in the toolbar, and the themes list names its marks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_theme_screens_edit_theme_cards_and_labels()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_file.php');

	pg_assert_false(strpos($source, 'class="field"') !== false, 'no legacy table.field');
	pg_assert_false(strpos($source, '<select name="folder">') !== false, 'no hand-written folder select');
	pg_assert_contains("pg_design_file_form_access_card(select_folder(\$file_folder))", $source, 'folder through the shared card');
	pg_assert_contains('<label for="name" class="form-label">', $source, 'name label');
	pg_assert_contains("'name'=>'name', 'id'=>'name'", $source, 'name field has its id');
	pg_assert_contains('<label for="description" class="form-label">', $source, 'description label');
	pg_assert_contains("'name'=>'description', 'id'=>'description'", $source, 'description field has its id');
	pg_assert_contains('<div class="switch">' . "' . \$liveform->output_field(array('type'=>'checkbox', 'name'=>'activated_desktop_theme'", $source, 'desktop switch row');
	pg_assert_contains('<div class="switch">' . "' . \$liveform->output_field(array('type'=>'checkbox', 'name'=>'activated_mobile_theme'", $source, 'mobile switch row');
	pg_assert_contains('<span class="material-icons me-3" aria-hidden="true">save</span>', $source, 'save ligature silent');
	pg_assert_contains('<span class="material-icons me-3" aria-hidden="true">delete</span>', $source, 'delete ligature silent');
}

function test_theme_screens_edit_theme_tools_in_toolbar()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_file.php');

	$bar = strpos($source, 'pg_screen_toolbar(');
	$save_bar = strpos($source, '<button type="submit" name="submit_save"');
	pg_assert_true($bar !== false && $save_bar !== false && $bar < $save_bar, 'toolbar before the save bar');

	$toolbar = substr($source, $bar, strpos($source, '))', $bar) - $bar);
	$save = substr($source, $save_bar, strpos($source, '</form>', $save_bar) - $save_bar);

	foreach (array('$output_edit_theme_button', '$output_export_button', '$output_download_file_button') as $button) {
		pg_assert_contains($button, $toolbar, $button . ' in the toolbar');
		pg_assert_false(strpos($save, $button) !== false, $button . ' not in the save bar');
	}
}

// Both theme file screens use the product_builder layout of the design file
// screens: the fields in the form column, the folder (and on edit the Summary
// and the activation switches) in the sticky status column.
function test_theme_screens_wide_layout()
{
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_file.php');
	$layout = strpos($edit, 'pg_design_file_form_layout(');
	pg_assert_true($layout !== false, 'edit: the shared layout');
	$status = strpos($edit, 'pg_design_file_form_summary_card($output_file_missing, $summary_rows)', $layout);
	pg_assert_true($status !== false, 'edit: the Summary with the missing-file note on top');
	pg_assert_true(strpos($edit, "'name'=>'name', 'id'=>'name'", $layout) < $status, 'edit: the name in the form column');
	pg_assert_true(strpos($edit, "'name'=>'activated_desktop_theme'", $layout) > $status, 'edit: the switches in the status column');
	pg_assert_true(strpos($edit, "\$summary_rows[lang('Access')]") !== false, 'edit: the Summary names the access');

	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_theme_file.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');", $add, 'add: the shared form');
	$layout = strpos($add, 'pg_design_file_form_layout(');
	$access = strpos($add, 'pg_design_file_form_access_card(select_folder($folder_id, 0))');
	pg_assert_true($layout !== false && $access > strpos($add, "'name'=>'description'", $layout), 'add: the folder in the status column after the form cards');
	pg_assert_contains('<button type="submit" id="create_button" name="submit_create"', $add, 'add: the Create button the type script relabels');
}

function test_theme_screens_list_marks_named()
{
	// The list's cells are drawn by includes/fn/theme_list_edit.php.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/theme_list_edit.php');

	pg_assert_false(strpos($source, '<span class="material-icons">task_alt</span>') !== false, 'no bare task_alt');
	pg_assert_same(1, substr_count($source, 'pg_list_table_check_cell('), 'both marks are check cells, drawn by one helper');
	pg_assert_contains('role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>', pg_list_table_check_cell(true)['html'], 'a check cell reads Yes');
	pg_assert_contains('pg_folder_list_name((string) $theme[\'folder_name\'])', $source, 'folder cell');
	pg_assert_contains('<span class="material-icons d-inline" aria-hidden="true">folder</span>', pg_folder_list_name('Docs'), 'folder ligature silent');
}

function test_theme_screens_private_label_cards_and_labels()
{
	// The screen became a card of Site Settings › Appearance.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/appearance.php');

	pg_assert_false(strpos($source, 'show_or_hide_private_label') !== false, 'no inline display script');
	pg_assert_contains('class="collapse-switcher" type="checkbox" id="private_label" name="private_label" data-bs-target="#private_label_fields"', $source, 'switch opens the fields');
	pg_assert_contains('id="private_label_fields"', $source, 'fields section');
	pg_assert_contains('<div class="form-text">\' . lang(\'Leave blank to use the default Pinegrap logo\')', $source, 'logo hint as form text');

	foreach (array('logo_url', 'control_panel_stylesheet_url') as $field) {
		pg_assert_contains('<label for="' . $field . '" class="form-label">', $source, $field . ' label');
		pg_assert_contains('name="' . $field . '" id="' . $field . '"', $source, $field . ' id');
	}

	// The five footer link pairs are drawn in a loop, each with its own id.
	pg_assert_contains("<label for=\"footer_link_' . \$private_label_link . '_label\" class=\"form-label\">", $source, 'footer label label');
	pg_assert_contains("name=\"footer_link_' . \$private_label_link . '_url\" id=\"footer_link_' . \$private_label_link . '_url\"", $source, 'footer url id');
}

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

	$bar = strpos($source, '<nav id="button_bar"');
	$save_bar = strpos($source, 'aria-label="\' . h(lang(\'data edit buttons\'))');
	pg_assert_true($bar !== false && $save_bar !== false && $bar < $save_bar, 'toolbar before the save bar');

	$toolbar = substr($source, $bar, strpos($source, '</nav>', $bar) - $bar);
	$save = substr($source, $save_bar, strpos($source, '</nav>', $save_bar) - $save_bar);

	foreach (array('$output_edit_theme_button', '$output_export_button', '$output_download_file_button') as $button) {
		pg_assert_contains($button, $toolbar, $button . ' in the toolbar');
		pg_assert_false(strpos($save, $button) !== false, $button . ' not in the save bar');
	}
}

function test_theme_screens_list_marks_named()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_themes.php');

	pg_assert_false(strpos($source, '<span class="material-icons">task_alt</span>') !== false, 'no bare task_alt');
	pg_assert_same(2, substr_count($source, '<span class="material-icons" role="img" aria-label="\' . h(lang(\'Yes\')) . \'">task_alt</span>'), 'both marks read Yes');
	pg_assert_contains('<span class="material-icons d-inline" aria-hidden="true">folder</span>', $source, 'folder ligature silent');
}

function test_theme_screens_private_label_cards_and_labels()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/private_label.php');

	pg_assert_false(strpos($source, 'show_or_hide_private_label') !== false, 'no inline display script');
	pg_assert_false(strpos($source, '<table') !== false, 'no table form');
	pg_assert_contains("'class'=>'collapse-switcher'", $source, 'private label switch');
	pg_assert_contains("'data-bs-target'=>'#private_label_fields'", $source, 'switch opens the fields');
	pg_assert_contains('id="private_label_fields"', $source, 'fields section');
	pg_assert_contains('<div class="form-text">\' . lang(\'leave blank to use the default product logo\')', $source, 'logo hint as form text');
	pg_assert_contains('<span class="material-icons me-3" aria-hidden="true">save</span>', $source, 'save ligature silent');

	foreach (array('logo_url', 'control_panel_stylesheet_url') as $field) {
		pg_assert_contains('<label for="' . $field . '" class="form-label">', $source, $field . ' label');
		pg_assert_contains("'name'=>'" . $field . "', 'id'=>'" . $field . "'", $source, $field . ' id');
	}

	// The five footer link pairs are drawn in a loop, each with its own id.
	pg_assert_contains("for (\$footer_link = 1; \$footer_link <= 5; \$footer_link++)", $source, 'footer link loop');
	pg_assert_contains("<label for=\"footer_link_' . \$footer_link . '_label\" class=\"form-label\">", $source, 'footer label label');
	pg_assert_contains("'name'=>'footer_link_' . \$footer_link . '_label', 'id'=>'footer_link_' . \$footer_link . '_label'", $source, 'footer label id');
	pg_assert_contains("<label for=\"footer_link_' . \$footer_link . '_url\" class=\"form-label\">", $source, 'footer url label');
	pg_assert_contains("'name'=>'footer_link_' . \$footer_link . '_url', 'id'=>'footer_link_' . \$footer_link . '_url'", $source, 'footer url id');
}

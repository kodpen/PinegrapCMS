<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form list view's variables: the clickable card on its edit
 * screen (shared with the form item view, without the e-mail links) and the
 * check box yes/no pair the list prints for ^^name^^%%Yes|No%%.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/form_item_view_form.php');

function pg_test_form_list_view_help()
{
	return array(
		'fields' => array(array('token' => '^^consent^^', 'label' => 'Consent', 'type_label' => 'Check Box', 'example' => '^^consent^^%%Yes|No%%')),
		'standard' => array(array('token' => '^^reference_code^^', 'label' => 'Reference Code', 'type_label' => '', 'example' => '')),
		'mail' => array(array('token' => '^^email_preferences_url^^', 'label' => 'E-mail preferences link', 'type_label' => '', 'example' => '')),
		'syntax' => array('conditional' => '[[^^x^^]]', 'date' => '^^d^^%%Y%%', 'checkbox' => '^^c^^%%Yes|No%%'),
	);
}

// The list view's card has the fields and formats, but no e-mail links: only
// the sender's e-mail fills them, a list view always prints them empty.
function test_form_list_view_variables_card_without_mail_links()
{
	$html = pg_form_item_view_form_variables(pg_test_form_list_view_help(), array('mail' => false, 'hint' => 'Hint text'));
	pg_assert_contains('data-pg-insert="^^consent^^"', $html, 'a form field inserts its token');
	pg_assert_contains('data-pg-insert="^^consent^^%%Yes|No%%"', $html, 'a check box gets its pair button');
	pg_assert_contains('data-pg-insert="^^reference_code^^"', $html, 'a system field inserts its token');
	pg_assert_contains('data-pg-insert="^^c^^%%Yes|No%%"', $html, 'the format samples stay');
	pg_assert_contains('Hint text', $html, 'the screen names its own editors');
	pg_assert_false(strpos($html, 'email_preferences_url') !== false, 'no e-mail link');
	pg_assert_false(strpos($html, lang('E-mail links')) !== false, 'no e-mail links group');

	$item_view = pg_form_item_view_form_variables(pg_test_form_list_view_help());
	pg_assert_contains('data-pg-insert="^^email_preferences_url^^"', $item_view, 'the item view keeps its e-mail links');
	pg_assert_contains(lang('Click a variable to insert it at the cursor in the layout.'), $item_view, 'the item view keeps its hint');
}

// One insert script serves both screens; the list view hands it its three
// editors, the item view its one.
function test_form_list_view_variables_insert_script_shared()
{
	$script = pg_form_item_view_form_insert_script('pg_fiv_variables', array('layout', 'header_content', 'footer_content'));
	pg_assert_contains('["layout","header_content","footer_content"]', $script, 'the editors, the default first');
	pg_assert_contains('insertAtCursor', $script, 'inserts at the editor cursor');
	pg_assert_contains('focusin', $script, 'follows the last focused editor');

	foreach (array('edit_form_item_view.php', 'edit_form_list_view.php') as $file) {
		$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_form_item_view_form_insert_script(', $screen, $file . ' prints the shared script');
		pg_assert_contains('pg_form_item_view_form_variables(', $screen, $file . ' draws the shared card');
		pg_assert_false(strpos($screen, 'closest("[data-pg-insert]")') !== false, $file . ' keeps no copy of the script');
	}

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_form_list_view.php');
	pg_assert_false(strpos($list, '<li class="list-group-item py-1" >^^') !== false, 'the plain ^^ lists are gone');
	pg_assert_contains("{{name: \\'", $list, 'the advanced search list stays');
}

// The list view prints a check box pair the way the item view does: one of
// the two texts as written, an unticked box (no form_data row) as empty.
function test_form_list_view_variables_checkbox_pair()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_form_list_view.php');
	pg_assert_contains("\$checkbox_text = (\$field_type == 'check box') ? pg_cf_checkbox_format_text(\$data, \$variable[3] ?? '') : null;", $source, 'the list view reads the pair');
	pg_assert_contains("\$data = \$form['field_' . \$field_id] ?? '';", $source, 'an unticked box reads as empty');

	pg_assert_same('Yes', pg_cf_checkbox_format_text('1', 'Yes|No'), 'ticked');
	pg_assert_same('No', pg_cf_checkbox_format_text(null, 'Yes|No'), 'no row');
	pg_assert_same(null, pg_cf_checkbox_format_text('2026-01-01', 'd.m.Y'), 'a date format is no pair');
}

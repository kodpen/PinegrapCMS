<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact group form shared by the create and the edit
 * screens (includes/forms/contact_group_form.php): the values a save
 * writes, the browser-side required name on create only, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

function test_contact_group_form_values_in_save_order()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('contact_group_form_test');
	$form->set('name', 'Newsletter');
	$form->set('email_subscription', '1');
	$form->set('email_subscription_type', 'closed');
	$form->set('description', "Monthly <b>news</b> & O'Neil");

	pg_assert_same(
		array('Newsletter', '1', 'closed', "Monthly <b>news</b> & O'Neil"),
		pg_contact_group_form_values($form),
		'name, subscription, type, description as raw strings');

	$form = new liveform('contact_group_form_test_empty');
	pg_assert_same(array('', '', '', ''), pg_contact_group_form_values($form), 'unposted fields are empty strings');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_contact_group_form_name_required_on_both_screens()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_contact_group.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

	pg_assert_contains("pg_contact_group_form_cards(\$liveform, array('required' => true))", $add, 'create marks the name required');
	pg_assert_contains("'required' => true,", $edit, 'edit marks the name required too');
	pg_assert_contains('name="submit_delete" value="Delete" formnovalidate', $edit, 'Delete submits with an emptied name');
	pg_assert_contains("'class'=>'form-control') + \$required)", $form, 'required follows the class attribute');
	pg_assert_contains('WHERE (name = ?) AND (id != ?)', $form, 'edit skips its own name');
}

// "Last refreshed" keeps get_relative_time()'s <time> markup; it was escaped
// and shown as text.
function test_contact_group_form_last_run_markup_not_escaped()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

	pg_assert_contains('<span id="pg_audience_last_run">\' . $last_run . \'</span>', $form, 'last run printed as built');
	pg_assert_contains("\$last_run = h(lang('A refresh is under way.'));", $form, 'plain text escaped');
	pg_assert_contains("\$last_run = h(lang('Not refreshed yet.'));", $form, 'plain text escaped');
	pg_assert_false(strpos($form, 'h($last_run)') !== false, 'the time markup is not escaped');
}

function test_contact_group_form_screens_use_shared_form()
{
	foreach (array('add_contact_group.php', 'edit_contact_group.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/contact_group_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_contact_group_form_cards($liveform', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_contact_group_form_layout(', $source, $file . ' uses the shared layout');
		pg_assert_contains('pg_contact_group_form_validate_name($liveform, ', $source, $file . ' checks the name the shared way');
		pg_assert_contains('pg_contact_group_form_values($liveform)', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, 'email_subscription_type_row') !== false, $file . ' keeps no copy of the card');
	}
}

function test_contact_group_form_wide_layout_columns_and_save_bar()
{
	$layout = pg_contact_group_form_layout('<div id="cards"></div>', '<button id="b"></button>');
	pg_assert_contains('<div class="row"><div id="cards"></div>', $layout, 'the cards open the row');
	pg_assert_contains('col-12 order-3 position-sticky', $layout, 'the save bar is sticky under both columns');
	pg_assert_contains('<button id="b"></button>', $layout, 'the buttons go in the bar');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');
	$form_column = strpos($src, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status_column = strpos($src, 'col-12 lg:col-4 2xl:col-3 order-2');
	pg_assert_true($form_column !== false, 'the form column');
	pg_assert_true(($status_column !== false) && ($status_column > $form_column), 'the status column follows the form column in the source');
	pg_assert_contains('style="top:3.3rem;"', $src, 'the status box sticks below the top bar');
	pg_assert_false(strpos($src, '<div class="card my-7" id="pg_audience_card">') !== false, 'the rule card sits in the form column, not in a row of its own');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');
	pg_assert_contains("'cards' => (\$audience_ready ? pg_contact_group_rules_card(", $edit, 'the rule card follows the shared cards');
	pg_assert_contains("'status' => pg_contact_group_form_members_status(\$member_count", $edit, 'the member count sits in the status column');
}

function test_contact_group_form_members_status()
{
	$row = pg_contact_group_form_members_status(12);
	pg_assert_contains('id="contact_group_summary_contacts">12</dd>', $row, 'the count');
	pg_assert_false(strpos($row, 'border-bottom') !== false, 'no rule row, no divider');

	$row = pg_contact_group_form_members_status(3, 'dynamic');
	pg_assert_contains(lang('Rules'), $row, 'a group filled by rules says so');
	pg_assert_contains(lang('By Hand'), pg_contact_group_form_members_status(3, 'static'), 'a static group is filled by hand');
	pg_assert_same(array('on', 'off'), array_keys(pg_contact_group_form_subscription_labels()), 'one label per switch state');
}

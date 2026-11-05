<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Gift Cards list quick edit
 * (includes/fn/gift_card_list_edit.php): the expiration date and the notes
 * are fields; the balance, store credit and notes on more lines stay text; the
 * screen and the list share one save (pg_gift_card_form_write()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform')) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours', 'BASE_CURRENCY_SYMBOL' => '$') as $gift_card_edit_test_name => $gift_card_edit_test_value) {
	if (!defined($gift_card_edit_test_name)) {
		define($gift_card_edit_test_name, $gift_card_edit_test_value);
	}
}

function pg_test_gift_card_quick_row(array $over = array())
{
	return $over + array(
		'id' => 7, 'code' => '4059436642850475', 'amount' => 5000, 'balance' => 1250,
		'expiration_date' => '2026-12-31', 'notes' => 'For <the> fair',
		'from_name' => '', 'recipient_email_address' => '', 'delivery_date' => '0000-00-00',
		'created_username' => 'ayse', 'created_timestamp' => time(),
		'last_modified_username' => 'ayse', 'last_modified_timestamp' => time(),
		'credit_user_id' => 0,
	);
}

function test_gift_card_quick_edit_fields_are_the_date_and_the_notes()
{
	$columns = pg_gift_card_edit_columns();
	$text = array_values(array_diff(array_keys(pg_gift_card_list_columns()), array_keys($columns)));
	sort($text);

	pg_assert_same(array('expiration_date', 'notes'), array_keys($columns), 'the date and the notes');
	// The balance: the list posts only the new value, so it could not keep
	// the screen's guard against a redemption made since the list was drawn.
	pg_assert_same(array('balance', 'code', 'created', 'delivery_date', 'from', 'last_modified', 'original_amount', 'recipient'), $text, 'the balance and what the screen does not change stay text');
	pg_assert_same('', pg_list_edit_parse($columns['expiration_date'], ' ')['value'], 'a blank date for no expiration');
}

function test_gift_card_quick_cells_editable_but_store_credit()
{
	$row = pg_test_gift_card_quick_row();

	pg_assert_false(pg_gift_card_quick_cell_editable($row, 'balance'), 'the balance changes on the screen only');

	foreach (array('expiration_date', 'notes') as $field) {
		pg_assert_true(pg_gift_card_quick_cell_editable($row, $field), $field . ' changes on the list');
		pg_assert_false(pg_gift_card_quick_cell_editable(pg_test_gift_card_quick_row(array('credit_user_id' => 3)), $field), $field . ': store credit stays on its screen');
	}

	pg_assert_false(pg_gift_card_quick_cell_editable($row, 'code'), 'the code is not a field');
	pg_assert_true(pg_gift_card_quick_cell_editable(pg_test_gift_card_quick_row(array('notes' => "One\r\nTwo")), 'notes'), 'notes on more lines change on the list too');
}

function test_gift_card_quick_form_values_are_the_screen_form_with_one_change()
{
	$row = pg_test_gift_card_quick_row();
	$values = pg_gift_card_quick_form_values($row, 'notes', 'New note');

	pg_assert_same('New note', $values['notes'], 'the change');
	pg_assert_same(pg_format_input_amount(12.5), $values['balance'], 'the balance as the screen opens it');
	pg_assert_same(prepare_form_data_for_output('2026-12-31', 'date', false), $values['expiration_date'], 'the date in the site\'s order');
	pg_assert_same('', pg_gift_card_quick_form_values(pg_test_gift_card_quick_row(array('expiration_date' => '0000-00-00')), 'notes', 'x')['expiration_date'], 'a card that never expires opens blank');
	pg_assert_same(pg_format_input_amount(12.5), pg_gift_card_quick_form_values($row, 'balance', '99')['balance'], 'the balance is not a field: the stored one is kept');
}

function test_gift_card_quick_form_values_pass_the_screen_check()
{
	$form = new liveform('gift_card_quick_edit_test');
	$form->remove_form();

	foreach (pg_gift_card_quick_form_values(pg_test_gift_card_quick_row(), 'expiration_date', '31/02/2027') as $field => $value) {
		$form->assign_field_value($field, $value);
	}

	pg_gift_card_form_check($form, 'edit');
	pg_assert_true($form->check_form_errors(), 'a date that does not exist is refused by the screen\'s check');
	$form->remove_form();

	foreach (pg_gift_card_quick_form_values(pg_test_gift_card_quick_row(array('balance' => 0)), 'expiration_date', '') as $field => $value) {
		$form->assign_field_value($field, $value);
	}

	$amount = pg_gift_card_form_check($form, 'edit');
	pg_assert_true(!$form->check_form_errors(), 'a used-up card takes a new date, and blank is no expiration');
	pg_assert_same(0, (int) round($amount * 100), 'its stored balance passes the screen\'s check');
	$form->remove_form();
}

function test_gift_card_form_write_result_tells_which_guard_failed()
{
	$save = array('balance' => 2000, 'balance_was' => 1250, 'expiration_date' => '0000-00-00', 'notes' => 'n', 'notes_was' => 'o');

	pg_assert_same(array('ok' => true, 'field' => '', 'balance' => 2000), pg_gift_card_form_write_result(1, array('balance' => 2000, 'notes' => 'n'), $save, true), 'a changed row landed');
	pg_assert_same('balance', pg_gift_card_form_write_result(0, array('balance' => 1000, 'notes' => 'o'), $save, true)['field'], 'an order spent part of the card meanwhile');
	pg_assert_same(1000, pg_gift_card_form_write_result(0, array('balance' => 1000, 'notes' => 'o'), $save, true)['balance'], 'and the balance now is told');
	pg_assert_same('notes', pg_gift_card_form_write_result(0, array('balance' => 2000, 'notes' => "o\nline"), $save, true)['field'], 'a cancelled order added a line meanwhile');
	pg_assert_true(pg_gift_card_form_write_result(0, array('balance' => 2000, 'notes' => 'n'), $save, true)['ok'], 'the same values already stored landed');
	pg_assert_same('', pg_gift_card_form_write_result(-1, array('balance' => 1250, 'notes' => 'o'), $save, true)['field'], 'a failed query');
	pg_assert_false(pg_gift_card_form_write_result(0, null, $save, true)['ok'], 'a deleted card');

	unset($save['notes_was']);
	pg_assert_true(pg_gift_card_form_write_result(0, array('balance' => 1250, 'notes' => 'x'), $save, false)['ok'], 'the screen leaves an unchanged balance and unguarded notes alone');
}

function test_gift_card_quick_cells_carry_the_values_their_fields_open_with()
{
	$columns = pg_gift_card_edit_columns();
	$cells = pg_gift_card_list_row_cells(pg_test_gift_card_quick_row(), '2026-11-05', $columns);

	pg_assert_same(array_keys(pg_gift_card_list_columns()), array_keys($cells), 'a cell for every column, in order');
	pg_assert_contains('edit_gift_card.php?id=7', $cells['code']['html'], 'the code opens the gift card');
	pg_assert_contains('status_enabled', $cells['code']['html'], 'green with a balance');
	pg_assert_false(isset($cells['balance']['attributes']['data-pg-edit']), 'the balance is not a field');
	pg_assert_same('1250', $cells['balance']['attributes']['data-pg-sort-value'], 'sorted in minor units');
	pg_assert_same(prepare_form_data_for_output('2026-12-31', 'date', false), $cells['expiration_date']['attributes']['data-pg-edit-value'], 'the date field');
	pg_assert_same('For <the> fair', $cells['notes']['attributes']['data-pg-edit-value'], 'the notes as stored');
	pg_assert_contains('For &lt;the&gt; fair', $cells['notes']['html'], 'escaped');
	pg_assert_false(isset($cells['original_amount']['attributes']['data-pg-edit']), 'the first amount is not a field');

	$spent = pg_gift_card_list_row_cells(pg_test_gift_card_quick_row(array('balance' => 0)), '2026-11-05', $columns);
	pg_assert_contains('status_disabled', $spent['code']['html'], 'red without a balance');

	$expired = pg_gift_card_list_row_cells(pg_test_gift_card_quick_row(array('expiration_date' => '2026-11-04')), '2026-11-05', $columns);
	pg_assert_contains(lang('Expired'), $expired['code']['html'], 'expired says so');

	$credit = pg_gift_card_list_row_cells(pg_test_gift_card_quick_row(array('credit_user_id' => 3)), '2026-11-05', $columns);
	pg_assert_contains(lang('Store credit'), $credit['code']['html'], 'store credit is marked');

	foreach (array('expiration_date', 'notes') as $field) {
		pg_assert_false(isset($credit[$field]['attributes']['data-pg-edit']), $field . ' of store credit stays text');
	}

	$plain = pg_gift_card_list_row_cells(pg_test_gift_card_quick_row(), '2026-11-05', array());
	pg_assert_false(isset($plain['notes']['attributes']['data-pg-edit']), 'no fields without quick edit');

	$answer = pg_gift_card_quick_saved($cells, 'expiration_date');
	pg_assert_same(prepare_form_data_for_output('2026-12-31', 'date', false), $answer['value'], 'the edited field\'s value');
	pg_assert_same('For <the> fair', $answer['cells']['notes']['value'], 'the other cells carry theirs');
	pg_assert_true(isset($answer['cells']['code']), 'the code redrawn: its colour follows the date');
	pg_assert_false(isset($answer['cells']['expiration_date']), 'the edited cell is the answer itself');
}

function test_gift_card_screen_and_list_share_one_save()
{
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_list_edit.php');
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_gift_cards.php');

	pg_assert_contains('pg_gift_card_form_write(', $edit, 'the screen saves through the shared write');
	pg_assert_contains('pg_gift_card_form_write(', $module, 'the list too');
	pg_assert_contains("pg_gift_card_form_check(\$form, 'edit')", $module, 'with the screen\'s check');

	foreach (array('edit_gift_card.php' => $edit, 'gift_card_list_edit.php' => $module, 'view_gift_cards.php' => $list) as $file => $source) {
		pg_assert_false(strpos($source, 'UPDATE gift_cards') !== false, $file . ' writes no gift card itself');
	}

	pg_assert_contains('pg_gift_card_list_row_cells(', $list, 'the list draws its rows as the answer does');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Key Codes list quick edit
 * (includes/fn/key_code_list_edit.php): every value of the list the key
 * code screen changes is a field; the form the screen's own check reads is
 * the stored key code with the one change laid over it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/key_code_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/key_code_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $key_code_edit_test_name => $key_code_edit_test_value) {
	if (!defined($key_code_edit_test_name)) {
		define($key_code_edit_test_name, $key_code_edit_test_value);
	}
}

function pg_test_key_code_quick_row(array $over = array())
{
	return $over + array(
		'id' => 4, 'code' => 'VIP<1>', 'offer_code' => 'SUMMER', 'enabled' => '1',
		'expiration_date' => '2026-12-31', 'notes' => 'For the fair', 'single_use' => '0', 'report' => 'offer_code',
		'last_modified_username' => 'ayse', 'last_modified_timestamp' => time(),
		'offer_id' => 9, 'offer_description' => 'Ten off', 'offer_status' => 'enabled',
		'offer_start_date' => '2026-01-01', 'offer_end_date' => '2099-01-01',
	);
}

function test_key_code_quick_edit_every_column_but_the_offer_message_and_last_modified_is_a_field()
{
	$columns = pg_key_code_edit_columns();
	$text = array_values(array_diff(array_keys(pg_key_code_list_columns()), array_keys($columns)));
	sort($text);

	pg_assert_same(array('last_modified', 'offer_message'), $text, 'only the offer\'s message and the last change stay text');
	pg_assert_same(50, $columns['code']['max'], 'the code column\'s length');
	pg_assert_true(!empty($columns['code']['required']) && !empty($columns['offer_code']['required']), 'both codes are required, as on the screen');
	pg_assert_same(array('key_code', 'offer_code'), array_keys($columns['report']['options']), 'the two reports');
	pg_assert_same('0', pg_list_edit_parse($columns['enabled'], '0')['value'], 'disabled is read as 0');
	pg_assert_false(pg_list_edit_parse($columns['enabled'], '2')['ok'], 'nothing else');
}

function test_key_code_quick_form_values_are_the_screen_form_with_one_change()
{
	$row = pg_test_key_code_quick_row();
	$values = pg_key_code_quick_form_values($row, 'notes', 'New note');

	pg_assert_same('New note', $values['notes'], 'the change');
	pg_assert_same('VIP<1>', $values['code'], 'the rest as stored');
	pg_assert_same(prepare_form_data_for_output('2026-12-31', 'date', false), $values['expiration_date'], 'the date in the site\'s order, as the screen opens it');
	pg_assert_same('1', $values['enabled'], 'enabled on');
	pg_assert_same('1', $values['enabled_was'], 'and what it was, so the save keeps the stored flag');
	pg_assert_same('', $values['single_use'], 'a switch that is off posts nothing');

	$off = pg_key_code_quick_form_values($row, 'enabled', '0');
	pg_assert_same('', $off['enabled'], 'turning enabled off');
	pg_assert_same('1', $off['enabled_was'], 'against the stored flag');

	pg_assert_same('', pg_key_code_quick_form_values(pg_test_key_code_quick_row(array('expiration_date' => '0000-00-00')), 'code', 'X')['expiration_date'], 'a code that never expires opens blank');
}

function test_key_code_quick_cells_carry_the_values_their_fields_open_with()
{
	$columns = pg_key_code_edit_columns();
	$cells = pg_key_code_list_row_cells(pg_test_key_code_quick_row(), '2026-11-05', $columns);

	pg_assert_same(array_keys(pg_key_code_list_columns()), array_keys($cells), 'a cell for every column, in order');
	pg_assert_contains('edit_key_code.php?id=4', $cells['code']['html'], 'the code opens the key code');
	pg_assert_contains('VIP&lt;1&gt;', $cells['code']['html'], 'escaped');
	pg_assert_same('VIP<1>', $cells['code']['attributes']['data-pg-edit-value'], 'the field opens with the code as stored');
	pg_assert_contains('edit_offer.php?id=9', $cells['offer_code']['html'], 'the offer code opens its offer');
	pg_assert_same('offer_code', $cells['report']['attributes']['data-pg-edit-value'], 'the report');
	pg_assert_same('0', $cells['single_use']['attributes']['data-pg-edit-value'], 'a switch off');
	pg_assert_false(isset($cells['offer_message']['attributes']['data-pg-edit']), 'the offer\'s message is not a field');

	$lines = pg_key_code_list_row_cells(pg_test_key_code_quick_row(array('notes' => "One\nTwo")), '2026-11-05', $columns);
	pg_assert_false(isset($lines['notes']['attributes']['data-pg-edit']), 'notes on more lines stay on the screen');
	pg_assert_contains('One<br />', $lines['notes']['html'], 'and keep their lines');

	$plain = pg_key_code_list_row_cells(pg_test_key_code_quick_row(), '2026-11-05', array());
	pg_assert_false(isset($plain['code']['attributes']['data-pg-edit']), 'no fields without quick edit');

	$answer = pg_key_code_quick_saved($cells, 'enabled');
	pg_assert_same('1', $answer['value'], 'the edited field\'s value');
	pg_assert_same('VIP<1>', $answer['cells']['code']['value'], 'the other cells carry theirs');
	pg_assert_false(isset($answer['cells']['enabled']), 'the edited cell is the answer itself');
}

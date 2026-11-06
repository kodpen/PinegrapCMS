<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lists that take quick edit beside products and minimum
 * stock: an ERP account's price list (includes/erp/price_lists.php), the key
 * codes (includes/fn/ecommerce.php) and the gift cards
 * (includes/fn/gift_card_list_edit.php). The shipping methods
 * are in shipping_method_list_edit_test.php.
 * What their columns accept and how the cells the answer redraws are drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/price_lists.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_list_edit.php');

if (!defined('BASE_CURRENCY_SYMBOL')) {
	define('BASE_CURRENCY_SYMBOL', '$');
}

function test_list_edit_account_price_columns_take_a_figure_or_nothing()
{
	$columns = erp_account_price_edit_columns();

	foreach (array('price', 'discount') as $field) {
		$read = pg_list_edit_parse($columns[$field], '  ');
		pg_assert_true($read['ok'], $field . ': an empty box is allowed');
		pg_assert_same('', $read['value'], $field . ': and reads as none');
		pg_assert_same('12,5', pg_list_edit_parse($columns[$field], '12,5')['value'], $field . ': a figure is kept as typed');
		pg_assert_false(pg_list_edit_parse($columns[$field], '-5')['ok'], $field . ': no minus sign');
		pg_assert_false(pg_list_edit_parse($columns[$field], '%5')['ok'], $field . ': no symbol');
	}

	// A percentage is refused as a number, not as an amount.
	pg_assert_same('decimal', $columns['discount']['type'], 'the discount is a figure, not an amount');
}

function test_list_edit_key_code_is_active_only_when_it_works_at_checkout()
{
	$today = '2026-10-14';
	$key_code = array(
		'enabled' => '1', 'expiration_date' => '0000-00-00',
		'offer_status' => 'enabled', 'offer_start_date' => '2026-01-01', 'offer_end_date' => '2026-12-31',
	);

	pg_assert_true(pg_key_code_active($key_code, $today), 'enabled, no expiry, offer running');
	pg_assert_true(pg_key_code_active(array('expiration_date' => $today) + $key_code, $today), 'still works on the day it expires');
	pg_assert_false(pg_key_code_active(array('enabled' => '0') + $key_code, $today), 'disabled');
	pg_assert_false(pg_key_code_active(array('expiration_date' => '2026-10-13') + $key_code, $today), 'expired');
	pg_assert_false(pg_key_code_active(array('offer_status' => 'disabled') + $key_code, $today), 'offer disabled');
	pg_assert_false(pg_key_code_active(array('offer_end_date' => '2026-10-13') + $key_code, $today), 'offer over');
	pg_assert_false(pg_key_code_active(array('offer_start_date' => '2026-10-15') + $key_code, $today), 'offer not started');
	pg_assert_false(pg_key_code_active(array('offer_status' => null, 'offer_start_date' => null, 'offer_end_date' => null) + $key_code, $today), 'no offer with that code');
}

function test_list_edit_key_code_cells_follow_the_enabled_flag()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$today = date('Y-m-d');
	$key_code = array(
		'enabled' => '1', 'expiration_date' => '0000-00-00',
		'offer_status' => 'enabled', 'offer_start_date' => '2000-01-01', 'offer_end_date' => '2099-12-31',
		'last_modified_timestamp' => time(), 'last_modified_username' => '<b>ayse</b>',
	);

	$cells = pg_key_code_list_cells($key_code, $today);

	pg_assert_contains('status_enabled fg-success', $cells['code']['class'], 'a working code is green');
	pg_assert_contains('chart_label', $cells['code']['class'], 'the code stays the row label');
	pg_assert_false(strpos($cells['code']['html'], 'fg-secondary') !== false, 'a working code carries no reason line');
	pg_assert_contains('task_alt', $cells['enabled']['html'], 'enabled is marked');
	pg_assert_contains('&lt;b&gt;ayse&lt;/b&gt;', $cells['last_modified']['html'], 'the username escaped');

	$key_code['enabled'] = '0';
	$key_code['last_modified_username'] = null;
	$cells = pg_key_code_list_cells($key_code, $today);

	pg_assert_contains('status_disabled fg-danger', $cells['code']['class'], 'a disabled code is red');
	pg_assert_same('', $cells['enabled']['html'], 'disabled has no mark');
	pg_assert_false(strpos($cells['last_modified']['html'], ' by ') !== false, 'nobody named when the change has no user');
}

function test_list_edit_gift_card_notes_column_keeps_its_lines()
{
	$columns = pg_gift_card_edit_columns();

	pg_assert_same('textarea', $columns['notes']['type'], 'several lines');
	pg_assert_same('', pg_list_edit_parse($columns['notes'], '   ')['value'], 'notes can be cleared');
	pg_assert_same('sent to Ayşe', pg_list_edit_parse($columns['notes'], " sent to Ayşe ")['value'], 'kept as typed, trimmed');
	pg_assert_same("first\nsecond", pg_list_edit_parse($columns['notes'], "first\r\nsecond")['value'], 'two lines stay two lines');
}

function test_list_edit_gift_card_cells_escape_the_notes()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$cells = pg_gift_card_list_cells(array(
		'notes' => "<i>vip</i>\nsecond", 'last_modified_timestamp' => time(), 'last_modified_username' => '',
	));

	pg_assert_contains('&lt;i&gt;vip&lt;/i&gt;', $cells['notes']['html'], 'the notes escaped');
	pg_assert_contains('<br', $cells['notes']['html'], 'lines drawn as on the list');
	pg_assert_same('align-middle text-wrap', $cells['notes']['class'], 'the list\'s class, wrapping');
	pg_assert_contains('<div class="text-wrap text-break" style="max-width:20rem;">', $cells['notes']['html'], 'a long note wraps in a box of its own width');
	pg_assert_false(strpos($cells['last_modified']['html'], ' by ') !== false, 'nobody named when the change has no user');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lists that take quick edit beside products and minimum
 * stock: an ERP account's price list (includes/erp/price_lists.php), the
 * shipping methods, the key codes and the gift cards (includes/fn/ecommerce.php).
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
}

function test_list_edit_shipping_rate_columns_take_a_figure_or_nothing()
{
	$columns = pg_shipping_method_edit_columns();

	pg_assert_same(array('base_rate', 'item_rate'), array_keys($columns), 'the base rate and the item rate');

	foreach ($columns as $field => $column) {
		pg_assert_same('money', $column['type'], $field . ': an amount');
		pg_assert_same('', pg_list_edit_parse($column, '')['value'], $field . ': an empty box is no charge');
		pg_assert_same('7,50', pg_list_edit_parse($column, '7,50')['value'], $field . ': a figure is kept as typed');
		pg_assert_false(pg_list_edit_parse($column, '-1')['ok'], $field . ': no minus sign');
	}
}

function test_list_edit_shipping_cells_draw_each_step_of_a_variable_base_rate()
{
	// The last change is drawn by get_relative_time(), which reads the
	// site's date order.
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$method = array(
		'base_rate' => 500, 'item_rate' => 125, 'variable_base_rate' => 0,
		'base_rate_2' => 300, 'base_rate_2_subtotal' => 10000,
		'base_rate_3' => 0, 'base_rate_3_subtotal' => 20000,
		'base_rate_4' => 0, 'base_rate_4_subtotal' => 0,
		'timestamp' => time(), 'user' => '<b>ayse</b>',
	);

	$cells = pg_shipping_method_list_cells($method);

	pg_assert_same(pg_format_money(5, BASE_CURRENCY_SYMBOL), $cells['base_rate']['html'], 'a single base rate');
	pg_assert_same(pg_format_money(1.25, BASE_CURRENCY_SYMBOL), $cells['item_rate']['html'], 'the item rate');
	pg_assert_contains('align-middle text-end', $cells['item_rate']['class'], 'amounts on the right');
	pg_assert_contains('&lt;b&gt;ayse&lt;/b&gt;', $cells['last_modified']['html'], 'the username escaped');

	$method['variable_base_rate'] = 1;
	$cells = pg_shipping_method_list_cells($method);

	pg_assert_same(2, substr_count($cells['base_rate']['html'], '<br>'), 'the two steps with a subtotal, the one without left out');
	pg_assert_contains(' (@' . pg_format_money(100, BASE_CURRENCY_SYMBOL) . ')', $cells['base_rate']['html'], 'a step shows the subtotal it starts at');

	$method['user'] = null;
	pg_assert_false(strpos(pg_shipping_method_list_cells($method)['last_modified']['html'], ' by ') !== false, 'nobody named when the change has no user');
}

function test_list_edit_key_code_enabled_column_offers_enabled_or_disabled()
{
	$columns = pg_key_code_edit_columns();

	pg_assert_same(array('enabled'), array_keys($columns), 'only the enabled flag');
	pg_assert_same('select', $columns['enabled']['type'], 'a choice');
	pg_assert_same(array('1', '0'), array_map('strval', array_keys($columns['enabled']['options'])), 'enabled first, then disabled');
	pg_assert_same('0', pg_list_edit_parse($columns['enabled'], '0')['value'], 'disabled is read as 0');
	pg_assert_false(pg_list_edit_parse($columns['enabled'], '2')['ok'], 'nothing else');
	pg_assert_false(pg_list_edit_parse($columns['enabled'], '')['ok'], 'not empty');
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
	pg_assert_false(isset($cells['code']['html']), 'the code itself is not redrawn');
	pg_assert_contains('task_alt', $cells['enabled']['html'], 'enabled is marked');
	pg_assert_contains('&lt;b&gt;ayse&lt;/b&gt;', $cells['last_modified']['html'], 'the username escaped');

	$key_code['enabled'] = '0';
	$key_code['last_modified_username'] = null;
	$cells = pg_key_code_list_cells($key_code, $today);

	pg_assert_contains('status_disabled fg-danger', $cells['code']['class'], 'a disabled code is red');
	pg_assert_same('', $cells['enabled']['html'], 'disabled has no mark');
	pg_assert_false(strpos($cells['last_modified']['html'], ' by ') !== false, 'nobody named when the change has no user');
}

function test_list_edit_gift_card_notes_column_takes_one_line()
{
	$columns = pg_gift_card_edit_columns();

	pg_assert_same(array('notes'), array_keys($columns), 'only the notes; the balance stays on the card');
	pg_assert_same('text', $columns['notes']['type'], 'a text');
	pg_assert_same('', pg_list_edit_parse($columns['notes'], '   ')['value'], 'notes can be cleared');
	pg_assert_same('sent to Ayşe', pg_list_edit_parse($columns['notes'], " sent to Ayşe ")['value'], 'kept as typed, trimmed');

	pg_assert_true(pg_gift_card_notes_fit_list(''), 'no notes fit');
	pg_assert_true(pg_gift_card_notes_fit_list('sent to Ayşe'), 'one line fits');
	pg_assert_false(pg_gift_card_notes_fit_list("first\nsecond"), 'two lines stay on the gift card');
	pg_assert_false(pg_gift_card_notes_fit_list("first\r\nsecond"), 'two lines from a browser too');
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
	pg_assert_same('align-middle', $cells['notes']['class'], 'the list\'s class');
	pg_assert_false(strpos($cells['last_modified']['html'], ' by ') !== false, 'nobody named when the change has no user');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lists that take quick edit beside products and minimum
 * stock: an ERP account's price list (includes/erp/price_lists.php) and the
 * shipping methods (includes/fn/ecommerce.php). What their columns accept
 * and how the shipping cells the answer redraws are drawn.
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

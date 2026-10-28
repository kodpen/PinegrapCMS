<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the products list (includes/fn/product_list.php): the view
 * names, the columns each view shows and sorts by, the view's condition and
 * order, the search columns, an amount typed into the search box, the
 * headings and a row, with its quick edit cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');

// Without a request the site sits at the root, as elsewhere in the tests.
foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $product_list_test_name => $product_list_test_value) {
	if (!defined($product_list_test_name)) {
		define($product_list_test_name, $product_list_test_value);
	}
}

function product_list_test_view($filter, $state = array())
{
	$_SESSION['software']['list_state']['view_products'] = $state;

	return pg_product_list_view($filter, true, false, true, array(1 => 'Colour', 2 => '', 3 => '', 4 => ''), true);
}

function product_list_test_product($values = array())
{
	return array_merge(array(
		'id' => '12', 'name' => 'MUG-1', 'enabled' => '1', 'image_name' => '', 'inventory' => '1', 'inventory_quantity' => '4',
		'short_description' => 'Mug <big>', 'price' => '1250', 'taxable' => '1', 'form_name' => '', 'seo_score' => '0',
		'seo_flags' => '0', 'seo_checked_at' => '0', 'seo_analysis_current' => '0', 'seo_impact' => null, 'seo_views' => '0',
		'shippable' => '1', 'selection_type' => 'quantity', 'default_quantity' => '1', 'custom_field_1' => 'Red & blue',
		'custom_field_2' => '', 'custom_field_3' => '', 'custom_field_4' => '', 'recurring' => '0', 'barcode_count' => '0',
		'user' => 'ali', 'out_of_stock' => '0', 'out_of_stock_timestamp' => '0', 'timestamp' => (string) time(),
	), $values);
}

function test_product_list_filter_names()
{
	pg_assert_same('all_products', pg_product_list_filter_name(''));
	pg_assert_same('all_products', pg_product_list_filter_name('<x>'));
	pg_assert_same('all_products', pg_product_list_filter_name(array('shippable_products')));
	pg_assert_same('shippable_products', pg_product_list_filter_name('shippable_products'));
	pg_assert_same('out_of_stock_products', pg_product_list_filter_name('out_of_stock_products'));
}

function test_product_list_columns_follow_the_view()
{
	$labels = array(1 => 'Colour', 2 => '', 3 => '', 4 => '');

	pg_assert_same(
		array('id', 'short_description', 'enabled', 'price', 'inventory_quantity', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'custom_field_1', 'recurring_payment', 'impact', 'last_modified'),
		pg_product_list_column_codes('all_products', true, false, $labels),
		'a custom field with no label has no column');

	pg_assert_same(
		array('id', 'short_description', 'enabled', 'price', 'compare_at_price', 'set_start_page', 'grant_private_access', 'membership_renewal', 'order_receipt_message', 'order_receipt_bcc', 'email_page', 'email_page_bcc', 'contact_group', 'impact', 'last_modified'),
		array_values(array_diff(pg_product_list_column_codes('all_product_actions', true, true, $labels), array('inventory_quantity'))),
		'no tax and no product form on the actions view');

	$codes = pg_product_list_column_codes('out_of_stock_products', false, false, $labels);
	pg_assert_false(in_array('inventory_quantity', $codes, true), 'no stock column on the out of stock view');
	pg_assert_false(in_array('taxable', $codes, true), 'no tax column without tax');
	pg_assert_same('out_of_stock_date', end($codes));

	$codes = pg_product_list_column_codes('shippable_products', true, false, $labels);
	pg_assert_true(in_array('allowed_zones', $codes, true) && in_array('disallowed_zones', $codes, true));

	$codes = pg_product_list_column_codes('donation_products', true, false, $labels);
	pg_assert_same(array('recurring_payment', 'start', 'number_of_payments', 'payment_period', 'allow_to_schedule'), array_slice($codes, 7, 5));
}

function test_product_list_view_where_and_order()
{
	$view = product_list_test_view('shippable_products');
	pg_assert_same("WHERE products.shippable = '1' AND (products.recycled = '0')", $view['where']);
	pg_assert_same('products.timestamp desc, products.id asc', $view['order_by'], 'the id last keeps pages apart');

	$view = product_list_test_view('out_of_stock_products');
	pg_assert_same('products.out_of_stock_timestamp desc, products.id asc', $view['order_by'], 'its own default sort');

	$view = product_list_test_view('all_products', array('sort' => 'price', 'order' => 'asc', 'then' => array(array('sort' => 'weight', 'order' => 'desc'))));
	pg_assert_same('products.price asc, products.id asc', $view['order_by'], 'a level the view does not sort by is dropped');
	pg_assert_same("WHERE (products.recycled = '0')", product_list_test_view('all_product_actions')['where'], 'a view with no condition of its own');

	$view = pg_product_list_view('all_products', true, false, true, array(), false);
	pg_assert_same('', $view['where'], 'nothing to leave out before the Recycle Bin');
	pg_assert_false(isset($view['sort_columns']['custom_field_1']), 'an unlabelled custom field is not sorted by');
}

function test_product_list_amount_term()
{
	pg_assert_same('123450', pg_product_list_amount_term('1.234,50'));
	pg_assert_same('199', pg_product_list_amount_term('199'));
	pg_assert_same('', pg_product_list_amount_term('mug'));
	pg_assert_same('', pg_product_list_amount_term('$12'));
}

function test_product_list_search_columns()
{
	$columns = pg_product_list_search_columns(product_list_test_view('all_products'));
	$where = pg_list_search_where(array('red'), $columns['columns'], $columns['labels']);

	pg_assert_contains('products.name LIKE ?', $where['sql']);
	pg_assert_contains('products.custom_field_1 LIKE ?', $where['sql']);
	pg_assert_contains('products.form_name LIKE ?', $where['sql']);
	pg_assert_false(strpos($where['sql'], 'products.price') !== false, 'a word is not an amount');
	pg_assert_same(substr_count($where['sql'], '?'), count($where['params']));

	$where = pg_list_search_where(array('quant'), $columns['columns'], $columns['labels']);
	pg_assert_contains('products.selection_type IN (?)', $where['sql'], 'the label shown, matched through its value');

	$where = pg_list_search_where(array('12,50'), $columns['columns'], $columns['labels']);
	pg_assert_contains('products.price LIKE ?', $where['sql']);
	pg_assert_true(in_array('%1250%', $where['params'], true));

	$columns = pg_product_list_search_columns(product_list_test_view('all_product_actions'));
	$where = pg_list_search_where(array('x'), $columns['columns'], $columns['labels']);
	pg_assert_contains('contact_groups.name LIKE ?', $where['sql']);
	pg_assert_contains('email_page.page_name LIKE ?', $where['sql']);
	pg_assert_false(strpos($where['sql'], 'products.form_name') !== false, 'no product form column on this view');
}

function test_product_list_headings_match_the_row()
{
	$view = product_list_test_view('all_products');
	$headings = pg_product_list_headings($view);
	$row = pg_product_list_row(product_list_test_product(), $view, array('quick_edit' => pg_pb_quick_edit_columns(), 'more_images' => array(), 'zones' => array()));

	pg_assert_same(substr_count($headings, '<th'), count($row['cells']), 'one heading per cell');
	pg_assert_contains('Colour', $headings);
}

function test_product_list_row_cells()
{
	$view = product_list_test_view('all_products');
	$row = pg_product_list_row(product_list_test_product(), $view, array('quick_edit' => pg_pb_quick_edit_columns(), 'more_images' => array(), 'zones' => array()));
	$html = pg_list_row_html($row);

	pg_assert_contains('name="products[]" value="12"', $html);
	pg_assert_contains('Mug &lt;big&gt;', $html, 'escaped');
	pg_assert_contains('Red &amp; blue', $html);
	pg_assert_contains('data-pg-cell="name"', $html);
	pg_assert_contains('data-pg-edit="price" data-pg-edit-id="12"', $html, 'the price changes in place');
	pg_assert_contains('data-pg-edit="inventory_quantity"', $html, 'a tracked stock changes in place');
	pg_assert_contains(lang('Quantity'), $html);

	$data = pg_list_row_data($row);
	pg_assert_true(count($data['DT_RowData']['pgAttrs']) >= 4, 'the quick edit attributes travel with the page');

	$row = pg_product_list_row(product_list_test_product(array('inventory' => '0')), $view, array('quick_edit' => pg_pb_quick_edit_columns(), 'more_images' => array(), 'zones' => array()));
	$html = pg_list_row_html($row);
	pg_assert_false(strpos($html, 'data-pg-edit="inventory_quantity"') !== false, 'no stock to change without tracking');
	pg_assert_contains('title="' . h(lang('Inventory tracking is not active for this product/service')) . '"', $html);
}

function test_product_list_zone_cells()
{
	$view = product_list_test_view('shippable_products');
	$product = product_list_test_product(array('weight' => '2.5', 'primary_weight_points' => '1', 'secondary_weight_points' => '0', 'length' => '0', 'width' => '0', 'height' => '0', 'container_required' => '0', 'preparation_time' => '0', 'free_shipping' => '1', 'extra_shipping_cost' => '0'));
	$row = pg_product_list_row($product, $view, array('quick_edit' => array(), 'more_images' => array(), 'zones' => array(12 => array('allowed' => array('EU <1>'), 'disallowed' => array('Asia', 'US')))));
	$html = pg_list_row_html($row);

	pg_assert_contains('EU &lt;1&gt;', $html);
	pg_assert_contains('Asia,<br />US', $html);
	pg_assert_same(substr_count(pg_product_list_headings($view), '<th'), count($row['cells']));
}

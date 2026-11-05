<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the products list on the list table driver
 * (includes/fn/product_list.php): its columns per view, the quick edit
 * columns keyed by their edit fields, a row's cells by column key and the
 * list's place among the driver's lists.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $product_list_driver_test_name => $product_list_driver_test_value) {
	if (!defined($product_list_driver_test_name)) {
		define($product_list_driver_test_name, $product_list_driver_test_value);
	}
}

function product_list_driver_test_view($filter, $compare_at = false)
{
	$_SESSION['software']['list_state']['view_products'] = array();

	return pg_product_list_view($filter, true, $compare_at, true, array(1 => 'Colour', 2 => '', 3 => '', 4 => ''), true);
}

function product_list_driver_test_product($values = array())
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

function test_product_list_driver_columns()
{
	$columns = pg_list_table_columns(pg_product_list_columns(product_list_driver_test_view('all_products'), pg_pb_quick_edit_columns()));

	pg_assert_same(
		array('id', 'image', 'seo', 'short_description', 'enabled', 'price', 'inventory_quantity', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'custom_field_1', 'recurring_payment', 'impact', 'last_modified', 'actions'),
		array_keys($columns));
	pg_assert_same(false, $columns['id']['hideable'], 'the name with the editor link is never hidden');
	pg_assert_same('products.name', $columns['id']['sort']);
	pg_assert_false($columns['seo']['hidden'], 'the score bar has a column of its own, shown');
	pg_assert_true($columns['seo']['hideable'], 'the score column can be hidden');
	pg_assert_same('products.seo_score', $columns['seo']['sort']);
	pg_assert_false($columns['image']['sortable']);
	pg_assert_false($columns['actions']['sortable']);
	pg_assert_same("IF(products.inventory = '1', products.inventory_quantity, NULL)", $columns['inventory_quantity']['sort']);
}

function test_product_list_driver_quick_edit_columns()
{
	$columns = pg_list_table_columns(pg_product_list_columns(product_list_driver_test_view('all_products', true), pg_pb_quick_edit_columns()));
	$editable = array();

	foreach ($columns as $key => $column) {
		if ($column['editable'] !== '') {
			$editable[$key] = $column['editable'];
		}
	}

	$expected = array('id' => 'text', 'short_description' => 'text', 'enabled' => 'select', 'price' => 'money');

	if (isset(pg_pb_quick_edit_columns()['compare_at_price'])) {
		$expected['compare_at_price'] = 'money';
	}

	$expected += array('inventory_quantity' => 'number', 'taxable' => 'select', 'product_form' => 'text', 'selection_type' => 'select', 'default_quantity' => 'number', 'shippable' => 'select', 'recurring_payment' => 'select');

	pg_assert_same($expected, $editable, 'each editable column is keyed by its edit field, the ID column by the product code');

	$columns = pg_list_table_columns(pg_product_list_columns(product_list_driver_test_view('all_products'), array()));

	foreach ($columns as $key => $column) {
		pg_assert_same('', $column['editable'], $key . ' is not editable without edit fields');
	}
}

function product_list_driver_test_choices()
{
	return array(
		'pages' => array('7' => 'Welcome', '9' => 'Thanks [ARCHIVED]'),
		'folders' => array('3' => 'Members', '4' => "\u{00A0}\u{00A0}Gold"),
		'groups' => array('5' => 'Buyers'),
		'zones' => array('5' => 'Asia', '2' => 'Europe', '8' => 'World'),
	);
}

function test_product_list_driver_every_screen_value_is_editable()
{
	// Computed, or set on the product screen with several choices or rich
	// text: the only columns that stay text in quick edit.
	$read_only = array('image', 'seo', 'impact', 'last_modified', 'out_of_stock_date', 'actions', 'order_receipt_message');

	foreach (array_keys(pg_product_list_filters()) as $filter) {
		$columns = pg_list_table_columns(pg_product_list_columns(product_list_driver_test_view($filter), pg_pb_quick_edit_columns(product_list_driver_test_choices())));

		foreach ($columns as $key => $column) {
			// A custom field changes in place where the site names it, as
			// the product screen draws it (the site's labels, not the view's).
			if (strpos($key, 'custom_field_') === 0) {
				pg_assert_same((pg_pb_custom_product_field_labels()[(int) substr($key, -1)] !== ''), ($column['editable'] !== ''), $filter . ': ' . $key . ' follows its label');
			} elseif (in_array($key, $read_only, true)) {
				pg_assert_same('', $column['editable'], $filter . ': ' . $key . ' stays text');
			} else {
				pg_assert_true($column['editable'] !== '', $filter . ': ' . $key . ' changes in place');
			}
		}
	}
}

function test_product_list_driver_more_fields_read_like_the_screen()
{
	$current = array('minimum_quantity' => 2, 'maximum_quantity' => 10);

	pg_assert_same(array('ok' => true, 'change' => array('taxable' => '1'), 'message' => ''), pg_pb_quick_more_change('taxable', '1', $current), 'a switch writes 1');
	pg_assert_same(array('container_required' => '0'), pg_pb_quick_more_change('cont_req', '0', $current)['change'], 'a switch writes 0, under its column');
	pg_assert_same(array('default_quantity' => 5), pg_pb_quick_more_change('default_quantity', 5, $current)['change'], 'a default quantity between the minimum and the maximum');
	pg_assert_true(!pg_pb_quick_more_change('default_quantity', 1, $current)['ok'], 'a default quantity under the minimum is refused');
	pg_assert_true(!pg_pb_quick_more_change('default_quantity', 11, $current)['ok'], 'a default quantity over the maximum is refused');
	pg_assert_same(array('extra_shipping_cost' => 1250), pg_pb_quick_more_change('extra_ship', '12.50', $current)['change'], 'an amount in minor units');
	pg_assert_true(!pg_pb_quick_more_change('extra_ship', '99999999999', $current)['ok'], 'an amount the column cannot hold is refused');
	pg_assert_same(array('weight' => '1.2500'), pg_pb_quick_more_change('weight', '1,25', $current)['change'], 'a weight with four places, either separator');
	pg_assert_true(!pg_pb_quick_more_change('weight', '123456789', $current)['ok'], 'a weight the column cannot hold is refused');
	pg_assert_true(!pg_pb_quick_more_change('email_page_bcc', 'not an address', $current)['ok'], 'an address that is not one is refused');
	pg_assert_same(array('email_bcc' => ''), pg_pb_quick_more_change('email_page_bcc', '', $current)['change'], 'an empty address clears it');
	pg_assert_same(array('name' => 'SKU-2'), pg_pb_quick_more_change('name', 'SKU-2', $current)['change'], 'the ID column writes the product code');
	pg_assert_true(!pg_pb_quick_more_change('nothing', '1', $current)['ok'], 'an unknown field is refused');

	pg_assert_same('name', pg_pb_quick_edit_field_of('id'), 'the ID column is the product code field');
	pg_assert_same('price', pg_pb_quick_edit_field_of('price'), 'another column is its own field');
	pg_assert_same('0', pg_pb_quick_edit_value('taxable', array('taxable' => '')), 'an empty switch opens as No');
	pg_assert_same('30', pg_pb_quick_edit_value('start', array('recurring_start' => '30')), 'the start day from the list read');
	pg_assert_same('30', pg_pb_quick_edit_value('start', array('start' => '30')), 'the start day from the products row');
	pg_assert_same('Monthly', pg_pb_quick_edit_value('payment_period', array('payment_period' => 'Monthly')), 'a choice opens with its value');
}

function test_product_list_driver_choice_fields()
{
	$fields = pg_pb_quick_edit_fields(product_list_driver_test_choices());
	$product = array('start_page' => 'Welcome', 'membership_renewal' => '0', 'order_receipt_message' => '', 'order_receipt_bcc_email_address' => '', 'email_bcc' => '', 'contact_group_name' => 'Buyers',
		'weight' => '0', 'primary_weight_points' => '0', 'secondary_weight_points' => '0', 'length' => '0', 'width' => '0', 'height' => '0', 'container_required' => '0',
		'preparation_time' => '0', 'free_shipping' => '0', 'extra_shipping_cost' => '0', 'recurring_start' => '0', 'number_of_payments' => '0', 'payment_period' => '',
		'recurring_schedule_editable_by_customer' => '0', 'grant_private_access' => '1', 'send_to_page' => '7', 'private_folder_id' => '4', 'private_folder' => 'Gold', 'email_page_id' => '0', 'email_page' => '', 'contact_group_id' => '5');

	pg_assert_same(array('0', '7', '9'), array_map('strval', array_keys($fields['email_page']['edit']['options'])), 'none first, then the pages in the picker\'s order');
	pg_assert_same('4', pg_pb_quick_edit_value('grant_private_access', $product, $fields), 'the folder id from the list read, not its name');
	pg_assert_same('0', pg_pb_quick_edit_value('email_page', $product, $fields), 'no page opens as none');
	pg_assert_true(pg_pb_quick_cell_editable('set_start_page', $product, $fields), 'a start page on a product that grants private access');
	pg_assert_false(pg_pb_quick_cell_editable('set_start_page', array('grant_private_access' => '0') + $product, $fields), 'not without private access, as the screen hides it');
	pg_assert_false(pg_pb_quick_cell_editable('contact_group', array('contact_group_id' => '6') + $product, $fields), 'a stored group outside the choices stays text');
	pg_assert_true(pg_pb_quick_cell_editable('weight', $product, $fields), 'a value typed in a box is always a field');
	pg_assert_false(pg_pb_quick_cell_editable('email_page', $product, pg_pb_quick_edit_more_fields()), 'no choice field without the choices');
	pg_assert_same(array('email_page' => 9), pg_pb_quick_more_change('email_page', '9', array(), $fields)['change'], 'a page id under its column');
	pg_assert_same(array('contact_group_id' => 0), pg_pb_quick_more_change('contact_group', '0', array(), $fields)['change'], 'none writes 0');
	pg_assert_same('Gold', trim(pg_pb_quick_more_shown('grant_private_access', $product, $fields), "\u{00A0} "), 'the log names the folder');

	$view = product_list_driver_test_view('all_product_actions');
	$context = array('quick_edit' => pg_pb_quick_edit_columns(product_list_driver_test_choices()), 'fields' => $fields, 'more_images' => array(), 'zones' => array());
	$row = pg_product_list_row(product_list_driver_test_product($product), $view, $context);

	foreach (array('set_start_page', 'grant_private_access', 'contact_group') as $code) {
		pg_assert_same($code, $row['cells'][$code]['attributes']['data-pg-edit'] ?? '', $code . ' opens as a choice');
	}

	pg_assert_same('Members', '' . (pg_pb_quick_edit_choice_fields(product_list_driver_test_choices())['grant_private_access']['edit']['options']['3'] ?? ''), 'the folder\'s label');
	$row = pg_product_list_row(product_list_driver_test_product(array('grant_private_access' => '0') + $product), $view, $context);
	pg_assert_false(isset($row['cells']['set_start_page']['attributes']['data-pg-edit']), 'no start page to change without private access');
}

function test_product_list_driver_dimensions()
{
	$fields = pg_pb_quick_edit_more_fields();

	if (!isset($fields['dim'])) {
		return;
	}

	pg_assert_same(array('length' => '12.0000', 'width' => '3.0000', 'height' => '4.5000'), pg_pb_quick_more_change('dim', '12 x 3 x 4,5', array())['change'], 'three sizes, either separator');
	pg_assert_same(array('length' => '1.0000', 'width' => '2.0000', 'height' => '3.0000'), pg_pb_quick_more_change('dim', '1×2*3', array())['change'], 'x, × or *');
	pg_assert_same(array('length' => '0.0000', 'width' => '0.0000', 'height' => '0.0000'), pg_pb_quick_more_change('dim', '', array())['change'], 'an empty cell takes them off');
	pg_assert_false(pg_pb_quick_more_change('dim', '12 x 3', array())['ok'], 'two sizes are refused');
	pg_assert_false(pg_pb_quick_more_change('dim', '12 x abc x 3', array())['ok'], 'a size that is not a number is refused');
	pg_assert_false(pg_pb_quick_more_change('dim', '1 x 2 x 123456789', array())['ok'], 'a size the column cannot hold is refused');
	pg_assert_false(pg_pb_quick_more_change('dim', '-1 x 2 x 3', array())['ok'], 'a negative size is refused');
	pg_assert_same('12 x 3 x 4.5', pg_pb_quick_edit_value('dim', array('length' => '12.0000', 'width' => '3.0000', 'height' => '4.5000')), 'the cell opens as typed');
	pg_assert_same('', pg_pb_quick_edit_value('dim', array('length' => '0.0000', 'width' => '0.0000', 'height' => '0.0000')), 'empty for none');
}

function test_product_list_driver_sort_columns_match_the_old_codes()
{
	$view = product_list_driver_test_view('shippable_products');
	$sorts = pg_list_table_sort_columns(pg_list_table_columns(pg_product_list_columns($view, array())));

	pg_assert_same(array(), array_values(array_diff(array_keys($view['sort_columns']), array_keys($sorts))), 'a kept sort still names its column');
	pg_assert_false(isset($sorts['allowed_zones']), 'the zones do not sort');

	$columns = pg_product_list_columns(product_list_driver_test_view('out_of_stock_products'), array());
	pg_assert_true(isset($columns['seo']), 'the score bar shows where the view does not sort by it');
	pg_assert_same('', pg_list_table_columns($columns)['seo']['sort'], 'that column does not sort');
	pg_assert_same('out_of_stock_date', array_keys($columns)[count($columns) - 2]);
}

function test_product_list_driver_row_cells()
{
	$view = product_list_driver_test_view('all_products');
	$context = array('quick_edit' => pg_pb_quick_edit_columns(), 'more_images' => array(), 'zones' => array());
	$row = pg_product_list_row(product_list_driver_test_product(), $view, $context);
	$columns = pg_list_table_columns(pg_product_list_columns($view, $context['quick_edit']));

	pg_assert_same('12', (string) $row['id']);
	pg_assert_same(array_keys($columns), array_keys($row['cells']), 'one cell per column, in its order');
	pg_assert_contains('data-pg-open', $row['cells']['id']['html'], 'a double-click opens the product');
	pg_assert_contains('edit_product.php?id=12', $row['cells']['id']['html']);
	pg_assert_false(strpos($row['cells']['id']['html'], 'pg-seo-bar') !== false, 'no score bar under the name');
	pg_assert_contains('data-pg-seo-bar="product:12"', $row['cells']['seo']['html'], 'the bar in its own column, redrawn after an analysis');
	pg_assert_same('-1', $row['cells']['seo']['attributes']['data-pg-sort-value'], 'a record not analysed sorts lowest');
	pg_assert_same('name', $row['cells']['id']['attributes']['data-pg-cell'], 'the name follows the enabled flag');
	pg_assert_same('price', $row['cells']['price']['attributes']['data-pg-edit']);
	pg_assert_same('12', $row['cells']['price']['attributes']['data-pg-edit-id']);
	pg_assert_same('inventory_quantity', $row['cells']['inventory_quantity']['attributes']['data-pg-edit']);
	pg_assert_contains('Mug &lt;big&gt;', $row['cells']['short_description']['html'], 'escaped');
	pg_assert_contains('Red &amp; blue', $row['cells']['custom_field_1']['html']);

	$html = pg_list_table_row_html($row, $columns);
	pg_assert_false(strpos($html, 'name="products[]"') !== false, 'the driver selects; no check box');

	$row = pg_product_list_row(product_list_driver_test_product(array('inventory' => '0')), $view, $context);
	pg_assert_false(isset($row['cells']['inventory_quantity']['attributes']['data-pg-edit']), 'no stock to change without tracking');
}

function test_product_list_driver_is_a_driver_list()
{
	$definition = pg_list_table_definition('view_products');

	pg_assert_same('includes/fn/product_list.php', $definition['file']);
	pg_assert_same('pg_product_list_source', $definition['source']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
	pg_assert_same(null, pg_product_list_source(array('role' => '3', 'manage_ecommerce' => '0'), array()), 'a user without the e-commerce right may not open it');
}

function test_product_list_driver_zone_fields()
{
	$fields = pg_pb_quick_edit_fields(product_list_driver_test_choices());

	if (!isset($fields['allowed_zones'])) {
		return;
	}

	pg_assert_same('multiselect', $fields['allowed_zones']['edit']['type'], 'several choices');
	pg_assert_same(array('5' => 'Asia', '2' => 'Europe', '8' => 'World'), $fields['disallowed_zones']['edit']['options'], 'every zone, by name');
	pg_assert_false(isset(pg_pb_quick_edit_fields(array('zones' => array()))['allowed_zones']), 'no zones, no field');

	$product = array('allowed_zone_ids' => '2,5');
	pg_assert_same('5,2', pg_pb_quick_edit_value('allowed_zones', $product, $fields), 'the allowed ones in the choices\' order');
	pg_assert_same('8', pg_pb_quick_edit_value('disallowed_zones', $product, $fields), 'the rest');
	pg_assert_same('', pg_pb_quick_edit_value('allowed_zones', array('allowed_zone_ids' => ''), $fields), 'none allowed');
	pg_assert_true(pg_pb_quick_cell_editable('allowed_zones', $product, $fields), 'a row read with its zones');
	pg_assert_false(pg_pb_quick_cell_editable('allowed_zones', array(), $fields), 'not without them');

	pg_assert_same(array('allowed_zone_ids' => '5,8'), pg_pb_quick_more_change('allowed_zones', array('8', '5'), array(), $fields)['change'], 'the allowed list stored as picked');
	pg_assert_same(array('allowed_zone_ids' => '5,8'), pg_pb_quick_more_change('disallowed_zones', array('2'), array(), $fields)['change'], 'the disallowed side writes the rest');
	pg_assert_same(array('allowed_zone_ids' => ''), pg_pb_quick_more_change('allowed_zones', array(), array(), $fields)['change'], 'none: ships nowhere, as the screen saves it');
	pg_assert_same('Asia, Europe', pg_pb_quick_more_shown('allowed_zones', $product, $fields), 'the log names the zones');

	$columns = pg_list_table_columns(pg_product_list_columns(product_list_driver_test_view('shippable_products'), pg_pb_quick_edit_columns(product_list_driver_test_choices())));
	pg_assert_same('multiselect', $columns['disallowed_zones']['editable'], 'the column opens as several choices');

	$view = product_list_driver_test_view('shippable_products');
	$context = array('quick_edit' => pg_pb_quick_edit_columns(product_list_driver_test_choices()), 'fields' => $fields, 'more_images' => array(),
		'zones' => array(12 => array('allowed' => array('2' => 'Europe'), 'disallowed' => array('5' => 'Asia', '8' => 'World'))));
	$row = pg_product_list_row(product_list_driver_test_product(array('weight' => '0', 'primary_weight_points' => '0', 'secondary_weight_points' => '0', 'length' => '0', 'width' => '0', 'height' => '0',
		'container_required' => '0', 'preparation_time' => '0', 'free_shipping' => '0', 'extra_shipping_cost' => '0')), $view, $context);

	pg_assert_same('2', $row['cells']['allowed_zones']['attributes']['data-pg-edit-value'] ?? '', 'the allowed cell opens with its zone');
	pg_assert_same('5,8', $row['cells']['disallowed_zones']['attributes']['data-pg-edit-value'] ?? '', 'the disallowed cell with the rest');
	pg_assert_contains('Asia,<br />World', $row['cells']['disallowed_zones']['html'], 'drawn by name');
}

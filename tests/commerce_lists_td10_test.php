<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product attributes, cart quotes, returns, subscriptions and a
 * contact's orders lists on the list table driver: printed lists, the
 * product attribute cells and what changes in place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php';

function test_commerce_lists_td10_printed()
{
	foreach (array('view_product_attributes', 'view_cart_quotes', 'view_returns', 'view_subscriptions', 'view_orders_for_contact') as $list) {
		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list);
	}

	foreach (array('view_product_attributes.php', 'view_cart_quotes.php', 'view_returns.php', 'view_subscriptions.php', 'view_orders_for_contact.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($source, 'class="chart') !== false, $file . ': no DataTables table left');
		pg_assert_contains('pg_list_table_printed(', $source, $file);
	}

	pg_assert_contains("'ids_field' => 'returns[]'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_returns.php'), 'Approve Selected gets the selection as returns[]');
}

function test_commerce_lists_td10_product_attribute_cells()
{
	$row = array('id' => 4, 'name' => 'Size', 'label' => '', 'options' => array('S', 'M <b>'), 'created_timestamp' => 1700000000, 'last_modified_timestamp' => 1700000000, 'created_username' => '', 'last_modified_username' => '');
	$cells = pg_product_attribute_list_cells($row, pg_product_attribute_edit_columns());

	pg_assert_same('Size', $cells['name']['attributes']['data-pg-edit-value']);
	pg_assert_contains('data-pg-open', $cells['name']['html']);
	pg_assert_contains('No Label', $cells['label']['html'], 'a missing label is said');
	pg_assert_same('', $cells['label']['attributes']['data-pg-edit-value']);
	pg_assert_same('S, M &lt;b&gt;', $cells['options']['html'], 'options escaped');
	pg_assert_false(isset($cells['options']['attributes']['data-pg-edit']), 'options change on the attribute screen');

	$specs = pg_product_attribute_list_columns();
	pg_assert_same('text', $specs['name']['editable']);
	pg_assert_false(isset($specs['options']['editable']));
}

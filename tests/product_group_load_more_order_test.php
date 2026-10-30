<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the products offered on the Create and Edit Product Group
 * screens are paged in one stable order (timestamp, then id), that Load
 * More skips a product already in the table, and that a ticked product is
 * linked to the group only once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function test_product_group_unselected_order_has_id_tie_breaker()
{
	pg_assert_same('ORDER BY timestamp DESC, id DESC', pg_product_group_form_unselected_order_sql(), 'rows with the same timestamp keep one order across batches');

	$files = array('add_product_group.php', 'edit_product_group.php', 'includes/panel/commerce.php');
	foreach ($files as $file) {
		$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_product_group_form_unselected_order_sql()', $src, $file . ' sorts the offered products with the shared order');
	}

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	pg_assert_true(strpos($panel, "ORDER BY timestamp DESC\n             LIMIT \$limit OFFSET \$offset") === false, 'Load More no longer sorts by timestamp alone');
}

function test_product_group_load_more_skips_rows_already_shown()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

	pg_assert_contains('input[name=\\"products[]\\"][value=\\"', $js, 'a returned product is looked up by its tick box, not its row id');
	pg_assert_contains('$rows = $rows.filter(function() {', $js, 'rows already in the table are dropped before they are added');
}

function test_product_group_link_ids_once()
{
	pg_assert_same(array(5, 3), pg_product_group_form_link_ids(array('5', '3', '5', 5)), 'a product ticked twice is linked once, first order kept');
	pg_assert_same(array(7), pg_product_group_form_link_ids(array('0', '', 'x', '-2', '7')), 'no id that is not a product');
	pg_assert_same(array(), pg_product_group_form_link_ids(array()), 'nothing ticked');
}

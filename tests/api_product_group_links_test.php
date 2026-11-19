<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the group links PATCH /products writes
 * (includes/api/resources/products.php): only the groups added or taken off
 * are written, so the rows the product stays in keep their sort order,
 * featured mark and new date.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');

function test_api_product_group_links_unchanged_groups_write_nothing()
{
	pg_assert_same(
		array(),
		api_products_group_link_statements(7, array('4', '9'), array(9, 4)),
		'the same groups sent again leave every row as it is');
}

function test_api_product_group_links_only_the_difference_is_written()
{
	$statements = api_products_group_link_statements(7, array(4, 9), array(9, 12));

	pg_assert_same(2, count($statements));
	pg_assert_true(
		(strpos($statements[0], 'DELETE FROM products_groups_xref') === 0)
			&& (strpos($statements[0], "product = '7'") !== false)
			&& (strpos($statements[0], 'product_group IN (4)') !== false),
		'only the group taken off is deleted: ' . $statements[0]);
	pg_assert_true(
		(strpos($statements[1], 'INSERT INTO products_groups_xref') === 0)
			&& (strpos($statements[1], "('7', '12')") !== false),
		'only the new group is added: ' . $statements[1]);

	foreach ($statements as $statement) {
		pg_assert_true(strpos($statement, "'9'") === false, 'the group kept is not touched: ' . $statement);
	}
}

function test_api_product_group_links_an_empty_list_takes_every_group_off()
{
	$statements = api_products_group_link_statements(7, array(4, 9), array());

	pg_assert_same(1, count($statements));
	pg_assert_true(strpos($statements[0], 'product_group IN (4, 9)') !== false, $statements[0]);
}

function test_api_product_group_links_the_handler_never_rewrites_every_row()
{
	$function = new ReflectionFunction('api_products_update');
	$body = implode('', array_slice(
		file($function->getFileName()),
		$function->getStartLine() - 1,
		$function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_true(
		strpos($body, "DELETE FROM products_groups_xref WHERE product = '\" . \$id . \"'\")") === false,
		'PATCH /products does not delete all of a product\'s group rows');
	pg_assert_true(strpos($body, 'api_products_group_link_statements(') !== false);
}

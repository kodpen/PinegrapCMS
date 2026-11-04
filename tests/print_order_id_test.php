<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * print_order.php reads the order by the id it checked: the existence check
 * took $_REQUEST['id'] and the order query $_GET['id'], so an id sent in the
 * form body passed the check and then read no order (PHP warnings, an empty
 * print).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_print_order_queries_the_checked_id()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/print_order.php');

	pg_assert_contains("WHERE orders.id = ?\";\n\$result = db_q(\$query, array((string) \$id))", $source, 'the order query uses the checked id');
	pg_assert_same(false, strpos($source, "\$_GET['id']") !== false, 'no raw $_GET id left');
}

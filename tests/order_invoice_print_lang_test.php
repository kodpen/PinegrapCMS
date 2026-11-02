<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The customer's invoice print (order_invoice_print.php): its refusals are
 * translated, and the page names the visitor's language rather than a fixed
 * "tr".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_invoice_print_errors_translated()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/order_invoice_print.php');

	pg_assert_same(0, preg_match("/output_error\\('/", $source), 'no output_error() with a bare English literal');

	foreach (array('E-commerce is not enabled on this site.', 'Order id is missing.', 'Order not found.', 'You are not allowed to view this invoice.') as $text) {
		pg_assert_contains("output_error(lang('" . $text . "')", $source, $text);
	}
}

function test_order_invoice_print_html_language()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/order_invoice_print.php');

	pg_assert_same(false, strpos($source, '<html lang="tr">') !== false, 'no fixed Turkish language');
	pg_assert_contains("<html lang=\"<?= h(lang(array('info' => true))) ?>\">", $source, 'the visitor language');
}

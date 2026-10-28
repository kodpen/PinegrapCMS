<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the order print views and the e-mail campaign popups: the
 * print views force the light theme (they stand for the paper), carry their
 * own window title and an icon that exists, and print_order.php's labels
 * go through lang().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function print_views_theme_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_print_views_theme_forced_light()
{
	foreach (array('print_order.php', 'print_packing_slip.php') as $path) {
		$source = print_views_theme_source($path);
		pg_assert_contains('document.documentElement.setAttribute("data-bs-theme", "light")', $source, $path);
		pg_assert_contains('document.addEventListener("DOMContentLoaded", pgPrintLight)', $source, $path);
		pg_assert_true(strpos($source, 'localStorage') === false, $path . ': writes the stored theme');
		pg_assert_true((bool) preg_match("/'icon'\\s*=>\\s*'store'/", $source), $path . ': store icon');
	}
}

function test_print_views_theme_packing_slip_title()
{
	$source = print_views_theme_source('print_packing_slip.php');

	pg_assert_contains("'title' => lang('Packing Slip')", $source);
	pg_assert_true(strpos($source, '<title>') === false, 'second <title> in the body');
}

function test_print_views_theme_campaign_popups()
{
	$source = print_views_theme_source('send_email_campaign.php');

	pg_assert_true(strpos($source, "lang('Modify Pages')") === false, 'Modify Pages title');
	pg_assert_same(2, substr_count($source, "output_header_secure(array('title'=>lang('Send E-mail Campaign'),'icon'=>'campaign'))"));
}

function test_print_views_theme_order_labels_translated()
{
	$source = print_views_theme_source('print_order.php');

	foreach (array('Fax:', 'Code:', 'Amount:', 'Recipient Email:', 'From Name:', 'Message:', 'Delivery Date:') as $label) {
		pg_assert_true(!preg_match('/>' . preg_quote($label, '/') . '</', $source), 'literal ' . $label);
	}

	pg_assert_contains("lang('Fax') . ':'", $source);
	pg_assert_contains("lang('Recipient Email') . ':'", $source);
	pg_assert_contains('pg_order_view_address_type($ship_tos[$key][\'address_type\'])', $source);
	pg_assert_true(strpos($source, "ucwords(\$ship_tos[\$key]['address_type'])") === false, 'raw address type');
}

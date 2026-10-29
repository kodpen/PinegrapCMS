<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the order screen and the printed order: the
 * verified-address check mark loads from assets/images and carries a
 * translated alternative text, and the billing Fax label is translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_view_marks_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_order_view_marks_address_verified_check_mark()
{
	foreach (array('view_order.php', 'print_order.php') as $path) {
		$source = order_view_marks_source($path);
		pg_assert_false(strpos($source, 'src="images/check_mark.gif"') !== false, $path . ': check mark points at a missing folder');
		pg_assert_false(strpos($source, 'alt="check mark"') !== false, $path . ': check mark alt is untranslated');
		pg_assert_contains("SOFTWARE_DIRECTORY . '/assets/images/check_mark.gif\" width=\"7\" height=\"7\" alt=\"' . h(lang('Yes')) . '\"", $source, $path);
	}
}

function test_order_view_marks_fax_label_translated()
{
	$source = order_view_marks_source('view_order.php');
	pg_assert_false(strpos($source, '>Fax:<') !== false, 'view_order.php: literal Fax label');
	pg_assert_contains("h(lang('Fax')) . ':</span>", $source, 'view_order.php');
}

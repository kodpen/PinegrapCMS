<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the receipt links of the ERP screens (erp_receipt_link_html(),
 * includes/erp/waybills.php): erp_receipt.php and add_erp_receipt.php are
 * cash-gated, so a receipt links only for a viewer with the cash right and
 * prints as plain text otherwise; the Record a Receipt / Record a Payment
 * buttons are drawn only for a cash clerk who is not read-only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');

function test_erp_receipt_link_links_for_a_cash_clerk()
{
	$html = erp_receipt_link_html(7, 'Collection 7', 'pg-link-body-emphasis', true);
	pg_assert_true($html === '<a href="erp_receipt.php?id=7" class="pg-link-body-emphasis">Collection 7</a>', 'the receipt is linked with the given class');

	$html = erp_receipt_link_html(7, '', '', true);
	pg_assert_true($html === '<a href="erp_receipt.php?id=7">#7</a>', 'a receipt without a label shows its id');

	$html = erp_receipt_link_html(7, '<b>', '', true);
	pg_assert_contains('&lt;b&gt;', $html, 'the label is escaped');
}

function test_erp_receipt_link_is_plain_text_without_the_cash_right()
{
	pg_assert_true(erp_receipt_link_html(7, 'Collection 7', 'pg-link-body-emphasis', false) === 'Collection 7', 'no link to "Access denied"');
	pg_assert_true(erp_receipt_link_html(7, '<b>', '', false) === '&lt;b&gt;', 'the plain label is escaped');
	pg_assert_true(erp_receipt_link_html(7, '', '', false) === '#7', 'a receipt without a label shows its id');
}

function test_erp_receipt_link_reads_the_viewer_right_when_not_given()
{
	$html = erp_receipt_link_html(7, 'R');
	$linked = defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH;
	pg_assert_true((strpos($html, '<a href="erp_receipt.php?id=7"') !== false) === $linked, 'the default follows USER_MANAGE_ERP_CASH, no link when it is not set');
}

function test_erp_receipt_link_without_an_id_is_the_label()
{
	pg_assert_true(erp_receipt_link_html(0, 'R', '', true) === 'R', 'no receipt id, no link');
}

function test_erp_receipt_link_used_by_every_receipt_link()
{
	$files = array(
		'includes/erp/drawer.php' => 1,
		'edit_erp_account.php' => 1,
		'erp_audit.php' => 1,
	);

	foreach ($files as $file => $count) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(substr_count($source, 'erp_receipt_link_html(') === $count, $file . ' draws its receipt links with the helper');
		pg_assert_false(strpos($source, 'href="erp_receipt.php') !== false, $file . ' has no hand-made receipt link');
	}
}

function test_erp_record_receipt_buttons_need_cash_and_write()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
	pg_assert_true(substr_count($source, 'href="add_erp_receipt.php?') === 2, 'two Record buttons in the drawer');
	pg_assert_true(substr_count($source, '$can_record_receipt = defined(\'USER_MANAGE_ERP_CASH\') && USER_MANAGE_ERP_CASH && !(defined(\'USER_ERP_READONLY\') && USER_ERP_READONLY);') === 2, 'both drawers ask for the cash right and no read-only right');
	$offset = 0;
	while (($at = strpos($source, 'href="add_erp_receipt.php?', $offset)) !== false) {
		$before = substr($source, max(0, $at - 300), min(300, $at));
		pg_assert_contains('($can_record_receipt', $before, 'the button at offset ' . $at . ' is drawn behind that answer');
		$offset = $at + 1;
	}
}

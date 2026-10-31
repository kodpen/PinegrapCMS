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

function test_erp_can_record_receipt_needs_cash_and_write()
{
	pg_assert_true(erp_can_record_receipt(true, false), 'a cash clerk who may write records');
	pg_assert_false(erp_can_record_receipt(false, false), 'no cash right, no button');
	pg_assert_false(erp_can_record_receipt(true, true), 'the read-only accountant gets no button');
	pg_assert_false(erp_can_record_receipt(false, true), 'neither right');
}

function test_erp_can_record_receipt_reads_the_viewer_rights_when_not_given()
{
	$expected = defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY);
	pg_assert_same($expected, erp_can_record_receipt(), 'the default follows USER_MANAGE_ERP_CASH and USER_ERP_READONLY');
}

function test_erp_record_receipt_label_follows_the_direction()
{
	pg_assert_same(lang('Record a Payment'), erp_record_receipt_label('purchase'), 'a purchase invoice is paid');
	pg_assert_same(lang('Record a Receipt'), erp_record_receipt_label('sales'), 'a sales invoice is collected');
}

function test_erp_record_receipt_buttons_need_cash_and_write()
{
	$files = array(
		'includes/erp/drawer.php' => 2,
		'edit_erp_invoice.php' => 1,
	);

	foreach ($files as $file => $count) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_same($count, substr_count($source, 'href="add_erp_receipt.php?'), $file . ' draws its Record buttons');
		pg_assert_same($count, substr_count($source, '$can_record_receipt = erp_can_record_receipt();'), $file . ' asks erp_can_record_receipt() for each');
		$offset = 0;
		while (($at = strpos($source, 'href="add_erp_receipt.php?', $offset)) !== false) {
			$before = substr($source, max(0, $at - 300), min(300, $at));
			pg_assert_contains('($can_record_receipt', $before, $file . ': the button at offset ' . $at . ' is drawn behind that answer');
			$offset = $at + 1;
		}
	}

	$invoice = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');
	pg_assert_contains("erp_record_receipt_label((string) \$invoice['direction'])", $invoice, 'the invoice screen names the button by direction');
}

function test_erp_can_open_receipt_follows_the_cash_right()
{
	pg_assert_true(erp_can_open_receipt(true), 'given right');
	pg_assert_false(erp_can_open_receipt(false), 'given no right');
	pg_assert_same(defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH, erp_can_open_receipt(), 'the default follows USER_MANAGE_ERP_CASH');
}

// The invoice screen's allocation rows show the receipt icon only for a cash
// clerk: erp_receipt.php would answer "Access denied" to anyone else.
function test_erp_invoice_screen_receipt_icon_needs_the_cash_right()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');
	$at = strpos($source, 'href="erp_receipt.php?id=');
	pg_assert_true($at !== false, 'the icon is still there');
	pg_assert_contains('erp_can_open_receipt()', substr($source, max(0, $at - 400), min(400, $at)), 'and drawn behind erp_can_open_receipt()');
	pg_assert_same(1, substr_count($source, 'href="erp_receipt.php'), 'no other receipt link on the screen');
}

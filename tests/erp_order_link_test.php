<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order cell of the ERP invoice and delivery note screens
 * (erp_order_link_html(), includes/erp/waybills.php): an order that is still
 * there links to view_order.php for a viewer with commerce rights and prints
 * as plain text for one without, a deleted one prints "#N (deleted)" with no
 * link, and the six places that show a document's order use the helper.
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

function test_erp_order_link_links_an_order_that_is_still_there()
{
	$html = erp_order_link_html(42, 'PG-1001', true, '', true);
	pg_assert_contains('<a href="view_order.php?id=42">', $html, 'the order is linked');
	pg_assert_contains('PG-1001</a>', $html, 'by its number');

	$html = erp_order_link_html(42, '', true, 'pg-link-body-emphasis', true);
	pg_assert_contains('<a href="view_order.php?id=42" class="pg-link-body-emphasis">#42</a>', $html, 'an order without a number shows its id, with the given class');

	$html = erp_order_link_html(42, '<b>', true, '', true);
	pg_assert_contains('&lt;b&gt;', $html, 'the number is escaped');
}

function test_erp_order_link_is_plain_text_for_a_clerk_without_commerce_rights()
{
	$html = erp_order_link_html(42, 'PG-1001', true, 'pg-link-body-emphasis', false);
	pg_assert_true($html === 'PG-1001', 'the number without a link to "Access denied"');

	$html = erp_order_link_html(42, '<b>', true, '', false);
	pg_assert_true($html === '&lt;b&gt;', 'the plain number is escaped');

	$html = erp_order_link_html(42, '', true, '', false);
	pg_assert_true($html === '#42', 'an order without a number shows its id');
}

function test_erp_order_link_reads_the_viewer_right_when_not_given()
{
	$html = erp_order_link_html(42, 'PG-1001', true);
	$linked = defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE;
	pg_assert_true((strpos($html, '<a href="view_order.php?id=42">') !== false) === $linked, 'the default follows USER_MANAGE_ECOMMERCE, no link when it is not set');
}

function test_erp_order_link_marks_a_deleted_order_without_a_link()
{
	$html = erp_order_link_html(42, null, false);
	pg_assert_false(strpos($html, '<a') !== false, 'no link to a 404');
	pg_assert_contains('#42', $html, 'the id stays readable');
	pg_assert_contains(h(lang('(deleted)')), $html, 'marked as deleted');
}

function test_erp_order_link_is_empty_without_an_order()
{
	pg_assert_true(erp_order_link_html(0, null, false) === '', 'no order, no cell content');
	pg_assert_true(erp_order_link_html(-3, 'X', true) === '', 'a negative id is no order');
}

function test_erp_order_link_used_by_every_order_cell()
{
	$files = array(
		'erp_invoices.php' => 1,
		'edit_erp_invoice.php' => 1,
		'erp_waybills.php' => 1,
		'edit_erp_waybill.php' => 1,
		'includes/erp/drawer.php' => 2,
	);

	foreach ($files as $file => $count) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(substr_count($source, 'erp_order_link_html(') === $count, $file . ' draws its order cell with the helper');
		pg_assert_false(strpos($source, '<a href="view_order.php?id=') !== false, $file . ' links no order by hand');
	}
}

function test_erp_order_queries_read_whether_the_order_exists()
{
	$files = array('erp_invoices.php', 'edit_erp_invoice.php', 'erp_waybills.php', 'includes/erp/waybills.php', 'includes/erp/drawer.php');

	foreach ($files as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('o.id AS order_exists', $source, $file . ' reads whether the joined order is still there');
	}
}

function test_erp_accounts_links_only_contacts_that_exist()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accounts.php');
	pg_assert_contains('SELECT id FROM contacts WHERE id IN (', $source, 'the page asks once which linked contacts still exist');
	pg_assert_contains('isset($live_contacts[$contact_id])', $source, 'a missing contact is treated as no contact');
	pg_assert_contains('pg_can_open_contact($user, $contact_id, $contacts_access)', $source, 'the rights check stays');
}

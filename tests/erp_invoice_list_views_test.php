<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice register and its neighbours: a repeat whose last
 * draft was deleted links nowhere, the e-document menu keeps the due-date
 * view, a read-only accountant is offered no write, and the side panel
 * follows the register's e-document rule for a cancelled invoice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _erp_invoice_list_views_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The last-run link is built from the joined invoice, not the stored id, and
// deleting a draft clears the id inside the delete's transaction.
function test_erp_invoice_repeat_last_link_needs_the_invoice()
{
	pg_assert_contains('l.id AS last_found_id', _erp_invoice_list_views_source('includes/erp/invoice_recurring.php'));
	pg_assert_contains("(((int) (\$row['last_found_id'] ?? 0) > 0)", _erp_invoice_list_views_source('erp_invoice_recurrences.php'));

	$api = _erp_invoice_list_views_source('includes/erp/api_documents.php');
	pg_assert_contains('l.id AS last_found_id', $api);
	pg_assert_contains("'last_invoice_id' => (int) (\$row['last_found_id'] ?? 0),", $api);

	$manual = _erp_invoice_list_views_source('includes/erp/invoice_manual.php');
	$start = strpos($manual, 'function erp_invoice_draft_delete');
	$body = substr($manual, $start, strpos($manual, 'erp_tx_commit()', $start) - $start);
	pg_assert_contains('UPDATE erp_invoice_recurrences SET last_invoice_id = 0 WHERE last_invoice_id = ', $body);
}

// Each e-document item and "All" are built from the screen's own view, with
// only the e-document key changed.
function test_erp_invoice_edoc_menu_keeps_the_view()
{
	$source = _erp_invoice_list_views_source('erp_invoices.php');

	pg_assert_false(strpos($source, 'href="erp_invoices.php?edoc=') !== false, 'no fixed e-document address');
	pg_assert_false(strpos($source, 'href="erp_invoices.php">') !== false, 'no bare "All" address');
	pg_assert_contains("unset(\$edoc_base_query['edoc']);", $source);
	pg_assert_contains("http_build_query(\$edoc_base_query + array('edoc' => \$edoc_key))", $source);
}

// The read-only accountant gets neither the new-invoice links nor the bulk
// e-document form, buttons and ticks.
function test_erp_invoice_register_hides_writes_from_readonly()
{
	$source = _erp_invoice_list_views_source('erp_invoices.php');

	pg_assert_contains("\$readonly = (defined('USER_ERP_READONLY') && USER_ERP_READONLY);", $source);
	pg_assert_contains("\$edoc_bulk = (\$edoc_provider !== '') && !\$readonly;", $source);
	pg_assert_contains("(\$readonly ? '' : '<a class=\"btn btn-sm btn-solid theme-primary rounded-pill px-5\" href=\"add_erp_manual_invoice.php\">", $source);
}

// A cancelled invoice never sent shows no e-document badge in the panel, and
// the full-page link is an icon on a narrow screen so the number keeps its line.
function test_erp_invoice_drawer_edoc_rule_and_header()
{
	$source = _erp_invoice_list_views_source('includes/erp/drawer.php');

	pg_assert_contains("(!\$edoc_unsent || ((erp_edoc_active() !== '') && ((string) \$invoice['status'] !== 'cancelled')))", $source);
	pg_assert_contains('<span class="pg-btn-label d-none sm:d-inline ms-1">', $source);
}

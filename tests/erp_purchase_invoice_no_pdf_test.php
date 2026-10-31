<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the purchase invoice's document: the store keeps and serves a
 * rendering of its own only for a sales invoice. A purchase invoice's document
 * is the supplier's, so the archive keeps none for it, the accountant pack
 * takes only the provider's copy, the invoice screen draws no PDF / Download
 * and get_erp_invoice_pdf.php answers it with a notice page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/archive.php');

// The body of a PHP function in a source file, for pins on its calls.
function erp_purchase_no_pdf_function_source_for_test($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' is in ' . $file);

	$next = strpos($source, "\nfunction ", $start + 10);

	return ($next !== false) ? substr($source, $start, $next - $start) : substr($source, $start);
}

function test_erp_archive_keeps_a_rendering_only_for_a_sales_invoice()
{
	pg_assert_true(erp_archive_keeps_rendering('sales'), 'a sales invoice');
	pg_assert_true(!erp_archive_keeps_rendering('purchase'), 'not a purchase invoice');
	pg_assert_true(!erp_archive_keeps_rendering(''), 'not an unknown direction');
	pg_assert_true(!erp_archive_keeps_rendering(null), 'not a missing direction');
}

// The issue defers the archive only for a sales invoice.
function test_erp_event_invoice_defers_the_archive_only_for_sales()
{
	$body = erp_purchase_no_pdf_function_source_for_test('includes/erp/events.php', 'erp_event_invoice');

	pg_assert_true((bool) preg_match("/erp_archive_keeps_rendering\(\(string\) \\\$invoice\['direction'\]\)[^;]*\{\s*erp_archive_defer\('invoice'/", $body), 'the deferral is gated on the direction');
}

// A direct caller cannot keep a rendering of a purchase invoice.
function test_erp_archive_invoice_refuses_a_purchase()
{
	$body = erp_purchase_no_pdf_function_source_for_test('includes/erp/archive.php', 'erp_archive_invoice');

	pg_assert_contains('SELECT id, full_number, status, direction FROM erp_invoices', $body, 'it reads the direction');
	pg_assert_contains("!erp_archive_keeps_rendering((string) \$invoice['direction'])", $body, 'and refuses a purchase');
}

// The pack takes the provider's copy of a purchase and nothing of ours.
function test_erp_accountant_pack_takes_only_the_providers_copy_of_a_purchase()
{
	$body = erp_purchase_no_pdf_function_source_for_test('includes/erp/accountant.php', 'erp_accountant_document_file');

	pg_assert_true((bool) preg_match("/erp_archive_file\('edoc', \\\$id\);\s*if \(\(\\\$file === null\) && erp_archive_keeps_rendering\(\(string\) \\\$entry\['direction'\]\)\)/", $body), 'the kept copy and the render are for sales only');
	pg_assert_true(strpos($body, "\$entry['direction'] === 'sales'") === false, 'one direction check, through the archive');
}

// The invoice screen and its drawer draw no PDF / Download for a purchase.
function test_erp_invoice_screen_draws_no_pdf_for_a_purchase()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');

	pg_assert_contains("\$has_own_pdf = erp_archive_keeps_rendering((string) \$invoice['direction']);", $screen, 'the screen asks the archive');
	pg_assert_true((bool) preg_match('/\(\$has_own_pdf \? \'\s*<a [^>]*get_erp_invoice_pdf\.php\?id=\' \. \$invoice_id \. \'"[^\n]*\n[^\n]*get_erp_invoice_pdf\.php\?id=\' \. \$invoice_id \. \'&amp;download=1/', $screen), 'PDF and Download are drawn only then');

	$drawer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');

	pg_assert_contains("(!\$is_draft && erp_archive_keeps_rendering((string) \$invoice['direction'])) ? '<a class=\"btn btn-sm btn-outline theme-secondary\" href=\"get_erp_invoice_pdf.php", $drawer, 'the drawer the same');
}

// The PDF endpoint answers a purchase invoice with the notice page, before the
// kept copy, the ?html=1 rendering or the download.
function test_erp_invoice_pdf_answers_a_purchase_with_a_notice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_erp_invoice_pdf.php');

	pg_assert_contains('SELECT id, full_number, status, direction FROM erp_invoices', $source, 'it reads the direction');

	$notice = strpos($source, "lang('A purchase invoice has no document of the store\\'s own; the supplier\\'s document is the invoice.')");
	$gate = strpos($source, "!erp_archive_keeps_rendering((string) \$invoice['direction'])");
	$kept = strpos($source, "erp_archive_file('invoice', \$invoice_id)");
	$html = strpos($source, 'erp_invoice_html($invoice_id');

	pg_assert_true($notice !== false, 'the notice text');
	pg_assert_true(($gate !== false) && ($gate < $notice), 'given on the direction');
	pg_assert_true(($kept !== false) && ($notice < $kept), 'before the kept copy is sent');
	pg_assert_true(($html !== false) && ($notice < $html), 'before any rendering');
}

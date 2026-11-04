<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two rules of the invoice view (edit_erp_invoice.php):
 * the note of an issued invoice is internal and saved through
 * erp_invoice_notes_save() (includes/erp/invoice_manual.php), which announces
 * erp.invoice.note_changed for the audit trail; and a cancelled invoice's PDF
 * (get_erp_invoice_pdf.php) is drawn live with a CANCELLED ribbon
 * (label.cancelled, erp_invoice_html_render() in includes/erp/document.php)
 * instead of the unmarked copy kept at issue.
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

require_once(PG_FUNCTIONS_DIR . '/includes/erp/document.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/audit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

function test_erp_invoice_note_clean_trims_and_cuts()
{
	pg_assert_same('Call before delivery', erp_invoice_notes_clean("  Call before delivery \n"), 'trimmed');
	pg_assert_same('', erp_invoice_notes_clean(array('x')), 'an array is no note');
	pg_assert_same('', erp_invoice_notes_clean(null), 'nothing is no note');

	$long = erp_invoice_notes_clean(str_repeat('ş', 5003));
	pg_assert_same(5000, mb_strlen($long), 'cut at 5,000 characters, not bytes');
}

// The screen saves through the module function, never with a bare UPDATE
// that skips the event and the audit trail.
function test_erp_invoice_note_screen_uses_the_module_function()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');

	pg_assert_contains('erp_invoice_notes_save($invoice_id', $screen, 'the notes branch calls the module');
	pg_assert_false(strpos($screen, 'UPDATE erp_invoices SET notes') !== false, 'no bare UPDATE of the note on the screen');
	pg_assert_false(strpos($screen, 'Printed at the foot of the document. The only part') !== false, 'the card no longer says the note is printed');
	pg_assert_contains("lang('An internal note, never printed on the issued document:", $screen, 'the card says the note is internal');
}

function test_erp_invoice_note_change_is_an_announced_event()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');

	pg_assert_contains("erp_event_invoice(\$invoice_id, 'erp.invoice.note_changed'", $module, 'the save announces the change');
	pg_assert_true(isset(erp_webhook_events()['erp.invoice.note_changed']), 'the event is declared, so the queue keeps it');
	pg_assert_same(lang('Invoice note changed'), erp_audit_event_text(array('event' => 'erp.invoice.note_changed')), 'the trail names it');
}

function test_erp_invoice_cancelled_default_template_prints_the_ribbon()
{
	$data = array(
		'invoice' => array('is_cancelled' => true, 'title' => 'INVOICE', 'full_number' => 'A2026000001'),
		'lines' => array(),
		'totals' => array(),
		'label' => array('cancelled' => 'L_CANCELLED'),
	);

	$html = erp_invoice_html_render(erp_invoice_default_template(), $data);
	pg_assert_contains('<div class="cancelled-ribbon">L_CANCELLED</div>', $html, 'the ribbon at the top');
	pg_assert_contains('<span class="cancelled">L_CANCELLED</span>', $html, 'and in the footer of every page');
	pg_assert_false(strpos($html, 'pg-cancelled-ribbon') !== false, 'the template prints it itself, nothing is put in');

	$data['invoice']['is_cancelled'] = false;
	$html = erp_invoice_html_render(erp_invoice_default_template(), $data);
	pg_assert_false(strpos($html, 'L_CANCELLED') !== false, 'an invoice in force carries no ribbon');
}

// A template saved before the ribbon existed still marks a cancelled invoice.
function test_erp_invoice_cancelled_old_template_gets_the_ribbon_put_in()
{
	$template = '<html><head><title>{{invoice.full_number}}</title></head><body class="x"><h1>{{invoice.full_number}}</h1></body></html>';
	$data = array('invoice' => array('is_cancelled' => true, 'full_number' => 'A1'), 'label' => array('cancelled' => 'İPTAL <EDİLDİ>'));

	$html = erp_invoice_html_render($template, $data);
	pg_assert_true((bool) preg_match('~<body class="x"><div class="pg-cancelled-ribbon"[^>]*>İPTAL &lt;EDİLDİ&gt;</div><h1>A1</h1>~u', $html), 'right after the body tag, escaped');

	$data['invoice']['is_cancelled'] = false;
	pg_assert_false(strpos(erp_invoice_html_render($template, $data), 'pg-cancelled-ribbon') !== false, 'not on an invoice in force');
}

function test_erp_document_ribbon_add_without_a_body_tag()
{
	$html = erp_document_ribbon_add('<p>x</p>', 'CANCELLED');

	pg_assert_same(0, strpos($html, '<div class="pg-cancelled-ribbon"'), 'in front of everything');
	pg_assert_contains('>CANCELLED</div><p>x</p>', $html, 'the document follows it');
}

// The endpoint draws a cancelled invoice live and never keeps that drawing.
function test_erp_invoice_cancelled_pdf_is_drawn_live_and_not_kept()
{
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_erp_invoice_pdf.php');

	pg_assert_contains("\$cancelled = ((string) \$invoice['status'] === 'cancelled');", $endpoint, 'the status is read');
	pg_assert_contains("|| !empty(\$_GET['html']) || \$cancelled);", $endpoint, 'a cancelled invoice skips the kept copy');
	pg_assert_contains("((string) \$invoice['status'] !== 'draft') && !\$cancelled", $endpoint, 'the marked drawing is never stored as the kept copy');

	$document = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/document.php');
	pg_assert_contains("'cancelled' => lang('CANCELLED'),", $document, 'label.cancelled is one of the invoice captions');
}

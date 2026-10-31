<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the template Preview of ERP Settings
 * (erp_template_preview_document() in includes/erp/settings_cards.php): the
 * preview is the PDF dompdf makes from the template, the one that is
 * downloaded, e-mailed and kept, and falls back to the HTML with a note only
 * when no PDF could be made. The three preview endpoints are read to pin that
 * a posted template leaves before anything is kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/settings_cards.php');

// A PDF that renders is the preview, as it is.
function test_erp_template_preview_document_is_the_pdf()
{
	$seen = null;
	$preview = erp_template_preview_document('<html><body>X</body></html>', function ($html) use (&$seen) {
		$seen = $html;
		return '%PDF-1.7 fake';
	});

	pg_assert_same('pdf', $preview['type'], 'a PDF');
	pg_assert_same('%PDF-1.7 fake', $preview['body'], 'the rendered bytes');
	pg_assert_same('<html><body>X</body></html>', $seen, 'the posted template is what is rendered');
}

// No library: the HTML, with one line saying the PDF may differ.
function test_erp_template_preview_document_without_the_library()
{
	$preview = erp_template_preview_document('<html><body class="a"><p>X</p></body></html>', function () {
		return false;
	});

	pg_assert_same('html', $preview['type'], 'HTML');
	pg_assert_contains('<body class="a"><div', $preview['body'], 'the note opens the body');
	pg_assert_contains(h(lang('The PDF could not be made, so this preview shows the HTML it is made from; the PDF may look different.')), $preview['body'], 'the note');
	pg_assert_contains('<p>X</p>', $preview['body'], 'the document follows');
}

// A render that throws falls back too, and a document without a body still gets the note.
function test_erp_template_preview_document_when_the_render_throws()
{
	$preview = erp_template_preview_document('<p>X</p>', function () {
		throw new \RuntimeException('bad CSS');
	});

	pg_assert_same('html', $preview['type'], 'HTML');
	pg_assert_same(0, strpos($preview['body'], '<div'), 'the note first');
	pg_assert_true(strpos($preview['body'], 'bad CSS') === false, 'the error text is not shown');
}

// Each endpoint sends a posted template through the preview before any
// kept copy is read or written, and ?html=1 alone stays HTML.
function test_erp_template_preview_endpoints_never_keep_a_preview()
{
	foreach (array('get_erp_invoice_pdf.php', 'get_erp_waybill_pdf.php', 'get_erp_reconciliation_pdf.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$send = strpos($source, 'erp_template_preview_send($html)');

		pg_assert_true($send !== false, $file . ': the preview is sent');
		pg_assert_true(strpos($source, "header('Content-Type: text/html; charset=utf-8');\n    header('X-Robots-Tag: noindex');\n    echo \$html;") !== false, $file . ': ?html=1 stays HTML');

		$store = strpos($source, 'erp_archive_store(');
		pg_assert_true(($store === false) || ($store > $send), $file . ': nothing is kept before the preview leaves');
		pg_assert_true(strpos($source, '$pdf = erp_invoice_pdf($html);') > $send, $file . ': the document PDF comes after');
	}
}

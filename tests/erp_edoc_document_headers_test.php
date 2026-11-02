<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the headers get_erp_edoc_document.php sends with the provider's
 * copy of an e-document (erp_edoc_document_headers()): a page the provider
 * rendered runs in a sandbox, never on the panel's origin, and the endpoint
 * sends them with nosniff.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');

// The function's name is the header, its value what follows the colon.
function erp_edoc_document_headers_test_map($headers)
{
	$map = array();

	foreach ($headers as $header) {
		$parts = explode(':', $header, 2);
		$map[strtolower(trim($parts[0]))] = trim((string) ($parts[1] ?? ''));
	}

	return $map;
}

// İşbaşı answers the PDF address with an HTML rendering, shown inline: it
// ran on the panel's origin with the operator's session. It now runs in an
// opaque origin; its scripts (the QR code) and printing still work.
function test_erp_edoc_document_headers_html_is_sandboxed()
{
	$map = erp_edoc_document_headers_test_map(erp_edoc_document_headers('text/html; charset=utf-8', 'EAR2026000000001.html', false));

	pg_assert_same('text/html; charset=utf-8', $map['content-type']);
	pg_assert_same('inline; filename="EAR2026000000001.html"', $map['content-disposition']);
	pg_assert_true(isset($map['content-security-policy']), 'a provider page carries a policy');
	pg_assert_true(strpos($map['content-security-policy'], 'sandbox') === 0, 'the policy is a sandbox');
	pg_assert_true(strpos($map['content-security-policy'], 'allow-same-origin') === false, 'never the panel origin');
	pg_assert_contains('allow-scripts', $map['content-security-policy']);
	pg_assert_contains('allow-modals', $map['content-security-policy'], 'print() still opens its dialog');
	pg_assert_same('noindex', $map['x-robots-tag']);
	pg_assert_same('private, no-store', $map['cache-control']);
}

// Whatever the provider hands back is sandboxed, a PDF included: a viewer
// that is not the browser's own gets nothing from the origin either.
function test_erp_edoc_document_headers_every_kind_is_sandboxed()
{
	foreach (array('application/pdf', 'application/xml', 'application/zip', 'application/octet-stream', '') as $mime) {
		foreach (array(false, true) as $download) {
			$map = erp_edoc_document_headers_test_map(erp_edoc_document_headers($mime, 'x.bin', $download));
			pg_assert_true(isset($map['content-security-policy']) && (strpos($map['content-security-policy'], 'sandbox') === 0), 'sandboxed: ' . $mime);
		}
	}
}

function test_erp_edoc_document_headers_disposition()
{
	$map = erp_edoc_document_headers_test_map(erp_edoc_document_headers('application/pdf', 'a.pdf', true));
	pg_assert_same('attachment; filename="a.pdf"', $map['content-disposition']);
	pg_assert_same('application/pdf', $map['content-type']);

	// No type from the provider is saved as bytes, never guessed.
	$map = erp_edoc_document_headers_test_map(erp_edoc_document_headers('', 'a.bin', true));
	pg_assert_same('application/octet-stream', $map['content-type']);
}

// The endpoint sends the list and nosniff; no header of its own beside them.
function test_erp_edoc_document_endpoint_uses_the_headers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_erp_edoc_document.php');

	pg_assert_contains('erp_edoc_document_headers(', $source);
	pg_assert_contains('pg_send_nosniff();', $source);
	pg_assert_false((bool) preg_match("/header\\('Content-(Type|Disposition)/", $source), 'no type or disposition of its own');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the PDF merger (includes/fn/pdf_merge.php): pages of several
 * documents in one, in order, with what a page inherits from its page tree
 * copied onto it, objects kept in object streams, and documents that cannot
 * be read left out. The documents are written here by hand, plus one dompdf
 * renders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A document from object bodies (number => body), a classic xref table.
function pdf_merge_test_document($objects, $root)
{
	$out = "%PDF-1.4\n";
	$offsets = array();

	foreach ($objects as $number => $body) {
		$offsets[$number] = strlen($out);
		$out .= $number . " 0 obj\n" . $body . "\nendobj\n";
	}

	$xref = strlen($out);
	$out .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";

	for ($number = 1; $number <= max(array_keys($objects)); $number++) {
		$out .= sprintf('%010d 00000 n ', isset($offsets[$number]) ? $offsets[$number] : 0) . "\n";
	}

	return $out . "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root " . $root . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

// One page, its content stream holding the word "endobj" and its own size.
function pdf_merge_test_one()
{
	$content = "BT /F1 12 Tf 10 10 Td (endobj ONE) Tj ET";

	return pdf_merge_test_document(array(
		1 => "<< /Type /Catalog /Pages 2 0 R >>",
		2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
		3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 283 425] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
		4 => "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
		5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
	), 1);
}

// Two pages under a nested tree that gives them their size and resources;
// the page tree itself sits in a compressed object stream.
function pdf_merge_test_two()
{
	$inner = array(
		2 => "<< /Type /Pages /Kids [6 0 R] /Count 2 /MediaBox [0 0 298 421] /Resources << /Font << /F1 7 0 R >> >> >>",
		6 => "<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 4 0 R] /Count 2 /Rotate 90 >>",
	);
	$header = '';
	$body = '';

	foreach ($inner as $number => $text) {
		$header .= $number . ' ' . strlen($body) . ' ';
		$body .= $text . "\n";
	}

	$stream = gzcompress($header . $body);
	$page = "BT /F1 12 Tf 10 10 Td (TWO) Tj ET";

	return pdf_merge_test_document(array(
		1 => "<< /Type /Catalog /Pages 2 0 R >>",
		3 => "<< /Type /Page /Parent 6 0 R /Contents 5 0 R >>",
		4 => "<< /Type /Page /Parent 6 0 R /Contents 5 0 R /MediaBox [0 0 100 100] >>",
		5 => "<< /Length " . strlen($page) . " >>\nstream\n" . $page . "\nendstream",
		7 => "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>",
		8 => "<< /Type /ObjStm /N 2 /First " . strlen($header) . " /Filter /FlateDecode /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
	), 1);
}

// The merged file read back: its pages in order, each with its dictionary.
function pdf_merge_test_pages($pdf)
{
	$document = pg_pdf_parse($pdf);
	pg_assert_true($document !== null, 'the merged file reads');

	return array_map(function ($number) use ($document) {
		return $document['objects'][$number];
	}, pg_pdf_page_numbers($document));
}

function test_pdf_merge_keeps_every_page_in_order()
{
	$merged = pg_pdf_merge(array(pdf_merge_test_one(), pdf_merge_test_two(), pdf_merge_test_one()));
	$pages = pdf_merge_test_pages($merged);

	pg_assert_same(4, count($pages), 'four pages');
	pg_assert_same(0, strpos($merged, '%PDF-'), 'a PDF');
	pg_assert_true(pg_label_document_valid($merged, 'pdf'), 'a label document');
	pg_assert_same(1, preg_match('#/MediaBox \[0 0 283 425\]#', $pages[0]), 'first page keeps its size');
	pg_assert_same(1, preg_match('#/MediaBox \[0 0 298 421\]#', $pages[1]), 'inherited size copied on');
	pg_assert_same(1, preg_match('#/Rotate 90#', $pages[1]), 'inherited rotation copied on');
	pg_assert_same(1, preg_match('#/Resources#', $pages[1]), 'inherited resources copied on');
	pg_assert_same(1, preg_match('#/MediaBox \[0 0 100 100\]#', $pages[2]), 'own size wins');
	pg_assert_same(0, preg_match('#/MediaBox \[0 0 298 421\]#', $pages[2]), 'and is not doubled');
	pg_assert_same(1, preg_match('#/MediaBox \[0 0 283 425\]#', $pages[3]), 'third document');

	foreach ($pages as $index => $page) {
		pg_assert_same(1, preg_match_all('#/Parent\s+\d+\s+0\s+R#', $page), 'one parent on page ' . $index);
	}

	pg_assert_true(strpos($merged, '(endobj ONE)') !== false, 'stream content kept whole');
	pg_assert_true(strpos($merged, '/ObjStm') === false, 'object streams unpacked');
}

// Every reference points at an object the file has, and the xref table
// points at each object's first byte.
function test_pdf_merge_references_and_xref_agree()
{
	$merged = pg_pdf_merge(array(pdf_merge_test_two(), pdf_merge_test_one()));

	preg_match('#startxref\s+(\d+)#', $merged, $match);
	$xref = (int) $match[1];
	pg_assert_same('xref', substr($merged, $xref, 4), 'startxref points at the table');

	preg_match_all('#^(\d{10}) 00000 n #m', substr($merged, $xref), $entries);

	foreach ($entries[1] as $index => $offset) {
		pg_assert_same(($index + 1) . ' 0 obj', substr($merged, (int) $offset, strlen(($index + 1) . ' 0 obj')), 'object ' . ($index + 1));
	}

	preg_match_all('#(\d+) 0 R#', $merged, $references);

	foreach (array_unique($references[1]) as $number) {
		pg_assert_true((int) $number >= 1 && (int) $number <= count($entries[1]), 'reference ' . $number . ' resolves');
	}
}

// What cannot be read is left out and said so; nothing readable gives ''.
function test_pdf_merge_leaves_out_what_it_cannot_read()
{
	$encrypted = str_replace('/Root 1 0 R', '/Root 1 0 R /Encrypt 9 0 R', pdf_merge_test_one());
	$skipped = array();
	$merged = pg_pdf_merge(array('not a pdf', pdf_merge_test_one(), $encrypted, ''), $skipped);

	pg_assert_same(1, count(pdf_merge_test_pages($merged)), 'the readable one');
	pg_assert_same(array(0, 2, 3), $skipped, 'the others named by position');
	pg_assert_same('', pg_pdf_merge(array('not a pdf')), 'nothing readable');
	pg_assert_same('', pg_pdf_merge(array()), 'nothing at all');
}

// A document from the bundled renderer, the way the note page is made.
function test_pdf_merge_takes_a_dompdf_document()
{
	$rendered = pg_privacy_export_pdf('<meta charset="utf-8"><p>Kargo etiketi</p>');

	if (!is_string($rendered)) {
		return;
	}

	pg_assert_same(3, count(pdf_merge_test_pages(pg_pdf_merge(array($rendered, pdf_merge_test_two())))), 'one and two pages');
}

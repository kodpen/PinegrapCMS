<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the verdicts a stored form signature is shown with
 * (pg_signature_verdicts() in includes/fn/signature.php): the drawing on disk
 * is checked beside the record seal and the document, so a replaced drawing
 * is never shown as intact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A drawing that no longer hashes to what was signed is named as changed,
// even when the seal and the document both still match.
function test_signature_verdicts_name_a_replaced_drawing()
{
	$verdicts = pg_signature_verdicts(true, true, false);

	pg_assert_contains(lang('Record intact'), $verdicts);
	pg_assert_contains(lang('Document unchanged'), $verdicts);
	pg_assert_contains(lang('Signature file changed since it was signed'), $verdicts);
	pg_assert_false(in_array(lang('Signature file unchanged'), $verdicts, true), 'a replaced drawing is not unchanged');
}

// A drawing that still matches says so.
function test_signature_verdicts_name_an_unchanged_drawing()
{
	pg_assert_same(
		array(lang('Record intact'), lang('Document unchanged'), lang('Signature file unchanged')),
		pg_signature_verdicts(true, true, true));
}

// A record written before the drawing's digest was kept has nothing to
// compare with, so no verdict about the drawing is given.
function test_signature_verdicts_leave_out_an_unrecorded_drawing()
{
	pg_assert_same(
		array(lang('Record does not match its seal'), lang('Document not recorded')),
		pg_signature_verdicts(false, null, null));
}

// The display line is built from the verdicts, so the drawing's check is
// read wherever a submitted signature is shown.
function test_signature_display_reads_the_drawing_check()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/signature.php');
	$start = strpos($source, 'function pg_signature_display(');
	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains('pg_signature_image_matches($record)', $body);
	pg_assert_contains('pg_signature_verdicts(', $body);
}

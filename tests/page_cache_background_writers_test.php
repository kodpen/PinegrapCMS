<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page cache and the writers the general job and the translation job
 * run outside a signed-in request: a content import worked by the job, the
 * server engines' translation batches and the bulk move to the visual
 * designer. None of them passes the request rule of
 * pg_page_cache_request_dirties(), so each has to mark the cache itself or
 * the file cache keeps serving the old page for the TTL.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');

/**
 * The body of a function in a product file, from its name to the first
 * closing brace at the start of a line.
 */
function pg_test_pcbw_function_body($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}", $start);

	return substr($source, $start, ($end === false) ? null : ($end - $start));
}

// Every kind a visitor may see on a page marks the cache; contacts and
// authors appear on no stored page.
function test_page_cache_import_kind_dirties()
{
	foreach (array('record', 'page', 'file', 'comment', 'product', 'redirect') as $kind) {
		pg_assert_true(pg_import_kind_dirties_pages($kind), $kind);
	}

	pg_assert_false(pg_import_kind_dirties_pages('contact'), 'contact');
	pg_assert_false(pg_import_kind_dirties_pages('user'), 'user');
	pg_assert_false(pg_import_kind_dirties_pages(''), 'empty');

	// Every writer kind is sorted on purpose.
	foreach (array_keys(pg_import_writer_kinds()) as $kind) {
		pg_assert_true(in_array($kind, array('record', 'page', 'file', 'comment', 'product', 'redirect', 'contact', 'user'), true), 'sorted: ' . $kind);
	}
}

// The shared write step marks the cache for a made item: the products a
// WooCommerce or Shopify import makes in the general job announce nothing.
function test_page_cache_import_write_marks_dirty()
{
	$body = pg_test_pcbw_function_body('includes/fn/importers.php', 'pg_import_write');

	pg_assert_contains('pg_import_kind_dirties_pages($kind)', $body, 'asks the kind rule');
	pg_assert_contains('pg_page_cache_mark_dirty()', $body, 'marks the cache');
}

// Translations a server engine finishes in the scheduled job show on the
// translated pages at once.
function test_page_cache_translation_job_marks_dirty()
{
	$body = pg_test_pcbw_function_body('includes/translate/jobs.php', 'pg_tr_jobs_run_due');

	pg_assert_contains('pg_page_cache_mark_dirty()', $body, 'marks the cache');
}

// A style moved to the visual designer draws its pages anew.
function test_page_cache_legacy_to_designer_marks_dirty()
{
	$body = pg_test_pcbw_function_body('includes/fn/legacy_to_designer_run.php', 'pg_ltd_run');

	pg_assert_contains('pg_page_cache_mark_dirty()', $body, 'marks the cache');
}

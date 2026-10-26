<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the translation writes of the external API
 * (includes/translate/api.php) can be rehearsed with X-Dry-Run: each route
 * carries the flag, each handler stops before its first write, and a
 * translation is judged without being written. Needs no database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

// The handler's source with its comments taken out.
function translate_api_dry_run_test_source($handler)
{
	$function = new ReflectionFunction($handler);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

// Every translation write takes X-Dry-Run and stops before it writes: a
// single write stops directly above it, a batch judges every item, skips the
// writes and stops with the per-item result.
function test_translate_api_writes_can_be_rehearsed()
{
	$routes = array();

	foreach (translate_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array(
		'translations.write' => array('translate_api_write', 'log_activity('),
		'translations.refresh' => array('translate_api_refresh', 'pg_tr_scope_extract('),
		'translations.status' => array('translate_api_status', 'log_activity('),
		'translations.glossary.save' => array('translate_api_glossary_save', 'INSERT INTO translation_glossary'),
		'translations.jobs.claim' => array('translate_api_jobs_claim', 'UPDATE translation_jobs'),
		'translations.jobs.results' => array('translate_api_jobs_results', 'pg_tr_job_close_if_complete('),
		'translations.jobs.fail' => array('translate_api_jobs_fail', 'pg_tr_job_fail('),
	) as $id => $expect) {
		list($handler, $write) = $expect;
		$code = translate_api_dry_run_test_source($handler);
		$stop = strpos($code, 'api_dry_run_stop(');

		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes X-Dry-Run');
		pg_assert_true($stop !== false, $handler . ' stops a rehearsal');
		pg_assert_true($stop < strpos($code, $write), $handler . ' stops before ' . $write);
	}

	// The batches judge each item with the same check the write makes, and
	// write nothing while rehearsing.
	pg_assert_contains('translate_api_write_check(', translate_api_dry_run_test_source('translate_api_write'), 'a rehearsed write judges each item');
	pg_assert_contains('translate_api_write_check(', translate_api_dry_run_test_source('translate_api_write_one'), 'the real write meets the same check');
	pg_assert_contains('pg_tr_result_judge(', translate_api_dry_run_test_source('translate_api_jobs_results'), 'a rehearsed result is judged');
	pg_assert_contains('pg_tr_result_judge(', translate_api_dry_run_test_source('pg_tr_accept_result'), 'the real result meets the same check');
	pg_assert_contains('pg_tr_reviewed_kept(', translate_api_dry_run_test_source('pg_tr_save_translation'), 'a kept reviewed translation is asked one way');

	foreach (array('translate_api_status', 'translate_api_jobs_results') as $handler) {
		pg_assert_contains('api_dry_run_requested()', translate_api_dry_run_test_source($handler), $handler . ' skips its writes in a rehearsal');
	}
}

// An empty answer is refused before anything is read or written.
function test_translate_api_write_check_refuses_an_empty_text()
{
	$string = array('id' => 1, 'source_text' => 'Hello', 'format' => 'text');

	pg_assert_same('empty', translate_api_write_check($string, 'en', '   ', 'reviewed', false)['reason']);
}

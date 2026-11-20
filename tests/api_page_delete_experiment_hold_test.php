<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that DELETE /pages/{id} (api_pages_delete() in
 * includes/api/resources/pages.php) refuses the variant draft of an open
 * experiment and a draft waiting in an open release bundle, each with its
 * own 409 code, as the panel's recycle does, before the dry-run stop and
 * before both the recycle and the permanent delete. No database is used.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/pages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function _api_page_delete_hold_source()
{
	$function = new ReflectionFunction('api_pages_delete');
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_api_pages_delete_refuses_held_variant_before_any_write()
{
	$source = _api_page_delete_hold_source();

	$check = strpos($source, 'pg_experiment_variant_hold_message(');
	$fail = strpos($source, 'api_fail(409, api_page_hold_code(');
	$branch = strpos($source, 'if (!$permanent)');
	$dry = strpos($source, 'api_dry_run_stop(');
	$recycle = strpos($source, 'UPDATE page SET');
	$delete = strpos($source, 'pg_delete_page_record(');

	pg_assert_true($check !== false && $fail !== false, 'the hold is asked and refused');
	pg_assert_true($branch !== false && $dry !== false && $recycle !== false && $delete !== false, 'both paths are there');
	pg_assert_true($fail < $branch, 'refused before the recycle and the permanent paths split');
	pg_assert_true($fail < $dry, 'refused before the first dry-run stop');
	pg_assert_true($fail < $recycle && $fail < $delete, 'refused before both writes');
}

function test_api_pages_delete_schema_names_the_hold()
{
	$found = 0;

	foreach (api_schema() as $endpoint) {
		if (in_array($endpoint['id'], array('pages.delete', 'pages.delete_post'), true)) {
			pg_assert_contains('409 experiment_variant_held', $endpoint['description'], $endpoint['id']);
			pg_assert_contains('release_bundle_held', $endpoint['description'], $endpoint['id']);
			$found++;
		}
	}

	pg_assert_same(2, $found, 'both delete routes');
}

function test_api_page_hold_code_tells_the_experiment_from_the_bundle()
{
	pg_assert_same('experiment_variant_held', api_page_hold_code(true), 'an open experiment\'s variant keeps its code');
	pg_assert_same('release_bundle_held', api_page_hold_code(false), 'a bundled draft has its own');
}

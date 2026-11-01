<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the search index's delta stage (includes/fn/site_search.php):
 * when a full pass is due, which pages and records changed since their row,
 * and how a record is told from its indexed address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_search.php');

function test_search_index_delta_full_pass_due()
{
	pg_assert_true(pg_search_index_full_due(0, 100000), 'no full pass yet');
	pg_assert_true(pg_search_index_full_due(1000, 1000 + 86400), 'a day later');
	pg_assert_true(pg_search_index_full_due(1000, 1000 + 86400 - 120), 'a dispatcher tick a little early still counts');
	pg_assert_false(pg_search_index_full_due(1000, 1000 + 3600), 'an hour later: delta only');
}

function test_search_index_delta_pages_changed_since_their_row()
{
	$pages = array(
		array('id' => 1, 'url' => 'about', 'page_timestamp' => 500),
		array('id' => 2, 'url' => 'contact', 'page_timestamp' => 300),
		array('id' => 3, 'url' => 'new-page', 'page_timestamp' => 900),
		array('id' => 4, 'url' => 'same', 'page_timestamp' => 400),
	);
	$rows = array('about' => 400, 'contact' => 400, 'same' => 400);

	pg_assert_same(array(1), pg_search_index_delta_pages($pages, $rows), 'only a page newer than its row; a page without one waits');
}

function test_search_index_delta_record_code_from_address()
{
	pg_assert_same('AB12', pg_search_index_record_code('products?r=AB12'));
	pg_assert_same('AB12', pg_search_index_record_code('products?x=1&r=AB12'));
	pg_assert_same('', pg_search_index_record_code('products/my-record'), 'an address name carries no code');
	pg_assert_same('', pg_search_index_record_code('products'), 'a page');
}

function test_search_index_delta_records_changed_since_their_row()
{
	$rows = array('items?r=A' => 400, 'items?r=B' => 400, 'items?r=C' => 400, 'items/slug' => 100);
	$forms = array('A' => 500, 'B' => 300, 'D' => 900);

	pg_assert_same(array('A' => true), pg_search_index_delta_records($rows, $forms), 'only a record changed after its row');
}

function test_search_index_delta_wiring()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');

	// Only a dispatched run with no pass under way and a full pass not due.
	$decide = substr($src, strpos($src, '$search_index_delta = null;'), 900);
	pg_assert_contains("defined('PG_CRON_DISPATCH') && (\$search_index_saved_state === null)", $decide);
	pg_assert_contains("pg_search_index_full_due(", $decide);
	pg_assert_contains("['update_search_index_done']", $decide, 'against the end of the last full pass');

	// It keeps no cursor, draws only changed pages and records, and ends
	// before the files, the sweep and the completion marker.
	pg_assert_contains("if (\$search_index_delta !== null) {\n        return;\n    }", $src, 'no cursor');
	pg_assert_contains("(page.page_id IN ('\" . implode(\"', '\", array_merge(array(0), \$search_index_delta['pages']))", $src);
	pg_assert_contains("!isset(\$search_index_delta['records'][(string) \$record['reference_code']])", $src);
	pg_assert_contains("|| (\$search_index_delta !== null)) ? array() : db_items(", $src, 'no files');
	$end = substr($src, strpos($src, '$search_index_done = '));
	pg_assert_true(strpos($end, 'if ($search_index_delta !== null) {') < strpos($end, 'db(pg_search_index_sweep_sql($timestamp));'), 'the delta branch comes before the sweep');

	// The job comes hourly; a new design asks for a full pass.
	pg_assert_contains("'interval'    => 3600,", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/cron.php'));
	pg_assert_contains("WHERE job_name = 'update_search_index_done'", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/site_search.php'));
}

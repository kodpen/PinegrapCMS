<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a failed write in a bulk page edit: pg_page_bulk_apply()
 * (includes/fn/page_bulk.php) answers false when a preview_styles or page
 * write fails and leaves the tag cloud alone; Edit Pages stops with
 * 'Query failed.' before the audit row and the count, and the File
 * Manager's bulk page panel counts only the pages it wrote and names the
 * others.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');

// Without a connection db_q() answers false, as a failed query does.
if (!class_exists('db')) {
	class db
	{
		public static $con;
	}
}

function page_bulk_failed_write_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function page_bulk_failed_write_test_decision($changes = array())
{
	return array_merge(array(
		'columns' => array(),
		'styles' => array(),
		'stale' => false,
		'tag_cloud' => '',
		'style_skipped' => false,
	), $changes);
}

function test_page_bulk_apply_reports_a_failed_page_write()
{
	$saved = db::$con;
	db::$con = null;

	$page = array('page_id' => '12', 'page_search_keywords' => 'a, b');

	// The tag cloud is not touched after the page write failed: the
	// search flag it counts did not change.
	$failed = pg_page_bulk_apply($page, page_bulk_failed_write_test_decision(array(
		'columns' => array('page_search' => 0),
		'stale' => true,
		'tag_cloud' => 'remove')), 1);

	$preview = pg_page_bulk_apply($page, page_bulk_failed_write_test_decision(array(
		'styles' => array('desktop' => 7))), 1, 3);

	$nothing = pg_page_bulk_apply($page, page_bulk_failed_write_test_decision(), 1);

	db::$con = $saved;

	pg_assert_same(false, $failed, 'a failed page UPDATE answers false');
	pg_assert_same(false, $preview, 'a failed preview_styles write answers false');
	pg_assert_same(true, $nothing, 'nothing to write answers true');
}

function test_edit_pages_stops_on_a_failed_write()
{
	$source = page_bulk_failed_write_test_source('edit_pages.php');

	$apply = strpos($source, 'pg_page_bulk_apply(');
	$stop = strpos($source, "output_error('Query failed.')", (int) $apply);
	$audit = strpos($source, "pg_audit_record('update', 'page'", (int) $apply);

	pg_assert_true(($apply !== false) && (strpos(substr($source, $apply - 20, 20), 'if (!') !== false), 'Edit Pages checks what the write answered');
	pg_assert_true(($stop !== false) && ($audit !== false) && ($stop < $audit), 'Edit Pages stops before the audit row and the count');
}

function test_file_manager_counts_only_written_pages()
{
	$source = page_bulk_failed_write_test_source('includes/explorer/handle_bulk_edit.php');
	$start = strpos($source, 'function pg_explorer_handle_pages_bulk_edit(');
	$end = strpos($source, 'function pg_explorer_handle_bulk_file_options(');
	$body = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_true(preg_match('/if \(!pg_page_bulk_apply\(/', $body) === 1, 'the panel checks what each write answered');
	pg_assert_true(strpos($body, "'updated' => \$written") !== false, 'the answer counts the pages written');
	pg_assert_true(strpos($body, 'pg_explorer_bulk_pages_status($written,') !== false, 'the status reads the pages written');
	pg_assert_true(strpos($body, "'updated' => count(\$allowed)") === false, 'no page is counted before its write');
	pg_assert_true(strpos($body, 'could not be saved') !== false, 'a page that was not written is named');
}

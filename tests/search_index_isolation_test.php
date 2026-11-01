<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a search index run that survives a bad item: one isolated
 * render (pg_search_index_render()) that puts back $_GET, the output buffer
 * level and the error flag, the failure note with the item's kind, the file
 * reader for the old office formats, the stop note of a run that ended early,
 * and source checks on update_search_index.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _search_index_isolation_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
}

// A render that throws an Error (not an Exception) comes back as a failure;
// $_GET, the buffer level and the flag are what they were before.
function test_search_index_render_catches_error_and_restores_state()
{
	$saved_get = $_GET;
	$_GET = array('keep' => '1');
	$level = ob_get_level();
	$flag = pg_error_throws();

	$seen_flag = null;
	$result = pg_search_index_render(7, array('form_id' => 3), function ($page_id, $properties) use (&$seen_flag) {
		$seen_flag = pg_error_throws();
		$_GET['r'] = 'X';
		unset($_GET['keep']);
		ob_start();
		echo 'half';
		throw new Error('Call to undefined function foo()');
	});

	$get_after = $_GET;
	$_GET = $saved_get;

	pg_assert_same(null, $result['content']);
	pg_assert_same('Call to undefined function foo()', $result['error']);
	pg_assert_same(true, $seen_flag);
	pg_assert_same(array('keep' => '1'), $get_after);
	pg_assert_same($level, ob_get_level());
	pg_assert_same($flag, pg_error_throws());
}

// A failed query under the flag throws (output_error()), and is a failure.
function test_search_index_render_turns_output_error_into_failure()
{
	$result = pg_search_index_render(7, array(), function () {
		output_error('Query failed.');
		return 'never';
	});

	pg_assert_same(null, $result['content']);
	pg_assert_same('Query failed.', $result['error']);
	pg_assert_same(false, pg_error_throws());
}

// A render that works returns its HTML and still closes a buffer it left open.
function test_search_index_render_returns_content()
{
	$level = ob_get_level();
	$args = null;

	$result = pg_search_index_render(5, array('calendar_event_id' => 9), function ($page_id, $properties) use (&$args) {
		$args = array($page_id, $properties);
		ob_start();
		return '<p>Hello</p>';
	});

	pg_assert_same('<p>Hello</p>', $result['content']);
	pg_assert_same('', $result['error']);
	pg_assert_same(array(5, array('calendar_event_id' => 9)), $args);
	pg_assert_same($level, ob_get_level());
}

function test_search_index_failure_note_names_kind()
{
	pg_assert_same('Page about-us: Query failed.', pg_search_index_failure_note('about-us', '', 'Query failed.', 200, 'Page'));
	pg_assert_same('Event event-view (12): x', pg_search_index_failure_note('event-view', '12', 'x', 200, 'Event'));
	pg_assert_same('blog (A1): x', pg_search_index_failure_note('blog', 'A1', 'x'));
}

// A missing or empty file is no text, never a TypeError or ValueError.
function test_search_index_file_bytes()
{
	$dir = sys_get_temp_dir() . '/pg_search_index_' . getmypid();
	@mkdir($dir);

	pg_assert_same('', pg_search_index_file_bytes($dir . '/missing.doc'));

	file_put_contents($dir . '/empty.doc', '');
	pg_assert_same('', pg_search_index_file_bytes($dir . '/empty.doc'));

	file_put_contents($dir . '/text.doc', "ab\rcd");
	pg_assert_same("ab\rcd", pg_search_index_file_bytes($dir . '/text.doc'));

	pg_assert_same('', pg_search_index_file_bytes($dir));

	@unlink($dir . '/empty.doc');
	@unlink($dir . '/text.doc');
	@rmdir($dir);
}

// The stop note names the stage, the item and only a fatal error.
function test_search_index_stop_note()
{
	pg_assert_same('pages, about-us', pg_search_index_stop_note('pages', 'about-us', null));
	pg_assert_same('files', pg_search_index_stop_note('files', '', null));
	pg_assert_same('pages, about-us', pg_search_index_stop_note('pages', 'about-us', array('type' => E_WARNING, 'message' => 'x', 'file' => '/a/b.php', 'line' => 3)));
	pg_assert_same(
		'records, blog (A1): Allowed memory size exhausted (get_page_content.php:120)',
		pg_search_index_stop_note('records', 'blog (A1)', array('type' => E_ERROR, 'message' => "Allowed memory\n size exhausted", 'file' => '/x/get_page_content.php', 'line' => 120)));
	$long = pg_search_index_stop_note('pages', 'p', array('type' => E_ERROR, 'message' => str_repeat('a', 400), 'file' => '/f.php', 'line' => 1));
	pg_assert_true(strlen($long) < 260, 'message cut');
}

// Pages, events and classic records render isolated; a failure keeps the
// old row and is named; the designer branch keeps its own isolation.
function test_search_index_items_render_isolated()
{
	$source = _search_index_isolation_source();
	$designer = strpos($source, '$designer_record_urls = array();');
	$before = substr($source, 0, $designer);

	pg_assert_false(strpos($before, 'get_page_content(') !== false, 'no bare render before the designer branch');
	pg_assert_same(3, substr_count($before, 'pg_search_index_render('));
	pg_assert_same(3, substr_count($before, 'pg_search_index_keep_item('));
	pg_assert_same(3, substr_count($before, 'pg_search_index_failed('));
}

// Files: shell_exec only when it exists, each file in its own try, a
// failure keeps the old row; the old office readers use the safe reader.
function test_search_index_files_isolated()
{
	$source = _search_index_isolation_source();
	$files = substr($source, strpos($source, '// Get files that should be included in search index.'));
	$files = substr($files, 0, strpos($files, '// Delete old items'));

	pg_assert_same(substr_count($files, 'shell_exec('), substr_count($files, "function_exists('shell_exec')"));
	pg_assert_contains('catch (Throwable $throwable)', $files);
	pg_assert_contains('pg_search_index_keep_item(', $files);
	pg_assert_contains('pg_search_index_failed(', $files);

	$readers = substr($source, strpos($source, 'function convert_doc_to_text('));
	pg_assert_false(strpos($readers, 'fopen(') !== false, 'no fopen() in the office readers');
	pg_assert_same(2, substr_count($readers, 'pg_search_index_file_bytes('));
}

// A run that stops early writes one Site Log line; the line and the finish
// window count every kind.
function test_search_index_run_reports_every_kind()
{
	$source = _search_index_isolation_source();

	pg_assert_contains('register_shutdown_function(', $source);
	pg_assert_contains('pg_search_index_stop_note(', $source);
	pg_assert_contains('error_get_last()', $source);
	pg_assert_true(strpos($source, "\$search_index_run['done'] = true;") > strpos($source, 'DELETE FROM search_items'), 'done after the sweep');

	$tail = substr($source, strpos($source, '// Delete old items'));
	pg_assert_contains('$index_failures > 0', $tail);
	pg_assert_false(strpos($source, '$designer_failures') !== false, 'one counter for every kind');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent questions waiting for a person (Today, the Assistant
 * card): the open questions are read page by page until enough waiting ones
 * are kept, so a person's question past the site's newest page is found.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Open question rows with ids from $count down to 1, newest first, served a
// page at a time below a given id, as the query reads them; $calls counts
// the pages read.
function ws_agent_questions_paging_test_fetch($count, &$calls)
{
	return function ($before_id, $page) use ($count, &$calls) {
		$calls++;
		$rows = array();
		$top = ($before_id > 0) ? min($count, $before_id - 1) : $count;

		for ($id = $top; ($id >= 1) && (count($rows) < $page); $id--) {
			$rows[] = array('id' => $id);
		}

		return $rows;
	};
}

// The person's one question lies past the first 100 open ones: it is found.
function test_ws_agent_questions_collect_reads_past_the_first_page()
{
	$calls = 0;
	$kept = ws_agent_questions_collect(ws_agent_questions_paging_test_fetch(250, $calls), function ($row) {
		return (int) $row['id'] === 7;
	}, 5, 100);

	pg_assert_same(array(7), array_map(function ($row) { return (int) $row['id']; }, $kept), 'the question past the first page is kept');
	pg_assert_same(3, $calls, 'pages are read until the rows run out');
}

// Enough waiting questions on the first page: no further page is read.
function test_ws_agent_questions_collect_stops_at_the_limit()
{
	$calls = 0;
	$kept = ws_agent_questions_collect(ws_agent_questions_paging_test_fetch(250, $calls), function ($row) {
		return ((int) $row['id'] % 2) === 0;
	}, 5, 100);

	pg_assert_same(array(250, 248, 246, 244, 242), array_map(function ($row) { return (int) $row['id']; }, $kept), 'newest first, at most the limit');
	pg_assert_same(1, $calls, 'one page is enough');
}

// Pages follow on below the last row read: no row is read twice or missed.
function test_ws_agent_questions_collect_pages_by_id()
{
	$calls = 0;
	$seen = array();
	ws_agent_questions_collect(ws_agent_questions_paging_test_fetch(25, $calls), function ($row) use (&$seen) {
		$seen[] = (int) $row['id'];
		return false;
	}, 5, 10);

	pg_assert_same(range(25, 1), $seen, 'every open question is looked at once, newest first');
	pg_assert_same(3, $calls, 'a short page ends the reading');
}

// No open question at all: nothing kept, one page read.
function test_ws_agent_questions_collect_empty()
{
	$calls = 0;
	pg_assert_same(array(), ws_agent_questions_collect(ws_agent_questions_paging_test_fetch(0, $calls), function () { return true; }, 5, 100), 'nothing to keep');
	pg_assert_same(1, $calls, 'one empty page');
}

// The query reads below the last id, not the site's newest 100 only.
function test_ws_agent_questions_for_pages_the_query()
{
	$reflection = new ReflectionFunction('ws_agent_questions_for');
	$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	pg_assert_contains('ws_agent_questions_collect(', $source);
	pg_assert_contains('p.id < ', $source);
}

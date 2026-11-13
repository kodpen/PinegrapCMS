<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how Workspace notes are listed (includes/workspace/notes.php):
 * the API's pages (ws_notes_page()) and their cursor, the conditions applied
 * in the query rather than after a cut, the columns a list reads, and the
 * route that offers them.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A position goes into a cursor's one number and comes back the same; the
// groups keep their order whatever the moments.
function test_workspace_notes_page_position_round_trips()
{
	foreach (array(array(0, 1800000000, 7), array(1, 0, 1), array(2, 9999999999, 123456)) as $case) {
		list($group, $updated_at, $id) = $case;

		pg_assert_same(
			array('group' => $group, 'updated_at' => $updated_at, 'id' => $id),
			ws_notes_position_unpack(ws_notes_position_pack($group, $updated_at), $id),
			'group ' . $group
		);
	}

	pg_assert_true(ws_notes_position_pack(0, 9999999999) < ws_notes_position_pack(1, 0), 'pinned before the other own notes');
	pg_assert_true(ws_notes_position_pack(1, 9999999999) < ws_notes_position_pack(2, 0), 'own notes before shared ones');
}

function test_workspace_notes_page_position_refuses_what_cannot_be_one()
{
	pg_assert_same(null, ws_notes_position_unpack(ws_notes_position_pack(3, 5), 1), 'no fourth group');
	pg_assert_same(null, ws_notes_position_unpack(-1, 1), 'no negative number');
	pg_assert_same(null, ws_notes_position_unpack(5, 0), 'no note without an id');
}

// The moment is a condition of the query, so a note older than any cut is
// still found. (The words are escaped through the connection, which the
// test runner has none of.)
function test_workspace_notes_page_filters_are_in_the_query()
{
	pg_assert_same('', ws_notes_filter_sql(''), 'nothing asked');
	pg_assert_same('', ws_notes_filter_sql('   ', 0), 'spaces are nothing');
	pg_assert_same(" AND n.updated_at >= '1800000000'", ws_notes_filter_sql('', 1800000000), 'changed since');
	pg_assert_same('', ws_notes_filter_sql('', -5), 'no moment before the epoch');
	pg_assert_same(" AND (n.updated_at >= '1800000000' OR s.created_at >= '1800000000')", ws_notes_filter_sql('', 1800000000, 's.created_at'), 'a note shared since counts as changed');
	pg_assert_same('', ws_notes_filter_sql('', 0, 's.created_at'), 'no share time without a moment');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
	$start = strpos($source, 'function ws_notes_page_rows(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("(int) (\$options['updated_since'] ?? 0), 's.created_at', \$behind)", $body, 'the shared notes take the share time');
	pg_assert_contains('ORDER BY n.updated_at DESC, n.id DESC', $body, 'the order stays on the change time');
}

// A search of the API's pages finds a note behind the reader's closed lock
// by its title alone, in the query, so the pages and their total agree.
function test_workspace_notes_page_search_leaves_locked_text_out()
{
	pg_assert_same(
		"(n.title LIKE 'x' OR (NOT ((n.user_id = '7' AND n.locked = 1) OR n.id IN (3,9)) AND n.body LIKE 'x'))",
		ws_notes_search_closed_sql("n.title LIKE 'x'", "n.body LIKE 'x'", "((n.user_id = '7' AND n.locked = 1) OR n.id IN (3,9))"),
		'the words in the title, or in the text of a note that is not behind the lock'
	);
	pg_assert_same('', ws_notes_filter_sql('', 0, '', "(n.user_id = '7' AND n.locked = 1)"), 'no words, no lock condition');
	pg_assert_same(" AND n.updated_at >= '1800000000'", ws_notes_filter_sql('  ', 1800000000, '', "(n.user_id = '7' AND n.locked = 1)"), 'a locked note still comes with the changes');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

	$start = strpos($source, 'function ws_notes_filter_sql(');
	$filter = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("ws_notes_search_closed_sql(ws_search_like_sql(\$search, array('n.title'), \$charset), ws_search_like_sql(\$search, array('n.body'), \$charset), (string) \$behind_lock)", $filter, 'the title and the text are asked apart');
	pg_assert_contains("ws_search_like_sql(\$search, array('n.title', 'n.body'), \$charset)", $filter, 'with no lock closed, the words are looked for in both');

	$start = strpos($source, 'function ws_notes_page_rows(');
	$rows = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("(int) (\$options['updated_since'] ?? 0), '', \$behind)", $rows, 'the own notes, and their count');

	$start = strpos($source, 'function ws_notes_page(');
	$page = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("\$options['behind_lock'] = ws_note_lock_behind_sql(\$me, ws_note_lock_has(\$me), ws_note_lock_group_note_ids(\$me));", $page, 'the API reads with the lock closed');
	pg_assert_true(strpos($page, 'ws_note_lock_is_open') === false, 'an open lock of a session is not asked: an API client holds none');
}

function test_workspace_notes_page_after_keeps_the_rest_of_the_order()
{
	pg_assert_same('', ws_notes_after_sql('2', null), 'the first page');

	$sql = preg_replace('/\s+/', ' ', ws_notes_after_sql('IF(n.pinned = 1, 0, 1)', array('group' => 1, 'updated_at' => 1800000000, 'id' => 42)));

	pg_assert_contains('((IF(n.pinned = 1, 0, 1)) > 1 OR ((IF(n.pinned = 1, 0, 1)) = 1', $sql, 'a later group, or the same group');
	pg_assert_contains("n.updated_at < '1800000000' OR (n.updated_at = '1800000000' AND n.id < '42')", $sql, 'older, or as old with a lower id');
}

// A list reads every column of the table, the text cut short; a column
// added to ws_notes has to be added to the list as well.
function test_workspace_notes_page_list_reads_every_column()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.4.4.php');

	pg_assert_true(preg_match('/CREATE TABLE ws_notes \((.*?)PRIMARY KEY/s', $migration, $match) === 1, 'the table is found');
	preg_match_all('/^\s*([a-z_]+)\s+(?:INT|VARCHAR|MEDIUMTEXT|TINYINT)/m', $match[1], $columns);

	$list = ws_notes_list_columns();

	pg_assert_true(count($columns[1]) >= 12, 'the columns are read');

	foreach ($columns[1] as $column) {
		pg_assert_contains('n.' . $column, $list, $column);
	}

	pg_assert_contains('LEFT(n.body, ' . WS_NOTE_LIST_BODY . ') AS body', $list, 'the text is cut');
	pg_assert_true(WS_NOTE_LIST_BODY >= 2000, 'enough for a name and an excerpt');
}

// The route takes a cursor and a page size no larger than the function
// gives, and the note says when it was made.
function test_workspace_notes_page_route_pages()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.notes.list') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route), 'the route is found');

	$params = array();

	foreach ($route['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_true(isset($params['cursor']), 'cursor');
	pg_assert_same(WS_NOTES_PAGE_MAX, $params['limit']['max'], 'limit stops where the function does');
	pg_assert_true(isset($params['updated_since']), 'updated_since');

	$resources = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_contains("'created_at'  => api_time(\$note['created_at'])", $resources, 'the object carries created_at');
	pg_assert_contains("'created_at'  => 'string?'", $resources, 'and declares it');
	pg_assert_true(preg_match('/function ws_api_notes_list\(\$params\)\s*\{.*?ws_notes_page\(.*?api_ok_list\(\$out, \$limit, \$next, \$page\[\'total\'\](?:, [^;]*)?\);/s', $resources) === 1, 'the list pages and reports the total');
}

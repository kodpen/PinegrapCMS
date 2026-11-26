<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the search in a channel's and the general memory and the
 * projects read (includes/workspace/channel_memory_search.php) that need no
 * database: the word match as SQL, the text around the first hit, the
 * projects with their counts, the routes.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_memory_search_where_every_word()
{
	$where = ws_memory_search_where(array('merge', 'train'), '');

	pg_assert_same('(m.title LIKE ? OR m.text LIKE ? OR m.tags LIKE ?) AND (m.title LIKE ? OR m.text LIKE ? OR m.tags LIKE ?)', $where['sql'], 'every word, in the title, the text or the tags');
	pg_assert_same(array('%merge%', '%merge%', '%merge%', '%train%', '%train%', '%train%'), $where['params']);
}

function test_workspace_memory_search_where_escapes()
{
	$where = ws_memory_search_where(array('50%', 'a_b', 'c\\d'), '');

	pg_assert_same('%50\\%%', $where['params'][0], '% stands for itself');
	pg_assert_same('%a\\_b%', $where['params'][3], '_ stands for itself');
	pg_assert_same('%c\\\\d%', $where['params'][6], 'the escape character stands for itself');
	pg_assert_true(strpos($where['sql'], '50') === false, 'a word is a bound value, never query text');
}

function test_workspace_memory_search_where_turkish()
{
	$where = ws_memory_search_where(array('Yıldız', 'kira'), 'utf8mb4');
	$fold = "REPLACE(REPLACE(CONVERT(m.%s USING utf8mb4), 'İ', 'i'), 'ı', 'i') LIKE ?";

	pg_assert_same('(' . sprintf($fold, 'title') . ' OR ' . sprintf($fold, 'text') . ' OR ' . sprintf($fold, 'tags') . ')'
		. ' AND (' . sprintf($fold, 'title') . ' OR ' . sprintf($fold, 'text') . ' OR ' . sprintf($fold, 'tags') . ')', $where['sql'], 'a word with an I letter matched folded, one pass a column');
	pg_assert_same('%yildiz%', $where['params'][0], 'the word folded');

	$plain = ws_memory_search_where(array('merge'), 'utf8mb4');
	pg_assert_same('(m.title LIKE ? OR m.text LIKE ? OR m.tags LIKE ?)', $plain['sql'], 'a word without one stays plain');

	$odd = ws_memory_search_where(array('kira'), 'latin1; DROP');
	pg_assert_same('(m.title LIKE ? OR m.text LIKE ? OR m.tags LIKE ?)', $odd['sql'], 'only a known charset is written into the query');
}

function test_workspace_memory_search_where_none()
{
	pg_assert_same(array('sql' => '', 'params' => array()), ws_memory_search_where(array(), ''));
}

function test_workspace_memory_search_terms()
{
	pg_assert_same(array('merge', 'train window'), ws_memory_search_terms('  merge "train window" merge '));
	pg_assert_same(array(), ws_memory_search_terms('   '));
	pg_assert_same(array(), ws_memory_search_terms(array('x')));
	pg_assert_same(10, count(ws_memory_search_terms('a b c d e f g h i j k l')), 'ten words at most');
}

function test_workspace_memory_search_snippet()
{
	pg_assert_same('Trains land at 08:00.', ws_memory_search_snippet("Trains\n  land at 08:00.", array('land'), 200), 'a short text whole, spaces folded');

	$text = str_repeat('a', 300) . ' Needle here ' . str_repeat('b', 300);
	$snippet = ws_memory_search_snippet($text, array('needle'), 40);

	pg_assert_contains('Needle', $snippet, 'around the first hit, case left aside');
	pg_assert_same('…', mb_substr($snippet, 0, 1), 'cut at the start');
	pg_assert_same('…', mb_substr($snippet, -1), 'cut at the end');
	pg_assert_same(42, mb_strlen($snippet), '40 characters and the two marks');

	$start = ws_memory_search_snippet('Needle ' . str_repeat('x', 100), array('needle'), 20);
	pg_assert_same('Needle ' . str_repeat('x', 13) . '…', $start, 'a hit at the start is not cut before');

	$end = ws_memory_search_snippet(str_repeat('x', 100) . ' Needle', array('needle'), 20);
	pg_assert_same('…' . str_repeat('x', 13) . ' Needle', $end, 'a hit at the end is not cut after');

	$title_only = ws_memory_search_snippet(str_repeat('z', 50), array('absent'), 10);
	pg_assert_same(str_repeat('z', 10) . '…', $title_only, 'no hit in the text: its start');

	$first = ws_memory_search_snippet(str_repeat('-', 50) . 'late ' . str_repeat('-', 50) . 'Early', array('early', 'late'), 10);
	pg_assert_contains('late', $first, 'the first hit of any word');

	$turkish = ws_memory_search_snippet(str_repeat('-', 60) . 'YILDIZ' . str_repeat('-', 60), array('yildiz'), 20);
	pg_assert_contains('YILDIZ', $turkish, 'I and ı fold to i, one letter for one');

	pg_assert_same('', ws_memory_search_snippet('', array('a'), 200));
}

function test_workspace_memory_project_counts()
{
	$rows = array(
		array('project' => 'launch', 'count' => '2'),
		array('project' => '', 'count' => 4),
		array('project' => 'Billing', 'count' => 1),
		array('project' => 'Launch ', 'count' => 3),
	);

	pg_assert_same(array(
		array('project' => 'Billing', 'count' => 1),
		array('project' => 'launch', 'count' => 5),
		array('project' => '', 'count' => 4),
	), ws_memory_project_counts($rows), 'case and spaces folded into one project, alphabetical, none last');

	pg_assert_same(array(), ws_memory_project_counts(array()));
}

function test_workspace_memory_search_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.memory.projects']), 'the projects read is declared');
	pg_assert_same(array('GET', '/workspace/memory/projects'), array($routes['workspace.memory.projects']['method'], $routes['workspace.memory.projects']['path']));
	pg_assert_true(!isset($routes['workspace.memory.projects']['mcp']) || ($routes['workspace.memory.projects']['mcp'] !== false), 'the projects read is a tool');

	foreach (array('workspace.memory.list', 'workspace.memory.general.list') as $id) {
		$names = array_map(function ($param) {
			return $param['name'];
		}, $routes[$id]['params']);

		foreach (array('search', 'cursor', 'tag', 'project', 'expired') as $name) {
			pg_assert_true(in_array($name, $names, true), $id . ' takes ' . $name);
		}

		pg_assert_same(array('list' => 'WorkspaceMemoryIndexEntry'), $routes[$id]['returns'], $id . ' keeps its answer');
	}

	$schema = ws_api_memory_index_entry_schema();
	pg_assert_same('string?', $schema['snippet'] ?? null, 'a hit carries the text around it');
	pg_assert_same(array('project' => 'string', 'count' => 'integer'), ws_api_memory_project_schema());
}

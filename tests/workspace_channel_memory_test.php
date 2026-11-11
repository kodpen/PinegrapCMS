<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's memory (includes/workspace/channel_memory.php) that
 * need no database: an entry's tags and checks, the brief's index, who
 * marked a message, the routes, the panel's wiring and the schema step.
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

function test_workspace_memory_tags()
{
	pg_assert_same(array('release', 'ci'), ws_memory_tags('#Release, ci  release'));
	pg_assert_same(array('a', 'b'), ws_memory_tags(array(' A ', 'b', '', 'a')));
	pg_assert_same(array(), ws_memory_tags(''));
}

function test_workspace_memory_entry_problem()
{
	$now = 1000;
	$ok = array('title' => 'Merge window', 'text' => 'Trains land at 08:00.', 'tags' => array('merge'), 'expires_at' => 0);

	pg_assert_same('', ws_memory_entry_problem($ok, $now));
	pg_assert_same('', ws_memory_entry_problem(array('title' => 'x', 'expires_at' => 1001), $now));
	pg_assert_true(ws_memory_entry_problem(array('title' => '  '), $now) !== '', 'a title is needed');
	pg_assert_true(ws_memory_entry_problem(array('title' => str_repeat('a', 151)), $now) !== '', 'title too long');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'text' => str_repeat('a', 4001)), $now) !== '', 'text too long');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'tags' => range(1, 11)), $now) !== '', 'too many tags');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'tags' => array('two words')), $now) !== '', 'a tag is one word');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'expires_at' => 1000), $now) !== '', 'expiry in the past');
}

function test_workspace_memory_digest()
{
	$now = 1000;
	$entries = array(
		array('id' => 1, 'title' => 'Old', 'text' => 'first', 'tags' => array(), 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 10),
		array('id' => 2, 'title' => 'Gone', 'text' => 'expired', 'tags' => array(), 'pinned' => 1, 'expires_at' => 900, 'updated_at' => 50),
		array('id' => 3, 'title' => "New\ntitle", 'text' => 'not in the index', 'tags' => 'ci, release', 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 30),
		array('id' => 4, 'title' => 'Pin', 'text' => '', 'tags' => array(), 'pinned' => 1, 'expires_at' => 2000, 'updated_at' => 5),
	);
	$decisions = array(array('body' => 'Ship on Friday', 'marked_at' => 20), array('body' => '  ', 'marked_at' => 10));
	$heading = 'Channel memory (read an entry with workspace.memory.get, its id without the #):';

	// The index only: ids, titles and tags, pinned first, expired left out,
	// never a text.
	pg_assert_same($heading . "\n- #4 Pin\n- #3 New title [ci, release]\n- #1 Old\n\nDecisions in this channel:\n- Ship on Friday",
		ws_memory_digest($entries, $decisions, $now));
	pg_assert_same('', ws_memory_digest(array(), array(), $now));

	// A line that does not fit is left out whole, with the lines after it, and counted.
	$small = ws_memory_digest($entries, $decisions, $now, mb_strlen($heading) + 12);
	pg_assert_same($heading . "\n- #4 Pin\n\n(3 more not shown: list them with workspace.memory.list and workspace.decisions.list.)", $small);
}

// The brief lists the newest decisions only, then one line with how many
// more the channel holds; the character bound still closes it with a
// single count of everything left out.
function test_workspace_memory_digest_bounds_the_decisions()
{
	pg_assert_same(10, WS_MEMORY_BRIEF_DECISIONS);

	$forty = array();

	for ($i = 40; $i >= 1; $i--) {
		$forty[] = array('body' => 'Decision ' . $i, 'marked_at' => $i);
	}

	$heading = 'Decisions in this channel:';
	$newest = array();

	for ($i = 40; $i >= 31; $i--) {
		$newest[] = '- Decision ' . $i;
	}

	$count = '(30 older decisions not shown: list them with workspace.decisions.list.)';

	// Forty given: the newest ten, then the count.
	pg_assert_same($heading . "\n" . implode("\n", $newest) . "\n\n" . $count, ws_memory_digest(array(), $forty, 100));

	// The newest ten given with the channel's total, as ws_memory_brief() passes them.
	pg_assert_same($heading . "\n" . implode("\n", $newest) . "\n\n" . $count,
		ws_memory_digest(array(), array_slice($forty, 0, 10), 100, WS_MEMORY_DIGEST_MAX, 40));

	// Ten or fewer: no count line.
	$ten = ws_memory_digest(array(), array_slice($forty, 0, 10), 100, WS_MEMORY_DIGEST_MAX, 10);
	pg_assert_same($heading . "\n" . implode("\n", $newest), $ten);
	pg_assert_false(strpos(ws_memory_digest(array(), array_slice($forty, 0, 3), 100), 'not shown') !== false, 'three decisions, no count');

	// The character bound cuts first: one closing line counts the lines it
	// left out and the older decisions together, never two counts.
	$cut = ws_memory_digest(array(), $forty, 100, mb_strlen($heading) + 2 + 3 * 14);
	pg_assert_same($heading . "\n- Decision 40\n- Decision 39\n- Decision 38\n\n(37 more not shown: list them with workspace.memory.list and workspace.decisions.list.)", $cut);
	pg_assert_same(1, substr_count($cut, 'not shown'));
}

// The lines that tell an agent how to read more of a channel name the
// limit and ask for only as many messages as the request needs; the
// route's own default and most stay.
function test_workspace_memory_read_more_names_the_limit()
{
	$queue = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_queue.php');
	$ai = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');
	$sentence = 'ask for only as many messages as the request needs';

	pg_assert_contains("'/context?limit=<n>: " . $sentence . ".'", $queue, 'the app agent\'s brief');
	pg_assert_false(strpos($queue, "'/context.'") !== false, 'no read without a limit');
	pg_assert_contains("'GET /workspace/channels/{id}/context?limit=<n> reads them; " . $sentence . "'", $ai, 'Claude\'s brief');
}

function test_workspace_memory_entry_fault_names_the_field()
{
	pg_assert_same(null, ws_memory_entry_fault(array('title' => 'x'), 1000));
	pg_assert_same('title', ws_memory_entry_fault(array('title' => ''), 1000)['field']);
	pg_assert_same('text', ws_memory_entry_fault(array('title' => 'x', 'text' => str_repeat('a', 4001)), 1000)['field']);
	pg_assert_same('tags', ws_memory_entry_fault(array('title' => 'x', 'tags' => array('a b')), 1000)['field']);
	pg_assert_same('expires_at', ws_memory_entry_fault(array('title' => 'x', 'expires_at' => 5), 1000)['field']);
}

function test_workspace_mark_by_kind()
{
	$person = array('marked_by' => 7, 'marked_by_kind' => 'user');
	$agent = array('marked_by' => 9, 'marked_by_kind' => 'app');
	$old = array('marked_by' => 7);

	pg_assert_true(!ws_mark_by_app($person), 'a person');
	pg_assert_true(ws_mark_by_app($agent), 'an application');
	pg_assert_true(!ws_mark_by_app($old), 'a row read before the column: a person');
	pg_assert_true(!ws_mark_by_app(array('marked_by' => 0, 'marked_by_kind' => 'app')), 'nobody');

	// An agent's mark never names the person whose id the application has.
	pg_assert_same(array(7), ws_mark_people_ids(array($person, $agent, $old, array('marked_by' => 0))));
	pg_assert_same('Ada', ws_mark_by_name($person, array(7 => array('name' => 'Ada'))));
	pg_assert_same('', ws_mark_by_name(array('marked_by' => 0), array()));
}

function test_workspace_memory_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.messages.mark', 'workspace.memory.list', 'workspace.memory.get', 'workspace.memory.create', 'workspace.memory.update', 'workspace.memory.delete', 'workspace.memory.delete_post') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		// Agents use them: every one is an MCP tool.
		pg_assert_true(!isset($routes[$id]['mcp']) || ($routes[$id]['mcp'] !== false), $id . ' is a tool');

		if (isset($routes[$id]) && ($routes[$id]['method'] !== 'GET')) {
			pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		}
	}

	pg_assert_same('/workspace/memory/{id}/delete', $routes['workspace.memory.delete_post']['path']);
	pg_assert_same(array('PATCH'), $routes['workspace.memory.update']['also_accepts']);
}

function test_workspace_memory_panel_wiring()
{
	$source = function ($relative) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	};

	foreach (array('ws_memory_list', 'ws_memory_save', 'ws_memory_delete') as $action) {
		pg_assert_same(1, substr_count($source('includes/panel/actions.php'), "'" . $action . "'"), $action . ' is a panel action');
		pg_assert_same(1, substr_count($source('includes/workspace/actions.php'), "case '" . $action . "':"), $action . ' is handled');
	}

	pg_assert_true(strpos($source('includes/fn/output.php'), "'assets/js/workspace_memory.js',") !== false, 'the tab is in workspace.min.js');
	pg_assert_true(strpos($source('assets/js/workspace.min.js'), 'PGWsMemory') !== false, 'the bundle is built');
}

function test_workspace_memory_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_channel_memory();"), 'called once');
	pg_assert_same(1, substr_count($source, '// 11.0.173' . "\n"), 'its label is its own');
	pg_assert_same(1, substr_count($install, "\t\t'ws_channel_memory',\n"), 'on get_tables()');

	$result = workspace_channel_memory_test_run_twice();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(2, count($first), 'one table and one column');
	pg_assert_same(1, count(preg_grep('/^CREATE TABLE ws_channel_memory \(/', $first)), 'the table');
	pg_assert_same(1, count(preg_grep("/^ALTER TABLE `ws_messages` ADD `marked_by_kind` ENUM\('user','app'\) NOT NULL DEFAULT 'user' AFTER marked_by$/", $first)), 'the column, people by default');
	pg_assert_same(array(), $second, 'the second run writes nothing');
}

// Runs the step twice in a child process against a fake db layer that
// answers the runner's probes from an array and logs every write. Null when
// PHP cannot be started again.
function workspace_channel_memory_test_run_twice()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmemschema');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array('ws_messages' => array('marked_by' => 'int'));
$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]]);
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^CREATE TABLE (\w+) \(/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]] = array('id' => 'int');
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` /', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = 'enum';
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_channel_memory();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

// A poll or an approval an agent asked is settled in the agent's name, and a
// decision an application posts is marked by the application: none of them
// reads as a decision of the person the agent acts for.
function test_workspace_agent_questions_settle_in_the_agents_name()
{
	$source = function ($relative) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	};
	$function = function ($file, $name) use ($source) {
		$text = $source($file);
		$start = strpos($text, 'function ' . $name . '(');

		return ($start === false) ? '' : substr($text, $start, strpos($text, "\n}\n", $start) - $start);
	};

	foreach (array(array('includes/workspace/interact.php', 'ws_poll_close', '$poll[\'message_id\']'), array('includes/workspace/approvals.php', 'ws_approval_settle', '$approval[\'message_id\']')) as $case) {
		$body = $function($case[0], $case[1]);

		pg_assert_contains('$app_id = ws_message_app_id(' . $case[2] . ');', $body, $case[1] . ' finds the agent that asked');
		pg_assert_contains("'kind' => 'decision', 'parent_id' => (int) " . $case[2] . ", 'app_id' => \$app_id)", $body, $case[1] . ' writes the outcome as the agent');
	}

	$send = $function('includes/workspace/messages.php', 'ws_message_send');

	pg_assert_contains('$app_mark = $marked && ($app_id > 0) && ws_marks_kind_ready();', $send);
	pg_assert_contains("(\$app_mark ? \$app_id : (int) \$viewer['id'])", $send, 'the application is the marker');
}

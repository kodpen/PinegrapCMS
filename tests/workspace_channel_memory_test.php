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

	foreach (array('ws_memory_list', 'ws_memory_save', 'ws_memory_delete', 'ws_memory_move') as $action) {
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

function test_workspace_memory_project_and_general()
{
	pg_assert_same('Site launch', ws_memory_project("  Site \n  launch "));
	pg_assert_same('', ws_memory_project(null));

	pg_assert_true(ws_memory_is_general(array('channel_id' => 0)), 'channel_id 0 is general');
	pg_assert_true(ws_memory_is_general(array('channel_id' => '0')), 'as the database gives it');
	pg_assert_true(!ws_memory_is_general(array('channel_id' => 5)), 'a channel record');
	pg_assert_true(!ws_memory_is_general(array()), 'no channel id is not general');
	pg_assert_true(!ws_memory_is_general(null), 'nothing is not general');
	pg_assert_true(ws_memory_is_general(ws_memory_general_channel(), 'id'), 'the general channel stand-in');
	pg_assert_true(!ws_memory_is_general(array('id' => 3), 'id'), 'a channel row');

	$now = 1000;
	pg_assert_same(null, ws_memory_entry_fault(array('title' => 'x', 'project' => str_repeat('p', 80)), $now));
	$fault = ws_memory_entry_fault(array('title' => 'x', 'project' => str_repeat('p', 81)), $now);
	pg_assert_same('project', $fault ? $fault['field'] : '', 'a project over 80 characters names its field');
}

function test_workspace_memory_general_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_memory.php');

	// The sweep deletes records of channels that are gone, never a general one.
	pg_assert_true(strpos($source, 'OR (c.id IS NULL AND m.channel_id > 0)') !== false, 'the sweep leaves general records alone');

	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.memory.create', 'workspace.memory.update') as $id) {
		$names = array_map(function ($param) {
			return $param['name'];
		}, $routes[$id]['params']);
		pg_assert_true(in_array('project', $names, true), $id . ' takes a project');
	}

	$schema = ws_api_memory_index_entry_schema();
	pg_assert_same('string', $schema['project']);
	pg_assert_same('boolean', $schema['general']);

	$present = ws_memory_present(array('id' => 1, 'channel_id' => 0, 'project' => ' Launch ', 'title' => 't', 'tags' => '', 'pinned' => 0, 'expires_at' => 0, 'author_kind' => 'user', 'author_id' => 0, 'updated_by_kind' => 'user', 'updated_by_id' => 0, 'created_at' => 1, 'updated_at' => 1, 'text' => ''));
	pg_assert_true($present['general'], 'a general record says so');
	pg_assert_same('Launch', $present['project']);

	$old = ws_memory_present(array('id' => 2, 'channel_id' => 4, 'title' => 't', 'tags' => '', 'pinned' => 0, 'expires_at' => 0, 'author_kind' => 'user', 'author_id' => 0, 'updated_by_kind' => 'user', 'updated_by_id' => 0, 'created_at' => 1, 'updated_at' => 1, 'text' => ''));
	pg_assert_same('', $old['project'], 'a row read before the step has no project');
	pg_assert_true(!$old['general'], 'a channel record');
}

function test_workspace_memory_project_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_memory_project();"), 'called once');
	pg_assert_same(1, substr_count($source, '// 11.0.236' . "\n"), 'its label is its own');

	$result = workspace_channel_memory_test_run_twice('upgrade_2026_11_0_ws_memory_project', array('ws_channel_memory' => array('id' => 'int', 'channel_id' => 'int')));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array("ALTER TABLE `ws_channel_memory` ADD `project` VARCHAR(80) NOT NULL DEFAULT '' AFTER channel_id"), $first, 'one column');
	pg_assert_same(array(), $second, 'the second run writes nothing');
}

// Runs the step twice in a child process against a fake db layer that
// answers the runner's probes from an array and logs every write. Null when
// PHP cannot be started again.
function workspace_channel_memory_test_run_twice($step = 'upgrade_2026_11_0_ws_channel_memory', $schema = array('ws_messages' => array('marked_by' => 'int')))
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

$GLOBALS['fake_schema'] = $pg_schema;
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
	$pg_step();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$pg_step = ' . var_export((string) $step, true) . ";\n"
		. '$pg_schema = ' . var_export($schema, true) . ";\n"
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

// In an agent channel the brief lists the general memory first, each line
// naming its project, inside the same size as the channel's own index.
function test_workspace_memory_digest_general_first()
{
	$now = 1000;
	$general = array(
		array('id' => 9, 'project' => 'Site  launch', 'title' => 'Release day', 'tags' => 'ci', 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 40),
		array('id' => 8, 'project' => '', 'title' => 'House rule', 'tags' => '', 'pinned' => 1, 'expires_at' => 0, 'updated_at' => 1),
		array('id' => 7, 'project' => 'Old', 'title' => 'Gone', 'tags' => '', 'pinned' => 0, 'expires_at' => 900, 'updated_at' => 99),
	);
	$entries = array(array('id' => 1, 'title' => 'Own', 'tags' => array(), 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 10));
	$decisions = array(array('body' => 'Ship on Friday', 'marked_at' => 20));
	$general_heading = 'General memory, shared by the agent channels (read an entry with workspace.memory.get, its id without the #):';
	$heading = 'Channel memory (read an entry with workspace.memory.get, its id without the #):';

	pg_assert_same($general_heading . "\n- #8 House rule\n- #9 Site launch: Release day [ci]\n\n" . $heading . "\n- #1 Own\n\nDecisions in this channel:\n- Ship on Friday",
		ws_memory_digest($entries, $decisions, $now, WS_MEMORY_DIGEST_MAX, null, $general));

	// Without the general records the digest is the channel's alone.
	pg_assert_same(ws_memory_digest($entries, $decisions, $now), ws_memory_digest($entries, $decisions, $now, WS_MEMORY_DIGEST_MAX, null, array()));

	// A general memory alone still makes a digest.
	pg_assert_same($general_heading . "\n- #8 House rule\n- #9 Site launch: Release day [ci]", ws_memory_digest(array(), array(), $now, WS_MEMORY_DIGEST_MAX, null, $general));

	// One size holds both: the general lines take their room first, and the
	// count names the general list when a general line was left out.
	$small = ws_memory_digest($entries, $decisions, $now, mb_strlen($general_heading) + 2 + 16, null, $general);
	pg_assert_same($general_heading . "\n- #8 House rule\n\n(3 more not shown: list them with workspace.memory.general.list, workspace.memory.list and workspace.decisions.list.)", $small);
}

function test_workspace_memory_move_fault()
{
	$row = array('id' => 3, 'channel_id' => 5);
	$agent = array('id' => 5, 'agent_channel' => 1);
	$plain = array('id' => 5, 'agent_channel' => 0);

	pg_assert_same(null, ws_memory_move_fault($row, $agent, true, true));

	$general = ws_memory_move_fault(array('id' => 3, 'channel_id' => 0), null, true, true);
	pg_assert_true(!$general['forbidden'] && ($general['error'] !== ''), 'already general: 422');

	$post = ws_memory_move_fault($row, $agent, false, true);
	pg_assert_true($post['forbidden'], 'not a poster of the channel');

	$not_agent = ws_memory_move_fault($row, $plain, true, true);
	pg_assert_true(!$not_agent['forbidden'] && ($not_agent['error'] !== ''), 'only an agent channel\'s records move');

	$keeper = ws_memory_move_fault($row, $agent, true, false);
	pg_assert_true($keeper['forbidden'], 'the mover must keep the general memory');

	pg_assert_true(ws_memory_move_fault(null, $agent, true, true) !== null, 'no record');
}

function test_workspace_memory_general_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expect = array(
		'workspace.memory.general.list'   => array('GET', '/workspace/memory/general'),
		'workspace.memory.general.create' => array('POST', '/workspace/memory/general'),
		'workspace.memory.move_general'   => array('POST', '/workspace/memory/{id}/general'),
	);

	foreach ($expect as $id => $where) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		pg_assert_same($where, array($routes[$id]['method'], $routes[$id]['path']), $id);
		pg_assert_true(!isset($routes[$id]['mcp']) || ($routes[$id]['mcp'] !== false), $id . ' is a tool');

		if ($where[0] !== 'GET') {
			pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		}
	}

	// GET /workspace/memory/{id} takes digits only, so "general" never reaches it.
	$get = $routes['workspace.memory.get']['params'][0];
	pg_assert_same(array('id', 'path', 'int'), array($get['name'], $get['in'], $get['type']));

	$names = array_map(function ($param) {
		return $param['name'];
	}, $routes['workspace.memory.general.create']['params']);
	pg_assert_true(in_array('project', $names, true) && !in_array('id', $names, true), 'a general record takes a project and no channel');

	// The brief reads the general records only for an agent channel's asker who keeps them.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_memory.php');
	pg_assert_contains("\$general = ws_memory_brief_general(\$viewer, \$channel) ? ws_memory_rows(0, false) : array();", $source);
	pg_assert_contains('return function_exists(\'ws_agent_channel_is\') && ws_agent_channel_is($channel) && ws_memory_general_can($viewer);', $source);
}

function test_workspace_memory_first_line()
{
	pg_assert_same('Trains land at 08:00.', ws_memory_first_line("\n  \nTrains   land at 08:00.\nThen the next."));
	pg_assert_same('', ws_memory_first_line(" \n\t"));
	pg_assert_same('abcd…', ws_memory_first_line('abcdefgh', 5), 'a long line is cut with an ellipsis');
	pg_assert_same('çğış', ws_memory_first_line('çğış', 4), 'a line at the limit stays whole, counted in characters');
}

function test_workspace_memory_groups()
{
	$entries = array(
		array('id' => 1, 'project' => ''),
		array('id' => 2, 'project' => 'launch'),
		array('id' => 3, 'project' => 'Billing'),
		array('id' => 4, 'project' => 'Launch '),
		array('id' => 5, 'project' => '2026'),
	);
	$groups = ws_memory_groups($entries);

	pg_assert_same(array('2026', 'Billing', 'launch', ''), array_map(function ($group) {
		return $group['project'];
	}, $groups), 'projects in alphabetical order, case aside, the ones without a project last');
	pg_assert_same(array(2, 4), array_map(function ($entry) {
		return $entry['id'];
	}, $groups[2]['entries']), 'one heading per project whatever its case, records in the order given');
	pg_assert_same(array(), ws_memory_groups(array()));
}

function test_workspace_memory_tab_parts_wiring()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	// The general part comes first, only in an agent channel for whoever keeps it.
	pg_assert_contains("\$general = !ws_memory_is_general(\$channel, 'id') && ws_memory_brief_general(\$viewer, \$channel);", $actions);
	pg_assert_contains("\$parts[] = ws_memory_panel_part('general', 0, true);", $actions);
	pg_assert_true(strpos($actions, "ws_memory_panel_part('general', 0, true)") < strpos($actions, "ws_memory_panel_part(ws_memory_is_general(\$channel, 'id') ? 'general' : 'channel'"), 'the general part before the channel\'s');
	// Moving goes through GM2's check before the write.
	pg_assert_true(strpos($actions, '$check = ws_memory_move_check($viewer, $row);') < strpos($actions, "ws_memory_move_general(\$row, 'user', \$viewer['id']);"), 'checked before it moves');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_memory.js');
	pg_assert_contains("h.api('ws_memory_move', { id: entry.id })", $script, 'a row moves to the general memory');
	pg_assert_contains("return general ? { general: 1 } : { channel_id: channel.id };", $script, 'a general record is written with general=1');
	pg_assert_contains('fields.project = project.value;', $script, 'the form files a record under a project');
}

// Outside a channel the general memory is a drawer of the channel screen,
// listed by ws_memory_list with general=1 alone.
function test_workspace_memory_general_drawer_wiring()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("} else if (query.panel === 'memory') {", $script, 'the More entry\'s address opens it');
	pg_assert_contains("memory: function () { self.showMemory(); },", $script, 'the list opens it in place');
	pg_assert_contains("window.PGWsMemory.mount(body, { general: true });", $script, 'the general part alone');

	$memory = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_memory.js');
	pg_assert_contains("h.api('ws_memory_list', target(!!channel.general))", $memory, 'listed with general=1');

	$nav = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/nav.php');
	pg_assert_contains("'memory'        => function_exists('ws_memory_general_can') && ws_memory_general_can(\$viewer),", $nav, 'under the general memory\'s gate');
}

// An agent channel's brief reads every agent channel's decisions: the heading
// says so and a decision made in another channel names it first.
function test_workspace_channel_memory_digest_across_agent_channels()
{
	$decisions = array(
		array('body' => 'Ship on Friday', 'marked_at' => 30, 'channel' => ''),
		array('body' => "Keep the\nold API", 'marked_at' => 20, 'channel' => 'old dev'),
	);
	$heading = 'Decisions in the agent channels (one made in another channel names it first):';

	pg_assert_same($heading . "\n- Ship on Friday\n- #old dev: Keep the old API", ws_memory_digest(array(), $decisions, 100, WS_MEMORY_DIGEST_MAX, null, array(), true));
	pg_assert_same("Decisions in this channel:\n- Ship on Friday\n- Keep the old API", ws_memory_digest(array(), $decisions, 100), 'one channel: no names');

	$many = array_fill(0, 12, array('body' => 'x', 'marked_at' => 1, 'channel' => ''));
	pg_assert_contains('workspace.decisions.list (agent_channels only)', ws_memory_digest(array(), $many, 100, WS_MEMORY_DIGEST_MAX, 40, array(), true), 'the count says where the rest are');
}

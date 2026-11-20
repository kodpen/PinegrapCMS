<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guide for AI agents (includes/fn/agent_guide.php and its
 * topic files): the sizes it is held to, every tool its text names is an
 * offered route, the digest follows the text, a topic without its file is not
 * listed, and the doors - GET /guide, the MCP tool, resources and opening
 * instructions - give it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/prompts.php');

// Every route of the core and of the modules, switched on or not, by id.
function agent_guide_test_routes()
{
	$routes = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes()) as $route) {
		if (!isset($routes[$route['id']])) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

// The topics whose file is there, for a caller holding every route on a site
// running every module.
function agent_guide_test_listed_all()
{
	return pg_agent_guide_listed(pg_agent_guide_registry(), array_keys(agent_guide_test_routes()), array('store', 'workspace', 'erp'));
}

function test_agent_guide_registry_names_the_eight_topics()
{
	$registry = pg_agent_guide_registry();

	pg_assert_same(array('start', 'designer', 'content', 'forms', 'store', 'workspace', 'erp', 'panel'), array_keys($registry));

	foreach ($registry as $id => $topic) {
		pg_assert_same(1, preg_match('/^[a-z_]+$/', $id), $id . ': id');
		pg_assert_same(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_' . $id . '.php', $topic['file'], $id . ': its own file');

		foreach (array('title', 'when', 'module', 'tools') as $key) {
			pg_assert_true(array_key_exists($key, $topic), $id . ': ' . $key);
		}

		pg_assert_true(in_array($topic['module'], array('', 'store', 'workspace', 'erp'), true), $id . ': a module the guide knows');
	}

	pg_assert_true(in_array('start', agent_guide_test_listed_all(), true), 'start has its file');
}

// The list, a topic and the opening instructions stay within their bytes.
function test_agent_guide_sizes()
{
	$sizes = pg_agent_guide_sizes();
	$registry = pg_agent_guide_registry();
	$listed = agent_guide_test_listed_all();
	$modules = array('store', 'workspace', 'erp');

	$list = pg_agent_guide_answer($registry, $listed, $modules);

	pg_assert_same(200, $list['status']);
	pg_assert_true(strlen($list['body']['text']) <= $sizes['list'], 'the topic list is ' . strlen($list['body']['text']) . ' bytes');

	foreach ($listed as $id) {
		$text = pg_agent_guide_topic_text($registry, $id, $modules);

		pg_assert_true(is_string($text) && ($text !== ''), $id . ': has text');
		pg_assert_true(strlen($text) <= $sizes['topic'], $id . ' is ' . strlen($text) . ' bytes');
	}

	pg_assert_true(strlen(pg_mcp_instructions()) <= $sizes['instructions'], 'the opening instructions are ' . strlen(pg_mcp_instructions()) . ' bytes');
}

// A tool the guide names is a route an MCP client is offered, so a renamed or
// removed route fails here rather than leaving the guide behind.
function test_agent_guide_every_named_tool_is_an_offered_route()
{
	$registry = pg_agent_guide_registry();
	$routes = agent_guide_test_routes();

	foreach (agent_guide_test_listed_all() as $id) {
		$sections = pg_agent_guide_sections($registry, $id);
		$named = pg_agent_guide_named_routes($sections);

		if ($id === 'start') {
			pg_assert_true(count($named) >= 10, 'start names the tools of its jobs');
		}

		foreach ($named as $route_id) {
			pg_assert_true(isset($routes[$route_id]), $id . ' names ' . $route_id . ', which is no route');
			pg_assert_true(isset($routes[$route_id]) && pg_mcp_route_is_offered($routes[$route_id]), $id . ' names ' . $route_id . ', which is no tool');
		}

		$text = pg_agent_guide_topic_text($registry, $id, array('store', 'workspace', 'erp'));

		pg_assert_same(0, preg_match('/\{tool:/', $text), $id . ': every mark is written as a name');
	}
}

function test_agent_guide_start_says_the_records_and_rules()
{
	$text = pg_agent_guide_topic_text(pg_agent_guide_registry(), 'start', array('store', 'workspace', 'erp'));

	foreach (array('Page:', 'Folder:', 'Design:', 'Region:', 'System widget:', 'Collection:', 'Form:', 'Product:', 'Order:', 'Channel:', 'minor units', 'next_cursor', 'dry_run', 'Propose rather than write', 'design_proposals_create', 'guide_read') as $needle) {
		pg_assert_contains($needle, $text, 'start says ' . $needle);
	}

	foreach (array('records', 'jobs', 'writes', 'calls', 'next') as $section) {
		pg_assert_contains('(#' . $section . ')', $text, 'section ' . $section);
	}
}

// A line kept for a module is left out while the module is off.
function test_agent_guide_module_lines_follow_the_module()
{
	$text = pg_agent_guide_topic_text(pg_agent_guide_registry(), 'start', array());

	pg_assert_false(strpos($text, 'Product:') !== false, 'no store, no product line');
	pg_assert_false(strpos($text, 'workspace_changes_propose') !== false, 'no workspace, no proposal card line');
	pg_assert_contains('Page:', $text, 'the rest stays');
}

function test_agent_guide_digest_changes_with_the_text()
{
	pg_assert_same(16, strlen(pg_agent_guide_digest('a')));
	pg_assert_same(pg_agent_guide_digest('a'), pg_agent_guide_digest('a'), 'the same text, the same digest');
	pg_assert_false(pg_agent_guide_digest('a') === pg_agent_guide_digest('b'), 'another text, another digest');

	$registry = pg_agent_guide_registry();
	$listed = agent_guide_test_listed_all();
	$all = pg_agent_guide_answer($registry, $listed, array('store', 'workspace', 'erp'), 'start');
	$none = pg_agent_guide_answer($registry, $listed, array(), 'start');

	pg_assert_same(pg_agent_guide_digest($all['body']['text']), $all['body']['digest'], 'the digest is the text\'s');
	pg_assert_false($all['body']['digest'] === $none['body']['digest'], 'a module line more, another digest');
}

// A topic named in the registry whose builder file is not there is not
// listed, nor answered; one with its file is.
function test_agent_guide_topic_without_its_file_is_not_listed()
{
	$dir = sys_get_temp_dir() . '/pg_agent_guide_test_' . getmypid();
	@mkdir($dir);
	$file = $dir . '/agent_guide_testonly.php';
	file_put_contents($file, "<?php\nfunction pg_agent_guide_testonly_sections() { return array(array('id' => 'one', 'heading' => 'One', 'lines' => array('A line.'))); }\n");

	$registry = array(
		'start'    => pg_agent_guide_registry()['start'],
		'missing'  => array('title' => 'Missing', 'when' => 'Never.', 'module' => '', 'tools' => array(), 'file' => $dir . '/agent_guide_missing.php'),
		'testonly' => array('title' => 'Test', 'when' => 'Now.', 'module' => '', 'tools' => array('design.'), 'file' => $file)
	);

	pg_assert_same(array('start'), pg_agent_guide_listed($registry, array('meta.read'), array()), 'no file, no topic; no design tool, no design topic');
	pg_assert_same(array('start', 'testonly'), pg_agent_guide_listed($registry, array('design.pages.get'), array()), 'a file and a tool, listed');

	$answer = pg_agent_guide_answer($registry, array('start'), array(), 'missing');

	pg_assert_same(404, $answer['status']);
	pg_assert_same('topic', $answer['what']);

	$answer = pg_agent_guide_answer($registry, array('start', 'testonly'), array(), 'testonly', 'one');

	pg_assert_same(200, $answer['status']);
	pg_assert_same("## One (#one)\n- A line.\n", $answer['body']['text'], 'one section alone');
	pg_assert_same('section', pg_agent_guide_answer($registry, array('start', 'testonly'), array(), 'testonly', 'two')['what'], 'a section it does not have');

	pg_assert_same('testonly', pg_agent_guide_topic_of_route($registry, array('start', 'testonly'), 'design.pages.get'));
	pg_assert_same('', pg_agent_guide_topic_of_route($registry, array('start'), 'design.pages.get'), 'not listed, no guide line');

	@unlink($file);
	@rmdir($dir);

	$registry = pg_agent_guide_registry();
	$store = $registry['store'];

	pg_assert_same(array(), pg_agent_guide_listed(array('store' => array('file' => $registry['start']['file']) + $store), array('products.list'), array()), 'a module off, not listed');
}

function test_agent_guide_list_answer()
{
	$answer = pg_agent_guide_answer(pg_agent_guide_registry(), array('start'), array());

	pg_assert_same(200, $answer['status']);
	pg_assert_same(null, $answer['body']['topic']);
	pg_assert_same(array('start'), $answer['body']['topics']);
	pg_assert_contains('- start: Start here.', $answer['body']['text']);
	pg_assert_same(array('version', 'digest', 'topic', 'section', 'topics', 'text'), array_keys($answer['body']));
}

// The route: no scope, credentials needed, offered over MCP as guide_read.
function test_agent_guide_route_is_a_tool_without_a_scope()
{
	$routes = agent_guide_test_routes();
	$route = $routes['guide.read'];

	pg_assert_same('', $route['scope']);
	pg_assert_true(empty($route['public']), 'credentials are needed');
	pg_assert_true(pg_mcp_route_is_offered($route), 'offered as a tool');
	pg_assert_true(isset(pg_mcp_routes_for(api_schema(), array())['guide_read']), 'to a caller with no scope at all');

	$auth_me = $routes['auth.me'];

	pg_assert_false(pg_mcp_route_is_offered($auth_me), 'an empty scope alone is still no tool');

	$tool = pg_mcp_tool_from_route($route);

	pg_assert_same('guide_read', $tool['name']);
	pg_assert_true($tool['annotations']['readOnlyHint'], 'read only');
}

function test_agent_guide_mcp_resources()
{
	pg_assert_same(array('kind' => 'guide', 'arguments' => array()), pg_mcp_resource_parse('pinegrap://guide'));
	pg_assert_same(array('kind' => 'guide_topic', 'arguments' => array('topic' => 'start')), pg_mcp_resource_parse('pinegrap://guide/start'));
	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://guide/Start'), 'a topic is lowercase');

	$routes = pg_mcp_routes_for(api_schema(), array());
	$target = pg_mcp_resource_target(array('uri' => 'pinegrap://guide/start'), $routes, true);

	pg_assert_same('guide.read', $target['route']['id']);
	pg_assert_same(array('topic' => 'start'), $target['arguments']);

	$uris = array();

	foreach (pg_mcp_resources_list($routes) as $entry) {
		$uris[] = $entry['uri'];
	}

	pg_assert_true(in_array('pinegrap://guide', $uris, true), 'listed to every caller');
}

function test_agent_guide_opening_instructions_point_at_it()
{
	$text = pg_mcp_instructions();

	pg_assert_same(2, count(pg_mcp_instructions_guide()));

	foreach (pg_mcp_instructions_guide() as $line) {
		pg_assert_contains($line, $text);
	}

	pg_assert_contains('pinegrap://guide', $text);
	pg_assert_contains('guide_read', $text);
	pg_assert_true(strpos($text, 'pinegrap://guide') < strpos($text, "\n\nRules:\n"), 'before the rules');
}

// A tool of an area with a listed topic ends with "Guide: <topic>."; while
// only start is built, no tool carries the line and the form lines stay.
function test_agent_guide_tool_guidance_line()
{
	$registry = pg_agent_guide_registry();
	$listed = agent_guide_test_listed_all();

	foreach (array('design.pages.get', 'forms.submission_create', 'products.list') as $route_id) {
		$topic = pg_agent_guide_topic_of_route($registry, $listed, $route_id);
		$line = pg_mcp_tool_guide_line($route_id);

		pg_assert_same(($topic === '') ? '' : 'Guide: ' . $topic . '.', $line, $route_id);
	}

	pg_assert_contains('Read the form\'s fields first', pg_mcp_tool_guidance('forms.submission_create'), 'the form line stays');
	pg_assert_same('', pg_mcp_tool_guide_line('meta.read'), 'start names no tools of its own');
}

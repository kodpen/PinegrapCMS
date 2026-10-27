<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for flow templates over the external API
 * (includes/workspace/api_flows.php): a draft's input made from a template
 * and its answers (ws_api_flow_from_template()), what the listing hands out
 * per template, and the routes as declared.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// The gallery as a staff member with every count sees it.
function ws_flow_api_templates_test_list()
{
	return ws_flow_templates(array(
		'me'       => 5,
		'channel'  => 0,
		'site'     => 'https://example.com/',
		'email'    => 'owner@example.com',
		'today'    => '2026-10-27',
		'metrics'  => array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'orders_today', 'sales_today', 'forms_new',
			'comments_pending', 'visitors_yesterday', 'stock_low', 'invoices_overdue', 'orders_new'),
		'recap_ai' => false,
	));
}

function ws_flow_api_templates_test_get($key)
{
	foreach (ws_flow_api_templates_test_list() as $template) {
		if ($template['key'] === $key) {
			return $template;
		}
	}

	return null;
}

// A template without answers: its own boxes, its title for a name, the
// other fields of the call kept.
function test_ws_flow_api_from_template_plain()
{
	$template = ws_flow_api_templates_test_get('quote_approval');
	$made = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval'), array('channel_id' => 3));

	pg_assert_true($made['ok']);
	pg_assert_same($template['title'], $made['data']['name']);
	pg_assert_same(3, $made['data']['channel_id']);
	pg_assert_same(ws_flow_template_apply($template, $template['graph'], array()), $made['data']['graph']);

	$named = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval'), array('name' => 'Quotes'));
	pg_assert_same('Quotes', $named['data']['name']);
}

// Answers go into the boxes that ask for them; one missing or wrong is
// refused with the setting's field.
function test_ws_flow_api_from_template_answers()
{
	$template = ws_flow_api_templates_test_get('quote_approval');
	$types = array_column($template['settings'], 'type');
	pg_assert_true(in_array('channel', $types, true) && in_array('person', $types, true), 'quote approval asks for a channel and people');

	$made = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval', 'answers' => array('channel' => 7, 'person' => array(9, 9, 11))), array());
	pg_assert_true($made['ok']);
	pg_assert_same(ws_flow_template_apply($template, $template['graph'], array('channel' => 7, 'person' => array(9, 11))), $made['data']['graph']);
	pg_assert_true($made['data']['graph'] !== $template['graph'], 'the answers changed the boxes');

	$missing = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval', 'answers' => array('person' => array(9))), array());
	pg_assert_same(false, $missing['ok']);
	pg_assert_same(422, $missing['status']);
	pg_assert_same('answers.channel', $missing['field']);

	$bad = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval', 'answers' => 'yes'), array());
	pg_assert_same(false, $bad['ok']);
}

// A key not offered, or boxes beside the template, are refused on template.
function test_ws_flow_api_from_template_refusals()
{
	$unknown = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'nope'), array());
	pg_assert_same(array(false, 422, 'template'), array($unknown['ok'], $unknown['status'], $unknown['field']));

	$none = ws_api_flow_from_template(array(), array('template' => 'quote_approval'), array());
	pg_assert_same('template', $none['field']);

	$both = ws_api_flow_from_template(ws_flow_api_templates_test_list(), array('template' => 'quote_approval', 'nodes' => array()), array());
	pg_assert_same(array(false, 'template'), array($both['ok'], $both['field']));
}

// The listing hands out what its schema names, with the setting types only.
function test_ws_flow_api_template_present()
{
	$template = ws_flow_api_templates_test_get('text_review');
	$present = ws_api_flow_template_present($template);

	pg_assert_same(array_keys(ws_api_flow_template_schema()), array_keys($present));
	pg_assert_same(array_column($template['settings'], 'type'), $present['settings']);
	pg_assert_same($template['boxes'], count($present['graph']['nodes']));
}

// The listing is a read tool; the create route takes template and answers
// and no longer requires a name.
function test_ws_flow_api_template_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.flow_templates.list']));
	pg_assert_same('workspace:read', $routes['workspace.flow_templates.list']['scope']);
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.flow_templates.list']), 'the listing is a tool');

	$params = array_column($routes['workspace.flows.create']['params'], null, 'name');
	pg_assert_same('string', $params['template']['type']);
	pg_assert_same('object', $params['answers']['type']);
	pg_assert_true(empty($params['name']['required']), 'a template names the flow');
}

// The source of a function, comments taken out.
function ws_flow_api_templates_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $source);
}

// The template is turned into boxes only for somebody who may build flows,
// before the save check, and both before the rehearsal stops: a dry run
// meets every refusal a real call meets, and nothing is written before.
function test_ws_flow_api_templates_create_order()
{
	$source = ws_flow_api_templates_test_source('ws_api_flows_create');
	$gate = strpos($source, 'ws_can_schedule($viewer)');
	$template = strpos($source, 'ws_api_flow_from_template(ws_flow_templates_for($viewer)');
	$check = strpos($source, 'ws_flow_save_check(');
	$stop = strpos($source, 'api_dry_run_stop(');
	$save = strpos($source, 'ws_flow_save($viewer, $data)');

	pg_assert_true($gate !== false && $template !== false && $check !== false && $stop !== false && $save !== false, 'every step is there');
	pg_assert_true($gate < $template, 'only a builder gets a template');
	pg_assert_true($template < $check, 'the template feeds the save check');
	pg_assert_true($check < $stop && $stop < $save, 'checked, rehearsed, then written');
	pg_assert_true(strpos($source, 'ws_flow_publish(') === false, 'a draft from the API is never published');
}

// The listing reads as the owner of the application, once the tables are
// there, and offers only what the gallery offers them.
function test_ws_flow_api_templates_list_handler()
{
	$source = ws_flow_api_templates_test_source('ws_api_flow_templates_list');

	pg_assert_true(strpos($source, 'ws_api_viewer()') < strpos($source, 'ws_api_flows_ready()'));
	pg_assert_contains('ws_flow_templates_for($viewer)', $source);
}

// The create tool still rehearses, writes, and takes template and answers;
// name is no longer one it must be sent.
function test_ws_flow_api_templates_create_tool()
{
	$route = null;

	foreach (ws_api_routes() as $item) {
		if ($item['id'] === 'workspace.flows.create') {
			$route = $item;
		}
	}

	pg_assert_true(!empty($route['dry_run']), 'create rehearses');
	pg_assert_true(pg_mcp_route_is_offered($route), 'create is a tool');

	$tool = pg_mcp_tool_from_route($route);
	$properties = (array) $tool['inputSchema']['properties'];

	pg_assert_same('string', $properties['template']['type'] ?? null);
	pg_assert_same('object', $properties['answers']['type'] ?? null);
	pg_assert_same('boolean', $properties['dry_run']['type'] ?? null);
	pg_assert_false(in_array('name', (array) ($tool['inputSchema']['required'] ?? array()), true), 'name is not required');
	pg_assert_false($tool['annotations']['readOnlyHint'], 'create writes');

	$list = null;

	foreach (ws_api_routes() as $item) {
		if ($item['id'] === 'workspace.flow_templates.list') {
			$list = $item;
		}
	}

	pg_assert_true(pg_mcp_tool_from_route($list)['annotations']['readOnlyHint'], 'the listing only reads');
}

// A template with no settings takes an empty answers object; a template
// whose answers are all given passes every setting type it asks for.
function test_ws_flow_api_from_template_every_setting()
{
	$values = array('channel' => 4, 'person' => array(6), 'time' => '07:30', 'url' => 'https://example.com/health', 'address' => 'a@example.com');

	foreach (ws_flow_api_templates_test_list() as $template) {
		$answers = array();

		foreach ($template['settings'] as $setting) {
			$answers[$setting['type']] = $values[$setting['type']];
		}

		$made = ws_api_flow_from_template(array($template), array('template' => $template['key'], 'answers' => $answers), array());
		pg_assert_true($made['ok'], $template['key'] . ' takes its answers');
		pg_assert_same(ws_flow_template_apply($template, $template['graph'], ws_flow_template_answers_check($template, $answers)['values']), $made['data']['graph'], $template['key']);
	}
}

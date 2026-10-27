<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow templates (includes/workspace/flow_templates.php):
 * every template's graph is one the engine publishes once its settings are
 * filled, its boxes are kinds the flow knows, every text it carries is in
 * the language shards and those it takes over from the scheduled actions'
 * ready templates are the same sentences, its settings name boxes and
 * fields that are there, the counts it needs leave it out when the person
 * cannot read them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');

// What a person who reads every count is offered, with no channel open.
function ws_flow_templates_test_fill($overrides = array())
{
	return $overrides + array(
		'me'       => 5,
		'channel'  => 0,
		'site'     => 'https://example.com/',
		'email'    => 'owner@example.com',
		'today'    => '2026-10-27',
		'metrics'  => array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'orders_today', 'sales_today', 'forms_new',
			'comments_pending', 'visitors_yesterday', 'stock_low', 'invoices_overdue', 'orders_new'),
		'recap_ai' => false,
	);
}

// A value of each kind of setting, as the setup panel would give it.
function ws_flow_templates_test_value($type)
{
	switch ($type) {
		case 'channel':
			return 7;
		case 'person':
			return array(9);
		case 'time':
			return '08:15';
		case 'url':
			return 'https://example.org/';
		case 'address':
			return 'team@example.org';
	}

	return null;
}

// The graph with every setting given a value.
function ws_flow_templates_test_filled($template)
{
	$graph = $template['graph'];

	foreach ($template['settings'] as $setting) {
		foreach ($setting['targets'] as $target) {
			foreach ($graph['nodes'] as $n => $node) {
				if ($node['id'] === $target[0]) {
					$graph['nodes'][$n]['config'][$target[1]] = ws_flow_templates_test_value($setting['type']);
				}
			}
		}
	}

	return $graph;
}

function ws_flow_templates_test_source()
{
	$function = new ReflectionFunction('ws_flow_templates');
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// Each template has the parts the gallery draws.
function test_workspace_flow_templates_shape()
{
	$templates = ws_flow_templates(ws_flow_templates_test_fill());

	pg_assert_true(count($templates) >= 8, 'the scheduled templates come over');

	$keys = array();

	foreach ($templates as $template) {
		foreach (array('key', 'category', 'title', 'help', 'icon', 'start', 'boxes', 'threads', 'settings', 'graph') as $part) {
			pg_assert_true(array_key_exists($part, $template), $template['key'] . ' has ' . $part);
		}

		pg_assert_true(isset(ws_flow_template_categories()[$template['category']]), $template['key'] . ' category');
		pg_assert_true(($template['title'] !== '') && ($template['help'] !== ''), $template['key'] . ' title and line');
		pg_assert_same(count($template['graph']['nodes']), $template['boxes'], $template['key'] . ' box count');
		pg_assert_false(isset($keys[$template['key']]), $template['key'] . ' once');
		$keys[$template['key']] = true;
	}
}

// Filled in, every graph is one the engine publishes, and its boxes are
// kinds the flow knows.
function test_workspace_flow_templates_graphs_validate()
{
	$kinds = ws_flow_kinds();

	foreach (array(0, 3) as $channel) {
		foreach (ws_flow_templates(ws_flow_templates_test_fill(array('channel' => $channel))) as $template) {
			foreach ($template['graph']['nodes'] as $node) {
				pg_assert_true(isset($kinds[$node['kind']]), $template['key'] . ': kind ' . $node['kind']);
			}

			$start = $template['graph']['nodes'][0];
			pg_assert_true($kinds[$start['kind']]['trigger'], $template['key'] . ' starts with its start box');
			pg_assert_same($start['kind'], $template['start'], $template['key'] . ' start');

			$problems = ws_flow_graph_check(ws_flow_templates_test_filled($template));
			pg_assert_same(array(), array_column($problems, 'text'), $template['key'] . ' with channel ' . $channel);

			// The graph is kept as the store keeps it.
			pg_assert_same(ws_flow_graph_normalize($template['graph']), $template['graph'], $template['key'] . ' normalized');
		}
	}
}

// A setting names a box and a field the box has.
function test_workspace_flow_templates_settings_point_at_fields()
{
	foreach (ws_flow_templates(ws_flow_templates_test_fill()) as $template) {
		foreach ($template['settings'] as $setting) {
			pg_assert_true(in_array($setting['type'], ws_flow_template_setting_types(), true), $template['key'] . ' setting type ' . $setting['type']);
			pg_assert_true(!empty($setting['targets']), $template['key'] . ' setting targets');

			foreach ($setting['targets'] as $target) {
				$found = false;

				foreach ($template['graph']['nodes'] as $node) {
					if (($node['id'] === $target[0]) && array_key_exists($target[1], $node['config'])) {
						$found = true;
					}
				}

				pg_assert_true($found, $template['key'] . ': ' . implode('.', $target));
			}
		}
	}
}

// Every text the templates carry is in the language shards.
function test_workspace_flow_templates_texts_are_translated()
{
	$map = pg_test_language_map('tr');

	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", ws_flow_templates_test_source(), $matches);
	pg_assert_true(count($matches[1]) > 10, 'texts found');

	foreach ($matches[1] as $text) {
		$text = stripslashes($text);
		pg_assert_true(isset($map[$text]), 'translated: ' . $text);
	}

	foreach (ws_flow_template_categories() as $label) {
		pg_assert_true(isset($map[$label]), 'translated: ' . $label);
	}
}

// The templates that come from the scheduled ones keep their sentences.
function test_workspace_flow_templates_keep_the_scheduled_texts()
{
	$strings = ws_scheduled_js_strings();

	foreach (ws_flow_templates(ws_flow_templates_test_fill()) as $template) {
		pg_assert_same($strings['sa_tpl_' . $template['key']] ?? null, $template['title'], $template['key'] . ' title');
	}

	$source = ws_flow_templates_test_source();

	// A follow-up's {{result}} is the box it follows in a flow.
	pg_assert_true(strpos($source, '{{node.check.text}}') !== false, 'result read from the check box');
}

// Counts the person cannot read leave out the templates built on them.
function test_workspace_flow_templates_need_their_counts()
{
	$all = array_column(ws_flow_templates(ws_flow_templates_test_fill()), 'key');
	$none = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('metrics' => array()))), 'key');

	pg_assert_true(in_array('stock', $all, true) && in_array('invoices', $all, true) && in_array('digest', $all, true), 'offered with the counts');
	pg_assert_false(in_array('stock', $none, true) || in_array('invoices', $none, true) || in_array('digest', $none, true) || in_array('overdue', $none, true), 'left out without them');
	pg_assert_true(in_array('uptime', $none, true) && in_array('monthly', $none, true), 'the rest stay');
}

// Where the texts go: the channel open when one is, else the person.
function test_workspace_flow_templates_tell_the_channel_or_the_person()
{
	$by_key = function ($fill) {
		return array_column(ws_flow_templates(ws_flow_templates_test_fill($fill)), null, 'key');
	};

	$alone = $by_key(array());
	$in = $by_key(array('channel' => 3));

	pg_assert_same('notify', $alone['ssl']['graph']['nodes'][2]['kind']);
	pg_assert_same(array(5), $alone['ssl']['graph']['nodes'][2]['config']['user_ids']);
	pg_assert_same('post', $in['ssl']['graph']['nodes'][2]['kind']);
	pg_assert_same(3, $in['ssl']['graph']['nodes'][2]['config']['channel_id']);

	// The e-mail of the site's being down only with an address to send it to.
	pg_assert_same(4, $alone['uptime']['boxes']);
	pg_assert_same(3, $by_key(array('email' => ''))['uptime']['boxes']);
	pg_assert_same(3, $alone['uptime']['threads']);

	// The first Monday after today, and the first of next month.
	pg_assert_same('2026-11-02', $alone['task_mail']['graph']['nodes'][0]['config']['date']);
	pg_assert_same('2026-11-01', $alone['monthly']['graph']['nodes'][0]['config']['date']);
	pg_assert_same('2026-10-28', $alone['morning']['graph']['nodes'][0]['config']['date']);
}

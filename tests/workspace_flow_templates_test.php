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
 * cannot read them, and the canvas is handed the list.
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

// The templates a scheduled action has no form for.
function ws_flow_templates_test_flow_only()
{
	return array('new_customer', 'quote_approval', 'text_review');
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
		if (in_array($template['key'], ws_flow_templates_test_flow_only(), true)) {
			pg_assert_false(isset($strings['sa_tpl_' . $template['key']]), $template['key'] . ' is a flow\'s own');
			continue;
		}

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

// The start dates are days of the calendar whatever PHP's own timezone: east
// of UTC "next monday" is not the Sunday before, nor a day that has begun
// east of UTC and not yet in it moved on. Without a day given it is the
// site's own today, the day the scheduler reads the start date in.
function test_workspace_flow_templates_dates_ignore_the_default_timezone()
{
	// today => next Monday, first of next month, tomorrow
	$days = array(
		'2026-10-27' => array('2026-11-02', '2026-11-01', '2026-10-28'),
		'2026-11-01' => array('2026-11-02', '2026-12-01', '2026-11-02'),
		'2026-11-02' => array('2026-11-09', '2026-12-01', '2026-11-03'),
		'2026-12-31' => array('2027-01-04', '2027-01-01', '2027-01-01'),
	);
	$zone = date_default_timezone_get();

	try {
		foreach (array('UTC', 'Asia/Kuwait', 'Pacific/Kiritimati', 'America/New_York', 'Pacific/Pago_Pago') as $name) {
			date_default_timezone_set($name);

			foreach ($days as $today => $expected) {
				$templates = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('today' => $today))), null, 'key');
				$at = $name . ', ' . $today . ': ';

				pg_assert_same($expected[0], $templates['task_mail']['graph']['nodes'][0]['config']['date'], $at . 'tasks by e-mail on the next Monday');
				pg_assert_same($expected[0], $templates['ssl']['graph']['nodes'][0]['config']['date'], $at . 'certificate check on the next Monday');
				pg_assert_same($expected[1], $templates['monthly']['graph']['nodes'][0]['config']['date'], $at . 'the first of next month');
				pg_assert_same($expected[2], $templates['morning']['graph']['nodes'][0]['config']['date'], $at . 'tomorrow');
				pg_assert_same($today, $templates['uptime']['graph']['nodes'][0]['config']['date'], $at . 'today');
			}

			$templates = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('today' => ''))), null, 'key');
			pg_assert_same(date('Y-m-d'), $templates['uptime']['graph']['nodes'][0]['config']['date'], $name . ': the site\'s today without a day given');
		}
	} finally {
		date_default_timezone_set($zone);
	}
}

// The canvas is handed the list, its texts, and draws the gallery.
function test_workspace_flow_templates_reach_the_canvas()
{
	$config = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true(strpos($config, "'templates'") !== false && strpos($config, 'ws_flow_templates_for(') !== false, 'config hands the templates');
	pg_assert_true(strpos($script, 'FL.templates') !== false, 'the script reads them');
	pg_assert_true(strpos($script, 'function showGallery(') !== false, 'the gallery');
	pg_assert_true(strpos($script, "'fl_tpl_from_template'") !== false, 'the box list offers a template');
}

// The flow-only templates are offered to everyone, whatever counts they read
// and whether a channel is open.
function test_workspace_flow_templates_flow_only_offered()
{
	foreach (array(array(), array('metrics' => array()), array('channel' => 3), array('email' => '')) as $fill) {
		$keys = array_column(ws_flow_templates(ws_flow_templates_test_fill($fill)), 'key');

		foreach (ws_flow_templates_test_flow_only() as $key) {
			pg_assert_true(in_array($key, $keys, true), $key . ' offered');
		}
	}
}

// Each arrow leaves its box on one of the outcomes its kind has, and the
// flow-only templates use what a scheduled action cannot: tasks that wait
// for each other, a wait, a timeout, an approval's outcomes.
function test_workspace_flow_templates_flow_only_use_their_boxes()
{
	$kinds = ws_flow_kinds();
	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill()), null, 'key');

	foreach (ws_flow_templates_test_flow_only() as $key) {
		$graph = $by_key[$key]['graph'];
		$node_kinds = array_column($graph['nodes'], 'kind', 'id');

		pg_assert_same('manual', $by_key[$key]['start'], $key . ' is started by hand');

		foreach ($graph['edges'] as $edge) {
			pg_assert_true(in_array($edge['on'], $kinds[$node_kinds[$edge['from']]]['outcomes'], true), $key . ': ' . $edge['from'] . ' on ' . $edge['on']);
		}

		// Filled in, the engine's own check finds nothing to stop it.
		pg_assert_same(array(), array_column(ws_flow_graph_check(ws_flow_templates_test_filled($by_key[$key])), 'text'), $key . ' publishes');
	}

	$on = function ($key) use ($by_key) {
		$out = array();

		foreach ($by_key[$key]['graph']['edges'] as $edge) {
			$out[] = $edge['from'] . ':' . $edge['on'];
		}

		return $out;
	};

	// New customer: a welcome task, a wait once it is done, a follow-up
	// task that leaves on timeout when nobody does it in time.
	$customer = array_column($by_key['new_customer']['graph']['nodes'], null, 'id');
	pg_assert_same('task', $customer['welcome']['kind']);
	pg_assert_same('delay', $customer['wait']['kind']);
	pg_assert_same('task', $customer['follow']['kind']);
	pg_assert_true($customer['follow']['config']['timeout'] !== '', 'the follow-up has a timeout');
	pg_assert_same(array('start:next', 'welcome:done', 'wait:done', 'follow:timeout'), $on('new_customer'));

	// Quote approval: an approval with a branch for each way it ends.
	$quote = array_column($by_key['quote_approval']['graph']['nodes'], null, 'id');
	pg_assert_same('approval', $quote['ask']['kind']);
	pg_assert_true($quote['ask']['config']['closes_in'] !== '', 'the approval has a deadline');
	pg_assert_same(array('start:next', 'ask:approved', 'ask:rejected', 'ask:expired', 'ask:withdrawn'), $on('quote_approval'));

	// Text review: a task, an approval waiting on it, a task after it.
	$review = array_column($by_key['text_review']['graph']['nodes'], null, 'id');
	pg_assert_same(array('manual', 'task', 'approval', 'task_open', 'notify'), array_values(array_column($review, 'kind')));
	pg_assert_true($by_key['text_review']['threads'] > 0, 'the approval reads the task');

	// The approval needs a channel: the person is asked for one.
	foreach (array('quote_approval', 'text_review') as $key) {
		pg_assert_true(in_array('channel', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for a channel');
		pg_assert_true(in_array('person', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for people');
	}
}

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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
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
		'join'     => true,
		'events'   => array('order.created', 'form.submitted', 'stock.low', 'workspace.task.overdue', 'workspace.approval.decided',
			'order.delivered', 'order.return.requested', 'booking.cancelled', 'order.cancelled', 'booking.confirmed', 'workspace.flow.run.failed',
			'erp.invoice.overdue', 'product.review.created', 'subscription.failed'),
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

// A follow-up's {{result}} of a scheduled action is the box it follows in a
// flow.
function test_workspace_flow_templates_read_the_result_from_the_check_box()
{
	pg_assert_true(strpos(ws_flow_templates_test_source(), '{{node.check.text}}') !== false, 'result read from the check box');
}

// Counts the person cannot read leave out the templates built on them.
function test_workspace_flow_templates_need_their_counts()
{
	$all = array_column(ws_flow_templates(ws_flow_templates_test_fill()), 'key');
	$none = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('metrics' => array()))), 'key');

	foreach (array('stock', 'invoices', 'digest', 'overdue', 'forms', 'orders') as $key) {
		pg_assert_true(in_array($key, $all, true), $key . ' offered with the counts');
		pg_assert_false(in_array($key, $none, true), $key . ' left out without them');
	}

	pg_assert_true(in_array('uptime', $none, true) && in_array('monthly', $none, true) && in_array('chain', $none, true), 'the rest stay');

	// The orders' text reads the day's sales too.
	$no_sales = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('metrics' => array('orders_new')))), 'key');
	pg_assert_false(in_array('orders', $no_sales, true), 'orders need the day\'s sales');
}

// The welcome needs the join start and each event starter its event; the
// welcome posts in the channel joined, any public one.
function test_workspace_flow_templates_need_their_starts()
{
	$keys = function ($fill) {
		return array_column(ws_flow_templates(ws_flow_templates_test_fill($fill)), 'key');
	};
	$started = array('welcome', 'order_event', 'form_event', 'stock_event', 'overdue_event', 'approval_event',
		'review_request', 'restock_approval', 'return_task', 'booking_cancelled', 'order_cancelled', 'booking_reminder', 'failed_runs',
		'invoice_overdue_follow_up');

	pg_assert_same(array(), array_values(array_diff($started, $keys(array()))), 'offered with the join start and the events');
	pg_assert_same(array(), array_values(array_intersect($started, $keys(array('join' => false, 'events' => array())))), 'left out without them');
	pg_assert_same(array('order_event'), array_values(array_intersect($started, $keys(array('join' => false, 'events' => array('order.created'))))), 'one event, one starter');

	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => 3))), null, 'key');

	pg_assert_same(array('channel_id' => 0), $by_key['welcome']['graph']['nodes'][0]['config'], 'any public channel');
	pg_assert_same(-1, $by_key['welcome']['graph']['nodes'][1]['config']['channel_id'], 'the channel joined');
	pg_assert_same('event', $by_key['approval_event']['start']);
	pg_assert_same(array('event' => 'workspace.approval.decided', 'page_id' => 0, 'status' => 'approved', 'channel_id' => 3),
		$by_key['approval_event']['graph']['nodes'][0]['config'], 'approved requests of the channel open');
	pg_assert_same('task_open', $by_key['stock_event']['graph']['nodes'][1]['kind']);
	pg_assert_same('post', $by_key['order_event']['graph']['nodes'][1]['kind'], 'to the channel open');
}

// The event templates that wait, ask or follow up: each arrow on an outcome
// its box has, the engine's check finds nothing to stop them, and each asks
// for the people (and the approval for its channel).
function test_workspace_flow_templates_event_follow_ups()
{
	$kinds = ws_flow_kinds();
	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => 3))), null, 'key');
	$on = function ($key) use ($by_key) {
		return array_map(function ($edge) {
			return $edge['from'] . ':' . $edge['on'];
		}, $by_key[$key]['graph']['edges']);
	};

	$events = array('review_request' => 'order.delivered', 'restock_approval' => 'stock.low', 'return_task' => 'order.return.requested',
		'booking_cancelled' => 'booking.cancelled', 'order_cancelled' => 'order.cancelled');

	foreach ($events as $key => $event) {
		$graph = $by_key[$key]['graph'];
		$node_kinds = array_column($graph['nodes'], 'kind', 'id');

		pg_assert_same('event', $by_key[$key]['start'], $key . ' starts on an event');
		pg_assert_same($event, $graph['nodes'][0]['config']['event'], $key . ' event');
		pg_assert_same('sales', $by_key[$key]['category'], $key . ' category');

		foreach ($graph['edges'] as $edge) {
			pg_assert_true(in_array($edge['on'], $kinds[$node_kinds[$edge['from']]]['outcomes'], true), $key . ': ' . $edge['from'] . ' on ' . $edge['on']);
		}

		pg_assert_same(array(), array_column(ws_flow_graph_check(ws_flow_templates_test_filled($by_key[$key])), 'text'), $key . ' publishes');
		pg_assert_true(in_array('person', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for people');
	}

	// Review request: a week's wait, the longest a delay box takes, then
	// a task about the order.
	$review = array_column($by_key['review_request']['graph']['nodes'], null, 'id');
	pg_assert_same(array('start:next', 'wait:done'), $on('review_request'));
	pg_assert_same(array('amount' => 7, 'unit' => 'days'), array_intersect_key($review['wait']['config'], array('amount' => 0, 'unit' => 0)));
	pg_assert_true(7 * 1440 <= WS_FLOW_DELAY_MAX, 'a week fits the delay box');
	pg_assert_same('task_open', $review['ask']['kind']);
	pg_assert_true(strpos($review['ask']['config']['description'], '{{record_link}}') !== false, 'the task links the order');

	// Restock: nothing is ordered before somebody approves it in a channel;
	// rejected or left undecided, the person is told.
	$restock = array_column($by_key['restock_approval']['graph']['nodes'], null, 'id');
	pg_assert_same('approval', $restock['ask']['kind']);
	pg_assert_same(3, $restock['ask']['config']['channel_id'], 'asked in the channel open');
	pg_assert_true($restock['ask']['config']['closes_in'] !== '', 'the approval has a deadline');
	pg_assert_same(array('start:next', 'ask:approved', 'ask:rejected', 'ask:expired'), $on('restock_approval'));
	pg_assert_same('task_open', $restock['order']['kind']);
	pg_assert_true(in_array('channel', array_column($by_key['restock_approval']['settings'], 'type'), true), 'asks for a channel');

	// Return: a task that waits two days and a line at once; the person is
	// told when the task is still open.
	$return = array_column($by_key['return_task']['graph']['nodes'], null, 'id');
	pg_assert_same(array('start:next', 'start:next', 'handle:timeout'), $on('return_task'));
	pg_assert_same('task', $return['handle']['kind']);
	pg_assert_same(48, $return['handle']['config']['timeout']);
	pg_assert_same('post', $return['tell']['kind'], 'the line goes to the channel open');
	pg_assert_same('notify', $return['late']['kind']);

	// A cancelled booking or order: a line and a task at once, both on the
	// start's one outcome.
	foreach (array('booking_cancelled' => 'offer', 'order_cancelled' => 'ask') as $key => $task) {
		$nodes = array_column($by_key[$key]['graph']['nodes'], null, 'id');
		pg_assert_same(array('start:next', 'start:next'), $on($key), $key . ' arrows');
		pg_assert_same('post', $nodes['tell']['kind'], $key . ' line in the channel open');
		pg_assert_same('task_open', $nodes[$task]['kind'], $key . ' task');
		pg_assert_true(strpos($nodes[$task]['config']['description'], '{{record_link}}') !== false, $key . ' links the record');
	}

	// Without a channel open, the line goes to the person.
	$alone = array_column(ws_flow_templates(ws_flow_templates_test_fill()), null, 'key');

	foreach (array('return_task', 'booking_cancelled', 'order_cancelled') as $key) {
		pg_assert_same('notify', array_column($alone[$key]['graph']['nodes'], null, 'id')['tell']['kind'], $key . ' alone');
	}
}

// A booking reminder, failed runs to the team and an overdue invoice: each
// arrow on an outcome of its box, and what each must hold.
function test_workspace_flow_templates_reminder_failed_runs_overdue()
{
	$kinds = ws_flow_kinds();
	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => 3))), null, 'key');
	$on = function ($key) use ($by_key) {
		return array_map(function ($edge) {
			return $edge['from'] . ':' . $edge['on'];
		}, $by_key[$key]['graph']['edges']);
	};
	$events = array('booking_reminder' => 'booking.confirmed', 'failed_runs' => 'workspace.flow.run.failed',
		'invoice_overdue_follow_up' => 'erp.invoice.overdue');

	foreach ($events as $key => $event) {
		$graph = $by_key[$key]['graph'];
		$node_kinds = array_column($graph['nodes'], 'kind', 'id');

		pg_assert_same($event, $graph['nodes'][0]['config']['event'], $key . ' event');

		foreach ($graph['edges'] as $edge) {
			pg_assert_true(in_array($edge['on'], $kinds[$node_kinds[$edge['from']]]['outcomes'], true), $key . ': ' . $edge['from'] . ' on ' . $edge['on']);
		}

		pg_assert_same(array(), array_column(ws_flow_graph_check(ws_flow_templates_test_filled($by_key[$key])), 'text'), $key . ' publishes');
	}

	// Booking: read, wait until a day before its start, read again, and
	// only a booking still confirmed tells the host and mails the guest.
	$booking = array_column($by_key['booking_reminder']['graph']['nodes'], null, 'id');
	pg_assert_same(array('start:next', 'read:done', 'wait:done', 'again:done', 'still:yes', 'still:yes'), $on('booking_reminder'));
	pg_assert_same(array('record' => 'booking', 'id' => '{{trigger.record_id}}'), array_intersect_key($booking['again']['config'], array('record' => 0, 'id' => 0)));
	pg_assert_same(array('mode' => 'until', 'at' => '{{node.read.start}}', 'amount' => 1, 'unit' => 'days'),
		array_intersect_key($booking['wait']['config'], array('mode' => 0, 'at' => 0, 'amount' => 0, 'unit' => 0)));
	pg_assert_same('{{node.again.status}}', $booking['still']['config']['rule']['ref']);
	pg_assert_same('confirmed', $booking['still']['config']['rule']['value']);
	pg_assert_same('notify', $booking['host']['kind']);
	pg_assert_same('email', $booking['guest']['kind']);
	pg_assert_same(array('record_person', 'info'), array($booking['guest']['config']['recipient'], $booking['guest']['config']['purpose']), 'the guest, never typed, no marketing');
	pg_assert_same(array('person'), array_column($by_key['booking_reminder']['settings'], 'type'));

	// Failed runs: the start narrowed to the channel the line goes to, one
	// setting for both.
	$failed = array_column($by_key['failed_runs']['graph']['nodes'], null, 'id');
	pg_assert_same('team', $by_key['failed_runs']['category']);
	pg_assert_same(3, $failed['start']['config']['channel_id'], 'runs of the channel open');
	pg_assert_same('post', $failed['tell']['kind']);
	pg_assert_same(3, $failed['tell']['config']['channel_id']);
	pg_assert_true(strpos($failed['tell']['config']['body'], '{{message}}') !== false, 'the box and its sentence');
	pg_assert_same(array(array('type' => 'channel', 'targets' => array(array('start', 'channel_id'), array('tell', 'channel_id')))),
		$by_key['failed_runs']['settings']);
	$alone = array_column(ws_flow_templates(ws_flow_templates_test_fill()), null, 'key');
	pg_assert_same('post', $alone['failed_runs']['graph']['nodes'][1]['kind'], 'a channel is asked, never the person alone');

	// Overdue invoice: a task that waits a week, then the person is told.
	$invoice = array_column($by_key['invoice_overdue_follow_up']['graph']['nodes'], null, 'id');
	pg_assert_same(array('start:next', 'collect:timeout'), $on('invoice_overdue_follow_up'));
	pg_assert_same('task', $invoice['collect']['kind']);
	pg_assert_same(168, $invoice['collect']['config']['timeout']);
	pg_assert_true(168 <= WS_FLOW_TIMEOUT_MAX, 'a week fits the task box');
	pg_assert_true(strpos($invoice['collect']['config']['description'], '{{record_link}}') !== false, 'the task links the invoice');
	pg_assert_same('notify', $invoice['late']['kind']);
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

// Forms and orders as a flow counts them: once a day, the last day, and
// their texts say so; told only when there is something to count.
function test_workspace_flow_templates_count_the_last_day()
{
	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill()), null, 'key');

	foreach (array('forms' => 'forms_new', 'orders' => 'orders_new') as $key => $metric) {
		$nodes = array_column($by_key[$key]['graph']['nodes'], null, 'id');

		pg_assert_same('schedule', $nodes['start']['kind'], $key . ' on a schedule');
		pg_assert_same('daily', $nodes['start']['config']['repeat'], $key . ' once a day');
		pg_assert_same(array('type' => 'metric', 'metric' => $metric, 'param' => 0, 'op' => 'gt', 'value' => '0'), $nodes['any']['config']['rule'], $key . ' counts');
		pg_assert_same(array('start:any:next', 'any:tell:yes'), array_map(function ($edge) {
			return $edge['from'] . ':' . $edge['to'] . ':' . $edge['on'];
		}, $by_key[$key]['graph']['edges']), $key . ' arrows');
		pg_assert_true(in_array('time', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for the time');
	}

	$forms = array_column($by_key['forms']['graph']['nodes'], null, 'id');
	pg_assert_same('notify', $forms['tell']['kind'], 'forms tell the person');
	pg_assert_true(strpos($forms['tell']['config']['text'], 'in the last day') !== false, 'forms say the last day');

	$orders = array_column($by_key['orders']['graph']['nodes'], null, 'id');
	pg_assert_same('webhook', $orders['tell']['kind'], 'orders to a chat service');
	pg_assert_same('', $orders['tell']['config']['url'], 'the address is asked');
	pg_assert_true(in_array('url', array_column($by_key['orders']['settings'], 'type'), true), 'orders ask for the address');
	pg_assert_true(strpos($orders['tell']['config']['text'], 'in the last day') !== false, 'orders say the last day');

	// In a channel the forms are written there.
	$in = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => 3))), null, 'key');
	pg_assert_same('post', $in['forms']['graph']['nodes'][2]['kind']);

	// No "since the last run" sentence is left in the templates.
	$source = ws_flow_templates_test_source();
	pg_assert_false(strpos($source, 'since the last look') !== false || strpos($source, 'in the last hour') !== false, 'no count since the last run');
}

// The chain step is a flow started by hand, which a "Start a flow" box of
// another flow starts: it says which flow finished and how.
function test_workspace_flow_templates_chain_is_a_manual_start()
{
	foreach (array(array(), array('metrics' => array(), 'events' => array(), 'join' => false)) as $fill) {
		pg_assert_true(in_array('chain', array_column(ws_flow_templates(ws_flow_templates_test_fill($fill)), 'key'), true), 'chain offered');
	}

	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill()), null, 'key');
	$chain = $by_key['chain'];

	pg_assert_same('manual', $chain['start'], 'started by hand');
	pg_assert_same(2, $chain['boxes']);
	pg_assert_same(lang('{{source}} finished: {{source_result}}'), $chain['graph']['nodes'][1]['config']['text'], 'says which flow finished');
	pg_assert_true(strpos($chain['help'], 'Start a flow') !== false, 'the line names the box that starts it');
}

/* ---------------------------------------------------------------------------
   Templates saved on the site (flow_templates_saved.php)
   --------------------------------------------------------------------------- */

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates_saved.php');

// A draft as a person built it: a time, a channel, people, an address, a
// site checked and a chat hook whose address holds its secret.
function ws_flow_templates_test_draft()
{
	return ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'schedule', 'x' => 0, 'y' => 0, 'config' => array('date' => '2026-11-02', 'time' => '07:45', 'repeat' => 'daily')),
		array('id' => 'check', 'kind' => 'web_check', 'x' => 256, 'y' => 0, 'config' => array('url' => 'https://ops:hunter2@shop.example.com/health', 'mode' => 'status')),
		array('id' => 'hook', 'kind' => 'webhook', 'x' => 512, 'y' => 0, 'config' => array('url' => 'https://hooks.slack.com/services/T0/B0/secret', 'text' => 'Down: {{node.check.text}}')),
		array('id' => 'say', 'kind' => 'post', 'x' => 512, 'y' => 112, 'config' => array('channel_id' => 12, 'body' => 'Down: {{node.check.text}}')),
		array('id' => 'tell', 'kind' => 'notify', 'x' => 512, 'y' => 224, 'config' => array('user_ids' => array(4, 8), 'text' => 'Down')),
		array('id' => 'other', 'kind' => 'notify', 'x' => 768, 'y' => 224, 'config' => array('user_ids' => array(3), 'text' => 'Also')),
		array('id' => 'mail', 'kind' => 'email', 'x' => 512, 'y' => 336, 'config' => array('to' => 'ops@example.com', 'subject' => 'Down', 'body' => 'Down')),
	), 'edges' => array(
		array('from' => 'start', 'to' => 'check', 'on' => 'next'),
		array('from' => 'check', 'to' => 'hook', 'on' => 'failed'),
		array('from' => 'check', 'to' => 'say', 'on' => 'failed'),
		array('from' => 'check', 'to' => 'tell', 'on' => 'failed'),
		array('from' => 'tell', 'to' => 'other', 'on' => 'done'),
		array('from' => 'check', 'to' => 'mail', 'on' => 'failed'),
	), 'notes' => array()));
}

// Secrets in box settings are never saved: a hook's address is dropped, a
// password in an address is taken out; threads stay.
function test_workspace_flow_templates_saved_drop_secrets()
{
	$graph = ws_flow_template_secrets_dropped(ws_flow_templates_test_draft());
	$by_id = array_column($graph['nodes'], null, 'id');

	pg_assert_same('', $by_id['hook']['config']['url'], 'the hook address is dropped');
	pg_assert_same('https://shop.example.com/health', $by_id['check']['config']['url'], 'no user and password in an address');
	pg_assert_same('Down: {{node.check.text}}', $by_id['hook']['config']['text'], 'threads are kept');
	pg_assert_same(2, count(ws_flow_template_threads($graph)), 'two boxes read the check');
	pg_assert_false(strpos(json_encode($graph), 'hunter2') !== false || strpos(json_encode($graph), 'secret') !== false, 'no secret anywhere');
}

// The values in the boxes become the settings Use asks, one per type, the
// boxes holding the first box's value as its targets.
function test_workspace_flow_templates_saved_settings_extracted()
{
	$graph = ws_flow_template_secrets_dropped(ws_flow_templates_test_draft());
	$settings = array_column(ws_flow_template_settings_of($graph), 'targets', 'type');

	pg_assert_same(array('time', 'url', 'channel', 'person', 'address'), array_keys($settings), 'one setting per type, in box order');
	pg_assert_same(array(array('start', 'time')), $settings['time']);
	pg_assert_same(array(array('check', 'url')), $settings['url'], 'the hook keeps its own (empty) address');
	pg_assert_same(array(array('say', 'channel_id')), $settings['channel']);
	pg_assert_same(array(array('tell', 'user_ids')), $settings['person'], 'a box with other people keeps them');
	pg_assert_same(array(array('mail', 'to')), $settings['address']);

	// Values the setup cannot give are no setting: any channel, the channel
	// joined, nobody, a value read from an earlier box.
	$empty = ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'join', 'config' => array('channel_id' => 0)),
		array('id' => 'say', 'kind' => 'post', 'config' => array('channel_id' => -1, 'body' => 'Hi')),
		array('id' => 'tell', 'kind' => 'notify', 'config' => array('user_ids' => array(), 'text' => 'x')),
		array('id' => 'mail', 'kind' => 'email', 'config' => array('to' => '{{trigger.email}}', 'subject' => 'x', 'body' => 'x')),
	), 'edges' => array(), 'notes' => array()));
	pg_assert_same(array(), ws_flow_template_settings_of($empty), 'nothing to ask');

	// A hook's dropped address is asked when it is the first address.
	$hook = ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'manual', 'config' => array()),
		array('id' => 'hook', 'kind' => 'webhook', 'config' => array('url' => 'https://hooks.example.com/abc', 'text' => 'x')),
	), 'edges' => array(array('from' => 'start', 'to' => 'hook', 'on' => 'next')), 'notes' => array()));
	$asked = ws_flow_template_settings_of(ws_flow_template_secrets_dropped($hook));
	pg_assert_same(array(array('type' => 'url', 'targets' => array(array('hook', 'url')))), $asked, 'the hook address is asked');
}

// A saved row read back as a gallery entry: its key, the saved category,
// counts and motion computed, settings kept only where they still point.
function test_workspace_flow_templates_saved_entry()
{
	$graph = ws_flow_template_secrets_dropped(ws_flow_templates_test_draft());
	$settings = ws_flow_template_settings_of($graph);
	$settings[] = array('type' => 'person', 'targets' => array(array('gone', 'user_ids')));
	$settings[] = array('type' => 'colour', 'targets' => array(array('say', 'body')));

	$entry = ws_flow_template_saved_entry(array('id' => 42, 'template_key' => 'site_42', 'category' => 'site', 'title' => 'Site down', 'help' => 'Tells us',
		'icon' => '', 'graph' => json_encode($graph), 'settings' => json_encode($settings), 'source_flow_id' => 3, 'created_by' => 5, 'updated_at' => 100),
		array('id' => 9, 'role' => 3, 'member' => true));

	pg_assert_same('site_42', $entry['key']);
	pg_assert_same('saved', $entry['category'], 'listed under saved on this site');
	pg_assert_same('site', $entry['saved']['category'], 'its own category kept');
	pg_assert_same('schedule', $entry['start']);
	pg_assert_same(7, $entry['boxes']);
	pg_assert_same(2, $entry['threads']);
	pg_assert_same(7, count($entry['motion']['boxes']));
	pg_assert_same(5, count($entry['settings']), 'unknown types and gone boxes left out');
	pg_assert_false($entry['saved']['edit'], 'somebody else may not change it');
	pg_assert_true(isset(ws_flow_template_categories()['saved']), 'the gallery has the category');

	// The start is the start box's kind, wherever the box sits in the list.
	$later = $graph;
	$later['nodes'] = array_merge(array_slice($graph['nodes'], 1), array($graph['nodes'][0]));
	$read = function ($graph) {
		return ws_flow_template_saved_entry(array('id' => 43, 'template_key' => 'site_43', 'category' => 'site', 'title' => 'Later', 'help' => '',
			'icon' => '', 'graph' => json_encode($graph), 'settings' => '[]', 'source_flow_id' => 3, 'created_by' => 9, 'updated_at' => 100),
			array('id' => 9, 'role' => 3, 'member' => true));
	};
	pg_assert_same('web_check', $later['nodes'][0]['kind'], 'the first box is not the start');
	pg_assert_same('schedule', $read($later)['start'], 'a start box placed later is still the start');
	$none = $graph;
	$none['nodes'] = array_slice($graph['nodes'], 1);
	pg_assert_same('', $read($none)['start'], 'no start box: no start');

	// A built-in key is never a saved one.
	pg_assert_same('site_42', ws_flow_template_saved_key(42));
	$builtin = array_column(ws_flow_templates(ws_flow_templates_test_fill()), 'key');
	pg_assert_same(array(), array_values(array_filter($builtin, function ($key) {
		return strpos($key, 'site_') === 0;
	})), 'no built-in key starts with site_');
	pg_assert_same(42, ws_flow_template_saved_id('site_42'));
	pg_assert_same(0, ws_flow_template_saved_id('uptime'));
	pg_assert_same(0, ws_flow_template_saved_id('site_x'));
}

// Its saver or a Manager and above changes and bins it; Designer (1) is above
// Manager (2), a user (3) only their own.
function test_workspace_flow_templates_saved_rights()
{
	$row = array('created_by' => 5);

	pg_assert_true(ws_flow_template_may_change(array('id' => 5, 'role' => 3, 'member' => true), $row), 'the saver');
	pg_assert_true(ws_flow_template_may_change(array('id' => 6, 'role' => 2, 'member' => true), $row), 'a manager');
	pg_assert_true(ws_flow_template_may_change(array('id' => 6, 'role' => 1, 'member' => true), $row), 'a designer');
	pg_assert_true(ws_flow_template_may_change(array('id' => 6, 'role' => 0, 'member' => true), $row), 'an administrator');
	pg_assert_false(ws_flow_template_may_change(array('id' => 6, 'role' => 3, 'member' => true), $row), 'another user');
	pg_assert_false(ws_flow_template_may_change(array('id' => 5, 'role' => 2, 'member' => false), $row), 'a site member');
	pg_assert_false(ws_flow_template_may_change(array('id' => 0, 'role' => 3, 'member' => true), array('created_by' => 0)), 'nobody saved it');
}

// Title, help and category as written, checked.
function test_workspace_flow_templates_saved_fields()
{
	$ok = ws_flow_template_fields_check(array('title' => "  Site\tdown ", 'help' => 'Tells us', 'category' => 'sales'));
	pg_assert_true($ok['ok']);
	pg_assert_same(array('title' => 'Site down', 'help' => 'Tells us', 'category' => 'sales'), $ok['sets']);

	pg_assert_same('title', ws_flow_template_fields_check(array('title' => '  ', 'category' => 'team'))['field'], 'a title is needed');
	pg_assert_same('category', ws_flow_template_fields_check(array('title' => 'x', 'category' => 'saved'))['field'], 'only the built-ins\' categories');
	pg_assert_same(160, mb_strlen(ws_flow_template_fields_check(array('title' => str_repeat('ş', 300)))['sets']['title']), 'cut by characters');
	pg_assert_same(array('help' => 'New'), ws_flow_template_fields_check(array('help' => 'New'), true)['sets'], 'an update sets only what came');
}

// A row box's table: a flow started by a row, a row added to the same table
// and a row changed in another.
function ws_flow_templates_test_rows()
{
	return ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'row', 'x' => 0, 'y' => 0, 'config' => array('table_id' => 7, 'what' => 'updated', 'column_id' => 31)),
		array('id' => 'add', 'kind' => 'row_add', 'x' => 256, 'y' => 0, 'config' => array('table_id' => 7, 'cells' => '{"31":"x"}')),
		array('id' => 'set', 'kind' => 'row_set', 'x' => 512, 'y' => 0, 'config' => array('table_id' => 9, 'row' => '12', 'cells' => '{"40":1}')),
		array('id' => 'set2', 'kind' => 'row_set', 'x' => 768, 'y' => 0, 'config' => array('table_id' => 7, 'row' => '{{node.add.row_id}}', 'cells' => '{"31":"y"}')),
	), 'edges' => array(
		array('from' => 'start', 'to' => 'add', 'on' => 'next'),
		array('from' => 'add', 'to' => 'set', 'on' => 'done'),
		array('from' => 'set', 'to' => 'set2', 'on' => 'done'),
	), 'notes' => array()));
}

// A saved template asks the table of its row boxes: the boxes on the first
// box's table are its targets, a box on another table keeps it, no table is
// no setting.
function test_workspace_flow_templates_saved_table_setting()
{
	pg_assert_true(in_array('table', ws_flow_template_setting_types(), true), 'a table is a setting type');

	$settings = array_column(ws_flow_template_settings_of(ws_flow_templates_test_rows()), 'targets', 'type');

	pg_assert_same(array(array('start', 'table_id'), array('add', 'table_id'), array('set2', 'table_id')), $settings['table'] ?? null, 'the boxes on the first table');

	$none = ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'manual', 'config' => array()),
		array('id' => 'add', 'kind' => 'row_add', 'config' => array('table_id' => 0, 'cells' => '')),
	), 'edges' => array(array('from' => 'start', 'to' => 'add', 'on' => 'next')), 'notes' => array()));
	pg_assert_same(array(), ws_flow_template_settings_of($none), 'no table chosen, nothing asked');

	$answer = array('settings' => array(array('type' => 'table', 'targets' => array(array('add', 'table_id')))));
	pg_assert_same('table', ws_flow_template_answers_check($answer, array('table' => 0))['field'], 'a table is needed');
	pg_assert_same(array('table' => 5), ws_flow_template_answers_check($answer, array('table' => '5'))['values']);
}

// The table chosen is written into its boxes; a box whose table changed
// loses what belonged to the old one (cells, the column a change writes, a
// row named by its number), a row read from a box before stays.
function test_workspace_flow_templates_saved_table_applied()
{
	$graph = ws_flow_templates_test_rows();
	$template = array('graph' => $graph, 'settings' => ws_flow_template_settings_of($graph));
	$by_id = array_column(ws_flow_template_apply($template, $graph, array('table' => 8))['nodes'], 'config', 'id');

	pg_assert_same(8, $by_id['start']['table_id']);
	pg_assert_same(0, $by_id['start']['column_id'], 'the column was the old table\'s');
	pg_assert_same('updated', $by_id['start']['what'], 'what happens stays');
	pg_assert_same(8, $by_id['add']['table_id']);
	pg_assert_same('', $by_id['add']['cells'], 'the cells were the old table\'s');
	pg_assert_same('{{node.add.row_id}}', $by_id['set2']['row'], 'a row read from a box stays');
	pg_assert_same('', $by_id['set2']['cells']);
	pg_assert_same(9, $by_id['set']['table_id'], 'a box on its own table keeps it');
	pg_assert_same('{"40":1}', $by_id['set']['cells']);
	pg_assert_same('12', $by_id['set']['row']);

	$same = array_column(ws_flow_template_apply($template, $graph, array('table' => 7))['nodes'], 'config', 'id');
	pg_assert_same(31, $same['start']['column_id'], 'the same table keeps its column');
	pg_assert_same('{"31":"x"}', $same['add']['cells'], 'the same table keeps its cells');

	// A literal row of a row change goes with its table.
	$set = ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'manual', 'config' => array()),
		array('id' => 'set', 'kind' => 'row_set', 'config' => array('table_id' => 9, 'row' => '12', 'cells' => '{"40":1}')),
	), 'edges' => array(array('from' => 'start', 'to' => 'set', 'on' => 'next')), 'notes' => array()));
	$one = array('graph' => $set, 'settings' => ws_flow_template_settings_of($set));
	$changed = array_column(ws_flow_template_apply($one, $set, array('table' => 8))['nodes'], 'config', 'id');
	pg_assert_same('', $changed['set']['row'], 'a row number was the old table\'s');
}

// The table chosen must be one the person reaches as far as its boxes need:
// read for a row start, change for a box that adds or changes rows.
function test_workspace_flow_templates_saved_table_rights()
{
	$graph = ws_flow_templates_test_rows();
	$writes = array('graph' => $graph, 'settings' => ws_flow_template_settings_of($graph));
	$start = array('graph' => $graph, 'settings' => array(array('type' => 'table', 'targets' => array(array('start', 'table_id')))));

	pg_assert_same('edit', ws_flow_template_table_need($writes), 'a row written needs change');
	pg_assert_same('view', ws_flow_template_table_need($start), 'a row start needs read');
	pg_assert_same('', ws_flow_template_table_need(array('graph' => $graph, 'settings' => array())), 'no table asked');

	pg_assert_true(ws_flow_template_table_refusal($writes, '') !== '', 'a table out of reach');
	pg_assert_true(ws_flow_template_table_refusal($writes, 'view') !== '', 'read is not enough to write');
	pg_assert_same('', ws_flow_template_table_refusal($writes, 'edit'));
	pg_assert_same('', ws_flow_template_table_refusal($writes, 'owner'));
	pg_assert_same('', ws_flow_template_table_refusal($start, 'view'));
	pg_assert_true(ws_flow_template_table_refusal($start, '') !== '');
}

// A value with a fallback is a thread of a template as one without is.
function test_workspace_flow_template_threads_with_a_fallback()
{
	$graph = array('nodes' => array(
		array('id' => 'check', 'kind' => 'web_check', 'config' => array('url' => 'https://example.com')),
		array('id' => 'hook', 'kind' => 'webhook', 'config' => array('text' => 'Down: {{node.check.text|no answer \\| try later}}')),
	));

	pg_assert_same(array(array('from' => 'check', 'to' => 'hook', 'key' => 'text')), ws_flow_template_threads($graph));
}

// Goods received and the daily cash count: an approval that asks a number
// with the approval, and what the flow does with the answer.
function test_workspace_flow_templates_answer_a_number()
{
	$kinds = ws_flow_kinds();

	foreach (array(0, 3) as $channel) {
		$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => $channel))), null, 'key');

		foreach (array('goods_received', 'cash_count') as $key) {
			pg_assert_true(isset($by_key[$key]), $key . ' offered');

			$nodes = array_column($by_key[$key]['graph']['nodes'], null, 'id');
			$ask = ws_step_config_normalize('approval', $nodes['ask']['config']);

			pg_assert_same('number', $ask['answer'], $key . ' asks a number');
			pg_assert_true($ask['answer_label'] !== '', $key . ' asks a question');
			pg_assert_true(in_array('channel', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for a channel');
			pg_assert_true(in_array('person', array_column($by_key[$key]['settings'], 'type'), true), $key . ' asks for people');
			pg_assert_true($by_key[$key]['threads'] > 0, $key . ' reads the answer');

			foreach ($by_key[$key]['graph']['edges'] as $edge) {
				pg_assert_true(in_array($edge['on'], $kinds[$nodes[$edge['from']]['kind']]['outcomes'], true), $key . ': ' . $edge['from'] . ' on ' . $edge['on']);
			}

			pg_assert_same(array(), array_column(ws_flow_graph_check(ws_flow_templates_test_filled($by_key[$key])), 'text'), $key . ' publishes');
		}

		// Goods received: started by hand when a delivery is due; the number
		// that arrived is told, a delivery not there is told too.
		$goods = array_column($by_key['goods_received']['graph']['nodes'], null, 'id');
		pg_assert_same('manual', $by_key['goods_received']['start']);
		pg_assert_true(strpos(json_encode($goods['got']['config']), '{{node.ask.answer_number}}') !== false, 'tells how many arrived');

		// Cash count: each evening; the difference from the expected amount
		// worked out by Calculate, told only when it is not zero.
		$cash = array_column($by_key['cash_count']['graph']['nodes'], null, 'id');
		pg_assert_same('schedule', $by_key['cash_count']['start']);
		pg_assert_same('daily', $cash['start']['config']['repeat']);
		pg_assert_same('constants', $cash['expected']['kind']);
		pg_assert_same('calculate', $cash['diff']['config']['rule']);
		pg_assert_same('{{node.ask.answer_number}} - {{node.expected.expected}}', $cash['diff']['config']['a']);
		pg_assert_same(array('type' => 'value', 'ref' => '{{node.diff.value}}', 'op' => 'neq', 'value' => '0'),
			array_intersect_key($cash['check']['config']['rule'], array('type' => 0, 'ref' => 0, 'op' => 0, 'value' => 0)));
		pg_assert_same('notify', $cash['off']['kind'], 'the manager is told');
	}

	// The difference as the run works it out: 1250 counted, 1200 expected.
	pg_assert_same('50', ws_flow_derived_calculate('{{node.ask.answer_number}} - {{node.expected.expected}}',
		array('ask' => array('answer_number' => '1250'), 'expected' => array('expected' => '1200')), 2));
}

// A low review and a failed subscription payment: offered with their
// events, a review read by its rating, the person told and a task.
function test_workspace_flow_templates_low_review_and_failed_subscription()
{
	$kinds = ws_flow_kinds();
	$by_key = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('channel' => 3))), null, 'key');
	$events = array('low_review' => 'product.review.created', 'subscription_failed' => 'subscription.failed');

	foreach ($events as $key => $event) {
		$graph = $by_key[$key]['graph'];
		$nodes = array_column($graph['nodes'], null, 'id');

		pg_assert_same($event, $graph['nodes'][0]['config']['event'], $key . ' event');

		foreach ($graph['edges'] as $edge) {
			pg_assert_true(in_array($edge['on'], $kinds[$nodes[$edge['from']]['kind']]['outcomes'], true), $key . ': ' . $edge['from'] . ' on ' . $edge['on']);
		}

		pg_assert_same(array(), array_column(ws_flow_graph_check(ws_flow_templates_test_filled($by_key[$key])), 'text'), $key . ' publishes');

		$left = array_column(ws_flow_templates(ws_flow_templates_test_fill(array('events' => array_values(array_diff(ws_flow_templates_test_fill()['events'], array($event)))))), null, 'key');
		pg_assert_false(isset($left[$key]), $key . ' needs its event');
	}

	$review = array_column($by_key['low_review']['graph']['nodes'], null, 'id');
	pg_assert_same(array('type' => 'value', 'ref' => '{{trigger.rating}}', 'op' => 'lte', 'value' => '2'),
		array_intersect_key($review['low']['config']['rule'], array('type' => 0, 'ref' => 0, 'op' => 0, 'value' => 0)));
	pg_assert_same('task_open', $review['answer']['kind']);
	pg_assert_same('post', $review['tell']['kind']);

	// Two stars holds, four does not, a review without stars never.
	$rule = ws_flow_condition_normalize($review['low']['config'])['rule'];
	pg_assert_true(ws_flow_condition_value_holds($rule, array(WS_STEP_TRIGGER => array('rating' => 2)))['holds']);
	pg_assert_false(ws_flow_condition_value_holds($rule, array(WS_STEP_TRIGGER => array('rating' => 4)))['holds']);
	pg_assert_false(ws_flow_condition_value_holds($rule, array(WS_STEP_TRIGGER => array('rating' => null)))['holds'], 'no stars');

	$failed = array_column($by_key['subscription_failed']['graph']['nodes'], null, 'id');
	pg_assert_same('notify', $failed['told']['kind']);
	pg_assert_same('task_open', $failed['contact']['kind']);
	pg_assert_same('high', $failed['contact']['config']['priority']);
}


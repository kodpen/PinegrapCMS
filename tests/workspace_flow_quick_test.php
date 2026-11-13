<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quick setup of a flow (includes/workspace/flow_quick.php):
 * every start event and start box sits in one area of the site, every kind
 * of step box in one group or in the list of those left out, the catalogue
 * offers only what the person has, each recipe and each set of choices
 * becomes a graph the engine checks without a fault of its own shape, and
 * the dialog's action and config reach it.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// The record each event's run is about, for the events a test reads it of.
function ws_flow_quick_test_record($event)
{
	$records = array('order' => 'order', 'form' => 'form', 'booking' => 'booking', 'stock' => 'product', 'customer' => 'contact', 'cart_quote' => 'cart_quote');

	return $records[explode('.', $event)[0]] ?? '';
}

// Every event of the event start's catalogue, read from its source: the
// function drops the events of modules a site does not run.
function ws_flow_quick_test_events()
{
	$source = pg_test_function_source('ws_events_catalog');

	preg_match_all('~\'([a-z_]+(?:\.[a-z_]+)+)\'\s*=>\s*array\(\'label\'~', $source, $listed);
	preg_match_all('~\$catalog\[\'([a-z_.]+)\'\]\s*=~', $source, $added);

	return array_values(array_unique(array_merge($listed[1], $added[1])));
}

// What a staff member who may wait for every event is offered.
function ws_flow_quick_test_fill($overrides = array())
{
	$events = array();

	foreach (ws_flow_quick_test_events() as $event) {
		$events[$event] = array('label' => 'Label of ' . $event, 'when' => 'When ' . $event, 'record' => ws_flow_quick_test_record($event));
	}

	return $overrides + array(
		'me'     => 5,
		'events' => $events,
		'starts' => array('manual', 'schedule', 'mention', 'web', 'join', 'row', 'no_order'),
		'kinds'  => null,
	);
}

function ws_flow_quick_test_catalogue($overrides = array())
{
	return ws_flow_quick_catalogue(ws_flow_quick_test_fill($overrides));
}

// The node of a graph by its id.
function ws_flow_quick_test_node($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

// The arrows of a graph as from>to:on, in order.
function ws_flow_quick_test_edges($graph)
{
	return array_map(function ($edge) {
		return $edge['from'] . '>' . $edge['to'] . ':' . $edge['on'];
	}, $graph['edges']);
}

// The codes of the problems a graph has beyond a box's own missing
// settings (bad_config), which the dialog's step settings fill.
function ws_flow_quick_test_shape_problems($graph)
{
	$codes = array();

	foreach (ws_flow_graph_check($graph) as $problem) {
		if (($problem['code'] !== 'bad_config') && empty($problem['warning'])) {
			$codes[] = $problem['code'] . ' ' . $problem['text'];
		}
	}

	return $codes;
}

// Every event a flow can start on and every start box sits in exactly one
// area of the site; Often used repeats only starts that have an area.
function test_workspace_flow_quick_areas_hold_every_start()
{
	$placed = array();

	foreach (ws_flow_quick_areas() as $key => $area) {
		pg_assert_true(in_array($area['part'], array('top', 'site', 'other'), true), $key . ' has its part of the rail');
		pg_assert_true(strpos($area['icon'], 'bi-') === 0, $key . ' has an icon');

		if ($area['part'] === 'top') {
			continue;
		}

		foreach ($area['starts'] as $start) {
			pg_assert_false(isset($placed[$start]), $start . ' sits in one area');
			$placed[$start] = $key;
		}
	}

	$events = ws_flow_quick_test_events();

	pg_assert_true(count($events) >= 50, 'the catalogue\'s events are read');

	foreach (array_unique(array_merge($events, array_keys(ws_flow_event_outputs()))) as $event) {
		pg_assert_true(isset($placed['event:' . $event]), $event . ' has its area');
	}

	foreach (ws_flow_kinds() as $kind => $info) {
		if (!empty($info['trigger']) && ($kind !== 'event')) {
			pg_assert_true(isset($placed[$kind]), $kind . ' has its area');
		}
	}

	foreach (ws_flow_quick_areas()['popular']['starts'] as $start) {
		pg_assert_true(isset($placed[$start]), $start . ' is often used and has an area');
	}

	foreach (array_keys($placed) as $start) {
		$kind = (strpos($start, 'event:') === 0) ? 'event' : $start;
		pg_assert_true(!empty(ws_flow_kinds()[$kind]['trigger']), $start . ' is a start the flow knows');
	}
}

// Every kind of box is a start, a step of one group or left out with its
// reason; a group names only kinds that exist and that a new box is made of.
function test_workspace_flow_quick_groups_place_every_kind()
{
	$kinds = ws_flow_kinds();
	$grouped = array();

	foreach (ws_flow_quick_groups() as $key => $group) {
		foreach ($group['kinds'] as $kind) {
			pg_assert_true(isset($kinds[$kind]), $kind . ' of ' . $key . ' is a kind of box');
			pg_assert_false(isset($grouped[$kind]), $kind . ' sits in one group');
			pg_assert_false(in_array($kind, ws_flow_kinds_not_offered(), true), $kind . ' is offered on the canvas too');
			$grouped[$kind] = true;
		}
	}

	foreach ($kinds as $kind => $info) {
		$placed = !empty($info['trigger']) || isset($grouped[$kind]) || in_array($kind, ws_flow_quick_kinds_left_out(), true)
			|| in_array($kind, ws_flow_kinds_not_offered(), true);
		pg_assert_true($placed, $kind . ' is offered by the quick setup or left out with its reason');
	}
}

// The catalogue offers the starts the person has, with the values "Only
// when" may look at; an area with none of them is left out.
function test_workspace_flow_quick_catalogue_offers_what_the_person_has()
{
	$catalogue = ws_flow_quick_test_catalogue();
	$order = $catalogue['starts']['event:order.created'];

	pg_assert_same('event', $order['kind']);
	pg_assert_same('order.created', $order['event']);
	pg_assert_same('When order.created', $order['label']);
	pg_assert_same(array('record' => 'order', 'id' => '{{trigger.record_id}}'), $order['read']);

	$fields = array_column($order['fields'], 'type', 'key');

	pg_assert_same('text', $fields['trigger.order_number'] ?? null, 'the event\'s own values');
	pg_assert_same('number', $fields['record.total'] ?? null, 'the order\'s total, read by a box');
	pg_assert_false(isset($fields['trigger.event']), 'the event itself is no condition');
	pg_assert_same(array('record' => 'contact', 'id' => '{{trigger.contact_id}}'), $catalogue['starts']['no_order']['read']);
	pg_assert_same(array(), $catalogue['starts']['row']['fields'], 'a row start\'s values come from its table');

	// Only the events the person may wait for; an area without any is gone.
	$few = ws_flow_quick_test_catalogue(array('events' => array('form.submitted' => array('label' => 'A form', 'when' => 'When a form', 'record' => 'form')),
		'starts' => array('manual', 'schedule')));
	$areas = array_column($few['areas'], 'starts', 'key');

	pg_assert_same(array('popular', 'forms', 'time', 'hand'), array_keys($areas));
	pg_assert_same(array('event:form.submitted', 'schedule', 'manual'), $areas['popular']);
	pg_assert_same(array('manual'), $areas['hand'], 'no web start where it is not open');
	pg_assert_false(isset($few['starts']['event:order.created']));
}

// A role-3 member is offered only the kinds of box their own flows hold,
// and only the recipes made of them.
function test_workspace_flow_quick_catalogue_for_a_member()
{
	$catalogue = ws_flow_quick_test_catalogue(array('kinds' => ws_flow_member_kinds()));
	$steps = array();

	foreach ($catalogue['groups'] as $group) {
		$steps = array_merge($steps, $group['kinds']);
	}

	pg_assert_same(array(), array_values(array_diff($steps, ws_flow_member_kinds())));
	pg_assert_false(in_array('email', $steps, true), 'no e-mail to addresses');
	pg_assert_false(isset($catalogue['starts']['web']), 'no web start');
	pg_assert_false(isset($catalogue['starts']['mention']), 'no mention start');
	pg_assert_true(isset($catalogue['starts']['no_order']));

	foreach ($catalogue['recipes'] as $recipe) {
		foreach ($recipe['steps'] as $step) {
			pg_assert_true(in_array($step['kind'], ws_flow_member_kinds(), true), $recipe['key'] . ' is made of the member\'s kinds');
		}
	}

	pg_assert_true(in_array('restock', array_column($catalogue['recipes'], 'key'), true));
	pg_assert_false(in_array('thank_then_review', array_column($catalogue['recipes'], 'key'), true));
}

// Each recipe fits a start that exists and becomes a graph with no fault of
// its shape; a box's settings left for the dialog are its own problems.
function test_workspace_flow_quick_recipes_build()
{
	$catalogue = ws_flow_quick_test_catalogue();
	$keys = array();

	foreach ($catalogue['recipes'] as $recipe) {
		pg_assert_false(isset($keys[$recipe['key']]), $recipe['key'] . ' is one recipe');
		$keys[$recipe['key']] = true;
		pg_assert_true(($recipe['title'] !== '') && (strpos($recipe['icon'], 'bi-') === 0), $recipe['key'] . ' has a title and an icon');

		$fits = array_values(array_filter($recipe['fits'], function ($key) use ($catalogue) {
			return isset($catalogue['starts'][$key]);
		}));
		$start = in_array('record', $recipe['fits'], true) ? 'event:order.created' : ($fits[0] ?? '');

		pg_assert_true($start !== '', $recipe['key'] . ' fits a start');

		$built = ws_flow_quick_graph($catalogue, array('start' => $start, 'steps' => $recipe['steps']));

		pg_assert_true($built['ok'], $recipe['key'] . ' builds');
		pg_assert_same(array(), ws_flow_quick_test_shape_problems($built['graph']), $recipe['key'] . ' has no fault of its shape');
	}

	$for_order = array_column(ws_flow_quick_recipes_for($catalogue, 'event:order.created'), 'key');

	pg_assert_true(in_array('thank_then_review', $for_order, true));
	pg_assert_true(in_array('task_and_tell', $for_order, true), 'a recipe for any record');
	pg_assert_false(in_array('form_reply', $for_order, true));

	$for_time = array_column(ws_flow_quick_recipes_for($catalogue, 'schedule'), 'key');

	pg_assert_same(array(), $for_time, 'a run at a time is about no record');
	pg_assert_same(array('win_back'), array_column(ws_flow_quick_recipes_for($catalogue, 'no_order'), 'key'));
	pg_assert_same(array(), ws_flow_quick_recipes_for($catalogue, 'event:nothing'));
}

// "Only when" becomes condition boxes on yes after the start; a value of
// the record is read by one Read a record box first.
function test_workspace_flow_quick_graph_only_when()
{
	$built = ws_flow_quick_graph(ws_flow_quick_test_catalogue(), array(
		'start'     => 'event:order.created',
		'config'    => array('event' => 'order.shipped', 'status' => 'complete'),
		'only_when' => array(
			array('field' => 'record.total', 'op' => 'gt', 'value' => '1.000,00'),
			array('field' => 'trigger.order_number', 'op' => 'contains', 'value' => 'SP'),
		),
		'steps'     => array(array('kind' => 'notify', 'config' => array('user_ids' => array(5), 'text' => 'Big order {{record}}'))),
		'name'      => '  Big orders  ',
	));

	pg_assert_true($built['ok']);
	pg_assert_same('Big orders', $built['name']);

	$graph = $built['graph'];

	pg_assert_same(array('start', 'read', 'only_when1', 'only_when2', 'step1'), array_column($graph['nodes'], 'id'));
	pg_assert_same(array('start>read:next', 'read>only_when1:done', 'only_when1>only_when2:yes', 'only_when2>step1:yes'), ws_flow_quick_test_edges($graph));
	pg_assert_same('order.created', ws_flow_quick_test_node($graph, 'start')['config']['event'], 'the start keeps its own event');
	pg_assert_same('complete', ws_flow_quick_test_node($graph, 'start')['config']['status']);
	pg_assert_same('order', ws_flow_quick_test_node($graph, 'read')['config']['record']);
	pg_assert_same('{{trigger.record_id}}', ws_flow_quick_test_node($graph, 'read')['config']['id']);
	pg_assert_same(array('type' => 'value', 'ref' => '{{node.read.total}}', 'op' => 'gt', 'value' => '1.000,00'), ws_flow_quick_test_node($graph, 'only_when1')['config']['rule']);
	pg_assert_same('{{trigger.order_number}}', ws_flow_quick_test_node($graph, 'only_when2')['config']['rule']['ref']);
	pg_assert_same(array(), ws_flow_graph_check($graph));

	// No condition on the record: no box reads it, and the flow is named
	// after its start.
	$plain = ws_flow_quick_graph(ws_flow_quick_test_catalogue(), array('start' => 'schedule', 'steps' => array(array('kind' => 'post', 'config' => array()))));

	pg_assert_same(array('start', 'step1'), array_column($plain['graph']['nodes'], 'id'));
	pg_assert_same('At a time', $plain['name']);

	// A step that reads the record gets the box too.
	$reads = ws_flow_quick_graph(ws_flow_quick_test_catalogue(), array('start' => 'event:order.created',
		'steps' => array(array('kind' => 'notify', 'config' => array('user_ids' => array(5), 'text' => 'Total {{node.read.total}}')))));

	pg_assert_same(array('start>read:next', 'read>step1:done'), ws_flow_quick_test_edges($reads['graph']));
	pg_assert_same(array(), ws_flow_graph_check($reads['graph']));
}

// Steps follow each other on their first outcome; a condition's steps on
// yes, after which the chain goes on from them or from its no; a loop's
// steps on each, ending in an End for each box the setup places.
function test_workspace_flow_quick_graph_chain()
{
	$built = ws_flow_quick_graph(ws_flow_quick_test_catalogue(), array(
		'start' => 'manual',
		'steps' => array(
			array('kind' => 'approval', 'id' => 'ask', 'config' => array('answer' => 'number')),
			array('kind' => 'condition', 'id' => 'big', 'config' => array('rule' => array('type' => 'value', 'ref' => '{{node.ask.answer_number}}', 'op' => 'gt', 'value' => '5')),
				'steps' => array(array('kind' => 'notify', 'id' => 'tell', 'config' => array()))),
			array('kind' => 'for_each', 'id' => 'each', 'config' => array('mode' => 'count', 'count' => '3'), 'steps' => array(
				array('kind' => 'condition', 'id' => 'odd', 'config' => array(), 'steps' => array(array('kind' => 'post', 'config' => array()))),
				array('kind' => 'task_open', 'config' => array()),
			)),
			array('kind' => 'condition', 'id' => 'gate', 'config' => array()),
			array('kind' => 'delay', 'config' => array('amount' => 1, 'unit' => 'days')),
		),
	));

	pg_assert_true($built['ok'], 'builds');

	$graph = $built['graph'];

	pg_assert_same(array('start', 'ask', 'big', 'tell', 'each', 'odd', 'step1', 'step2', 'loop_end1', 'gate', 'step3'), array_column($graph['nodes'], 'id'));
	pg_assert_same(array(
		'start>ask:next', 'ask>big:approved', 'big>tell:yes', 'tell>each:done', 'big>each:no', 'each>odd:each', 'odd>step1:yes', 'step1>step2:done',
		'odd>step2:no', 'step2>loop_end1:done', 'loop_end1>gate:done', 'gate>step3:yes',
	), ws_flow_quick_test_edges($graph));
	pg_assert_same('for_each_end', ws_flow_quick_test_node($graph, 'loop_end1')['kind']);
	pg_assert_same('each', ws_flow_quick_test_node($graph, 'loop_end1')['config']['for_each']);
	pg_assert_same(112, ws_flow_quick_test_node($graph, 'tell')['y'], 'a held step a row lower');
	pg_assert_same(224, ws_flow_quick_test_node($graph, 'step1')['y']);
	pg_assert_same(array(), ws_flow_quick_test_shape_problems($graph), 'the loop and the joins pass the graph check');
}

// A Pinegrap AI box that sorts goes on to the next step whatever it found;
// one that answers, when it answered.
function test_workspace_flow_quick_ai_box_goes_on()
{
	$kinds = ws_flow_kinds();

	pg_assert_same('always', ws_flow_quick_on($kinds, array('kind' => 'ai', 'config' => array('mode' => 'classify', 'classes' => array('A', 'B')))));
	pg_assert_same('done', ws_flow_quick_on($kinds, array('kind' => 'ai', 'config' => array('mode' => 'ask'))));
	pg_assert_same('done', ws_flow_quick_on($kinds, array('kind' => 'task', 'config' => array())));
	pg_assert_same('done', ws_flow_quick_on($kinds, array('kind' => 'delay', 'config' => array())));
}

// The ids the dialog gives its steps are kept, so a step reads an earlier
// one's values; one taken, of the wrong shape or the setup's own gets a
// free one.
function test_workspace_flow_quick_graph_ids()
{
	$built = ws_flow_quick_graph(ws_flow_quick_test_catalogue(), array('start' => 'manual', 'steps' => array(
		array('kind' => 'post', 'id' => 'step2', 'config' => array()),
		array('kind' => 'post', 'id' => 'step2', 'config' => array()),
		array('kind' => 'post', 'id' => 'Bad id', 'config' => array()),
		array('kind' => 'post', 'id' => 'start', 'config' => array()),
		array('kind' => 'post', 'id' => 'read', 'config' => array()),
	)));

	pg_assert_same(array('start', 'step2', 'step1', 'step3', 'step4', 'step5'), array_column($built['graph']['nodes'], 'id'));
}

// What is wrong with a choice is refused, with the part of the dialog.
function test_workspace_flow_quick_graph_refusals()
{
	$catalogue = ws_flow_quick_test_catalogue();
	$post = array('kind' => 'post', 'config' => array());
	$refused = function ($choices) use ($catalogue) {
		$built = ws_flow_quick_graph($catalogue, $choices);

		pg_assert_false($built['ok']);

		return $built['field'] . ': ' . $built['error'];
	};

	pg_assert_same('start: Choose what starts the flow.', $refused(array('start' => 'event:nothing', 'steps' => array($post))));
	pg_assert_same('start: Choose what starts the flow.', $refused(array('start' => 'event', 'steps' => array($post))));
	pg_assert_same('steps: Choose at least one step.', $refused(array('start' => 'manual', 'steps' => array())));
	pg_assert_same('steps: That step is not offered to you.', $refused(array('start' => 'manual', 'steps' => array(array('kind' => 'end')))));
	pg_assert_same('steps: That step is not offered to you.', $refused(array('start' => 'manual', 'steps' => array(array('kind' => 'start_action')))));
	pg_assert_same('steps: Only a condition or a loop holds steps of its own.',
		$refused(array('start' => 'manual', 'steps' => array(array('kind' => 'post', 'steps' => array($post))))));
	$inner = array('kind' => 'for_each', 'steps' => array($post));
	$held = array('kind' => 'condition', 'steps' => array($inner));
	pg_assert_same('steps: A loop cannot hold another loop.',
		$refused(array('start' => 'manual', 'steps' => array(array('kind' => 'for_each', 'steps' => array($held))))));
	pg_assert_same('steps: A loop needs at least one step inside it.', $refused(array('start' => 'manual', 'steps' => array(array('kind' => 'for_each')))));
	$third = array('kind' => 'condition', 'steps' => array($post));
	$second = array('kind' => 'condition', 'steps' => array($third));
	pg_assert_same('steps: Steps may be held at most 3 deep.',
		$refused(array('start' => 'manual', 'steps' => array(array('kind' => 'condition', 'steps' => array($second))))));
	pg_assert_same('steps: A quick setup places at most 60 boxes; add more on the canvas.', $refused(array('start' => 'manual', 'steps' => array_fill(0, 60, $post))));
	pg_assert_same('only_when: Choose a value of the start for each condition.',
		$refused(array('start' => 'manual', 'only_when' => array(array('field' => 'record.total')), 'steps' => array($post))));
	pg_assert_same('only_when: A start may be narrowed by at most 5 conditions.', $refused(array('start' => 'event:order.created',
		'only_when' => array_fill(0, 6, array('field' => 'trigger.order_number')), 'steps' => array($post))));
	pg_assert_same('only_when: The start is not about a record a flow can read.', $refused(array('start' => 'schedule',
		'steps' => array(array('kind' => 'post', 'config' => array('body' => '{{node.read.total}}'))))));

	// The most boxes a setup places, its own boxes counted.
	pg_assert_true(ws_flow_quick_graph($catalogue, array('start' => 'manual', 'steps' => array_fill(0, 59, $post)))['ok']);
	pg_assert_false(ws_flow_quick_graph($catalogue, array('start' => 'event:order.created', 'only_when' => array(array('field' => 'record.total')),
		'steps' => array_fill(0, 58, $post)))['ok']);
	pg_assert_true(ws_flow_quick_graph($catalogue, array('start' => 'event:order.created', 'only_when' => array(array('field' => 'record.total')),
		'steps' => array_fill(0, 57, $post)))['ok']);
}

// The dialog reaches the server through one panel action, and the editor's
// config carries the catalogue; the flow is made and published by the
// store's own functions.
function test_workspace_flow_quick_wiring()
{
	$root = PG_FUNCTIONS_DIR . '/includes/';

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_quick.php');", (string) file_get_contents($root . 'workspace/bootstrap.php'));
	pg_assert_contains("'ws_flow_quick_setup'", (string) file_get_contents($root . 'panel/actions.php'));

	$actions = (string) file_get_contents($root . 'workspace/actions.php');

	pg_assert_contains("case 'ws_flow_quick_setup':", $actions);
	pg_assert_contains('ws_flow_quick_setup($viewer, array(', $actions);

	$config = pg_test_function_source('ws_flow_js_config');

	pg_assert_contains("'quick'    => \$quick,", $config);
	pg_assert_contains('ws_flow_quick_for($viewer, $scheduled,', $config);

	$setup = pg_test_function_source('ws_flow_quick_setup');

	pg_assert_contains('ws_flow_save($viewer,', $setup);
	pg_assert_contains('ws_flow_publish($viewer, $flow,', $setup);
}

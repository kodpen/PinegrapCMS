<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The quick setup dialog (assets/js/workspace_flow_quick.js): the area it
 * opens on, the starts of an area, the search over every area, the "Only
 * when" rows it sends and the choices in the shape ws_flow_quick_setup
 * takes; the second stage's recipes, step ids, recipe copies and the graph a
 * step's setting reads; its place in the flow bundle and its texts.
 * The JavaScript runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A catalogue in the shape ws_flow_quick_catalogue() gives.
function workspace_flow_quick_js_test_catalogue()
{
	$field = function ($key, $type) {
		return array('key' => $key, 'type' => $type, 'label' => $key);
	};

	return array(
		'areas'  => array(
			array('key' => 'often', 'label' => 'Often used', 'icon' => 'bi-star', 'part' => 'top', 'starts' => array('event:order.created', 'manual')),
			array('key' => 'orders', 'label' => 'Orders', 'icon' => 'bi-bag', 'part' => 'site', 'starts' => array('event:order.created', 'event:order.shipped')),
			array('key' => 'forms', 'label' => 'Forms', 'icon' => 'bi-ui-checks', 'part' => 'site', 'starts' => array('event:form.submitted')),
			array('key' => 'hand', 'label' => 'By hand', 'icon' => 'bi-hand-index', 'part' => 'other', 'starts' => array('manual')),
		),
		'starts' => array(
			'event:order.created'   => array('key' => 'event:order.created', 'kind' => 'event', 'event' => 'order.created', 'label' => 'When an order is placed', 'name' => 'Order placed', 'record' => 'order',
				'fields' => array($field('trigger.record_id', 'number'), $field('record.total', 'number'), $field('record.status', 'text'))),
			'event:order.shipped'   => array('key' => 'event:order.shipped', 'kind' => 'event', 'event' => 'order.shipped', 'label' => 'When an order is shipped', 'name' => 'Order shipped', 'record' => 'order', 'fields' => array()),
			'event:form.submitted'  => array('key' => 'event:form.submitted', 'kind' => 'event', 'event' => 'form.submitted', 'label' => 'When a form is sent', 'name' => 'Form sent', 'record' => 'form', 'fields' => array()),
			'manual'                => array('key' => 'manual', 'kind' => 'manual', 'event' => '', 'label' => 'Started by hand', 'name' => 'Started by hand', 'record' => '', 'fields' => array()),
		),
		'groups'  => array(),
		'recipes' => array(),
		'limits'  => array('boxes' => 60, 'only_when' => 2, 'depth' => 3),
	);
}

// Runs $body (JavaScript with Q = PGWsFlowQuick.pure and C, the catalogue)
// and returns what it gives as JSON; null when Node cannot run.
function workspace_flow_quick_js_test_run($body)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow_quick.js') . "\n"
		. "var Q = window.PGWsFlowQuick.pure;\n"
		. 'var C = ' . json_encode(workspace_flow_quick_js_test_catalogue()) . ";\n"
		. 'process.stdout.write(JSON.stringify((function () {' . $body . '})()));';

	$file = tempnam(sys_get_temp_dir(), 'pgquick') . '.js';
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The rail opens on the site's first area; a start begins in its own area,
// not in Often used; an area's starts come in its order.
function test_workspace_flow_quick_js_areas()
{
	$out = workspace_flow_quick_js_test_run('return [
		Q.firstArea(C),
		Q.firstArea({ areas: [] }),
		Q.areaOfStart(C, "event:order.created"),
		Q.areaOfStart(C, "manual"),
		Q.startsOf(C, "orders").map(function (s) { return s.key; }),
		Q.startsOf(C, "nothing")
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same('orders', $out[0]);
	pg_assert_same('', $out[1]);
	pg_assert_same('orders', $out[2], 'its own area before Often used');
	pg_assert_same('hand', $out[3]);
	pg_assert_same(array('event:order.created', 'event:order.shipped'), $out[4]);
	pg_assert_same(array(), $out[5]);
}

// The search looks in every area, finds each start once, matches every
// word in its sentence, name or area, Turkish letters as their twins.
function test_workspace_flow_quick_js_search()
{
	$out = workspace_flow_quick_js_test_run('var keys = function (list) { return list.map(function (s) { return s.key; }); };
		C.starts["event:order.shipped"].label = "Sipariş kargolandığında";
		return [
			keys(Q.search(C, "order")),
			keys(Q.search(C, "SIPARIS kargo")),
			keys(Q.search(C, "forms")),
			keys(Q.search(C, "hand")),
			keys(Q.search(C, "   ")),
			keys(Q.search(C, "nothing here"))
		];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('event:order.created', 'event:order.shipped'), $out[0], 'once each, in the areas\' order');
	pg_assert_same(array('event:order.shipped'), $out[1]);
	pg_assert_same(array('event:form.submitted'), $out[2], 'by its area');
	pg_assert_same(array('manual'), $out[3]);
	pg_assert_same(array(), $out[4]);
	pg_assert_same(array(), $out[5]);
}

// "Only when" sends a row on a value of the start with a comparison of its
// type and a value, but none for empty; at most the catalogue's limit.
function test_workspace_flow_quick_js_only_when()
{
	$out = workspace_flow_quick_js_test_run('var S = C.starts["event:order.created"];
		return [
			Q.onlyWhen(C, S, [
				{ field: "record.total", op: "gt", value: " 1000 " },
				{ field: "record.status", op: "gt", value: "x" },
				{ field: "record.status", op: "contains", value: "" },
				{ field: "trigger.gone", op: "eq", value: "1" },
				{ field: "record.status", op: "empty", value: "kept" },
				{ field: "record.total", op: "lt", value: "5" }
			]),
			Q.opsFor({ type: "number" }),
			Q.opsFor({ type: "text" }),
			Q.opsFor(null)
		];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('field' => 'record.total', 'op' => 'gt', 'value' => '1000'),
		array('field' => 'record.status', 'op' => 'empty', 'value' => ''),
	), $out[0], 'a text is not compared by size, an empty value is no condition, the limit holds');
	pg_assert_same(array('eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'), $out[1]);
	pg_assert_same(array('eq', 'neq', 'contains', 'empty', 'not_empty'), $out[2]);
	pg_assert_same($out[2], $out[3]);

	// The comparisons are those the server takes for a condition on a value.
	foreach (array_merge($out[1], $out[2]) as $op) {
		pg_assert_true(in_array($op, ws_flow_condition_value_operators(), true), $op);
	}
}

// The choices: an event start names its event, another start box keeps
// what is set, no start gives nothing.
function test_workspace_flow_quick_js_choices()
{
	$out = workspace_flow_quick_js_test_run('return [
		Q.choices(C, { start: "event:order.created", config: { event: "other" }, only_when: [{ field: "record.total", op: "gte", value: "10" }] }),
		Q.choices(C, { start: "manual", config: null, only_when: [] }),
		Q.choices(C, { start: "", only_when: [] }),
		Q.choices(C, { start: "event:nothing" })
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('start' => 'event:order.created', 'config' => array('event' => 'order.created'), 'only_when' => array(array('field' => 'record.total', 'op' => 'gte', 'value' => '10'))), $out[0]);
	pg_assert_same(array('start' => 'manual', 'config' => array(), 'only_when' => array()), $out[1]);
	pg_assert_same(null, $out[2]);
	pg_assert_same(null, $out[3]);
}

// The recipes of a start: those naming it, and those for any start about
// a record; none without a start.
function test_workspace_flow_quick_js_recipes()
{
	$out = workspace_flow_quick_js_test_run('C.recipes = [
			{ key: "thank", fits: ["event:order.created"], steps: [] },
			{ key: "task", fits: ["record"], steps: [] },
			{ key: "back", fits: ["no_order"], steps: [] }
		];
		var keys = function (list) { return list.map(function (r) { return r.key; }); };
		return [keys(Q.recipesFor(C, "event:order.created")), keys(Q.recipesFor(C, "manual")), keys(Q.recipesFor(C, "nothing"))];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('thank', 'task'), $out[0]);
	pg_assert_same(array(), $out[1], 'a start about no record');
	pg_assert_same(array(), $out[2]);
}

// A new step takes the first free id, never the start's or its read box's;
// a recipe's steps are copies after those chosen, held ones after their box;
// a loop counts its End box; the steps are sent with their title.
function test_workspace_flow_quick_js_steps()
{
	$out = workspace_flow_quick_js_test_run('var chosen = [{ id: "step1", kind: "post", config: {}, steps: [] }, { id: "step3", kind: "condition", config: {}, steps: [{ id: "step2", kind: "post", config: {}, steps: [] }] }];
		var recipe = { steps: [{ kind: "email", config: { subject: "Hi" } }, { kind: "for_each", config: {}, steps: [{ kind: "notify", config: {} }] }] };
		var made = Q.fromRecipe(recipe, chosen);
		made[2].config.subject = "Changed";
		return [
			Q.freeId([]),
			Q.freeId(chosen),
			made.map(function (s) { return s.id + ":" + s.kind; }),
			made[3].steps.map(function (s) { return s.id; }),
			recipe.steps[0].config.subject,
			Q.boxCount(made),
			Q.sentSteps([{ id: "step1", kind: "post", label: "Tell", config: { body: "x" }, steps: [], extra: 1 }])
		];');

	if ($out === null) {
		return;
	}

	pg_assert_same('step1', $out[0]);
	pg_assert_same('step4', $out[1], 'a held step\'s id is taken too');
	pg_assert_same(array('step1:post', 'step3:condition', 'step4:email', 'step5:for_each'), $out[2]);
	pg_assert_same(array('step6'), $out[3]);
	pg_assert_same('Hi', $out[4], 'the recipe is copied, not shared');
	pg_assert_same(7, $out[5]);
	pg_assert_same(array(array('id' => 'step1', 'kind' => 'post', 'label' => 'Tell', 'config' => array('body' => 'x'), 'steps' => array())), $out[6]);
}

// The graph a step's setting reads: the start (an event start naming its
// event), the start's Read a record box, the steps chained on their first
// outcome, a condition's held steps on yes and the rest on its no, a loop's
// on each up to its End box; a box's settings are the step's own object.
function test_workspace_flow_quick_js_graph()
{
	$out = workspace_flow_quick_js_test_run('C.starts["event:order.created"].read = { record: "order", id: "{{trigger.record_id}}" };
		var out = function (kind) { return { email: "done", approval: "approved", record_get: "done" }[kind] || ""; };
		var steps = [
			{ id: "step1", kind: "approval", config: {}, steps: [] },
			{ id: "step2", kind: "condition", config: {}, steps: [{ id: "step3", kind: "email", config: {}, steps: [] }] },
			{ id: "step4", kind: "for_each", config: {}, steps: [{ id: "step5", kind: "email", config: {}, steps: [] }] },
			{ id: "step6", kind: "delay", config: null, steps: [] }
		];
		var chosen = { start: "event:order.created", config: {}, only_when: [], steps: steps };
		var graph = Q.graphOf(C, chosen, out);
		var plain = Q.graphOf(C, { start: "manual", config: {}, only_when: [], steps: [] }, out);
		return [
			graph.nodes.map(function (n) { return n.id + ":" + n.kind; }),
			graph.edges.map(function (e) { return e.from + ">" + e.to + ":" + e.on; }),
			graph.nodes[0].config,
			graph.nodes[1].config,
			graph.nodes[2].config === steps[0].config,
			graph.nodes[7].config,
			plain.nodes.map(function (n) { return n.id; }),
			Q.graphOf(C, { start: "", steps: [] }, out)
		];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('start:event', 'read:record_get', 'step1:approval', 'step2:condition', 'step3:email', 'step4:for_each', 'step5:email', 'loop_end1:for_each_end', 'step6:delay'), $out[0]);
	pg_assert_same(array('start>read:next', 'read>step1:done', 'step1>step2:approved', 'step2>step3:yes', 'step3>step4:done', 'step2>step4:no',
		'step4>step5:each', 'step5>loop_end1:done', 'loop_end1>step6:done'), $out[1]);
	pg_assert_same(array('event' => 'order.created'), $out[2]);
	pg_assert_same(array('record' => 'order', 'id' => '{{trigger.record_id}}'), $out[3]);
	pg_assert_true($out[4], 'the setting writes into the step');
	pg_assert_same(array('for_each' => 'step4', 'collect' => ''), $out[5]);
	pg_assert_same(array('start'), $out[6]);
	pg_assert_same(null, $out[7]);
}

// The summary's moves: up and down among siblings, into the condition or
// loop just above (within the depth, no loop in a loop), out after the step
// holding it; a step taken out leaves its held steps in its place.
function test_workspace_flow_quick_js_moves()
{
	$out = workspace_flow_quick_js_test_run('var s = function (id, kind, steps) { return { id: id, kind: kind, config: {}, steps: steps || [] }; };
		var steps = [s("a", "post"), s("c", "condition"), s("b", "email"), s("l", "for_each", [s("x", "post")]), s("m", "for_each")];
		var ids = function (list) { return Q.rows(list).map(function (r) { return r.step.id + "@" + r.depth; }).join(" "); };
		var out = [];
		out.push(Q.moves(steps, "a", 3), Q.moves(steps, "b", 3), Q.moves(steps, "m", 3), Q.moves(steps, "x", 3), Q.moves(steps, "nothing", 3));
		out.push(Q.move(steps, "b", "into", 3), ids(steps));
		out.push(Q.move(steps, "b", "up", 3), Q.move(steps, "a", "down", 3), ids(steps));
		out.push(Q.move(steps, "x", "out", 3), ids(steps));
		out.push(Q.moves([s("c1", "condition"), s("z", "condition", [s("y", "post")])], "z", 2).into);
		out.push(Q.emptyLoop(steps) && Q.emptyLoop(steps).id);
		out.push(Q.remove(steps, "c"), ids(steps), Q.remove(steps, "gone"));
		return out;');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('up' => false, 'down' => true, 'into' => false, 'out' => false), $out[0]);
	pg_assert_same(array('up' => true, 'down' => true, 'into' => true, 'out' => false), $out[1], 'into the condition above');
	pg_assert_same(array('up' => true, 'down' => false, 'into' => false, 'out' => false), $out[2], 'no loop in a loop');
	pg_assert_same(array('up' => false, 'down' => false, 'into' => false, 'out' => true), $out[3]);
	pg_assert_same(array('up' => false, 'down' => false, 'into' => false, 'out' => false), $out[4]);
	pg_assert_true($out[5]);
	pg_assert_same('a@0 c@0 b@1 l@0 x@1 m@0', $out[6]);
	pg_assert_false($out[7], 'the first held step has none above it');
	pg_assert_true($out[8]);
	pg_assert_same('c@0 b@1 a@0 l@0 x@1 m@0', $out[9]);
	pg_assert_true($out[10]);
	pg_assert_same('c@0 b@1 a@0 l@0 x@0 m@0', $out[11], 'out comes right after its box');
	pg_assert_false($out[12], 'deeper than the limit');
	pg_assert_same('l', $out[13], 'the first loop with nothing inside');
	pg_assert_true($out[14]);
	pg_assert_same('b@0 a@0 l@0 x@0 m@0', $out[15]);
	pg_assert_false($out[16]);
}

// What the dialog sends: the first stage's choices, the steps with their
// titles, the name trimmed to 160, the home channel, publish as 0 or 1.
function test_workspace_flow_quick_js_sent()
{
	$out = workspace_flow_quick_js_test_run('var chosen = { start: "manual", config: {}, only_when: [], name: "  " + new Array(200).join("n") + " ",
			steps: [{ id: "step1", kind: "post", label: "Tell", config: { body: "x" }, steps: [] }] };
		return [Q.sent(C, chosen, { home: "7", publish: true }), Q.sent(C, { start: "" }, {})];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('start' => 'manual', 'config' => array(), 'only_when' => array(),
		'steps' => array(array('id' => 'step1', 'kind' => 'post', 'label' => 'Tell', 'config' => array('body' => 'x'), 'steps' => array())),
		'name' => str_repeat('n', 160), 'channel_id' => 7, 'publish' => 1), $out[0]);
	pg_assert_same(null, $out[1]);
}

// The Flows list offers the quick setup beside New flow and under the empty
// list, to whoever builds flows when the catalogue is served, with the
// comparisons' labels, the home channel and the flow it made opened.
function test_workspace_flow_quick_js_ways_in()
{
	$canvas = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_contains("return !!(FL.build && FL.quick && window.PGWsFlowQuick && !singleFlow());", $canvas);
	pg_assert_contains("window.PGWsFlowQuick.open(K, FL.quick, {", $canvas);
	pg_assert_contains("operators: stepLists().operators,", $canvas);
	pg_assert_contains("onDone: quickMade,", $canvas);
	pg_assert_true(preg_match('~function newFlowButton\(\) \{.*?quickButton\(.*?\n    \}~s', $canvas) === 1, 'beside New flow');
	pg_assert_true(preg_match('~ui\.flowsQuick\.appendChild\(quickButton\(~', $canvas) === 1, 'under the empty list');
	pg_assert_true(preg_match('~function quickMade\(result\) \{.*?keepEntry\(result\.flow\);\s*show\(result\.flow\);~s', $canvas) === 1, 'the flow made opens');

	$strings = ws_flow_js_strings();

	foreach (array('fl_quick_title', 'fl_quick_started', 'fl_quick_not_started') as $key) {
		pg_assert_true(isset($strings[$key]), $key);
	}
}

// The dialog is served in the flow bundle after the canvas, and every text
// it asks for is one of the flow strings.
function test_workspace_flow_quick_js_wiring()
{
	pg_assert_same(array('assets/js/pg_var_chips.js', 'assets/js/workspace_flow.js', 'assets/js/workspace_flow_quick.js'), pg_js_bundles()['assets/js/workspace_flow.min.js'] ?? null);

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow_quick.js');
	$strings = ws_flow_js_strings();

	preg_match_all("~t\\('(fl_quick_[a-z_]+)'[,)]~", $source, $matches);
	pg_assert_true(count($matches[1]) > 10, 'the dialog asks for its texts');

	foreach (array_unique($matches[1]) as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is a flow string');
	}

	foreach (array('start', 'steps', 'summary') as $stage) {
		pg_assert_true(isset($strings['fl_quick_stage_' . $stage]), $stage);
	}
}

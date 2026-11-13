<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The quick setup dialog's first stage (assets/js/workspace_flow_quick.js):
 * the area it opens on, the starts of an area, the search over every area,
 * the "Only when" rows it sends and the choices in the shape
 * ws_flow_quick_setup takes; its place in the flow bundle and its texts.
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

// The dialog is served in the flow bundle after the canvas, and every text
// it asks for is one of the flow strings.
function test_workspace_flow_quick_js_wiring()
{
	pg_assert_same(array('assets/js/workspace_flow.js', 'assets/js/workspace_flow_quick.js'), pg_js_bundles()['assets/js/workspace_flow.min.js'] ?? null);

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

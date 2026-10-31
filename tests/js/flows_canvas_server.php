<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What the Flows canvas harness (tests/js/flows_canvas.js) takes from the
 * product's PHP, which needs no database for it: the texts the flows screen
 * carries, the script bundles (pg_js_bundles()), the parts of the canvas's
 * settings that are not read from the database (ws_flow_js_config()), and
 * the graph rules its stand-in for api.php answers with.
 *
 * It prints one JSON line (texts, bundles, config), then answers each JSON
 * line of standard input, {"id": n, "graph": {...}}, with one line: the graph
 * as the store keeps it (ws_flow_graph_normalize(), presented as
 * ws_flow_detail() presents it) and what ws_flow_graph_check() finds in it,
 * problems and warnings apart. It ends with its input. ws_flow_problems() is
 * not used: its extra check reads the site's record types from the database.
 *
 * Usage:  php tests/js/flows_canvas_server.php   (started by flows_canvas.js)
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

// The modules load as tools/test.php loads them: functions only, no
// connection.
require_once(dirname(__DIR__, 2) . '/pinegrap/functions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// The texts ws_screen_config() hands the screen, merged in its order: the
// sets it names, read from its own source, so a set added there comes here
// too. Its one adjustment (the estimate hint's minutes) is no canvas text.
$screen = new ReflectionFunction('ws_screen_config');
$source = implode('', array_slice(file($screen->getFileName()), $screen->getStartLine() - 1, $screen->getEndLine() - $screen->getStartLine() + 1));
preg_match_all('/\b(ws_(?:[a-z0-9_]+_)?js_strings)\s*\(\s*\)/', $source, $calls);

$texts = array();

foreach (array_unique($calls[1]) as $set) {
	if (function_exists($set)) {
		$texts = array_merge($texts, $set());
	}
}

// What ws_flow_js_config() gives staff who build flows, but for what it
// reads from the database: the screen is ready, the person builds flows,
// and the lists of the step boxes are empty (the store fills them where a
// scenario needs them).
$outputs = array();
$trigger_outputs = array('kinds' => array(), 'events' => function_exists('ws_flow_event_outputs') ? ws_flow_event_outputs() : array());

foreach (ws_flow_kinds() as $kind => $info) {
	$outputs[$kind] = $info['outputs'];

	if (!empty($info['trigger'])) {
		$trigger_outputs['kinds'][$kind] = function_exists('ws_flow_trigger_outputs') ? ws_flow_trigger_outputs($kind) : array();
	}
}

$config = array(
	'nav'       => true,
	'scheduled' => false,
	'ready'     => true,
	'build'     => true,
	'templates' => array(),
	'template_categories' => function_exists('ws_flow_template_categories') ? ws_flow_template_categories() : array(),
	'template_save' => true,
	'steps'     => array(
		'metrics' => array(), 'digest_only' => (object) array(), 'targets' => array(), 'changes' => (object) array(),
		'records' => (object) array(), 'operators' => (object) array(), 'counts' => array(), 'repeats' => (object) array(),
		'events' => null, 'tables' => array(), 'mcp' => array(),
		'retry' => array('kinds' => function_exists('ws_step_kinds') ? array_keys(ws_step_kinds()) : array(), 'max' => WS_FLOW_RETRY_MAX, 'after_max' => WS_FLOW_DELAY_MAX),
	),
	'outputs'   => $outputs,
	'trigger_outputs' => $trigger_outputs,
	'subjects'  => array(),
	'derived'   => function_exists('ws_flow_derived_lists') ? ws_flow_derived_lists() : array(),
	'limits'    => array('nodes' => WS_FLOW_MAX_NODES, 'notes' => WS_FLOW_MAX_NOTES, 'coord' => WS_FLOW_COORD_MAX, 'constants' => defined('WS_FLOW_CONSTANTS_MAX') ? WS_FLOW_CONSTANTS_MAX : 20),
);

echo json_encode(array('texts' => $texts, 'bundles' => pg_js_bundles(), 'config' => $config), $json_flags), "\n";
flush();

while (($line = fgets(STDIN)) !== false) {
	$request = json_decode($line, true);

	if (!is_array($request)) {
		continue;
	}

	$graph = ws_flow_graph_normalize($request['graph'] ?? array());
	$answer = array('id' => $request['id'] ?? 0, 'graph' => ws_flow_graph_present($graph, true), 'problems' => array(), 'warnings' => array());

	foreach (ws_flow_graph_check($graph) as $problem) {
		$answer[empty($problem['warning']) ? 'problems' : 'warnings'][] = $problem;
	}

	echo json_encode($answer, $json_flags), "\n";
	flush();
}

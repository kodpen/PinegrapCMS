<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the graph canvas core (assets/js/pg_graph_canvas.js,
 * window.PGGraphCanvas): it is bundled and printed before the flow canvas
 * that uses it, the flow canvas keeps no copy of what moved into it, and its
 * viewport pans and zooms as the flow canvas did.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The core is a bundle of its own, and the flows screen prints it before
// the flow canvas: workspace_flow.js reads window.PGGraphCanvas as it runs.
function test_graph_canvas_bundled_and_printed_first()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_graph_canvas.js'), isset($bundles['assets/js/pg_graph_canvas.min.js']) ? $bundles['assets/js/pg_graph_canvas.min.js'] : null, 'the core has its bundle');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
	$start = strpos($screen, 'function ws_screen_assets(');
	$body = ($start !== false) ? substr($screen, $start, strpos($screen, "\n}\n", $start) - $start) : '';
	$core = strpos($body, "pg_js_bundle_tags('assets/js/pg_graph_canvas.min.js')");
	$flow = strpos($body, "pg_js_bundle_tags('assets/js/workspace_flow.min.js')");

	pg_assert_true(($core !== false) && ($flow !== false) && ($core < $flow), 'the flows screen prints the core before the flow canvas');

	$harness = (string) file_get_contents(__DIR__ . '/js/flows_canvas.js');

	pg_assert_true(strpos($harness, "'assets/js/pg_graph_canvas.min.js', 'assets/js/workspace_flow.min.js'") !== false, 'the browser harness serves the core before the flow canvas');
}

// What moved into the core is not defined again in the flow canvas, which
// takes it from window.PGGraphCanvas.
function test_graph_canvas_flow_canvas_keeps_no_copy()
{
	$flow = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	pg_assert_contains('    var G = window.PGGraphCanvas;', $flow, 'the flow canvas reads the core');

	foreach (array('snap', 'clamp', 'svgEl', 'curve', 'freeId', 'typing', 'bytes', 'historyOf', 'historyRecord', 'historyStep', 'applyView', 'zoomAt', 'zoomBy', 'fit', 'canvasPoint') as $name) {
		pg_assert_false((bool) preg_match('/^ *function ' . $name . '\(/m', $flow), 'no ' . $name . '() of its own');
	}

	foreach (array('ZOOM_MIN', 'ZOOM_MAX', 'SVG_NS', 'HISTORY_MAX', 'HISTORY_FOLD') as $name) {
		pg_assert_false((bool) preg_match('/^ *var ' . $name . ' = /m', $flow), 'no ' . $name . ' of its own');
	}
}

// Runs the core in Node with a stand-in viewport 800 x 600 at (100, 50) and
// the script given; null when Node cannot run.
function graph_canvas_test_node($script)
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

	$stage = <<<'JS'
var window = {};
function Box() { this.style = {}; this.textContent = ''; }
var ui = { viewport: new Box(), layer: new Box(), zoomLabel: new Box() };
ui.viewport.getBoundingClientRect = function () { return { left: 100, top: 50, width: 800, height: 600 }; };
var state = { view: { x: 40, y: 40, k: 1 }, nodes: [] };
JS;
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');
	$host = <<<'JS'
var G = window.PGGraphCanvas;
var view = G.viewport({
    ui: ui,
    view: function (next) { if (next) { state.view = next; } return state.view; },
    items: function () { return state.nodes; }
});
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pggraph');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stage . "\n" . $core . "\n" . $host . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The viewport: the transform, grid and label follow the view; zoom keeps
// the point under the pointer; zoom stays within 0.25-2; fit brings every
// box into sight (an empty canvas goes back to 40, 40 at 100%); a point is
// read in canvas units; a box near the edge is brought into the middle; a
// pinch and the wheel move the view.
function test_graph_canvas_viewport()
{
	$result = graph_canvas_test_node(<<<'JS'
var out = {};
function copy(value) { return JSON.parse(JSON.stringify(value)); }
view.apply();
out.apply = [ui.layer.style.transform, ui.viewport.style.backgroundSize, ui.viewport.style.backgroundPosition, ui.zoomLabel.textContent];
var before = view.point(300, 250);
view.zoomAt(300, 250, 2);
var after = view.point(300, 250);
out.zoomKeepsPoint = [before, after, state.view.k];
view.zoomBy(10);
out.zoomMax = state.view.k;
view.zoomBy(0.001);
out.zoomMin = [state.view.k, ui.viewport.style.backgroundSize, ui.zoomLabel.textContent];
view.fit();
out.fitEmpty = copy(state.view);
state.nodes = [{ x: 0, y: 0 }, { x: 1000, y: 400 }];
view.fit();
out.fit = [Math.round(state.view.x * 100) / 100, Math.round(state.view.y * 100) / 100, Math.round(state.view.k * 1000) / 1000];
state.view = { x: 0, y: 0, k: 1 };
view.reveal({ x: 100, y: 100 });
out.revealInSight = copy(state.view);
view.reveal({ x: 2000, y: 100 });
out.revealOut = copy(state.view);
state.view = { x: 0, y: 0, k: 1 };
var pinch = view.pinchStart([{ x: 200, y: 150 }, { x: 300, y: 150 }]);
view.pinchMove(pinch, [{ x: 150, y: 150 }, { x: 350, y: 150 }]);
out.pinch = copy(state.view);
var prevented = false;
view.wheel({ preventDefault: function () { prevented = true; }, deltaX: 5, deltaY: 7, ctrlKey: false, metaKey: false });
out.wheel = [state.view.x, state.view.y, prevented];
out.history = (function () {
    var h = G.historyOf(1, 'a');
    G.historyRecord(h, 'b', false, 0);
    return [G.historyStep(h, true), G.historyStep(h, false)];
})();
out.helpers = [G.snap(23), G.snap(25), G.clamp(5, 0, 3), G.freeId('task', [{ id: 'task_1' }]), G.curve(0, 0, 10, 20).d];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('translate(40px, 40px) scale(1)', '16px 16px', '40px 40px', '100%'), $result['apply'], 'the layer, the grid and the label follow the view');
	pg_assert_same($result['zoomKeepsPoint'][0], $result['zoomKeepsPoint'][1], 'zoom keeps the point under the pointer');
	pg_assert_same(2, $result['zoomKeepsPoint'][2], 'zoomed twice as large');
	pg_assert_same(2, $result['zoomMax'], 'at most 200%');
	pg_assert_same(array(0.25, '16px 16px', '25%'), $result['zoomMin'], 'at least 25%, every fourth grid dot');
	pg_assert_same(array('x' => 40, 'y' => 40, 'k' => 1), $result['fitEmpty'], 'an empty canvas fits at 40, 40, 100%');
	pg_assert_same(array(48, 160.13, 0.583), $result['fit'], 'fit brings every box into sight');
	pg_assert_same(array('x' => 0, 'y' => 0, 'k' => 1), $result['revealInSight'], 'a box in sight is left where it is');
	pg_assert_same(array('x' => -1704, 'y' => 160, 'k' => 1), $result['revealOut'], 'a box out of sight comes to the middle');
	pg_assert_same(array('x' => -150, 'y' => -100, 'k' => 2), $result['pinch'], 'a pinch zooms about its middle');
	pg_assert_same(array(-155, -107, true), $result['wheel'], 'the wheel pans');
	pg_assert_same(array('a', 'b'), $result['history'], 'undo and redo');
	pg_assert_same(array(16, 32, 3, 'task_2', 'M0 0 C48 0 -38 20 10 20'), $result['helpers'], 'the geometry helpers');
}

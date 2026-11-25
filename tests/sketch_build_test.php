<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Build the page" and "Build again" of a Prototype view sketch page
 * (assets/js/style_designer_sketchbuild.js, the part 'sketchbuild'): what
 * "Build again" does with each section, the page's children in the
 * sketch's order with what was added in Design kept in place, the
 * signatures, the part's file and the core's wiring. The pure functions run
 * in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with the part loaded through a stand-in
// definePart; `P` is its pure functions. `out` comes back decoded; null
// when Node cannot run.
function sketch_build_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {}, api = null;\n"
		. "var StyleDesigner = {definePart: function (name, f) { api = f({_sdT: function (s) { return s; }, esc: function (s) { return s; }}); }};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchbuild.js') . "\n"
		. "var P = api.pure, out = null;\n"
		. "function sec(id, kind, more) { var s = {id: id, kind: kind, widget: null, columns: 1, buttons: 0, picture: false, heading: ''}; for (var k in (more || {})) s[k] = more[k]; return s; }\n"
		. "function n(id, text) { return {_id: 'sd_' + id, type: 'semantic', props: {_sketchId: id, text: text || ''}, children: []}; }\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsketchbuild');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Each section's fate: unchanged stays, changed and not edited is built
// again, changed and edited clashes, new is added, gone is removed; asked
// only for a removal or a clash.
function test_sketch_build_plan_sorts_the_sections()
{
	$out = sketch_build_test_node(<<<'JS'
var was = [sec(1, 'header'), sec(2, 'intro'), sec(3, 'features', {columns: 3}), sec(4, 'text'), sec(5, 'cta'), sec(6, 'footer')];
var nodes = {1: n(1), 2: n(2), 3: n(3), 4: n(4), 5: n(5), 6: n(6)};
var built = {}, now = {};
was.forEach(function (s) { built[s.id] = {sig: P.secSig(s), fp: P.nodeFp(nodes[s.id])}; now[s.id] = P.nodeFp(nodes[s.id]); });
// Intro edited in Design, its box changed; features gets four columns;
// text edited, box as it was; cta gone; a gallery new; the header has a
// button now (shared: stays).
now[2] = P.nodeFp(n(2, 'edited'));
now[4] = P.nodeFp(n(4, 'edited'));
var sections = [sec(1, 'header', {buttons: 1}), sec(2, 'intro', {heading: 'Hi'}), sec(3, 'features', {columns: 4}), sec(4, 'text'), sec(7, 'gallery'), sec(6, 'footer')];
var quiet = P.plan(sections.filter(function (s) { return s.id !== 7; }).concat([sec(5, 'cta')]), built, now);
var same = P.plan(was, built, (function () { var o = {}; was.forEach(function (s) { o[s.id] = P.nodeFp(nodes[s.id]); }); return o; })());
out = {plan: P.plan(sections, built, now), quiet: quiet.ask, same: [same.stay.length, same.ask]};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('1', '4', '6'), $out['plan']['stay'], 'the shared header, the edited text whose box is the same and the footer stay');
	pg_assert_same(array('3'), $out['plan']['rebuild'], 'features changed and was not edited: built again');
	pg_assert_same(array('2'), $out['plan']['clash'], 'intro was edited and its box changed: a clash');
	pg_assert_same(array('7'), $out['plan']['add'], 'the gallery is new');
	pg_assert_same(array('5'), $out['plan']['remove'], 'the call to action left the sketch');
	pg_assert_true($out['plan']['ask'], 'a removal or a clash asks');
	pg_assert_true($out['quiet'], 'a clash alone asks too');
	pg_assert_same(array(6, false), $out['same'], 'nothing changed: everything stays and nothing is asked');
}

// A section never built (its node deleted in Design, or new) is added, and
// a build with only changes to rebuild asks nothing.
function test_sketch_build_plan_rebuilds_without_asking()
{
	$out = sketch_build_test_node(<<<'JS'
var a = sec(1, 'intro'), b = sec(2, 'text');
var built = {1: {sig: P.secSig(a), fp: P.nodeFp(n(1))}, 2: {sig: P.secSig(b), fp: P.nodeFp(n(2))}};
var pl = P.plan([sec(1, 'intro', {picture: true}), b], built, {1: P.nodeFp(n(1))});
out = [pl.rebuild, pl.add, pl.remove, pl.ask];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('1'), array('2'), array(), false), $out, 'intro built again, the deleted text added again, nothing asked');
}

// The children in the sketch's order; what was added in Design stays after
// the section it followed, after a removed one after the one before it.
function test_sketch_build_arrange_keeps_added_nodes()
{
	$out = sketch_build_test_node(<<<'JS'
var mine = {_id: 'sd_90', type: 'semantic', props: {customName: 'mine'}, children: []};
var top = {_id: 'sd_91', type: 'semantic', props: {customName: 'top'}, children: []};
var late = {_id: 'sd_92', type: 'semantic', props: {customName: 'late'}, children: []};
var kids = [top, n(1), n(2), mine, n(3), late, n(4)];
var fresh = {1: n(1, 'a'), 2: n(2, 'b'), 4: n(4, 'd'), 5: n(5, 'e')};
var list = P.arrange(kids, ['1', '5', '2', '4'], function (id) { return fresh[id]; });
out = list.map(function (c) { return c.props.customName || String(c.props._sketchId); });
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('top', '1', '5', '2', 'mine', 'late', '4'), $out, 'top first, mine after 2, late (after the removed 3) after 2 as well');
}

// The signatures: a moved box changes the sketch's, not the section's; an
// edit changes the node's, its ids and open marks do not.
function test_sketch_build_signatures()
{
	$out = sketch_build_test_node(<<<'JS'
var page = {id: 1, boxes: [{id: 2, x: 0, y: 0, w: 100, h: 40, kind: '', text: ''}]};
var moved = {id: 1, boxes: [{id: 2, x: 8, y: 0, w: 100, h: 40, kind: '', text: ''}]};
var typed = {id: 1, boxes: page.boxes, details: {type: 'catalog'}};
var a = n(3, 'x'), b = JSON.parse(JSON.stringify(a));
b._id = 'sd_77'; b.props._expanded = true;
out = [
	P.boxSig(page) === P.boxSig(moved), P.boxSig(page) === P.boxSig(typed),
	P.secSig(sec(1, 'intro')) === P.secSig(sec(1, 'intro', {top: 40})),
	P.nodeFp(a) === P.nodeFp(b), P.nodeFp(a) === P.nodeFp(n(3, 'y'))
];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(false, false, true, true, false), $out, 'box moved and page type change the sketch; place not the section; ids not the node; text does');
}

// The part is handed to every editor, loads the reader and the builder
// first, and the core loads it when the switch goes on, draws through it
// and keeps a built sketch.
function test_sketch_build_wiring()
{
	$part = pg_designer_part_files();
	pg_assert_true(isset($part['sketchbuild']), 'the part sketchbuild exists');
	pg_assert_same('always', $part['sketchbuild']['when'], 'every editor may build');
	pg_assert_same(array('assets/js/pg_sketch_reader.js', 'assets/js/pg_sketch_builder.js', 'assets/js/style_designer_sketchbuild.js'), $part['sketchbuild']['sources'], 'the reader and builder before the part');

	$core = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
	pg_assert_contains("_sdPartLoad('sketchbuild')", $core, 'the switch loads the part');
	pg_assert_contains("_sdPartApis.sketchbuild.fill(_sdSketchBuildHost()", $core, 'the frame is drawn through the part');
	pg_assert_contains("_sdPartApis.sketchbuild.escape()", $core, 'Escape closes the list or the question');
	pg_assert_contains("id: 'act_sketch_build'", $core, 'the command palette lists Build the page');
	pg_assert_contains("if (p.built && /^[a-z0-9]{4,40}$/", $core, 'the stored sketch keeps what was built');
	pg_assert_contains('props: { _sketch: meta }', $core, 'the draft keeps the sketch in its root');
	pg_assert_false(strpos($core, "pg_sketch_reader.js") !== false && strpos($core, '<script') !== false && preg_match('#<script[^>]+pg_sketch_reader#', $core) === 1, 'no script tag for the reader');
}

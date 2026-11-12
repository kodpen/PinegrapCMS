<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the block drawings of the visual editor's palette hover card
 * (_sdBlockSketch() and _sdBlockSketchOf() in style_designer.src.js): the
 * palette code, the block builders of createFromPalette() and every family
 * file are cut out of the core and run under Node (skipped on a machine
 * without Node); every core and family block is built as a drag builds it
 * and drawn from that tree, and none comes out empty; a row's columns are
 * drawn side by side; the drawing is kept per componentType; the hover card
 * of a block shows it with the block's description line (a family block's
 * from the desc of its item), a component keeps its own drawing and an item
 * with neither shows no card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_block_sketch_test_core()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $js;
}

// The core from $from up to the first $to after it ($to left out), asserted
// found.
function designer_block_sketch_test_cut($from, $to)
{
	$js = designer_block_sketch_test_core();
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// A top-level function of the core, up to its four-space closing brace.
function designer_block_sketch_test_fn($head)
{
	return designer_block_sketch_test_cut('    ' . $head, "\n    }\n") . "\n    }\n";
}

// Runs the drawings under Node and returns what the driver prints; null
// without Node.
function designer_block_sketch_test_run()
{
	static $got = false;
	if ($got !== false) {
		return $got;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		$got = null;
		return $got;
	}

	$script = "'use strict';\n"
		. "function _sdT(key, vars) { return vars === undefined ? key : String(key).replace('{var}', vars); }\n"
		. "var nodeIdCounter = 0, builds = 0;\n"
		. "function gid() { return 'n' + (++nodeIdCounter); }\n"
		. "function createNode(type, props, children) { return { id: gid(), type: type, props: props || {}, children: children || [] }; }\n"
		. "function esc(s) { return s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;') : ''; }\n"
		. "var COMPONENTS = {}, CONTENT = {};\n"
		. designer_block_sketch_test_fn('function colProps(xs)')
		. designer_block_sketch_test_fn('function makeCarouselSlide(')
		. designer_block_sketch_test_cut('    // Palette items as the tree they would add', '    // A semantic <input type="hidden">')
		. designer_block_sketch_test_fn('function createFromPalette(type, extra)')
		. designer_block_sketch_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_block_sketch_test_cut('    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. designer_block_sketch_test_cut('    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. designer_block_sketch_test_cut('    // ── Palette item info: hover card and search words', '    function _sdPalTipHide()')
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n";
	foreach (array_slice(pg_designer_js_sources(), 1) as $family) {
		$script .= (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $family) . "\n";
	}
	$script .= <<<'JS'
var _cfp = createFromPalette;
createFromPalette = function (type, extra) { builds++; return _cfp(type, extra); };
function row(it) {
    return { dataset: { type: it.type, label: it.label, icon: it.icon || '', extra: JSON.stringify(it.extra || {}) } };
}
function marks(svg) { return (svg.match(/<(rect|circle|path|ellipse|line)\b/g) || []).length; }
var items = _sdBlockPaletteItems();
var empty = [], thin = [], broken = [], first = {};
items.forEach(function (it) {
    var ct = it.extra.componentType;
    var svg = '';
    try { svg = _sdBlockSketchOf(it); } catch (e) { broken.push(ct + ': ' + e.message); return; }
    first[ct] = svg;
    if (!svg) { empty.push(ct); return; }
    // The page's own white is one mark; a block draws at least two more.
    if (marks(svg) < 3) thin.push(ct);
    if (/NaN|undefined|Infinity|null/.test(svg) || svg.indexOf('<svg') !== 0 || !/<\/svg>$/.test(svg)) broken.push(ct + ': ' + svg.slice(0, 80));
});
var before = builds;
var same = items.every(function (it) { return _sdBlockSketchOf(it) === first[it.extra.componentType]; });

// Two columns, a heading in each: two dark bars on one line.
var cols = { type: 'semantic', props: { tag: 'section', cssClass: 'py-5' }, children: [
    { type: 'container', props: {}, children: [
        { type: 'row', props: {}, children: [
            { type: 'col', props: { lg: '6' }, children: [ { type: 'content', props: { contentType: 'heading', tag: 'h2', text: 'One' }, children: [] } ] },
            { type: 'col', props: { lg: '6' }, children: [ { type: 'content', props: { contentType: 'heading', tag: 'h2', text: 'Two' }, children: [] } ] }
        ] }
    ] }
] };
var bars = [];
_sdBlockSketch(cols).replace(/<rect x="([\d.]+)" y="([\d.]+)"[^>]*fill="#343a40"/g, function (all, x, y) { bars.push([+x, +y]); return all; });

var block = items[0];
var tipBlock = _sdPalTipHTML(row(block));
var tipCard = _sdPalTipHTML(row({ type: 'component', label: 'Card', icon: 'bi-card-heading', extra: { componentType: 'card' } }));
var tipNone = _sdPalTipHTML(row({ type: 'component', label: 'X', extra: { componentType: 'no-such-thing' } }));
var described = items.filter(function (it) { return typeof it.desc === 'string' && it.desc !== ''; });
var famBlock = described[0];
var tipFamily = famBlock ? _sdPalTipHTML(row(famBlock)) : '';

console.log(JSON.stringify({
    total: items.length,
    built: Object.keys(first).length,
    empty: empty,
    thin: thin,
    broken: broken,
    cached: same && builds === before,
    nullTree: _sdBlockSketch(null),
    bareTree: _sdBlockSketch({ type: 'semantic', props: { tag: 'div' }, children: [] }),
    bars: bars,
    tipBlock: [tipBlock.indexOf(first[block.extra.componentType]) !== -1, tipBlock.indexOf('sd-pal-tip-name') !== -1,
               !!_sdBlockDesc(block) && tipBlock.indexOf('<div class="sd-pal-tip-desc">' + esc(_sdBlockDesc(block)) + '</div>') !== -1],
    tipCard: [tipCard.indexOf(_sdPalDrawing('card')) !== -1, tipCard.indexOf('sd-pal-tip-desc') !== -1],
    tipNone: tipNone,
    tipFamily: [described.length, !!famBlock && tipFamily.indexOf('<div class="sd-pal-tip-desc">' + esc(famBlock.desc) + '</div>') !== -1,
                described.every(function (it) { return _sdBlockDesc(it) === it.desc; })]
}));
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgblocksketch');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", array_slice($out, 0, 20)));
	$read = $code === 0 ? json_decode((string) end($out), true) : null;
	$got = (is_array($read) ? $read : array()) + array(
		'total' => 0, 'built' => -1, 'empty' => null, 'thin' => null, 'broken' => null, 'cached' => false,
		'nullTree' => null, 'bareTree' => null, 'bars' => array(), 'tipBlock' => null, 'tipCard' => null, 'tipNone' => null, 'tipFamily' => array(0, false, false),
	);
	return $got;
}

function test_designer_block_sketch_draws_every_block()
{
	$got = designer_block_sketch_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_true(isset($got['total']) && $got['total'] > 200, 'the core and family blocks are listed');
	pg_assert_same($got['total'], $got['built'], 'every block is built and drawn');
	pg_assert_same(array(), $got['broken'], 'blocks whose drawing fails or is not a whole SVG');
	pg_assert_same(array(), $got['empty'], 'blocks drawn empty');
	pg_assert_same(array(), $got['thin'], 'blocks drawn with fewer than two marks');
}

function test_designer_block_sketch_lays_out_the_tree()
{
	$got = designer_block_sketch_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('', $got['nullTree'], 'no tree draws nothing');
	pg_assert_same('', $got['bareTree'], 'a tree with nothing to show draws nothing');
	pg_assert_same(2, count($got['bars']), 'two headings draw two dark bars');
	if (count($got['bars']) === 2) {
		pg_assert_same($got['bars'][0][1], $got['bars'][1][1], 'the columns sit on one line');
		pg_assert_true($got['bars'][1][0] > $got['bars'][0][0] + 40, 'the second column is drawn to the right of the first');
	}
}

function test_designer_block_sketch_cache_and_hover_card()
{
	$got = designer_block_sketch_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_true($got['cached'], 'a second hover draws from the cache without building the block again');
	pg_assert_same(array(true, true, true), $got['tipBlock'], 'a block\'s hover card shows its drawing, its name and its description line');
	pg_assert_same(array(true, true), $got['tipCard'], 'a component keeps its own drawing and description');
	pg_assert_same('', $got['tipNone'], 'an item with neither a description nor a block drawing shows no card');
	pg_assert_true($got['tipFamily'][0] >= 111, 'family blocks carry a desc in their item (' . $got['tipFamily'][0] . ')');
	pg_assert_same(array(true, true), array_slice($got['tipFamily'], 1), 'a family block\'s hover card shows the desc of its item, and every desc is its block\'s line');
}

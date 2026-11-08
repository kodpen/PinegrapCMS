<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for block families in their own files
 * (assets/js/style_designer_blocks_<family>.js): pg_designer_js_sources()
 * lists the core first and the family files sorted, and the designer bundle
 * is built from that list; the block stylesheet reader takes a family file's
 * rules without letting it replace a core rule; the core's registration
 * call, palette readers and helper kit are cut out of style_designer.src.js
 * and run under Node with the sample family file of tests/fixtures (skipped
 * on a machine without Node): its group and blocks reach the palette, its
 * builder draws, its rules join the core's; and createFromPalette() and the
 * drop's container rule ask the family first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_block_families_test_core()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $js;
}

function designer_block_families_test_sample()
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/designer/style_designer_blocks_sample.js');
}

// The core between $from and the first $to after it (both markers kept out
// of the end), asserted found.
function designer_block_families_test_cut($from, $to)
{
	$js = designer_block_families_test_core();
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

function test_designer_block_families_sources_and_bundle()
{
	$sources = pg_designer_js_sources();
	pg_assert_same('assets/js/style_designer.src.js', $sources[0], 'the core comes first');
	$families = array_slice($sources, 1);
	$sorted = $families;
	sort($sorted, SORT_STRING);
	pg_assert_same($sorted, $families, 'family files are sorted by name');
	foreach ($families as $family) {
		pg_assert_true((bool) preg_match('#^assets/js/style_designer_blocks_[a-z0-9_]+\.js$#', $family), $family . ' is a family file');
	}
	$bundles = pg_js_bundles();
	pg_assert_same(array_merge(array('assets/js/pg_google_fonts.js'), $sources), $bundles['assets/js/style_designer.min.js'], 'the designer bundle is the fonts list, the core, then the family files');
}

function test_designer_block_families_css_reader()
{
	$part = _pg_designer_block_css_parse(designer_block_families_test_sample());
	pg_assert_same(null, $part['mark'], 'a family file has no marker of its own');
	pg_assert_same(array(
		'pg-sample-band' => '.pg-sample-band { min-height: 40vh; }',
		'pg-sample-tile' => '.pg-sample-tile { border-radius: 1.5rem; }',
	), $part['rules'], 'the sample\'s rules are read');
	pg_assert_same(array(
		'sample-band'  => array('pg-sample-band', 'pg-ratio-16x9'),
		'sample-tiles' => array('pg-sample-tile'),
	), $part['needs'], 'the sample\'s needs are read');

	$core = _pg_designer_block_css_parse(designer_block_families_test_core());
	pg_assert_same('/* Pinegrap block styles */', $core['mark'], 'the core\'s marker is read');
	$source = _pg_designer_block_css_source();
	pg_assert_same($core['mark'], $source['mark'], 'the stylesheet takes the core\'s marker');
	foreach ($core['rules'] as $name => $css) {
		pg_assert_same($css, $source['rules'][$name], 'core rule ' . $name . ' is kept');
	}
}

// Runs the core's family code with the sample under Node; null without Node.
function designer_block_families_test_run()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n"
		. "function _sdT(key, vars) { return vars === undefined ? key : String(key).replace('{var}', vars); }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_block_families_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_block_families_test_cut('    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. designer_block_families_test_cut('    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. "var coreBand = _SD_BLOCK_CSS['pg-hero-tall'];\n"
		. designer_block_families_test_sample() . "\n"
		// A second family reaching for a core rule and the sample's names.
		. "StyleDesigner.registerBlockFamily(function (kit) { return { prefix: 'features-', items: [\n"
		. "  { label: 'X', type: 'component', icon: 'bi-x', extra: { componentType: 'features-sample' } },\n"
		. "  { label: 'Y', type: 'component', icon: 'bi-x', extra: { componentType: 'sample-band' } } ],\n"
		. "  build: function (ctype) { return ctype === 'features-sample' ? kit.div('p-3', 'X', []) : null; },\n"
		. "  css: { 'pg-hero-tall': '.pg-hero-tall { min-height: 1px; }', 'pg-sample-band': '.pg-sample-band { color: red; }' } }; });\n"
		. "StyleDesigner.registerBlockFamily('not a factory');\n"
		. "StyleDesigner.registerBlockFamily(function () { return { prefix: 'broken-' }; });\n"
		. "var groups = _sdBlockPaletteGroups();\n"
		. "var band = _sdBlockFamilyOf('sample-band').build('sample-band');\n"
		. "var tiles = _sdBlockFamilyOf('sample-tiles').build('sample-tiles');\n"
		. "console.log(JSON.stringify({\n"
		. "  last: groups[groups.length - 1].title,\n"
		. "  sampleItems: groups[groups.length - 1].items.map(function (it) { return it.extra.componentType; }),\n"
		. "  marketing: groups.filter(function (g) { return g.title === 'Marketing'; })[0].items.map(function (it) { return it.extra.componentType; }).slice(-1),\n"
		. "  other: groups.some(function (g) { return g.title === 'Other'; }),\n"
		. "  families: _sdBlockFamilies().map(function (f) { return f.prefix; }),\n"
		. "  core: _sdBlockFamilyOf('hero-centered'),\n"
		. "  bandOwner: _sdBlockFamilyOf('sample-band').prefix,\n"
		. "  band: [band.type, band.props.tag, band.props.cssClass, band.children[0].type, band.children[0].children.length],\n"
		. "  tiles: tiles.children[0].children.length,\n"
		. "  unknown: _sdBlockFamilyOf('sample-band').build('sample-none'),\n"
		. "  needs: _SD_BLOCK_CSS_NEEDS['sample-band'],\n"
		. "  rule: _SD_BLOCK_CSS['pg-sample-band'],\n"
		. "  coreKept: _SD_BLOCK_CSS['pg-hero-tall'] === coreBand\n"
		. "}));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgblockfam');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return json_decode((string) end($out), true);
}

function test_designer_block_families_register_and_draw()
{
	$got = designer_block_families_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('Sample Family', $got['last'], 'the family\'s group is the palette\'s last');
	pg_assert_same(array('sample-band', 'sample-tiles'), $got['sampleItems'], 'its blocks sit in its group; a later family\'s copy of a type does not take it');
	pg_assert_same(array('features-sample'), $got['marketing'], 'a family without a group places its blocks in the core group of its prefix');
	pg_assert_false($got['other'], 'no block falls into "Other"');
	pg_assert_same(array('sample-', 'features-'), $got['families'], 'families register in load order; a non-factory and a family without a builder are left out');
	pg_assert_same(null, $got['core'], 'a core block belongs to no family');
	pg_assert_same('sample-', $got['bandOwner'], 'the first family to list a type builds it');
	pg_assert_same(array('semantic', 'section', 'py-5 pg-sample-band', 'container', 2), $got['band'], 'the band draws through the kit: a section, its container, the heading and the picture');
	pg_assert_same(2, $got['tiles'], 'the tiles block draws its intro and its row');
	pg_assert_same(null, $got['unknown'], 'a type the builder does not know draws nothing');
	pg_assert_same(array('pg-sample-band', 'pg-ratio-16x9'), $got['needs'], 'the family\'s needs join _SD_BLOCK_CSS_NEEDS');
	pg_assert_same('.pg-sample-band { min-height: 40vh; }', $got['rule'], 'the family\'s rules join _SD_BLOCK_CSS; a later family does not replace them');
	pg_assert_true($got['coreKept'], 'a family does not replace a core rule');
}

// createFromPalette() asks the family before the core's builders, after
// reading its rules; a family block dropped on the page root is not wrapped.
function test_designer_block_families_core_asks_the_family()
{
	$js = designer_block_families_test_core();
	$at = strpos($js, 'function createFromPalette(');
	$family = strpos($js, 'var _ctFamily = _sdBlockFamilyOf(ctype);', (int) $at);
	$ensure = strpos($js, '_sdEnsureBlockStyles(_SD_BLOCK_CSS_NEEDS[ctype]);', (int) $at);
	$build = strpos($js, 'var _ctFamilyNode = _ctFamily.build(ctype);', (int) $at);
	$card = strpos($js, "if (ctype === 'card') {", (int) $at);
	pg_assert_true($at !== false && $family !== false && $ensure !== false && $build !== false && $card !== false, 'createFromPalette() asks the family');
	pg_assert_true($family < $ensure && $ensure < $build && $build < $card, 'the family is read, its rules ensured, then it builds before the core builders');
	pg_assert_contains("_noWrapCtypes.indexOf(_nwCtype) === -1 && !_sdBlockFamilyOf(_nwCtype)", $js, 'a family block is not wrapped in a container');
	pg_assert_contains('registerBlockFamily: _sdRegisterBlockFamily,', $js, 'the registration call is public');
}

// Every block of every shipped family file draws under Node: a tree whose
// nodes all have a type, and whose interactions pg_fx_normalize() keeps as
// they are written (a typo in an effect or field would be dropped silently).
function test_designer_block_families_shipped_blocks_draw()
{
	$families = array_slice(pg_designer_js_sources(), 1);
	if (!$families) {
		pg_assert_true(true, 'no family file is shipped');
		return;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$script = "'use strict';\n"
		. "function _sdT(key) { return key; }\n"
		. "function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }\n"
		. designer_block_families_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_block_families_test_cut('    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. designer_block_families_test_cut('    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n";
	foreach ($families as $family) {
		$script .= (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $family) . "\n";
	}
	$script .= "var out = {};\n"
		. "_sdBlockFamilies().forEach(function (fam) { fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, node = fam.build(ct), fx = [], untyped = 0;\n"
		. "  (function walk(n) { if (!n || !n.type) { untyped++; return; } if (n.props._fx) fx.push(n.props._fx); (n.children || []).forEach(walk); })(node);\n"
		. "  out[ct] = { drawn: !!node, untyped: untyped, fx: fx };\n"
		. "}); });\n"
		. "console.log(JSON.stringify(out));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgblockship');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$got = json_decode((string) end($out), true);
	pg_assert_true(is_array($got) && count($got) > 0, 'the shipped family blocks are drawn');
	foreach (is_array($got) ? $got : array() as $ctype => $block) {
		pg_assert_true($block['drawn'], $ctype . ' draws');
		pg_assert_same(0, $block['untyped'], $ctype . ': nodes without a type');
		foreach ($block['fx'] as $fx) {
			$kept = pg_fx_normalize($fx);
			pg_assert_true(is_array($kept), $ctype . ': ' . json_encode($fx) . ' is an interaction');
			foreach ($fx as $field => $value) {
				pg_assert_true(is_array($kept) && array_key_exists($field, $kept) && $kept[$field] === $value, $ctype . ': ' . $field . ' of ' . json_encode($fx) . ' is kept as written');
			}
		}
	}
}

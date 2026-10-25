<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lists inside a record in the designer (style_designer.js, run
 * under Node): the editor's list sources, kinds and settings rule against
 * the server's (includes/fn/widgets_lists.php), the lists a node is drawn
 * in and the row tokens Bind Data offers there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_lists_editor_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// The text of a `var NAME = <open> ... <close>;` block of style_designer.js
// at its four-space indent.
function _widget_lists_editor_js_var($source, $name, $open = '[')
{
	$from = strpos($source, "\n    var " . $name . ' = ' . $open);
	if ($from === false) {
		return '';
	}
	$close = ($open === '{') ? "\n    };\n" : "\n    ];\n";
	$to = strpos($source, $close, $from);
	return ($to === false) ? '' : substr($source, $from, $to + strlen($close) - $from);
}

// A function of style_designer.js, cut out at its four-space indent.
function _widget_lists_editor_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// Runs $script after the editor's list code under Node and returns what it
// printed as JSON; null when Node is not at hand. $stubs come first.
function _widget_lists_editor_run_js($script, $stubs = '')
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

	$js = _widget_lists_editor_js();
	$cut = _widget_lists_editor_js_var($js, 'SW_LIST_SOURCES', '{') . _widget_lists_editor_js_var($js, 'SW_LIST_ORDERS');
	pg_assert_true(strpos($cut, 'SW_LIST_SOURCES') !== false && strpos($cut, 'SW_LIST_ORDERS') !== false, 'the list tables are found');
	foreach (array('_sdT', '_sdListIsSourced', '_sdListSpec', '_sdKindLists', '_sdListsAbove', '_sdListRowOptions', '_sdListRowLabel') as $name) {
		$fn = _widget_lists_editor_js_function($js, $name);
		pg_assert_true($fn !== '', $name . ' is found');
		$cut .= $fn;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pglist');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, $stubs . $cut . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));
	return $answer;
}

// A palette named SW_*TOKEN* is read as offered tokens by
// tools/check_bindings.php; the list tables must not be.
function test_widget_lists_editor_tables_are_not_palettes()
{
	pg_assert_true(!preg_match('/var SW_LIST[A-Z_]*TOKEN/', _widget_lists_editor_js()), 'no SW_LIST*TOKEN* name');
}

// The editor knows the server's sources: their names, labels, arg, whether
// a list without it draws nothing, their row tokens and the kinds that
// offer them.
function test_widget_lists_editor_sources_match_the_server()
{
	$kinds = array('form_list_view', 'form_item_view', 'catalog_listing', 'comments', 'custom_form', 'calendar_view', '');
	$a = _widget_lists_editor_run_js(
		'var kinds = ' . json_encode($kinds) . ";\n"
		. "var out = { sources: {}, kinds: {} };\n"
		. "Object.keys(SW_LIST_SOURCES).forEach(function (s) { var d = SW_LIST_SOURCES[s]; out.sources[s] = { label: d.label, arg: d.arg, arg_required: d.argRequired, tokens: d.rows }; });\n"
		. "kinds.forEach(function (k) { out.kinds[k] = _sdKindLists(k); });\n"
		. "console.log(JSON.stringify(out));"
	);
	if ($a === null) {
		return;
	}
	$php = array();
	foreach (pg_sw_list_sources() as $source => $d) {
		$php[$source] = array('label' => $d['label'], 'arg' => $d['arg'], 'arg_required' => $d['arg_required'], 'tokens' => $d['tokens']);
	}
	pg_assert_same($php, $a['sources']);
	foreach ($kinds as $kind) {
		pg_assert_same(array_keys(pg_sw_kind_lists($kind)), $a['kinds'][$kind], 'kind ' . $kind);
	}
}

// The editor reads a list's settings as the server does: the same lists
// draw, the same are refused, with the same field, limit and order.
function test_widget_lists_editor_spec_matches_the_server()
{
	$cases = array(
		'{}',
		'{"source":""}',
		'{"source":5}',
		'{"source":"form_record.values"}',
		'{"source":"form_record.values","sourceArg":"color"}',
		'{"source":"form_record.values","sourceArg":"co lor!"}',
		'{"source":"form_record.values","sourceArg":"publish-date"}',
		'{"source":"form_record.values","sourceArg":12}',
		'{"source":"form_record.values","sourceArg":true}',
		'{"source":"form_record.values","sourceArg":["a"]}',
		'{"source":"form_record.split","sourceArg":"tags","limit":5,"order":"az"}',
		'{"source":"form_record.files"}',
		'{"source":"form_record.files","limit":"7","order":"reverse"}',
		'{"source":"form_record.files","limit":100,"order":"za"}',
		'{"source":"form_record.files","limit":101}',
		'{"source":"form_record.files","limit":-1}',
		'{"source":"form_record.files","limit":2.5}',
		'{"source":"form_record.files","limit":3.0}',
		'{"source":"form_record.files","limit":null,"order":null}',
		'{"source":"form_record.files","limit":""}',
		'{"source":"form_record.files","limit":"0100"}',
		'{"source":"form_record.files","limit":"abc"}',
		'{"source":"form_record.files","limit":true}',
		'{"source":"form_record.files","order":"AZ"}',
		'{"source":"form_record.files","order":5}',
		'{"source":"form_record.nope","sourceArg":"x"}',
		'{"source":"Form_record.values","sourceArg":"x"}',
	);
	$a = _widget_lists_editor_run_js(
		'var cases = ' . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return _sdListSpec(JSON.parse(c)); })));"
	);
	if ($a === null) {
		return;
	}
	foreach ($cases as $i => $case) {
		pg_assert_same(pg_sw_list_spec(json_decode($case, true)), $a[$i], $case);
	}
}

// A list is a loop_area with a non-empty string source, on both sides.
function test_widget_lists_editor_is_sourced_matches_the_server()
{
	$nodes = array(
		array('type' => 'loop_area', 'props' => array('source' => 'form_record.values'), 'children' => array()),
		array('type' => 'loop_area', 'props' => array('source' => 'anything.else'), 'children' => array()),
		array('type' => 'loop_area', 'props' => array('source' => ''), 'children' => array()),
		array('type' => 'loop_area', 'props' => array(), 'children' => array()),
		array('type' => 'loop_area', 'props' => array('source' => 1), 'children' => array()),
		array('type' => 'semantic', 'props' => array('source' => 'form_record.values'), 'children' => array()),
		array('type' => 'loop_area'),
	);
	$a = _widget_lists_editor_run_js(
		'var nodes = ' . json_encode($nodes) . ";\n"
		. "console.log(JSON.stringify(nodes.map(function (n) { return _sdListIsSourced(n); })));"
	);
	if ($a === null) {
		return;
	}
	$php = array();
	foreach ($nodes as $node) {
		$php[] = pg_sw_list_is_sourced($node);
	}
	pg_assert_same($php, $a);
}

// Inside lists Bind Data offers the row tokens of every list the node is
// drawn in, the nearest list's first and each token once; the main loop
// and the widget's root end the walk, a list's own node is not inside it.
function test_widget_lists_editor_row_options_follow_the_lists_above()
{
	$stubs = <<<'JS'
var span  = { type: 'semantic', props: { tag: 'span' }, children: [] };
var files = { type: 'loop_area', props: { source: 'form_record.files' }, children: [span] };
var inner = { type: 'semantic', props: { tag: 'div' }, children: [files] };
var vals  = { type: 'loop_area', props: { source: 'form_record.values', sourceArg: 'color' }, children: [inner] };
var cell  = { type: 'semantic', props: { tag: 'p' }, children: [] };
var main  = { type: 'loop_area', props: {}, children: [vals, cell] };
var outer = { type: 'loop_area', props: { source: 'form_record.split', sourceArg: 'tags' }, children: [main] };
var root  = { type: 'root', props: {}, children: [outer] };
function _sdAnyParent(n) {
    var hit = null;
    (function walk(p) {
        if (hit || !p || !p.children) return;
        p.children.forEach(function (c) { if (c === n) hit = { parent: p }; else walk(c); });
    })(root);
    return hit;
}
JS;
	$script = <<<'JS'
var tokens = function (n) { return _sdListRowOptions(n).map(function (o) { return o.token; }); };
console.log(JSON.stringify({
    span: tokens(span),
    inner: tokens(inner),
    cell: tokens(cell),
    vals: tokens(vals),
    above: _sdListsAbove(span).map(function (l) { return l.props.source; }),
    label: _sdListRowLabel('__file_ext'),
    noLabel: _sdListRowLabel('__name')
}));
JS;
	$a = _widget_lists_editor_run_js($script, $stubs);
	if ($a === null) {
		return;
	}
	pg_assert_same(array('__file_name', '__file_url', '__file_field', '__file_ext', '__value_text', '__value_param'), $a['span']);
	pg_assert_same(array('__value_text', '__value_param'), $a['inner']);
	pg_assert_same(array(), $a['cell'], 'the main loop ends the walk');
	pg_assert_same(array(), $a['vals'], 'a list is not inside itself');
	pg_assert_same(array('form_record.files', 'form_record.values'), $a['above']);
	pg_assert_same('File type', $a['label']);
	pg_assert_same('', $a['noLabel']);
}

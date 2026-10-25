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
	$cut = _widget_lists_editor_js_var($js, 'SW_LIST_SOURCES', '{') . _widget_lists_editor_js_var($js, 'SW_LIST_ORDERS')
		. "\n    var SW_LIST_MAX_DEPTH = 4;\n";
	pg_assert_true(strpos($js, "\n    var SW_LIST_MAX_DEPTH = 4;\n") !== false, 'the depth is four, as the site draws');
	pg_assert_true(strpos($cut, 'SW_LIST_SOURCES') !== false && strpos($cut, 'SW_LIST_ORDERS') !== false, 'the list tables are found');
	foreach (array('_sdT', '_sdListIsSourced', '_sdListSpec', '_sdKindLists', '_sdListsAbove', '_sdListRowOptions', '_sdListRowLabel',
		'_sdListName', '_sdListStarter', '_sdListWidgetKind', '_sdInMainLoopAt', '_sdListNesting', '_sdDragIsList', '_sdListPlaceOk',
		'_sdListWrite', '_sdListFieldOf', '_sdListFieldFits', '_sdListBand', 'isDescendantOf', '_subtreeContainsLoopArea', '_sdFirstLoopArea') as $name) {
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

// Trees of three widgets for the placement and band tests: a form list
// (5), a form detail view (6) holding four lists in lists, a catalog
// listing (7). _isInsideSystemWidget() and _sdAnyParent() read them.
function _widget_lists_editor_trees_js()
{
	return <<<'JS'
var _sharedCache = {}, _formFieldsCache = {}, _sdLabelFieldsAsked = {}, FETCHED = [];
var id = 0;
function N(type, props, children) { return { _id: 'n' + (++id), type: type, props: props || {}, children: children || [] }; }
var span5 = N('semantic', { tag: 'span' });
var list5 = N('loop_area', { source: 'form_record.values', sourceArg: 'color' }, [span5]);
var odd5  = N('loop_area', { source: 'form_record.nope' }, []);
var card5 = N('semantic', { tag: 'div' }, [list5, odd5]);
var main5 = N('loop_area', {}, [card5]);
var head5 = N('semantic', { tag: 'h2' });
var out5  = N('loop_area', { source: 'form_record.files' }, []);
var root5 = N('root', {}, [head5, out5, main5]);
var leaf6 = N('semantic', { tag: 'span' });
var l4 = N('loop_area', { source: 'form_record.files' }, [leaf6]);
var l3 = N('loop_area', { source: 'form_record.split', sourceArg: 'tags' }, [l4]);
var l2 = N('loop_area', { source: 'form_record.values', sourceArg: 'color' }, [l3]);
var l1 = N('loop_area', { source: 'form_record.files' }, [l2]);
var box6  = N('semantic', { tag: 'div' });
var bad6  = N('loop_area', { source: 'form_record.values', sourceArg: 'cv' }, []);
var nof6  = N('loop_area', { source: 'form_record.values', sourceArg: 'gone' }, []);
var root6 = N('root', {}, [box6, l1, bad6, nof6]);
var card7 = N('semantic', { tag: 'div' });
var cat7  = N('loop_area', { source: 'form_record.files' }, []);
var root7 = N('root', {}, [N('loop_area', {}, [card7, cat7])]);
_sharedCache[5] = { tree: root5, system_region_config: JSON.stringify({ regionType: 'blog_list', custom_form_page_id: 40 }) };
_sharedCache[6] = { tree: root6, system_region_config: JSON.stringify({ regionType: 'form_item_view', custom_form_page_id: 40 }) };
_sharedCache[7] = { tree: root7, system_region_config: JSON.stringify({ regionType: 'catalog_listing' }) };
function _inTree(n, t) { if (t === n) return true; return (t.children || []).some(function (c) { return _inTree(n, c); }); }
function _isInsideSystemWidget(n) {
    var ks = Object.keys(_sharedCache);
    for (var i = 0; i < ks.length; i++) if (_inTree(n, _sharedCache[ks[i]].tree)) return parseInt(ks[i], 10);
    return 0;
}
function _sdAnyParent(n) {
    var hit = null;
    Object.keys(_sharedCache).forEach(function (k) {
        (function walk(p) {
            if (hit || !p || !p.children) return;
            p.children.forEach(function (c) { if (c === n) hit = { parent: p }; else walk(c); });
        })(_sharedCache[k].tree);
    });
    return hit;
}
function _getSystemWidgetCustomFormPageId(n) { var c = _sharedCache[_isInsideSystemWidget(n)]; return c ? (JSON.parse(c.system_region_config).custom_form_page_id || 0) : 0; }
function _fetchFormFields(id, cb) { FETCHED.push(id); }
function renderCanvas() {}
function palette(source) { return { source: 'palette', type: 'loop_area', extra: { listSource: source } }; }
function move(node) { return { source: 'node', node: node, type: node.type }; }
JS;
}

// A list lands where a record fills it: in a form list inside the main
// loop, anywhere in a form detail view, never in a kind without lists,
// never inside itself, at most four lists deep; a list of a source the
// editor does not know moves only inside its own widget.
function test_widget_lists_editor_placement()
{
	$script = <<<'JS'
console.log(JSON.stringify({
    inCard:        _sdListPlaceOk(palette('form_record.values'), card5),
    inMain:        _sdListPlaceOk(palette('form_record.split'), main5),
    inHeading:     _sdListPlaceOk(palette('form_record.values'), head5),
    onListRoot:    _sdListPlaceOk(palette('form_record.values'), root5),
    detailBox:     _sdListPlaceOk(palette('form_record.files'), box6),
    detailRoot:    _sdListPlaceOk(palette('form_record.files'), root6),
    catalog:       _sdListPlaceOk(palette('form_record.files'), card7),
    unknown:       _sdListPlaceOk(palette('form_record.nope'), card5),
    intoItself:    _sdListPlaceOk(move(list5), span5),
    toOtherWidget: _sdListPlaceOk(move(list5), box6),
    fifthLevel:    _sdListPlaceOk(palette('form_record.files'), leaf6),
    fourthLevel:   _sdListPlaceOk(palette('form_record.files'), l3),
    deepMoveTop:   _sdListPlaceOk(move(l1), box6),
    deepMoveIn:    _sdListPlaceOk(move(l1), span5),
    oddHome:       _sdListPlaceOk(move(odd5), main5),
    oddAway:       _sdListPlaceOk(move(odd5), box6),
    drags: [
        _sdDragIsList(palette('form_record.values')),
        _sdDragIsList({ source: 'palette', type: 'loop_area', extra: {} }),
        _sdDragIsList(move(main5)),
        _sdDragIsList(move(list5)),
        _sdDragIsList(null)
    ],
    kinds: [_sdListWidgetKind(5), _sdListWidgetKind(6), _sdListWidgetKind(7), _sdListWidgetKind(0)],
    nesting: [_sdListNesting(l1), _sdListNesting(root5), _sdListNesting(span5)]
}));
JS;
	$a = _widget_lists_editor_run_js($script, _widget_lists_editor_trees_js());
	if ($a === null) {
		return;
	}
	pg_assert_true($a['inCard'], 'in the record of a form list');
	pg_assert_true($a['inMain'], 'in the main loop itself');
	pg_assert_true(!$a['inHeading'], 'outside the record of a form list');
	pg_assert_true(!$a['onListRoot'], 'on a form list\'s root');
	pg_assert_true($a['detailBox'], 'anywhere in a form detail view');
	pg_assert_true($a['detailRoot'], 'on a form detail view\'s root');
	pg_assert_true(!$a['catalog'], 'a kind without lists');
	pg_assert_true(!$a['unknown'], 'an unknown source from the palette');
	pg_assert_true(!$a['intoItself'], 'into itself');
	pg_assert_true($a['toOtherWidget'], 'into another form widget');
	pg_assert_true(!$a['fifthLevel'], 'a fifth level');
	pg_assert_true($a['fourthLevel'], 'a fourth level');
	pg_assert_true($a['deepMoveTop'], 'four levels moved to the top');
	pg_assert_true(!$a['deepMoveIn'], 'four levels moved into a list');
	pg_assert_true($a['oddHome'], 'an unknown source inside its own widget');
	pg_assert_true(!$a['oddAway'], 'an unknown source into another widget');
	pg_assert_same(array(true, false, false, true, false), $a['drags']);
	pg_assert_same(array('form_list_view', 'form_item_view', 'catalog_listing', ''), $a['kinds']);
	pg_assert_same(array(4, 1, 0), $a['nesting']);
}

// The editor matches a list's field as the site does and offers a field
// for a source only when the site gives that source rows from it.
function test_widget_lists_editor_fields_match_the_server()
{
	$fields = array(
		array('id' => 1, 'name' => 'color', 'label' => 'Favourite Colour', 'type' => 'check box', 'wysiwyg' => '0'),
		array('id' => 2, 'name' => 'publish-date', 'label' => 'Date', 'type' => 'date', 'wysiwyg' => '0'),
		array('id' => 3, 'name' => 'Bio', 'label' => 'About', 'type' => 'text area', 'wysiwyg' => '1'),
		array('id' => 4, 'name' => 'cv', 'label' => 'CV', 'type' => 'file upload', 'wysiwyg' => '0'),
		array('id' => 5, 'name' => 'notes', 'label' => 'notes2', 'type' => 'text area', 'wysiwyg' => '0'),
	);
	$args = array('color', 'COLOR', 'publishdate', 'publish-date', 'date', 'bio', 'about', 'cv', 'favouritecolour', 'notes2', 'x');
	$a = _widget_lists_editor_run_js(
		'var fields = ' . json_encode($fields) . '; var args = ' . json_encode($args) . ";\n"
		. "console.log(JSON.stringify({\n"
		. "  match: args.map(function (a) { return fields.map(function (f) { return _sdListFieldOf([f], a) !== null; }); }),\n"
		. "  values: fields.map(function (f) { return _sdListFieldFits('form_record.values', f); }),\n"
		. "  files: fields.map(function (f) { return _sdListFieldFits('form_record.files', f); }),\n"
		. "  none: [_sdListFieldOf(fields, ''), _sdListFieldOf(null, 'cv')]\n"
		. "}));"
	);
	if ($a === null) {
		return;
	}
	$data_rows = array();
	foreach ($fields as $f) {
		$data_rows[] = array('form_id' => 1, 'data_id' => $f['id'], 'field_name' => $f['name'], 'field_label' => $f['label'],
			'field_type' => $f['type'], 'field_wysiwyg' => $f['wysiwyg'], 'field_office_use' => '0', 'data' => 'red',
			'file_name' => ($f['type'] === 'file upload') ? 'cv.pdf' : '');
	}
	$records = pg_sw_list_form_records($data_rows);
	$rows = $records[1];
	$match = array();
	foreach ($args as $arg) {
		$m = array();
		foreach ($rows as $row) {
			$m[] = _pg_sw_list_names_field($row, $arg);
		}
		$match[] = $m;
	}
	pg_assert_same($match, $a['match']);
	$values = array();
	$files = array();
	foreach ($rows as $row) {
		$values[] = count(pg_sw_list_value_rows(array($row), $row['name'])) > 0;
		$files[] = count(pg_sw_list_file_rows(array($row), $row['name'])) > 0;
	}
	pg_assert_same($values, $a['values']);
	pg_assert_same($files, $a['files']);
	pg_assert_same(array(null, null), $a['none']);
}

// A palette list lands without its field around one bound row; the
// writer keeps the tree to what the site reads and marks the widget; a
// list is neither the main loop nor protected like it.
function test_widget_lists_editor_starter_writer_and_main_loop()
{
	$stubs = <<<'JS'
var DIRTY = [];
function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }
function _sdUsesBootstrap() { return true; }
function _sdSharedMarkEdited(n) { DIRTY.push(n.props.source); return 1; }
JS;
	$script = <<<'JS'
var v = _sdListStarter('form_record.values'), f = _sdListStarter('form_record.files');
var w = { type: 'loop_area', props: { source: 'form_record.values', sourceArg: 'color' }, children: [] };
var steps = [];
var snap = function () { steps.push(JSON.parse(JSON.stringify(w.props))); };
_sdListWrite(w, 'source', 'form_record.split'); snap();
_sdListWrite(w, 'source', 'form_record.files'); snap();
_sdListWrite(w, 'source', 'nope.nope'); snap();
_sdListWrite(w, 'sourceArg', 'cv'); snap();
_sdListWrite(w, 'sourceArg', ''); snap();
_sdListWrite(w, 'limit', '150'); snap();
_sdListWrite(w, 'limit', '7'); snap();
_sdListWrite(w, 'limit', '0'); snap();
_sdListWrite(w, 'order', 'az'); snap();
_sdListWrite(w, 'order', 'bogus'); snap();
var main = { type: 'loop_area', props: {}, children: [] };
var list = { type: 'loop_area', props: { source: 'form_record.files' }, children: [main] };
console.log(JSON.stringify({
    v: v, f: f, steps: steps, dirty: DIRTY.length,
    names: [_sdListName(v), _sdListName({ props: { source: 'x.y' } })],
    protect: [
        _subtreeContainsLoopArea(list),
        _subtreeContainsLoopArea({ type: 'semantic', props: {}, children: [list] }),
        _subtreeContainsLoopArea({ type: 'semantic', props: {}, children: [main] })
    ],
    first: _sdFirstLoopArea({ type: 'root', props: {}, children: [{ type: 'loop_area', props: { source: 'form_record.values' }, children: [] }, main] }) === main
}));
JS;
	$a = _widget_lists_editor_run_js($script, $stubs);
	if ($a === null) {
		return;
	}
	pg_assert_same(array('source' => 'form_record.values'), $a['v']['props']);
	pg_assert_same('span', $a['v']['children'][0]['props']['tag']);
	pg_assert_same(array('text' => '__value_text'), $a['v']['children'][0]['props']['_bindings']);
	pg_assert_same('a', $a['f']['children'][0]['props']['tag']);
	pg_assert_same('_blank', $a['f']['children'][0]['props']['target']);
	pg_assert_same(array('href' => '__file_url', 'text' => '__file_name'), $a['f']['children'][0]['props']['_bindings']);
	pg_assert_same(false, pg_sw_list_spec($a['v']['props']), 'a new values list waits for its field');
	pg_assert_true(is_array(pg_sw_list_spec($a['f']['props'])), 'a new attachments list draws at once');
	pg_assert_same(array(
		array('source' => 'form_record.split', 'sourceArg' => 'color'),
		array('source' => 'form_record.files'),
		array('source' => 'form_record.files'),
		array('source' => 'form_record.files', 'sourceArg' => 'cv'),
		array('source' => 'form_record.files'),
		array('source' => 'form_record.files', 'limit' => 100),
		array('source' => 'form_record.files', 'limit' => 7),
		array('source' => 'form_record.files'),
		array('source' => 'form_record.files', 'order' => 'az'),
		array('source' => 'form_record.files'),
	), $a['steps']);
	pg_assert_same(9, $a['dirty'], 'every write but the refused source marks the widget');
	pg_assert_same(array('☰ Values of a field', '☰ List'), $a['names']);
	pg_assert_same(array(false, false, true), $a['protect']);
	pg_assert_true($a['first'], 'the main loop is the first loop area without a source');
}

// The band over a list says what it lists or why the site draws nothing,
// and the canvas fades the list then.
function test_widget_lists_editor_band()
{
	$script = <<<'JS'
var band = function (n) { var b = _sdListBand(n); return [b.off, b.text]; };
var before = band(list5);
_formFieldsCache[40] = [
    { name: 'color', label: 'Colour', type: 'check box', wysiwyg: '0' },
    { name: 'tags', label: 'Tags', type: 'text box', wysiwyg: '0' },
    { name: 'cv', label: 'CV', type: 'file upload', wysiwyg: '0' }
];
console.log(JSON.stringify({
    before: before, fetched: FETCHED,
    ok: band(list5),
    unknown: band(odd5),
    outside: band(out5),
    catalog: band(cat7),
    noField: band({ _id: 'z', type: 'loop_area', props: { source: 'form_record.split' }, children: [] }),
    badSettings: band({ _id: 'y', type: 'loop_area', props: { source: 'form_record.files', limit: 500 }, children: [] }),
    wrongField: band(bad6),
    goneField: band(nof6),
    deep: band(l4),
    files: band(l1)
}));
JS;
	$a = _widget_lists_editor_run_js($script, _widget_lists_editor_trees_js());
	if ($a === null) {
		return;
	}
	pg_assert_same(array(false, 'Values of a field · color — one row shown (repeated for every row at runtime)'), $a['before'], 'the arg while the fields load');
	pg_assert_same(array(40), $a['fetched'], 'the fields are asked for once');
	pg_assert_same(array(false, 'Values of a field · Colour — one row shown (repeated for every row at runtime)'), $a['ok']);
	pg_assert_same(array(true, 'A list of a kind this site does not know: nothing is drawn'), $a['unknown']);
	pg_assert_same(array(true, 'Attachments: outside the record, no record fills it'), $a['outside']);
	pg_assert_same(array(true, 'Attachments: this widget draws no such list'), $a['catalog']);
	pg_assert_same(array(true, 'Parts of a field, split at commas: choose the field it lists'), $a['noField']);
	pg_assert_same(array(true, 'Attachments: settings the site cannot read, nothing is drawn'), $a['badSettings']);
	pg_assert_same(array(true, 'Values of a field: the field cv gives this list no rows'), $a['wrongField']);
	pg_assert_same(array(true, 'Values of a field: the field gone gives this list no rows'), $a['goneField']);
	pg_assert_same(array(false, 'Attachments — one row shown (repeated for every row at runtime)'), $a['deep'], 'the fourth level draws');
	pg_assert_same(array(false, 'Attachments — one row shown (repeated for every row at runtime)'), $a['files']);
}

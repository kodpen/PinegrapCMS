<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a widget part placed outside its detail widget (props.partOf)
 * on a page tree: the save validator (_validate_and_clean_tree_json())
 * takes a part holding one of its widget's lists, refuses in translated
 * sentences, and the designer's panels read the part's widget
 * (_sdPartSidAt(), run under Node), as the canvas draws it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _dpt_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function _dpt_check($tree)
{
	return _validate_and_clean_tree_json(json_encode($tree));
}

// A Breadcrumb part (a nav holding the catalog.crumbs list) and a
// Cross-sell part beside a heading pass the page save.
function test_designer_part_tree_list_part_saves()
{
	// The builders give the part's nodes; the palette wraps several in one div.
	$crumbs = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => pg_sw_part_catalog_breadcrumb_item());
	pg_assert_true(is_array($crumbs) && strpos(json_encode($crumbs), '"loop_area"') !== false, 'the breadcrumb part holds a list');
	$crumbs['props']['partOf'] = 12;
	$cross = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => pg_sw_part_catalog_cross_sell());
	pg_assert_true(is_array($cross) && strpos(json_encode($cross), '"loop_area"') !== false, 'the cross-sell part holds a list');
	$cross['props']['partOf'] = '12';
	$heading = array('type' => 'semantic', 'props' => array('tag' => 'h1', 'text' => 'Shop'), 'children' => array());
	$res = _dpt_check(_dpt_root(array($heading, $crumbs, $cross)));
	pg_assert_same('', $res['error']);
	pg_assert_true($res['ok'], 'a page with list parts saves');
	pg_assert_same(json_decode(json_encode(_dpt_root(array($heading, $crumbs, $cross))), true), json_decode($res['cleaned_json'], true));
}

// A list outside every part, or a part's list without a source (a main
// loop), is refused; the refusal is a translated sentence, not a node type.
function test_designer_part_tree_refusals()
{
	$loop = array('type' => 'loop_area', 'props' => array('source' => 'catalog.crumbs'), 'children' => array());
	$res = _dpt_check(_dpt_root(array($loop)));
	pg_assert_true(!$res['ok'], 'a list outside a part is refused');
	pg_assert_true(strpos($res['error'], 'Invalid node type') === false, 'no technical text: ' . $res['error']);
	pg_assert_true(strpos($res['error'], 'loop_area') === false, 'no node type name: ' . $res['error']);

	$part = array('type' => 'semantic', 'props' => array('tag' => 'div', 'partOf' => 12), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array()),
	));
	$res = _dpt_check(_dpt_root(array($part)));
	pg_assert_true(!$res['ok'], 'a main loop in a part is refused');
	pg_assert_true(strpos($res['error'], 'loop_area') === false, 'no node type name: ' . $res['error']);

	$odd = array('type' => 'mystery', 'props' => array(), 'children' => array());
	$res = _dpt_check(_dpt_root(array($odd)));
	pg_assert_true(!$res['ok'], 'an unknown node is refused');
	pg_assert_true(strpos($res['error'], 'Invalid node type') === false, 'no technical text: ' . $res['error']);

	$bad = array('type' => 'semantic', 'props' => array(), 'children' => array());
	$res = _dpt_check(_dpt_root(array($bad)));
	pg_assert_true(!$res['ok'], 'an element without its tag is refused');
	pg_assert_true(strpos($res['error'], 'missing') === false, 'no technical text: ' . $res['error']);
}

// Every refusal of the walk is a translated text.
function test_designer_part_tree_refusals_translated()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$start = strpos($src, "\nfunction _vt_walk(");
	$end = ($start === false) ? false : strpos($src, "\n}\n", $start);
	pg_assert_true($start !== false && $end !== false, '_vt_walk() is found');
	$body = substr($src, $start, $end - $start);
	preg_match_all('/\$errors\[\]\s*=\s*([^;]+);/', $body, $m);
	pg_assert_true(count($m[1]) > 0, 'the walk writes errors');
	foreach ($m[1] as $expr) {
		pg_assert_true(strpos(ltrim($expr), 'lang(') === 0 || strpos(ltrim($expr), '_vt_error(') === 0, 'translated: ' . $expr);
	}
}

function _dpt_js_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start + 6);
}

// $script run under Node after the part helpers and stubs: its JSON answer,
// or null when Node is missing.
function _dpt_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = "var SD_PART_KINDS = ['catalog_item_view', 'form_item_view', 'calendar_event_view'];\n";
	foreach (array('_sdPartOf', '_sdPartRefusal', '_sdPartSidAt') as $fn) {
		$body = _dpt_js_fn($js, $fn);
		pg_assert_true($body !== '', $fn . '() is found');
		$code_js .= $body . "\n";
	}
	$file = tempnam(sys_get_temp_dir(), 'pgdpt');
	file_put_contents($file, $code_js . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	return json_decode(implode("\n", $out), true);
}

// The panels of a part (and of an element inside it) read the part's
// widget; a node of no part, a node in a shared tree and a part whose
// widget the page does not show read none.
function test_designer_part_tree_panels_read_the_parts_widget()
{
	$res = _dpt_node(<<<'JS'
var SD_PART_REFUSED_TAGS = ['form', 'input', 'select', 'textarea', 'button', 'option', 'script'];
var SD_PART_REFUSED_CONTENT = ['messages'];
var price = { type: 'semantic', props: { tag: 'p', _bindings: { text: '__price_formatted' } }, children: [] };
var part = { type: 'semantic', props: { tag: 'div', partOf: 12 }, children: [price] };
var lost = { type: 'semantic', props: { tag: 'div', partOf: 99 }, children: [] };
var plain = { type: 'semantic', props: { tag: 'p' }, children: [] };
var inner = { type: 'semantic', props: { tag: 'p', partOf: 12 }, children: [] };
var tree = { type: 'root', props: {}, children: [part, lost, plain] };
var _sharedCache = { 12: { name: 'Product', system_region_config: '{}', tree: { type: 'root', children: [inner] } } };
function findParent(node, root) {
    var hit = null;
    (function walk(n) { (n.children || []).forEach(function (c) { if (c === node) hit = n; else if (!hit) walk(c); }); })(root);
    return hit;
}
function _resolveSharedSid(node) { return node === inner ? 12 : 0; }
function _sdPlacementOnPage(sid) { return sid === 12 ? {} : null; }
function _sdPartKindOf(sid) { return sid === 12 ? 'catalog_item_view' : ''; }
function _sdPartInfo(node) {
    var sid = _sdPartOf(node);
    if (!sid) return null;
    var state = _resolveSharedSid(node) ? 'inside' : (!_sharedCache[sid] || !_sdPlacementOnPage(sid) ? 'gone' : 'ok');
    return { sid: sid, state: state };
}
console.log(JSON.stringify([part, price, lost, plain, inner, tree, null].map(_sdPartSidAt)));
JS
	);
	if ($res === null) {
		return;
	}
	pg_assert_same(array(12, 12, 0, 0, 0, 0, 0), $res);
}

// The Bind Data and condition panels and the list band ask for the part's
// widget; a binding change on a part still marks only the tree it is in.
function test_designer_part_tree_panels_wired()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos(_dpt_js_fn($js, '_propsDataBindingCore'), '_sdPartSidAt(node)') !== false, 'Bind Data reads the part\'s widget');
	pg_assert_true(strpos(_dpt_js_fn($js, '_sdCondTokenOptions'), '_sdPartSidAt(node)') !== false, 'conditions read the part\'s widget');
	pg_assert_true(strpos(_dpt_js_fn($js, '_sdConditionRows'), '_sdPartSidAt(node)') !== false, 'the condition note reads the part\'s widget');
	pg_assert_true(strpos(_dpt_js_fn($js, '_sdListBand'), '_sdPartSidAt(node)') !== false, 'the list band reads the part\'s widget');
	pg_assert_true(strpos(_dpt_js_fn($js, '_sdListBandIn'), '_sdValueSidOf(node)') !== false, 'the list band reads the drawn widget');
	pg_assert_true(strpos(_dpt_js_fn($js, '_sdNodeSetBinding'), 'var sid = _isInsideSystemWidget(node);') !== false, 'a binding marks only the widget it stands in');
}

// The Bind Data panel of a part placed outside its widget draws the widget's
// "Show only when" flag row, the Format row and "Hide when empty", read from
// the part's widget; the action and field rows stay widget-only. A node of
// no part gets none of them.
function test_designer_part_tree_panel_draws_flag_format_and_hide_empty()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = "var _sdPartDrawing = 0;\n";
	foreach (array('propsDataBinding', '_sdBindingActionRows', '_sdVisibilityFlagRows', '_sdFormatRows', '_sdFormatRowsIn',
		'_sdFillKindOf', '_sdHideEmptyRows', '_sdHideEmptyRowsIn', '_sdInWidgetScope', '_sdWithValueSid', '_sdValueSidOf',
		'_sdParseFilter') as $fn) {
		$body = _dpt_js_fn($js, $fn);
		pg_assert_true($body !== '', $fn . '() is found');
		$code_js .= $body . "\n";
	}
	$code_js .= <<<'JS'
var title = { type: 'content', props: { contentType: 'heading', tag: 'h1', partOf: 12,
    _bindings: { text: '__short_description', eo_visible_if: 'no_rating' }, _bindFormats: { text: 'upper' } }, children: [] };
var btn = { type: 'semantic', props: { tag: 'button' }, children: [] };
var part = { type: 'semantic', props: { tag: 'div', partOf: 12 }, children: [btn] };
var plain = { type: 'content', props: { contentType: 'heading', tag: 'h1', _bindings: { text: '__short_description' } }, children: [] };
var tree = { type: 'root', props: {}, children: [title, part, plain] };
var _sharedCache = { 12: { system_region_config: '{"regionType":"catalog_item_view"}' } };
var SW_VISIBILITY_FLAGS = { catalog_item_view: [['has_rating', 'Has a rating'], ['no_rating', 'No rating yet']] };
var SW_DATE_FIELD_TOKENS = {};
var _SD_FILTER_KINDS = { catalog_item_view: true };
var _SD_TEXT_FILTERS = [['', 'As it is'], ['upper', 'UPPER CASE']];
var _SD_FILTER_ARG = {};
var _SD_PARSE_FILTERS = null;
var SD_HIDE_EMPTY = 'pg-hide-if-empty', SD_FIELD_VALUE = 'pg-field-value';
function _sdT(s) { return s; }
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
function row(label, html) { return '<div class="row">' + label + html + '</div>'; }
function sect(icon, title, html) { return '<div class="sect">' + title + '<div>' + html + '</div></div>'; }
function swAttr(attr, on) { return '<input type="checkbox" ' + attr + (on ? ' checked' : '') + '>'; }
function _isInsideSystemWidget(node) { return 0; }
function _sdPartOf(node) { return (node && node.props && node.props.partOf) || 0; }
function _sdPartSidAt(node) { return (node === title || node === part || node === btn) ? 12 : 0; }
function _getSystemWidgetRegionType(node) { return _sdValueSidOf(node) === 12 ? 'catalog_item_view' : ''; }
function _swNameKind(cfg) { return cfg.regionType; }
function _sdListRowFlags(node) { return []; }
function _sdFormFieldIsDate() { return false; }
function _sdNodeClassHas(node, cls) { return String(node.props.cssClass || '').split(/\s+/).indexOf(cls) !== -1; }
function _sdAnyParent(node) {
    var hit = null;
    (function walk(n) { (n.children || []).forEach(function (c) { if (c === node) hit = n; else if (!hit) walk(c); }); })(tree);
    return hit ? { parent: hit, sid: 0 } : null;
}
function _propsDataBindingCore(node) { return ''; }
function _sdConditionRows(node) { return ''; }
function _sdBindingTextNote(node) { return ''; }
function has(html, s) { return html.indexOf(s) !== -1; }
var a = propsDataBinding(title), b = propsDataBinding(btn), c = propsDataBinding(plain);
console.log(JSON.stringify({
    flag: has(a, 'data-bind-visible'), flagSet: has(a, '<option value="no_rating" selected>'),
    format: has(a, 'sd-sw-bindfilter'), formatSet: has(a, '<option value="upper" selected>'),
    hide: has(a, 'data-sd-hide-empty'), section: has(a, 'Bind Data (System Widget)'),
    btnAction: has(b, 'data-bind-action'), btnHide: has(b, 'data-sd-hide-empty'), btnBoxed: has(b, 'data-sd-field-value'),
    plain: c, drawing: _sdPartDrawing
}));
JS;
	$file = tempnam(sys_get_temp_dir(), 'pgdpt');
	file_put_contents($file, $code_js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	pg_assert_same(array(
		'flag' => true, 'flagSet' => true, 'format' => true, 'formatSet' => true, 'hide' => true, 'section' => true,
		'btnAction' => false, 'btnHide' => true, 'btnBoxed' => false, 'plain' => '', 'drawing' => 0,
	), json_decode(implode("\n", $out), true));
}

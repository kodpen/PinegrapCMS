<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Show only when a value…" row of the designer's Bind Data
 * panel: its operator and refused-token lists against the server's
 * (includes/fn/widgets_conditions.php), the token choice, the writer and
 * the canvas sentence (style_designer.js, run under Node), the rule that a
 * condition never hides a required form control, and the condition's
 * compared text as a translatable field (includes/translate/extract.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_cond_panel_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The text of a `var NAME = [ ... ];` block of style_designer.js.
function _widget_cond_panel_js_var($source, $name)
{
	$from = strpos($source, "\n    var " . $name . ' = [');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    ];\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// A function of style_designer.js, cut out at its four-space indent.
function _widget_cond_panel_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// The editor lists the server's operators, in its order, with the same
// "takes a value" flag.
function test_widget_condition_panel_ops_match_the_server()
{
	$block = _widget_cond_panel_js_var(_widget_cond_panel_js(), 'SW_COND_OPS');
	pg_assert_true($block !== '', 'SW_COND_OPS is found');
	preg_match_all("/\['([a-z_]+)',\s*_sdT\('[^']+'\),\s*(true|false)\]/", $block, $m, PREG_SET_ORDER);
	$js = array();
	foreach ($m as $row) {
		$js[$row[1]] = ($row[2] === 'true');
	}
	$php = array();
	foreach (pg_sw_cond_ops() as $op => $spec) {
		$php[$op] = $spec['operand'];
	}
	pg_assert_same($php, $js);
}

// The editor refuses the tokens the server refuses.
function test_widget_condition_panel_refused_tokens_match_the_server()
{
	$block = _widget_cond_panel_js_var(_widget_cond_panel_js(), 'SW_COND_DENY');
	pg_assert_true($block !== '', 'SW_COND_DENY is found');
	preg_match_all("/'(__[a-z0-9_]+)'/", $block, $m);
	pg_assert_same(pg_sw_cond_denied_tokens(), $m[1]);
}

// A palette named SW_*TOKEN* is read as offered tokens by
// tools/check_bindings.php; the condition lists must not be.
function test_widget_condition_panel_lists_are_not_palettes()
{
	$js = _widget_cond_panel_js();
	pg_assert_true(!preg_match('/var SW_COND[A-Z_]*TOKEN/', $js), 'no SW_COND*TOKEN* name');
}

function _widget_cond_panel_input($required, $attr_name = 'required')
{
	$attrs = array(array('name' => 'type', 'value' => 'text'));
	if ($required) {
		$attrs[] = array('name' => $attr_name, 'value' => '');
	}
	return array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => $attrs), 'children' => array());
}

function _widget_cond_panel_box($children, $show_if = '__name')
{
	$props = array('tag' => 'div', 'cssClass' => 'box');
	if ($show_if !== null) {
		$props['_bindings'] = array('show_if' => $show_if);
		$props['_bindParams'] = array('show_if' => array('op' => 'eq', 'value' => 'x'));
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// A node holds a required control when it is one or has one inside.
function test_widget_condition_holds_required()
{
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_input(true)), 'a required input');
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_input(true, 'REQUIRED')), 'the name in capitals');
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_box(array(_widget_cond_panel_box(array(_widget_cond_panel_input(true)), null)))), 'deep inside');
	pg_assert_true(!pg_sw_cond_holds_required(_widget_cond_panel_input(false)), 'an optional input');
	pg_assert_true(!pg_sw_cond_holds_required(_widget_cond_panel_box(array(_widget_cond_panel_input(false)))), 'a box of optional inputs');
	pg_assert_true(!pg_sw_cond_holds_required(array('type' => 'content', 'props' => array('text' => 'required'), 'children' => array())), 'the word in a text');
	pg_assert_true(!pg_sw_cond_holds_required(null), 'nothing');
}

// Inside a widget a condition on a node that holds a required control is
// not applied: the control is drawn, so the form stays sendable. Any other
// node is drawn between the condition markers.
function test_widget_condition_never_hides_a_required_control()
{
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => 'custom_form', 'email' => false));
	try {
		$required = _render_tree_node(_widget_cond_panel_box(array(_widget_cond_panel_input(true))), 0, 0);
		$optional = _render_tree_node(_widget_cond_panel_box(array(_widget_cond_panel_input(false))), 0, 0);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_contains('<input', $required);
	pg_assert_true(strpos($required, '<!--pg-if:') === false, 'no condition marker around a required control');
	pg_assert_contains('<!--pg-if:', $optional);
}

function _widget_cond_panel_props($op, $value)
{
	return array(
		'tag' => 'span',
		'text' => 'Sample',
		'_bindings' => array('text' => '__name', 'show_if' => '__category'),
		'_bindParams' => array('show_if' => array('op' => $op, 'value' => $value)),
	);
}

// The text a condition compares a value with enters the site translation
// when it is words; a number, an empty test and a missing value do not.
function test_widget_condition_operand_is_a_translatable_field()
{
	$has = function ($props) {
		foreach (pg_tr_node_fields('semantic', $props) as $spec) {
			if ($spec[0] === '_cond:show_if') return $spec[1];
		}
		return null;
	};
	foreach (array('eq', 'neq', 'contains', 'not_contains', 'starts') as $op) {
		pg_assert_same('text', $has(_widget_cond_panel_props($op, 'Kültür')), $op);
	}
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '12,50')), 'a number');
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '₺0')), 'an amount');
	pg_assert_same(null, $has(_widget_cond_panel_props('gt', 'abc')), 'a numeric operator');
	pg_assert_same(null, $has(_widget_cond_panel_props('not_empty', 'Kültür')), 'no value is compared');
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '  ')), 'an empty value');

	$props = _widget_cond_panel_props('eq', 'Kültür');
	pg_assert_same('Kültür', pg_tr_field_get($props, '_cond:show_if'));
	pg_tr_field_set($props, '_cond:show_if', 'Culture');
	pg_assert_same('Culture', $props['_bindParams']['show_if']['value']);
	pg_assert_same('eq', $props['_bindParams']['show_if']['op']);
	pg_assert_same('', pg_tr_field_get(array(), '_cond:show_if'));
}

// The renderer reads the condition after the language hook has translated
// its compared text, then drops the condition from the props it draws.
function test_widget_condition_render_translates_before_it_reads()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$from = strpos($src, "pg_sw_cond_present(isset(\$node['props'])");
	pg_assert_true($from !== false, 'the condition block is found');
	if ($from === false) return;
	$block = substr($src, $from, 1200);
	$tr = strpos($block, 'pg_tr_condition_props(');
	$spec = strpos($block, 'pg_sw_cond_specs(');
	pg_assert_true($tr !== false && $spec !== false && $tr < $spec, 'translated before it is read');
}

// Runs a script against the editor's condition helpers under Node; null
// when Node is not installed.
function _widget_cond_panel_run_js($script)
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

	$js = _widget_cond_panel_js();
	$cut = _widget_cond_panel_js_var($js, 'SW_COND_OPS') . _widget_cond_panel_js_var($js, 'SW_COND_DENY');
	$names = strpos($js, "\n    var SD_COND_NAMES = ");
	$cut .= substr($js, $names, strpos($js, ";\n", $names) + 2 - $names);
	foreach (array('_sdT', '_sdCondTokenAllowed', '_sdCondOp', '_sdCondHoldsRequired', '_sdCondTokenOptions', '_sdCondRowsOf', '_sdCondJoin',
		'_sdCondWriteRows', '_sdCondWrite', '_sdCondSentence', '_sdNodeSetBinding') as $name) {
		$fn = _widget_cond_panel_js_function($js, $name);
		pg_assert_true($fn !== '', $name . ' is found');
		$cut .= $fn;
	}

	$stubs = "var _sharedDirty = {};\n"
		. "var SW_DATE_FIELD_TOKENS = {};\n"
		. "function _sdIsFilterChain() { return false; }\n"
		. "var INSIDE = 7, KIND = 'catalog_item_view';\n"
		. "function _isInsideSystemWidget(n) { return INSIDE; }\n"
		. "function _getSystemWidgetRegionType(n) { return KIND; }\n"
		. "function _swBindTokenOptions(n) { return [{ token: '__name', label: 'Product name' }, { token: '__price_block_html', label: 'Price block' }, { token: '__variant_picker', label: 'Variants' }, { token: '__link_label', label: 'View' }, { token: '__inventory_quantity', label: 'Stock quantity' }]; }\n"
		. "function _swBindLabel(type, token) { var l = { __name: 'Product name', __inventory_quantity: 'Stock quantity' }; return l[token] || token; }\n";

	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return null;
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

// The editor's token rule equals the server's on the same tokens.
function test_widget_condition_panel_token_rule_matches_the_server()
{
	$tokens = array('__name', '__price_block_html', '__PRICE_BLOCK_HTML', '__variant_picker', '__edit_url',
		'__comment_edit_url', '__empty_message', '__page_link', '__page_link_2', '__link_label', '__link_label_10',
		'__link_labels', '__filter_chips', '__loop_is_first', 'title', '');
	$answer = _widget_cond_panel_run_js('console.log(JSON.stringify(' . json_encode($tokens) . '.map(function (t) { return _sdCondTokenAllowed(t); })));');
	if ($answer === null) {
		return;
	}
	$php = array();
	foreach ($tokens as $t) {
		$php[] = pg_sw_cond_token_allowed($t);
	}
	pg_assert_same($php, $answer);
}

// The token choice drops refused tokens; the writer keeps the binding and
// its operator and value in step, clears both together, and leaves other
// bindings and parameters alone; the canvas sentence names the choice.
function test_widget_condition_panel_choice_writer_and_sentence()
{
	$script = <<<'JS'
var answer = {};
answer.options = _sdCondTokenOptions({ type: 'content', props: {} }).map(function (o) { return o.token; });
var n = { type: 'content', props: { _bindings: { text: '__name' }, _bindParams: { text: { a: 1 } } } };
_sdCondWrite(n, '__inventory_quantity', 'gt', '0');
answer.set = JSON.parse(JSON.stringify(n.props));
answer.sentence = _sdCondSentence(n);
_sdCondWrite(n, '__inventory_quantity', 'not_empty', '5');
answer.noValue = JSON.parse(JSON.stringify(n.props._bindParams.show_if));
answer.sentenceNoValue = _sdCondSentence(n);
_sdCondWrite(n, '', 'eq', 'x');
answer.cleared = JSON.parse(JSON.stringify(n.props));
var m = { type: 'content', props: {} };
_sdCondWrite(m, '__name', 'eq', 'Chair');
_sdCondWrite(m, '', 'eq', '');
answer.emptied = JSON.parse(JSON.stringify(m.props));
var r = { type: 'content', props: {} };
_sdCondWrite(r, '__name', 'nope', 'x');
answer.unknownOp = r.props._bindParams.show_if;
answer.dirty = Object.keys(_sharedDirty);
answer.required = [
    _sdCondHoldsRequired({ type: 'semantic', props: { tag: 'input', _attrs: [{ name: 'required', value: '' }] }, children: [] }),
    _sdCondHoldsRequired({ type: 'semantic', props: { tag: 'div' }, children: [{ type: 'semantic', props: { tag: 'select', _attrs: [{ name: 'Required', value: 'required' }] }, children: [] }] }),
    _sdCondHoldsRequired({ type: 'semantic', props: { tag: 'div' }, children: [{ type: 'semantic', props: { tag: 'input', _attrs: [{ name: 'type', value: 'text' }] }, children: [] }] })
];
answer.op = _sdCondOp('multiple_of');
answer.noOp = _sdCondOp('nope');
console.log(JSON.stringify(answer));
JS;
	$a = _widget_cond_panel_run_js($script);
	if ($a === null) {
		return;
	}
	pg_assert_same(array('__name', '__inventory_quantity'), $a['options']);
	pg_assert_same(array('text' => '__name', 'show_if' => '__inventory_quantity'), $a['set']['_bindings']);
	pg_assert_same(array('text' => array('a' => 1), 'show_if' => array('op' => 'gt', 'value' => '0')), $a['set']['_bindParams']);
	pg_assert_same('Only when: Stock quantity is greater than 0', $a['sentence']);
	pg_assert_same(array('op' => 'not_empty'), $a['noValue']);
	pg_assert_same('Only when: Stock quantity is not empty', $a['sentenceNoValue']);
	pg_assert_same(array('_bindings' => array('text' => '__name'), '_bindParams' => array('text' => array('a' => 1))), $a['cleared']);
	pg_assert_same(array(), $a['emptied']);
	pg_assert_same(array('op' => 'not_empty'), $a['unknownOp']);
	pg_assert_same(array('7'), $a['dirty']);
	pg_assert_same(array(true, true, false), $a['required']);
	pg_assert_same(array('multiple_of', 'is a multiple of', true), $a['op']);
	pg_assert_same(null, $a['noOp']);
}

// Several conditions: each row writes its own name, a removed row lets the
// ones after it move up, the join stays on the first row only while there
// are several, and the chip names every row.
function test_widget_condition_panel_rows()
{
	$script = <<<'JS'
var answer = {};
var n = { type: 'content', props: { _bindings: { text: '__name' } } };
_sdCondWrite(n, '__name', 'eq', 'Chair', 0);
_sdCondWrite(n, '__inventory_quantity', 'gt', '0', 1);
_sdCondWrite(n, '__name', 'empty', 'x', 5);
answer.three = JSON.parse(JSON.stringify(n.props));
answer.allSentence = _sdCondSentence(n);
_sdCondWriteRows(n, _sdCondRowsOf(n), 'any');
answer.join = n.props._bindParams.show_if.join;
answer.anySentence = _sdCondSentence(n);
_sdCondWrite(n, '', 'eq', '', 0);
answer.moved = JSON.parse(JSON.stringify(n.props));
_sdCondWrite(n, '', 'eq', '', 1);
answer.one = JSON.parse(JSON.stringify(n.props));
_sdCondWrite(n, '__name', 'eq', 'Desk', 1);
answer.joinBack = _sdCondJoin(n);
_sdCondWriteRows(n, [{ token: '__name' }, { token: '' }, { token: '__inventory_quantity', op: 'lt', value: 3 }, { token: '__name' }], 'all');
answer.kept = JSON.parse(JSON.stringify(n.props));
console.log(JSON.stringify(answer));
JS;
	$a = _widget_cond_panel_run_js($script);
	if ($a === null) {
		return;
	}
	pg_assert_same(array('text' => '__name', 'show_if' => '__name', 'show_if_2' => '__inventory_quantity', 'show_if_3' => '__name'), $a['three']['_bindings']);
	pg_assert_same(array('show_if' => array('op' => 'eq', 'value' => 'Chair'), 'show_if_2' => array('op' => 'gt', 'value' => '0'),
		'show_if_3' => array('op' => 'empty')), $a['three']['_bindParams']);
	pg_assert_same('Only when: Product name is equal to Chair and Stock quantity is greater than 0 and Product name is empty', $a['allSentence']);
	pg_assert_same('any', $a['join']);
	pg_assert_same('Only when: Product name is equal to Chair or Stock quantity is greater than 0 or Product name is empty', $a['anySentence']);
	pg_assert_same(array('text' => '__name', 'show_if' => '__inventory_quantity', 'show_if_2' => '__name'), $a['moved']['_bindings'], 'the rows move up');
	pg_assert_same(array('show_if' => array('op' => 'gt', 'value' => '0', 'join' => 'any'), 'show_if_2' => array('op' => 'empty')), $a['moved']['_bindParams']);
	pg_assert_same(array('show_if' => array('op' => 'gt', 'value' => '0')), $a['one']['_bindParams'], 'one row keeps no join');
	pg_assert_same('all', $a['joinBack']);
	pg_assert_same(array('text' => '__name', 'show_if' => '__name', 'show_if_2' => '__inventory_quantity', 'show_if_3' => '__name'), $a['kept']['_bindings'], 'empty rows dropped, three at most');
	pg_assert_same(array('op' => 'lt', 'value' => '3'), $a['kept']['_bindParams']['show_if_2']);
	pg_assert_same(array(), pg_sw_cond_specs($a['kept']) === false ? array('invalid') : array(), 'the server reads what the panel writes');
}

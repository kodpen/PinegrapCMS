<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order view's form parts (includes/fn/widgets_parts_forms.php,
 * includes/fn/widgets_lists_order.php): the cancel form and the return
 * request form are drawn by real design elements, showing the same words,
 * classes and controls as the forms the server built before; their
 * address, hidden fields and the controls' names are the system's; a
 * deleted submit button or required control comes back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pof_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _pof_form()
{
	return array('action' => '/software/order_return.php', 'send_to' => '/order?id=9&x=1', 'token' => get_token_field(), 'order_id' => 9);
}

// Two deliveries: Ada's (a mug that may go back, a cup already in a
// return), Bob's with nothing left (no form).
function _pof_deliveries()
{
	return array(
		array('ship_to_id' => 31, 'label' => 'Ada <A>', 'deadline' => '9 November 2026', 'lines' => array(
			array('id' => 301, 'name' => 'Mug <XL>', 'quantity' => 2, 'returnable' => 2),
			array('id' => 302, 'name' => 'Cup', 'quantity' => 1, 'returnable' => 0),
		)),
		array('ship_to_id' => 32, 'label' => 'Bob', 'deadline' => '', 'lines' => array(
			array('id' => 303, 'name' => 'Pen', 'quantity' => 1, 'returnable' => 0),
		)),
	);
}

// $tree given its parts as the widget door does, its forms wired and drawn
// with the return form's rows published, as the order view draws it.
function _pof_render($tree, $flags = array('cancel_needs_refund' => true))
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
	_eo_apply_visibility_bindings($tree, $flags);
	$f = _pof_form();
	pg_sw_order_cancel_wire($tree, array('action' => '/software/cancel_order.php', 'token' => $f['token'], 'order_id' => 9, 'send_to' => $f['send_to']));
	pg_sw_order_return_form_wire($tree, $f);
	$scope = pg_sw_finish_open(array('sid' => 47, 'kind' => 'order_view'));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('order', pg_sw_list_order_rows(array(), array(), array(), array()) + pg_sw_list_order_return_form_rows(_pof_deliveries()));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _pof_visible($html)
{
	// Tags break words, but for a sentence's value (a span).
	$html = preg_replace('/<(?!\/?span\b)/', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pof_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

// Each form, input, select, textarea, label and button: its tag and the
// attributes the browser sends or reads (ids without a row's suffix); a
// select's options.
function _pof_controls($html)
{
	preg_match_all('/<(form|input|select|textarea|label|button)\b([^>]*)>/', $html, $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $c) {
		preg_match_all('/\s([a-z-]+)(?:="([^"]*)")?/', $c[2], $a, PREG_SET_ORDER);
		$attrs = array();
		foreach ($a as $x) {
			if ($x[1] === 'class' || $x[1] === 'rows' || $x[1] === 'placeholder' || strpos($x[1], 'data-pg-bind') === 0 || $x[1] === 'data-pg-cond') continue;
			$v = isset($x[2]) ? preg_replace('/(_pgw\d+r\d+)+$/', '', $x[2]) : '';
			$attrs[$x[1]] = ($x[1] === 'required' || $x[1] === 'aria-hidden' && $v === 'true') ? ($x[1] === 'required' ? '' : $v) : $v;
		}
		ksort($attrs);
		$out[] = $c[1] . ' ' . json_encode($attrs);
	}
	preg_match_all('/<option\b[^>]*>[^<]*<\/option>/', $html, $o);
	return array_merge($out, $o[0]);
}

function test_widget_parts_order_forms_return_rows()
{
	$rows = pg_sw_list_order_return_form_rows(_pof_deliveries());
	pg_assert_same(array('31'), array_column($rows['order.return_deliveries'][0], '__return_delivery_id'), 'a delivery with nothing left has no form');
	$d = $rows['order.return_deliveries'][0][0];
	pg_assert_same('1', $d['__return_delivery_several']);
	pg_assert_same('Ada <A>', $d['__return_delivery_label'], 'raw: the pass escapes it');
	pg_assert_same(array('2', '0'), array_column($rows['order.return_delivery_lines'][31], '__return_item_left'));
	$sources = pg_sw_list_sources();
	pg_assert_same(array_column($sources['order.return_deliveries']['tokens'], 0), array_keys($d));
	pg_assert_same(array_column($sources['order.return_delivery_lines']['tokens'], 0), array_keys($rows['order.return_delivery_lines'][31][0]));
	pg_assert_true(isset(pg_sw_raw_token_keys()['__return_reason_options_html']), 'the reasons are markup');
	$one = pg_sw_list_order_return_form_rows(array_slice(_pof_deliveries(), 0, 1));
	pg_assert_same('', $one['order.return_deliveries'][0][0]['__return_delivery_several'], 'one delivery: no recipient heading');
	pg_assert_same(array('order.return_deliveries' => array(), 'order.return_delivery_lines' => array()), pg_sw_list_order_return_form_rows(array()));
}

function test_widget_parts_order_forms_cancel_matches_the_old_form()
{
	$_SERVER['REQUEST_URI'] = '/order?id=9&x=1';
	$tree = _pof_node('root', array(), array(_pof_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__cancel_form')))));
	foreach (array('paid through a provider' => array('Stripe', true), 'offline' => array('Offline Payment', false)) as $case => $c) {
		$old = '<div>' . _eo_render_cancel_order_form(9, $c[0], 1000) . '</div>';
		$new = _pof_render($tree, array('cancel_needs_refund' => $c[1]));
		pg_assert_same(_pof_visible($old), _pof_visible($new), $case . ': same words');
		pg_assert_same(_pof_classes($old), _pof_classes($new), $case . ': same classes in the same order');
		pg_assert_same(_pof_controls($old), _pof_controls($new), $case . ': same form and controls');
		pg_assert_contains('placeholder="' . h(lang('Tell us why you\'re cancelling — helps us improve.')) . '"', $new, $case);
	}
	unset($_SERVER['REQUEST_URI']);
}

function test_widget_parts_order_forms_return_matches_the_old_form()
{
	$old = '<div>' . pg_return_form_html(_pof_deliveries(), _pof_form()) . '</div>';
	$tree = _pof_node('root', array(), array(_pof_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__return_form')))));
	$new = _pof_render($tree);
	pg_assert_same(_pof_visible($old), _pof_visible($new), 'same words in the same order');
	pg_assert_same(_pof_classes($old), _pof_classes($new), 'same classes in the same order');
	pg_assert_same(_pof_controls($old), _pof_controls($new), 'same form and controls');
	pg_assert_contains('>Ada &lt;A&gt;</span></h6>', $new, 'the recipient escaped once, in its sentence');
	pg_assert_true(preg_match('/aria-describedby="(pg-ov-return-note-help-31[^"]*)".*id="\\1"/s', $new) === 1, 'the note names its help line');
	pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, 'nothing left');
	pg_assert_same(1, substr_count($new, '<form '), 'one form: Bob has nothing left');
}

// The design's look and words are the designer's; what the forms send is not.
function test_widget_parts_order_forms_controls_are_the_systems()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pof_node('root', array(), array(
		_pof_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__cancel_form'))),
		_pof_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__return_form'))),
	))), 'order_view'), true);
	// The cancel form: its button deleted, its reason renamed and restyled.
	$cancel = &$tree['children'][0]['children'][0];
	array_pop($cancel['children']);
	$cancel['children'][1]['children'][1]['props']['_attrs'][] = array('name' => 'name', 'value' => 'mine');
	$cancel['children'][1]['children'][1]['props']['cssClass'] = 'form-control form-control-lg';
	unset($cancel);
	// The return form: its reason and button deleted, the quantity box too.
	$form = &$tree['children'][1]['children'][0]['children'][0];
	$form['children'] = array_values(array_filter($form['children'], function ($c) {
		return !isset($c['props']['customName']) || ($c['props']['customName'] !== 'Reason' && $c['props']['customName'] !== 'Request button');
	}));
	$row = &$form['children'][2]['children'][0]['children'][1]['children'][0]['children'][0];
	array_pop($row['children']);
	unset($row, $form);
	$new = _pof_render($tree);
	pg_assert_contains('name="cancellation_reason"', $new);
	pg_assert_true(strpos($new, 'name="mine"') === false, 'not the design\'s name');
	pg_assert_contains('form-control form-control-lg', $new, 'the design\'s classes');
	pg_assert_same(1, preg_match_all('/<button class="btn btn-outline-danger" type="submit"/', $new), 'the cancel button comes back');
	pg_assert_same(1, preg_match_all('/<select [^>]*name="reason"[^>]*required/', $new), 'the reason comes back');
	pg_assert_same(1, preg_match_all('/<button class="btn btn-outline-primary" type="submit"/', $new), 'the request button comes back');
	pg_assert_same(1, preg_match_all('/name="qty\[301\]"/', $new), 'the quantity box comes back');
}

function test_widget_parts_order_forms_placed_beats_pushed()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pof_node('root', array(), array(
		_pof_node('semantic', array('tag' => 'div', 'cssClass' => 'card-body', '_bindings' => array('text' => '__cancel_form'))),
	))), 'order_view'), true);
	$host = $tree['children'][0];
	pg_assert_same('card-body', $host['props']['cssClass'], 'the host keeps its classes');
	pg_assert_same('order_cancel_form', $host['children'][0]['props']['_bindings']['action']);
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'order_view'), 'a tree with its part is left as it is');
	// The returns card added after a layout placing neither holds the form part.
	$card = $tree['children'][1];
	pg_assert_contains('pg-ov-returns-block', $card['props']['cssClass']);
	pg_assert_same('order.return_deliveries', $card['children'][0]['children'][1]['children'][1]['children'][0]['props']['source']);
}

// The editor's parts (style_designer.js) are the server's.
function test_widget_parts_order_forms_editor_twin()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = '';
	foreach (array('_sdPartEl', '_sdPartWhen', '_sdPartOrderCancelSubmit', '_sdPartOrderCancelForm', '_sdPartOrderReturnQtyCell', '_sdPartSentence',
		'_sdPartOrderReturnForm', '_sdPartOrderReturnReason', '_sdPartOrderReturnSubmit', '_sdPartOrderReturnMoveSubmit', '_sdPartOrderReturnMoves') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = "console.log(JSON.stringify({ cancel: _sdPartOrderCancelForm(), ret: _sdPartOrderReturnForm(), moves: _sdPartOrderReturnMoves() }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgparts');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);
	pg_assert_same(array_map('_pof_norm', pg_sw_part_order_cancel_form()), array_map('_pof_norm', $answer['cancel']), 'the same cancel form');
	pg_assert_same(array_map('_pof_norm', pg_sw_part_order_return_form()), array_map('_pof_norm', $answer['ret']), 'the same return form');
	pg_assert_same(array_map('_pof_norm', pg_sw_part_order_return_moves()), array_map('_pof_norm', $answer['moves']), 'the same moves');
}

// A tree without what only one side writes: the editor's ids and names.
function _pof_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id']);
	if (isset($node['props']) && is_array($node['props'])) {
		unset($node['props']['customName']);
		ksort($node['props']);
		foreach ($node['props'] as $k => $v) {
			if (is_array($v)) ksort($node['props'][$k]);
		}
		if (!$node['props']) $node['props'] = array();
	}
	if (isset($node['children']) && is_array($node['children'])) {
		$node['children'] = array_map('_pof_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

// A return's moves: each form posts its own action for the row's return;
// a form whose button was deleted gets it back.
function test_widget_parts_order_forms_return_moves()
{
	$moves = pg_sw_part_order_return_moves();
	array_pop($moves[1]['children']);
	$tree = _pof_node('root', array(), $moves);
	pg_sw_order_return_form_wire($tree, _pof_form());
	$sent = $tree['children'][0];
	pg_assert_same('order_return_shipped_form', $sent['props']['_bindings']['action']);
	pg_assert_contains('name="action" value="shipped"', $sent['children'][0]['props']['html']);
	pg_assert_contains('name="return_id" value="^^__return_id^^"', $sent['children'][0]['props']['html']);
	$box = $sent['children'][2]['children'][0];
	pg_assert_same('pg-ov-return-tracking-^^__return_id^^', $box['props']['id']);
	pg_assert_same(array('name' => 'tracking_number', 'maxlength' => '100'), array_intersect_key(array_column($box['props']['_attrs'], 'value', 'name'), array('name' => 1, 'maxlength' => 1)));
	pg_assert_same('pg-ov-return-tracking-^^__return_id^^', pg_sw_form_attr($sent['children'][1], 'for'), 'the label names the box in its group');
	$withdraw = $tree['children'][1];
	pg_assert_contains('value="withdraw"', $withdraw['children'][0]['props']['html']);
	pg_assert_same(lang('Withdraw the request'), $withdraw['children'][1]['props']['text'], 'the deleted button comes back');
}

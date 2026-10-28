<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart's product form part (includes/fn/widgets_parts_forms.php,
 * includes/fn/widgets_lists_cart.php): a line's order form is drawn by real
 * design elements from the cart.item_forms, cart.item_form_fields and
 * cart.item_form_field_options lists, with the same words, classes and
 * control names as the block the cart built before; what a control sends,
 * its limits and whether it is required stay the system's, row by row; a
 * deleted control comes back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pccf_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _pccf_field($id, $label, $type, $more = array())
{
	return array_merge(array('id' => $id, 'label' => $label, 'type' => $type, 'required' => 0, 'default_value' => '',
		'rows_' => 0, 'cols_' => 0, 'maxlength' => 0, 'multiple' => 0, 'information' => ''), $more);
}

// One field of every type, a choice of each kind stored, an upload stored.
function _pccf_fields()
{
	return array(
		_pccf_field(1, 'Name', 'text box', array('required' => 1, 'maxlength' => 40, 'default_value' => 'Ann')),
		_pccf_field(2, 'Note', 'text area', array('rows_' => 3, 'cols_' => 20)),
		_pccf_field(3, 'Size', 'pick list', array('required' => 1)),
		_pccf_field(4, 'Tags', 'pick list', array('multiple' => 1)),
		_pccf_field(5, 'Colour', 'radio button', array('required' => 1, 'default_value' => 'b')),
		_pccf_field(6, 'Extras', 'check box', array('required' => 1)),
		_pccf_field(7, 'Day', 'date'),
		_pccf_field(8, 'At', 'date and time', array('required' => 1)),
		_pccf_field(9, 'Hour', 'time'),
		_pccf_field(10, 'Mail', 'email address', array('maxlength' => 80)),
		_pccf_field(11, 'Photo', 'file upload', array('required' => 1)),
		_pccf_field(12, 'About', 'information', array('information' => '<b>Read</b> me')),
		_pccf_field(13, '', 'information'),
	);
}

function _pccf_blocks($qty, $per)
{
	$data = array(
		array('form_field_id' => 1, 'data' => 'A "b"', 'quantity_number' => 1),
		array('form_field_id' => 3, 'data' => 'm', 'quantity_number' => 2),
		array('form_field_id' => 6, 'data' => 'x|z', 'quantity_number' => 1),
		array('form_field_id' => 7, 'data' => (string)mktime(12, 0, 0, 12, 1, 2026), 'quantity_number' => 1),
		array('form_field_id' => 11, 'data' => 'photo.jpg', 'quantity_number' => 1),
	);
	$opts = array(
		3 => array(array('name' => 'M', 'value' => 'm'), array('name' => 'L', 'value' => 'l')),
		4 => array(array('name' => 'A', 'value' => 'a')),
		5 => array(array('name' => 'Blue', 'value' => 'b'), array('name' => 'Cyan', 'value' => 'c')),
		6 => array(array('name' => 'X', 'value' => 'x'), array('name' => 'Y', 'value' => 'y'), array('name' => 'Z', 'value' => 'z')),
	);
	return _pg_cart_item_form_blocks(41, _pccf_fields(), $data, $opts, $qty, $per ? 'One Form per Quantity' : 'One Form per Product', 'Card');
}

// A line with a form per unit (two), a plain line.
function _pccf_items()
{
	return array(
		array('item_id' => 41, 'name' => 'Ticket', 'form' => _pccf_blocks(2, true)),
		array('item_id' => 42, 'name' => 'Mug', 'form' => array()),
	);
}

function _pccf_tree($more = array())
{
	return _pccf_node('root', array(), array(
		_pccf_node('loop_area', array(), array_merge(array(
			_pccf_node('semantic', array('tag' => 'div', 'cssClass' => 'py-3', '_bindings' => array('text' => '__item_name'))),
		), $more)),
	));
}

// $tree given its parts as the widget door does, its controls wired as the
// cart wires them, drawn and finished as the cart draws it. Only the form's
// part: the gift card and schedule parts have no rows here.
function _pccf_render($tree)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true);
	_apply_shopping_cart_bindings($tree, array('cart_form_id' => 'pg-cart-form-w9'));
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => 'shopping_cart', 'email' => false));
	try {
		$split = _split_widget_tree($tree);
		$loop = '';
		foreach ($split['loop_children'] as $child) $loop .= _render_tree_node($child, 0, 0);
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		pg_sw_list_publish('cart', pg_sw_list_cart_rows(_pccf_items()));
		$out = '';
		foreach (_pccf_items() as $item) {
			$out .= pg_sw_sweep_tokens(pg_sw_fill_longest(trim($loop), array('__item_id' => (string)$item['item_id'], '__item_name' => h($item['name']))));
		}
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest(str_replace('<!--pg-loop-slot-->', $out, $static), array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _pccf_old()
{
	$out = '';
	foreach (_pccf_items() as $item) {
		$out .= '<div class="py-3">' . h($item['name']) . '</div>'
			. _pg_render_cart_item_form_html($item['item_id'], $item['form'], 'pg-cart-form-w9');
	}
	return $out;
}

function _pccf_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pccf_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map(function ($c) { return trim(preg_replace('/\s+/', ' ', str_replace('pg-hide-if-empty', '', $c))); }, $m[1]), 'strlen'));
}

// What the cart reads: each control's tag and the attributes it sends or
// checks, a select's options, a text area's text; the data-pg-* marker.
// Ids left out (made unique per row either way).
function _pccf_controls($html)
{
	preg_match_all('/<input\b([^>]*)>|<select\b([^>]*)>(.*?)<\/select>|<textarea\b([^>]*)>(.*?)<\/textarea>/s', $html, $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $c) {
		$tag = (isset($c[4]) && $c[4] !== '') ? 'textarea' : ((isset($c[2]) && $c[2] !== '') ? 'select' : 'input');
		$attr = ($tag === 'textarea') ? $c[4] : (($tag === 'select') ? $c[2] : $c[1]);
		preg_match_all('/\s([a-z-]+)(?:="([^"]*)")?/', $attr, $a, PREG_SET_ORDER);
		$attrs = array();
		foreach ($a as $x) {
			if (in_array($x[1], array('type', 'name', 'value', 'form', 'required', 'maxlength', 'rows', 'cols', 'multiple', 'checked'), true)) {
				$attrs[$x[1]] = ($x[1] === 'required' || $x[1] === 'multiple' || $x[1] === 'checked') ? 'on' : (isset($x[2]) ? $x[2] : '');
			}
		}
		ksort($attrs);
		$inner = ($tag === 'select') ? preg_replace('/>\s+</', '><', trim($c[3])) : (($tag === 'textarea') ? trim($c[5]) : '');
		$out[] = $tag . ' ' . json_encode($attrs) . ($inner !== '' ? ' ' . $inner : '');
	}
	preg_match_all('/\s(data-pg-cart-item-form)="([^"]*)"/', $html, $d, PREG_SET_ORDER);
	foreach ($d as $x) $out[] = $x[1] . '=' . $x[2];
	return $out;
}

function test_widget_parts_cart_form_blocks()
{
	$blocks = _pccf_blocks(2, true);
	pg_assert_same(2, count($blocks), 'a block per unit');
	pg_assert_same(array(2, 2, true), array($blocks[1]['number'], $blocks[1]['count'], $blocks[1]['has_legend']));
	$f = $blocks[0]['fields'];
	pg_assert_same('order_item_41_quantity_number_1_form_field_1', $f[0]['name']);
	pg_assert_same(array('input', 'text', 'A "b"', 40), array($f[0]['kind'], $f[0]['type'], $f[0]['value'], $f[0]['maxlength']));
	pg_assert_same('Ann', $blocks[1]['fields'][0]['value'], 'the default where nothing is stored');
	pg_assert_same('order_item_41_quantity_number_1_form_field_4[]', $f[3]['name'], 'several choices send a list');
	pg_assert_same(array('choice', 'checkbox', array(true, false, true)), array($f[5]['kind'], $f[5]['type'], array_column($f[5]['options'], 'selected')));
	pg_assert_same(array('date', '2026-12-01', 0), array($f[6]['type'], $f[6]['value'], $f[6]['maxlength']));
	pg_assert_same(array('file', '', 'photo.jpg'), array($f[10]['kind'], $f[10]['value'], $f[10]['file']));
	$plain = _pg_cart_item_form_blocks(41, _pccf_fields(), array(), array(), 1, 'One Form per Product', '');
	pg_assert_false($plain[0]['has_legend'], 'no name and one block: no heading');
	pg_assert_same(1, count(_pccf_blocks(3, false)), 'one form per product');
}

function test_widget_parts_cart_form_rows()
{
	$rows = pg_sw_list_cart_rows(_pccf_items());
	pg_assert_same(array(41), array_keys($rows['cart.item_forms']), 'only the line with a form');
	pg_assert_same(array('41001', '41002'), array_column($rows['cart.item_forms'][41], '__form_key'));
	$fields = $rows['cart.item_form_fields']['41001'];
	pg_assert_same(12, count($fields), 'an empty information field gives no row');
	pg_assert_same(array('41001001', 'input', '1', '40'), array($fields[0]['__field_key'], $fields[0]['__field_control'], $fields[0]['__field_required'], $fields[0]['__field_maxlength']));
	pg_assert_contains('<option value="">Select...</option>', $fields[2]['__field_options_html']);
	pg_assert_same(array('1', ''), array($fields[3]['__field_multiple'], $fields[1]['__field_maxlength']));
	$opts = $rows['cart.item_form_field_options']['41001005'];
	pg_assert_same(array('radio', '1', '1', ''), array($opts[0]['__option_type'], $opts[0]['__option_checked'], $opts[0]['__option_required'], $opts[1]['__option_checked']));
	pg_assert_same('', $rows['cart.item_form_field_options']['41001006'][0]['__option_required'], 'a check box group is never required');
	$sources = pg_sw_list_sources();
	foreach (array('cart.item_forms' => $rows['cart.item_forms'][41][0], 'cart.item_form_fields' => $fields[0], 'cart.item_form_field_options' => $opts[0]) as $source => $row) {
		pg_assert_same(array_column($sources[$source]['tokens'], 0), array_keys($row), $source . ': the source tokens');
		pg_assert_true(isset(pg_sw_kind_lists('shopping_cart')[$source]), $source . ': the cart offers it');
		pg_assert_true(!isset(pg_sw_kind_lists('cart_link')[$source]), $source . ': the cart link does not');
	}
	pg_assert_true(isset(pg_sw_raw_token_keys()['__field_options_html']), 'the choices are markup');
}

function test_widget_parts_cart_form_matches_the_old_block()
{
	$old = _pccf_old();
	foreach (array(
		'the old token' => _pccf_tree(array(_pccf_node('content', array('contentType' => 'text', 'text' => '^^__item_form_data_html^^')))),
		'a layout from before the token' => _pccf_tree(),
	) as $case => $tree) {
		$new = _pccf_render($tree);
		pg_assert_same(_pccf_visible($old), _pccf_visible($new), $case . ': same words in the same order');
		pg_assert_same(_pccf_classes($old), _pccf_classes($new), $case . ': same classes in the same order');
		pg_assert_same(_pccf_controls($old), _pccf_controls($new), $case . ': same controls and marker');
		pg_assert_same(1, preg_match('/<label class="form-label" for="pgCif_41_q1_f1_pgw/', $new), $case . ': a label names its control');
		pg_assert_same(1, preg_match('/<label class="form-check-label" for="pgCif_41_q1_f5_0_pgw/', $new), $case . ': a choice\'s label names its check');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false && strpos($new, 'pgav-') === false, $case . ': nothing left');
	}
}

// The design's look is the designer's; what a control sends is not.
function test_widget_parts_cart_form_controls_are_the_systems()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pccf_tree()), 'shopping_cart'), true);
	$field = &$tree['children'][0]['children'][1]['children'][0]['children'][0]['children'][1]['children'][0];
	pg_assert_same('mb-3', $field['props']['cssClass']);
	// The designer restyles the text box and names it.
	$box = &$field['children'][1];
	$box['props']['cssClass'] = 'form-control form-control-lg';
	$box['props']['_attrs'] = array(array('name' => 'name', 'value' => 'mine'), array('name' => 'title', 'value' => 'Yours'), array('name' => 'required', 'value' => ''));
	unset($box);
	// … and deletes the pick list.
	unset($field['children'][3]);
	unset($field);
	$new = _pccf_render($tree);
	pg_assert_same(1, preg_match('/<input [^>]*name="order_item_41_quantity_number_1_form_field_1"[^>]*class="form-control form-control-lg\s*"[^>]*title="Yours"|<input [^>]*class="form-control form-control-lg\s*"[^>]*name="order_item_41_quantity_number_1_form_field_1"/', $new),
		'the design\'s classes and attribute, the system\'s name');
	pg_assert_true(strpos($new, 'name="mine"') === false, 'not the design\'s name');
	pg_assert_same(0, preg_match('/<input [^>]*form_field_9"[^>]*required/', $new), 'an optional field stays optional whatever the design says');
	pg_assert_same(2, preg_match_all('/<select [^>]*name="order_item_41_quantity_number_\d_form_field_3"/', $new), 'the deleted pick list comes back');
}

function test_widget_parts_cart_form_empty_switch_is_left_out()
{
	$nonce = pg_sw_marker_nonce();
	$html = '<input required="pgav-' . $nonce . '-1-" maxlength="pgav-' . $nonce . '-2-" title="pgav-' . $nonce . '-3-">'
		. '<!--pg-cv:' . $nonce . ':a1--><!--/pg-cv:' . $nonce . ':a1-->'
		. '<!--pg-cv:' . $nonce . ':a2-->12<!--/pg-cv:' . $nonce . ':a2-->'
		. '<!--pg-cv:' . $nonce . ':a3--><!--/pg-cv:' . $nonce . ':a3-->';
	$out = pg_sw_attr_resolve($html, $nonce);
	pg_assert_same('<input maxlength="12" title="">', substr($out, 0, strpos($out, '<!--')), 'an empty switch goes, a plain attribute stays');
}

function test_widget_parts_cart_form_placed_beats_pushed()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pccf_tree()), 'shopping_cart'), true);
	pg_assert_same(4, count($tree['children'][0]['children']), 'the row, the form, the gift cards, the schedule');
	pg_assert_same('pg-cart-item-form mt-3 pg-hide-if-empty', $tree['children'][0]['children'][1]['props']['cssClass']);
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'shopping_cart'), 'a tree with its part is left as it is');
}

// The editor's part (style_designer.js) is the server's.
function test_widget_parts_cart_form_editor_twin()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SD_COND_NAMES = ", ";\n") . $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n")
		. $cut("\n    var SW_DEFAULT_LOOP_TAILS = {", "\n    };\n")
		. $cut("\n    var SW_CART_GIFT_CARD_CONTROLS = {", "\n    };\n")
		. $cut("\n    var SW_CART_SCHEDULE_CONTROLS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartValue', '_sdPartPlaced', '_sdPartItemLegend',
		'_sdPartCartGiftCardField', '_sdPartCartItemGiftCard', '_sdPartCartScheduleField', '_sdPartCartItemSchedule',
		'_sdPartCartFormControl', '_sdPartCartItemForm', '_sdMainLoop', '_sdDefaultLoopTails') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _pccf_tree(array(_pccf_node('content', array('contentType' => 'paragraph', 'text' => '^^__item_form_data_html^^'))));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function _sdListIsSourced(n) { return !!(n && n.type === 'loop_area' && n.props && typeof n.props.source === 'string' && n.props.source !== ''); }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ', bare = ' . json_encode(_pccf_tree()) . ";\n"
		. "var changed = _sdDefaultParts(tree, 'shopping_cart', null);\n"
		. "_sdDefaultLoopTails(tree, 'shopping_cart', null);\n"
		. "var tail = _sdDefaultLoopTails(bare, 'shopping_cart', null);\n"
		. "console.log(JSON.stringify({ changed: changed, tail: tail, again: _sdDefaultParts(tree, 'shopping_cart', null) || _sdDefaultLoopTails(tree, 'shopping_cart', null), tree: tree, bare: bare }));\n";
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
	pg_assert_true($answer['changed'] && $answer['tail'], 'the editor gave the part, in place and at the loop\'s end');
	pg_assert_false($answer['again'], 'a tree with its part has nothing left to give');
	pg_assert_same(_pccf_norm(json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true)), _pccf_norm($answer['tree']), 'the same tree');
	pg_assert_same(_pccf_norm(json_decode(pg_sw_default_parts_apply(json_encode(_pccf_tree()), 'shopping_cart'), true)), _pccf_norm($answer['bare']), 'the same tail');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _pccf_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id']);
	if (isset($node['props']) && is_array($node['props'])) {
		unset($node['props']['customName']);
		if (isset($node['props']['_bindings']['text'])) unset($node['props']['text']);
		ksort($node['props']);
		foreach ($node['props'] as $k => $v) {
			if (is_array($v)) ksort($node['props'][$k]);
		}
		if (!$node['props']) $node['props'] = array();
	}
	if (isset($node['children']) && is_array($node['children'])) {
		$node['children'] = array_map('_pccf_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart's payment schedule part (includes/fn/widgets_parts.php,
 * includes/fn/widgets_lists_cart.php): the frequency, number of payments
 * and start date of a line whose product lets the customer set them are
 * drawn by real design elements from the cart.item_schedule list, with the
 * same words, classes and control names as the block the cart built
 * before; what a control sends, whether it is required and the gateway's
 * rules stay the system's; a deleted required control comes back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pcsc_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

// The visitor's rejected attempt: a number of payments that is no number.
function _pcsc_lf()
{
	return new pg_liveform_snapshot(array('recurring_number_of_payments_31' => array('value' => '1"2', 'error' => 1)));
}

// A line whose schedule the customer sets, a plain line.
function _pcsc_rows()
{
	return array(
		array('item_id' => 31, 'name' => 'Coffee club', 'recurring_payment_period' => 'Quarterly', 'recurring_number_of_payments' => 6,
			'recurring_start_date' => '2026-12-01'),
		array('item_id' => 32, 'name' => 'Mug'),
	);
}

function _pcsc_items()
{
	$out = array();
	foreach (_pcsc_rows() as $i => $r) {
		$out[] = array('item_id' => $r['item_id'], 'name' => $r['name'],
			'schedule' => $i === 0 ? _pg_cart_recurring_schedule($r, _pcsc_lf()) : null);
	}
	return $out;
}

function _pcsc_tree($more = array())
{
	return _pcsc_node('root', array(), array(
		_pcsc_node('loop_area', array(), array_merge(array(
			_pcsc_node('semantic', array('tag' => 'div', 'cssClass' => 'py-3', '_bindings' => array('text' => '__item_name'))),
		), $more)),
	));
}

// $tree given its parts as the widget door does, its controls wired as the
// cart wires them, drawn and finished as the cart draws it.
function _pcsc_render($tree)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true);
	_apply_shopping_cart_bindings($tree, array('cart_form_id' => 'pg-cart-form-w9'));
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => 'shopping_cart', 'email' => false));
	try {
		$split = _split_widget_tree($tree);
		$loop = '';
		foreach ($split['loop_children'] as $child) $loop .= _render_tree_node($child, 0, 0);
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		pg_sw_list_publish('cart', pg_sw_list_cart_rows(_pcsc_items()));
		$out = '';
		foreach (_pcsc_items() as $item) {
			$out .= pg_sw_sweep_tokens(pg_sw_fill_longest(trim($loop), array('__item_id' => (string)$item['item_id'], '__item_name' => h($item['name']))));
		}
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest(str_replace('<!--pg-loop-slot-->', $out, $static), array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _pcsc_old()
{
	$out = '';
	foreach (_pcsc_rows() as $i => $r) {
		$out .= '<div class="py-3">' . h($r['name']) . '</div>'
			. ($i === 0 ? _pg_render_cart_item_recurring_schedule($r, _pcsc_lf(), ' form="pg-cart-form-w9"') : '');
	}
	return $out;
}

function _pcsc_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pcsc_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map(function ($c) { return trim(preg_replace('/\s+/', ' ', $c)); }, $m[1]), 'strlen'));
}

// What the cart reads: each control's tag and the attributes it sends or
// checks, a select's options; the data-pg-* marker. Ids left out (made
// unique per row either way).
function _pcsc_controls($html)
{
	preg_match_all('/<input\b([^>]*)>|<select\b([^>]*)>(.*?)<\/select>/s', $html, $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $c) {
		$select = isset($c[2]) && $c[2] !== '';
		preg_match_all('/\s([a-z-]+)(?:="([^"]*)")?/', $select ? $c[2] : $c[1], $a, PREG_SET_ORDER);
		$attrs = array();
		foreach ($a as $x) {
			if (in_array($x[1], array('type', 'name', 'value', 'form', 'required', 'min', 'max', 'step', 'disabled'), true)) $attrs[$x[1]] = isset($x[2]) ? $x[2] : '';
		}
		ksort($attrs);
		$out[] = ($select ? 'select ' : 'input ') . json_encode($attrs) . ($select ? ' ' . preg_replace('/>\s+</', '><', trim($c[3])) : '');
	}
	preg_match_all('/\s(data-pg-recurring[a-z-]*)="([^"]*)"/', $html, $d, PREG_SET_ORDER);
	foreach ($d as $x) $out[] = $x[1] . '=' . $x[2];
	return $out;
}

function test_widget_parts_cart_schedule_rows()
{
	$rows = pg_sw_list_cart_rows(_pcsc_items());
	pg_assert_same(array(31), array_keys($rows['cart.item_schedule']), 'only the line the customer schedules');
	$row = $rows['cart.item_schedule'][31][0];
	pg_assert_same('31', $row['__schedule_item']);
	pg_assert_same('1"2', $row['__schedule_payments'], 'the rejected attempt wins');
	pg_assert_same('2026-12-01', $row['__schedule_start']);
	pg_assert_same('is-invalid', $row['__schedule_payments_invalid']);
	pg_assert_same('', $row['__schedule_period_invalid']);
	pg_assert_contains('<option value="Quarterly" selected>', $row['__schedule_period_options_html']);
	$sources = pg_sw_list_sources();
	pg_assert_same(array_column($sources['cart.item_schedule']['tokens'], 0), array_keys($row), 'the source tokens');
	pg_assert_true(isset(pg_sw_kind_lists('shopping_cart')['cart.item_schedule']), 'the cart offers it');
	pg_assert_true(!isset(pg_sw_kind_lists('cart_link')['cart.item_schedule']), 'the cart link does not');
	pg_assert_true(isset(pg_sw_raw_token_keys()['__schedule_period_options_html']), 'the options are markup');
}

function test_widget_parts_cart_schedule_rules()
{
	$none = pg_cart_schedule_rules(false, '');
	pg_assert_false($none['payments_required']);
	pg_assert_true($none['show_start']);
	pg_assert_same(array(1, 0), array($none['payments_min'], $none['payments_max']));
	$cc = pg_cart_schedule_rules(true, 'ClearCommerce');
	pg_assert_true($cc['payments_required']);
	pg_assert_false($cc['show_start'], 'ClearCommerce bills from today');
	pg_assert_same(array(2, 999), array($cc['payments_min'], $cc['payments_max']));
	$fd = pg_cart_schedule_rules(true, 'First Data Global Gateway');
	pg_assert_same(array(true, true, 1, 99), array($fd['payments_required'], $fd['show_start'], $fd['payments_min'], $fd['payments_max']));
}

function test_widget_parts_cart_schedule_matches_the_old_block()
{
	$old = _pcsc_old();
	foreach (array(
		'the old token' => _pcsc_tree(array(_pcsc_node('content', array('contentType' => 'text', 'text' => '^^__item_recurring_schedule_html^^')))),
		'a layout from before the token' => _pcsc_tree(),
	) as $case => $tree) {
		$new = _pcsc_render($tree);
		pg_assert_same(_pcsc_visible($old), _pcsc_visible($new), $case . ': same words in the same order');
		pg_assert_same(_pcsc_classes($old), _pcsc_classes($new), $case . ': same classes in the same order');
		pg_assert_same(_pcsc_controls($old), _pcsc_controls($new), $case . ': same controls and marker');
		pg_assert_same(3, preg_match_all('/<label class="form-label" for="recurring_[a-z_]+_31_pgw/', $new), $case . ': each label names its control');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $case . ': nothing left');
	}
}

// The gateway's rules reach the controls: ClearCommerce asks for the number
// of payments in its range and no start date.
function test_widget_parts_cart_schedule_gateway_rules_on_the_controls()
{
	$rules = pg_cart_schedule_rules(true, 'ClearCommerce');
	$node = _pcsc_node('semantic', array('tag' => 'input', 'cssClass' => 'form-control', '_bindings' => array('value' => 'schedule_payments')));
	pg_sw_cart_schedule_control($node, 'f1', $rules);
	$attrs = array_column($node['props']['_attrs'], 'value', 'name');
	pg_assert_same(array('2', '999', ''), array($attrs['min'], $attrs['max'], $attrs['required']));
	pg_assert_same('recurring_number_of_payments_^^__schedule_item^^', $attrs['name']);
	pg_assert_same('form-control ^^__schedule_payments_invalid^^', $node['props']['cssClass']);
	$node = _pcsc_node('semantic', array('tag' => 'input', '_bindings' => array('value' => 'schedule_start')));
	pg_sw_cart_schedule_control($node, 'f1', $rules);
	$attrs = array_column($node['props']['_attrs'], 'value', 'name');
	pg_assert_true(isset($attrs['disabled']) && !isset($attrs['required']), 'a start date the gateway does not ask never sends');
	$loop = _pcsc_node('loop_area', array('source' => 'cart.item_schedule'), array(_pcsc_node('semantic', array('tag' => 'fieldset'))));
	pg_sw_cart_schedule_restore($loop, $rules);
	pg_assert_same(array('schedule_period', 'schedule_payments'), array_map(function ($f) { return $f['children'][1]['props']['_bindings']['value']; }, $loop['children'][0]['children']),
		'the required ones come back, the start date does not');
	$node = _pcsc_node('semantic', array('tag' => 'div', '_bindings' => array('value' => 'schedule_period')));
	pg_sw_cart_schedule_control($node, 'f1', $rules);
	pg_assert_true(!isset($node['props']['_attrs']), 'a frequency that is no select is left');
}

// The design's look is the designer's; what a control sends is not.
function test_widget_parts_cart_schedule_controls_are_the_systems()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcsc_tree()), 'shopping_cart'), true);
	$set = &$tree['children'][0]['children'][3]['children'][0];
	pg_assert_same('pg-cart-recurring-set border rounded p-3 mb-3', $set['props']['cssClass']);
	// The designer restyles the frequency, names it, and deletes the
	// optional number of payments.
	$period = &$set['children'][1]['children'][1];
	$period['props']['cssClass'] = 'form-select form-select-lg';
	$period['props']['_attrs'] = array(array('name' => 'name', 'value' => 'mine'), array('name' => 'title', 'value' => 'How often'));
	$period['children'] = array(_pcsc_node('semantic', array('tag' => 'option', 'text' => 'Mine')));
	unset($period);
	unset($set['children'][2]);
	unset($set);
	$new = _pcsc_render($tree);
	pg_assert_same(1, preg_match('/<select [^>]*class="form-select form-select-lg\s*"/', $new), 'the design\'s classes');
	pg_assert_contains('title="How often"', $new, 'its own attribute kept');
	pg_assert_contains('name="recurring_payment_period_31"', $new, 'the name the cart reads');
	pg_assert_contains('<option value="Quarterly" selected>', $new, 'the system\'s choices');
	pg_assert_true(strpos($new, 'name="mine"') === false && strpos($new, '>Mine<') === false, 'not the design\'s name or choices');
	pg_assert_true(strpos($new, 'recurring_number_of_payments') === false, 'a deleted optional field stays deleted');

	// The required frequency deleted: it comes back in the box.
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcsc_tree()), 'shopping_cart'), true);
	unset($tree['children'][0]['children'][3]['children'][0]['children'][1]);
	$new = _pcsc_render($tree);
	pg_assert_same(1, preg_match_all('/<select[^>]*name="recurring_payment_period_31"[^>]*required/', $new), 'one required frequency');
}

function test_widget_parts_cart_schedule_placed_beats_pushed()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcsc_tree()), 'shopping_cart'), true);
	pg_assert_same(4, count($tree['children'][0]['children']), 'the row, the product form, the gift cards, then the schedule');
	pg_assert_same('cart.item_schedule', $tree['children'][0]['children'][3]['props']['source']);
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'shopping_cart'), 'a tree with its part is left as it is');
}

// The editor's part (style_designer.js) is the server's.
function test_widget_parts_cart_schedule_editor_twin()
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
	$src = $cut("\n    var SD_COND_NAMES = ", ";\n") . $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n")
		. $cut("\n    var SW_DEFAULT_LOOP_TAILS = {", "\n    };\n")
		. $cut("\n    var SW_CART_GIFT_CARD_CONTROLS = {", "\n    };\n")
		. $cut("\n    var SW_CART_SCHEDULE_CONTROLS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartPlaced', '_sdPartItemLegend',
		'_sdPartCartGiftCardField', '_sdPartCartItemGiftCard', '_sdPartCartScheduleField', '_sdPartCartItemSchedule',
		'_sdPartValue', '_sdPartCartFormControl', '_sdPartCartItemForm', '_sdMainLoop', '_sdDefaultLoopTails') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _pcsc_tree(array(_pcsc_node('content', array('contentType' => 'paragraph', 'text' => '^^__item_recurring_schedule_html^^'))));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function _sdListIsSourced(n) { return !!(n && n.type === 'loop_area' && n.props && typeof n.props.source === 'string' && n.props.source !== ''); }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ', bare = ' . json_encode(_pcsc_tree()) . ";\n"
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
	pg_assert_same(_pcsc_norm(json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true)), _pcsc_norm($answer['tree']), 'the same tree');
	pg_assert_same(_pcsc_norm(json_decode(pg_sw_default_parts_apply(json_encode(_pcsc_tree()), 'shopping_cart'), true)), _pcsc_norm($answer['bare']), 'the same tail');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _pcsc_norm($node)
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
		$node['children'] = array_map('_pcsc_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

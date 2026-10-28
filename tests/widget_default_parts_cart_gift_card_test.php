<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart's gift card part (includes/fn/widgets_parts.php,
 * includes/fn/widgets_lists_cart.php): a gift card line's recipient
 * controls are drawn by real design elements, each card from the
 * cart.item_gift_cards list, with the same words, classes, markers and
 * control names as the block the cart built before; what a control sends
 * and whether it is required stay the system's; a deleted required control
 * comes back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pcgc_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

// A gift card line of two units (the second one's values typed by the
// visitor), a plain line.
function _pcgc_items()
{
	$card = function ($iid, $n, $count, $values) {
		return array_merge(array('number' => $n, 'count' => $count, 'prefix' => 'order_item_' . $iid . '_quantity_number_' . $n . '_gift_card_',
			'recipient_email_address' => '', 'from_name' => '', 'message' => '', 'delivery_date' => ''), $values);
	};
	return array(
		array('item_id' => 21, 'name' => 'Gift card', 'gift_cards' => array(
			$card(21, 1, 2, array('recipient_email_address' => 'amy@example.com', 'from_name' => 'Bob & Co', 'message' => "Happy <day>\nlove", 'delivery_date' => '2026-11-02')),
			$card(21, 2, 2, array()),
		)),
		array('item_id' => 22, 'name' => 'Mug', 'gift_cards' => array()),
	);
}

// The block the cart built before the part (widgets_cart.php).
function _pcgc_old_html($item, $form)
{
	if (!$item['gift_cards']) return '';
	$fa = ' form="' . $form . '"';
	$out = '';
	foreach ($item['gift_cards'] as $c) {
		$fp = $c['prefix'];
		$field = function ($suffix, $label, $control, $required = false, $help = '') use ($fp) {
			return '<div class="mb-3 pg-cart-gc-field" data-pg-gc-field="' . h($suffix) . '">'
				. '<label class="form-label" for="' . h($fp . $suffix) . '">' . h($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
				. $control . ($help !== '' ? '<div class="form-text">' . h($help) . '</div>' : '') . '</div>';
		};
		$out .= '<fieldset class="pg-cart-gc-set border rounded p-3 mb-3" data-pg-gc-set="' . $c['number'] . '">'
			. '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h(lang('Gift card')) . ($c['count'] > 1 ? ' (' . $c['number'] . ' / ' . $c['count'] . ')' : '') . '</legend>'
			. $field('recipient_email_address', lang('Recipient Email'), '<input type="email" required class="form-control"' . $fa . ' id="' . $fp . 'recipient_email_address" name="' . $fp . 'recipient_email_address" value="' . h($c['recipient_email_address']) . '">', true)
			. $field('from_name', lang('From Name'), '<input type="text" class="form-control"' . $fa . ' id="' . $fp . 'from_name" name="' . $fp . 'from_name" value="' . h($c['from_name']) . '" maxlength="100">')
			. $field('message', lang('Message'), '<textarea class="form-control" rows="3"' . $fa . ' id="' . $fp . 'message" name="' . $fp . 'message" maxlength="500">' . h($c['message']) . '</textarea>')
			. $field('delivery_date', lang('Delivery Date'), '<input type="date" class="form-control"' . $fa . ' id="' . $fp . 'delivery_date" name="' . $fp . 'delivery_date" value="' . h($c['delivery_date']) . '">', false, lang('Leave blank to send immediately.'))
			. '</fieldset>';
	}
	return '<div class="pg-cart-gift-cards" data-pg-cart-gc-item="' . $item['item_id'] . '">' . $out . '</div>';
}

function _pcgc_tree($more = array())
{
	return _pcgc_node('root', array(), array(
		_pcgc_node('loop_area', array(), array_merge(array(
			_pcgc_node('semantic', array('tag' => 'div', 'cssClass' => 'py-3', '_bindings' => array('text' => '__item_name'))),
		), $more)),
	));
}

// $tree given its parts as the widget door does, its controls wired as the
// cart wires them, drawn and finished as the cart draws it.
function _pcgc_render($tree)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true);
	_apply_shopping_cart_bindings($tree, array('cart_form_id' => 'pg-cart-form-w9'));
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => 'shopping_cart', 'email' => false));
	try {
		$split = _split_widget_tree($tree);
		$loop = '';
		foreach ($split['loop_children'] as $child) $loop .= _render_tree_node($child, 0, 0);
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		pg_sw_list_publish('cart', pg_sw_list_cart_rows(_pcgc_items()));
		$out = '';
		foreach (_pcgc_items() as $item) {
			$out .= pg_sw_sweep_tokens(pg_sw_fill_longest(trim($loop), array('__item_id' => (string)$item['item_id'], '__item_name' => h($item['name']))));
		}
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest(str_replace('<!--pg-loop-slot-->', $out, $static), array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _pcgc_old()
{
	$out = '';
	foreach (_pcgc_items() as $item) $out .= '<div class="py-3">' . h($item['name']) . '</div>' . _pcgc_old_html($item, 'pg-cart-form-w9');
	return $out;
}

function _pcgc_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pcgc_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map(function ($c) { return trim(str_replace('pg-hide-if-empty', '', $c)); }, $m[1]), 'strlen'));
}

// What the cart reads and page scripts look for: each control's tag, name,
// type, value, form, required and maxlength, a text area's text, the
// data-pg-* markers; ids left out (made unique per row either way).
function _pcgc_controls($html)
{
	preg_match_all('/<input\b([^>]*)>|<textarea\b([^>]*)>(.*?)<\/textarea>/s', $html, $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $c) {
		$textarea = isset($c[2]) && $c[2] !== '';
		preg_match_all('/\s([a-z-]+)(?:="([^"]*)")?/', $textarea ? $c[2] : $c[1], $a, PREG_SET_ORDER);
		$attrs = array();
		foreach ($a as $x) if (in_array($x[1], array('type', 'name', 'value', 'form', 'required', 'maxlength'), true)) $attrs[$x[1]] = isset($x[2]) ? $x[2] : '';
		ksort($attrs);
		$out[] = ($textarea ? 'textarea ' : 'input ') . json_encode($attrs) . ($textarea ? ' ' . trim($c[3]) : '');
	}
	preg_match_all('/\s(data-pg-(?:gc|cart-gc)[a-z-]*)="([^"]*)"/', $html, $d, PREG_SET_ORDER);
	foreach ($d as $x) $out[] = $x[1] . '=' . $x[2];
	return $out;
}

function test_widget_parts_cart_gift_card_rows()
{
	$rows = pg_sw_list_cart_rows(_pcgc_items());
	pg_assert_same(array(21), array_keys($rows['cart.item_gift_cards']), 'only the gift card line');
	pg_assert_same(array('1', '2'), array_column($rows['cart.item_gift_cards'][21], '__gift_card_number'));
	pg_assert_same('order_item_21_quantity_number_2_gift_card_', $rows['cart.item_gift_cards'][21][1]['__gift_card_field']);
	$sources = pg_sw_list_sources();
	pg_assert_same(array_column($sources['cart.item_gift_cards']['tokens'], 0), array_keys($rows['cart.item_gift_cards'][21][0]), 'the source tokens');
	pg_assert_true(isset(pg_sw_kind_lists('shopping_cart')['cart.item_gift_cards']), 'the cart offers it');
	pg_assert_true(!isset(pg_sw_kind_lists('cart_link')['cart.item_gift_cards']), 'the cart link does not');
}

function test_widget_parts_cart_gift_card_matches_the_old_block()
{
	$old = _pcgc_old();
	foreach (array(
		'the old token' => _pcgc_tree(array(_pcgc_node('content', array('contentType' => 'text', 'text' => '^^__item_gift_card_html^^')))),
		'a layout from before the token' => _pcgc_tree(),
	) as $case => $tree) {
		$new = _pcgc_render($tree);
		pg_assert_same(_pcgc_visible($old), _pcgc_visible($new), $case . ': same words in the same order');
		pg_assert_same(_pcgc_classes($old), _pcgc_classes($new), $case . ': same classes in the same order');
		pg_assert_same(_pcgc_controls($old), _pcgc_controls($new), $case . ': same controls and markers');
		pg_assert_same(4, preg_match_all('/<label class="form-label" for="order_item_21_quantity_number_1_gift_card_[a-z_]+_pgw/', $new), $case . ': each label names its control');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $case . ': nothing left');
	}
}

// The design's look is the designer's; what a control sends is not.
function test_widget_parts_cart_gift_card_controls_are_the_systems()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcgc_tree()), 'shopping_cart'), true);
	$set = &$tree['children'][0]['children'][2]['children'][0]['children'][0];
	pg_assert_same('pg-cart-gc-set border rounded p-3 mb-3', $set['props']['cssClass']);
	// The designer restyles the e-mail box, names it, and deletes the from
	// name field.
	$email = &$set['children'][1]['children'][1];
	$email['props']['cssClass'] = 'form-control form-control-lg';
	$email['props']['_attrs'] = array(array('name' => 'name', 'value' => 'mine'), array('name' => 'placeholder', 'value' => 'name@example.com'));
	unset($email);
	unset($set['children'][2]);
	unset($set);
	$new = _pcgc_render($tree);
	pg_assert_contains('class="form-control form-control-lg"', $new);
	pg_assert_contains('placeholder="name@example.com"', $new, 'its own attribute kept');
	pg_assert_contains('name="order_item_21_quantity_number_1_gift_card_recipient_email_address"', $new, 'the name the cart reads');
	pg_assert_true(strpos($new, 'name="mine"') === false, 'not the design\'s name');
	pg_assert_true(strpos($new, 'from_name') === false, 'a deleted optional field stays deleted');

	// The required control deleted: it comes back in the card.
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcgc_tree()), 'shopping_cart'), true);
	unset($tree['children'][0]['children'][2]['children'][0]['children'][0]['children'][1]);
	$new = _pcgc_render($tree);
	pg_assert_same(2, preg_match_all('/<input[^>]*type="email"[^>]*required/', $new), 'one required e-mail box per card');
	pg_assert_same(2, substr_count($new, 'data-pg-gc-field="recipient_email_address"'));
}

function test_widget_parts_cart_gift_card_placed_beats_pushed()
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pcgc_tree()), 'shopping_cart'), true);
	pg_assert_same(4, count($tree['children'][0]['children']), 'the row, the product form, then the part and the schedule\'s');
	pg_assert_same('cart.item_gift_cards', $tree['children'][0]['children'][2]['children'][0]['props']['source']);
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'shopping_cart'), 'a tree with its part is left as it is');
	$own = json_encode(_pcgc_tree(array(_pcgc_node('loop_area', array('source' => 'cart.item_gift_cards'), array(
		_pcgc_node('semantic', array('tag' => 'input', '_bindings' => array('value' => 'gift_card_email'))),
	)))));
	pg_assert_same(1, substr_count(pg_sw_default_parts_apply($own, 'shopping_cart'), 'cart.item_gift_cards'), 'a row listing the cards itself gets no second copy');
}

// The editor's part (style_designer.js) is the server's.
function test_widget_parts_cart_gift_card_editor_twin()
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
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartPlaced', '_sdPartItemLegend',
		'_sdPartCartGiftCardField', '_sdPartCartItemGiftCard', '_sdPartCartScheduleField', '_sdPartCartItemSchedule',
		'_sdPartValue', '_sdPartCartFormControl', '_sdPartCartItemForm', '_sdMainLoop', '_sdDefaultLoopTails') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _pcgc_tree(array(_pcgc_node('content', array('contentType' => 'paragraph', 'text' => '^^__item_gift_card_html^^'))));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function _sdListIsSourced(n) { return !!(n && n.type === 'loop_area' && n.props && typeof n.props.source === 'string' && n.props.source !== ''); }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ', bare = ' . json_encode(_pcgc_tree()) . ";\n"
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
	pg_assert_same(_pcgc_norm(json_decode(pg_sw_default_parts_apply(json_encode($tree), 'shopping_cart'), true)), _pcgc_norm($answer['tree']), 'the same tree');
	pg_assert_same(_pcgc_norm(json_decode(pg_sw_default_parts_apply(json_encode(_pcgc_tree()), 'shopping_cart'), true)), _pcgc_norm($answer['bare']), 'the same tail');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _pcgc_norm($node)
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
		$node['children'] = array_map('_pcgc_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

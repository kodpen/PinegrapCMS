<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order view's item form and gift card parts
 * (includes/fn/widgets_parts.php, includes/fn/widgets_lists_order.php):
 * the answers a buyer gave in a product's order form and a gift card's
 * details are drawn by real design elements in each item row, showing the
 * visitor the same words, classes and order as the blocks the renderer
 * built before; the editor builds the same parts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pitem_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

// The product's form fields, its saved answers (two units of 'One Form per
// Quantity'), and a gift card item's cards (the second unit not filled in).
function _pitem_fields()
{
	return array(
		array('id' => 1, 'label' => 'Attendee <name>', 'type' => 'text box', 'information' => '', 'wysiwyg' => 0),
		array('id' => 2, 'label' => 'Notes', 'type' => 'text area', 'information' => '', 'wysiwyg' => 0),
		array('id' => 3, 'label' => 'Please note', 'type' => 'information', 'information' => '<p>Bring <b>ID</b></p>', 'wysiwyg' => 0),
		array('id' => 4, 'label' => '', 'type' => 'information', 'information' => '', 'wysiwyg' => 0),
		array('id' => 5, 'label' => 'Sizes', 'type' => 'check box', 'information' => '', 'wysiwyg' => 0),
	);
}

function _pitem_data()
{
	return array(
		array('form_field_id' => 1, 'data' => 'Ada & Lin', 'quantity_number' => 1),
		array('form_field_id' => 2, 'data' => "line one\nline two", 'quantity_number' => 1),
		array('form_field_id' => 5, 'data' => 'S', 'quantity_number' => 1),
		array('form_field_id' => 5, 'data' => 'M', 'quantity_number' => 1),
		array('form_field_id' => 1, 'data' => 'Grace', 'quantity_number' => 2),
	);
}

function _pitem_cards()
{
	return array(
		array('quantity_number' => 1, 'from_name' => 'Bob', 'recipient_email_address' => 'amy@example.com',
			'message' => "Happy <day>\nlove", 'delivery_date' => '2026-11-02'),
		array('quantity_number' => 3, 'from_name' => 'Cy', 'recipient_email_address' => 'dan@example.com',
			'message' => '', 'delivery_date' => '0000-00-00'),
	);
}

// The order view's items: a form product (two units), a gift card (three)
// and a plain product.
function _pitem_items()
{
	return array(
		array('id' => 11, 'product_id' => 5, 'name' => 'Ticket', 'qty' => 2, 'unit_cents' => 1500,
			'has_form' => true, 'form_name' => 'Attendee', 'form_quantity_type' => 'One Form per Quantity', 'is_gift_card' => false),
		array('id' => 12, 'product_id' => 6, 'name' => 'Gift', 'qty' => 3, 'unit_cents' => 2550,
			'has_form' => false, 'form_name' => '', 'form_quantity_type' => '', 'is_gift_card' => true),
		array('id' => 13, 'product_id' => 7, 'name' => 'Mug', 'qty' => 1, 'unit_cents' => 990,
			'has_form' => false, 'form_name' => '', 'form_quantity_type' => '', 'is_gift_card' => false),
	);
}

function _pitem_money($cents)
{
	return '₺' . number_format($cents / 100, 2, ',', '.');
}

function _pitem_date($ymd)
{
	return date('d.m.Y', strtotime($ymd));
}

// The product form block as the order view built it before the part.
function _pitem_old_form_html($item, $fields, $data)
{
	if (!$fields || !$data) return '';
	$by_q = array();
	foreach ($data as $r) $by_q[max(1, (int)$r['quantity_number'])][(int)$r['form_field_id']][] = (string)$r['data'];
	$per = $item['form_quantity_type'] === 'One Form per Quantity';
	$n = $per ? min(100, max(1, (int)$item['qty'])) : 1;
	$multi = $per && $n > 1;
	$legend = $item['form_name'];
	$out = '<div class="pg-ov-item-form mt-2" data-pg-ov-item-form="' . $item['id'] . '">';
	for ($q = 1; $q <= $n; $q++) {
		$vals = isset($by_q[$q]) ? $by_q[$q] : array();
		$out .= '<fieldset class="pg-ov-item-form-block border rounded p-3 mt-2">';
		if ($legend !== '' || $multi) {
			$out .= '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h($legend) . ($multi ? ' (' . $q . ' / ' . $n . ')' : '') . '</legend>';
		}
		foreach ($fields as $f) {
			if ($f['type'] === 'information') {
				if ($f['label'] !== '' || $f['information'] !== '') {
					$out .= '<div class="mb-2 small">';
					if ($f['label'] !== '') $out .= '<div class="fw-semibold">' . h($f['label']) . '</div>';
					if ($f['information'] !== '') $out .= '<div class="text-muted">' . $f['information'] . '</div>';
					$out .= '</div>';
				}
				continue;
			}
			$d = isset($vals[$f['id']]) ? implode(', ', $vals[$f['id']]) : '';
			$out .= '<div class="mb-2"><div class="form-label mb-0 fw-semibold">' . h($f['label']) . '</div>'
				. '<div class="pg-ov-form-value">' . prepare_form_data_for_output($d, $f['type'], true) . '</div></div>';
		}
		$out .= '</fieldset>';
	}
	return $out . '</div>';
}

// The gift card block as the order view built it before the part.
function _pitem_old_gift_html($item, $cards)
{
	$by_q = array();
	foreach ($cards as $r) $by_q[max(1, (int)$r['quantity_number'])] = $r;
	$n = min(100, max(1, (int)$item['qty']));
	$line = function ($label, $html) {
		return '<div class="mb-2"><div class="form-label mb-0 fw-semibold">' . h($label) . '</div><div class="pg-ov-form-value">' . $html . '</div></div>';
	};
	$out = '<div class="pg-ov-item-gift-card mt-2" data-pg-ov-item-gift-card="' . $item['id'] . '">';
	for ($q = 1; $q <= $n; $q++) {
		if (!isset($by_q[$q])) continue;
		$gc = $by_q[$q];
		$d = ($gc['delivery_date'] === '0000-00-00') ? lang('Immediate') : _pitem_date($gc['delivery_date']);
		$out .= '<fieldset class="pg-ov-item-gift-card-block border rounded p-3 mt-2">'
			. '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h(lang('Gift card')) . ($n > 1 ? ' (' . $q . ' / ' . $n . ')' : '') . '</legend>'
			. $line(lang('Amount'), '<strong>' . h(_pitem_money($item['unit_cents'])) . '</strong>')
			. $line(lang('Recipient Email'), h($gc['recipient_email_address']))
			. $line(lang('Your Name'), h($gc['from_name']))
			. $line(lang('Message'), nl2br(h($gc['message'])))
			. $line(lang('Delivery Date'), h($d))
			. '</fieldset>';
	}
	return $out . '</div>';
}

// An item row of the layout: its name, then $more.
function _pitem_tree($more = array())
{
	return _pitem_node('root', array(), array(
		_pitem_node('semantic', array('tag' => 'h5', 'text' => 'Items')),
		_pitem_node('loop_area', array(), array_merge(array(
			_pitem_node('semantic', array('tag' => 'div', 'cssClass' => 'py-3', '_bindings' => array('text' => '__item_name'))),
		), $more)),
	));
}

// $tree given its parts as the widget door does, drawn and finished as the
// order view draws it: the loop once per item, the item lists published.
function _pitem_render($tree, $email = false)
{
	$json = pg_sw_default_parts_apply(json_encode($tree), 'order_view', array(), $email);
	$tree = json_decode($json, true);
	// The cards added after an order view show only by their own flags.
	_eo_apply_visibility_bindings($tree, array());
	$scope = pg_sw_finish_open(array('sid' => 46, 'kind' => 'order_view', 'email' => $email));
	try {
		$split = _split_widget_tree($tree);
		$loop = '';
		foreach ($split['loop_children'] as $child) $loop .= _render_tree_node($child, 0, 0);
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		$items = _pitem_items();
		$rows = pg_sw_list_order_item_parts_rows($items, array(5 => _pitem_fields()), array(11 => _pitem_data()),
			array(12 => _pitem_cards()), '_pitem_money', '_pitem_date');
		pg_sw_list_publish('order', pg_sw_list_order_rows(array(), $items, array(), array()) + $rows);
		$out = '';
		foreach ($items as $item) {
			$out .= pg_sw_sweep_tokens(pg_sw_fill_longest(trim($loop), array(
				'__item_id' => (string)$item['id'], '__item_name' => h($item['name']),
				'__item_has_form_data' => !empty($item['has_form_data']) ? '1' : '',
				'__item_has_gift_card' => !empty($item['has_gift_card']) ? '1' : '',
			)));
		}
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest(str_replace('<!--pg-loop-slot-->', $out, $static), array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

// The old output: each row's name, then its blocks.
function _pitem_old_html()
{
	$out = '<h5>Items</h5>';
	foreach (_pitem_items() as $item) {
		$out .= '<div class="py-3">' . h($item['name']) . '</div>';
		if ($item['has_form']) $out .= _pitem_old_form_html($item, _pitem_fields(), _pitem_data());
		if ($item['is_gift_card']) $out .= _pitem_old_gift_html($item, _pitem_cards());
	}
	return $out;
}

// The visible text: tags out (a tag reads as a space), entities decoded,
// white space collapsed.
function _pitem_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pitem_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

// The markers page scripts look for: data-pg-ov-item-* with their values.
function _pitem_markers($html)
{
	preg_match_all('/\s(data-pg-ov-item-[a-z-]+)="([^"]*)"/', $html, $m, PREG_SET_ORDER);
	return array_map(function ($x) { return $x[1] . '=' . $x[2]; }, $m);
}

function test_widget_parts_item_rows()
{
	$items = _pitem_items();
	$rows = pg_sw_list_order_item_parts_rows($items, array(5 => _pitem_fields()), array(11 => _pitem_data()),
		array(12 => _pitem_cards()), '_pitem_money', '_pitem_date');
	pg_assert_same(array(11), array_keys($rows['order.item_forms']), 'forms of the form product');
	pg_assert_same(array('11001', '11002'), array_column($rows['order.item_forms'][11], '__form_key'), 'a block per unit');
	pg_assert_same(array('1', '1'), array_column($rows['order.item_forms'][11], '__form_has_legend'));
	pg_assert_same(array('Attendee <name>', 'Notes', 'Please note', 'Sizes'), array_column($rows['order.item_form_fields'][11001], '__field_label'),
		'an empty information field gives no row');
	pg_assert_same('S, M', $rows['order.item_form_fields'][11001][3]['__field_value_html'], 'several values joined');
	pg_assert_same('', $rows['order.item_form_fields'][11002][1]['__field_value_html'], 'an unanswered field reads empty');
	pg_assert_same(array('1', '3'), array_column($rows['order.item_gift_cards'][12], '__gift_card_number'), 'cards filled in');
	pg_assert_same('₺25,50', $rows['order.item_gift_cards'][12][0]['__gift_card_amount'], 'the unit price, minor units formatted');
	pg_assert_same(lang('Immediate'), $rows['order.item_gift_cards'][12][1]['__gift_card_delivery']);
	pg_assert_true(!empty($items[0]['has_form_data']) && !empty($items[1]['has_gift_card']), 'items marked');
	pg_assert_true(empty($items[2]['has_form_data']) && empty($items[2]['has_gift_card']), 'a plain item is not');

	// One form per product: one block, a heading only for a named form.
	$one = array('id' => 4, 'qty' => 3, 'form_name' => '', 'form_quantity_type' => 'One Form per Product');
	$r = pg_sw_list_order_item_form_rows($one, _pitem_fields(), _pitem_data());
	pg_assert_same(array(array('__form_key' => '4001', '__form_name' => '', '__form_number' => '1', '__form_count' => '1', '__form_has_legend' => '')),
		$r['order.item_forms'][4]);
	pg_assert_same(array('order.item_forms' => array(), 'order.item_form_fields' => array()), pg_sw_list_order_item_form_rows($one, _pitem_fields(), array()),
		'no answer saved: no form');

	// Every row carries the source's tokens.
	$sources = pg_sw_list_sources();
	foreach (array('order.item_forms' => $rows['order.item_forms'][11][0], 'order.item_form_fields' => $rows['order.item_form_fields'][11001][0],
		'order.item_gift_cards' => $rows['order.item_gift_cards'][12][0]) as $source => $row) {
		pg_assert_same(array_column($sources[$source]['tokens'], 0), array_keys($row), $source . ' tokens');
	}
}

function test_widget_parts_item_blocks_match_the_old_blocks()
{
	$old = _pitem_old_html();
	foreach (array(
		'the old tokens' => _pitem_tree(array(
			_pitem_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__item_form_data_html'))),
			_pitem_node('content', array('contentType' => 'text', 'text' => '^^__item_gift_card_html^^')),
		)),
		'a layout from before the tokens' => _pitem_tree(),
	) as $case => $tree) {
		$new = _pitem_render($tree);
		pg_assert_same(_pitem_visible($old), _pitem_visible($new), $case . ': same words in the same order');
		$classes = _pitem_classes($new);
		pg_assert_same(_pitem_classes($old), $classes, $case . ': same classes in the same order');
		pg_assert_same(_pitem_markers($old), _pitem_markers($new), $case . ': the markers page scripts read');
		pg_assert_contains('Ada &amp; Lin', $new, $case . ': an answer escaped once');
		pg_assert_contains('Happy &lt;day&gt;<br />', $new, $case . ': the message keeps its line breaks');
		pg_assert_contains('<p>Bring <b>ID</b></p>', $new, $case . ': the information text as written');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $case . ': nothing left');
	}
}

function test_widget_parts_item_blocks_in_an_email()
{
	$new = _pitem_render(_pitem_tree(), true);
	pg_assert_same(_pitem_visible(_pitem_old_html()), _pitem_visible($new), 'same words');
	foreach (array('<style', 'flex', 'position:', '<script') as $unsafe) {
		pg_assert_true(strpos($new, $unsafe) === false, 'nothing a mail client drops: ' . $unsafe);
	}
}

function test_widget_parts_item_blocks_placed_beats_pushed()
{
	// The parts given once; the tree is then left as it is.
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pitem_tree()), 'order_view'), true);
	$loop = $tree['children'][1];
	pg_assert_same(3, count($loop['children']), 'the row, then both parts in the loop');
	pg_assert_same('pg-ov-item-form mt-2', $loop['children'][1]['props']['cssClass']);
	pg_assert_same('pg-ov-item-gift-card mt-2', $loop['children'][2]['props']['cssClass']);
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'order_view'), 'a tree with its parts is left as it is');

	// A designer who deleted one part keeps the other's place: the deleted
	// one comes back, as the old block did.
	unset($tree['children'][1]['children'][2]);
	$tree['children'][1]['children'] = array_values($tree['children'][1]['children']);
	$back = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
	pg_assert_same(3, count($back['children'][1]['children']), 'the gift card part back');

	// A row that lists the forms itself gets no second copy.
	$own = _pitem_tree(array(_pitem_node('loop_area', array('source' => 'order.item_forms'), array(
		_pitem_node('semantic', array('tag' => 'span', '_bindings' => array('text' => '__form_name'))),
	))));
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($own), 'order_view'), true);
	pg_assert_same(3, count($tree['children'][1]['children']), 'the row, its list and the gift card part');
	pg_assert_same('pg-ov-item-gift-card mt-2', $tree['children'][1]['children'][2]['props']['cssClass']);

	// No main loop (the cards placed): nothing.
	$flat = json_encode(_pitem_node('root', array(), array(
		_pitem_node('loop_area', array('source' => 'order.return_deliveries'), array()),
		_pitem_node('semantic', array('tag' => 'a', '_bindings' => array('href' => '__legal_contract_url'))),
		_pitem_node('semantic', array('tag' => 'a', '_bindings' => array('href' => '__legal_kvkk_url'))),
	)));
	pg_assert_same($flat, pg_sw_default_parts_apply($flat, 'order_view'), 'no main loop');
}

// The editor's parts (style_designer.js) are the server's.
function test_widget_parts_item_editor_twin()
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
	$src = $cut("\n    var SD_COND_NAMES = ", ";\n") . $cut("\n    var SD_OV_STATUS_CLASS_DEFAULTS = {", "\n    };\n")
		. $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n")
		. $cut("\n    var SD_OV_STATUS_STAGES = ", ";\n")
		. $cut("\n    var SW_DEFAULT_TAILS = {", "\n    };\n")
		. $cut("\n    var SD_RETURN_BADGE_CLASSES = {", "\n    };\n")
		. $cut("\n    var SW_DEFAULT_LOOP_TAILS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartOrderTimeline', '_sdPartOrderStatusBadge',
		'_sdPartPlaced', '_sdPartValue', '_sdPartItemLegend', '_sdPartItemLine', '_sdPartOrderItemForm', '_sdPartOrderItemGiftCard',
		'_sdMainLoop', '_sdDefaultLoopTails', '_sdDefaultTails', '_sdPartOrderCard', '_sdPartOrderReturns', '_sdPartOrderReturnMoves', '_sdPartOrderReturnMoveSubmit', '_sdPartOrderReturnForm', '_sdPartSentence', '_sdPartOrderReturnQtyCell', '_sdPartOrderReturnReason', '_sdPartOrderReturnSubmit', '_sdPartOrderCancelForm', '_sdPartOrderCancelSubmit', '_sdPartOrderReturnsList', '_sdPartOrderLegalLink', '_sdPartOrderLegal', '_sdPartOrderLegalKvkk') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _pitem_tree(array(
		_pitem_node('content', array('contentType' => 'paragraph', 'cssClass' => 'mb-0', 'text' => '^^__item_form_data_html^^')),
	));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\n"
		. "function _sdT(s) { return s; }\nfunction _sdDateSample() { return '20.10.2026'; }\nfunction _sdMoney() { return '100,00'; }\n"
		. "function _sdListIsSourced(n) { return !!(n && n.type === 'loop_area' && n.props && typeof n.props.source === 'string' && n.props.source !== ''); }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ";\n"
		. "var changed = _sdDefaultParts(tree, 'order_view', null);\n"
		. "var tails = _sdDefaultLoopTails(tree, 'order_view', null) && _sdDefaultTails(tree, 'order_view', null);\n"
		. "console.log(JSON.stringify({ changed: changed && tails, again: _sdDefaultParts(tree, 'order_view', null) || _sdDefaultLoopTails(tree, 'order_view', null) || _sdDefaultTails(tree, 'order_view', null), tree: tree }));\n";
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
	pg_assert_true($answer['changed'], 'the editor gave the parts');
	pg_assert_false($answer['again'], 'a tree with its parts has nothing left to give');

	$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
	pg_assert_same(_pitem_norm($server), _pitem_norm($answer['tree']), 'the same tree');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _pitem_norm($node)
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
		$node['children'] = array_map('_pitem_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

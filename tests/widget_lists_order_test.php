<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order view's and the my account widget's lists
 * (includes/fn/widgets_lists_order.php): an order's recipients, items,
 * parcels and steps, and a member's orders, drawn by the finish pass from
 * the rows the renderers publish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_ord_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _widget_ord_box($tag, $class, $children = array(), $extra = array())
{
	return _widget_ord_node('semantic', array('tag' => $tag, 'cssClass' => $class) + $extra, $children);
}

function _widget_ord_text($tag, $class, $token, $extra = array())
{
	return _widget_ord_box($tag, $class, array(), array('text' => 'Sample', '_bindings' => array('text' => $token)) + $extra);
}

function _widget_ord_list($source, $children, $props = array())
{
	return _widget_ord_node('loop_area', array('source' => $source) + $props, $children);
}

function _widget_ord_texts($html, $class)
{
	preg_match_all('#<[a-z][a-z0-9]* class="' . preg_quote($class, '#') . '"[^>]*>(.*?)</#s', $html, $m);
	return $m[1];
}

// An item as the order view's renderer builds it.
function _widget_ord_item($id, $name, $ship_to, $extra = array())
{
	return $extra + array(
		'id' => $id, 'product_id' => $id + 100, 'name' => $name, 'item_number' => $name, 'short_description' => '',
		'full_description' => '', 'qty' => 1, 'unit_price' => '₺10,00', 'line_total' => '₺10,00', 'image' => '',
		'url' => '', 'ship_to' => '', 'ship_to_id' => $ship_to,
	);
}

// An order of two recipients: items 1 and 2 to Ayşe (ship_to 5), item 3
// to Can (ship_to 6), item 4 digital; one parcel each; three steps.
function _widget_ord_rows()
{
	$arrival = function ($ymd) { return date('d.m.Y', strtotime($ymd)); };
	$shipments = array(
		pg_sw_list_order_shipment_row(array('id' => 5, 'first_name' => 'Ayşe', 'last_name' => 'Kaya', 'city' => 'Izmir', 'state' => 'IZMIR',
			'zip_code' => '35000', 'country' => 'TR', 'address_1' => 'Çiçek Sk. 1', 'arrival_date' => '2026-10-30', 'shipping_method' => 'Kargo'), $arrival),
		pg_sw_list_order_shipment_row(array('id' => 6, 'first_name' => 'Can', 'last_name' => '', 'company' => 'A & B', 'arrival_date' => '0000-00-00'), $arrival),
	);
	$items = array(
		_widget_ord_item(1, 'Mug', 5), _widget_ord_item(2, 'Tea "Earl"', 5), _widget_ord_item(3, 'Pot', 6), _widget_ord_item(4, 'E-book', 0),
	);
	$parcels = array(
		array('number' => 'P5', 'url' => 'https://t.example/?n=P5&x=1', 'carrier' => 'Yurtiçi', 'ship_to' => 5),
		array('number' => 'P6', 'url' => '', 'carrier' => '', 'ship_to' => 6),
	);
	$steps = array(
		array('icon' => 'bi-receipt', 'label' => 'Order Created', 'ts' => '20.10.2026 10:00', 'kind' => 'created'),
		array('label' => 'Estimated shipping', 'ts' => '28.10.2026', 'kind' => 'shipped', 'estimate' => true),
		array('label' => 'Cancelled — late', 'step_label' => 'Cancelled', 'note' => 'late', 'ts' => '21.10.2026 09:00', 'kind' => 'cancelled'),
		array('label' => 'No date', 'ts' => '', 'kind' => 'paid'),
	);
	return pg_sw_list_order_rows($shipments, $items, $parcels, $steps);
}

// What a renderer does: the tree drawn in a scope of $kind, the rows
// published, the static values filled, swept, then the finish pass.
function _widget_ord_finished($tree, $kind, $family, $rows, $static)
{
	$scope = pg_sw_finish_open(array('sid' => 43, 'kind' => $kind, 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish($family, $rows);
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, $static));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function test_widget_lists_order_sources()
{
	$sources = pg_sw_list_order_sources();
	$order = array('order.shipments', 'order.items', 'order.parcels', 'order.steps', 'order.item_forms', 'order.item_form_fields', 'order.item_gift_cards');
	pg_assert_same(array_merge($order, array('account.orders')), array_keys($sources));
	foreach ($sources as $source => $d) {
		pg_assert_same(array('family', 'key', 'label', 'arg', 'arg_required', 'tokens'), array_keys($d), $source . ' shape');
		pg_assert_true(isset(pg_sw_list_sources()[$source]), $source . ' is a list source');
	}
	pg_assert_same($order, array_keys(pg_sw_kind_lists('order_view')));
	pg_assert_same(array('account.orders'), array_keys(pg_sw_kind_lists('my_account')));
	pg_assert_same(array(), pg_sw_kind_lists('address_book'));
	pg_assert_true(!isset(pg_sw_kind_lists('calendar_view')['order.items']), 'no order list in a calendar');

	// Every row carries exactly its source's tokens.
	$rows = _widget_ord_rows();
	$rows += pg_sw_list_account_rows(7, array(array('id' => 9, 'order_number' => '1009', 'order_date' => 1792400000, 'total' => '12.50', 'status' => 'complete')), '/view-order', 'd.m.Y');
	foreach (array('order.shipments' => 0, 'order.items' => 0, 'order.parcels' => 0, 'order.steps' => 0, 'account.orders' => 7) as $source => $key) {
		$tokens = array();
		foreach ($sources[$source]['tokens'] as $pair) $tokens[] = $pair[0];
		pg_assert_true(!empty($rows[$source][$key]), $source . ' gives rows');
		foreach ($rows[$source][$key] as $row) pg_assert_same($tokens, array_keys($row), $source . ' row keys');
	}
}

function test_widget_lists_order_rows()
{
	$rows = _widget_ord_rows();
	pg_assert_same(array('Mug', 'Tea "Earl"', 'Pot', 'E-book'), array_column($rows['order.items'][0], '__item_name'));
	pg_assert_same(array('Mug', 'Tea "Earl"'), array_column($rows['order.items'][5], '__item_name'));
	pg_assert_same(array('Pot'), array_column($rows['order.items'][6], '__item_name'));
	pg_assert_same(array('5', '6'), array_column($rows['order.shipments'][0], '__ship_to_key'));
	pg_assert_same(array('2', '1'), array_column($rows['order.shipments'][0], '__ship_to_item_count'));
	pg_assert_true(!isset($rows['order.shipments'][5]), 'recipients only under the whole order');

	$a = $rows['order.shipments'][0][0];
	pg_assert_same('Ayşe Kaya', $a['__shipping_name']);
	pg_assert_same('Ayşe Kaya, Çiçek Sk. 1, 35000 Izmir, TR', $a['__shipping_full_address'], 'a state named like its city once');
	pg_assert_same('30.10.2026', $a['__arrival_date']);
	pg_assert_same('Kargo', $a['__shipping_method']);
	pg_assert_same('', $rows['order.shipments'][0][1]['__arrival_date'], 'no arrival date');

	pg_assert_same(array('P5', 'P6'), array_column($rows['order.parcels'][0], '__tracking_code'));
	pg_assert_same(array('P6'), array_column($rows['order.parcels'][6], '__tracking_code'));

	pg_assert_same(array('created', 'shipped', 'cancelled'), array_column($rows['order.steps'][0], '__step_kind'), 'a step without a date is left out');
	pg_assert_same(array('', '1', ''), array_column($rows['order.steps'][0], '__step_is_estimate'));
	pg_assert_same(array('Order Created', 'Estimated shipping', 'Cancelled'), array_column($rows['order.steps'][0], '__step_label'));
	pg_assert_same('late', $rows['order.steps'][0][2]['__step_note']);

	$acc = pg_sw_list_account_rows('7', array(
		array('id' => 9, 'order_number' => '1009', 'order_date' => mktime(12, 0, 0, 10, 20, 2026), 'total' => '12.50', 'status' => 'cancelled'),
	), '', 'd.m.Y');
	$o = $acc['account.orders'][7][0];
	pg_assert_same(array('9', '1009', '20.10.2026', '1', '#'), array($o['__order_id'], $o['__order_no'], $o['__order_date'], $o['__order_is_cancelled'], $o['__order_url']));
	pg_assert_same(false, strpos($o['__order_total'], '&'), 'the total as plain text');
	$acc = pg_sw_list_account_rows(7, array(array('id' => 9, 'status' => 'complete')), '/view-order', 'd.m.Y');
	pg_assert_same('/view-order?id=9', $acc['account.orders'][7][0]['__order_url']);
	pg_assert_same('', $acc['account.orders'][7][0]['__order_is_cancelled']);
}

// Recipients as cards, each with its items and parcels; the order's own
// items and steps beside them.
function test_widget_lists_order_view_end_to_end()
{
	$item = _widget_ord_box('li', 'item', array(_widget_ord_text('span', 'iname', '__item_name')));
	$parcel = _widget_ord_box('a', 'parcel', array(), array('href' => '#', 'text' => 'P', '_bindings' => array('href' => '__tracking_link', 'text' => '__tracking_code')));
	$tree = _widget_ord_node('root', array(), array(
		_widget_ord_text('h2', 'no', '__order_no'),
		_widget_ord_list('order.shipments', array(_widget_ord_box('div', 'card', array(
			_widget_ord_text('h3', 'sname', '__shipping_name'),
			_widget_ord_text('p', 'order-no', '__order_no'),
			_widget_ord_box('ul', 'items', array(_widget_ord_list('order.items', array($item)))),
			_widget_ord_box('div', 'parcels', array(_widget_ord_list('order.parcels', array($parcel)))),
		)))),
		_widget_ord_box('ul', 'all', array(_widget_ord_list('order.items', array(_widget_ord_text('li', 'all-item', '__item_name'))))),
		_widget_ord_box('ol', 'steps', array(_widget_ord_list('order.steps', array(_widget_ord_box('li', 'step', array(
			_widget_ord_text('span', 'slabel', '__step_label'),
			_widget_ord_box('span', 'est', array(), array('text' => 'Estimated', '_bindings' => array('show_if' => '__step_is_estimate'))),
		)))))),
	));
	$out = _widget_ord_finished($tree, 'order_view', 'order', _widget_ord_rows(), array('__order_no' => '1001', '__ship_to_key' => '0'));
	pg_assert_same(false, strpos($out, 'pg-list'), 'no span left');
	pg_assert_same(false, strpos($out, '^^'), 'no token left');
	pg_assert_same(array('Ayşe Kaya', 'Can'), _widget_ord_texts($out, 'sname'));
	pg_assert_same(array('1001', '1001'), _widget_ord_texts($out, 'order-no'), 'a recipient row reads the order through its carrier');
	pg_assert_same(array('Mug', 'Tea &quot;Earl&quot;', 'Pot'), _widget_ord_texts($out, 'iname'), 'each recipient its own items, escaped once');
	pg_assert_same(array('Mug', 'Tea &quot;Earl&quot;', 'Pot', 'E-book'), _widget_ord_texts($out, 'all-item'), 'outside a recipient every item');
	pg_assert_true(strpos($out, 'href="https://t.example/?n=P5&amp;x=1"') !== false, 'the parcel link');
	pg_assert_same(2, substr_count($out, 'class="parcel"'));
	pg_assert_same(array('Order Created', 'Estimated shipping', 'Cancelled'), _widget_ord_texts($out, 'slabel'));
	pg_assert_same(1, substr_count($out, 'class="est"'), 'the estimate badge on the estimated step only');

	// Not published (no such list wanted elsewhere), or a recipient list
	// inside a recipient: nothing drawn.
	$out = _widget_ord_finished($tree, 'order_view', 'order', array(), array('__order_no' => '1001', '__ship_to_key' => '0'));
	pg_assert_same(array(), _widget_ord_texts($out, 'sname'));
	// The same tree in another kind draws no list.
	$out = _widget_ord_finished($tree, 'calendar_view', 'order', _widget_ord_rows(), array('__order_no' => '1001', '__ship_to_key' => '0'));
	pg_assert_same(array(), _widget_ord_texts($out, 'all-item'));
}

function test_widget_lists_order_account_end_to_end()
{
	$tree = _widget_ord_node('root', array(), array(
		_widget_ord_box('div', 'history', array(_widget_ord_list('account.orders', array(
			_widget_ord_box('a', 'order', array(_widget_ord_text('span', 'ono', '__order_no')), array('href' => '#', '_bindings' => array('href' => '__order_url'))),
		), array('limit' => 1)))),
	));
	$rows = pg_sw_list_account_rows(7, array(
		array('id' => 9, 'order_number' => '1009', 'order_date' => 1792400000, 'total' => '12.50', 'status' => 'complete'),
		array('id' => 8, 'order_number' => '1008', 'order_date' => 1792300000, 'total' => '5', 'status' => 'complete'),
	), '/view-order', 'd.m.Y');
	$out = _widget_ord_finished($tree, 'my_account', 'account', $rows, array('account_id' => '7'));
	pg_assert_same(array('1009'), _widget_ord_texts($out, 'ono'), 'the newest order, the limit read');
	pg_assert_true(strpos($out, 'href="/view-order?id=9"') !== false, 'the order link');
	// Another member's key reads no rows.
	$out = _widget_ord_finished($tree, 'my_account', 'account', $rows, array('account_id' => '8'));
	pg_assert_same(array(), _widget_ord_texts($out, 'ono'));
}

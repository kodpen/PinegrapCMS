<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order view's returns list part
 * (includes/fn/widgets_parts.php, includes/fn/widgets_lists_order.php):
 * the order's return requests are drawn by real design elements, showing
 * the customer the same words, classes and forms as the list returns.php
 * built before; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pret_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _pret_form()
{
	return array('action' => '/software/order_return.php', 'send_to' => '/order?id=9', 'token' => '<input type="hidden" name="token" value="t">', 'order_id' => 9);
}

// Four requests: asked (a note over two lines, may be withdrawn), approved
// with a carrier and a code (may be marked sent), approved with nothing
// (no lines, no date asked), refunded (a tracking number, a refund date).
function _pret_returns()
{
	$base = array(
		'id' => 5, 'status' => 'requested', 'status_label' => 'Return requested',
		'requested' => '19 October 2026', 'lines' => array(array('name' => 'Mug <XL>', 'quantity' => 2), array('name' => 'Cup', 'quantity' => 1)),
		'reason_label' => 'It arrived damaged', 'customer_note' => "Box <torn>\nsorry", 'decision_note' => '',
		'carrier' => '', 'carrier_code' => '', 'tracking_number' => '', 'refund_deadline' => '',
		'can_withdraw' => true, 'can_mark_sent' => false,
	);
	return array(
		$base,
		array_merge($base, array('id' => 6, 'status' => 'approved', 'status_label' => 'Return approved',
			'carrier' => 'Yurtiçi Kargo', 'carrier_code' => 'IADE<77>', 'decision_note' => 'Use the code.',
			'customer_note' => '', 'can_withdraw' => false, 'can_mark_sent' => true)),
		array_merge($base, array('id' => 7, 'status' => 'approved', 'status_label' => 'Return approved',
			'requested' => '', 'lines' => array(), 'reason_label' => '', 'customer_note' => '', 'can_withdraw' => false)),
		array_merge($base, array('id' => 8, 'status' => 'refunded', 'status_label' => 'Money refunded',
			'customer_note' => '', 'tracking_number' => 'TR123', 'refund_deadline' => '2 November 2026', 'can_withdraw' => false)),
	);
}

function test_widget_parts_returns_rows()
{
	$rows = pg_sw_list_order_return_rows(_pret_returns(), _pret_form());
	pg_assert_same(array(0), array_keys($rows['order.returns']), 'the whole order\'s');
	pg_assert_same(array('5', '6', '7', '8'), array_column($rows['order.returns'][0], '__return_id'));
	$r = $rows['order.returns'][0];
	pg_assert_same(lang(array('string' => 'Return request #{var:1}', 'vars' => array(5))), $r[0]['__return_title']);
	pg_assert_same(lang(array('string' => 'Asked on {var:1}', 'vars' => array('19 October 2026'))), $r[0]['__return_asked']);
	pg_assert_same('', $r[2]['__return_asked'], 'no date asked');
	pg_assert_same(array('1', '1', '', '1'), array_column($r, '__return_has_lines'));
	pg_assert_same('Box &lt;torn&gt;<br />' . "\n" . 'sorry', $r[0]['__return_customer_note_html'], 'escaped, line breaks kept');
	pg_assert_same('', $r[0]['__return_how'], 'how only once approved');
	pg_assert_same(lang(array('string' => 'Send the parcel with {var:1} and give them this return code:', 'vars' => array('Yurtiçi Kargo'))), $r[1]['__return_how']);
	pg_assert_same('IADE<77>', $r[1]['__return_code'], 'raw: the pass escapes it');
	pg_assert_same(lang('Your return was approved. You can send the goods back now.'), $r[2]['__return_how']);
	pg_assert_contains('value="withdraw"', $r[0]['__return_actions_html']);
	pg_assert_contains('value="shipped"', $r[1]['__return_actions_html']);
	pg_assert_same('', $r[3]['__return_actions_html'], 'nothing left to do');
	pg_assert_same(array(5, 6, 8), array_keys($rows['order.return_lines']), 'a return without products has no lines');
	pg_assert_same(array(array('__return_line_name' => 'Mug <XL>', '__return_line_qty' => '2'), array('__return_line_name' => 'Cup', '__return_line_qty' => '1')),
		$rows['order.return_lines'][5]);

	// Every row carries the source's tokens.
	$sources = pg_sw_list_sources();
	pg_assert_same(array_column($sources['order.returns']['tokens'], 0), array_keys($r[0]), 'order.returns tokens');
	pg_assert_same(array_column($sources['order.return_lines']['tokens'], 0), array_keys($rows['order.return_lines'][5][0]), 'order.return_lines tokens');

	pg_assert_same(array('order.returns' => array(), 'order.return_lines' => array()), pg_sw_list_order_return_rows(array(), _pret_form()), 'none');
}

// A layout holding the requests where the order view's starter had them:
// a heading, then a div bound to the old token.
function _pret_tree()
{
	return _pret_node('root', array(), array(
		_pret_node('semantic', array('tag' => 'h6', 'text' => 'Returns')),
		_pret_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__returns', 'eo_visible_if' => 'show_returns'))),
		_pret_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__return_form'))),
	));
}

// $tree given its parts as the widget door does and drawn with the order's
// returns published, as the order view draws it.
function _pret_render($tree, $returns, $flags = null)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
	$rows = pg_sw_list_order_return_rows($returns, _pret_form());
	if ($flags === null) $flags = array('show_returns' => true, 'show_returns_card' => true, 'has_returns' => !empty($rows['order.returns']));
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => 47, 'kind' => 'order_view'));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('order', pg_sw_list_order_rows(array(), array(), array(), array()) + $rows);
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, array('__ship_to_key' => '0', '__return_form' => '')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _pret_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _pret_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

function _pret_forms($html)
{
	preg_match_all('/<form\b.*?<\/form>/s', $html, $m);
	// A list row's ids carry the row's suffix (pg_sw_uniquify_row_ids()),
	// the label's for with them.
	return array_map(function ($f) { return preg_replace(array('/>\s+</', '/(_pgw\d+r\d+)+"/'), array('><', '"'), $f); }, $m[0]);
}

function test_widget_parts_returns_match_the_old_list()
{
	$old = '<h6>Returns</h6><div>' . pg_returns_list_html(_pret_returns(), _pret_form()) . '</div><div></div>';
	foreach (array(
		'the old token' => _pret_tree(),
		'the token as a text' => _pret_node('root', array(), array(
			_pret_node('semantic', array('tag' => 'h6', 'text' => 'Returns')),
			_pret_node('content', array('contentType' => 'text', 'text' => '^^__returns^^')),
			_pret_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__return_form'))),
		)),
	) as $case => $tree) {
		$new = _pret_render($tree, _pret_returns());
		pg_assert_same(_pret_visible($old), _pret_visible($new), $case . ': same words in the same order');
		pg_assert_same(_pret_classes($old), _pret_classes($new), $case . ': same classes in the same order');
		pg_assert_same(_pret_forms($old), _pret_forms($new), $case . ': the same forms, names and ids');
		pg_assert_contains('Mug &lt;XL&gt; × 2', $new, $case . ': a product escaped once');
		pg_assert_contains('Box &lt;torn&gt;<br />', $new, $case . ': the note keeps its line breaks');
		pg_assert_contains('>IADE&lt;77&gt;</strong>', $new, $case . ': the code escaped once');
		pg_assert_true(preg_match('/for="(pg-ov-return-tracking-6[^"]*)".*id="\\1"/s', $new) === 1, $case . ': the label still names its box');
		pg_assert_contains('role="status"', $new, $case . ': the alert keeps its role');
		pg_assert_same(4, substr_count($new, 'class="pg-ov-return '), $case . ': a box per request');
		pg_assert_same(4, substr_count($new, 'class="badge '), $case . ': one badge per request');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $case . ': nothing left');
	}

	// No returns: nothing, as the old list printed nothing.
	$none = _pret_render(_pret_tree(), array());
	pg_assert_false(strpos($none, 'pg-ov-returns') !== false, 'no returns: no list');
}

function test_widget_parts_returns_placed_beats_pushed()
{
	// The token's host gets the part, once.
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_pret_tree()), 'order_view'), true);
	$host = $tree['children'][1];
	pg_assert_false(isset($host['props']['_bindings']['text']), 'the text binding goes');
	pg_assert_same('show_returns', $host['props']['_bindings']['eo_visible_if'], 'other bindings stay');
	pg_assert_same('pg-ov-returns', $host['children'][0]['props']['cssClass']);
	pg_assert_same(4, count($tree['children']), 'placed by the form token: no returns card added');
	pg_assert_contains('pg-ov-legal-documents', $tree['children'][3]['props']['cssClass'], 'only the legal documents card');
	$json = json_encode($tree);
	pg_assert_same($json, pg_sw_default_parts_apply($json, 'order_view'), 'a tree with its part is left as it is');

	// A layout placing neither gets the card with the part at its end.
	$bare = json_decode(pg_sw_default_parts_apply(json_encode(_pret_node('root', array(), array(
		_pret_node('semantic', array('tag' => 'h6', 'text' => 'Order')),
	))), 'order_view'), true);
	$card = $bare['children'][1];
	pg_assert_contains('pg-ov-returns-block', $card['props']['cssClass']);
	$body = $card['children'][0]['children'][1];
	pg_assert_same('pg-ov-returns', $body['children'][0]['children'][0]['props']['cssClass'], 'the list in the card');
	pg_assert_same('order.return_deliveries', $body['children'][1]['children'][0]['props']['source'], 'the request form after it');

	// A layout with a returns list of its own is not given the card.
	$own = json_encode(_pret_node('root', array(), array(
		_pret_node('loop_area', array('source' => 'order.returns'), array(
			_pret_node('semantic', array('tag' => 'span', '_bindings' => array('text' => '__return_title'))),
		)),
		_pret_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__return_form'))),
	)));
	$own = json_decode(pg_sw_default_parts_apply($own, 'order_view'), true);
	pg_assert_same(3, count($own['children']), 'its own list places it: only the legal documents card');
	pg_assert_contains('pg-ov-legal-documents', $own['children'][2]['props']['cssClass']);

	// The raw values take no condition or attribute.
	$raw = pg_sw_raw_token_keys();
	foreach (array('__return_customer_note_html', '__return_decision_note_html', '__return_actions_html') as $k) {
		pg_assert_true(isset($raw[$k]), 'raw: ' . $k);
	}
}

// The editor's part (style_designer.js) is the server's: for the old token
// and for a layout given the card.
function test_widget_parts_returns_editor_twin()
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
		. $cut("\n    var SW_DEFAULT_LOOP_TAILS = {", "\n    };\n")
		. $cut("\n    var SD_RETURN_BADGE_CLASSES = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartPlaced', '_sdPartValue',
		'_sdDefaultTails', '_sdPartOrderCard', '_sdPartOrderReturns', '_sdPartOrderReturnForm', '_sdPartSentence', '_sdPartOrderReturnQtyCell', '_sdPartOrderReturnReason', '_sdPartOrderReturnSubmit', '_sdPartOrderCancelForm', '_sdPartOrderCancelSubmit', '_sdPartOrderReturnsList', '_sdPartOrderLegal') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$bare = _pret_node('root', array(), array(_pret_node('semantic', array('tag' => 'h6', 'text' => 'Order'))));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\n"
		. "function _sdT(s) { return s; }\nfunction _sdDateSample() { return '20.10.2026'; }\n"
		. "function _sdListIsSourced(n) { return !!(n && n.type === 'loop_area' && n.props && typeof n.props.source === 'string' && n.props.source !== ''); }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var a = ' . json_encode(_pret_tree()) . ";\nvar b = " . json_encode($bare) . ";\n"
		. "var ca = _sdDefaultParts(a, 'order_view', null); _sdDefaultTails(a, 'order_view', null);\n"
		. "var cb = _sdDefaultTails(b, 'order_view', null);\n"
		. "console.log(JSON.stringify({ changed: ca && cb, again: _sdDefaultParts(a, 'order_view', null) || _sdDefaultTails(a, 'order_view', null) || _sdDefaultTails(b, 'order_view', null), a: a, b: b }));\n";
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
	pg_assert_true($answer['changed'], 'the editor gave the part and the card');
	pg_assert_false($answer['again'], 'nothing left to give');

	foreach (array('a' => _pret_tree(), 'b' => $bare) as $k => $tree) {
		$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
		pg_assert_same(_pret_norm($server), _pret_norm($answer[$k]), 'the same tree: ' . $k);
	}
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _pret_norm($node)
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
		$node['children'] = array_map('_pret_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the placement rule and the default parts of system widgets
 * (includes/fn/widgets_parts.php): an old token drawn by real design
 * elements shows the visitor the same words, classes and order as the
 * block the renderer built before; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _parts_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _parts_tree($children)
{
	return _parts_node('root', array(), $children);
}

// The order timeline as the order view drew it before the part: one
// <li> per dated event, an inline <style> before the list.
function _parts_old_timeline_html($events)
{
	$css = '<style>.pg-ov-timeline{list-style:none}</style>';
	$html = $css . '<ul class="pg-ov-timeline">';
	foreach ($events as $e) {
		if (empty($e['ts'])) continue;
		$html .= '<li class="pg-ov-timeline-item">'
			. '<span class="pg-ov-timeline-icon bg-' . h($e['color']) . '"><i class="bi ' . h($e['icon']) . '"></i></span>'
			. '<div class="pg-ov-timeline-label">' . h($e['label']) . '</div>'
			. '<div class="pg-ov-timeline-ts">' . h($e['ts']) . '</div>'
			. '</li>';
	}
	return $html . '</ul>';
}

// The events the order view builds: the old block read icon, colour and
// label, the part reads kind, estimate, step label and note.
function _parts_events()
{
	return array(
		array('icon' => 'bi-receipt', 'color' => 'primary', 'label' => 'Order Created', 'ts' => '20.10.2026 10:00', 'kind' => 'created'),
		array('icon' => 'bi-credit-card', 'color' => 'success', 'label' => 'Payment Received', 'ts' => '20.10.2026 10:01', 'kind' => 'paid'),
		array('icon' => 'bi-truck', 'color' => 'info', 'label' => 'Shipped', 'ts' => '21.10.2026', 'kind' => 'shipped', 'estimate' => false),
		array('icon' => 'bi-box-seam', 'color' => 'secondary', 'label' => 'Estimated delivery', 'ts' => '30.10.2026', 'kind' => 'delivered', 'estimate' => true),
		array('icon' => 'bi-x-circle', 'color' => 'danger', 'label' => 'Cancelled — <late> & sorry', 'step_label' => 'Cancelled',
			'note' => '<late> & sorry', 'ts' => '22.10.2026 09:00', 'kind' => 'cancelled'),
		array('icon' => 'bi-truck', 'color' => 'info', 'label' => 'No date', 'ts' => '', 'kind' => 'shipped'),
	);
}

// The visible text: style blocks and tags out (a tag reads as a space, as
// the boxes of a list sit apart), entities decoded, white space collapsed.
function _parts_visible($html)
{
	$html = str_replace('<', ' <', preg_replace('#<style\b.*?</style>#s', '', $html));
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

// The class attributes in document order, empty ones left out.
function _parts_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

// $tree given its parts as the widget door does, drawn and finished as the
// order view draws it, the step rows published under the whole order.
function _parts_render($tree, $static = array(), $cfg = array(), $email = false, $flags = array())
{
	$json = pg_sw_default_parts_apply(json_encode($tree), 'order_view', $cfg, $email);
	$tree = json_decode($json, true);
	// The order view's flags; a card added after it shows only by its own.
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => 44, 'kind' => 'order_view', 'email' => $email));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('order', pg_sw_list_order_rows(array(), array(), array(), _parts_events()));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, $static + array('__ship_to_key' => '0')));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function test_widget_parts_placed()
{
	$tree = _parts_tree(array(
		_parts_node('semantic', array('tag' => 'a', 'href' => '#', '_bindings' => array('href' => '__legal_preinfo_url'))),
		_parts_node('semantic', array('tag' => 'span', '_bindings' => array('show_if' => '__returns'), '_bindParams' => array('show_if' => array('op' => 'not_empty')))),
		_parts_node('semantic', array('tag' => 'p', 'text' => 'Due ^^__withdrawal_deadline^^')),
		_parts_node('semantic', array('tag' => 'p', '_attrs' => array(array('name' => 'data-x', 'value' => '{{__item_gift_card_html}}')))),
		_parts_node('semantic', array('tag' => 'p', '_bindings' => array('text' => '__return_form_note'))),
	));
	pg_assert_true(pg_sw_placed($tree, array('__legal_contract_url', '__legal_preinfo_url')), 'a bound link places');
	pg_assert_true(pg_sw_placed(json_encode($tree), array('__withdrawal_deadline')), 'a literal token places, JSON too');
	pg_assert_true(pg_sw_placed($tree, array('__item_gift_card_html')), 'an attribute token places');
	pg_assert_false(pg_sw_placed($tree, array('__returns')), 'a condition does not place');
	pg_assert_false(pg_sw_placed($tree, array('__return_form')), 'a longer name does not place a shorter one');
	pg_assert_false(pg_sw_placed($tree, array()), 'no token');
}

function test_widget_parts_apply_leaves_other_trees()
{
	$tree = json_encode(_parts_tree(array(
		_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__order_no'))),
		_parts_node('loop_area', array('source' => 'order.return_deliveries'), array()),
		_parts_node('semantic', array('tag' => 'a', '_bindings' => array('href' => '__legal_contract_url'))),
	)));
	pg_assert_same($tree, pg_sw_default_parts_apply($tree, 'order_view'), 'no old token, the cards placed: as it is');
	$timeline = json_encode(_parts_tree(array(_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__timeline'))))));
	pg_assert_same($timeline, pg_sw_default_parts_apply($timeline, 'cart'), 'a kind without parts: as it is');
	pg_assert_same('not json __timeline', pg_sw_default_parts_apply('not json __timeline', 'order_view'), 'broken JSON: as it is');
}

function test_widget_parts_order_timeline_matches_the_old_block()
{
	$host = array('tag' => 'div', 'cssClass' => 'card-body', 'customName' => 'Timeline (HTML)', '_bindings' => array('text' => '__timeline', 'eo_visible_if' => 'has_timeline'));
	$new = _parts_render(_parts_tree(array(_parts_node('semantic', $host))), array(), array(), false, array('has_timeline' => true));
	$old = '<div class="card-body">' . _parts_old_timeline_html(_parts_events()) . '</div>';

	pg_assert_same(_parts_visible($old), _parts_visible($new), 'same words in the same order');
	pg_assert_same(_parts_classes($old), _parts_classes($new), 'same classes in the same order');
	pg_assert_contains('&lt;late&gt; &amp; sorry', $new, 'the reason escaped once');
	pg_assert_true(strpos($new, '<style') === false, 'no inline stylesheet');
	pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false, 'no token or marker left');
	pg_assert_true(strpos($new, 'eo_visible_if') === false, 'the host keeps its other bindings as bindings');

	// A literal ^^__timeline^^, as older layouts wrote it, and a content node.
	$literal = _parts_render(_parts_tree(array(_parts_node('content', array('contentType' => 'paragraph', 'cssClass' => 'small', 'text' => ' ^^__timeline^^ ')))));
	pg_assert_same(_parts_visible($old), _parts_visible($literal), 'a literal token: same words');
	pg_assert_same(array_merge(array('small'), array_slice(_parts_classes($old), 1)), _parts_classes($literal), 'the paragraph keeps its class');
}

function test_widget_parts_order_timeline_in_an_email()
{
	$tree = _parts_tree(array(_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__timeline')))));
	$mail = _parts_render($tree, array(), array(), true);
	pg_assert_same(_parts_visible(_parts_old_timeline_html(_parts_events())), _parts_visible($mail), 'same words');
	pg_assert_contains('style="list-style:none;padding:0;margin:0"', $mail, 'the list styled inline');
	pg_assert_contains('background-color:#dc3545', $mail, 'an icon coloured inline');
	foreach (array('<style', 'flex', 'position:', '::before') as $unsafe) {
		pg_assert_true(strpos($mail, $unsafe) === false, 'nothing a mail client drops: ' . $unsafe);
	}
	// A designer's own style on a part element wins: it comes after.
	$own = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view'), true);
	$own['children'][0]['children'][0]['props']['_attrs'] = array(array('name' => 'style', 'value' => 'margin:4px'));
	$out = json_decode(pg_sw_default_parts_apply(json_encode($own), 'order_view', array(), true), true);
	pg_assert_same('list-style:none;padding:0;margin:0;margin:4px', $out['children'][0]['children'][0]['props']['_attrs'][0]['value'], 'own style after');
}

function test_widget_parts_order_status_badge_matches_the_old_block()
{
	$host = array('contentType' => 'paragraph', 'text' => 'Status', 'cssClass' => 'mb-0 fs-5', 'customName' => 'Status Badge', '_bindings' => array('text' => '__order_status_badge'));
	$tree = _parts_tree(array(_parts_node('content', $host)));
	$cases = array(
		array('complete', 'complete', array(), 'badge bg-success'),
		array('Awaiting Payment', 'pending', array(), 'badge bg-warning text-dark'),
		array('cancelled', 'cancelled', array('status_class_cancelled' => 'badge rounded-pill "x" text-bg-danger'), 'badge rounded-pill x text-bg-danger'),
		array('on hold', 'default', array(), 'badge bg-secondary'),
	);
	foreach ($cases as $c) {
		list($text, $stage, $cfg, $class) = $c;
		$new = _parts_render($tree, array('__order_status_text' => h($text), '__order_status_stage' => $stage), $cfg);
		$old = '<p class="mb-0 fs-5"><span class="' . h(_pg_order_status_badge_class($stage, $cfg)) . '">' . h($text) . '</span></p>';
		pg_assert_same(_parts_visible($old), _parts_visible($new), $stage . ': same words');
		pg_assert_same(array('mb-0 fs-5', $class), _parts_classes($new), $stage . ': one badge, its classes');
		pg_assert_same(_parts_classes($old), _parts_classes($new), $stage . ': as before');
	}
}

// A part's condition compares a code, the same in every language: its
// compared text is no translatable words, or a translated "complete" would
// never match and the badge would not draw.
function test_widget_parts_code_conditions_are_not_translated()
{
	foreach (array('__order_status_stage' => 'complete', '__step_kind' => 'shipped') as $token => $code) {
		$props = array('_bindings' => array('show_if' => $token), '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => $code)));
		pg_assert_false(pg_sw_cond_operand_is_words($props), $token . ' compares a code');
	}
	$props = array('_bindings' => array('show_if' => '__name'), '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => 'Culture')));
	pg_assert_true(pg_sw_cond_operand_is_words($props), 'other values stay words');
}

// The editor's part (style_designer.js) is the server's.
function test_widget_parts_editor_twin()
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
		. $cut("\n    var SD_RETURN_BADGE_CLASSES = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartOrderTimeline', '_sdPartOrderStatusBadge',
		'_sdPartPlaced', '_sdPartValue', '_sdDefaultTails', '_sdPartOrderCard', '_sdPartOrderReturns', '_sdPartOrderReturnMoves', '_sdPartOrderReturnMoveSubmit', '_sdPartOrderReturnForm', '_sdPartSentence', '_sdPartOrderReturnQtyCell', '_sdPartOrderReturnReason', '_sdPartOrderReturnSubmit', '_sdPartOrderCancelForm', '_sdPartOrderCancelSubmit', '_sdPartOrderReturnsList', '_sdPartOrderLegal') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _parts_tree(array(
		_parts_node('content', array('contentType' => 'paragraph', 'lead' => true, 'align' => 'end', 'cssClass' => 'mb-0', 'text' => 'Status', '_bindings' => array('text' => '__order_status_badge', 'eo_visible_if' => 'x'))),
		_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__timeline'), '_bindFormats' => array('text' => 'upper')), array(
			_parts_node('semantic', array('tag' => 'hr')),
		)),
		_parts_node('content', array('contentType' => 'heading', 'tag' => 'H3', 'text' => '^^__timeline^^')),
		_parts_node('component', array('componentType' => 'badge', 'text' => '^^__order_status_badge^^')),
		// A condition places nothing: both cards and the withdrawal line come.
		_parts_node('semantic', array('tag' => 'p', '_bindings' => array('show_if' => '__returns'), '_bindParams' => array('show_if' => array('op' => 'not_empty')))),
		_parts_node('semantic', array('tag' => 'p', '_bindings' => array('show_if' => '__withdrawal_deadline'))),
	));
	$cfg = array('regionType' => 'order_view', 'status_class_pending' => 'badge <b> text-bg-info', 'status_class_shipped' => '');
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\n"
		. "function _sdT(s) { return s; }\nfunction _sdDateSample() { return '20.10.2026'; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ";\n"
		. 'var changed = _sdDefaultParts(tree, "order_view", ' . json_encode($cfg) . ");\n"
		. 'var tails = _sdDefaultTails(tree, "order_view", ' . json_encode($cfg) . ");\n"
		. "console.log(JSON.stringify({ changed: changed && tails, again: _sdDefaultParts(tree, 'order_view', null) || _sdDefaultTails(tree, 'order_view', null), tree: tree }));\n";
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

	$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'order_view', $cfg), true);
	pg_assert_same(_parts_norm($server), _parts_norm($answer['tree']), 'the same tree');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _parts_norm($node)
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
		$node['children'] = array_map('_parts_norm', $node['children']);
	}
	ksort($node);
	return $node;
}

// The legal documents card as the order view added it before the part.
function _parts_old_legal_html($urls, $withdrawal)
{
	$types = pg_legal_document_types();
	$items = '';
	foreach ($urls as $type => $url) {
		if ($url !== '') $items .= '<li><a href="' . h($url) . '" target="_blank" rel="noopener">' . h($types[$type]) . '</a></li>';
	}
	if ($items === '') return '';
	return '<div class="container pb-4 pg-ov-legal-documents"><div class="card">'
		. '<div class="card-header"><h6 class="mb-0 fw-semibold">' . h(lang('Legal Documents')) . '</h6></div>'
		. '<div class="card-body"><ul class="list-unstyled mb-0">' . $items . '</ul>'
		. (($withdrawal !== '') ? '<p class="small text-muted mt-2 mb-0">' . h($withdrawal) . '</p>' : '')
		. '</div></div></div>';
}

// The returns card as the order view added it before the part.
function _parts_old_returns_html($list, $form)
{
	return '<div class="container pb-4 pg-ov-returns-block"><div class="card">'
		. '<div class="card-header"><h6 class="mb-0 fw-semibold">' . h(lang('Returns')) . '</h6></div>'
		. '<div class="card-body">' . $list . $form . '</div></div></div>';
}

// The links of a fragment: address, window and rel, in order.
function _parts_links($html)
{
	preg_match_all('/<a href="([^"]*)" target="([^"]*)" rel="([^"]*)"/', $html, $m, PREG_SET_ORDER);
	return array_map(function ($l) { return array_slice($l, 1); }, $m);
}

// The form fields of a fragment: names in order.
function _parts_fields($html)
{
	preg_match_all('/<(?:input|select|textarea|button)\b[^>]*\bname="([^"]*)"/', $html, $m);
	return $m[1];
}

// A layout from before the cards existed gets both after it, returns first,
// showing what the old blocks showed: the same words, classes, links and
// form fields, the withdrawal line under the links.
function test_widget_parts_order_tail_cards_match_the_old_blocks()
{
	$layout = _parts_tree(array(_parts_node('semantic', array('tag' => 'p', 'cssClass' => 'lead', 'text' => 'Thanks'))));
	$urls = array('preinfo' => '/p/legal_document.php?order_id=7&type=preinfo', 'contract' => '/p/legal_document.php?order_id=7&type=contract');
	$day = '03.11.2026 <est>';
	$line = pg_legal_withdrawal_text(array('open' => true, 'estimated' => false), $day);
	// The requests are a list of their own, without rows here
	// (widget_default_parts_returns_test.php).
	$list = '';
	// The request form is a part of its own, without rows here
	// (widget_default_parts_order_forms_test.php).
	$form = '';
	$static = array(
		'__legal_preinfo_url' => h($urls['preinfo']), '__legal_contract_url' => h($urls['contract']),
		'__withdrawal_deadline' => h($day), '__returns' => $list, '__return_form' => $form,
	);
	$flags = array('has_legal_documents' => true, 'has_legal_preinfo' => true, 'has_legal_contract' => true,
		'has_withdrawal_deadline' => true, 'withdrawal_deadline_firm' => true, 'show_returns_card' => true);
	$new = _parts_render($layout, $static, array(), false, $flags);
	$old = '<p class="lead">Thanks</p>' . _parts_old_returns_html($list, $form) . _parts_old_legal_html($urls, $line);

	pg_assert_same(_parts_visible($old), _parts_visible($new), 'same words in the same order');
	pg_assert_same(_parts_classes($old), _parts_classes($new), 'same classes in the same order');
	pg_assert_same(_parts_links($old), _parts_links($new), 'same links, new window');
	pg_assert_same(array(), _parts_fields($new), 'no request form without a delivery to ask for');
	pg_assert_contains('&lt;est&gt;', $new, 'the line escaped once');

	// Each state its own sentence, the words pg_legal_withdrawal_text() writes.
	foreach (array('withdrawal_deadline_estimated' => array('open' => true, 'estimated' => true), 'withdrawal_ended' => array('open' => false, 'estimated' => false)) as $flag => $state) {
		$state_line = pg_legal_withdrawal_text($state, $day);
		$new = _parts_render($layout, $static, array(), false, array('has_legal_documents' => true, 'has_legal_contract' => true, 'has_withdrawal_deadline' => true, $flag => true));
		$old = '<p class="lead">Thanks</p>' . _parts_old_legal_html(array('preinfo' => '', 'contract' => $urls['contract']), $state_line);
		// Tags read as spaces: a value before its full stop joins it again.
		$words = function ($html) { return preg_replace('/ ([.)])/', '$1', _parts_visible($html)); };
		pg_assert_same($words($old), $words($new), $flag . ': same words');
		pg_assert_same(_parts_classes($old), _parts_classes($new), $flag . ': same classes');
		pg_assert_same(1, substr_count($new, '">03.11.2026 &lt;est&gt;</span>'), $flag . ': the day a value of its own, once');
	}

	// The contract alone, no deadline, returns off: what the old blocks drew.
	$flags = array('has_legal_documents' => true, 'has_legal_contract' => true);
	$new = _parts_render($layout, $static, array(), false, $flags);
	$old = '<p class="lead">Thanks</p>' . _parts_old_legal_html(array('preinfo' => '', 'contract' => $urls['contract']), '');
	pg_assert_same(_parts_visible($old), _parts_visible($new), 'one link: same words');
	pg_assert_same(_parts_classes($old), _parts_classes($new), 'one link: same classes');
	pg_assert_same(_parts_links($old), _parts_links($new), 'one link');

	// Nothing kept, returns off (the receipt e-mail): no card.
	pg_assert_same('<p class="lead">Thanks</p>', _parts_render($layout, $static), 'no card');
}

// A layout that shows the last day itself gets the card without the line;
// one that places a card's token gets no copy of that card.
function test_widget_parts_order_tail_cards_placed_beats_pushed()
{
	$own_day = _parts_tree(array(_parts_node('semantic', array('tag' => 'span', '_bindings' => array('text' => '__withdrawal_deadline')))));
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($own_day), 'order_view'), true);
	pg_assert_same(3, count($tree['children']), 'the layout, then both cards');
	pg_assert_false(strpos(json_encode($tree), '__withdrawal_text') !== false, 'no second withdrawal line');

	$own_link = _parts_tree(array(_parts_node('semantic', array('tag' => 'a', 'text' => 'Contract', '_bindings' => array('href' => '__legal_contract_url')))));
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($own_link), 'order_view'), true);
	pg_assert_same(2, count($tree['children']), 'the layout and the returns card');
	pg_assert_contains('pg-ov-returns-block', json_encode($tree), 'the returns card');

	$again = pg_sw_default_parts_apply(json_encode($tree), 'order_view');
	pg_assert_same(json_encode($tree), $again, 'a tree with its cards is left as it is');

	// A template's placeholder, left for the editor's starter, gets nothing.
	$placeholder = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'loop_area', 'props' => array(), 'children' => array()))));
	pg_assert_same($placeholder, pg_sw_default_parts_apply($placeholder, 'order_view'), 'the placeholder as it is');
}

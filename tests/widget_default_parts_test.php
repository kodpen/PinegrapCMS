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
function _parts_render($tree, $static = array(), $cfg = array(), $email = false)
{
	$json = pg_sw_default_parts_apply(json_encode($tree), 'order_view', $cfg, $email);
	$scope = pg_sw_finish_open(array('sid' => 44, 'kind' => 'order_view', 'email' => $email));
	try {
		$html = trim(_render_tree_node(json_decode($json, true), 0, 0));
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
	$tree = json_encode(_parts_tree(array(_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__order_no'))))));
	pg_assert_same($tree, pg_sw_default_parts_apply($tree, 'order_view'), 'no old token: as it is');
	$timeline = json_encode(_parts_tree(array(_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__timeline'))))));
	pg_assert_same($timeline, pg_sw_default_parts_apply($timeline, 'cart'), 'a kind without parts: as it is');
	pg_assert_same('not json __timeline', pg_sw_default_parts_apply('not json __timeline', 'order_view'), 'broken JSON: as it is');
}

function test_widget_parts_order_timeline_matches_the_old_block()
{
	$host = array('tag' => 'div', 'cssClass' => 'card-body', 'customName' => 'Timeline (HTML)', '_bindings' => array('text' => '__timeline', 'eo_visible_if' => 'has_timeline'));
	$new = _parts_render(_parts_tree(array(_parts_node('semantic', $host))));
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
	$src = $cut("\n    var SD_OV_STATUS_CLASS_DEFAULTS = {", "\n    };\n")
		. $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n")
		. $cut("\n    var SD_OV_STATUS_STAGES = ", ";\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartOrderTimeline', '_sdPartOrderStatusBadge') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = _parts_tree(array(
		_parts_node('content', array('contentType' => 'paragraph', 'lead' => true, 'align' => 'end', 'cssClass' => 'mb-0', 'text' => 'Status', '_bindings' => array('text' => '__order_status_badge', 'eo_visible_if' => 'x'))),
		_parts_node('semantic', array('tag' => 'div', '_bindings' => array('text' => '__timeline'), '_bindFormats' => array('text' => 'upper')), array(
			_parts_node('semantic', array('tag' => 'hr')),
		)),
		_parts_node('content', array('contentType' => 'heading', 'tag' => 'H3', 'text' => '^^__timeline^^')),
		_parts_node('component', array('componentType' => 'badge', 'text' => '^^__order_status_badge^^')),
	));
	$cfg = array('regionType' => 'order_view', 'status_class_pending' => 'badge <b> text-bg-info', 'status_class_shipped' => '');
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\n"
		. "function _sdT(s) { return s; }\nfunction _sdDateSample() { return '20.10.2026'; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ";\n"
		. 'var changed = _sdDefaultParts(tree, "order_view", ' . json_encode($cfg) . ");\n"
		. "console.log(JSON.stringify({ changed: changed, again: _sdDefaultParts(tree, 'order_view', null), tree: tree }));\n";
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

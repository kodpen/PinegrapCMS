<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Phone Launch Site design template
 * (includes/design_templates/pinegrap-launch.php): it is offered with the
 * shop's catalog, carries every part's pages in order and each widget
 * once, every placed widget, shared part and page placeholder names
 * something the template defines, only the shop pages need the shop, the
 * contact page's parts are search sections, the plx- classes equal their
 * rules, the comment threads run on the Comments widget with the reply
 * e-mail's card, and the phone's models survive the assembly.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/template_threads.php');

function _test_lx_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_lx_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_lx_asm_tpl_nodes($child, $out);
	}
}

function test_launch_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-launch']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['shop']) && !isset($all['requests']), 'the parts folder is not read as a template');
	$tpl = _test_lx_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-phone', $tpl['icon']);
	pg_assert_same(74, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('lavender', $tpl['palette'], 'the palette the e-mails\' violet is made for');
	pg_assert_same('minimal', $tpl['look']);
	pg_assert_same(array('launch_list'), array_keys($tpl['contact_groups']), 'the launch list is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['launch_list']['subscription']), 'the launch list takes subscriptions');
}

function test_launch_assembly_template_pages_and_widgets()
{
	$tpl = _test_lx_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/shop.php',
		include $dir . '/requests.php',
		include $dir . '/standard.php',
		include $dir . '/account.php',
	);
	$expected = array();
	$widget_count = 0;
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'launch_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	pg_assert_same($parts[1]['catalog'], $tpl['catalog'], 'the shop\'s catalog');
	$shop_only = array('accessories', 'product', 'cart', 'checkout', 'order', 'email_order', 'address_book');
	foreach ($tpl['pages'] as $p) {
		pg_assert_same(in_array($p['key'], $shop_only, true), isset($p['requires']), $p['key'] . ': needs the shop only if it is a shop page');
	}
	foreach ($tpl['pages'] as $p) {
		if ($p['key'] === 'buy') pg_assert_true(!isset($p['requires']), 'the buy page always stands');
	}

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_lx_asm_tpl_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(in_array($key, $keys, true), 'placeholder names page ' . $key);
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_launch_assembly_template_search_and_look()
{
	$tpl = _test_lx_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_lx_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(plx-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_lx_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bplx-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'plx- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_lx_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'tradein_messages' => array('page' => 'tradein_view', 'view' => 'tradein_detail') + $request,
		'support_messages' => array('page' => 'support_view', 'view' => 'support_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_launch_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-launch');
	_tpl_threads_check_no_fixed_block('pinegrap-launch', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
		'tradein_messages' => 'comments', 'support_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'tradein_view' => lang('Message'), 'support_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_lx_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-launch', $key, $t);
		_tpl_threads_check_draws('pinegrap-launch', $key, $t);
	}
	foreach (array('tradein_detail', 'support_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the customer\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-launch', 'email_comment', 'email_reply', array('blog_post', 'tradein_view', 'support_view', 'conversation'));
}

// The phone is parts.php's $models; the assembly keeps every part's return
// under another name, so the pages and the catalog still name all three
// models.
function test_launch_assembly_models_kept()
{
	$tpl = _test_lx_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
	include $dir . '/parts.php';
	pg_assert_same(array('mini', 'one', 'plus'), array_keys($models), 'three models in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['catalog']), JSON_UNESCAPED_UNICODE);
	foreach ($models as $key => $m) {
		pg_assert_contains(json_encode($m['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': its name is in the template');
		pg_assert_contains('ORBIT-' . strtoupper($key) . '-', $json, $key . ': its products are in the catalog');
	}
}

// Home, features, camera, specs and compare are built on the Product Launch
// blocks: every pg- class they write is a block rule the template's User
// Styles ship, as a drop of the block writes it; their interactions are kept as written by
// pg_fx_normalize(); the motion is where the blocks put it (the hero on
// load, the sticky picture, the parallax camera band, the panels and cards
// on view); the colour picker has a labelled radio per finish, one checked;
// the press quotes and the dropped plx- look are gone; camera, specs and
// compare draw the spec comparison.
function test_launch_assembly_home_and_features_on_launch_blocks()
{
	$tpl = _test_lx_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$source = _pg_designer_block_css_source();
	foreach ($tpl['pages'] as $p) {
		if (!in_array($p['key'], array('home', 'features', 'camera', 'specs', 'compare'), true)) continue;
		$nodes = array();
		_test_lx_asm_tpl_nodes($p['tree'], $nodes);
		$effects = array();
		$radios = array();
		$checked = 0;
		$labels = array();
		$classes = '';
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_fx'])) {
				$kept = pg_fx_normalize($n['props']['_fx']);
				pg_assert_true(is_array($kept), $p['key'] . ': ' . json_encode($n['props']['_fx']) . ' is an interaction');
				foreach ($n['props']['_fx'] as $field => $value) {
					pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $p['key'] . ': ' . $field . ' is kept as written');
				}
				$effects[] = $n['props']['_fx']['effect'] . (isset($n['props']['_fx']['trigger']) ? '/' . $n['props']['_fx']['trigger'] : '');
			}
			$attrs = array();
			foreach (isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array() as $a) $attrs[$a['name']] = $a['value'];
			if (isset($n['props']['tag']) && $n['props']['tag'] === 'input' && isset($attrs['type']) && $attrs['type'] === 'radio') {
				$radios[] = $attrs['id'];
				if (isset($attrs['checked'])) $checked++;
			}
			if (isset($n['props']['tag']) && $n['props']['tag'] === 'label' && isset($attrs['for'])) $labels[] = $attrs['for'];
		}
		preg_match_all('/(?<![\w-])pg-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) {
			$rule = '';
			foreach ($source['rules'] as $name => $text) {
				if (preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $text)) {
					$rule = $text;
					break;
				}
			}
			pg_assert_true($rule !== '' && strpos($css, $rule) !== false, $p['key'] . ": the rule of $class ships with the template");
		}
		$json = json_encode($p['tree']);
		pg_assert_false(strpos($json, 'blockquote') !== false, $p['key'] . ': no press quotes');
		pg_assert_false((bool)preg_match('/plx-(hero|specs)/', $json), $p['key'] . ': no dropped plx- look');
		if ($p['key'] === 'home' || $p['key'] === 'features') {
			pg_assert_same(4, count($radios), $p['key'] . ': a radio per finish');
			pg_assert_same(1, $checked, $p['key'] . ': one finish is checked');
			pg_assert_same(array(), array_values(array_diff($radios, $labels)), $p['key'] . ': every finish has its label');
		}
		if ($p['key'] === 'home') {
			foreach (array('slide-up/load', 'scale/load', 'parallax', 'slide-up/view') as $e) pg_assert_true(in_array($e, $effects, true), "home moves: $e");
		} elseif ($p['key'] === 'features') {
			pg_assert_true(in_array('sticky', $effects, true), 'features: the sticky picture');
			pg_assert_same(6, count(array_keys($effects, 'slide-up/view', true)), 'features: a panel per highlight comes in on view');
		} elseif ($p['key'] === 'camera') {
			foreach (array('parallax', 'sticky') as $e) pg_assert_true(in_array($e, $effects, true), "camera moves: $e");
			pg_assert_same(5, count(array_keys($effects, 'slide-up/view', true)), 'camera: four panels and the sample photos come in on view');
		} elseif ($p['key'] === 'compare') {
			pg_assert_same(array('slide-up/view'), $effects, 'compare: the model heads come in on view');
		} else {
			pg_assert_same(array(), $effects, 'specs: a still page');
		}
		if ($p['key'] !== 'home' && $p['key'] !== 'features') {
			pg_assert_contains('pg-launch-specs', $json, $p['key'] . ': the spec comparison');
		}
	}
}

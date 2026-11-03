<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Marketplace design template
 * (includes/design_templates/pinegrap-market.php): it is offered with the
 * shop's catalog, carries every part's pages in order and each widget
 * once, every placed widget, shared part and page placeholder names
 * something the template defines, only the shop pages need the shop, the
 * contact page's parts are search sections, the pmk- classes equal their
 * rules, the comment threads run on the Comments widget with the reply
 * e-mail's card, and the sample makers and products survive the assembly.
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

function _test_mk_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_mk_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mk_asm_tpl_nodes($child, $out);
	}
}

function test_market_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-market']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['shop']) && !isset($all['sellers']), 'the parts folder is not read as a template');
	$tpl = _test_mk_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-shop', $tpl['icon']);
	pg_assert_same(77, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('organic', $tpl['look']);
	pg_assert_same('sunset', $tpl['palette'], 'the palette the e-mails\' orange is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_market_assembly_template_pages_and_widgets()
{
	$tpl = _test_mk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/shop.php',
		include $dir . '/sellers.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'maker_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	pg_assert_same($parts[1]['catalog'], $tpl['catalog'], 'the shop\'s catalog');
	$shop_keys = array();
	foreach ($parts[1]['pages'] as $p) $shop_keys[] = $p['key'];
	$shop_keys[] = 'address_book';
	foreach ($tpl['pages'] as $p) {
		pg_assert_same(in_array($p['key'], $shop_keys, true), isset($p['requires']), $p['key'] . ': needs the shop only if it is a shop page');
	}
	foreach (array('home', 'categories', 'sellers', 'seller', 'apply', 'help') as $key) {
		pg_assert_true(in_array($key, $keys, true) && !in_array($key, $shop_keys, true), $key . ': stands without the shop');
	}

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder, every
	// group placeholder a group of the catalog.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_mk_asm_tpl_nodes($tree, $nodes);
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
	preg_match_all('/\{\{product_group(?:_path)?:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true($key === 'root' || isset($tpl['catalog']['groups'][$key]), 'placeholder names group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_market_assembly_template_search_and_look()
{
	$tpl = _test_mk_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_mk_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pmk-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_mk_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpmk-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pmk- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_mk_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'seller_reviews' => array('page' => 'seller', 'view' => 'seller_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'help_messages' => array('page' => 'help_view', 'view' => 'help_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_market_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-market');
	_tpl_threads_check_no_fixed_block('pinegrap-market', array(
		'application_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'help_messages' => 'comments', 'post_comments' => 'comments', 'seller_reviews' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'seller' => lang('Review'), 'application_view' => lang('Message'),
		'help_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	pg_assert_true(!empty(_tpl_threads_page($tpl, 'seller')['comments']['rating']), 'a maker\'s reviews carry stars');
	// The shared drawing check's sample thread goes two answers deep; a
	// maker's reviews take one (the maker's answer), so they are checked
	// for their settings only.
	foreach (_test_mk_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-market', $key, $t);
		if ($key !== 'seller_reviews') _tpl_threads_check_draws('pinegrap-market', $key, $t);
	}
	foreach (array('application_detail', 'help_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the customer\'s own');
	}
	pg_assert_same('public', $tpl['widgets']['seller_detail']['config']['access_control'], 'a maker\'s page is public');
	_tpl_threads_check_email_card('pinegrap-market', 'email_comment', 'email_reply',
		array('blog_post', 'seller', 'application_view', 'help_view', 'conversation'));
}

// The samples are parts.php's $sellers and $market_products; the assembly
// keeps every part's return under another name, so the pages name every
// maker and the catalog carries every product.
function test_market_assembly_samples_kept()
{
	$tpl = _test_mk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	pg_assert_same(6, count($sellers), 'six makers in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($sellers as $key => $s) {
		pg_assert_contains(json_encode($s['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': its name is in the template');
		pg_assert_true(isset($tpl['catalog']['groups'][$key]), $key . ': its shop is a group of the catalog');
		foreach ($market_products[$key] as $i => $p) {
			pg_assert_contains($product_sku($key, $i + 1), $catalog, $key . ': product ' . ($i + 1) . ' is in the catalog');
		}
	}
}

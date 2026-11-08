<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Florist design template
 * (includes/design_templates/pinegrap-florist.php): it is offered as a
 * template that opens without the shop, with the bouquets, subscription
 * bundles, extras and gift cards' catalog,
 * carries every part's pages in order and each widget once, only shop
 * pages need the shop, every placed widget, shared part and page
 * placeholder names something the template defines, the contact page's
 * parts are search sections, the pfl- classes equal their rules, the
 * comment threads run on the Comments widget with the reply e-mail's card,
 * and the sample work, bouquets, bundles and extras survive the assembly.
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

function _test_fl_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_fl_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fl_asm_tpl_nodes($child, $out);
	}
}

function test_florist_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-florist']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['work']) && !isset($all['orders']) && !isset($all['enquiries']), 'the parts folder is not read as a template');
	$tpl = _test_fl_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-flower1', $tpl['icon']);
	pg_assert_same(119, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the bouquets, bundles, extras and gift cards\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('organic', $tpl['look']);
	pg_assert_same('coral', $tpl['palette'], 'the palette the e-mails\' rose is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_florist_assembly_template_pages_and_widgets()
{
	$tpl = _test_fl_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/work.php',
		include $dir . '/orders.php',
		include $dir . '/enquiries.php',
		include $dir . '/shop.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'delivery_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array('address_book');
	foreach ($parts[4]['pages'] as $p) $shop_keys[] = $p['key'];
	foreach ($tpl['pages'] as $p) {
		pg_assert_same(in_array($p['key'], $shop_keys, true), isset($p['requires']), $p['key'] . ': needs the shop only if it is a shop page');
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
		_test_fl_asm_tpl_nodes($tree, $nodes);
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

function test_florist_assembly_template_search_and_look()
{
	$tpl = _test_fl_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_fl_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pfl-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_fl_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpfl-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pfl- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_fl_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'request_messages' => array('page' => 'request_view', 'view' => 'request_detail') + $request,
		'event_messages' => array('page' => 'event_enquiry_view', 'view' => 'event_detail') + $request,
		'subscription_messages' => array('page' => 'subscription_view', 'view' => 'subscription_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and an enquiry's messages are as
// private as the request.
function test_florist_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-florist');
	_tpl_threads_check_no_fixed_block('pinegrap-florist', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'request_messages' => 'comments', 'event_messages' => 'comments',
		'subscription_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'request_view' => lang('Message'),
		'event_enquiry_view' => lang('Message'), 'subscription_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_fl_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-florist', $key, $t);
		_tpl_threads_check_draws('pinegrap-florist', $key, $t);
	}
	foreach (array('request_detail', 'event_detail', 'subscription_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the sender\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-florist', 'email_comment', 'email_reply',
		array('blog_post', 'request_view', 'event_enquiry_view', 'subscription_view', 'conversation'));
}

// The sample work, bouquets, plans and extras are parts.php's $works,
// $bouquets, $plans and $extras; the assembly keeps every part's return
// under another name, so the collection still seeds every work and the
// catalog holds every bouquet, subscription bundle and extra.
function test_florist_assembly_samples()
{
	$tpl = _test_fl_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
	include $dir . '/parts.php';
	pg_assert_true(count($works) >= 4, 'sample work in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($works as $key => $w) {
		pg_assert_contains(json_encode($w['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($bouquets) as $key) {
		pg_assert_contains('"FLORIST-BQ-' . strtoupper(str_replace('-', '', $key)) . '"', $catalog, $key . ': its bouquet is in the catalog');
	}
	foreach (array_keys($plans) as $key) {
		pg_assert_contains('"FLORIST-SUB-' . strtoupper($key) . '"', $catalog, $key . ': its subscription bundle is in the catalog');
	}
	foreach ($extras as $x) {
		pg_assert_contains(json_encode($x['sku'], JSON_UNESCAPED_UNICODE), $catalog, $x['sku'] . ': its product is in the catalog');
	}
}

// The florist's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_florist_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-florist', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-florist');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_work"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places Our Work and the journal');
}

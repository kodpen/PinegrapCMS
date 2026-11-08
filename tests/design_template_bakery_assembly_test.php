<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Bakery design template
 * (includes/design_templates/pinegrap-bakery.php): it is offered without a
 * shop requirement and with its catalog; every part's pages come in order,
 * only the shop pages and the address book need the shop; every widget is
 * defined once and placed; placeholders name pages and folders; the
 * contact page's search sections; the pbk- classes and their rules match;
 * the comment threads and the reply e-mail card; the home page's lists;
 * the sample classes and the shop's products kept.
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
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_bakery_test.php');

function _test_bk_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_bk_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bk_asm_nodes($child, $out);
	}
}

function test_bakery_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-bakery']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['classes']) && !isset($all['wholesale']), 'the parts folder is not read as a template');
	$tpl = _test_bk_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-cake2', $tpl['icon']);
	pg_assert_same(137, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the shop\'s catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('private', $tpl['folders']['private']['access'], 'inspiration photos stay private');
	pg_assert_same('organic', $tpl['look']);
	pg_assert_same('coral', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_bakery_assembly_template_pages_and_widgets()
{
	$tpl = _test_bk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/classes.php',
		include $dir . '/requests.php',
		include $dir . '/wholesale.php',
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
	$all = _test_bk_page_keys();
	$sorted = $keys;
	sort($sorted);
	sort($all);
	pg_assert_same($all, $sorted, 'the finished template\'s pages');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'order_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array();
	foreach ($tpl['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	$expected_shop = array_merge(array('address_book'), _test_bk_shop_keys());
	sort($expected_shop);
	sort($shop_keys);
	pg_assert_same($expected_shop, $shop_keys, 'the shop pages');

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_bk_asm_nodes($tree, $nodes);
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
	preg_match_all('/\{\{contact_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['contact_groups'][$key]), 'placeholder names contact group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

// No page but the shop's own links a shop page, and no shared part; the
// order and collect page leads to the shop only while it is on.
function test_bakery_assembly_shop_links()
{
	$tpl = _test_bk_asm_tpl();
	$shop = array_merge(array('address_book'), _test_bk_shop_keys());
	foreach ($tpl['pages'] as $p) {
		if (in_array($p['key'], $shop, true) || $p['key'] === 'how_to_order') continue;
		$json = json_encode($p['tree']);
		foreach (_test_bk_shop_keys() as $key) {
			pg_assert_true(strpos($json, '{{page:' . $key . '}}') === false, $p['key'] . ': no link to ' . $key . ' without the shop');
		}
	}
	foreach ($tpl['shared'] as $key => $s) {
		pg_assert_true(strpos(json_encode($s['tree']), '{{page:shop}}') === false, $key . ': no shop link');
	}
	$order = json_encode(_tpl_threads_page($tpl, 'how_to_order')['tree']);
	pg_assert_same(defined('ECOMMERCE') && ECOMMERCE, strpos($order, '{{page:shop}}') !== false, 'how to order links the shop only with the shop on');
}

function test_bakery_assembly_template_search_and_look()
{
	$tpl = _test_bk_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_bk_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pbk-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree'])) continue;
			$nodes = array();
			_test_bk_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpbk-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pbk- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Playfair Display', 'Nunito Sans'), array_column($fonts, 'family'), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_bakery_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'cake_order_messages' => array('page' => 'cake_order_view', 'view' => 'cake_order_detail') + $request,
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-bakery');
	_tpl_threads_check_no_fixed_block('pinegrap-bakery', array(
		'booking_messages' => 'comments', 'cake_order_messages' => 'comments', 'conversation_replies' => 'comments',
		'email_comment' => 'comments', 'enquiry_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'cake_order_view' => lang('Message'), 'booking_view' => lang('Message'),
		'enquiry_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-bakery', $key, $t);
		_tpl_threads_check_draws('pinegrap-bakery', $key, $t);
	}
	foreach (array('cake_order_detail', 'booking_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-bakery', 'email_comment', 'email_reply',
		array('blog_post', 'cake_order_view', 'booking_view', 'enquiry_view', 'conversation'));
}

// The sample classes are parts.php's $baking_classes, the shop's products
// its bakes, cakes, gift boxes and class places; the assembly keeps every
// part's return under another name, so the collection still seeds every
// class and the catalog every product.
function test_bakery_assembly_samples()
{
	$tpl = _test_bk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
	include $dir . '/parts.php';
	pg_assert_true(count($baking_classes) >= 3, 'sample classes in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($baking_classes as $key => $c) {
		pg_assert_contains(json_encode($c['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($baking_classes) as $key) {
		pg_assert_contains('BAKERY-CLASS-' . strtoupper($key), $catalog, $key . ': its class place is in the catalog');
	}
	foreach ($bakes as $key => $b) {
		pg_assert_contains(json_encode($b['name'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The home page places the featured classes, the Journal's latest and,
// beside the sample words, the bakery's Google reviews.
function test_bakery_assembly_home()
{
	_tpl_threads_check_google_reviews('pinegrap-bakery', 'google_reviews', 'home', lang('Customer Stories'));
	$tpl = _tpl_threads_tpl('pinegrap-bakery');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_classes', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Zoo and Aquarium design template
 * (includes/design_templates/pinegrap-zoo.php): it is offered without a
 * shop requirement and with its catalog; every part's pages come in order,
 * only the shop pages and the address book need the shop; every widget is
 * defined once and placed; placeholders name pages, folders and contact
 * groups; the contact page's search sections; the pzo- classes and their
 * rules match, and the zoo- block rules the pages use are shipped; the
 * comment threads and the reply e-mail card; the sample animals, events
 * and products kept.
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
require_once(__DIR__ . '/design_template_zoo_test.php');

function _test_pzo_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_pzo_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pzo_asm_nodes($child, $out);
	}
}

function test_zoo_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-zoo']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['animals']) && !isset($all['visits']), 'the parts folder is not read as a template');
	$tpl = _test_pzo_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-tree', $tpl['icon']);
	pg_assert_same(143, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the tickets, memberships and adoptions catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('private', $tpl['folders']['private']['access'], 'the private folder stays private');
	pg_assert_same('organic', $tpl['look']);
	pg_assert_same('forest', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template takes the same place in the installer's list.
	foreach ($all as $id => $other) {
		if ($id === 'pinegrap-zoo' || !isset($other['order'])) continue;
		pg_assert_true($other['order'] !== $tpl['order'], $id . ' has another order');
	}
}

function test_zoo_assembly_template_pages_and_widgets()
{
	$tpl = _test_pzo_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/animals.php',
		include $dir . '/visits.php',
		include $dir . '/events.php',
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
	$all = _test_zoo_page_keys();
	$sorted = $keys;
	sort($sorted);
	sort($all);
	pg_assert_same($all, $sorted, 'the finished template\'s pages');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'visit_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array();
	foreach ($tpl['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	$expected_shop = array_merge(array('address_book'), _test_zoo_shop_keys());
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
		_test_pzo_asm_nodes($tree, $nodes);
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
	pg_assert_true(in_array('public', $m[1], true), 'the photos are uploaded into the public folder');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	preg_match_all('/\{\{contact_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['contact_groups'][$key]), 'placeholder names contact group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

// No page but the shop's own links a shop page, and no shared part.
function test_zoo_assembly_shop_links()
{
	$tpl = _test_pzo_asm_tpl();
	$shop = array_merge(array('address_book'), _test_zoo_shop_keys());
	foreach ($tpl['pages'] as $p) {
		if (in_array($p['key'], $shop, true)) continue;
		$json = json_encode($p['tree']);
		foreach (_test_zoo_shop_keys() as $key) {
			pg_assert_true(strpos($json, '{{page:' . $key . '}}') === false, $p['key'] . ': no link to ' . $key . ' without the shop');
		}
	}
	foreach ($tpl['shared'] as $key => $s) {
		foreach (_test_zoo_shop_keys() as $page_key) {
			pg_assert_true(strpos(json_encode($s['tree']), '{{page:' . $page_key . '}}') === false, $key . ': no link to ' . $page_key);
		}
	}
}

function test_zoo_assembly_template_search_and_look()
{
	$tpl = _test_pzo_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_pzo_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pzo-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree'])) continue;
			$nodes = array();
			_test_pzo_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpzo-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pzo- classes and rules match');
	pg_assert_true(strpos($css, 'prefers-reduced-motion') !== false, 'the zone cards keep still when motion is reduced');
	// The pages build the slot times and the habitat pins themselves, so
	// their block rules travel with the template.
	foreach (array('pg-zoo-time', 'pg-zoo-pin') as $rule) {
		pg_assert_true(strpos($classes, $rule) !== false, $rule . ' is used');
		pg_assert_true(preg_match('/\\.' . $rule . '(?![\\w-])/', $css) === 1, $rule . ' is shipped');
	}
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Bricolage Grotesque', 'Rubik'), array_column($fonts, 'family'), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_zoo_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'visit_messages' => array('page' => 'visit_view', 'view' => 'visit_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-zoo');
	_tpl_threads_check_no_fixed_block('pinegrap-zoo', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
		'visit_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'visit_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-zoo', $key, $t);
		_tpl_threads_check_draws('pinegrap-zoo', $key, $t);
	}
	foreach (array('visit_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-zoo', 'email_comment', 'email_reply',
		array('blog_post', 'visit_view', 'conversation'));
}

// The sample animals and events are parts.php's $animals and $events, the
// shop's products its tickets, memberships and adoptions; the assembly
// keeps every part's return under another name, so the collections still
// seed every record and the catalog every product.
function test_zoo_assembly_samples()
{
	$tpl = _test_pzo_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	pg_assert_true(count($animals) >= 3, 'sample animals in parts.php');
	pg_assert_true(count($events) >= 3, 'sample events in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($animals as $key => $a) {
		pg_assert_contains(json_encode($a['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($tickets) as $key) pg_assert_contains('ZOO-TICKET-', $catalog, $key . ': tickets in the catalog');
	foreach (array_keys($memberships) as $key) pg_assert_contains('ZOO-MEMBER-', $catalog, $key . ': memberships in the catalog');
	foreach ($animals as $key => $a) {
		if (!$a['adopt']) continue;
		pg_assert_contains('ZOO-ADOPT-', $catalog, $key . ': adoptions in the catalog');
	}
}

// The home page opens on the day and places the spotlight animals, the
// coming events and the News.
function test_zoo_assembly_home()
{
	$tpl = _test_pzo_asm_tpl();
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_animals', 'home_events', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

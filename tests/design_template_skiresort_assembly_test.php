<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Ski Resort design template
 * (includes/design_templates/pinegrap-skiresort.php): it is offered as a
 * template that opens without the shop, with the shop's catalog, carries
 * every part's pages in order (exactly the plan's pages) and each widget
 * once, only shop pages need the shop, every placed widget, shared part
 * and page placeholder names something the template defines, the contact
 * page's parts are search sections, the psk- classes equal their rules,
 * the two fonts pair as no other template does, the comment threads run
 * on the Comments widget with the reply e-mail's card, and the sample
 * slopes, lifts, events and products survive the assembly.
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
require_once(__DIR__ . '/design_template_skiresort_test.php');

function _test_sk_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_sk_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sk_asm_tpl_nodes($child, $out);
	}
}

function test_skiresort_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-skiresort']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['cards']) && !isset($all['lessons']), 'the parts folder is not read as a template');
	$tpl = _test_sk_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-snow', $tpl['icon']);
	pg_assert_same(135, $tpl['order']);
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-skiresort') pg_assert_true(!isset($other['order']) || $other['order'] !== 135, $id . ': another installer place');
	}
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the shop\'s catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('minimal', $tpl['look']);
	pg_assert_same('sky', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_skiresort_assembly_template_pages_and_widgets()
{
	$tpl = _test_sk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public_part = include $dir . '/pages_public.php';
	$slope_part = include $dir . '/slopes.php';
	include $dir . '/mail.php';
	$parts = array(
		$public_part,
		$slope_part,
		include $dir . '/lessons.php',
		include $dir . '/enquiries.php',
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
	$plan = _test_sk_page_keys();
	sort($plan);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($plan, $sorted, 'exactly the plan\'s pages');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'snow_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array_merge(array('address_book'), _test_sk_shop_keys());
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
	$shared_used = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_sk_asm_tpl_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) {
				$shared_used[$n['props']['templateShared']] = true;
				pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
			}
		}
	}
	foreach (array_keys($tpl['shared']) as $key) pg_assert_true(isset($shared_used[$key]), 'shared part ' . $key . ' is placed');
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

function test_skiresort_assembly_template_search_and_look()
{
	$tpl = _test_sk_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_sk_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(psk-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree'])) continue;
			$nodes = array();
			_test_sk_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpsk-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'psk- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Outfit', 'Nunito Sans'), array($fonts[0]['family'], $fonts[1]['family']), 'a geometric and a humanist sans');
	// No other template pairs the same two fonts.
	foreach (pg_design_templates() as $id => $other) {
		if ($id === 'pinegrap-skiresort' || empty($other['assets']['fonts'])) continue;
		$families = array();
		foreach ((array)json_decode($other['assets']['fonts'], true) as $f) if (isset($f['family'])) $families[] = $f['family'];
		pg_assert_false(in_array('Outfit', $families, true) && in_array('Nunito Sans', $families, true), $id . ': another pair of fonts');
	}
}

function _test_sk_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'lesson_messages' => array('page' => 'lesson_view', 'view' => 'lesson_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_skiresort_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-skiresort');
	_tpl_threads_check_no_fixed_block('pinegrap-skiresort', array(
		'lesson_messages' => 'comments', 'enquiry_messages' => 'comments', 'conversation_replies' => 'comments',
		'email_comment' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'lesson_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_sk_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-skiresort', $key, $t);
		_tpl_threads_check_draws('pinegrap-skiresort', $key, $t);
	}
	foreach (array('lesson_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the owner\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-skiresort', 'email_comment', 'email_reply',
		array('blog_post', 'lesson_view', 'enquiry_view', 'conversation'));
}

// The sample slopes, lifts and events are parts.php's $slopes, $lifts and
// $events; the assembly keeps every part's return under another name, so
// the pages still show every slope, lift and event, and the catalog
// carries every pass and rental.
function test_skiresort_assembly_samples()
{
	$tpl = _test_sk_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	pg_assert_true(count($slopes) >= 5 && count($lifts) >= 3 && count($events) >= 3, 'sample slopes, lifts and events in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_UNICODE);
	foreach (array('slope' => $slopes, 'lift' => $lifts, 'event' => $events) as $what => $set) {
		foreach ($set as $key => $item) {
			pg_assert_contains(json_encode($item['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the ' . $what . ' is in the template');
		}
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	// A rental product's title names the item after "Rental per day: ".
	foreach (array_merge($lift_passes, $season_passes, $rental) as $key => $item) {
		pg_assert_contains(trim(json_encode($item['title'], JSON_UNESCAPED_UNICODE), '"') . '"', $catalog, $key . ': the product is in the catalog');
	}
}

// The home places the featured slopes, the coming events and the newest
// News, and today's snow band.
function test_skiresort_assembly_home()
{
	$tpl = _tpl_threads_tpl('pinegrap-skiresort');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_slopes', 'home_events', 'home_posts') as $key) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $key . '"') !== false, 'the home places ' . $key);
	}
}

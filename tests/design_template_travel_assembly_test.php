<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Travel Agency design template
 * (includes/design_templates/pinegrap-travel.php): it is offered as a
 * template that opens without the shop, with the deposits' and gift cards'
 * catalog,
 * carries every part's pages in order and each widget once, only shop
 * pages need the shop, every placed widget, shared part and page
 * placeholder names something the template defines, the contact page's
 * parts are search sections, the ptv- classes equal their rules, the
 * comment threads (the tours' reviews with stars among them) run on the
 * Comments widget with the reply e-mail's card, and the sample tours,
 * destinations, deposits and gift cards survive the assembly; the home,
 * the tour page and the destination page are drawn on the Travel blocks
 * with their rules shipped and motion from the interactions on nodes.
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

function _test_tv_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_tv_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_tv_asm_tpl_nodes($child, $out);
	}
}

function test_travel_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-travel']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['tours']) && !isset($all['bookings']), 'the parts folder is not read as a template');
	$tpl = _test_tv_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-airplane', $tpl['icon']);
	pg_assert_same(83, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the deposits\' and gift cards\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('modern-soft', $tpl['look']);
	pg_assert_same('sky', $tpl['palette'], 'the palette the e-mails\' blues are made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_travel_assembly_template_pages_and_widgets()
{
	$tpl = _test_tv_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/tours.php',
		include $dir . '/bookings.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'trip_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_tv_asm_tpl_nodes($tree, $nodes);
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

function test_travel_assembly_template_search_and_look()
{
	$tpl = _test_tv_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_tv_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(ptv-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_tv_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bptv-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'ptv- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_tv_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'tour_reviews' => array('page' => 'tour', 'view' => 'tour_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_travel_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-travel');
	_tpl_threads_check_no_fixed_block('pinegrap-travel', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'enquiry_messages' => 'comments', 'post_comments' => 'comments', 'tour_reviews' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'), 'tour' => lang('Review'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	// The shared drawing check's sample thread goes two answers deep; a
	// tour's reviews take one (the agency's answer), so they are checked
	// for their settings only.
	foreach (_test_tv_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-travel', $key, $t);
		if ($key !== 'tour_reviews') _tpl_threads_check_draws('pinegrap-travel', $key, $t);
	}
	foreach (array('booking_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the traveller\'s own');
	}
	pg_assert_true(!empty(_tpl_threads_page($tpl, 'tour')['comments']['rating']), 'a tour\'s reviews carry stars');
	_tpl_threads_check_email_card('pinegrap-travel', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'enquiry_view', 'conversation', 'tour'));
}

// The samples are parts.php's $tours, $destinations and $gift_cards; the
// assembly keeps every part's return under another name, so the
// collections still seed every tour and destination and the catalog has a
// deposit per tour and every gift card.
function test_travel_assembly_samples()
{
	$tpl = _test_tv_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	pg_assert_true(count($tours) >= 6, 'sample tours in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($tours as $key => $t) {
		pg_assert_contains(json_encode($t['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($destinations as $key => $d) {
		pg_assert_contains(json_encode($d['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($tours) as $key) {
		pg_assert_contains(json_encode('TRAVEL-DEPOSIT-' . strtoupper($key)), $catalog, $key . ': its deposit is in the catalog');
	}
	foreach ($gift_cards as $key => $g) {
		pg_assert_contains(json_encode($g['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The agency's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_travel_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-travel', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-travel');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_tours"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the tours and the journal');
}

// Version 2.0.0 draws the home, the tour page and the destination page on
// the Travel blocks and the Hero (Image Background): every block class they
// place ships its rule in the template's User Styles, each moves through
// the interactions on its nodes (kept as written by pg_fx_normalize()), and
// each has one h1. The home's tour runs day by day and its destination has
// its four seasons; the old figures, reasons and question bands are gone.
function test_travel_assembly_pages_on_travel_blocks()
{
	$tpl = _test_tv_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'about', 'travel_info', 'deals', 'faq'), true)) $trees[$p['key']] = $p['tree'];
	$trees['tour'] = $tpl['widgets']['tour_detail']['tree'];
	$trees['destination'] = $tpl['widgets']['destination_detail']['tree'];
	$wanted = array(
		'home'        => array('pg-hero-tall', 'pg-shade-bottom', 'pg-travel-days', 'pg-travel-day', 'pg-travel-glance', 'pg-travel-figure', 'pg-travel-seasons'),
		'tour'        => array('pg-travel-glance', 'pg-travel-figure', 'sticky-lg-top'),
		'destination' => array('pg-travel-glance'),
	);
	pg_assert_same(7, count($trees), 'five public pages, the tour page, the destination page');
	$found = array();
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_tv_asm_tpl_nodes($tree, $nodes);
		$classes = '';
		$labels = array();
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			foreach (array('pg-travel-days', 'pg-travel-seasons') as $list) {
				if (preg_match('/(?<![\w-])' . $list . '(?![\w-])/', isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '')) $found[$key][$list] = $n;
			}
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-(?:travel-[a-z0-9-]+|hero-tall|shade-bottom)(?![\w-])/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach (isset($wanted[$key]) ? $wanted[$key] : array() as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $classes), $key . ': places ' . $class);
		}
		pg_assert_true($effects >= 1, $key . ': something moves');
		pg_assert_true($h1 <= 1, $key . ': at most one h1');
		if (in_array($key, array('home', 'tour', 'destination'), true)) {
			pg_assert_true($effects >= 3, $key . ': opens and scrolls in motion');
			pg_assert_same(1, $h1, $key . ': one h1');
		}
		if (in_array($key, array('home', 'about', 'travel_info'), true)) pg_assert_false(in_array('Write to Us', $labels, true), $key . ': no question band');
		if ($key === 'home') {
			foreach (array('Figures', 'Why Travel with Us') as $band) pg_assert_false(in_array($band, $labels, true), 'home: no ' . $band . ' band');
			foreach (array('Hero', 'Travel (Day by Day)', 'Travel (Destination Guide)') as $block) pg_assert_true(in_array($block, $labels, true), 'home: the ' . $block . ' block');
		}
	}

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	pg_assert_contains('"id":"tv-home-itinerary"', $home, 'the tour day by day is a search section');
	pg_assert_contains('"id":"tv-home-destination"', $home, 'the destination guide is a search section');
	pg_assert_contains('{{page:destinations}}?region=', $home, 'the hero links each region');
	pg_assert_same(4, count($found['home']['pg-travel-days']['children']), 'the sample tour has four days');
	$seasons = $found['home']['pg-travel-seasons']['children'];
	pg_assert_same(4, count($seasons), 'four seasons');
	$best = 0;
	foreach ($seasons as $li) if (strpos(isset($li['props']['cssClass']) ? $li['props']['cssClass'] : '', 'pg-travel-best') !== false) $best++;
	pg_assert_same(2, $best, 'spring and autumn are the best time');
	$tour = json_encode($trees['tour'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"price"', $tour, 'the glance shows the bound price');
	pg_assert_contains('"group_size"', $tour, 'the glance shows the bound group size');
	$destination = json_encode($trees['destination'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"season"', $destination, 'the guide shows the bound best time to go');
}

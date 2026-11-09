<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Hotel design template
 * (includes/design_templates/pinegrap-hotel.php): it is offered as a
 * template that opens without the shop, with the gift vouchers' catalog,
 * carries every part's pages in order and each widget once, only shop
 * pages need the shop, every placed widget, shared part and page
 * placeholder names something the template defines, the contact page's
 * parts are search sections, the pht- classes equal their rules, the
 * comment threads run on the Comments widget with the reply e-mail's card,
 * and the sample rooms and vouchers survive the assembly.
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

function _test_ht_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_ht_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ht_asm_tpl_nodes($child, $out);
	}
}

function test_hotel_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-hotel']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['rooms']) && !isset($all['bookings']), 'the parts folder is not read as a template');
	$tpl = _test_ht_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-buildings', $tpl['icon']);
	pg_assert_same(76, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the gift vouchers\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('elegant', $tpl['look']);
	pg_assert_same('cocoa', $tpl['palette'], 'the palette the e-mails\' browns are made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_hotel_assembly_template_pages_and_widgets()
{
	$tpl = _test_ht_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/rooms.php',
		include $dir . '/bookings.php',
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
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'booking_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_ht_asm_tpl_nodes($tree, $nodes);
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

function test_hotel_assembly_template_search_and_look()
{
	$tpl = _test_ht_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_ht_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pht-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_ht_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpht-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pht- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_ht_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'event_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_hotel_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-hotel');
	_tpl_threads_check_no_fixed_block('pinegrap-hotel', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'event_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_ht_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-hotel', $key, $t);
		_tpl_threads_check_draws('pinegrap-hotel', $key, $t);
	}
	foreach (array('booking_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the guest\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-hotel', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'enquiry_view', 'conversation'));
}

// The sample rooms and vouchers are parts.php's $rooms and $vouchers; the
// assembly keeps every part's return under another name, so the
// collection still seeds every room and the catalog every voucher.
function test_hotel_assembly_samples()
{
	$tpl = _test_ht_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel';
	include $dir . '/parts.php';
	pg_assert_true(count($rooms) >= 6, 'sample rooms in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($rooms as $key => $r) {
		pg_assert_contains(json_encode($r['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($vouchers as $key => $v) {
		pg_assert_contains(json_encode($v['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The hotel's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_hotel_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-hotel', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-hotel');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_rooms"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the rooms and the journal');
}

// Home, the rooms page and the rates page place the Hotel blocks: every
// pg-hotel- class's rule ships in the User Styles, the interactions are
// kept as written and something moves on each, one h1 on each; the home
// opens with a load entrance, shows the Junior Suite in depth and the stay
// clock, its figures band left for the about page; the rooms page shows
// the Terrace Suite before the list; the clock's times are the hotel's.
function test_hotel_assembly_pages_on_hotel_blocks()
{
	$tpl = _test_ht_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	pg_assert_same('2.0.0', $tpl['version'], 'version raised');
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'rooms', 'rates'), true)) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'  => array('pg-hotel-gallery', 'pg-hotel-facts', 'pg-hotel-figure', 'pg-hotel-clock', 'pg-hotel-end'),
		'rooms' => array('pg-hotel-gallery', 'pg-hotel-facts', 'pg-hotel-figure'),
		'rates' => array('pg-hotel-clock', 'pg-hotel-end', 'pg-hotel-figure'),
	);
	pg_assert_same(3, count($trees), 'home, rooms, rates');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_ht_asm_tpl_nodes($tree, $nodes);
		$classes = '';
		$labels = array();
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-hotel-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) {
			if ($class === 'pg-hotel-shot' || $class === 'pg-hotel-main' || $class === 'pg-hotel-end') continue; // styled under their parent's rule
			pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		}
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		if ($key === 'home') pg_assert_false(in_array('Figures', $labels, true), 'home: no figures band');
	}
	pg_assert_contains('.pg-hotel-clock .pg-hotel-end', $css, 'the stay cards are styled');
	pg_assert_contains('.pg-hotel-gallery .pg-hotel-main', $css, 'the main picture is styled');

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	pg_assert_contains('"id":"ht-home-room"', $home, 'the showcase is a search section');
	pg_assert_contains('"id":"ht-home-stay"', $home, 'the clock is a search section');
	pg_assert_contains('"text":"Junior Suite"', $home, 'the home shows the Junior Suite');
	foreach (array('Guests', 'Floor Area', 'Beds', 'Room view') as $fact) pg_assert_contains('"text":"' . $fact . '"', $home, 'the fact ' . $fact);
	foreach (array('15:00', '11:00') as $t) pg_assert_contains('"tag":"time","cssClass":"pg-hotel-figure pht-price d-block display-5 fw-bold mb-3","_label":"Time","text":"' . $t . '","_attrs":[{"name":"datetime","value":"' . $t . '"}]', $home, 'the time ' . $t);
	pg_assert_contains('"href":"tel:+905550000000"', $home, 'the reception\'s phone');
	pg_assert_contains('{{page:book}}?room=Junior%20Suite', $home, 'ask for the shown room');
	$rooms = json_encode($trees['rooms'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"text":"Terrace Suite"', $rooms, 'the rooms page shows the Terrace Suite');
	pg_assert_false(strpos($rooms, '"href":"{{page:rooms}}"') !== false && strpos($rooms, '"text":"All Rooms"') !== false, 'the rooms page does not link to itself');
	pg_assert_contains('"id":"ht-rooms-showcase"', $rooms, 'the rooms showcase is a search section');
	$rates = json_encode($trees['rates'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"id":"ht-rates-stay"', $rates, 'the rates clock is a search section');
}

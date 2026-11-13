<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Home Services design template
 * (includes/design_templates/pinegrap-homeservices.php): it is offered as a
 * template that opens without the shop, with the care plans' catalog,
 * carries every part's pages in order (exactly the plan's pages) and each
 * widget once, only shop pages need the shop, every placed widget, shared
 * part and page placeholder names something the template defines, the
 * contact page's parts are search sections, the phs- classes equal their
 * rules, the comment threads run on the Comments widget with the reply
 * e-mail's card, and the sample services, team and care plans survive the
 * assembly.
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
require_once(__DIR__ . '/design_template_homeservices_test.php');

function _test_hs_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_hs_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_hs_asm_tpl_nodes($child, $out);
	}
}

function test_homeservices_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-homeservices']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['services']) && !isset($all['visits']), 'the parts folder is not read as a template');
	$tpl = _test_hs_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-tools', $tpl['icon']);
	pg_assert_same(123, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the care plans\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('modern-sharp', $tpl['look']);
	pg_assert_same('steel', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_homeservices_assembly_template_pages_and_widgets()
{
	$tpl = _test_hs_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/services.php',
		include $dir . '/visits.php',
		include $dir . '/estimates.php',
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
	$plan = _test_hs_page_keys();
	sort($plan);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($plan, $sorted, 'exactly the plan\'s pages');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'visit_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_hs_asm_tpl_nodes($tree, $nodes);
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

function test_homeservices_assembly_template_search_and_look()
{
	$tpl = _test_hs_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_hs_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(phs-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_hs_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bphs-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'phs- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_hs_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'service_reviews' => array('page' => 'service', 'view' => 'service_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'visit_messages' => array('page' => 'visit_view', 'view' => 'visit_detail') + $request,
		'estimate_messages' => array('page' => 'estimate_view', 'view' => 'estimate_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_homeservices_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-homeservices');
	_tpl_threads_check_no_fixed_block('pinegrap-homeservices', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'estimate_messages' => 'comments',
		'post_comments' => 'comments', 'service_reviews' => 'comments', 'visit_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'service' => lang('Review'), 'visit_view' => lang('Message'), 'estimate_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_hs_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-homeservices', $key, $t);
		// The reviews answer one level deep, so the shared draw's depth-two
		// check does not apply to them.
		if ($key !== 'service_reviews') _tpl_threads_check_draws('pinegrap-homeservices', $key, $t);
	}
	foreach (array('visit_detail', 'estimate_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the guest\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-homeservices', 'email_comment', 'email_reply',
		array('blog_post', 'service', 'visit_view', 'estimate_view', 'conversation'));
}

// The sample services, team and care plans are parts.php's $services,
// $team and $care_plans; the assembly keeps every part's return under
// another name, so the collections still seed every service and team
// member and the catalog every plan.
function test_homeservices_assembly_samples()
{
	$tpl = _test_hs_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	pg_assert_true(count($services) >= 6, 'sample services in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($services as $key => $s) {
		pg_assert_contains(json_encode($s['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the service is in the template');
	}
	foreach ($team as $n => $m) {
		pg_assert_contains(json_encode($m['name'], JSON_UNESCAPED_UNICODE), $json, $n . ': the team member is in the template');
	}
	foreach ($areas as $key => $a) {
		pg_assert_contains(json_encode($a['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the area is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($care_plans as $key => $p) {
		pg_assert_contains(json_encode($p['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The company's Google reviews sit under the customers' words on the home
// page, with the layout Google asks for.
function test_homeservices_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-homeservices', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-homeservices');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	foreach (array('home_services', 'home_team', 'home_posts') as $key) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $key . '"') !== false, 'the home places ' . $key);
	}
}

// Every node of a tree whose class list holds the class.
function _test_hs_asm_with_class($tree, $class)
{
	$nodes = array();
	_test_hs_asm_tpl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '')) $out[] = $n;
	}
	return $out;
}

// Every node of a tree with the tag.
function _test_hs_asm_with_tag($tree, $tag)
{
	$nodes = array();
	_test_hs_asm_tpl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === $tag) $out[] = $n;
	return $out;
}

// The words of a tree, joined.
function _test_hs_asm_words($tree)
{
	$nodes = array();
	_test_hs_asm_tpl_nodes($tree, $nodes);
	$words = array();
	foreach ($nodes as $n) if (isset($n['props']['text']) && is_string($n['props']['text'])) $words[] = $n['props']['text'];
	return implode(' | ', $words);
}

// The node's attribute, or null.
function _test_hs_asm_attr($node, $name)
{
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) if ($a['name'] === $name) return $a['value'];
	return null;
}

// Version 2.0.0 draws the home, the service areas and the price guide on
// the blocks: the home opens with the Hero with Job Sheet and carries the
// Arrival Windows and the dark Emergency Line, the areas page draws the
// Zones. Every block class they place ships its rule in the template's
// User Styles, every interaction is kept as written by pg_fx_normalize(),
// each page has one h1, the job sheet's visit is the template's own (its
// service, window, price and technician), the windows are the visit
// form's, the emergency line calls the company's number, and the zones
// hold every area with the call-out charge where it applies.
function test_homeservices_assembly_pages_on_homeservices_blocks()
{
	$tpl = _test_hs_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/parts.php';
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'areas', 'prices'), true)) $trees[$p['key']] = $p['tree'];
	pg_assert_same(3, count($trees), 'the home, the areas and the price guide');
	$wanted = array(
		'home'   => array('pg-homeservices-sheet', 'pg-homeservices-paper', 'pg-homeservices-no', 'pg-homeservices-stamp', 'pg-homeservices-facts',
			'pg-homeservices-fact', 'pg-homeservices-call', 'pg-homeservices-safety', 'pg-window', 'pg-window-icon', 'pg-window-day',
			'pg-window-am', 'pg-window-pm', 'pg-window-eve'),
		'areas'  => array('pg-zone-map', 'pg-zone-ring', 'pg-zone-z1', 'pg-zone-z3', 'pg-zone-key', 'pg-zone-list'),
		'prices' => array(),
	);
	$blocks = array(
		'home'   => array('Home Services (Hero with Job Sheet)', 'Booking (Arrival Windows)', 'Home Services (Emergency Line)'),
		'areas'  => array('Locations (Zones)'),
		'prices' => array(),
	);
	$search = array(
		'home'   => array('hs-home-categories', 'hs-home-windows', 'hs-home-emergency'),
		'areas'  => array('hs-call-out', 'hs-areas'),
		'prices' => array('hs-price-guide', 'hs-how-it-works', 'hs-how-prices-work'),
	);
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_hs_asm_tpl_nodes($tree, $nodes);
		$class_list = '';
		$labels = array();
		$ids = array();
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$class_list .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-(?:homeservices|window|zone)(?:-[a-z0-9]+)*(?![\w-])/', $class_list, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $class_list), $key . ': places ' . $class);
		}
		foreach ($blocks[$key] as $label) pg_assert_true(in_array($label, $labels, true), $key . ': the ' . $label . ' block');
		foreach ($search[$key] as $id) pg_assert_true(in_array($id, $ids, true), $key . ': the search section ' . $id);
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_true($effects >= 3, $key . ': scrolls in motion');
	}

	// The hero: its words come in on load and the job sheet after them;
	// the sheet is a visit of the template's own.
	$home = $trees['home'];
	$sheet = _test_hs_asm_with_class($home, 'pg-homeservices-sheet');
	pg_assert_same(1, count($sheet), 'one job sheet');
	pg_assert_same('scale', isset($sheet[0]['props']['_fx']['effect']) ? $sheet[0]['props']['_fx']['effect'] : '', 'the sheet scales in');
	pg_assert_same('load', isset($sheet[0]['props']['_fx']['trigger']) ? $sheet[0]['props']['_fx']['trigger'] : '', 'the sheet comes in on load');
	$words = _test_hs_asm_words($sheet[0]);
	foreach (array($services['leak_repair']['name'], $time_windows[0], $money($service_prices['leak_repair']), $team[0]['name'], $visit_statuses[1]) as $fact) {
		pg_assert_contains($fact, $words, 'the job sheet names ' . $fact);
	}
	pg_assert_same(4, count(_test_hs_asm_with_class($sheet[0], 'pg-homeservices-fact')), 'four facts on the sheet');
	$hero = null;
	foreach (_test_hs_asm_with_tag($home, 'section') as $s) if (isset($s['props']['_label']) && $s['props']['_label'] === 'Home Services (Hero with Job Sheet)') $hero = $s;
	pg_assert_same(1, count(_test_hs_asm_with_tag($hero, 'h1')), 'the hero holds the h1');

	// The windows: one card per window, in the visit form's order, each
	// with its name and hours; the day's drawing is hidden from screen
	// readers, which read the times.
	$cards = _test_hs_asm_with_class($home, 'pg-window');
	pg_assert_same(count($time_windows), count($cards), 'a card per time window');
	$i = 0;
	foreach ($arrival_windows as $wkey => $w) {
		$words = _test_hs_asm_words($cards[$i]);
		pg_assert_contains($w['name'], $words, $wkey . ': its name');
		pg_assert_same(0, strpos($time_windows[$i], $w['name']), $wkey . ': the form\'s window ' . $i . ' is this one');
		pg_assert_contains(ltrim($w['from'], '0'), $time_windows[$i], $wkey . ': the form has its start');
		pg_assert_contains($w['to'], $time_windows[$i], $wkey . ': the form has its end');
		$times = _test_hs_asm_with_tag($cards[$i], 'time');
		pg_assert_same(array($w['from'], $w['to']), array(_test_hs_asm_attr($times[0], 'datetime'), _test_hs_asm_attr($times[1], 'datetime')), $wkey . ': its times');
		$i++;
	}
	foreach (_test_hs_asm_with_class($home, 'pg-window-day') as $day) {
		pg_assert_same('true', _test_hs_asm_attr($day, 'aria-hidden'), 'a window drawing is hidden from screen readers');
	}

	// The emergency line: a dark band whatever the visitor's theme, the
	// company's number to call, the ringing mark hidden from screen
	// readers, and the four safety steps.
	$band = null;
	foreach (_test_hs_asm_with_tag($home, 'section') as $s) if (isset($s['props']['_label']) && $s['props']['_label'] === 'Home Services (Emergency Line)') $band = $s;
	pg_assert_true($band !== null, 'the emergency band');
	pg_assert_same('dark', _test_hs_asm_attr($band, 'data-bs-theme'), 'the band is dark');
	$tel = 'tel:' . preg_replace('/[^0-9+]/', '', $company['emergency']);
	pg_assert_contains('"href":"' . $tel . '"', json_encode($band), 'the band calls the emergency number');
	pg_assert_contains($company['emergency'], _test_hs_asm_words($band), 'the band shows the emergency number');
	$call = _test_hs_asm_with_class($band, 'pg-homeservices-call');
	pg_assert_same('true', _test_hs_asm_attr($call[0], 'aria-hidden'), 'the ringing mark is hidden from screen readers');
	$safety = _test_hs_asm_with_class($band, 'pg-homeservices-safety');
	pg_assert_same('ol', $safety[0]['props']['tag'], 'the safety steps are numbered');
	pg_assert_same(4, count($safety[0]['children']), 'four safety steps');

	// The zones: three rings around the base, hidden from screen readers;
	// the list holds every area, the call-out charge on the outer areas,
	// and each window as a time slot.
	$areas_tree = $trees['areas'];
	$map = _test_hs_asm_with_class($areas_tree, 'pg-zone-map');
	pg_assert_same(1, count($map), 'one zone drawing');
	pg_assert_same('true', _test_hs_asm_attr($map[0], 'aria-hidden'), 'the drawing is hidden from screen readers');
	pg_assert_same(3, count(_test_hs_asm_with_class($map[0], 'pg-zone-ring')), 'three rings');
	$keys = _test_hs_asm_with_class($areas_tree, 'pg-zone-key');
	pg_assert_same(3, count($keys), 'a key per zone');
	foreach ($keys as $k) pg_assert_same('true', _test_hs_asm_attr($k, 'aria-hidden'), 'a zone key is hidden from screen readers');
	$lists = _test_hs_asm_with_class($areas_tree, 'pg-zone-list');
	$zones = _test_hs_asm_words($lists[0]);
	foreach ($areas as $akey => $a) pg_assert_contains($a['name'], $zones, $akey . ': the zones name the area');
	$rows = $lists[0]['children'];
	pg_assert_same(3, count($rows), 'three zones');
	pg_assert_contains(lang('None'), _test_hs_asm_words($rows[0]), 'no call-out charge in the core areas');
	pg_assert_contains($money($call_out), _test_hs_asm_words($rows[1]), 'the call-out charge in the outer areas');
	$slots = _test_hs_asm_with_tag($lists[1], 'time');
	pg_assert_same(2 * count($arrival_windows), count($slots), 'each window as a time slot');

	// The price guide: each category's table rises on view.
	foreach (array_keys($categories) as $ckey) {
		$block = null;
		foreach (_test_hs_asm_with_tag($trees['prices'], 'div') as $d) if (isset($d['props']['id']) && $d['props']['id'] === 'hs-prices-' . $ckey) $block = $d;
		pg_assert_true($block !== null && isset($block['props']['_fx']), $ckey . ': its table rises on view');
	}
}

// The header opens out from 1200 px, where its six links and two buttons
// fit, and leaves Get an Estimate to the collapsed menu from 1200 to 1399
// px; the price tables' scroll boxes are positioned, so the hidden heading
// of their last column gives a phone no sideways scroll; on the dark
// theme's tertiary bands coloured text takes the palette's emphasis tint.
function test_homeservices_assembly_header_tables_and_dark_bands()
{
	$tpl = _test_hs_asm_tpl();
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/parts.php';
	$head = $header();
	$navs = _test_hs_asm_with_class($head, 'navbar');
	pg_assert_same(1, count($navs), 'one navbar');
	pg_assert_contains('navbar-expand-xl', $navs[0]['props']['cssClass'], 'the menu opens out from 1200 px');
	$estimate = null;
	foreach (_test_hs_asm_with_class($head, 'btn-outline-primary') as $b) $estimate = $b;
	pg_assert_true($estimate !== null, 'the header asks for an estimate');
	pg_assert_contains('d-xl-none d-xxl-inline-block', $estimate['props']['cssClass'], 'Get an Estimate waits for 1400 px in the open bar');

	$prices = null;
	foreach ($tpl['pages'] as $p) if ($p['key'] === 'prices') $prices = $p['tree'];
	$boxes = _test_hs_asm_with_class($prices, 'table-responsive');
	pg_assert_same(count($categories), count($boxes), 'a scroll box per category');
	foreach ($boxes as $b) pg_assert_contains('position-relative', $b['props']['cssClass'], 'the scroll box holds its hidden heading');

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	// Small coloured text on a tertiary band is mixed darker in the light
	// theme and lighter in the dark one (Steel's dark text colour: 4.2:1).
	pg_assert_contains('{.bg-body-tertiary{--phs-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 90%,black);', $css, 'darker on a light tertiary band');
	pg_assert_contains('{[data-bs-theme=dark] .bg-body-tertiary{--phs-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,white);', $css, 'lighter on a dark tertiary band');
	pg_assert_contains('.bg-body-tertiary :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--phs-band-primary,var(--pg-primary-fg,var(--bs-primary)))!important}', $css, 'primary text on a tertiary band');
	pg_assert_contains('.bg-body-tertiary :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--phs-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))!important}', $css, 'secondary links on a tertiary band');
	pg_assert_contains('.bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--phs-band-primary,var(--pg-primary-fg,var(--bs-primary)))}', $css, 'outline buttons on a tertiary band');
}

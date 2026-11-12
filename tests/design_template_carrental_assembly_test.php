<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Car Rental design template
 * (includes/design_templates/pinegrap-carrental.php): it is offered with
 * its look, folders and newsletter group; every part's pages come in
 * order with every widget once and placed, every placeholder names a page
 * or folder, only the address book needs the shop; the search anchors are
 * unique and the contact page is one; the pcr- classes match their rules;
 * the comment threads keep their page settings and the requests' messages
 * are as private as the requests; the Google reviews sit under the
 * renters' words; the sample cars are seeded; the home and the extras
 * page are drawn on the Car Rental blocks.
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

function _test_cr_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_cr_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cr_asm_nodes($child, $out);
	}
}

function test_carrental_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-carrental']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['fleet']) && !isset($all['bookings']), 'the parts folder is not read as a template');
	$tpl = _test_cr_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-key', $tpl['icon']);
	pg_assert_same(131, $tpl['order']);
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-carrental' && isset($other['order'])) pg_assert_true($other['order'] !== 131, "order 131 is not also $id's");
	}
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(empty($tpl['catalog']), 'no catalog: the rental is paid at the desk');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('modern-soft', $tpl['look']);
	pg_assert_same('amber', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_carrental_assembly_template_pages_and_widgets()
{
	$tpl = _test_cr_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$parts = array(include $dir . '/fleet.php');
	include $dir . '/mail.php';
	$parts[] = include $dir . '/bookings.php';
	$parts[] = include $dir . '/business.php';
	$parts[] = include $dir . '/standard.php';
	$parts[] = include $dir . '/account.php';
	$expected = array();
	foreach ($public as $p) $expected[] = $p['key'];
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
	// The test helpers' list of the finished template's pages, the account
	// e-mails aside, is the template's.
	require_once __DIR__ . '/design_template_carrental_test.php';
	pg_assert_same(array(), array_values(array_diff($keys, _test_cr_page_keys(), array('email_account_welcome', 'email_password_reset'))), 'no page left out of the helpers\' list');
	pg_assert_same(array(), array_values(array_diff(_test_cr_page_keys(), $keys)), 'every listed page is built');
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': only the address book needs the shop');
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
		_test_cr_asm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	foreach ($tpl['widgets'] as $key => $w) pg_assert_true($w['page'] === '' || in_array($w['page'], $keys, true), "widget $key: its page exists");
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

function test_carrental_assembly_template_search_and_look()
{
	$tpl = _test_cr_asm_tpl();
	$searchable_pages = array();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_cr_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($ids) $searchable_pages[] = $p['key'];
	}
	foreach (array('contact', 'locations', 'extras', 'deals', 'terms', 'faq') as $key) {
		pg_assert_true(in_array($key, $searchable_pages, true), "$key: a search section");
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pcr-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_cr_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpcr-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pcr- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Archivo', 'Inter'), array_column($fonts, 'family'), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_carrental_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-carrental');
	_tpl_threads_check_no_fixed_block('pinegrap-carrental', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'enquiry_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-carrental', $key, $t);
		_tpl_threads_check_draws('pinegrap-carrental', $key, $t);
	}
	foreach (array('booking_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the renter\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-carrental', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'enquiry_view', 'conversation'));
}

// The sample cars are parts.php's $cars; the assembly keeps every part's
// return under another name, so the Fleet collection still seeds every car.
function test_carrental_assembly_samples()
{
	$tpl = _test_cr_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	pg_assert_true(count($cars) >= 4, 'sample cars in parts.php');
	$forms = array();
	foreach ($tpl['pages'] as $p) if (isset($p['form'])) $forms[$p['key']] = $p['form'];
	pg_assert_same(count($cars), count($forms['car_new']['sample_records']), 'every sample car is seeded');
	pg_assert_true(count($forms['blog_new']['sample_records']) >= 4, 'the journal\'s sample posts are seeded');
}

// The company's Google reviews sit under the renters' words on the home
// page, with the layout Google asks for.
function test_carrental_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-carrental', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-carrental');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_cars', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

// Every node of a tree whose class list holds the class.
function _test_cr_asm_with_class($tree, $class)
{
	$nodes = array();
	_test_cr_asm_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '')) $out[] = $n;
	}
	return $out;
}

// The words of a tree, joined.
function _test_cr_asm_words($tree)
{
	$nodes = array();
	_test_cr_asm_nodes($tree, $nodes);
	$words = array();
	foreach ($nodes as $n) if (isset($n['props']['text']) && is_string($n['props']['text'])) $words[] = $n['props']['text'];
	return implode(' | ', $words);
}

// Version 2.0.0 draws the home and the extras page on the Car Rental
// blocks: every block class they place ships its rule in the template's
// User Styles, every interaction is kept as written by pg_fx_normalize(),
// each page has one h1, the hero comes in on load with its car driving a
// road screen readers skip, the line-up lists every class from the
// cheapest up with its rate and deposit, the three cover levels lower the
// excess in turn, and the sample quote's lines add up to its total.
function test_carrental_assembly_pages_on_carrental_blocks()
{
	$tpl = _test_cr_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/parts.php';
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'extras'), true)) $trees[$p['key']] = $p['tree'];
	pg_assert_same(2, count($trees), 'the home and the extras page');
	$wanted = array(
		'home'   => array('pg-carrental-slip', 'pg-carrental-trip', 'pg-carrental-road', 'pg-carrental-car', 'pg-carrental-photo',
			'pg-carrental-rail', 'pg-carrental-lineup', 'pg-carrental-class', 'pg-carrental-receipt', 'pg-carrental-figure', 'pg-ratio-16x9'),
		'extras' => array('pg-carrental-figure'),
	);
	$blocks = array(
		'home'   => array('Car Rental Hero', 'Car Rental Class Line-up', 'Car Rental Sample Quote', 'Car Rental Cover Levels'),
		'extras' => array('Car Rental Cover Levels'),
	);
	$search = array(
		'home'   => array('cr-home-classes', 'cr-home-quote'),
		'extras' => array('cr-cover', 'cr-extras'),
	);
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_cr_asm_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-(?:carrental|ratio)-[a-z0-9-]+(?![\w-])/', $class_list, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $class_list), $key . ': places ' . $class);
		}
		foreach ($blocks[$key] as $label) pg_assert_true(in_array($label, $labels, true), $key . ': the ' . $label . ' block');
		foreach ($search[$key] as $id) pg_assert_true(in_array($id, $ids, true), $key . ': the search section ' . $id);
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_true($effects >= 4, $key . ': opens and scrolls in motion');

		// The cover levels: three cards, the excess lower at each, one
		// marked as the most chosen; the bars are hidden from screen readers.
		$band = null;
		foreach ($nodes as $n) if (isset($n['props']['_label']) && $n['props']['_label'] === 'Car Rental Cover Levels') $band = $n;
		pg_assert_true($band !== null, $key . ': the cover levels');
		$cards = array();
		$band_nodes = array();
		_test_cr_asm_nodes($band, $band_nodes);
		foreach ($band_nodes as $n) if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'article') $cards[] = $n;
		pg_assert_same(count($covers), count($cards), $key . ': a card per cover');
		$i = 0;
		foreach ($covers as $ckey => $c) {
			$words = _test_cr_asm_words($cards[$i]);
			pg_assert_contains($c['title'], $words, $key . ': cover ' . $ckey . ' in order');
			pg_assert_contains($money($c['excess']), $words, $key . ': cover ' . $ckey . ' names its excess');
			pg_assert_same($c['chosen'], strpos($cards[$i]['props']['cssClass'], 'border-primary') !== false, $key . ': cover ' . $ckey . ' marked as chosen or not');
			$i++;
		}
		foreach (_test_cr_asm_with_class($band, 'progress') as $bar) {
			pg_assert_contains('{"name":"aria-hidden","value":"true"}', json_encode(isset($bar['props']['_attrs']) ? $bar['props']['_attrs'] : array()), $key . ': a cover bar is hidden from screen readers');
		}
	}
	$keys = array_keys($covers);
	foreach (array_keys($covers['basic']['excess']) as $code) {
		for ($i = 1; $i < count($keys); $i++) {
			pg_assert_true($covers[$keys[$i]]['excess'][$code] < $covers[$keys[$i - 1]]['excess'][$code], $keys[$i] . ': a lower excess in ' . $code);
		}
	}
	pg_assert_same(1, count(array_filter(array_column($covers, 'chosen'))), 'one cover is the most chosen');
	pg_assert_same($extras['cover']['price'], $covers['full']['price'], 'Full Cover costs what the extra costs');

	// The hero: its line comes in on load and a car drives the road.
	$home = $trees['home'];
	pg_assert_contains('"trigger":"load"', json_encode($home), 'the hero comes in on load');
	$road = _test_cr_asm_with_class($home, 'pg-carrental-road');
	pg_assert_same(1, count($road), 'one road on the trip slip');
	pg_assert_contains('{"name":"aria-hidden","value":"true"}', json_encode(isset($road[0]['props']['_attrs']) ? $road[0]['props']['_attrs'] : array()), 'the road is hidden from screen readers');
	$car = _test_cr_asm_with_class($home, 'pg-carrental-car');
	pg_assert_same('load', isset($car[0]['props']['_fx']['trigger']) ? $car[0]['props']['_fx']['trigger'] : '', 'the car drives on load');

	// The line-up: a tile per class, cheapest first, each with its rate
	// and its deposit.
	$tiles = _test_cr_asm_with_class($home, 'pg-carrental-class');
	$sorted = $classes;
	uasort($sorted, function ($a, $b) { return $a['rate']['USD'] - $b['rate']['USD']; });
	pg_assert_same(array_column($sorted, 'title'), array_map(function ($t) { return $t['props']['_label']; }, $tiles), 'a tile per class, cheapest first');
	$i = 0;
	foreach ($sorted as $ckey => $c) {
		$words = _test_cr_asm_words($tiles[$i++]);
		pg_assert_contains($money($c['rate']), $words, $ckey . ': the tile names the day rate');
		pg_assert_contains($money($c['deposit']), $words, $ckey . ': the tile names the deposit');
	}

	// The quote: a mid-size car and Full Cover for seven days, a day off
	// for the weekly deal; the total is the sum of the lines.
	$receipt = _test_cr_asm_with_class($home, 'pg-carrental-receipt');
	pg_assert_same(1, count($receipt), 'one receipt');
	$words = _test_cr_asm_words($receipt[0]);
	$rate = $classes['compact']['rate'];
	$cover = $extras['cover']['price'];
	$total = array();
	foreach ($rate as $code => $amount) $total[$code] = $amount * 6 + $cover[$code] * 7;
	foreach (array($money($times($rate, 7)), $money($times($cover, 7)), "\xE2\x88\x92" . $money($rate), $money($total)) as $figure) {
		pg_assert_contains($figure, $words, 'the receipt shows ' . $figure);
	}
	pg_assert_contains($money($times($classes['compact']['deposit'], $covers['full']['deposit'])), $words, 'the receipt names the smaller deposit');
}

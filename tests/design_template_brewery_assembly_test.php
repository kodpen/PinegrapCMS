<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Craft Brewery design template
 * (includes/design_templates/pinegrap-brewery.php): it is offered without
 * a shop requirement and with its catalog; every part's pages come in
 * order, only the shop pages and the address book need the shop; every
 * widget is defined once and placed; placeholders name pages and folders;
 * the club folder is a membership folder; the contact page's search
 * sections; the pbw- classes and their rules match; the comment threads
 * and the reply e-mail card; the home page's lists; the sample beers, the
 * sample events and the shop's products kept.
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
require_once(__DIR__ . '/design_template_brewery_test.php');

function _test_bw_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_bw_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bw_asm_nodes($child, $out);
	}
}

function test_brewery_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-brewery']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['beers']) && !isset($all['events']), 'the parts folder is not read as a template');
	$tpl = _test_bw_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-cup-straw', $tpl['icon']);
	pg_assert_same(129, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the shop\'s catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'club'), array_keys($tpl['folders']));
	pg_assert_same('membership', $tpl['folders']['club']['access'], 'a Beer Club term opens the club folder');
	pg_assert_same('organic', $tpl['look']);
	pg_assert_same('amber', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_brewery_assembly_template_pages_and_widgets()
{
	$tpl = _test_bw_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/beers.php',
		include $dir . '/events.php',
		include $dir . '/tours.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'news_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop; the
	// club's members' area is a shop page too, behind its folder's access.
	$shop_keys = array('address_book');
	foreach ($parts[4]['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	$expected_shop = array_merge(array('address_book'), _test_bw_shop_keys());
	sort($expected_shop);
	$sorted = $shop_keys;
	sort($sorted);
	pg_assert_same($expected_shop, $sorted, 'the shop pages');
	foreach (array('club_home', 'club_releases') as $key) pg_assert_same('club', _tpl_threads_page($tpl, $key)['folder'], $key . ': in the club folder');
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
		_test_bw_asm_nodes($tree, $nodes);
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

function test_brewery_assembly_template_search_and_look()
{
	$tpl = _test_bw_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_bw_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pbw-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_bw_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpbw-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pbw- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a booking's messages are as
// private as the booking.
function test_brewery_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'beer_notes' => array('page' => 'beer', 'view' => 'beer_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-brewery');
	_tpl_threads_check_no_fixed_block('pinegrap-brewery', array(
		'beer_notes' => 'comments', 'booking_messages' => 'comments', 'conversation_replies' => 'comments',
		'email_comment' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('beer' => lang('Note'), 'blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	pg_assert_true(!empty(_tpl_threads_page($tpl, 'beer')['comments']['rating']), 'a drinker\'s note carries stars');
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-brewery', $key, $t);
		// The drinkers' notes are threaded one level deep, which the drawing
		// fixture (an answer two levels down) does not fit.
		if ($key !== 'beer_notes') _tpl_threads_check_draws('pinegrap-brewery', $key, $t);
	}
	foreach (array('booking_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-brewery', 'email_comment', 'email_reply',
		array('beer', 'blog_post', 'booking_view', 'conversation'));
}

// The sample beers and events are parts.php's $beers and $events, the
// shop's products its packs, gift cards and club terms; the assembly keeps
// every part's return under another name, so the collections still seed
// every beer and event and the catalog every product.
function test_brewery_assembly_samples()
{
	$tpl = _test_bw_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	pg_assert_true(count($beers) >= 4, 'sample beers in parts.php');
	pg_assert_true(count($events) >= 3, 'sample events in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($beers as $key => $b) {
		pg_assert_contains(json_encode($b['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_merge(array_values($packs), array_values($gift_cards), array_values($club_terms)) as $p) {
		if (!isset($p['sku'])) continue;
		pg_assert_contains(json_encode($p['sku'], JSON_UNESCAPED_UNICODE), $catalog, $p['sku'] . ': its product is in the catalog');
	}
}

// The home page places its beers, events and news, and the tours page
// leads to the private tastings.
function test_brewery_assembly_home_and_tours()
{
	$tpl = _tpl_threads_tpl('pinegrap-brewery');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_beers', 'home_events', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
	pg_assert_contains('{{page:book_online}}', json_encode(_tpl_threads_page($tpl, 'tours')['tree']), 'the tours lead to the private tastings');
}

// The nodes of a tree whose class list holds $class, depth first.
function _test_bw_asm_with_class($tree, $class)
{
	$nodes = array();
	_test_bw_asm_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '')) $out[] = $n;
	}
	return $out;
}

// Version 2.0.0 draws the home, the taproom and the beer page on the
// Brewery blocks: every block class they place ships its rule in the
// template's User Styles, every interaction is kept as written by
// pg_fx_normalize(), each page has one h1, the glasses are hidden from
// screen readers, the tap board lists every sample beer, the flight pours
// four tasters light to dark one after another, and the beer page's
// sticker and figures are the record's own fields.
function test_brewery_assembly_pages_on_brewery_blocks()
{
	$tpl = _test_bw_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/parts.php';
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'taproom', 'about'), true)) $trees[$p['key']] = $p['tree'];
	$trees['beer'] = $tpl['widgets']['beer_detail']['tree'];
	pg_assert_same(4, count($trees), 'home, taproom, about and the beer page');
	$wanted = array(
		'home'    => array('pg-brewery-glass', 'pg-brewery-pour', 'pg-brewery-photo', 'pg-brewery-tap', 'pg-brewery-mini', 'pg-brewery-paddle', 'pg-brewery-taster', 'pg-brewery-sticker', 'pg-brewery-figure'),
		'taproom' => array('pg-brewery-tap', 'pg-brewery-mini', 'pg-brewery-paddle', 'pg-brewery-taster', 'pg-brewery-figure'),
		'beer'    => array('pg-brewery-photo', 'pg-brewery-sticker', 'pg-brewery-figure'),
	);
	$blocks = array(
		'home'    => array('Brewery Hero', 'Brewery Tap Board', 'Brewery Release', 'Brewery Tasting Flight'),
		'taproom' => array('Brewery Tap Board', 'Brewery Tasting Flight'),
		'beer'    => array('Release Facts'),
	);
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_bw_asm_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-brewery-[a-z0-9-]+(?![\w-])/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach (isset($wanted[$key]) ? $wanted[$key] : array() as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $classes), $key . ': places ' . $class);
		}
		foreach (isset($blocks[$key]) ? $blocks[$key] : array() as $label) pg_assert_true(in_array($label, $labels, true), $key . ': the ' . $label . ' block');
		foreach (_test_bw_asm_with_class($tree, 'pg-brewery-glass') as $glass) {
			pg_assert_contains('{"name":"aria-hidden","value":"true"}', json_encode(isset($glass['props']['_attrs']) ? $glass['props']['_attrs'] : array()), $key . ': a glass is hidden from screen readers');
		}
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		if ($key !== 'about') pg_assert_true($effects >= 4, $key . ': opens and scrolls in motion');
	}

	// The home: the hero comes in on load and pours its glass; the story
	// band left the home (the about page keeps it).
	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	$pour = _test_bw_asm_with_class($trees['home'], 'pg-brewery-pour');
	pg_assert_same(1, count($pour), 'one glass over the hero picture');
	$poured = isset($pour[0]['children'][0]['props']['_fx']) ? $pour[0]['children'][0]['props']['_fx'] : array();
	pg_assert_same('load', isset($poured['trigger']) ? $poured['trigger'] : '', 'the hero glass fills on load');
	pg_assert_false(strpos($home, '"id":"bw-home-story"') !== false, 'no story band on the home');
	foreach (array('bw-home-taps', 'bw-home-release', 'bw-home-flight', 'bw-home-tours', 'bw-home-reviews') as $id) {
		pg_assert_contains('"id":"' . $id . '"', $home, 'the home\'s search section ' . $id);
	}
	foreach (array('bw-taproom-taps', 'bw-taproom-visit', 'bw-taproom-food', 'bw-taproom-flight') as $id) {
		pg_assert_contains('"id":"' . $id . '"', json_encode($trees['taproom']), 'the taproom\'s search section ' . $id);
	}

	// The tap board: a tap per sample beer, numbered in order, with its
	// strength and bitterness; the flight: four tasters, tones 1 to 4,
	// each poured after the one before.
	foreach (array('home', 'taproom') as $key) {
		$board = _test_bw_asm_with_class($trees[$key], 'pg-brewery-tap');
		pg_assert_same(count($beers), count($board), $key . ': a tap per beer');
		$texts = json_encode($trees[$key], JSON_UNESCAPED_UNICODE);
		$n = 0;
		foreach ($beers as $bkey => $b) {
			$n++;
			pg_assert_same(sprintf('%02d', $n), isset($board[$n - 1]['props']['text']) ? $board[$n - 1]['props']['text'] : '', $key . ': tap ' . $n . ' is numbered');
			foreach (array($b['name'], $b['abv'], str_replace('{var}', $b['ibu'], lang('{var} IBU'))) as $t) {
				pg_assert_contains(json_encode($t, JSON_UNESCAPED_UNICODE), $texts, $key . ': ' . $bkey . ' shows ' . $t);
			}
		}
		$tasters = _test_bw_asm_with_class($trees[$key], 'pg-brewery-taster');
		pg_assert_same(4, count($tasters), $key . ': four tasters');
		$delay = -1;
		foreach ($tasters as $i => $t) {
			$beer = $t['children'][0];
			$pour_fx = isset($beer['props']['_fx']) ? $beer['props']['_fx'] : array();
			pg_assert_contains('pg-brewery-tone-' . ($i + 1), $beer['props']['cssClass'], $key . ': taster ' . ($i + 1) . ' is tone ' . ($i + 1));
			pg_assert_same('view', isset($pour_fx['trigger']) ? $pour_fx['trigger'] : '', $key . ': taster ' . ($i + 1) . ' pours in view');
			pg_assert_true(isset($pour_fx['delay']) && $pour_fx['delay'] > $delay, $key . ': taster ' . ($i + 1) . ' pours after the one before');
			if (isset($pour_fx['delay'])) $delay = $pour_fx['delay'];
		}
	}
	pg_assert_contains('{{page:book_online}}', json_encode($trees['taproom']), 'the taproom\'s flight books a tasting');

	// The beer page: the release's picture with parallax, the sticker says
	// how long the beer stays, the figures are its style and strength.
	$sticker = _test_bw_asm_with_class($trees['beer'], 'pg-brewery-sticker');
	pg_assert_same(1, count($sticker), 'the beer page has its sticker');
	pg_assert_contains('"text":"availability"', json_encode($sticker[0]), 'the sticker is the beer\'s availability');
	$figures = json_encode(_test_bw_asm_with_class($trees['beer'], 'pg-brewery-figure'));
	foreach (array('style', 'strength') as $field) pg_assert_contains('"text":"' . $field . '"', $figures, 'the beer page\'s figure ' . $field);
	pg_assert_contains('"effect":"parallax"', json_encode($trees['beer']), 'the beer\'s picture moves slower than the page');
}

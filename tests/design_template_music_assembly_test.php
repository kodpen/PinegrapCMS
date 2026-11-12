<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Music Artist design template
 * (includes/design_templates/pinegrap-music.php): it is offered as a
 * template that opens without the shop, with the merch and gift cards'
 * catalog and the public press folder, carries every part's pages in order
 * and each widget once, only shop pages need the shop, every placed
 * widget, shared part, folder and page placeholder names something the
 * template defines, the contact page's parts are search sections, the
 * pmu- classes equal their rules, the comment threads (the fans' words on
 * a release among them) run on the Comments widget with the reply
 * e-mail's card, and the sample releases, shows, merch and gift cards
 * survive the assembly.
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

function _test_mu_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_mu_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mu_asm_tpl_nodes($child, $out);
	}
}

function test_music_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-music']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['releases']) && !isset($all['press']), 'the parts folder is not read as a template');
	$tpl = _test_mu_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-music-note-beamed', $tpl['icon']);
	pg_assert_same(91, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the merch and gift cards\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'press'), array_keys($tpl['folders']));
	pg_assert_same('public', $tpl['folders']['press']['access'], 'the press files are open to everyone');
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('midnight', $tpl['palette'], 'the palette the e-mails\' blues are made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_music_assembly_template_pages_and_widgets()
{
	$tpl = _test_mu_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/releases.php',
		include $dir . '/shows.php',
		include $dir . '/press.php',
		include $dir . '/merch.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'list_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_mu_asm_tpl_nodes($tree, $nodes);
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
	pg_assert_true(in_array('press', $m[1], true), 'the press files go to the press folder');
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_music_assembly_template_search_and_look()
{
	$tpl = _test_mu_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_mu_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pmu-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_mu_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpmu-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pmu- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_mu_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'release_comments' => array('page' => 'release', 'view' => 'release_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and an enquiry's messages are as
// private as the enquiry.
function test_music_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-music');
	_tpl_threads_check_no_fixed_block('pinegrap-music', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'post_comments' => 'comments', 'release_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'),
		'conversation' => lang('reply'), 'release' => lang('Comment'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	// The shared drawing check's sample thread goes two answers deep; the
	// fans' words on a release take one, so they are checked for their
	// settings only.
	foreach (_test_mu_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-music', $key, $t);
		if ($key !== 'release_comments') _tpl_threads_check_draws('pinegrap-music', $key, $t);
	}
	foreach (array('booking_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the sender\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-music', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'conversation', 'release'));
}

// The samples are parts.php's $releases, $shows, $merch and $gift_cards;
// the assembly keeps every part's return under another name, so the
// collections still seed every release and show and the catalog has every
// merch product and gift card.
function test_music_assembly_samples()
{
	$tpl = _test_mu_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
	include $dir . '/parts.php';
	pg_assert_true(count($releases) >= 5 && count($shows) >= 6, 'sample releases and shows in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($releases as $key => $r) {
		pg_assert_contains(json_encode($r['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($shows as $key => $s) {
		pg_assert_contains(json_encode($s['venue'], JSON_UNESCAPED_UNICODE), $json, $key . ': its venue is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_merge($merch, $gift_cards) as $key => $g) {
		pg_assert_contains(json_encode($g['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The home page places its next shows, its releases and the news.
function test_music_assembly_home_lists()
{
	$tpl = _tpl_threads_tpl('pinegrap-music');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_shows', 'home_releases', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

// Home, the discography and the press kit place the Music blocks: every
// pg-music- class's rule ships in the User Styles, the interactions are
// kept as written and something moves on each, one h1 on each; the home
// opens on load with the newest single as a record and its tracks as
// <time>s with ISO durations, the discography shows the newest album
// before the list, the press kit's one-sheet sits beside photos that save;
// the tour lists draw a show's day as the tour dates block does.
function test_music_assembly_pages_on_music_blocks()
{
	$tpl = _test_mu_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version raised');
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'discography', 'press'), true)) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'        => array('pg-music-sleeve', 'pg-music-disc', 'pg-music-cover', 'pg-music-tracks'),
		'discography' => array('pg-music-sleeve', 'pg-music-disc', 'pg-music-cover', 'pg-music-tracks'),
		'press'       => array('pg-music-sheet'),
	);
	pg_assert_same(3, count($trees), 'home, discography, press');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_mu_asm_tpl_nodes($tree, $nodes);
		$classes = '';
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-music-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) {
			if ($class === 'pg-music-disc' || $class === 'pg-music-cover') continue; // styled under the sleeve's rule
			pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		}
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
	}
	pg_assert_contains('.pg-music-sleeve > .pg-music-disc', $css, 'the disc is styled');
	pg_assert_contains('.pg-music-day', $css, 'the show day is styled');

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	pg_assert_contains('"id":"mu-home-release"', $home, 'the release is a search section');
	pg_assert_contains('pmu-hero', $home, 'the home keeps its glow');
	pg_assert_contains('{"name":"data-bs-theme","value":"dark"}', $home, 'on a dark band');
	pg_assert_contains('{"name":"aria-hidden","value":"true"}', $home, 'the disc is hidden from screen readers');
	pg_assert_contains('"text":"3:33","_attrs":[{"name":"datetime","value":"PT3M33S"}]', $home, 'a track length as a duration');
	pg_assert_contains('"text":"Fire Escape"', $home, 'the single\'s second song');
	pg_assert_contains('"href":"{{page:tour}}"', $home, 'the way to the tour');
	$disc = json_encode($trees['discography'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"id":"mu-discography-album"', $disc, 'the album is a search section');
	pg_assert_contains('"text":"Night Bus Hymns"', $disc, 'the discography shows the newest album');
	pg_assert_contains('"text":"6:10","_attrs":[{"name":"datetime","value":"PT6M10S"}]', $disc, 'its last song');
	pg_assert_contains('"templateWidget":"releases_list"', $disc, 'the list stays');
	$press = json_encode($trees['press'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"id":"mu-press-kit"', $press, 'the press kit is a search section');
	foreach (array('Genre', 'From', 'Line-up', 'Record Label', 'Latest Release') as $fact) pg_assert_contains('"tag":"dt","cssClass":"","_label":"Label","text":"' . $fact . '"', $press, 'the fact ' . $fact);
	pg_assert_same(3, substr_count($press, '{"name":"download","value":""}'), 'three photos to save');
	pg_assert_contains('"href":"{{page:booking}}"', $press, 'book the band');
	pg_assert_contains('"href":"#mu-press-files"', $press, 'the way to the press files');
	pg_assert_contains('"id":"mu-press-files"', $press, 'the press files stay');
	$tour = json_encode($tpl['widgets']['tour_list'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('pg-music-day', $tour, 'the tour list draws the day as the block does');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Museum design template
 * (includes/design_templates/pinegrap-museum.php): it is offered as a
 * template that opens without the shop and carries the tickets' and the
 * membership's catalog, every part's pages in order and each widget once,
 * only the shop pages and the address book need the shop, every placed
 * widget, shared part and page placeholder names something the template
 * defines, the contact page's parts are search sections, the pmz- classes
 * equal their rules, the comment threads run on the Comments widget with
 * the reply e-mail's card, the sample exhibitions, objects, events and
 * products survive the assembly, and the Google reviews sit under the
 * sample testimonials.
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
// The page keys of the finished template (_test_mz_page_keys()).
require_once __DIR__ . '/design_template_museum_test.php';

function _test_mz_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_mz_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mz_asm_tpl_nodes($child, $out);
	}
}

function test_museum_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-museum']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['exhibitions']) && !isset($all['learning']), 'the parts folder is not read as a template');
	$tpl = _test_mz_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-bank2', $tpl['icon']);
	pg_assert_same(95, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(array('admission', 'membership'), array_keys($tpl['catalog']['groups']), 'the tickets\' and the membership\'s catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('editorial', $tpl['look']);
	pg_assert_same('burgundy', $tpl['palette'], 'the palette the e-mails\' burgundy is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_museum_assembly_template_pages_and_widgets()
{
	$tpl = _test_mz_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'exhibitions', 'objects', 'events', 'learning', 'shop', 'standard', 'account') as $part_name) {
		$parts[$part_name] = include $dir . '/' . $part_name . '.php';
	}
	$expected = array();
	$widget_count = 0;
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	$shared_keys = _test_mz_page_keys();
	$sorted = $keys;
	sort($shared_keys);
	sort($sorted);
	pg_assert_same($shared_keys, $sorted, 'the page list the part tests share');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'visit_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array('address_book');
	foreach ($parts['shop']['pages'] as $p) $shop_keys[] = $p['key'];
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
		_test_mz_asm_tpl_nodes($tree, $nodes);
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

function test_museum_assembly_template_search_and_look()
{
	$tpl = _test_mz_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_mz_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pmz-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_mz_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpmz-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pmz- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Fraunces', 'Work Sans'), array_column($fonts, 'family'), 'two fonts');
}

function _test_mz_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'school_visit_messages' => array('page' => 'school_visit_view', 'view' => 'school_visit_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_museum_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-museum');
	_tpl_threads_check_no_fixed_block('pinegrap-museum', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'post_comments' => 'comments', 'school_visit_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'school_visit_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_mz_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-museum', $key, $t);
		_tpl_threads_check_draws('pinegrap-museum', $key, $t);
	}
	foreach (array('booking_detail', 'school_visit_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the visitor\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-museum', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'school_visit_view', 'conversation'));
}

// The samples are parts.php's $exhibitions, $objects, $events and the
// tickets and tiers; the assembly keeps every part's return under another
// name, so the collections still seed every sample and the catalog every
// ticket and tier.
function test_museum_assembly_samples()
{
	$tpl = _test_mz_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	pg_assert_true(count($exhibitions) >= 4 && count($objects) >= 6 && count($events) >= 4, 'sample records in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($exhibitions as $key => $e) {
		pg_assert_contains(json_encode($e['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($objects as $key => $o) {
		pg_assert_contains(json_encode($o['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($admission) as $key) {
		pg_assert_contains('MUSEUM-TICKET-' . strtoupper($key), $catalog, $key . ': its ticket is in the catalog');
	}
	foreach (array_keys($tiers) as $key) {
		pg_assert_contains('MUSEUM-MEMBER-' . strtoupper($key), $catalog, $key . ': its tier is in the catalog');
	}
}

// The museum's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_museum_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-museum', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-museum');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_exhibitions', 'home_events', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

// Home, the exhibitions page and the collection page place the Museum
// blocks: every pg-museum- class's rule ships in the User Styles, the
// interactions are kept as written and something moves on each, one h1 on
// each; the home opens on the exhibition on now with its facts and the
// others on, its figures band left for the about page; the collection
// looks closer at one work with its wall label and three numbered details.
function test_museum_assembly_pages_on_museum_blocks()
{
	$tpl = _test_mz_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	pg_assert_same('2.0.0', $tpl['version'], 'version raised');
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'exhibitions', 'collection'), true)) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'        => array('pg-museum-poster', 'pg-museum-facts', 'pg-museum-trail', 'pg-museum-no'),
		'exhibitions' => array('pg-museum-poster', 'pg-museum-facts'),
		'collection'  => array('pg-museum-label', 'pg-museum-no'),
	);
	pg_assert_same(3, count($trees), 'home, exhibitions, collection');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_mz_asm_tpl_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-museum-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		if ($key === 'home') pg_assert_false(in_array('Figures', $labels, true), 'home: no figures band');
	}

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the exhibition hero comes in on load');
	foreach (array('When', 'Where', 'Curator', 'Admission') as $fact) pg_assert_contains('"tag":"dt","cssClass":"","_label":"Label","text":"' . $fact . '"', $home, 'the fact ' . $fact);
	pg_assert_contains('"text":"Also on"', $home, 'the other exhibitions on');
	pg_assert_contains('"id":"mz-home-trail"', $home, 'the trail is a search section');
	$exhibitions = json_encode($trees['exhibitions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false(strpos($exhibitions, '"text":"Also on"') !== false, 'the list holds the others on the exhibitions page');
	$collection = json_encode($trees['collection'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_same(3, substr_count($collection, 'pg-museum-no"'), 'three numbered details');
	pg_assert_contains('"tag":"figcaption","cssClass":"pg-museum-label', $collection, 'the wall label is the caption');
}

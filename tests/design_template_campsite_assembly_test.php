<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Campsite design template
 * (includes/design_templates/pinegrap-campsite.php): it is offered and
 * its parts folder is not; its settings (order, icon, look, palette,
 * folders, the newsletter's contact group, no catalog); every part's pages
 * in order and every widget once; every placed widget and shared part is
 * defined and every placeholder names a page or folder; the contact page's
 * search sections; the pcp- rules match the classes in use; the comment
 * threads keep their settings and draw; the sample pitches and cabins
 * travel with the template; the Google reviews sit on the home page.
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

function _test_cp_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_cp_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cp_asm_nodes($child, $out);
	}
}

function test_campsite_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-campsite']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['units']) && !isset($all['stays']), 'the parts folder is not read as a template');
	$tpl = _test_cp_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-tree', $tpl['icon']);
	pg_assert_same(99, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the park takes no payment online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('elegant', $tpl['look']);
	pg_assert_same('forest', $tpl['palette'], 'the palette the e-mails\' green is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template's order.
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-campsite' && isset($other['order'])) pg_assert_true($other['order'] !== 99, "$id: order 99 is the Campsite template's");
	}
}

function test_campsite_assembly_template_pages_and_widgets()
{
	$tpl = _test_cp_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/units.php',
		include $dir . '/stays.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'stay_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the address book needs the shop.
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': needs the shop only if it is the address book');
	}

	// Every placed widget and shared part is defined, every widget placed,
	// every page and tab placeholder names a page, every folder placeholder
	// a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_cp_asm_nodes($tree, $nodes);
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

function test_campsite_assembly_template_search_and_look()
{
	$tpl = _test_cp_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_cp_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pcp-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_cp_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpcp-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pcp- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Bitter', 'Source Sans 3'), array_column($fonts, 'family'), 'Bitter headings, Source Sans 3 body');
	pg_assert_false((bool)preg_match('/#[0-9a-f]{3,6}\b/i', $css), 'colours are tokens only');
}

function _test_cp_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'request_messages' => array('page' => 'request_view', 'view' => 'request_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_campsite_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-campsite');
	_tpl_threads_check_no_fixed_block('pinegrap-campsite', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'enquiry_messages' => 'comments',
		'post_comments' => 'comments', 'request_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'request_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_cp_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-campsite', $key, $t);
		_tpl_threads_check_draws('pinegrap-campsite', $key, $t);
	}
	foreach (array('request_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the guest\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-campsite', 'email_comment', 'email_reply',
		array('blog_post', 'request_view', 'enquiry_view', 'conversation'));
}

// The samples are parts.php's $units and $kinds; the assembly keeps every
// part's return under another name, so the collection still seeds every
// pitch and cabin and every kind of stay has its page.
function test_campsite_assembly_samples()
{
	$tpl = _test_cp_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite';
	include $dir . '/parts.php';
	pg_assert_true(count($units) >= 6, 'sample pitches and cabins in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($units as $key => $u) {
		pg_assert_contains(json_encode($u['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	foreach (array_keys($kinds) as $kind) pg_assert_true(in_array('stay_' . $kind, $keys, true), $kind . ': a page of its own');
}

// The park's Google reviews sit beside the sample testimonials on the home
// page, with the layout Google asks for.
function test_campsite_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-campsite', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-campsite');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_units"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the units and the News');
}

// Home, the places to stay and the tent pitches page place the Campsite
// blocks: every pg-campsite- class's rule ships in the User Styles, the
// interactions are kept as written and something moves on each; one h1 on
// each; the home opens on the campsite hero with a way into every kind of
// stay and has no figures band; the places to stay lay the kinds on the
// comfort scale; the tent pitches page draws a pitch with its legend.
function test_campsite_assembly_pages_on_campsite_blocks()
{
	$tpl = _test_cp_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'stays', 'stay_tent'), true)) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'      => array('pg-campsite-lift', 'pg-campsite-kinds', 'pg-campsite-scale', 'pg-campsite-stop'),
		'stays'     => array('pg-campsite-scale', 'pg-campsite-stop'),
		'stay_tent' => array('pg-campsite-plot', 'pg-campsite-area', 'pg-campsite-marker'),
	);
	pg_assert_same(3, count($trees), 'home, places to stay, tent pitches');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_cp_asm_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-campsite-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_false(in_array('Figures', $labels, true), $key . ': no figures band');
	}

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	foreach (array('tent', 'touring', 'glamping', 'cabin') as $kind) {
		pg_assert_contains('{{page:stay_' . $kind . '}}', $home, 'the hero leads into ' . $kind);
	}
	pg_assert_contains('{{page:book}}', $home, 'the hero asks for a stay');
	pg_assert_same(4, substr_count($home, 'pg-campsite-stop '), 'four stops on the scale');
	$tent = json_encode($trees['stay_tent'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_same(8, substr_count($tent, 'pg-campsite-marker'), 'four points on the pitch, four in its legend');
	pg_assert_contains('"aria-hidden"', $tent, 'the drawing is hidden from screen readers');
}

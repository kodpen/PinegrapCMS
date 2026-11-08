<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Pharmacy design template
 * (includes/design_templates/pinegrap-pharmacy.php): it is offered without
 * a shop requirement and with its catalog; every part's pages come in
 * order, only the shop pages and the address book need the shop; every
 * widget is defined once and placed; placeholders name pages and folders;
 * every page key is one the part tests list; the contact page's search
 * sections; the prx- classes and their rules match; the comment threads
 * and the reply e-mail card; the Google reviews on the home page; the
 * sample pharmacists and the products kept; the template's name and words
 * in Turkish.
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
// The page keys of the finished template live in the parts test.
require_once __DIR__ . '/design_template_pharmacy_test.php';

function _test_rx_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_rx_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rx_asm_nodes($child, $out);
	}
}

function test_pharmacy_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-pharmacy']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['team']) && !isset($all['requests']), 'the parts folder is not read as a template');
	$tpl = _test_rx_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-capsule', $tpl['icon']);
	pg_assert_same(111, $tpl['order']);
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-pharmacy' && isset($other['order'])) pg_assert_true($other['order'] !== $tpl['order'], "$id has another order");
	}
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the health products\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('modern-soft', $tpl['look']);
	pg_assert_same('ocean', $tpl['palette'], 'the palette the e-mails\' teal is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_pharmacy_assembly_template_pages_and_widgets()
{
	$tpl = _test_rx_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$rx_parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/team.php',
		include $dir . '/requests.php',
		include $dir . '/shop.php',
		include $dir . '/standard.php',
		include $dir . '/account.php',
	);
	$expected = array();
	$widget_count = 0;
	foreach ($rx_parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'booking_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));

	// The part tests' list of page keys is the template's, the e-mails
	// aside.
	$listed = _test_rx_page_keys();
	foreach ($keys as $key) {
		if (strpos($key, 'email_') !== 0) pg_assert_true(in_array($key, $listed, true), "$key is in the part tests' list");
	}
	foreach ($listed as $key) pg_assert_true(in_array($key, $keys, true), "listed page $key exists");

	// Only the shop's pages and the address book need the shop.
	$shop_keys = array();
	foreach ($rx_parts[3]['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	pg_assert_same(_test_rx_shop_keys(), $shop_keys, 'the shop pages');
	$shop_keys[] = 'address_book';
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
		_test_rx_asm_nodes($tree, $nodes);
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

function test_pharmacy_assembly_template_search_and_look()
{
	$tpl = _test_rx_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_rx_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('rx-contact-details', $ids, true) && in_array('rx-inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(prx-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!is_array($item['tree'])) continue;
			$nodes = array();
			_test_rx_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bprx-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'prx- classes and rules match');
	pg_assert_same(array('Lexend', 'Source Sans 3'), array_column(json_decode($tpl['assets']['fonts'], true), 'family'), 'two fonts');
	// A quiet sector: no interactions on any node.
	pg_assert_false(strpos(json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared'])), '"_fx"') !== false, 'no motion');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a customer, a visitor and the staff, and a request's messages are as
// private as the request.
function test_pharmacy_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'prescription_messages' => array('page' => 'prescription_view', 'view' => 'prescription_detail') + $request,
		'question_messages' => array('page' => 'ask_view', 'view' => 'question_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-pharmacy');
	_tpl_threads_check_no_fixed_block('pinegrap-pharmacy', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
		'prescription_messages' => 'comments', 'question_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'prescription_view' => lang('Message'), 'ask_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-pharmacy', $key, $t);
		_tpl_threads_check_draws('pinegrap-pharmacy', $key, $t);
	}
	foreach (array('prescription_detail', 'question_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the customer\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-pharmacy', 'email_comment', 'email_reply',
		array('blog_post', 'prescription_view', 'ask_view', 'conversation'));
}

// The sample pharmacists are parts.php's $pharmacists; the assembly keeps
// every part's return under another name, so the collection still seeds
// every pharmacist, and the catalog keeps its products.
function test_pharmacy_assembly_samples()
{
	$tpl = _test_rx_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$rx_shop = include $dir . '/shop.php';
	pg_assert_true(count($pharmacists) >= 4, 'sample pharmacists in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($pharmacists as $key => $p) {
		pg_assert_contains(json_encode($p['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	pg_assert_same($rx_shop['catalog'], $tpl['catalog'], 'the shop part\'s catalog');
}

// The pharmacy's Google reviews sit under the sample customer stories on
// the home page, with the layout Google asks for.
function test_pharmacy_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-pharmacy', 'google_reviews', 'home', lang('Customer Stories'));
	$tpl = _tpl_threads_tpl('pinegrap-pharmacy');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_team', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

// The main file's literals have their Turkish; a pharmacy reads "eczane".
function test_pharmacy_assembly_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
		elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
		if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
		if (preg_match('/\bon[ -]duty\b/i', $key)) pg_assert_true(mb_stripos($tr, 'nöbet', 0, 'UTF-8') !== false, "'$key' says nöbet");
		if (preg_match('/\bVaccinations?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'aşı', 0, 'UTF-8') !== false, "'$key' says aşı");
	}
}

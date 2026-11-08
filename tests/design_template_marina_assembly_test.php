<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Marina design template
 * (includes/design_templates/pinegrap-marina.php): it is offered and
 * its parts folder is not; its settings (order, icon, look, palette,
 * folders, the newsletter's contact group, no catalog); every part's pages
 * in order and every widget once; every placed widget and shared part is
 * defined and every placeholder names a page or folder; the contact page's
 * search sections; the pmn- rules match the classes in use; the comment
 * threads keep their settings and draw; the sample charter boats travel
 * with the template and every kind of berth has its page; the Google
 * reviews sit on the home page.
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

function _test_mn_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_mn_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mn_asm_nodes($child, $out);
	}
}

function test_marina_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-marina']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['charters']) && !isset($all['requests']), 'the parts folder is not read as a template');
	$tpl = _test_mn_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-life-preserver', $tpl['icon']);
	pg_assert_same(105, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the harbour office takes payment on arrival');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('minimal', $tpl['look']);
	pg_assert_same('midnight', $tpl['palette'], 'the palette the e-mails\' navy is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template's order.
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-marina' && isset($other['order'])) pg_assert_true($other['order'] !== 105, "$id: order 105 is the Marina template's");
	}
}

function test_marina_assembly_template_pages_and_widgets()
{
	$tpl = _test_mn_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/charters.php',
		include $dir . '/requests.php',
		include $dir . '/events.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'berth_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_mn_asm_nodes($tree, $nodes);
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

function test_marina_assembly_template_search_and_look()
{
	$tpl = _test_mn_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_mn_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pmn-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_mn_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpmn-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pmn- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Josefin Sans', 'Nunito Sans'), array_column($fonts, 'family'), 'Josefin Sans headings, Nunito Sans body');
	pg_assert_false((bool)preg_match('/#[0-9a-f]{3,6}\b/i', $css), 'colours are tokens only');
}

function _test_mn_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'request_messages' => array('page' => 'request_view', 'view' => 'request_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'entry_messages' => array('page' => 'entry_view', 'view' => 'entry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_marina_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-marina');
	_tpl_threads_check_no_fixed_block('pinegrap-marina', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'enquiry_messages' => 'comments', 'entry_messages' => 'comments',
		'post_comments' => 'comments', 'request_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'request_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'entry_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_mn_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-marina', $key, $t);
		_tpl_threads_check_draws('pinegrap-marina', $key, $t);
	}
	foreach (array('request_detail', 'enquiry_detail', 'entry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the skipper\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-marina', 'email_comment', 'email_reply',
		array('blog_post', 'request_view', 'enquiry_view', 'entry_view', 'conversation'));
}

// The samples are parts.php's $boats and $kinds; the assembly keeps every
// part's return under another name, so the collection still seeds every
// charter boat and every kind of berth has its page.
function test_marina_assembly_samples()
{
	$tpl = _test_mn_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	pg_assert_true(count($boats) >= 6, 'sample charter boats in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($boats as $key => $b) {
		pg_assert_contains(json_encode($b['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	foreach (array_keys($kinds) as $kind) pg_assert_true(in_array('berth_' . $kind, $keys, true), $kind . ': a page of its own');
}

// The harbour's Google reviews sit beside the sample testimonials on the home
// page, with the layout Google asks for.
function test_marina_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-marina', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-marina');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_charters"') !== false && strpos($home, '"templateWidget":"home_events"') !== false
		&& strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the charter boats, the events and the News');
}

// The home, the berths and the arriving page place the Marina blocks: every
// pg-marina-* class's rule ships, the interactions are kept as written,
// something rises on each, and the stock bands left them.
function test_marina_assembly_pages_on_marina_blocks()
{
	$tpl = _test_mn_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$wanted = array('home' => array('mn-home-berths', 'mn-home-arriving'), 'berths' => array('mn-berths-pontoons'), 'arriving' => array('mn-arriving', 'mn-arriving-pontoons'));
	$seen = array();
	foreach ($tpl['pages'] as $p) {
		if (!isset($wanted[$p['key']]) && strpos($p['key'], 'berth_') !== 0) continue;
		if ($p['key'] === 'berth_request') continue;
		$seen[] = $p['key'];
		$nodes = array();
		_test_mn_asm_nodes($p['tree'], $nodes);
		$classes = '';
		$labels = array();
		$ids = array();
		$effects = 0;
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if (isset($n['props']['id'])) $ids[] = $n['props']['id'];
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $p['key'] . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-marina-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $p['key'] . ': the rule of ' . $class . ' ships');
		pg_assert_true($effects >= 1, $p['key'] . ': something rises in');
		if (!isset($wanted[$p['key']])) continue;
		foreach ($wanted[$p['key']] as $id) pg_assert_true(in_array($id, $ids, true), $p['key'] . ': places ' . $id);
		pg_assert_contains('pg-marina-figures', $classes, $p['key'] . ': on the marina blocks');
		pg_assert_false((bool)preg_match('/(?<![\w-])(pmn-stat|accordion)(?![\w-])/', $classes), $p['key'] . ': no figures row or accordion');
		foreach (array('Write to Us', 'Figures', 'Why Crews Come Back') as $band) pg_assert_false(in_array($band, $labels, true), $p['key'] . ': no ' . $band . ' band');
	}
	pg_assert_same(8, count($seen), 'home, berths, five kinds of berth, arriving');

	$home = null;
	foreach ($tpl['pages'] as $p) if ($p['key'] === 'home') $home = $p['tree'];
	$json = json_encode($home, JSON_UNESCAPED_UNICODE);
	pg_assert_contains('{"name":"data-bs-theme","value":"dark"}', $json, 'the arrival is a dark band');
	pg_assert_contains('"trigger":"load"', $json, 'the hero rises in on load');
	pg_assert_same(4, substr_count($json, 'pg-marina-pontoon flex-shrink-0'), 'four pontoons');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Theatre design template
 * (includes/design_templates/pinegrap-theatre.php): it is offered as a
 * template that opens without the shop and carries the tickets', the
 * friends' and the gift vouchers' catalog, every part's pages in order and
 * each widget once, only the shop pages and the address book need the
 * shop, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the pth- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, the sample
 * productions, performances, spaces and products survive the assembly,
 * and the Google reviews sit under the sample testimonials.
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
// The page keys of the finished template (_test_th_page_keys()).
require_once __DIR__ . '/design_template_theatre_test.php';

function _test_th_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_th_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_th_asm_nodes($child, $out);
	}
}

function test_theatre_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-theatre']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['productions']) && !isset($all['hire']), 'the parts folder is not read as a template');
	$tpl = _test_th_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-ticket-perforated', $tpl['icon']);
	pg_assert_same(105, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(array('tickets', 'friends', 'vouchers'), array_keys($tpl['catalog']['groups']), 'the tickets\', the friends\' and the vouchers\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('luxe', $tpl['look']);
	pg_assert_same('crimson', $tpl['palette'], 'the palette the e-mails\' crimson is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_theatre_assembly_template_pages_and_widgets()
{
	$tpl = _test_th_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'productions', 'performances', 'hire', 'learning', 'shop', 'standard', 'account') as $part_name) {
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
	$shared_keys = _test_th_page_keys();
	$sorted = $keys;
	sort($shared_keys);
	sort($sorted);
	pg_assert_same($shared_keys, $sorted, 'the page list the part tests share');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'box_office_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_th_asm_nodes($tree, $nodes);
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

function test_theatre_assembly_template_search_and_look()
{
	$tpl = _test_th_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_th_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pth-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_th_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpth-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pth- classes and rules match');
	pg_assert_contains('prefers-reduced-motion:reduce', $css, 'the hover lift stops under reduced motion');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Playfair Display', 'Karla'), array_column($fonts, 'family'), 'two fonts');
}

function _test_th_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'production_comments' => array('page' => 'production', 'view' => 'production_performances', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'hire_messages' => array('page' => 'hire_view', 'view' => 'hire_detail') + $request,
		'school_group_messages' => array('page' => 'school_group_view', 'view' => 'school_group_detail') + $request,
		'youth_place_messages' => array('page' => 'youth_place_view', 'view' => 'youth_place_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_theatre_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-theatre');
	_tpl_threads_check_no_fixed_block('pinegrap-theatre', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'hire_messages' => 'comments',
		'post_comments' => 'comments', 'production_comments' => 'comments', 'school_group_messages' => 'comments',
		'youth_place_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'production' => lang('Comment'), 'hire_view' => lang('Message'),
		'school_group_view' => lang('Message'), 'youth_place_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	// The draw check needs two levels of answers; the audience's words on a
	// production take one, so they are checked for their settings only.
	foreach (_test_th_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-theatre', $key, $t);
		if ($key !== 'production_comments') _tpl_threads_check_draws('pinegrap-theatre', $key, $t);
	}
	foreach (array('hire_detail', 'school_group_detail', 'youth_place_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the visitor\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-theatre', 'email_comment', 'email_reply',
		array('blog_post', 'production', 'hire_view', 'school_group_view', 'youth_place_view', 'conversation'));
}

// The samples are parts.php's $productions, $performances, $spaces and the
// ticket bands and tiers; the assembly keeps every part's return under
// another name, so the collections still seed every sample and the catalog
// every ticket and tier.
function test_theatre_assembly_samples()
{
	$tpl = _test_th_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	pg_assert_true(count($productions) >= 4 && count($performances) >= 6 && count($spaces) >= 3, 'sample records in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($productions as $key => $p) {
		pg_assert_contains(json_encode($p['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($spaces as $key => $s) {
		pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($tickets) as $key) {
		pg_assert_contains('THEATRE-TICKET-' . strtoupper($key), $catalog, $key . ': its ticket is in the catalog');
	}
	foreach (array_keys($tiers) as $key) {
		pg_assert_contains('THEATRE-FRIEND-' . strtoupper($key), $catalog, $key . ': its tier is in the catalog');
	}
	// The performance dates are counted from today (ISO dates); no word
	// names a fixed year.
	pg_assert_true(!preg_match('/(?<![0-9-])20[2-3][0-9](?![0-9-])/', json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['catalog']), JSON_UNESCAPED_UNICODE)), 'no fixed year in the sample');
}

// The theatre's Google reviews sit under the sample testimonials on the
// home page, with the layout Google asks for.
function test_theatre_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-theatre', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-theatre');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_productions', 'home_performances', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

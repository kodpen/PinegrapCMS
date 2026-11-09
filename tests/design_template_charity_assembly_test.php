<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Charity design template
 * (includes/design_templates/pinegrap-charity.php): it is offered with the
 * Donate group of the catalog, carries every part's pages in order and
 * each widget once, every placed widget, shared part and placeholder names
 * something the template defines, only the shop pages need the shop, the
 * search anchors are unique, the pch- classes equal their rules, the
 * comment threads run on the Comments widget with the reply e-mail's card,
 * and the campaigns survive the assembly.
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

function _test_ch_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_ch_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ch_asm_nodes($child, $out);
	}
}

function test_charity_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-charity']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['donate']) && !isset($all['volunteer']), 'the parts folder is not read as a template');
	$tpl = _test_ch_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-heart', $tpl['icon']);
	pg_assert_same(81, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'reports'), array_keys($tpl['folders']));
	pg_assert_same('public', $tpl['folders']['reports']['access'], 'the reports are public');
	pg_assert_same('modern-soft', $tpl['look']);
	pg_assert_same('emerald', $tpl['palette'], 'the palette the e-mails\' green is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	pg_assert_same(array('donate'), array_keys($tpl['catalog']['groups']), 'the Donate group');
}

function test_charity_assembly_pages_and_widgets()
{
	$tpl = _test_ch_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$parts = array();
	foreach (array('pages_public', 'donate', 'campaigns', 'volunteer', 'events', 'standard', 'account') as $name) {
		$parts[$name] = include $dir . '/' . $name . '.php';
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
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'donate_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	pg_assert_same($parts['donate']['catalog'], $tpl['catalog'], 'the donate part\'s catalog');

	// The template's page list in the data test is the template's pages.
	$listed = _test_ch_asm_page_keys();
	sort($listed);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($listed, $sorted, 'the data test lists every page');

	$shop_keys = array('product', 'cart', 'checkout', 'order', 'email_order', 'address_book');
	foreach ($tpl['pages'] as $p) {
		pg_assert_same(in_array($p['key'], $shop_keys, true), isset($p['requires']), $p['key'] . ': needs the shop only if it is a shop page');
	}

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder, every
	// group placeholder a group of the catalog.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_ch_asm_nodes($tree, $nodes);
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
	pg_assert_contains('{{folder:reports}}', $json, 'a report\'s file goes to the reports folder');
	preg_match_all('/\{\{product_group(?:_path)?:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true($key === 'root' || isset($tpl['catalog']['groups'][$key]), 'placeholder names group ' . $key);
	preg_match_all('/\{\{contact_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['contact_groups'][$key]), 'placeholder names contact group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

// The data test's page list, read without running its tests.
function _test_ch_asm_page_keys()
{
	require_once(__DIR__ . '/design_template_charity_test.php');
	return _test_charity_page_keys();
}

function test_charity_assembly_search_and_look()
{
	$tpl = _test_ch_asm_tpl();
	$sections = 0;
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_ch_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		$sections += count($ids);
	}
	pg_assert_true($sections >= 20, 'the parts mark their search sections');

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pch-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_ch_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpch-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pch- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_ch_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'campaign_updates' => array('page' => 'campaign', 'view' => 'campaign_detail', 'reply' => false, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 10)),
		'pledge_messages' => array('page' => 'pledge_view', 'view' => 'pledge_detail') + $request,
		'volunteer_messages' => array('page' => 'volunteer_view', 'view' => 'volunteer_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_charity_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-charity');
	_tpl_threads_check_no_fixed_block('pinegrap-charity', array(
		'campaign_updates' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'pledge_messages' => 'comments', 'post_comments' => 'comments', 'volunteer_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'campaign' => lang('Update'), 'pledge_view' => lang('Message'),
		'volunteer_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'], $key . ': signed in');
	}
	pg_assert_false(_tpl_threads_page($tpl, 'campaign')['comments']['auto_publish'], 'a visitor\'s message on a campaign waits for the staff');
	// The shared drawing check reads a flat thread oldest first; a
	// campaign's updates come newest first, so they are checked for their
	// settings only.
	foreach (_test_ch_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-charity', $key, $t);
		if ($key !== 'campaign_updates') _tpl_threads_check_draws('pinegrap-charity', $key, $t);
	}
	foreach (array('pledge_detail', 'volunteer_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the sender\'s own');
	}
	pg_assert_same('public', $tpl['widgets']['campaign_detail']['config']['access_control'], 'a campaign\'s page is public');
	_tpl_threads_check_email_card('pinegrap-charity', 'email_comment', 'email_reply',
		array('blog_post', 'campaign', 'pledge_view', 'volunteer_view', 'conversation'));
}

// The figures are parts.php's $causes and $campaigns; the assembly keeps
// every part's return under another name, so the pages name every
// campaign and cause and the catalog carries a gift per campaign.
function test_charity_assembly_campaigns_kept()
{
	$tpl = _test_ch_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	pg_assert_same(6, count($campaigns), 'six campaigns in parts.php');
	pg_assert_same(5, count($causes), 'five causes in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($campaigns as $key => $c) {
		pg_assert_contains(json_encode($c['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
		pg_assert_contains($campaign_sku($key), $catalog, $key . ': its gift is in the catalog');
	}
	foreach ($causes as $key => $c) pg_assert_contains(json_encode($c['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	pg_assert_contains($general_sku, $catalog, 'the general gift is in the catalog');
}

// Every literal of the main file has its Turkish.
function test_charity_assembly_turkish_words()
{
	require_once(__DIR__ . '/helpers/language_map.php');
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 10, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "pinegrap-charity.php: Turkish for '$key'");
	}
}

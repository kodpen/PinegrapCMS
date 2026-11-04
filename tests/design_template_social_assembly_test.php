<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Social Community design template
 * (includes/design_templates/pinegrap-social.php): it is offered without
 * the shop, carries every part's pages in order and each widget once,
 * every placed widget, shared part, page and folder placeholder names
 * something the template defines, the members' photos and pictures go to
 * public folders of their own, the contact page's parts are search
 * sections, the psc- classes equal their rules, the comment threads run on
 * the Comments widget with the e-mail card, and the sample topics, members
 * and posts survive the assembly.
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

function _test_sc_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_sc_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sc_asm_nodes($child, $out);
	}
}

// The parts as the main file reads them, each return in its order.
function _test_sc_asm_parts()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	return array(
		include $dir . '/pages_public.php',
		include $dir . '/posts.php',
		include $dir . '/profiles.php',
		include $dir . '/reports.php',
		include $dir . '/standard.php',
		include $dir . '/account.php',
	);
}

function test_social_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-social']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['posts']) && !isset($all['profiles']), 'the parts folder is not read as a template');
	$tpl = _test_sc_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-camera', $tpl['icon']);
	pg_assert_same(78, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no shop of its own');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'posts', 'avatars'), array_keys($tpl['folders']));
	foreach (array('posts', 'avatars') as $key) {
		pg_assert_same('root', $tpl['folders'][$key]['parent'], $key . ': under the template\'s folder');
		pg_assert_same('public', $tpl['folders'][$key]['access'], $key . ': public files');
	}
	pg_assert_true(isset(pg_design_looks()[$tpl['look']]), 'a known look');
	pg_assert_true(isset(pg_design_palettes()[$tpl['palette']]), 'a known palette');
	pg_assert_same('#be185d', pg_design_palettes()[$tpl['palette']]['secondary'], 'the palette the e-mails\' pink is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_social_assembly_template_pages_and_widgets()
{
	$tpl = _test_sc_asm_tpl();
	$expected = array();
	$widget_count = 0;
	foreach (_test_sc_asm_parts() as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'member_bar', 'footer', 'join_band', 'staff_bar', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	foreach ($tpl['pages'] as $p) pg_assert_true(!isset($p['requires']), $p['key'] . ': stands without the shop');

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder, every
	// contact group placeholder a contact group.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	$shared = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_sc_asm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) $shared[$n['props']['templateShared']] = true;
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	foreach (array_keys($shared) as $key) pg_assert_true(isset($tpl['shared'][$key]), 'shared part ' . $key . ' is defined');
	foreach (array_keys($tpl['shared']) as $key) pg_assert_true(isset($shared[$key]), 'shared part ' . $key . ' is placed');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(in_array($key, $keys, true), 'placeholder names page ' . $key);
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	pg_assert_true(in_array('posts', $m[1], true) && in_array('avatars', $m[1], true), 'the photos and the pictures have their folders');
	preg_match_all('/\{\{contact_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['contact_groups'][$key]), 'placeholder names contact group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_social_assembly_template_search_and_look()
{
	$tpl = _test_sc_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_sc_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(psc-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_sc_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpsc-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'psc- classes and rules match');
	pg_assert_true(strpos($css, 'prefers-reduced-motion') !== false, 'motion stops when the visitor asks');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, sits
// right under its record view and is drawn for a member, a visitor and
// the staff; a conversation and a report are as private as their record.
function test_social_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-social');
	_tpl_threads_check_no_fixed_block('pinegrap-social', array(
		'conversation_replies' => 'comments', 'email_comment_card' => 'comments', 'news_comments' => 'comments',
		'post_comments' => 'comments', 'report_notes' => 'comments',
	));
	$open = array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20);
	$flat = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'post', 'view' => 'post_view', 'reply' => true, 'watch' => true, 'options' => $open),
		'news_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true, 'options' => $open),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $flat,
	);
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-social', $key, $t);
		_tpl_threads_check_draws('pinegrap-social', $key, $t);
	}
	$notes = $tpl['widgets']['report_notes'];
	pg_assert_same('staff_report', $notes['page'], 'the moderators\' notes are on the staff report page');
	pg_assert_same($flat['options'], pg_comments_widget_options($notes['config']), 'the notes are flat');
	$order = _tpl_threads_order(_tpl_threads_page($tpl, 'staff_report')['tree']);
	$pos = array_search('report_notes', $order, true);
	pg_assert_true($pos !== false && $pos > 0 && $order[$pos - 1] === 'staff_report_view', 'the notes right under the report');
	pg_assert_true(empty(_tpl_threads_page($tpl, 'staff_report')['comments']['email_page']), 'the notes are e-mailed to nobody');
	pg_assert_same('public', $tpl['widgets']['post_view']['config']['access_control'], 'a post is public');
	foreach (array('report_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-social', 'email_comment_card', 'email_comment', array('post'));
}

// The samples are parts.php's $topics, $members and $social_posts; the
// assembly keeps every part's return under another name, so the pages
// name every topic, member and post.
function test_social_assembly_samples_kept()
{
	$tpl = _test_sc_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	pg_assert_same(6, count($topics), 'six topics in parts.php');
	pg_assert_same(8, count($members), 'eight members in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($topics as $key => $t) {
		pg_assert_contains(json_encode($t['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the topic is in the template');
	}
	foreach ($members as $key => $m) {
		pg_assert_contains(json_encode($m['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the member is in the template');
		pg_assert_same(2, count($social_posts[$key]), $key . ': two posts');
		foreach ($social_posts[$key] as $i => $p) {
			pg_assert_contains(json_encode($p['caption'], JSON_UNESCAPED_UNICODE), $json, $key . ': post ' . ($i + 1) . ' is in the template');
		}
	}
}

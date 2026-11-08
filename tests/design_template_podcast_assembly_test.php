<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Podcast Network design template
 * (includes/design_templates/pinegrap-podcast.php): it is offered without
 * a shop requirement and with its catalog; every part's pages come in
 * order, only the shop pages and the address book need the shop; every
 * widget is defined once and placed; placeholders name pages, folders and
 * contact groups; the recordings' folder and the supporters' membership
 * folder; the contact page's search sections; the ppd- classes and their
 * rules match; the comment threads and the reply e-mail card; the sample
 * podcasts, episodes and tiers kept.
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
require_once(__DIR__ . '/design_template_podcast_test.php');

function _test_ppd_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_ppd_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ppd_asm_nodes($child, $out);
	}
}

function test_podcast_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-podcast']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['podcasts']) && !isset($all['supporters']), 'the parts folder is not read as a template');
	$tpl = _test_ppd_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-mic', $tpl['icon']);
	pg_assert_same(141, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the supporters\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'episodes', 'supporters'), array_keys($tpl['folders']));
	pg_assert_same('private', $tpl['folders']['private']['access'], 'attachments stay private');
	pg_assert_same('public', $tpl['folders']['episodes']['access'], 'the recordings play for everyone');
	pg_assert_same('membership', $tpl['folders']['supporters']['access'], 'the supporters\' area opens to a member');
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('sunset', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template takes the same place in the installer's list.
	foreach ($all as $id => $other) {
		if ($id === 'pinegrap-podcast' || !isset($other['order'])) continue;
		pg_assert_true($other['order'] !== $tpl['order'], $id . ' has another order');
	}
}

function test_podcast_assembly_template_pages_and_widgets()
{
	$tpl = _test_ppd_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/podcasts.php',
		include $dir . '/episodes.php',
		include $dir . '/sponsors.php',
		include $dir . '/supporters.php',
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
	$all = _test_ppd_page_keys();
	$sorted = $keys;
	sort($sorted);
	sort($all);
	pg_assert_same($all, $sorted, 'the finished template\'s pages');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'subscribe_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array();
	foreach ($tpl['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	$expected_shop = array_merge(array('address_book'), _test_ppd_shop_keys());
	sort($expected_shop);
	sort($shop_keys);
	pg_assert_same($expected_shop, $shop_keys, 'the shop pages');

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_ppd_asm_nodes($tree, $nodes);
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
	pg_assert_true(in_array('episodes', $m[1], true), 'the recordings are uploaded into the episodes folder');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	preg_match_all('/\{\{contact_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['contact_groups'][$key]), 'placeholder names contact group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
	foreach (array('supporters_home', 'bonus_outtakes', 'bonus_askus', 'bonus_live') as $key) {
		pg_assert_same('supporters', _tpl_threads_page($tpl, $key)['folder'], $key . ': behind the membership');
	}
}

// No page but the shop's own links a shop page, and no shared part.
function test_podcast_assembly_shop_links()
{
	$tpl = _test_ppd_asm_tpl();
	$shop = array_merge(array('address_book'), _test_ppd_shop_keys());
	foreach ($tpl['pages'] as $p) {
		if (in_array($p['key'], $shop, true)) continue;
		$json = json_encode($p['tree']);
		foreach (_test_ppd_shop_keys() as $key) {
			pg_assert_true(strpos($json, '{{page:' . $key . '}}') === false, $p['key'] . ': no link to ' . $key . ' without the shop');
		}
	}
	foreach ($tpl['shared'] as $key => $s) {
		foreach (_test_ppd_shop_keys() as $page_key) {
			pg_assert_true(strpos(json_encode($s['tree']), '{{page:' . $page_key . '}}') === false, $key . ': no link to ' . $page_key);
		}
	}
}

function test_podcast_assembly_template_search_and_look()
{
	$tpl = _test_ppd_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_ppd_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(ppd-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree'])) continue;
			$nodes = array();
			_test_ppd_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bppd-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'ppd- classes and rules match');
	pg_assert_true(strpos($css, 'prefers-reduced-motion') !== false, 'the cards keep still when motion is reduced');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Space Grotesk', 'DM Sans'), array_column($fonts, 'family'), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_podcast_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'episode_comments' => array('page' => 'episode', 'view' => 'episode_detail', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'newest', 'featured' => 'none', 'layout' => 'threaded', 'max_depth' => 1, 'per_page' => 10)),
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'sponsor_enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'sponsor_enquiry_detail') + $request,
		'guest_pitch_messages' => array('page' => 'pitch_view', 'view' => 'guest_pitch_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-podcast');
	_tpl_threads_check_no_fixed_block('pinegrap-podcast', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'episode_comments' => 'comments',
		'guest_pitch_messages' => 'comments', 'post_comments' => 'comments', 'sponsor_enquiry_messages' => 'comments',
	));
	$labels = array('episode' => lang('Comment'), 'blog_post' => lang('Comment'), 'enquiry_view' => lang('Message'),
		'pitch_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-podcast', $key, $t);
		_tpl_threads_check_draws('pinegrap-podcast', $key, $t);
	}
	foreach (array('sponsor_enquiry_detail', 'guest_pitch_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-podcast', 'email_comment', 'email_reply',
		array('episode', 'blog_post', 'enquiry_view', 'pitch_view', 'conversation'));
}

// The sample podcasts and episodes are parts.php's $podcasts and
// $episodes, the shop's products its tiers and gifts; the assembly keeps
// every part's return under another name, so the collections still seed
// every record and the catalog every product.
function test_podcast_assembly_samples()
{
	$tpl = _test_ppd_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	pg_assert_true(count($podcasts) >= 3, 'sample podcasts in parts.php');
	pg_assert_true(count($episodes) >= 3, 'sample episodes in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($podcasts as $key => $p) {
		pg_assert_contains(json_encode($p['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	foreach ($episodes as $key => $e) {
		pg_assert_contains(json_encode($e['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach (array_merge($tiers, $gift_memberships) as $key => $t) {
		pg_assert_contains(json_encode($t['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The home page places the latest episodes, the podcasts and the News.
function test_podcast_assembly_home()
{
	$tpl = _test_ppd_asm_tpl();
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('home_episodes', 'home_podcasts', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

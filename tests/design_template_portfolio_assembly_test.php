<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Web Designer Portfolio design template
 * (includes/design_templates/pinegrap-portfolio.php): it is offered as a
 * template without a catalog, carries every part's pages in order and each
 * widget once, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the pdp- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, the sample case
 * studies survive the assembly, and the home page's Google Reviews come
 * after the sample words.
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

function _test_pd_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_pd_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pd_asm_tpl_nodes($child, $out);
	}
}

function test_portfolio_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-portfolio']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['work']) && !isset($all['briefs']), 'the parts folder is not read as a template');
	$tpl = _test_pd_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-vector-pen', $tpl['icon']);
	pg_assert_same(72, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: a website is quoted, not bought through a cart');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('graphite', $tpl['palette'], 'the palette the e-mails\' greys are made for');
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_portfolio_assembly_template_pages_and_widgets()
{
	$tpl = _test_pd_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		array('pages' => include $dir . '/pages_public.php', 'widgets' => array()),
		include $dir . '/work.php',
		include $dir . '/briefs.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'brief_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	foreach ($tpl['pages'] as $p) pg_assert_true(!isset($p['requires']) || $p['key'] === 'address_book', $p['key'] . ': opens without the shop');

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_pd_asm_tpl_nodes($tree, $nodes);
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

function test_portfolio_assembly_template_search_and_look()
{
	$tpl = _test_pd_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_pd_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pdp-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_pd_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpdp-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pdp- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_pd_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'brief_messages' => array('page' => 'brief_view', 'view' => 'brief_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the brief.
function test_portfolio_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-portfolio');
	_tpl_threads_check_no_fixed_block('pinegrap-portfolio', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
		'brief_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'brief_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_pd_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-portfolio', $key, $t);
		_tpl_threads_check_draws('pinegrap-portfolio', $key, $t);
	}
	foreach (array('brief_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the client\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-portfolio', 'email_comment', 'email_reply', array('blog_post', 'brief_view', 'conversation'));
}

// The sample case studies are parts.php's $projects; the assembly keeps
// every part's return under another name, so the Projects collection still
// seeds all six.
function test_portfolio_assembly_sample_projects()
{
	$tpl = _test_pd_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	pg_assert_same(6, count($projects), 'six sample case studies in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($projects as $key => $p) {
		pg_assert_contains(json_encode($p['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
}

function test_portfolio_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-portfolio', 'google_reviews', 'home', lang('Client Quotes'));
	$tpl = _tpl_threads_tpl('pinegrap-portfolio');
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio/parts.php';
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	$sample = strpos($home, json_encode($testimonials[0]['quote'], JSON_UNESCAPED_UNICODE));
	$widget = strpos($home, '"templateWidget":"google_reviews"');
	pg_assert_true($sample !== false && $widget !== false && $sample < $widget, 'the sample words stay, the widget after them');
}

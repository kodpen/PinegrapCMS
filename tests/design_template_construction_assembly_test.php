<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Construction Firm design template
 * (includes/design_templates/pinegrap-construction.php): it is offered as
 * a template that opens without the shop and has no catalog, carries every
 * part's pages in order and each widget once, only the address book needs
 * the shop, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the pcn- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, the sample projects
 * and job openings survive the assembly, and the Google reviews sit under
 * the sample testimonials.
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

function _test_cn_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_cn_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cn_asm_tpl_nodes($child, $out);
	}
}

function test_construction_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-construction']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['projects']) && !isset($all['quotes']), 'the parts folder is not read as a template');
	$tpl = _test_cn_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-bricks', $tpl['icon']);
	pg_assert_same(89, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the firm sells nothing online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('amber', $tpl['palette'], 'the palette the e-mails\' amber is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_construction_assembly_template_pages_and_widgets()
{
	$tpl = _test_cn_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/projects.php',
		include $dir . '/quotes.php',
		include $dir . '/careers.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'quote_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the address book needs the shop.
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': needs the shop only if it is the address book');
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
		_test_cn_asm_tpl_nodes($tree, $nodes);
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

function test_construction_assembly_template_search_and_look()
{
	$tpl = _test_cn_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_cn_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pcn-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_cn_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpcn-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pcn- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_cn_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'quote_messages' => array('page' => 'quote_view', 'view' => 'quote_detail') + $request,
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_construction_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-construction');
	_tpl_threads_check_no_fixed_block('pinegrap-construction', array(
		'application_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'post_comments' => 'comments', 'quote_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'quote_view' => lang('Message'), 'application_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_cn_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-construction', $key, $t);
		_tpl_threads_check_draws('pinegrap-construction', $key, $t);
	}
	foreach (array('quote_detail', 'application_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the client\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-construction', 'email_comment', 'email_reply',
		array('blog_post', 'quote_view', 'application_view', 'conversation'));
}

// The samples are parts.php's $projects and $jobs; the assembly keeps every
// part's return under another name, so the collections still seed every
// project and job opening.
function test_construction_assembly_samples()
{
	$tpl = _test_cn_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction';
	include $dir . '/parts.php';
	pg_assert_true(count($projects) >= 6, 'sample projects in parts.php');
	pg_assert_true(count($jobs) >= 3, 'sample job openings in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($projects as $key => $p) {
		pg_assert_contains(json_encode($p['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($jobs as $key => $j) {
		pg_assert_contains(json_encode($j['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': its title is in the template');
	}
}

// The firm's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_construction_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-construction', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-construction');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_projects"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the projects and the news');
}

// Home, the projects page and the safety page place the Construction
// blocks: every pg-cn- class's rule ships in the User Styles, the
// interactions are kept as written and something moves on each; one h1 on
// each; the home and the projects page open on the site board hero (the
// home asks for a quote and has no figures band), show a project on site
// with its stages, and the safety page puts up the notice for the street.
function test_construction_assembly_pages_on_construction_blocks()
{
	$tpl = _test_cn_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'projects', 'safety'), true)) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'     => array('pg-cn-board', 'pg-cn-stripe', 'pg-cn-stages', 'pg-cn-dot'),
		'projects' => array('pg-cn-board', 'pg-cn-stripe', 'pg-cn-stages', 'pg-cn-dot'),
		'safety'   => array('pg-cn-notice', 'pg-cn-stripe', 'pg-cn-hours'),
	);
	pg_assert_same(3, count($trees), 'home, projects, safety');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_cn_asm_tpl_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-cn-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		if ($key === 'home') pg_assert_false(in_array('Figures', $labels, true), 'home: no figures band');
	}

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the hero comes in on load');
	pg_assert_contains('{{page:quote}}', $home, 'the hero asks for a quote');
	pg_assert_contains('"role"', $home, 'the work done is a progress bar');
	foreach (array('home', 'projects') as $key) {
		$json = json_encode($trees[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		pg_assert_same(5, substr_count($json, 'pg-cn-dot'), $key . ': five stages');
		pg_assert_same(1, substr_count($json, '"cssClass":"pg-cn-now'), $key . ': one stage on site now');
		pg_assert_same(4, substr_count($json, '"tag":"dt"'), $key . ': four board facts');
	}
	$safety = json_encode($trees['safety'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_same(4, substr_count($safety, '"tag":"dt"'), 'four notice facts');
	pg_assert_contains('tel:', $safety, 'the notice calls the firm');
}

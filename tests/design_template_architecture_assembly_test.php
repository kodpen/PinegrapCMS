<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Architecture & Interiors Studio design template
 * (includes/design_templates/pinegrap-architecture.php): it is offered and
 * its parts folder is not; its settings (order, icon, look, palette,
 * folders, the newsletter's contact group, no catalog); every part's pages
 * in order and every widget once; every placed widget and shared part is
 * defined and every placeholder names a page or folder; the contact page's
 * search sections; the pai- rules match the classes in use; the comment
 * threads keep their settings and draw; the sample projects and job
 * openings travel with the template; the Google reviews sit on the home
 * page.
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

function _test_ai_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_ai_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ai_asm_nodes($child, $out);
	}
}

function test_architecture_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-architecture']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['projects']) && !isset($all['enquiries']), 'the parts folder is not read as a template');
	$tpl = _test_ai_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-rulers', $tpl['icon']);
	pg_assert_same(97, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the studio sells nothing online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('minimal', $tpl['look']);
	pg_assert_same('graphite', $tpl['palette'], 'the palette the e-mails\' graphite is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template's order.
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-architecture' && isset($other['order'])) pg_assert_true($other['order'] !== 97, "$id: order 97 is the Architecture template's");
	}
}

function test_architecture_assembly_template_pages_and_widgets()
{
	$tpl = _test_ai_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/projects.php',
		include $dir . '/enquiries.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'enquiry_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_ai_asm_nodes($tree, $nodes);
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

function test_architecture_assembly_template_search_and_look()
{
	$tpl = _test_ai_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_ai_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pai-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			if (!isset($item['tree']) || !is_array($item['tree'])) continue;
			$nodes = array();
			_test_ai_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpai-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pai- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Jost', 'Karla'), array_column($fonts, 'family'), 'Jost headings, Karla body');
	pg_assert_false((bool)preg_match('/#[0-9a-f]{3,6}\b/i', $css), 'colours are tokens only');
}

function _test_ai_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'consultation_messages' => array('page' => 'consultation_view', 'view' => 'consultation_detail') + $request,
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_architecture_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-architecture');
	_tpl_threads_check_no_fixed_block('pinegrap-architecture', array(
		'application_messages' => 'comments', 'consultation_messages' => 'comments', 'conversation_replies' => 'comments',
		'email_comment' => 'comments', 'enquiry_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'enquiry_view' => lang('Message'), 'consultation_view' => lang('Message'),
		'application_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_ai_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-architecture', $key, $t);
		_tpl_threads_check_draws('pinegrap-architecture', $key, $t);
	}
	foreach (array('enquiry_detail', 'consultation_detail', 'application_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the client\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-architecture', 'email_comment', 'email_reply',
		array('blog_post', 'enquiry_view', 'consultation_view', 'application_view', 'conversation'));
}

// The samples are parts.php's $projects and $jobs; the assembly keeps every
// part's return under another name, so the collections still seed every
// project and job opening.
function test_architecture_assembly_samples()
{
	$tpl = _test_ai_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture';
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

// The studio's Google reviews sit under the sample testimonials on the
// home page, with the layout Google asks for.
function test_architecture_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-architecture', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-architecture');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_projects"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the projects and the Journal');
}

// Home, projects and the project page place the Architecture blocks: every
// pg-arch- class's rule ships in the User Styles, the interactions are kept
// as written and something rises on each; the home has one h1 in the plate
// hero, a plate captioned with its project and a link to it, the material
// board as a search section, and no figures or principles band.
function test_architecture_assembly_pages_on_architecture_blocks()
{
	$tpl = _test_ai_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$trees = array();
	foreach ($tpl['pages'] as $p) if (in_array($p['key'], array('home', 'projects'), true)) $trees[$p['key']] = $p['tree'];
	$trees['project'] = $tpl['widgets']['project_detail']['tree'];
	$wanted = array('home' => array('pg-arch-plate', 'pg-arch-swatch'), 'projects' => array('pg-arch-plate'), 'project' => array('pg-arch-facts'));
	pg_assert_same(3, count($trees), 'home, projects, the project page');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_ai_asm_nodes($tree, $nodes);
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
		preg_match_all('/(?<![\w-])pg-arch-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($wanted[$key] as $class) pg_assert_contains($class, $classes, $key . ': places ' . $class);
		pg_assert_true($effects >= 2, $key . ': something moves');
		pg_assert_same(1, $h1, $key . ': one h1');
		foreach (array('Figures', 'Our Principles') as $band) pg_assert_false(in_array($band, $labels, true), $key . ': no ' . $band . ' band');
	}

	$home = json_encode($trees['home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"trigger":"load"', $home, 'the plate hero comes in on load');
	pg_assert_contains('"id":"ai-home-materials"', $home, 'the material board is a search section');
	pg_assert_same(6, substr_count($home, 'pg-arch-swatch '), 'six mounted samples');
	pg_assert_contains('{{page:projects}}?location=', $home, 'the plate links its project by its place');
	$projects = json_encode($trees['projects'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('{{page:projects}}?type=', $projects, 'the projects plate links its project by its type');
	pg_assert_contains('"templateWidget":"projects_list"', $projects, 'the list stays');
	$project = json_encode($trees['project'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_same(1, substr_count($project, '"src":"photo_1"'), 'the first photo once, in the sheet');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Film Studio design template
 * (includes/design_templates/pinegrap-filmstudio.php): it is offered as a
 * template that opens without the shop and has no catalog, carries every
 * part's pages in order (exactly the plan's pages) and each widget once,
 * only the address book needs the shop, every placed widget, shared part
 * and page placeholder names something the template defines, the contact
 * page's parts are search sections, the pfm- classes equal their rules,
 * the comment threads run on the Comments widget with the reply e-mail's
 * card, and the sample services, projects, spaces, crew and crew calls
 * survive the assembly.
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
require_once(__DIR__ . '/design_template_filmstudio_test.php');

function _test_fm_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_fm_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fm_asm_tpl_nodes($child, $out);
	}
}

function test_filmstudio_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-filmstudio']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['projects']) && !isset($all['enquiries']), 'the parts folder is not read as a template');
	$tpl = _test_fm_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-film', $tpl['icon']);
	pg_assert_same(127, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(empty($tpl['catalog']), 'no shop, no catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('midnight', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_filmstudio_assembly_template_pages_and_widgets()
{
	$tpl = _test_fm_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio';
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
	$plan = _test_fm_page_keys();
	sort($plan);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($plan, $sorted, 'exactly the plan\'s pages');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'enquiry_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// No shop: only the address book needs it.
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
		_test_fm_asm_tpl_nodes($tree, $nodes);
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

function test_filmstudio_assembly_template_search_and_look()
{
	$tpl = _test_fm_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_fm_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('studio-hire-note', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pfm-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_fm_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpfm-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pfm- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_fm_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'studio_hire_messages' => array('page' => 'studio_hire_view', 'view' => 'studio_hire_detail') + $request,
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_filmstudio_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-filmstudio');
	_tpl_threads_check_no_fixed_block('pinegrap-filmstudio', array(
		'application_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'enquiry_messages' => 'comments', 'post_comments' => 'comments', 'studio_hire_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'enquiry_view' => lang('Message'), 'studio_hire_view' => lang('Message'),
		'application_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_fm_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-filmstudio', $key, $t);
		_tpl_threads_check_draws('pinegrap-filmstudio', $key, $t);
	}
	foreach (array('enquiry_detail', 'studio_hire_detail', 'application_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the owner\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-filmstudio', 'email_comment', 'email_reply',
		array('blog_post', 'enquiry_view', 'studio_hire_view', 'application_view', 'conversation'));
}

// The sample services, projects, spaces, crew and crew calls are
// parts.php's $services, $projects, $spaces, $crew and $jobs; the assembly
// keeps every part's return under another name, so the pages still show
// every service, space and crew member and the collections seed every
// project and crew call.
function test_filmstudio_assembly_samples()
{
	$tpl = _test_fm_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio';
	include $dir . '/parts.php';
	pg_assert_true(count($services) >= 6, 'sample services in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_UNICODE);
	foreach ($services as $key => $s) {
		pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the service is in the template');
	}
	foreach ($projects as $key => $p) {
		pg_assert_contains(json_encode($p['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the project is in the template');
	}
	foreach ($spaces as $key => $s) {
		pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the space is in the template');
	}
	foreach ($crew as $n => $c) {
		pg_assert_contains(json_encode($c['name'], JSON_UNESCAPED_UNICODE), $json, $n . ': the crew member is in the template');
	}
	foreach ($jobs as $key => $j) {
		pg_assert_contains(json_encode($j['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the crew call is in the template');
	}
}

// The studio's Google reviews sit under the clients' words on the home
// page, with the layout Google asks for.
function test_filmstudio_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-filmstudio', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-filmstudio');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	foreach (array('home_projects', 'home_posts') as $key) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $key . '"') !== false, 'the home places ' . $key);
	}
}

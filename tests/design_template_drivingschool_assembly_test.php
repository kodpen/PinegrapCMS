<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Driving School design template
 * (includes/design_templates/pinegrap-drivingschool.php): it is offered as
 * a template that opens without the shop and sells nothing, carries every
 * part's pages in order (exactly the plan's pages) and each widget once,
 * only the address book needs the shop, every placed widget, shared part
 * and page placeholder names something the template defines, the contact
 * page's parts are search sections, the pdr- classes equal their rules,
 * the comment threads run on the Comments widget with the reply e-mail's
 * card, and the sample courses and stories survive the assembly.
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
require_once(__DIR__ . '/design_template_drivingschool_test.php');

function _test_ds_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_ds_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ds_asm_tpl_nodes($child, $out);
	}
}

function test_drivingschool_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-drivingschool']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['courses']) && !isset($all['enrolments']), 'the parts folder is not read as a template');
	$tpl = _test_ds_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-car-front', $tpl['icon']);
	pg_assert_same(111, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(empty($tpl['catalog']), 'nothing is sold online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('amber', $tpl['palette'], 'the palette the e-mails\' amber is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_drivingschool_assembly_template_pages_and_widgets()
{
	$tpl = _test_ds_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/courses.php',
		include $dir . '/enrolments.php',
		include $dir . '/lessons.php',
		include $dir . '/stories.php',
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
	$plan = _test_ds_page_keys();
	sort($plan);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($plan, $sorted, 'exactly the plan\'s pages');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'enrol_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the address book needs the shop; no page is a shop page.
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': needs the shop only if it is the address book');
	}
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) pg_assert_false(in_array($key, $keys, true), "no shop page $key");

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_ds_asm_tpl_nodes($tree, $nodes);
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

function test_drivingschool_assembly_template_search_and_look()
{
	$tpl = _test_ds_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_ds_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pdr-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_ds_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpdr-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pdr- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_ds_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'enrolment_messages' => array('page' => 'enrolment_view', 'view' => 'enrolment_detail') + $request,
		'lesson_messages' => array('page' => 'lesson_request_view', 'view' => 'lesson_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_drivingschool_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-drivingschool');
	_tpl_threads_check_no_fixed_block('pinegrap-drivingschool', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'enrolment_messages' => 'comments',
		'lesson_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'enrolment_view' => lang('Message'), 'lesson_request_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_ds_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-drivingschool', $key, $t);
		_tpl_threads_check_draws('pinegrap-drivingschool', $key, $t);
	}
	foreach (array('enrolment_detail', 'lesson_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the learner\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-drivingschool', 'email_comment', 'email_reply',
		array('blog_post', 'enrolment_view', 'lesson_request_view', 'conversation'));
}

// The sample courses and stories are parts.php's $courses and $stories;
// the assembly keeps every part's return under another name, so the
// collections still seed every course and story.
function test_drivingschool_assembly_samples()
{
	$tpl = _test_ds_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	pg_assert_true(count($courses) >= 6, 'sample courses in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($courses as $key => $c) {
		pg_assert_contains(json_encode($c['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the course is in the template');
	}
	foreach ($stories as $key => $st) {
		pg_assert_contains(json_encode($st['quote'], JSON_UNESCAPED_UNICODE), $json, $key . ': the story is in the template');
	}
}

// The school's Google reviews sit under the pass stories on the home page,
// with the layout Google asks for.
function test_drivingschool_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-drivingschool', 'google_reviews', 'home', lang('Pass Stories'));
	$tpl = _tpl_threads_tpl('pinegrap-drivingschool');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	foreach (array('home_courses', 'home_stories', 'home_posts') as $key) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $key . '"') !== false, 'the home places ' . $key);
	}
}

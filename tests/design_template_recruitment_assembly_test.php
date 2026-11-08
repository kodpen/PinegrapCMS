<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Recruitment Agency design template
 * (includes/design_templates/pinegrap-recruitment.php): it is offered with
 * its look, folders and job alerts group; every part's pages come in
 * order with every widget once and placed, every placeholder names a page
 * or folder, only the address book needs the shop; the search anchors are
 * unique and the contact page is one; the prc- classes match their rules;
 * the comment threads keep their page settings and the applications' and
 * staff requests' messages are as private as the records; the Google
 * reviews sit under what people say; the sample jobs are seeded.
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

function _test_rc_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_rc_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rc_asm_nodes($child, $out);
	}
}

function test_recruitment_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-recruitment']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['jobs']) && !isset($all['applications']), 'the parts folder is not read as a template');
	$tpl = _test_rc_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-person-workspace', $tpl['icon']);
	pg_assert_same(135, $tpl['order']);
	foreach ($all as $id => $other) {
		if ($id !== 'pinegrap-recruitment' && isset($other['order'])) pg_assert_true($other['order'] !== 135, "order 135 is not also $id's");
	}
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(empty($tpl['catalog']), 'no catalog: the agency sells nothing online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('modern-soft', $tpl['look']);
	pg_assert_same('emerald', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_recruitment_assembly_template_pages_and_widgets()
{
	$tpl = _test_rc_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$parts = array(include $dir . '/jobs.php');
	include $dir . '/mail.php';
	$parts[] = include $dir . '/applications.php';
	$parts[] = include $dir . '/employers.php';
	$parts[] = include $dir . '/standard.php';
	$parts[] = include $dir . '/account.php';
	$expected = array();
	foreach ($public['pages'] as $p) $expected[] = $p['key'];
	$widget_count = count($public['widgets']);
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'call_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// The test helpers' list of the finished template's pages, the account
	// e-mails aside, is the template's.
	require_once __DIR__ . '/design_template_recruitment_test.php';
	pg_assert_same(array(), array_values(array_diff($keys, _test_rc_page_keys(), array('email_account_welcome', 'email_password_reset'))), 'no page left out of the helpers\' list');
	pg_assert_same(array(), array_values(array_diff(_test_rc_page_keys(), $keys)), 'every listed page is built');
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': only the address book needs the shop');
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
		_test_rc_asm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	foreach ($tpl['widgets'] as $key => $w) pg_assert_true($w['page'] === '' || in_array($w['page'], $keys, true), "widget $key: its page exists");
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

function test_recruitment_assembly_template_search_and_look()
{
	$tpl = _test_rc_asm_tpl();
	$searchable_pages = array();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_rc_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($ids) $searchable_pages[] = $p['key'];
	}
	foreach (array('contact', 'home', 'sectors', 'sector_technology', 'cities', 'candidates', 'employers', 'about', 'team', 'faq', 'jobs', 'privacy') as $key) {
		pg_assert_true(in_array($key, $searchable_pages, true), "$key: a search section");
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(prc-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_rc_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bprc-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'prc- classes and rules match');
	$fonts = json_decode($tpl['assets']['fonts'], true);
	pg_assert_same(array('Plus Jakarta Sans', 'Inter'), array_column($fonts, 'family'), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a record's messages are as
// private as the record.
function test_recruitment_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'enquiry_messages' => array('page' => 'enquiry_view', 'view' => 'enquiry_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-recruitment');
	_tpl_threads_check_no_fixed_block('pinegrap-recruitment', array(
		'application_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'enquiry_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'application_view' => lang('Message'), 'enquiry_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-recruitment', $key, $t);
		_tpl_threads_check_draws('pinegrap-recruitment', $key, $t);
	}
	foreach (array('application_detail', 'enquiry_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the sender\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-recruitment', 'email_comment', 'email_reply',
		array('blog_post', 'application_view', 'enquiry_view', 'conversation'));
}

// The sample jobs are parts.php's $jobs; the assembly keeps every part's
// return under another name, so the Jobs collection still seeds every job.
function test_recruitment_assembly_samples()
{
	$tpl = _test_rc_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	pg_assert_true(count($jobs) >= 4, 'sample jobs in parts.php');
	$forms = array();
	foreach ($tpl['pages'] as $p) if (isset($p['form'])) $forms[$p['key']] = $p['form'];
	pg_assert_same(count($jobs), count($forms['job_new']['sample_records']), 'every sample job is seeded');
	pg_assert_true(count($forms['blog_new']['sample_records']) >= 4, 'the Career Advice guides are seeded');
}

// The agency's Google reviews sit under what candidates and employers say
// on the home page, with the layout Google asks for.
function test_recruitment_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-recruitment', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-recruitment');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_jobs', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

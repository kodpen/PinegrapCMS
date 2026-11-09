<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Veterinary design template
 * (includes/design_templates/pinegrap-veterinary.php): it is offered as a
 * template that opens without the shop, with the health plans' catalog,
 * carries every part's pages in order (exactly the plan's pages) and each
 * widget once, only shop pages need the shop, every placed widget, shared
 * part and page placeholder names something the template defines, the
 * contact page's parts are search sections, the pvt- classes equal their
 * rules, the comment threads run on the Comments widget with the reply
 * e-mail's card, and the sample services, pets, vets and health plans
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
require_once(__DIR__ . '/design_template_veterinary_test.php');

function _test_vt_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_vt_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vt_asm_tpl_nodes($child, $out);
	}
}

function test_veterinary_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-veterinary']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['vets']) && !isset($all['appointments']), 'the parts folder is not read as a template');
	$tpl = _test_vt_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-heart-pulse', $tpl['icon']);
	pg_assert_same(125, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the health plans\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('playful', $tpl['look']);
	pg_assert_same('emerald', $tpl['palette']);
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_veterinary_assembly_template_pages_and_widgets()
{
	$tpl = _test_vt_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/vets.php',
		include $dir . '/appointments.php',
		include $dir . '/shop.php',
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
	$plan = _test_vt_page_keys();
	sort($plan);
	$sorted = $keys;
	sort($sorted);
	pg_assert_same($plan, $sorted, 'exactly the plan\'s pages');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'booking_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop.
	$shop_keys = array('address_book');
	foreach ($parts[3]['pages'] as $p) $shop_keys[] = $p['key'];
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
		_test_vt_asm_tpl_nodes($tree, $nodes);
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

function test_veterinary_assembly_template_search_and_look()
{
	$tpl = _test_vt_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_vt_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pvt-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_vt_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpvt-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pvt- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_vt_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'appointment_messages' => array('page' => 'appointment_view', 'view' => 'appointment_detail') + $request,
		'plan_join_messages' => array('page' => 'plan_join_view', 'view' => 'plan_join_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_veterinary_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-veterinary');
	_tpl_threads_check_no_fixed_block('pinegrap-veterinary', array(
		'appointment_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'plan_join_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'appointment_view' => lang('Message'), 'plan_join_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_vt_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-veterinary', $key, $t);
		_tpl_threads_check_draws('pinegrap-veterinary', $key, $t);
	}
	foreach (array('appointment_detail', 'plan_join_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the owner\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-veterinary', 'email_comment', 'email_reply',
		array('blog_post', 'appointment_view', 'plan_join_view', 'conversation'));
}

// The sample services, pets, vets and health plans are parts.php's
// $services, $species, $vets and $plans; the assembly keeps every part's
// return under another name, so the pages still show every service and
// pet, the Vets collection seeds every vet and the catalog every plan.
function test_veterinary_assembly_samples()
{
	$tpl = _test_vt_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary';
	include $dir . '/parts.php';
	pg_assert_true(count($services) >= 6, 'sample services in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_UNICODE);
	foreach ($services as $key => $s) {
		pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the service is in the template');
	}
	foreach ($species as $key => $s) {
		pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, $key . ': the pet is in the template');
	}
	foreach ($vets as $key => $v) {
		pg_assert_contains(json_encode($v['name'], JSON_UNESCAPED_UNICODE), $json, $key . ': the vet is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($plans as $key => $p) {
		pg_assert_contains(json_encode($p['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The practice's Google reviews sit under the owners' words on the home
// page, with the layout Google asks for.
function test_veterinary_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-veterinary', 'google_reviews', 'home', lang('Owner Stories'));
	$tpl = _tpl_threads_tpl('pinegrap-veterinary');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	foreach (array('home_vets', 'home_posts') as $key) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $key . '"') !== false, 'the home places ' . $key);
	}
}

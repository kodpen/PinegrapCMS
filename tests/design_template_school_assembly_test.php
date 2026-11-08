<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Private School design template
 * (includes/design_templates/pinegrap-school.php): it is offered as a
 * template that opens without the shop and has no catalog, carries every
 * part's pages in order and each widget once, only the address book needs
 * the shop, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the psh- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, the sample events
 * survive the assembly, and the Google reviews sit under the sample
 * testimonials.
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

function _test_sh_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_sh_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sh_asm_tpl_nodes($child, $out);
	}
}

function test_school_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-school']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['events']) && !isset($all['admissions']), 'the parts folder is not read as a template');
	$tpl = _test_sh_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-backpack', $tpl['icon']);
	pg_assert_same(93, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the school takes no payment online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('playful', $tpl['look']);
	pg_assert_same('sunset', $tpl['palette'], 'the palette the e-mails\' orange is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_school_assembly_template_pages_and_widgets()
{
	$tpl = _test_sh_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/events.php',
		include $dir . '/admissions.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'admissions_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_sh_asm_tpl_nodes($tree, $nodes);
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

function test_school_assembly_template_search_and_look()
{
	$tpl = _test_sh_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_sh_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(psh-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_sh_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpsh-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'psh- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_sh_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'application_messages' => array('page' => 'application_view', 'view' => 'application_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_school_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-school');
	_tpl_threads_check_no_fixed_block('pinegrap-school', array(
		'application_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'application_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_sh_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-school', $key, $t);
		_tpl_threads_check_draws('pinegrap-school', $key, $t);
	}
	foreach (array('application_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the parent\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-school', 'email_comment', 'email_reply',
		array('blog_post', 'application_view', 'conversation'));
}

// The samples are parts.php's $events; the assembly keeps every part's
// return under another name, so the collection still seeds every event.
function test_school_assembly_samples()
{
	$tpl = _test_sh_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
	include $dir . '/parts.php';
	pg_assert_true(count($events) >= 4, 'sample events in parts.php');
	pg_assert_true(count($programmes) === 5, 'five programmes in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach (array_keys($programmes) as $key) {
		pg_assert_contains('"key":"programme_' . $key . '"', $json, $key . ': its programme page');
	}
}

// The school's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_school_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-school', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-school');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_events"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the events and the news');
}

// Home, programmes and the day page place the School blocks: every
// pg-school- class's rule ships in the User Styles, the interactions are
// kept as written, something rises on each page and none keeps the stock
// figures, why-us or write-to-us bands; the home has one h1 and an age
// finder that opens every programme page.
function test_school_assembly_pages_on_school_blocks()
{
	$tpl = _test_sh_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$wanted = array('home' => 'pg-school-finder', 'programmes' => 'pg-school-ages', 'day' => 'pg-school-term');
	$seen = array();
	foreach ($tpl['pages'] as $p) {
		if (!isset($wanted[$p['key']])) continue;
		$seen[] = $p['key'];
		$nodes = array();
		_test_sh_asm_tpl_nodes($p['tree'], $nodes);
		$classes = '';
		$labels = array();
		$effects = 0;
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $p['key'] . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-school-[a-z0-9-]+/', $classes, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $p['key'] . ': the rule of ' . $class . ' ships');
		pg_assert_contains($wanted[$p['key']], $classes, $p['key'] . ': on the school blocks');
		pg_assert_true($effects >= 2, $p['key'] . ': something rises in');
		foreach (array('Write to Us', 'Figures', 'Why Families Choose Us') as $band) pg_assert_false(in_array($band, $labels, true), $p['key'] . ': no ' . $band . ' band');
	}
	pg_assert_same(3, count($seen), 'home, programmes, day');

	$home = null;
	foreach ($tpl['pages'] as $p) if ($p['key'] === 'home') $home = $p['tree'];
	$nodes = array();
	_test_sh_asm_tpl_nodes($home, $nodes);
	$h1 = 0;
	$finder = null;
	foreach ($nodes as $n) {
		if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
		if (isset($n['props']['cssClass']) && strpos($n['props']['cssClass'], 'pg-school-finder') !== false) $finder = $n;
	}
	pg_assert_same(1, $h1, 'one h1, in the school hero');
	pg_assert_true($finder !== null && $finder['props']['tag'] === 'nav', 'the age finder is a nav');
	$finder_json = json_encode($finder);
	foreach (array('nursery', 'kindergarten', 'primary', 'clubs', 'summer') as $key) {
		pg_assert_contains('{{page:programme_' . $key . '}}', $finder_json, 'the finder opens programme_' . $key);
	}
	$json = json_encode($home, JSON_UNESCAPED_UNICODE);
	pg_assert_contains('"trigger":"load"', $json, 'the school hero comes in on load');
	foreach (array('sh-home-programmes', 'sh-home-year') as $id) pg_assert_contains('"id":"' . $id . '"', $json, $id . ' is a search section');
	pg_assert_same(3, substr_count($json, 'pg-school-term '), 'three terms');
	pg_assert_same(2, substr_count($json, 'pg-school-break '), 'two holidays');
}

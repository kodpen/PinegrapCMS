<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Car Dealership design template
 * (includes/design_templates/pinegrap-dealership.php): it is offered as a
 * template without a shop, carries every part's pages in order and each
 * widget once, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the pdl- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, and the sample
 * vehicles survive the assembly.
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

function _test_dl_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_dl_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dl_asm_tpl_nodes($child, $out);
	}
}

function test_dealership_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-dealership']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['vehicles']) && !isset($all['requests']), 'the parts folder is not read as a template');
	$tpl = _test_dl_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-car-front', $tpl['icon']);
	pg_assert_same(75, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: a car is sold in the showroom');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('corporate', $tpl['look']);
	pg_assert_same('cobalt', $tpl['palette'], 'the palette the e-mails\' blue is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_dealership_assembly_template_pages_and_widgets()
{
	$tpl = _test_dl_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		array('pages' => include $dir . '/pages_public.php', 'widgets' => array()),
		include $dir . '/vehicles.php',
		include $dir . '/requests.php',
		include $dir . '/finance_requests.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'test_drive_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
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
		_test_dl_asm_tpl_nodes($tree, $nodes);
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

function test_dealership_assembly_template_search_and_look()
{
	$tpl = _test_dl_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_dl_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pdl-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_dl_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpdl-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pdl- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_dl_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'test_drive_messages' => array('page' => 'test_drive_view', 'view' => 'test_drive_detail') + $request,
		'tradein_messages' => array('page' => 'tradein_view', 'view' => 'tradein_detail') + $request,
		'finance_messages' => array('page' => 'finance_view', 'view' => 'finance_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_dealership_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-dealership');
	_tpl_threads_check_no_fixed_block('pinegrap-dealership', array(
		'conversation_replies' => 'comments', 'email_comment' => 'comments', 'finance_messages' => 'comments',
		'post_comments' => 'comments', 'test_drive_messages' => 'comments', 'tradein_messages' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'test_drive_view' => lang('Message'), 'tradein_view' => lang('Message'),
		'finance_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_dl_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-dealership', $key, $t);
		_tpl_threads_check_draws('pinegrap-dealership', $key, $t);
	}
	foreach (array('test_drive_detail', 'tradein_detail', 'finance_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the customer\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-dealership', 'email_comment', 'email_reply',
		array('blog_post', 'test_drive_view', 'tradein_view', 'finance_view', 'conversation'));
}

// The sample vehicles are parts.php's $vehicles; the assembly keeps every
// part's return under another name, so the collection still seeds all of
// them.
function test_dealership_assembly_samples()
{
	$tpl = _test_dl_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	pg_assert_true(count($vehicles) >= 6, 'sample vehicles in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($vehicles as $key => $v) {
		pg_assert_contains(json_encode($v['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
}

// The dealer's Google reviews sit under the sample testimonials on the
// home page, with the layout Google asks for.
function test_dealership_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-dealership', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-dealership');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_featured"') !== false && strpos($home, '"templateWidget":"home_used"') !== false, 'the home places the featured and the used cars');
}

// The home is built on the Car Dealership blocks and CTA: Sticky Bar: every
// pg-dl- and pg-sticky- class's rule ships in the User Styles, the
// interactions are kept as written, one h1 (the first showroom panel, on
// load), three panels with their photos, the bar sticking through the rest
// of the page, the finder's filters and the three ways' requests.
function test_dealership_assembly_home_on_dealership_blocks()
{
	$tpl = _test_dl_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version raised');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$home = null;
	foreach ($tpl['pages'] as $p) if ($p['key'] === 'home') $home = $p['tree'];
	$nodes = array();
	_test_dl_asm_tpl_nodes($home, $nodes);
	$classes = '';
	$effects = array();
	$h1 = 0;
	$panels = 0;
	foreach ($nodes as $n) {
		$class = isset($n['props']['cssClass']) && is_string($n['props']['cssClass']) ? $n['props']['cssClass'] : '';
		$classes .= ' ' . $class;
		if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
		if (strpos(' ' . $class . ' ', ' pg-dl-panel ') !== false) {
			$panels++;
			pg_assert_true(strpos($n['props']['_attrs'][0]['value'], 'background-image: url(https://picsum.photos/seed/pinegrap-dealership-panel-') === 0, 'a panel\'s photo is its background');
		}
		if (!isset($n['props']['_fx'])) continue;
		$effects[] = $n['props']['_fx']['effect'];
		$kept = pg_fx_normalize($n['props']['_fx']);
		foreach ($n['props']['_fx'] as $field => $value) {
			pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, 'home: ' . $field . ' is kept as written');
		}
	}
	preg_match_all('/(?<![\w-])pg-(dl|sticky)-[a-z0-9-]+/', $classes, $m);
	foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, 'the rule of ' . $class . ' ships');
	pg_assert_contains('.pg-shade-bottom', $css, 'the core scrim of the tiles ships');
	pg_assert_same(1, $h1, 'one h1');
	pg_assert_same(3, $panels, 'three showroom panels');
	pg_assert_same(1, count(array_keys($effects, 'sticky', true)), 'one sticky bar');
	pg_assert_true(count($effects) >= 8, 'the home moves');

	$json = json_encode($home, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('"tag":"h1","text":"Velo Volt Long Range"', $json, 'the first panel is the page\'s title');
	pg_assert_contains('"trigger":"load"', $json, 'the first panel comes in on load');
	pg_assert_contains('{{page:test_drive}}?vehicle=Velo%20Volt%20Long%20Range', $json, 'a panel books a test drive of its car');
	pg_assert_contains('{{page:vehicles}}?body=SUV', $json, 'a panel opens the stock of its body style');
	pg_assert_contains('"id":"car-velo-volt"', $json, 'a panel is a search section');
	pg_assert_contains('"id":"stock"', $json, 'the finder is a search section');
	pg_assert_contains('"id":"ways"', $json, 'the three ways are a search section');
	foreach (array('{{page:new_cars}}', '{{page:used_cars}}', '{{page:tradein}}', '{{page:finance}}') as $href) {
		pg_assert_contains('"href":"' . $href . '"', $json, 'the home links ' . $href);
	}
	pg_assert_false(strpos($classes, 'btn-light') !== false, 'no fixed light button on the home');
}

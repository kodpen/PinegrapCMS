<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Gym design template
 * (includes/design_templates/pinegrap-gym.php): it is offered without a
 * shop requirement and with its catalog; every part's pages come in order,
 * only the shop pages and the address book need the shop; every widget is
 * defined once and placed; placeholders name pages and folders; the
 * members folder is a membership folder; the contact page's search
 * sections; the pgy- classes and their rules match; the comment threads
 * and the reply e-mail card; the Google reviews on the home page; the
 * sample trainers and the plans' products kept.
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

function _test_gy_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_gy_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gy_asm_nodes($child, $out);
	}
}

function test_gym_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-gym']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['trainers']) && !isset($all['classes']), 'the parts folder is not read as a template');
	$tpl = _test_gy_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-lightning-charge', $tpl['icon']);
	pg_assert_same(79, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!empty($tpl['catalog']), 'the memberships\' catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'members'), array_keys($tpl['folders']));
	pg_assert_same('membership', $tpl['folders']['members']['access'], 'a membership opens the members folder');
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('crimson', $tpl['palette'], 'the palette the e-mails\' crimson is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_gym_assembly_template_pages_and_widgets()
{
	$tpl = _test_gy_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/trainers.php',
		include $dir . '/classes.php',
		include $dir . '/members.php',
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
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'join_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop's pages and the address book need the shop; the
	// members' area stands on its folder's access.
	$shop_keys = array('address_book');
	foreach ($parts[3]['pages'] as $p) if (isset($p['requires'])) $shop_keys[] = $p['key'];
	pg_assert_same(array('address_book', 'memberships', 'product', 'cart', 'checkout', 'order', 'email_order'), $shop_keys, 'the shop pages');
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
		_test_gy_asm_nodes($tree, $nodes);
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

function test_gym_assembly_template_search_and_look()
{
	$tpl = _test_gy_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_gy_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pgy-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_gy_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpgy-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pgy- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_gym_assembly_comment_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	$threads = array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'join_messages' => array('page' => 'join_view', 'view' => 'join_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
	$tpl = _tpl_threads_tpl('pinegrap-gym');
	_tpl_threads_check_no_fixed_block('pinegrap-gym', array(
		'booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'join_messages' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'join_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach ($threads as $key => $t) {
		_tpl_threads_check_thread('pinegrap-gym', $key, $t);
		_tpl_threads_check_draws('pinegrap-gym', $key, $t);
	}
	foreach (array('booking_detail', 'join_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-gym', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'join_view', 'conversation'));
}

// The sample trainers are parts.php's $trainers and the plans its $plans;
// the assembly keeps every part's return under another name, so the
// collection still seeds every trainer and the catalog every plan.
function test_gym_assembly_samples()
{
	$tpl = _test_gy_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym';
	include $dir . '/parts.php';
	pg_assert_true(count($trainers) >= 4, 'sample trainers in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($trainers as $key => $t) {
		pg_assert_contains(json_encode($t['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	$catalog = json_encode($tpl['catalog'], JSON_UNESCAPED_UNICODE);
	foreach ($plans as $key => $p) {
		pg_assert_contains(json_encode($p['sku'], JSON_UNESCAPED_UNICODE), $catalog, $key . ': its product is in the catalog');
	}
}

// The gym's Google reviews sit under the sample member stories on the home
// page, with the layout Google asks for.
function test_gym_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-gym', 'google_reviews', 'home', lang('Member Stories'));
	$tpl = _tpl_threads_tpl('pinegrap-gym');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_trainers', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, 'the home places ' . $w);
	}
}

// Home, classes and timetable place the palette's blocks (Gym: Free Pass
// Hero and Class Finder, Booking: Weekly Timetable, Hero: Image Background):
// the rule of every pg- class they use ships in the template's User Styles;
// every interaction is one pg_fx_normalize() keeps as written; the motion is
// where it belongs (the hero on load, the parallax band and the sticky floor
// on the home, the sticky focus pills on the class page, the week's days on
// view); every class card has its intensity meter, and the week has its
// seven days in a scrolling region a keyboard can reach.
function test_gym_assembly_home_classes_timetable_on_blocks()
{
	$tpl = _test_gy_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$source = _pg_designer_block_css_source();
	$want = array(
		'home'      => array('slide-up/load', 'fade/load', 'parallax', 'sticky', 'slide-up/view', 'slide-right/view', 'slide-left/view', 'fade/view'),
		'classes'   => array('sticky', 'slide-up/view', 'fade/view', 'scale/view'),
		'timetable' => array('slide-up/view'),
	);
	foreach ($tpl['pages'] as $p) {
		if (!isset($want[$p['key']])) continue;
		$key = $p['key'];
		$nodes = array();
		_test_gy_asm_nodes($p['tree'], $nodes);
		$effects = array();
		$classes = '';
		$cards = 0;
		$meters = 0;
		$week = null;
		foreach ($nodes as $n) {
			$class = isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '';
			$classes .= ' ' . $class;
			if (isset($n['props']['_fx'])) {
				$kept = pg_fx_normalize($n['props']['_fx']);
				pg_assert_true(is_array($kept), "$key: " . json_encode($n['props']['_fx']) . ' is an interaction');
				foreach ($n['props']['_fx'] as $field => $value) {
					pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, "$key: $field is kept as written");
				}
				$effects[] = $n['props']['_fx']['effect'] . (isset($n['props']['_fx']['trigger']) ? '/' . $n['props']['_fx']['trigger'] : '');
			}
			if (isset($n['props']['_label']) && $n['props']['_label'] === lang('Class Card')) $cards++;
			if (preg_match('/(^|\s)pg-gym-meter(\s|$)/', $class)) {
				$meters++;
				pg_assert_same(5, count($n['children']), "$key: a meter has five steps");
			}
			if (preg_match('/(^|\s)pg-week(\s|$)/', $class)) $week = $n;
		}
		preg_match_all('/(?<![\w-])pg-[a-z0-9-]+/', $classes, $m);
		pg_assert_true(!empty($m[0]), "$key: places palette blocks");
		foreach (array_unique($m[0]) as $class) {
			// The class's own rule, else the first that names it (a part
			// such as pg-week-slot lives in its block's rule).
			$rule = isset($source['rules'][$class]) ? $source['rules'][$class] : '';
			foreach ($rule === '' ? $source['rules'] : array() as $text) {
				if (preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $text)) {
					$rule = $text;
					break;
				}
			}
			pg_assert_true($rule !== '' && strpos($css, $rule) !== false, "$key: the rule of $class ships with the template");
		}
		foreach ($want[$key] as $e) pg_assert_true(in_array($e, $effects, true), "$key moves: $e");
		pg_assert_same($cards, $meters, "$key: an intensity meter per class card");
		if ($key === 'classes') pg_assert_true($cards >= 7, 'classes: every class has its card');
		if ($key === 'timetable') {
			pg_assert_true($week !== null, 'timetable: the week');
			$attrs = array();
			foreach ($week['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
			pg_assert_same(array('role' => 'region', 'aria-label' => lang('Class Timetable'), 'tabindex' => '0'), $attrs, 'timetable: a named region a keyboard can scroll');
			pg_assert_same(7, count($week['children'][0]['children']), 'timetable: seven days');
		}
	}
}

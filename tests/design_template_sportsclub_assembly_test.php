<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Sports Club design template
 * (includes/design_templates/pinegrap-sportsclub.php): it is offered as a
 * template that opens without the shop and carries the kit, season ticket
 * and membership catalog, every part's pages in order and each widget
 * once, only the shop pages and the address book need the shop, every
 * placed widget, shared part and page placeholder names something the
 * template defines, the contact page's parts are search sections, the
 * psp- classes equal their rules, the comment threads run on the Comments
 * widget with the reply e-mail's card, the sample players, matches and
 * events survive the assembly with a page per team, and the Google reviews
 * sit under the sample testimonials.
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
require_once(__DIR__ . '/design_template_sportsclub_test.php');

function _test_sp_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_sp_asm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sp_asm_nodes($child, $out);
	}
}

function test_sportsclub_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-sportsclub']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['squad']) && !isset($all['matches']), 'the parts folder is not read as a template');
	$tpl = _test_sp_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-trophy', $tpl['icon']);
	pg_assert_same(113, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(array('kit', 'season_tickets', 'memberships'), array_keys($tpl['catalog']['groups']), 'the catalog');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'members'), array_keys($tpl['folders']));
	pg_assert_same('membership', $tpl['folders']['members']['access'], 'a membership opens the members folder');
	pg_assert_same('bold', $tpl['look']);
	pg_assert_same('forest', $tpl['palette'], 'the palette the e-mails\' green is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
	// No other template takes the same place in the installer.
	foreach ($all as $id => $t) {
		if ($id !== 'pinegrap-sportsclub' && isset($t['order'])) pg_assert_true($t['order'] !== 113, "$id is not at order 113");
	}
}

function test_sportsclub_assembly_template_pages_and_widgets()
{
	$tpl = _test_sp_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	include $dir . '/blocks.php';
	$squad = include $dir . '/squad.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		$squad,
		include $dir . '/matches.php',
		include $dir . '/registrations.php',
		include $dir . '/events.php',
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
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	// Every page the plan names is there (the e-mail pages besides).
	foreach (_test_sp_page_keys() as $key) pg_assert_true(in_array($key, $keys, true), "page $key is in the template");
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'join_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the shop pages and the address book need the shop.
	$shop = array_flip(array_merge(_test_sp_shop_keys(), array('address_book')));
	foreach ($tpl['pages'] as $p) {
		pg_assert_same(isset($shop[$p['key']]), isset($p['requires']), $p['key'] . ': needs the shop only if it is a shop page');
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
		_test_sp_asm_nodes($tree, $nodes);
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
	preg_match_all('/\{\{product_group:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['catalog']['groups'][$key]), 'placeholder names product group ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_sportsclub_assembly_template_search_and_look()
{
	$tpl = _test_sp_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_sp_asm_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
		if ($p['key'] === 'search') {
			pg_assert_true(in_array('sp-help-faq-1-item', $ids, true), 'the help page\'s questions are search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(psp-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_sp_asm_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpsp-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'psp- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
	pg_assert_contains('prefers-reduced-motion', $css, 'the lift stays still for reduced motion');
}

function _test_sp_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'registration_messages' => array('page' => 'registration_view', 'view' => 'registration_detail') + $request,
		'trial_messages' => array('page' => 'trial_view', 'view' => 'trial_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_sportsclub_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-sportsclub');
	_tpl_threads_check_no_fixed_block('pinegrap-sportsclub', array(
		'registration_messages' => 'comments', 'trial_messages' => 'comments', 'conversation_replies' => 'comments',
		'email_comment' => 'comments', 'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'registration_view' => lang('Message'), 'trial_view' => lang('Message'),
		'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_sp_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-sportsclub', $key, $t);
		_tpl_threads_check_draws('pinegrap-sportsclub', $key, $t);
	}
	foreach (array('registration_detail', 'trial_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-sportsclub', 'email_comment', 'email_reply',
		array('blog_post', 'registration_view', 'trial_view', 'conversation'));
}

// The samples are parts.php's $players, $matches and $events; the assembly
// keeps every part's return under another name, so the collections still
// seed every player, match and event, and every team has its page.
function test_sportsclub_assembly_samples()
{
	$tpl = _test_sp_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	pg_assert_true(count($players) >= 4 && count($matches) >= 4 && count($events) >= 3, 'sample records in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($players as $key => $p) {
		pg_assert_contains(json_encode($p['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': the player is in the template');
	}
	foreach ($matches as $key => $m) {
		pg_assert_contains(json_encode($m['report'], JSON_UNESCAPED_UNICODE), $json, $key . ': the match is in the template');
	}
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': the event is in the template');
	}
	foreach (array_keys($teams) as $key) {
		pg_assert_contains('"key":"team_' . $key . '"', $json, $key . ': its team page');
	}
}

// The club's Google reviews sit under the sample words on the home page,
// with the layout Google asks for.
function test_sportsclub_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-sportsclub', 'google_reviews', 'home', lang('Voices of the Club'));
	$tpl = _tpl_threads_tpl('pinegrap-sportsclub');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('google_reviews', 'home_fixtures', 'home_results', 'home_events', 'home_posts') as $w) {
		pg_assert_true(strpos($home, '"templateWidget":"' . $w . '"') !== false, "the home places $w");
	}
}

// Home, fixtures, results and teams are built on the palette's blocks
// (Sports Club: Match Day, League Table, Squad by Position): every pg-
// class they write is a block rule the template's User Styles ship, their
// interactions are kept as written by pg_fx_normalize(), the match day is
// a dark band with two crests and a kick-off time, and no story band or
// figure row is left on the home.
function test_sportsclub_assembly_signature_pages_on_blocks()
{
	$tpl = _test_sp_asm_tpl();
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$source = _pg_designer_block_css_source();
	$want = array('home', 'fixtures', 'results', 'teams');
	$seen = array();
	foreach ($tpl['pages'] as $p) {
		if (!in_array($p['key'], $want, true)) continue;
		$seen[] = $p['key'];
		$nodes = array();
		_test_sp_asm_nodes($p['tree'], $nodes);
		$effects = array();
		$classes = '';
		foreach ($nodes as $n) {
			$classes .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (!isset($n['props']['_fx'])) continue;
			$kept = pg_fx_normalize($n['props']['_fx']);
			pg_assert_true(is_array($kept), $p['key'] . ': ' . json_encode($n['props']['_fx']) . ' is an interaction');
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $p['key'] . ': ' . $field . ' is kept as written');
			}
			$effects[] = $n['props']['_fx']['effect'] . '/' . $n['props']['_fx']['trigger'];
		}
		preg_match_all('/(?<![\w-])pg-[a-z0-9-]+/', $classes, $m);
		$pg = array_values(array_diff(array_unique($m[0]), array('pg-hide-if-empty', 'pg-field-value')));
		pg_assert_true(!empty($pg), $p['key'] . ': places palette blocks');
		foreach ($pg as $class) {
			$rule = '';
			foreach ($source['rules'] as $text) {
				if (preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $text)) {
					$rule = $text;
					break;
				}
			}
			pg_assert_true($rule !== '' && strpos($css, $rule) !== false, $p['key'] . ": the rule of $class ships with the template");
		}
		$json = json_encode($p['tree']);
		if ($p['key'] === 'home' || $p['key'] === 'fixtures') {
			pg_assert_same(2, substr_count($json, '"pg-sc-crest"'), $p['key'] . ': a crest for each side');
			pg_assert_true(strpos($json, '"name":"data-bs-theme","value":"dark"') !== false, $p['key'] . ': the match day is a dark band');
			pg_assert_true(strpos($json, '"tag":"time"') !== false && strpos($json, '"name":"datetime","value":"15:00"') !== false, $p['key'] . ': the kick-off is a time');
		}
		if ($p['key'] === 'home') {
			foreach (array('slide-up/load', 'fade/load', 'slide-up/view', 'fade/view') as $e) pg_assert_true(in_array($e, $effects, true), "home moves: $e");
			pg_assert_true(strpos($json, 'pg-sc-up') !== false, 'home: the league table');
			pg_assert_same(false, strpos($json, '"Figures"'), 'home: no figure row');
			pg_assert_same(false, strpos($json, 'sp-home-academy'), 'home: no story band');
		} elseif ($p['key'] === 'results') {
			pg_assert_same(1, substr_count($json, '"name":"aria-current","value":"true"'), 'results: the club\'s own row in the league table');
		} elseif ($p['key'] === 'teams') {
			pg_assert_same(4, substr_count($json, '"pg-sc-number"'), 'teams: a shirt number per first-team player');
			pg_assert_true(count(array_keys($effects, 'slide-up/view', true)) >= 3, 'teams: the team rows and the squad come in on view');
		}
	}
	pg_assert_same($want, array_values(array_intersect($want, $seen)), 'the four pages are in the template');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's public pages
 * (includes/design_templates/pinegrap-appstudio/pages_public.php): the six
 * pages are there once each, every page link names a page of the finished
 * template, the home places the lists of the other page files, the links
 * into the app list and the project request use the pick lists' own words,
 * each FAQ accordion holds its own questions, the search anchors are unique
 * and no class or field id of another template slipped in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then the public pages in the same scope, as the template file
// reads them. Read once per run.
function _test_map_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	$public_part = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($public_part as $p) $pages[$p['key']] = $p;
	$out = array(
		'pages'           => $pages,
		'count'           => count($public_part),
		'category_titles' => $category_titles,
		'project_types'   => array_values($project_types),
		'apps'            => $apps,
	);
	return $out;
}

function _test_map_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_map_nodes($child, $out);
	}
}

function _test_map_all_nodes()
{
	$nodes = array();
	foreach (_test_map_load()['pages'] as $key => $p) {
		$list = array();
		_test_map_nodes($p['tree'], $list);
		foreach ($list as $node) $nodes[] = array($key, $node);
	}
	return $nodes;
}

function test_appstudio_public_pages_are_there()
{
	$d = _test_map_load();
	pg_assert_same(array('home', 'services', 'process', 'about', 'faq', 'press'), array_keys($d['pages']), 'the six public pages in order');
	pg_assert_same(6, $d['count'], 'each page once');
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('public', $p['folder'], "$key: public folder");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: page name is an address");
		pg_assert_true(trim($p['title']) !== '' && trim($p['meta_description']) !== '', "$key: title and description");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		$names[$p['name']] = true;
	}
	pg_assert_same(6, count($names), 'page names unique');
}

function test_appstudio_public_pages_link_template_pages()
{
	$known = array_flip(array(
		'home', 'services', 'process', 'about', 'faq', 'press',
		'app_new', 'apps', 'app',
		'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	));
	$links = 0;
	$anchors = array();
	foreach (_test_map_all_nodes() as $pair) {
		list($page, $node) = $pair;
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $node['props'][$prop], $m);
			foreach ($m[1] as $key) {
				$links++;
				pg_assert_true(isset($known[$key]), "$page: link to a page of the template: $key");
			}
		}
		$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf|pdp|pm)-[a-z]/', $class), "$page: no class of another template: $class");
		pg_assert_false((bool)preg_match('/\bratio-9x16\b/', $class), "$page: only Bootstrap's own ratios");
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^(lw|re|pd)-/', $id), "$page: no field id of another template: $id");
		if (!empty($node['props']['searchSection'])) {
			pg_assert_false(isset($anchors[$page][$id]), "$page: search anchor $id once");
			$anchors[$page][$id] = true;
		}
	}
	pg_assert_true($links > 30, 'the pages link the site');
	foreach (array('services', 'process', 'about', 'press') as $page) pg_assert_true(!empty($anchors[$page]), "$page: has search sections");
}

// The home places the app list, the release notes and the blog posts of the
// other page files, and every page ends with the shared project band.
function test_appstudio_public_pages_place_widgets_and_shared_parts()
{
	$d = _test_map_load();
	$refs = array();
	foreach (_test_map_all_nodes() as $pair) {
		list($page, $node) = $pair;
		if ($node['type'] !== 'shared_ref') continue;
		foreach (array('templateWidget', 'templateShared') as $kind) {
			if (isset($node['props'][$kind])) $refs[$page][$kind][] = $node['props'][$kind];
		}
	}
	foreach (array('home_apps', 'home_releases', 'home_posts') as $w) {
		pg_assert_true(in_array($w, $refs['home']['templateWidget'], true), "home places $w");
	}
	foreach (array_keys($d['pages']) as $page) {
		pg_assert_same(array('account_bar', 'header', 'project_band', 'footer'), $refs[$page]['templateShared'], "$page: bar, header, project band, footer");
		pg_assert_true(in_array('newsletter_band', $refs[$page]['templateWidget'], true), "$page: newsletter band");
	}
}

// Links into the app list (?category=) and the project request (?type=,
// ?package=) carry the words of the forms' pick lists.
function test_appstudio_public_pages_filter_links_use_list_words()
{
	$d = _test_map_load();
	$seen = array('category' => array(), 'type' => array());
	foreach (_test_map_all_nodes() as $pair) {
		list($page, $node) = $pair;
		if (!isset($node['props']['href']) || !is_string($node['props']['href'])) continue;
		$href = $node['props']['href'];
		if (strpos($href, '{{page:apps}}?') === 0) {
			parse_str(substr($href, strlen('{{page:apps}}?')), $q);
			pg_assert_true(isset($q['category']) && in_array($q['category'], $d['category_titles'], true), "$page: app list link names a Category answer");
			$seen['category'][$q['category']] = true;
		} elseif (strpos($href, '{{page:hire}}?') === 0) {
			parse_str(substr($href, strlen('{{page:hire}}?')), $q);
			if (isset($q['type'])) {
				pg_assert_true(in_array($q['type'], $d['project_types'], true), "$page: project link names a kind of project");
				$seen['type'][$q['type']] = true;
			} else {
				pg_assert_true(isset($q['package']), "$page: project link chooses a package or a kind");
			}
		}
	}
	pg_assert_same(count($d['category_titles']), count($seen['category']), 'a link per kind of app');
	pg_assert_true(count($seen['type']) >= 3, 'services ask for several kinds of project');
}

// Each FAQ accordion holds only its own questions: every answer names the
// accordion it sits in as its parent, and every question opens its answer.
function test_appstudio_faq_accordions_hold_their_questions()
{
	$d = _test_map_load();
	$nodes = array();
	_test_map_nodes($d['pages']['faq']['tree'], $nodes);
	$accordions = 0;
	foreach ($nodes as $node) {
		if (($node['props']['cssClass'] ?? '') !== 'accordion' && strpos($node['props']['cssClass'] ?? '', 'accordion ') !== 0) continue;
		$accordions++;
		$id = $node['props']['id'];
		$inner = array();
		_test_map_nodes($node, $inner);
		$targets = array();
		$answers = array();
		foreach ($inner as $n) {
			foreach ($n['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'data-bs-parent') pg_assert_same('#' . $id, $a['value'], "$id: answer in its accordion");
				if ($a['name'] === 'data-bs-target') $targets[] = substr($a['value'], 1);
			}
			if (strpos($n['props']['cssClass'] ?? '', 'accordion-collapse') !== false) $answers[] = $n['props']['id'];
		}
		pg_assert_same($answers, $targets, "$id: every question opens its answer");
		pg_assert_true(count($answers) >= 4, "$id: questions");
	}
	pg_assert_same(2, $accordions, 'users and clients');
}

// The press kit names every sample app once, with an icon to download.
function test_appstudio_press_kit_lists_every_app()
{
	$d = _test_map_load();
	$nodes = array();
	_test_map_nodes($d['pages']['press']['tree'], $nodes);
	$names = array();
	$downloads = 0;
	foreach ($nodes as $node) {
		if (($node['props']['contentType'] ?? '') === 'heading' && ($node['props']['tag'] ?? '') === 'h3') $names[] = $node['props']['text'];
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'download') $downloads++;
		}
	}
	foreach ($d['apps'] as $key => $a) pg_assert_true(in_array($a['name'], $names, true), "press kit: $key");
	pg_assert_same(count($d['apps']), $downloads, 'an icon to download per app');
}

// The home is built on the designer's App Studio blocks: the local bar
// held at the top, one h1, the block classes whose rules the template
// ships, interactions the normalizer keeps as written, and a switch whose
// labels each point at one of its radio inputs.
function test_appstudio_home_on_the_app_studio_blocks()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/designer_fx.php';
	$home = _test_map_load()['pages']['home'];
	$nodes = array();
	_test_map_nodes($home['tree'], $nodes);

	$main = null;
	foreach ($nodes as $n) if (isset($n['props']['tag']) && $n['props']['tag'] === 'main') { $main = $n; break; }
	pg_assert_true($main !== null, 'the home has its main');
	$bar = $main['children'][0];
	pg_assert_same('nav', $bar['props']['tag'], 'the local bar comes first');
	pg_assert_same(array('effect' => 'sticky', 'offset' => 0), $bar['props']['_fx'], 'and stays at the top of the window');

	$classes = array();
	$h1 = 0;
	$fx = 0;
	$ids = array();
	$fors = array();
	foreach ($nodes as $n) {
		$p = $n['props'];
		foreach (preg_split('/\s+/', isset($p['cssClass']) ? $p['cssClass'] : '', -1, PREG_SPLIT_NO_EMPTY) as $c) $classes[$c] = true;
		if (isset($p['contentType'], $p['tag']) && $p['contentType'] === 'heading' && $p['tag'] === 'h1') $h1++;
		if (isset($p['_fx'])) {
			$fx++;
			$norm = pg_fx_normalize($p['_fx']);
			pg_assert_true(is_array($norm), 'a known interaction: ' . json_encode($p['_fx']));
			foreach ($p['_fx'] as $k => $v) pg_assert_same($v, $norm[$k], 'the normalizer keeps ' . $k . ' of ' . $p['_fx']['effect']);
		}
		if (isset($p['_attrs'])) {
			foreach ($p['_attrs'] as $a) {
				if ($a['name'] === 'id' && isset($p['tag']) && $p['tag'] === 'input') $ids[] = $a['value'];
				if ($a['name'] === 'for') $fors[] = $a['value'];
			}
		}
	}
	pg_assert_same(1, $h1, 'one h1');
	pg_assert_true($fx >= 8, 'the home moves: ' . $fx . ' interactions');
	pg_assert_same(3, count(array_unique($ids)), 'three app choices');
	pg_assert_same($ids, $fors, 'each label points at its choice');

	$used = array_keys($classes);
	$block_classes = array_values(preg_grep('/^pg-appstudio-/', $used));
	foreach (array('pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-fan', 'pg-appstudio-switch', 'pg-appstudio-peek', 'pg-appstudio-figure') as $c) {
		pg_assert_true(in_array($c, $block_classes, true), 'the home uses ' . $c);
	}
	pg_assert_true(!isset($classes['launch-hero']) && !preg_grep('/^pg-launch-/', $used), 'nothing of the Product Launch template');

	// The template ships the rules of the blocks it names.
	$template = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio.php');
	pg_assert_true((bool)preg_match("/pg_design_template_user_styles\(array\(([^)]*)\)/", $template, $m), 'the template names its blocks');
	$names = array_map(function ($s) { return trim($s, " '"); }, explode(',', $m[1]));
	$css = pg_designer_block_css($names);
	foreach ($block_classes as $c) {
		pg_assert_true((bool)preg_match('/\.' . preg_quote($c, '/') . '(?![\w-])/', $css), 'the template ships a rule for ' . $c);
	}
}

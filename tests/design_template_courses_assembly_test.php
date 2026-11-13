<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Online Courses design template
 * (includes/design_templates/pinegrap-courses.php): it is offered as a
 * template, carries every part's pages in order and each widget once, every
 * placed widget, shared part and page placeholder names something the
 * template defines, each course has its own private folder that its
 * product grants and the library sits in the membership folder, the
 * catalog is the shop part's one, the contact page's parts are search
 * sections, the pco- classes equal their rules, and the home, the courses
 * page and each course's page are built on the Online Courses blocks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_courses_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-courses.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_courses_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_courses_tpl_nodes($child, $out);
	}
}

function test_courses_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-courses']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['learn']), 'the parts folder is not read as a template');
	$tpl = _test_courses_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'the version moved');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-mortarboard', $tpl['icon']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private', 'course_web', 'course_data', 'course_ux', 'members'), array_keys($tpl['folders']));
	foreach (array('course_web', 'course_data', 'course_ux') as $key) pg_assert_same('private', $tpl['folders'][$key]['access'], "$key is private");
	pg_assert_same('membership', $tpl['folders']['members']['access'], 'the library\'s folder is for members');
	pg_assert_same('ocean', $tpl['palette'], 'the palette the e-mails\' teal is made for');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter contact group');
}

function test_courses_template_pages_and_widgets()
{
	$tpl = _test_courses_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-courses';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/learn.php',
		include $dir . '/assignments.php',
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
	pg_assert_same($parts[3]['catalog'], $tpl['catalog'], 'the shop part\'s catalog');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'enrol_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));

	// Each course's product grants a folder of the template.
	$granted = 0;
	foreach ($tpl['catalog']['groups'] as $g) {
		foreach ($g['products'] as $product) {
			if (!isset($product['private_folder'])) continue;
			pg_assert_true(isset($tpl['folders'][$product['private_folder']]), 'product ' . $product['sku'] . ' grants a template folder');
			$granted++;
		}
	}
	pg_assert_same(3, $granted, 'every course grants its folder');

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_courses_tpl_nodes($tree, $nodes);
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

function test_courses_template_search_and_look()
{
	$tpl = _test_courses_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_courses_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pco-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	// Classes only: the form fields' ids share the prefix.
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_courses_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bpco-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pco- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

// The home, the courses page and each course's page are built on the Online
// Courses blocks (2.0.0): their classes' rules ship in the User Styles,
// their interactions survive pg_fx_normalize(), each page has one h1 and
// only nodes as children, a
// course's path holds its lessons in order with the first one free and the
// rest locked, and the prices are the shop's in the base currency.
function test_courses_pages_on_online_courses_blocks()
{
	$tpl = _test_courses_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-courses/parts.php';
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'    => array('pg-courses-hero', 'pg-courses-desk', 'pg-courses-player', 'pg-courses-play', 'pg-courses-scrub',
			'pg-courses-shelf', 'pg-courses-card', 'pg-courses-level', 'pg-courses-on', 'pg-courses-face', 'pg-courses-price',
			'pg-courses-modules', 'pg-courses-module', 'pg-courses-marker', 'pg-courses-project', 'pg-courses-facts', 'pg-courses-second'),
		'courses' => array('pg-courses-shelf', 'pg-courses-card', 'pg-courses-level', 'pg-courses-on', 'pg-courses-price'),
	);
	foreach (array_keys($courses) as $ckey) $wanted['course_' . $ckey] = array('pg-courses-modules', 'pg-courses-module', 'pg-courses-marker', 'pg-courses-project');
	foreach ($wanted as $key => $classes) {
		pg_assert_true(isset($trees[$key]), $key . ': the page is there');
		$nodes = array();
		_test_courses_tpl_nodes($trees[$key], $nodes);
		$class_list = '';
		$effects = 0;
		$h1 = 0;
		$loose = 0;
		foreach ($nodes as $n) {
			// A child that is a list of nodes instead of a node is dropped
			// when the page is drawn.
			if (!empty($n['children'])) foreach ($n['children'] as $child) if (!isset($child['type'], $child['props'])) $loose++;
			$class_list .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-courses-[a-z0-9-]+(?![\w-])/', $class_list, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($classes as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $class_list), $key . ': places ' . $class);
		}
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_same(0, $loose, $key . ': every child is a node');
		pg_assert_true($effects >= 1, $key . ': comes in as it is seen');
	}

	// A course's path: its lessons in order, the first a link to its free
	// lesson and every other one locked, then the project.
	foreach (array_keys($courses) as $ckey) {
		$lessons = $course_lessons($ckey);
		$nodes = array();
		_test_courses_tpl_nodes($trees['course_' . $ckey], $nodes);
		$path = null;
		foreach ($nodes as $n) {
			if (isset($n['props']['cssClass']) && strpos(' ' . $n['props']['cssClass'] . ' ', ' pg-courses-modules ') !== false) $path = $n;
		}
		pg_assert_true($path !== null, $ckey . ': the curriculum is a path');
		pg_assert_same(count($courses[$ckey]['modules']) + 1, count($path['children']), $ckey . ': each module, then the project');
		$in_path = array();
		_test_courses_tpl_nodes($path, $in_path);
		$rows = array();
		$free = array();
		$locked = 0;
		foreach ($in_path as $n) {
			if (isset($n['props']['tag'], $n['props']['cssClass']) && $n['props']['tag'] === 'li' && strpos($n['props']['cssClass'], 'border-top') !== false) $rows[] = $n['props']['_label'];
			if (isset($n['props']['contentType']) && $n['props']['contentType'] === 'link') $free[] = $n['props']['href'];
			if (isset($n['props']['text']) && $n['props']['text'] === lang('Enrolled students only')) $locked++;
		}
		$titles = array();
		foreach ($lessons as $l) $titles[] = $l['title'];
		pg_assert_same($titles, $rows, $ckey . ': the lessons in their order');
		pg_assert_same(array('{{page:lesson_' . $lessons[0]['key'] . '}}'), $free, $ckey . ': only the first lesson opens');
		pg_assert_same(count($lessons) - 1, $locked, $ckey . ': every other lesson says it is for enrolled students');
		pg_assert_contains($courses[$ckey]['assignment'], json_encode(end($path['children']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $ckey . ': the project ends the path');
	}

	// The prices are the shop's, in the base currency (USD's figure for one
	// the template does not list).
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	foreach ($courses as $ckey => $c) {
		$amount = isset($c['prices'][$base]) ? $c['prices'][$base] : $c['prices']['USD'];
		pg_assert_same(pg_money_text($amount, $symbol), $c['price'], $ckey . ': the price the shop asks');
	}
	$pricing = json_encode($trees['pricing'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains(json_encode(sprintf(lang('From %s'), $courses['web']['price']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $pricing, 'one course starts at the cheapest');
	pg_assert_contains(json_encode($membership['price'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $pricing, 'the membership\'s price a year');
}

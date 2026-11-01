<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Online Courses design template's parts
 * (includes/design_templates/pinegrap-courses/): the school's data hangs
 * together (subjects, levels, instructors, courses with their modules and
 * lessons, the membership), every course names its own folder and a unique
 * SKU, lesson keys are unique, and every page link of the shared parts
 * names a page of the finished template; the shared parts carry no class
 * of another template. The public pages load with their keys and root
 * trees, link only template pages and anchors that exist, place only
 * widgets the template defines, give each course a page with its free
 * lesson, buy box and enrolment request, and place the Google Reviews
 * widget on the home page. The learning pages keep the access rules: a
 * course's classroom and lessons in its own folder, only the first lesson
 * public, the library in the membership folder; every lesson has its
 * questions thread on its own Comments widget and links its neighbours.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_courses_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-courses';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$learn = include $dir . '/learn.php';
	$lessons = array();
	foreach (array_keys($courses) as $key) $lessons[$key] = $course_lessons($key);
	$out = array(
		'figures'     => $figures,
		'subjects'    => $subjects,
		'levels'      => $levels,
		'instructors' => $instructors,
		'courses'     => $courses,
		'lessons'     => $lessons,
		'membership'  => $membership,
		'library'     => $library,
		'pages'       => $public['pages'],
		'widgets'     => $public['widgets'],
		'learn'       => $learn['pages'],
		'learn_widgets' => $learn['widgets'],
		'shared'      => array($account_bar_tree(), $header(), $footer(), $enrol_band(), $notice(), $staff_bar()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_courses_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_courses_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_courses_page_keys()
{
	$keys = array(
		'home', 'courses', 'instructors', 'pricing', 'about', 'faq',
		'library', 'my_courses',
		'enrolment', 'enrolment_view', 'assignment', 'assignment_view', 'my_assignments', 'staff_enrolments', 'staff_assignments', 'privacy',
		'product', 'cart', 'checkout', 'order', 'email_order',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	$d = _test_courses_load();
	foreach ($d['courses'] as $key => $c) {
		$keys[] = 'course_' . $key;
		$keys[] = 'learn_' . $key;
		foreach ($d['lessons'][$key] as $l) $keys[] = 'lesson_' . $l['key'];
	}
	return $keys;
}

function test_courses_data_hangs_together()
{
	$d = _test_courses_load();
	pg_assert_same(4, count($d['figures']), 'four figures');
	$skus = array();
	$folders = array();
	$lesson_keys = array();
	foreach ($d['courses'] as $key => $c) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "course $key: key");
		pg_assert_true(isset($d['subjects'][$c['subject']]), "course $key: known subject");
		pg_assert_true(isset($d['levels'][$c['level']]), "course $key: known level");
		pg_assert_true(isset($d['instructors'][$c['instructor']]), "course $key: known instructor");
		pg_assert_same($c['subject'], $d['instructors'][$c['instructor']]['subject'], "course $key: the instructor teaches its subject");
		pg_assert_true((bool)preg_match('/^COURSE-[A-Z]+$/', $c['sku']), "course $key: SKU");
		pg_assert_same('course_' . $key, $c['folder'], "course $key: its own folder");
		pg_assert_true(isset($c['prices']['USD'], $c['prices']['TRY']) && $c['prices']['USD'] > 0, "course $key: prices");
		pg_assert_true(count($c['outcomes']) >= 3, "course $key: what is learnt");
		pg_assert_true(trim($c['assignment']) !== '', "course $key: assignment");
		pg_assert_true(count($c['modules']) >= 2, "course $key: modules");
		$skus[] = $c['sku'];
		$folders[] = $c['folder'];
		foreach ($d['lessons'][$key] as $n => $l) {
			pg_assert_true(strpos($l['key'], $key . '_') === 0, $l['key'] . ': belongs to its course');
			pg_assert_same($key . '_' . ($n + 1), $l['key'], $l['key'] . ': numbered in order');
			pg_assert_true($l['minutes'] > 0 && trim($l['text']) !== '' && trim($l['module']) !== '', $l['key'] . ': minutes, text, module');
			$lesson_keys[] = $l['key'];
		}
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'course SKUs are unique');
	pg_assert_same(count($folders), count(array_unique($folders)), 'course folders are unique');
	pg_assert_same(count($lesson_keys), count(array_unique($lesson_keys)), 'lesson keys are unique');
	pg_assert_false(in_array($d['membership']['sku'], $skus, true), 'membership SKU apart from the courses');
	pg_assert_true($d['membership']['days'] > 0 && count($d['membership']['items']) > 0, 'membership days and items');
	pg_assert_true(count($d['library']) > 0, 'members\' library');
}

function test_courses_shared_parts_link_template_pages()
{
	$d = _test_courses_load();
	$known = array_flip(_test_courses_page_keys());
	$nodes = array();
	foreach ($d['shared'] as $tree) _test_courses_nodes($tree, $nodes);
	$links = 0;
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $node['props'][$prop], $m);
			foreach ($m[1] as $key) {
				$links++;
				pg_assert_true(isset($known[$key]), "link to a page of the template: $key");
			}
		}
		$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/\b(pev|pe)-[a-z]/', $class) && !preg_match('/\bpe-[0-9]/', $class), 'no class of another template: ' . $class);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// The widgets the finished template defines (this step's and the later
// steps' keys), so a page may place them already.
function _test_courses_widget_keys()
{
	$d = _test_courses_load();
	$keys = array_merge(array_keys($d['widgets']), array_keys($d['learn_widgets']), array('home_posts', 'login_region', 'cart_link', 'newsletter_band', 'buy_membership'));
	foreach (array_keys($d['courses']) as $key) $keys[] = 'buy_' . $key;
	return $keys;
}

function test_courses_public_pages_load()
{
	$d = _test_courses_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['meta_description']), 'page has key, name, title, tree, description');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), $p['key'] . ': page name is an address');
		$keys[] = $p['key'];
	}
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	$expected = array('home', 'courses', 'instructors', 'pricing', 'about', 'faq');
	foreach (array_keys($d['courses']) as $key) $expected[] = 'course_' . $key;
	sort($expected);
	sort($keys);
	pg_assert_same($expected, $keys, 'the public pages of the plan');
}

function test_courses_public_pages_links_anchors_widgets()
{
	$d = _test_courses_load();
	$known = array_flip(_test_courses_page_keys());
	$widgets = array_flip(_test_courses_widget_keys());
	$ids = array();
	$nodes_by_page = array();
	foreach (array_merge($d['pages'], $d['learn']) as $p) {
		$nodes = array();
		_test_courses_nodes($p['tree'], $nodes);
		$nodes_by_page[$p['key']] = $nodes;
		$ids[$p['key']] = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['id'])) {
				pg_assert_false(isset($ids[$p['key']][$node['props']['id']]), $p['key'] . ': id ' . $node['props']['id'] . ' is unique');
				$ids[$p['key']][$node['props']['id']] = true;
			}
		}
	}
	foreach ($nodes_by_page as $page_key => $nodes) {
		foreach ($nodes as $node) {
			if (!empty($node['props']['templateWidget'])) {
				pg_assert_true(isset($widgets[$node['props']['templateWidget']]), "$page_key places a known widget: " . $node['props']['templateWidget']);
			}
			$href = isset($node['props']['href']) ? (string)$node['props']['href'] : '';
			if (!preg_match('/^\{\{page:([a-z0-9_]+)\}\}(?:#([a-z0-9-]+))?/', $href, $m)) continue;
			pg_assert_true(isset($known[$m[1]]), "$page_key links a page of the template: " . $m[1]);
			if (!empty($m[2]) && isset($ids[$m[1]])) {
				pg_assert_true(isset($ids[$m[1]][$m[2]]), "$page_key: anchor #" . $m[2] . ' exists on ' . $m[1]);
			}
		}
	}
}

function test_courses_course_pages_offer_the_ways_in()
{
	$d = _test_courses_load();
	foreach ($d['courses'] as $key => $c) {
		$page = null;
		foreach ($d['pages'] as $p) if ($p['key'] === 'course_' . $key) $page = $p;
		pg_assert_true($page !== null, "course $key has its page");
		$nodes = array();
		_test_courses_nodes($page['tree'], $nodes);
		$hrefs = array();
		$placed = array();
		foreach ($nodes as $n) {
			if (isset($n['props']['href'])) $hrefs[] = (string)$n['props']['href'];
			if (!empty($n['props']['templateWidget'])) $placed[] = $n['props']['templateWidget'];
		}
		pg_assert_true(in_array('{{page:lesson_' . $d['lessons'][$key][0]['key'] . '}}', $hrefs, true), "course $key: free lesson");
		pg_assert_true(in_array('{{page:enrolment}}?course=' . rawurlencode($c['title']), $hrefs, true), "course $key: enrolment request names the course");
		pg_assert_true(in_array('{{page:learn_' . $key . '}}', $hrefs, true), "course $key: classroom");
		pg_assert_true(in_array('buy_' . $key, $placed, true), "course $key: buy box");
	}
	$home = array();
	_test_courses_nodes($d['pages'][0]['tree'], $home);
	$placed = array();
	foreach ($home as $n) if (!empty($n['props']['templateWidget'])) $placed[] = $n['props']['templateWidget'];
	pg_assert_true(in_array('google_reviews', $placed, true), 'home places the Google Reviews widget');
	pg_assert_same('google_reviews', $d['widgets']['google_reviews']['config']['regionType'], 'Google Reviews widget kind');
}

function test_courses_learning_pages_keep_access()
{
	$d = _test_courses_load();
	$by_key = array();
	foreach ($d['learn'] as $p) {
		pg_assert_false(isset($by_key[$p['key']]), $p['key'] . ': unique key');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		$by_key[$p['key']] = $p;
	}
	$names = array();
	foreach (array_merge($d['pages'], $d['learn']) as $p) {
		pg_assert_false(isset($names[$p['name']]), 'page name ' . $p['name'] . ' is unique');
		$names[$p['name']] = true;
	}
	foreach ($d['courses'] as $key => $c) {
		pg_assert_same($c['folder'], $by_key['learn_' . $key]['folder'], "classroom $key in the course folder");
		pg_assert_false($by_key['learn_' . $key]['search'], "classroom $key: not searched");
		foreach ($d['lessons'][$key] as $n => $l) {
			$p = $by_key['lesson_' . $l['key']];
			pg_assert_same($n === 0 ? 'public' : $c['folder'], $p['folder'], $l['key'] . ': folder');
			pg_assert_same($n === 0, $p['search'], $l['key'] . ': only the preview is searched');
			pg_assert_same($n === 0, $p['sitemap'], $l['key'] . ': only the preview is in the sitemap');
			pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], $l['key'] . ': answer e-mail page');
			pg_assert_true($p['comments']['login'], $l['key'] . ': signed-in questions');
			$w = $d['learn_widgets']['questions_' . $l['key']];
			pg_assert_same('lesson_' . $l['key'], $w['page'], $l['key'] . ': its own questions widget');
			pg_assert_same('comments', $w['config']['regionType'], $l['key'] . ': Comments widget');
			$nodes = array();
			_test_courses_nodes($p['tree'], $nodes);
			$hrefs = array();
			$placed = array();
			foreach ($nodes as $node) {
				if (isset($node['props']['href'])) $hrefs[] = (string)$node['props']['href'];
				if (!empty($node['props']['templateWidget'])) $placed[] = $node['props']['templateWidget'];
			}
			pg_assert_same(array('questions_' . $l['key']), array_values(array_intersect($placed, array_keys($d['learn_widgets']))), $l['key'] . ': places its widget once');
			$lessons = $d['lessons'][$key];
			if ($n > 0) pg_assert_true(in_array('{{page:lesson_' . $lessons[$n - 1]['key'] . '}}', $hrefs, true), $l['key'] . ': previous lesson');
			if ($n < count($lessons) - 1) pg_assert_true(in_array('{{page:lesson_' . $lessons[$n + 1]['key'] . '}}', $hrefs, true), $l['key'] . ': next lesson');
			pg_assert_true(in_array($n === 0 ? '{{page:course_' . $key . '}}' : '{{page:learn_' . $key . '}}', $hrefs, true), $l['key'] . ': the way in or back');
		}
	}
	pg_assert_same('members', $by_key['library']['folder'], 'library in the membership folder');
	pg_assert_same('registration', $by_key['my_courses']['folder'], 'My Courses for signed-in visitors');
	pg_assert_same(count($d['learn_widgets']), count(array_unique(array_map(function ($w) { return $w['page']; }, $d['learn_widgets']))), 'one questions widget per page');
}

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
 * of another template.
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

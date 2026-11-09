<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's courses
 * (includes/design_templates/pinegrap-drivingschool/courses.php): the
 * pages and widgets, the Courses collection with a sample per course whose
 * values fit their fields, the list's filters reading what the filter
 * links, the licence cards and pages and the footer send, the home lists
 * the featured courses, the course page opens the enrolment on its name,
 * the lesson request and the list on its licence, every page link names a
 * template page, ids and classes are the template's own, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys and helpers of the template (_test_ds_page_keys(),
// _test_ds_nodes(), _test_ds_filter_links()).
require_once(__DIR__ . '/design_template_drivingschool_test.php');

// The courses' part as the template file reads it: parts.php first, then
// courses.php and the public pages in the same scope. Read once per run.
function _test_dsc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	$part = include $dir . '/courses.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'courses' => $courses,
		'licence_titles' => $licence_titles, 'format_titles' => $format_titles, 'gearbox_titles' => $gearbox_titles,
		'enrolment_course_choices' => $enrolment_course_choices, 'price_text' => $price_text, 'included_text' => $included_text,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// The name, type and options of every form control of a tree.
function _test_dsc_controls($node)
{
	$nodes = array();
	_test_ds_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

function test_drivingschool_courses_pages_and_widgets()
{
	$d = _test_dsc_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('courses', 'course', 'course_new'), array_keys($d['pages']));
	pg_assert_same(array('course_form', 'courses_list', 'home_courses', 'course_detail'), array_keys($d['widgets']));
	foreach (array('courses', 'course') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['course_new']['folder'], 'only the staff add a course');
	pg_assert_true(!empty($d['pages']['course_new']['noindex']), 'the staff page is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	// The home page places home_courses, which this part defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_courses"', json_encode($home['tree']), 'the home places the featured courses');
}

// The form is a collection with a sample per course; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photo is an image and featured is the staff's.
function test_drivingschool_courses_form_and_samples()
{
	$d = _test_dsc_load();
	$form = $d['pages']['course_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['courses']), count($form['sample_records']), 'one sample per course');
	$controls = _test_dsc_controls($d['widgets']['course_form']['tree']);
	$fields = array('name', 'licence', 'format', 'gearbox', 'price', 'lessons', 'duration', 'summary', 'included', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['licence_titles'], $controls['licence']['options'], 'the licences');
	pg_assert_same($d['format_titles'], $controls['format']['options'], 'the formats');
	pg_assert_same($d['gearbox_titles'], $controls['gearbox']['options'], 'the gearboxes');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true(trim($value) !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $name === 'price' ? '' : $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['courses'])[$n];
		pg_assert_same(call_user_func($d['price_text'], $key), $r['price'], "sample $n: its price in the site's currency");
		pg_assert_same(call_user_func($d['included_text'], $key), $r['included'], "sample $n: what it includes, a line each");
		// The course page opens the enrolment with the record's name, an
		// answer of the enrolment's course list.
		pg_assert_true(in_array($r['name'], $d['enrolment_course_choices'], true), "sample $n: an enrolment answer");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads the parameters the filter links, the licence cards and
// pages and the footer send, each an answer of its pick list; the home
// lists the featured courses, three at most.
function test_drivingschool_courses_filters()
{
	$d = _test_dsc_load();
	$params = array();
	foreach ($d['widgets']['courses_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('licence' => 'licence', 'format' => 'format'), $params, 'the list filters licence and format');
	$answers = array('licence' => $d['licence_titles'], 'format' => $d['format_titles']);
	$trees = array($d['pages']['courses']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('licence' => 0, 'format' => 0);
	foreach ($trees as $tree) {
		foreach (_test_ds_filter_links($tree, 'courses') as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_true($seen['licence'] >= count($d['licence_titles']) * 3, 'licence links');
	pg_assert_true($seen['format'] >= count($d['format_titles']), 'format links');
	$c = $d['widgets']['home_courses']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('courses_list', 'home_courses') as $key) {
		pg_assert_same('{{tab:course_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Courses form");
		pg_assert_same('{{tab:course}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the course page");
	}
}

// The course page shows the record or the not-found note, opens the
// enrolment on its name, a lesson request and the list on its licence.
function test_drivingschool_courses_record_page()
{
	$d = _test_dsc_load();
	$nodes = array();
	_test_ds_nodes($d['widgets']['course_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		$b = $n['props']['_bindings'] ?? array();
		if (($b['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($b[$k])) $bound[] = $b[$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:enrol}}', 'params' => array(array('name' => 'course', 'token' => 'name'))),
		array('page_id' => '{{tab:lesson_request}}', 'params' => array(array('name' => 'licence', 'token' => 'licence'))),
		array('page_id' => '{{tab:courses}}', 'params' => array(array('name' => 'licence', 'token' => 'licence'))),
	), $links, 'the enrolment on its name, a lesson and the list on its licence');
	foreach (array('name', 'licence', 'format', 'gearbox', 'price', 'lessons', 'duration', 'summary', 'included', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['course_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_drivingschool_courses_links_ids_and_classes()
{
	$d = _test_dsc_load();
	$known = array_flip(_test_ds_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ds_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_ds_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pfm|pcn|par|ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|plb|pvt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'drv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a course reads "kurs", a licence
// "ehliyet", a lesson "ders".
function test_drivingschool_courses_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/courses.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bcourses?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kurs', 0, 'UTF-8') !== false, "'$key' says kurs");
		if (preg_match('/\blicences?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ehliyet', 0, 'UTF-8') !== false, "'$key' says ehliyet");
		if (preg_match('/\blessons?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}

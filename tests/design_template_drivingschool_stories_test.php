<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's pass stories
 * (includes/design_templates/pinegrap-drivingschool/stories.php): the
 * pages and widgets, the Pass Stories collection with a sample per story
 * whose values fit their fields, the list's licence filter reading what
 * the filter links send, the home lists the featured stories, the story
 * page opens a lesson request on its instructor and the courses on its
 * licence, the cards bind fields the form has, every page link names a
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
// _test_ds_nodes(), _test_ds_filter_links()) and the controls reader of
// the courses test.
require_once(__DIR__ . '/design_template_drivingschool_test.php');
require_once(__DIR__ . '/design_template_drivingschool_courses_test.php');

// The stories' part as the template file reads it: parts.php, courses.php,
// then stories.php in the same scope. Read once per run.
function _test_dss_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	include $dir . '/courses.php';
	$part = include $dir . '/stories.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'stories' => $stories,
		'licence_titles' => $licence_titles, 'try_choices' => $try_choices, 'instructor_names' => $instructor_names,
		'instructor_choices' => $instructor_choices, 'public' => $public);
	return $out;
}

function test_drivingschool_stories_pages_and_widgets()
{
	$d = _test_dss_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('stories', 'story', 'story_new'), array_keys($d['pages']));
	pg_assert_same(array('story_form', 'stories_list', 'home_stories', 'story_detail'), array_keys($d['widgets']));
	foreach (array('stories', 'story') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['story_new']['folder'], 'only the staff add a story');
	pg_assert_true(!empty($d['pages']['story_new']['noindex']), 'the staff page is not indexed');
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
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_stories"', json_encode($home['tree']), 'the home places the featured stories');
}

// The form is a collection with a sample per story; every sample value has
// its field and is filled, a pick list's value is one of its answers, the
// photo is an image and featured is the staff's.
function test_drivingschool_stories_form_and_samples()
{
	$d = _test_dss_load();
	$form = $d['pages']['story_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['stories']), count($form['sample_records']), 'one sample per story');
	$controls = _test_dsc_controls($d['widgets']['story_form']['tree']);
	$fields = array('name', 'licence', 'try', 'instructor', 'lessons', 'words', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['licence_titles'], $controls['licence']['options'], 'the licences');
	pg_assert_same($d['try_choices'], $controls['try']['options'], 'the tries');
	pg_assert_same($d['instructor_names'], $controls['instructor']['options'], 'the instructors');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true(trim($value) !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		// The story page opens a lesson request with the record's
		// instructor, an answer of the request's instructor list.
		pg_assert_true(in_array($r['instructor'], $d['instructor_choices'], true), "sample $n: a lesson request answer");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads ?licence= as the filter links send it; the home lists
// the featured stories, three at most; the cards and the record page bind
// fields the form has.
function test_drivingschool_stories_filters_and_bindings()
{
	$d = _test_dss_load();
	$f = $d['widgets']['stories_list']['config']['filters'];
	pg_assert_same(array(array('field' => 'licence', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'licence')), $f, 'the list filters licence');
	$seen = 0;
	foreach (_test_ds_filter_links($d['pages']['stories']['tree'], 'stories') as $q) {
		pg_assert_same(array('licence'), array_keys($q), 'a licence link');
		pg_assert_true(in_array($q['licence'], $d['licence_titles'], true), "?licence={$q['licence']} is an answer");
		$seen++;
	}
	pg_assert_same(count($d['licence_titles']), $seen, 'a link per licence');
	$c = $d['widgets']['home_stories']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	$fields = array_keys(_test_dsc_controls($d['widgets']['story_form']['tree']));
	foreach (array('stories_list', 'home_stories', 'story_detail') as $key) {
		pg_assert_same('{{tab:story_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Pass Stories form");
		$nodes = array();
		_test_ds_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
				if (!in_array($prop, array('text', 'src', 'alt'), true) || strpos($token, '__') === 0 || $token === 'not_found') continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
}

// The story page shows the record or the not-found note, opens a lesson
// request on its instructor and the courses on its licence.
function test_drivingschool_stories_record_page()
{
	$d = _test_dss_load();
	$nodes = array();
	_test_ds_nodes($d['widgets']['story_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:lesson_request}}', 'params' => array(array('name' => 'instructor', 'token' => 'instructor'))),
		array('page_id' => '{{tab:courses}}', 'params' => array(array('name' => 'licence', 'token' => 'licence'))),
	), $links, 'a lesson with its instructor, the courses of its licence');
	$json = json_encode($d['widgets']['story_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_drivingschool_stories_links_ids_and_classes()
{
	$d = _test_dss_load();
	$known = array_flip(_test_ds_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ds_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_ds_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pdr-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'drv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a pass story reads "başarı hikâyesi" (or
// its plural), a licence "ehliyet", a lesson "ders".
function test_drivingschool_stories_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/stories.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bpass stor(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'başarı hikâye', 0, 'UTF-8') !== false, "'$key' says başarı hikâyesi");
		if (preg_match('/\blicences?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ehliyet', 0, 'UTF-8') !== false, "'$key' says ehliyet");
		if (preg_match('/\blessons?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}

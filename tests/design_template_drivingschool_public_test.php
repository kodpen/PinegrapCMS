<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's public pages
 * (includes/design_templates/pinegrap-drivingschool/pages_public.php): the
 * pages with their keys, a page per licence class under a slug of its
 * own, every page link names a page of the finished template and none a
 * shop page, the widgets the home places are the ones the other parts
 * define, the licence pages open the course list and the lesson request
 * with the class chosen, the licence cards the list on their class, the
 * instructor cards the lesson request on the instructor, search anchors
 * and classes are the template's own, no fixed date, and every literal
 * has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_ds_page_keys()).
require_once(__DIR__ . '/design_template_drivingschool_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_dsp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/pages_public.php';
	$out = array('part' => $public_part, 'licences' => $licences, 'instructors' => $instructors,
		'licence_titles' => $licence_titles, 'instructor_choices' => $instructor_choices);
	return $out;
}

// The pages of this part, by key.
function _test_dsp_pages()
{
	$pages = array();
	foreach (_test_dsp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_drivingschool_public_pages_are_the_plans()
{
	$d = _test_dsp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'licences');
	foreach (array_keys($d['licences']) as $key) $expected[] = 'licence_' . $key;
	$expected = array_merge($expected, array('theory', 'practical_test', 'instructors', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_dsp_pages()), 'the public pages, a page per licence');
	$known = array_flip(_test_ds_page_keys());
	$names = array();
	foreach (_test_dsp_pages() as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_drivingschool_public_links_name_template_pages()
{
	$known = array_flip(_test_ds_page_keys());
	$json = json_encode(_test_dsp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured courses, the featured pass stories, the
// Google reviews and the News, the widgets the courses, the stories and
// the standard parts define; this part defines none.
function test_drivingschool_public_home_widgets()
{
	pg_assert_same(array(), _test_dsp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_dsp_pages() as $key => $p) {
		$nodes = array();
		_test_ds_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_courses', 'home_stories', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A licence's page opens the course list and the lesson request with the
// class chosen, in the words of the Licence list; the licence cards open
// the list on their class; the instructor cards the lesson request on
// their instructor.
function test_drivingschool_public_links_choose()
{
	$d = _test_dsp_load();
	$pages = _test_dsp_pages();
	foreach ($d['licences'] as $key => $l) {
		$tree = $pages['licence_' . $key]['tree'];
		pg_assert_same(array(array('licence' => $l['title'])), _test_ds_filter_links($tree, 'courses'), "licence_$key: the courses of the class");
		pg_assert_same(array(array('licence' => $l['title'])), _test_ds_filter_links($tree, 'lesson_request'), "licence_$key: a lesson request with the class");
		pg_assert_true(in_array($l['title'], $d['licence_titles'], true), "licence_$key: a Licence answer");
	}
	foreach (array('home', 'licences') as $key) {
		$seen = array();
		foreach (_test_ds_filter_links($pages[$key]['tree'], 'courses') as $q) {
			pg_assert_true(isset($q['licence']) && in_array($q['licence'], $d['licence_titles'], true), "$key: a licence card names a Licence answer");
			$seen[$q['licence']] = true;
		}
		pg_assert_same(count($d['licences']), count($seen), "$key: a card per licence");
	}
	$seen = array();
	foreach (_test_ds_filter_links($pages['instructors']['tree'], 'lesson_request') as $q) {
		pg_assert_true(isset($q['instructor']) && in_array($q['instructor'], $d['instructor_choices'], true), 'an instructor link names an Instructor answer');
		$seen[$q['instructor']] = true;
	}
	pg_assert_same(count($d['instructors']), count($seen), 'a lesson link per instructor');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_drivingschool_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_dsp_pages() as $key => $p) {
		$nodes = array();
		_test_ds_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pfm|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|plb|pvt)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "$key: no faded text class $c");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'drv-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old offer
// a year later.
function test_drivingschool_public_no_fixed_dates()
{
	$json = json_encode(_test_dsp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a licence reads "ehliyet", an instructor
// "eğitmen", a lesson "ders".
function test_drivingschool_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		// Search topics and address slugs (ASCII) are left out of the word check.
		if (!isset($map[$key]) || strpos($key, ',') !== false || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/^Licences?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ehliyet', 0, 'UTF-8') !== false, "'$key' says ehliyet");
		if (preg_match('/\binstructors?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eğitmen', 0, 'UTF-8') !== false, "'$key' says eğitmen");
		if (preg_match('/\blessons?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}

// The home and the sector's pages are built on the palette blocks (the
// Driving School family and the numbered steps), with no stock band: no
// framed story band, figures band or ask band. Their rows rise in through
// the interactions, the home's opening as the page loads.
function test_drivingschool_public_signature_pages_place_the_blocks()
{
	$d = _test_dsp_load();
	$pages = _test_dsp_pages();
	$signature = array('home', 'licences', 'theory', 'practical_test');
	foreach (array_keys($d['licences']) as $key) $signature[] = 'licence_' . $key;
	$facts = array();
	foreach ($signature as $key) {
		$nodes = array();
		_test_ds_nodes($pages[$key]['tree'], $nodes);
		$f = array('labels' => array(), 'classes' => array(), 'fx' => array(), 'scopes' => array(), 'hidden_signs' => 0);
		foreach ($nodes as $n) {
			$p = $n['props'];
			if (isset($p['_label'])) $f['labels'][] = $p['_label'];
			$cls = preg_split('/\s+/', (string)($p['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
			$f['classes'] = array_merge($f['classes'], $cls);
			if (isset($p['_fx'])) $f['fx'][] = $p['_fx'];
			foreach ((array)($p['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'scope') $f['scopes'][] = $a['value'];
				if ($a['name'] === 'aria-hidden' && in_array('pg-drv-sign', $cls, true)) $f['hidden_signs']++;
			}
		}
		foreach (array('Picture Frame', 'Figures', 'Write to Us') as $stock) {
			pg_assert_false(in_array(lang($stock), $f['labels'], true), "$key: no stock band ($stock)");
		}
		pg_assert_true(count($f['fx']) > 0, "$key: something rises in");
		foreach ($f['fx'] as $fx) {
			pg_assert_same('slide-up', $fx['effect'] ?? '', "$key: an entrance");
			pg_assert_true(in_array($fx['trigger'] ?? '', array('view', 'load'), true), "$key: played on view or load");
		}
		$facts[$key] = $f;
	}

	$count = function ($key, $class) use (&$facts) { return count(array_keys($facts[$key]['classes'], $class, true)); };
	$n = count($d['licences']);
	pg_assert_same(2 * $n, $count('home', 'pg-drv-plate'), 'home: a plate per class in the opening and on the board');
	pg_assert_same($n, $count('licences', 'pg-drv-plate'), 'licences: the board, a plate per class');
	pg_assert_true(in_array('load', array_column($facts['home']['fx'], 'trigger'), true), 'home: the opening rises as the page loads');
	foreach (array('home', 'theory') as $key) {
		pg_assert_same(6, $count($key, 'pg-drv-sign'), "$key: a road sign per theory topic");
		pg_assert_same(6, $facts[$key]['hidden_signs'], "$key: the signs are decoration");
	}
	foreach (array('home', 'licences') as $key) {
		pg_assert_same(4, count(array_keys($facts[$key]['labels'], lang('Step Card'), true)), "$key: the four numbered steps");
	}
	pg_assert_same(1, $count('practical_test', 'pg-drv-sheet'), 'practical_test: the examiner\'s sheet');
	pg_assert_same(array('col', 'col', 'col', 'col', 'row', 'row', 'row', 'row', 'row', 'row'), $facts['practical_test']['scopes'], 'practical_test: the fault table names its heads');
	$home = json_encode($pages['home']['tree']);
	foreach (array_keys($d['licences']) as $key) {
		pg_assert_same(2, substr_count($home, '{{page:licence_' . $key . '}}'), "home: the plate and the card open licence_$key");
	}
}

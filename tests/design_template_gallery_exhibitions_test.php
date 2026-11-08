<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's exhibitions
 * (includes/design_templates/pinegrap-gallery/exhibitions.php): the pages
 * and widgets, the Exhibitions collection with a sample per exhibition
 * whose values fit their fields and whose titles the works name, the
 * state filter reading what the filter row, the notice and the footer
 * send, the home's exhibitions now on and coming, the exhibition's page
 * listing its works and opening a private viewing on its title, every page
 * link naming a template page, ids and classes that are the template's
 * own, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys (_test_gl_page_keys()), the node walker (_test_gl_nodes())
// and the helpers of the artists test (_test_gla_controls(),
// _test_gla_queries(), _test_gla_page_links()).
require_once __DIR__ . '/design_template_gallery_test.php';
require_once __DIR__ . '/design_template_gallery_artists_test.php';

// parts.php, cards.php, the public pages, artists.php and exhibitions.php
// in one scope, as the template file reads them. Read once per run.
function _test_glx_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$artists_part = include $dir . '/artists.php';
	$part = include $dir . '/exhibitions.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$works_form = null;
	foreach ($artists_part['pages'] as $p) {
		if ($p['key'] === 'work_new') $works_form = $p['form'];
	}
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'home' => $home, 'works_form' => $works_form,
		'exhibitions' => $exhibitions, 'statuses' => $status_titles, 'exhibition_statuses' => $exhibition_statuses,
		'exhibition_choices' => $exhibition_choices, 'notice' => $notice(), 'footer' => $footer());
	return $out;
}

function test_gallery_exhibitions_pages_and_widgets()
{
	$d = _test_glx_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('exhibitions', 'exhibition', 'exhibition_new'), array_keys($d['pages']));
	pg_assert_same(array('exhibition_form', 'exhibitions_list', 'home_exhibitions', 'exhibition_works', 'exhibition_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_gl_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($key === 'exhibition_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	pg_assert_true(!empty($d['pages']['exhibition_new']['noindex']), 'the staff page is not indexed');
	foreach ($d['widgets'] as $key => $w) {
		$tree = $w['page'] === 'home' ? $d['home'] : $d['pages'][$w['page']]['tree'];
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($tree), "$key is placed on its page");
	}
}

// The form is a collection with a sample per exhibition; every value is
// filled, the state is an answer of the list, and the works name the
// samples' titles.
function test_gallery_exhibitions_form_and_samples()
{
	$d = _test_glx_load();
	$form = $d['pages']['exhibition_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['exhibitions']), count($form['sample_records']), 'one sample per exhibition');
	$controls = _test_gla_controls($d['widgets']['exhibition_form']['tree']);
	$fields = array('title', 'status', 'artists', 'dates', 'opening', 'summary', 'curator_text', 'photo_1', 'photo_2', 'photo_3');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['cf']['kind'] ?? '', "$f is an image");
	pg_assert_same('title', $controls['title']['cf']['rss_field'] ?? '', 'the title is the title');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the three states');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	$titles = array();
	$states = array();
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) pg_assert_true($value !== '', "sample $n: $name filled");
		pg_assert_true(in_array($r['status'], $d['statuses'], true), "sample $n: a state of the list");
		$titles[] = $r['title'];
		$states[$r['status']] = true;
	}
	pg_assert_same(count($d['statuses']), count($states), 'a sample in every state');
	foreach ($d['works_form']['sample_records'] as $n => $w) {
		if (isset($w['exhibition'])) pg_assert_true(in_array($w['exhibition'], $titles, true), "work $n names a sample exhibition");
	}
	foreach (array_slice($d['exhibition_choices'], 0, -1) as $t) pg_assert_true(in_array($t, $titles, true), "the viewing offers '$t', a sample");
}

// The list reads ?status=, which the filter row, the notice and the footer
// send with a state's own words; the home leaves the past out; the
// exhibition's page lists its works.
function test_gallery_exhibitions_filters()
{
	$d = _test_glx_load();
	pg_assert_same(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'status')),
		$d['widgets']['exhibitions_list']['config']['filters'], 'the list by state');
	pg_assert_same(array(array('field' => 'status', 'operator' => 'is not equal to', 'value' => $d['exhibition_statuses']['past'], 'dynamic_value' => '')),
		$d['widgets']['home_exhibitions']['config']['filters'], 'the home: now on and coming');
	pg_assert_same(array(array('field' => 'exhibition', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => '')),
		$d['widgets']['exhibition_works']['config']['filters'], 'the exhibition\'s own works');
	pg_assert_same('{{tab:work_new}}', $d['widgets']['exhibition_works']['config']['custom_form_page_id'], 'works from the Works form');
	foreach (array($d['pages']['exhibitions']['tree'], $d['notice'], $d['footer']) as $n => $tree) {
		foreach (_test_gla_queries($tree, 'exhibitions') as $q) {
			pg_assert_same(array('status'), array_keys($q), "tree $n: a state link");
			pg_assert_true(in_array($q['status'], $d['statuses'], true), "tree $n: '{$q['status']}' is an answer");
		}
	}
	pg_assert_same(count($d['statuses']), count(_test_gla_queries($d['pages']['exhibitions']['tree'], 'exhibitions')), 'a link per state');
}

// The exhibition's page reads its fields and opens a private viewing on
// its title.
function test_gallery_exhibitions_record_page()
{
	$d = _test_glx_load();
	$w = $d['widgets']['exhibition_detail'];
	pg_assert_same('form_item_view', $w['config']['regionType'], 'a record view');
	pg_assert_same('public', $w['config']['access_control'], 'open to anyone');
	pg_assert_same(array('viewing' => array('exhibition' => 'title')), _test_gla_page_links($w['tree']), 'the viewing on this exhibition');
	$json = json_encode($w['tree']);
	foreach (array('title', 'status', 'artists', 'dates', 'opening', 'summary', 'curator_text') as $t) pg_assert_contains('"text":"' . $t . '"', $json, "reads $t");
	foreach (array('record_not_found', 'can_edit', '"photo_1"', '"photo_2"', '"photo_3"') as $s) pg_assert_contains($s, $json, "has $s");
	foreach (array('exhibitions_list', 'home_exhibitions') as $key) {
		pg_assert_same('{{tab:exhibition}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the exhibition");
	}
}

function test_gallery_exhibitions_links_classes_and_dates()
{
	$d = _test_glx_load();
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$json = json_encode($trees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$known = array_flip(_test_gl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json . json_encode($d['widgets']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_gl_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
	$nodes = array();
	foreach ($trees as $t) _test_gl_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false(strpos($c, 'ratio-') === 0 && !in_array($c, array('ratio-1x1', 'ratio-4x3', 'ratio-16x9', 'ratio-21x9'), true), "a Bootstrap ratio ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	$samples = json_encode($d['pages']['exhibition_new']['form'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	foreach (array($json, $samples) as $text) {
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $text), 'no year in the sample');
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month in the sample');
	}
}

// Every literal has its Turkish; an exhibition is "sergi".
function test_gallery_exhibitions_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/exhibitions.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 30, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bexhibitions?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'sergi', 0, 'UTF-8') !== false, "'$key' says sergi");
	}
}

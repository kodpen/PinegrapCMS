<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music Artist design template's public pages
 * (includes/design_templates/pinegrap-music/pages_public.php): the four
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the home leads with the newest single, every
 * member, quote and video is shown, search anchors and classes are the
 * template's own, no fixed date, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_music_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_mup_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/pages_public.php';
	$out = array('part' => $public_part, 'releases' => $releases, 'members' => $members,
		'press_quotes' => $press_quotes, 'videos' => $videos);
	return $out;
}

// Every node of a tree, depth first.
function _test_mup_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mup_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_mup_pages()
{
	$pages = array();
	foreach (_test_mup_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The texts of a page's tree.
function _test_mup_texts($tree)
{
	$nodes = array();
	_test_mup_nodes($tree, $nodes);
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	return $texts;
}

function test_music_public_pages_are_the_plans()
{
	$part = _test_mup_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'videos', 'faq'), array_keys(_test_mup_pages()), 'the four public pages');
	foreach (_test_mup_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_music_public_links_name_template_pages()
{
	$known = array_flip(_test_mu_page_keys());
	$json = json_encode(_test_mup_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_mu_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the next shows, the featured releases and the news, the
// widgets the releases and shows and the standard parts define; this part
// defines none.
function test_music_public_home_widgets()
{
	pg_assert_same(array(), _test_mup_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_mup_pages() as $key => $p) {
		$nodes = array();
		_test_mup_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_shows', 'home_releases', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home leads with the newest single (a featured one), names it once
// as its h1, and shows every video and press quote; the about page shows
// every member and every quote.
function test_music_public_pages_show_the_data()
{
	$d = _test_mup_load();
	$pages = _test_mup_pages();
	$nodes = array();
	_test_mup_nodes($pages['home']['tree'], $nodes);
	$h1 = array();
	foreach ($nodes as $n) {
		if (($n['props']['tag'] ?? '') === 'h1') $h1[] = $n['props']['text'];
	}
	pg_assert_same(array($d['releases']['rooftop']['title']), $h1, 'the home leads with the newest single');
	pg_assert_same('single', $d['releases']['rooftop']['format']);
	$home = _test_mup_texts($pages['home']['tree']);
	foreach ($d['videos'] as $n => $v) pg_assert_true(in_array($v['title'], $home, true), "home: video $n");
	foreach ($d['press_quotes'] as $n => $q) pg_assert_true(in_array($q['quote'], $home, true), "home: quote $n");
	$about = _test_mup_texts($pages['about']['tree']);
	foreach ($d['members'] as $key => $m) pg_assert_true(in_array($m['name'], $about, true), "about: member $key");
	foreach ($d['press_quotes'] as $n => $q) pg_assert_true(in_array($q['quote'], $about, true), "about: quote $n");
	$videos = _test_mup_texts($pages['videos']['tree']);
	foreach ($d['videos'] as $n => $v) pg_assert_true(in_array($v['title'], $videos, true), "videos: video $n");
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_music_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_mup_pages() as $key => $p) {
		$nodes = array();
		_test_mup_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'mu-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
	pg_assert_contains('pmu-hero', json_encode(_test_mup_pages()['home']['tree']), 'the home hero');
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_music_public_no_fixed_dates()
{
	$json = json_encode(_test_mup_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a show reads "konser", the tour "turne".
function test_music_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ss]hows?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser', 0, 'UTF-8') !== false, "'$key' says konser");
		if (preg_match('/\bTour\b/', $key)) pg_assert_true(mb_stripos($tr, 'turne', 0, 'UTF-8') !== false, "'$key' says turne");
	}
}

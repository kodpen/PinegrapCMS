<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's public pages
 * (includes/design_templates/pinegrap-podcast/pages_public.php): the five
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the home leads with the newest featured
 * episode, every host and figure is shown, search anchors and classes are
 * the template's own, no fixed date, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_ppdp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/pages_public.php';
	$out = array('part' => $public_part, 'episodes' => $episodes, 'podcasts' => $podcasts, 'hosts' => $hosts,
		'audience' => $audience, 'apps' => $apps);
	return $out;
}

// The pages of this part, by key.
function _test_ppdp_pages()
{
	$pages = array();
	foreach (_test_ppdp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The nodes and the texts of a page's tree.
function _test_ppdp_texts($tree)
{
	$nodes = array();
	_test_ppd_nodes($tree, $nodes);
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	return $texts;
}

function test_podcast_public_pages_are_the_plans()
{
	$part = _test_ppdp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'hosts', 'listen', 'faq'), array_keys(_test_ppdp_pages()), 'the five public pages');
	$names = array();
	foreach (_test_ppdp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name once");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_podcast_public_links_name_template_pages()
{
	$known = array_flip(_test_ppd_page_keys());
	$json = json_encode(_test_ppdp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_ppd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the newest episodes, the featured podcasts and the news,
// the widgets the collections and the standard parts define; this part
// defines none.
function test_podcast_public_home_widgets()
{
	pg_assert_same(array(), _test_ppdp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_ppdp_pages() as $key => $p) {
		$nodes = array();
		_test_ppd_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_episodes', 'home_podcasts', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home leads with the newest featured episode, once as its h1; the
// home and the about page show every figure; the about and hosts pages
// show every host; the listen page names every app.
function test_podcast_public_pages_show_the_data()
{
	$d = _test_ppdp_load();
	$pages = _test_ppdp_pages();
	$newest = null;
	foreach ($d['episodes'] as $key => $e) {
		if ($e['featured'] && ($newest === null || $e['days'] < $d['episodes'][$newest]['days'])) $newest = $key;
	}
	$nodes = array();
	_test_ppd_nodes($pages['home']['tree'], $nodes);
	$h1 = array();
	foreach ($nodes as $n) {
		if (($n['props']['tag'] ?? '') === 'h1') $h1[] = $n['props']['text'];
	}
	pg_assert_same(array($d['episodes'][$newest]['title']), $h1, 'the home leads with the newest featured episode');
	foreach (array('home', 'about') as $key) {
		$texts = _test_ppdp_texts($pages[$key]['tree']);
		foreach ($d['audience'] as $n => $f) pg_assert_true(in_array($f['label'], $texts, true), "$key: figure $n");
	}
	foreach (array('about', 'hosts') as $key) {
		$json = json_encode($pages[$key]['tree']);
		foreach ($d['hosts'] as $h => $host) pg_assert_contains(json_encode($host['name']), $json, "$key: host $h");
	}
	$listen = json_encode($pages['listen']['tree']);
	foreach ($d['apps'] as $a) pg_assert_contains(json_encode($a['name']), $listen, 'listen: ' . $a['name']);
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_podcast_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_ppdp_pages() as $key => $p) {
		$nodes = array();
		_test_ppd_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'pd-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
	pg_assert_contains('ppd-hero', json_encode(_test_ppdp_pages()['home']['tree']), 'the home hero');
}

// The sample is never dated: a year or a month would read as an old
// season a year later.
function test_podcast_public_no_fixed_dates()
{
	$json = json_encode(_test_ppdp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; an episode reads "bölüm", a host
// "sunucu", a guest "konuk".
function test_podcast_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "'$key' says bölüm");
		if (preg_match('/\b[Hh]osts?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sunucu', 0, 'UTF-8') !== false, "'$key' says sunucu");
		if (preg_match('/\b[Gg]uests?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konuk', 0, 'UTF-8') !== false, "'$key' says konuk");
	}
}

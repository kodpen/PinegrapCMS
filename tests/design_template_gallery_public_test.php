<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's public pages
 * (includes/design_templates/pinegrap-gallery/pages_public.php): the pages
 * of the plan in the public folder and without the shop, links that name
 * template pages and no shop page, the widgets only the home places, the
 * filter and form links that use the lists' own answers, the visit page
 * with the address, hours and private viewing, the template's ids and
 * classes, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template (_test_gl_page_keys(),
// _test_gl_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_gallery_test.php';

// parts.php, cards.php and the public pages in one scope, as the template
// file reads them. Read once per run.
function _test_glp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/cards.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/pages_public.php';
	$out = array('part' => $public_part, 'gallery' => $gallery, 'opening_hours' => $opening_hours, 'advisory' => $advisory,
		'mediums' => $mediums, 'enquiry_choices' => $enquiry_choices, 'buying_steps' => $buying_steps);
	return $out;
}

// The pages of this part, by key.
function _test_glp_pages()
{
	$pages = array();
	foreach (_test_glp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_glp_queries($tree, $page)
{
	$nodes = array();
	_test_gl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_gallery_public_pages_are_the_plans()
{
	$part = _test_glp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'visit', 'advisory', 'faq'), array_keys(_test_glp_pages()), 'the public pages');
	$known = array_flip(_test_gl_page_keys());
	$names = array();
	foreach (_test_glp_pages() as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
		// Each page ends with the private viewing band above the footer.
		pg_assert_contains('viewing_band', json_encode($p['tree']), "$key: the viewing band");
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_gallery_public_links_name_template_pages()
{
	$known = array_flip(_test_gl_page_keys());
	$json = json_encode(_test_glp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_gl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the exhibitions, the artists and the articles, the
// widgets the later parts define; this part defines none.
function test_gallery_public_home_widgets()
{
	pg_assert_same(array(), _test_glp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_glp_pages() as $key => $p) {
		$nodes = array();
		_test_gl_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_exhibitions', 'home_artists', 'home_posts')), $widgets, 'only the home places widgets');
}

// Filter and form links use the lists' own answers: the artists by a
// medium, the enquiry by a kind; the advisory page carries every service
// and every buying step.
function test_gallery_public_links_use_the_answers()
{
	$d = _test_glp_load();
	$pages = _test_glp_pages();
	$mediums = array();
	foreach ($pages as $key => $p) {
		foreach (_test_glp_queries($p['tree'], 'artists') as $q) {
			pg_assert_true(in_array($q['medium'], $d['mediums'], true), "$key: filters the artists by a medium");
			if ($key === 'home') $mediums[$q['medium']] = true;
		}
		foreach (_test_glp_queries($p['tree'], 'enquire') as $q) {
			pg_assert_true(in_array($q['kind'], $d['enquiry_choices'], true), "$key: names a Kind answer");
		}
	}
	pg_assert_same(count($d['mediums']), count($mediums), 'the home links every medium');
	$advisory = json_encode($pages['advisory']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['advisory'] as $key => $s) pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $advisory, "advisory $key listed");
	foreach ($d['buying_steps'] as $s) pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $advisory, 'a buying step');
	pg_assert_same(count($d['advisory']), count(_test_glp_queries($pages['advisory']['tree'], 'enquire')), 'an enquiry per service');
}

// The visit page gives the address, the phone, the hours and the way to a
// private viewing.
function test_gallery_public_visit_page()
{
	$d = _test_glp_load();
	$json = json_encode(_test_glp_pages()['visit']['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains(json_encode($d['gallery']['address'], JSON_UNESCAPED_UNICODE), $json, 'the address');
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['gallery']['phone']), $json, 'calls the gallery');
	foreach ($d['opening_hours'] as $h) pg_assert_contains(json_encode($h['days'], JSON_UNESCAPED_UNICODE), $json, 'the hours');
	pg_assert_contains('{{page:viewing}}', $json, 'a private viewing');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no faded text.
function test_gallery_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_glp_pages() as $key => $p) {
		$nodes = array();
		_test_gl_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(strpos($c, 'ratio-') === 0 && !in_array($c, array('ratio-1x1', 'ratio-4x3', 'ratio-16x9', 'ratio-21x9'), true), "$key: a Bootstrap ratio ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'gl-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	pg_assert_true(count($ids) > 8, 'search anchors found');
}

function test_gallery_public_no_fixed_dates()
{
	$json = json_encode(_test_glp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The map link carries the address URL-encoded ("%20" before a number).
	$json = preg_replace('#https://www\.openstreetmap\.org/search\?query=[^"]*#', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the pages has its Turkish; an artist is "sanatçı", a
// gallery "galeri".
function test_gallery_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bartists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sanatçı', 0, 'UTF-8') !== false, "'$key' says sanatçı");
		if (preg_match('/\bgallery\b/i', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
	}
}

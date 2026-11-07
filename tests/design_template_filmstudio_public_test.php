<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Film Studio design template's public pages
 * (includes/design_templates/pinegrap-filmstudio/pages_public.php): the
 * pages with their keys, a page per service under a slug of its own, every
 * page link names a page of the finished template and none a shop page,
 * the widgets the home places are the ones the other parts define, the
 * home plays the showreel, the service pages open the enquiry and the work
 * list with the service chosen, the industry tiles the list on their
 * industry, the studio page asks for each space, search anchors and
 * classes are the template's own, no fixed date, and every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_fmp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/pages_public.php';
	$out = array('part' => $public_part, 'services' => $services, 'industries' => $industries, 'spaces' => $spaces,
		'enquiry_service_choices' => $enquiry_service_choices, 'service_titles' => $service_titles,
		'industry_titles' => $industry_titles, 'space_titles' => $space_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_fmp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fmp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_fmp_pages()
{
	$pages = array();
	foreach (_test_fmp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_fmp_queries($tree, $page)
{
	$nodes = array();
	_test_fmp_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $page . '}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_filmstudio_public_pages_are_the_plans()
{
	$d = _test_fmp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'services');
	foreach (array_keys($d['services']) as $key) $expected[] = 'service_' . $key;
	$expected = array_merge($expected, array('process', 'studio', 'crew', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_fmp_pages()), 'the public pages, a page per service');
	$names = array();
	foreach (_test_fmp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_filmstudio_public_links_name_template_pages()
{
	$keys = array(
		'home', 'services', 'process', 'studio', 'crew', 'about', 'faq',
		'projects', 'project', 'enquiry', 'my_enquiries', 'studio_hire', 'privacy',
		'careers', 'job', 'application', 'my_applications',
		'contact', 'newsletter', 'blog', 'blog_post', 'search', 'my_account', 'login', 'register',
	);
	foreach (array_keys(_test_fmp_load()['services']) as $key) $keys[] = 'service_' . $key;
	$known = array_flip($keys);
	$json = json_encode(_test_fmp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured projects, the Google reviews and the
// Journal, the widgets the projects and the standard parts define; this
// part defines none. The home plays the showreel.
function test_filmstudio_public_home_widgets_and_showreel()
{
	pg_assert_same(array(), _test_fmp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	$videos = array();
	foreach (_test_fmp_pages() as $key => $p) {
		$nodes = array();
		_test_fmp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
			if (($n['props']['tag'] ?? '') === 'video') $videos[$key] = true;
		}
	}
	pg_assert_same(array('home' => array('home_projects', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
	pg_assert_true(isset($videos['home']), 'the home plays the showreel');
}

// A service's page opens the enquiry and the work list with the service
// chosen, in the words of the Service list; an industry tile opens the
// work list on its industry; the studio page asks for each space.
function test_filmstudio_public_links_choose()
{
	$d = _test_fmp_load();
	$pages = _test_fmp_pages();
	foreach ($d['services'] as $key => $s) {
		$tree = $pages['service_' . $key]['tree'];
		pg_assert_same(array(array('service' => $s['title'])), _test_fmp_queries($tree, 'enquiry'), "service_$key: one enquiry link with the service");
		pg_assert_true(in_array($s['title'], $d['enquiry_service_choices'], true), "service_$key: an enquiry answer");
		pg_assert_same(array(array('service' => $s['title'])), _test_fmp_queries($tree, 'projects'), "service_$key: the work of the service");
		pg_assert_true(in_array($s['title'], $d['service_titles'], true), "service_$key: a Service answer");
	}
	$seen = array();
	foreach (_test_fmp_queries($pages['services']['tree'], 'projects') as $q) {
		pg_assert_true(isset($q['industry']) && in_array($q['industry'], $d['industry_titles'], true), 'an industry tile names an Industry answer');
		$seen[$q['industry']] = true;
	}
	pg_assert_same(count($d['industries']), count($seen), 'a tile per industry');
	$spaces = array();
	foreach (_test_fmp_queries($pages['studio']['tree'], 'studio_hire') as $q) {
		pg_assert_true(isset($q['space']) && in_array($q['space'], $d['space_titles'], true), 'a hire link names a Space answer');
		$spaces[$q['space']] = true;
	}
	pg_assert_same(count($d['spaces']), count($spaces), 'a hire link per space');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_filmstudio_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_fmp_pages() as $key => $p) {
		$nodes = array();
		_test_fmp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'fm-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old film a
// year later.
function test_filmstudio_public_no_fixed_dates()
{
	$json = json_encode(_test_fmp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a project reads "proje", an enquiry
// "talep", the crew "ekip".
function test_filmstudio_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/', $key) && strpos($key, '-') === false) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		if (preg_match('/\benquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/\bthe crew\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ekip', 0, 'UTF-8') !== false, "'$key' says ekip");
	}
}

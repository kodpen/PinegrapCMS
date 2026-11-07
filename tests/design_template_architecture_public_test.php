<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Architecture & Interiors Studio design template's public
 * pages (includes/design_templates/pinegrap-architecture/pages_public.php):
 * the pages the plan names with a page per service, addresses that are
 * slugs of their own, links only to pages the finished template carries,
 * the home's widgets, a service page that opens the enquiry and the
 * consultation with the service chosen, type and place links in the
 * words of the Projects form's lists, the template's own ids and classes,
 * no fixed date in the sample and every literal in Turkish.
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
function _test_aip_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/pages_public.php';
	$out = array('part' => $public_part, 'services' => $services, 'project_types' => $project_types, 'locations' => $locations,
		'enquiry_service_choices' => $enquiry_service_choices, 'type_titles' => $type_titles, 'location_titles' => $location_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_aip_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_aip_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_aip_pages()
{
	$pages = array();
	foreach (_test_aip_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_aip_queries($tree, $page)
{
	$nodes = array();
	_test_aip_nodes($tree, $nodes);
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

function test_architecture_public_pages_are_the_plans()
{
	$d = _test_aip_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'services');
	foreach (array_keys($d['services']) as $key) $expected[] = 'service_' . $key;
	$expected = array_merge($expected, array('studio', 'team', 'process', 'awards', 'faq'));
	pg_assert_same($expected, array_keys(_test_aip_pages()), 'the public pages, a page per service');
	$names = array();
	foreach (_test_aip_pages() as $key => $p) {
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
function test_architecture_public_links_name_template_pages()
{
	$keys = array(
		'home', 'services', 'studio', 'team', 'process', 'awards', 'faq',
		'projects', 'project', 'enquiry', 'my_enquiries', 'consultation', 'privacy',
		'careers', 'job', 'application', 'my_applications', 'staff', 'project_new', 'staff_enquiries',
		'staff_consultations', 'job_new', 'staff_applications', 'blog_new', 'inbox',
		'contact', 'newsletter', 'blog', 'blog_post', 'search', 'my_account', 'login', 'register',
	);
	foreach (array_keys(_test_aip_load()['services']) as $key) $keys[] = 'service_' . $key;
	$known = array_flip($keys);
	$json = json_encode(_test_aip_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured projects, the Google reviews and the
// journal, the widgets the projects and the standard parts define; this
// part defines none.
function test_architecture_public_home_widgets()
{
	pg_assert_same(array(), _test_aip_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_aip_pages() as $key => $p) {
		$nodes = array();
		_test_aip_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_projects', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A service's page opens the enquiry and the consultation with the service
// chosen, in the words of the Service list; the type tiles and the place
// buttons open the project list in the words of the Type and Location
// lists, one tile per type and one button per place.
function test_architecture_public_links_choose()
{
	$d = _test_aip_load();
	$pages = _test_aip_pages();
	foreach ($d['services'] as $key => $s) {
		$tree = $pages['service_' . $key]['tree'];
		pg_assert_same(array(array('service' => $s['title'])), _test_aip_queries($tree, 'enquiry'), "service_$key: one enquiry link with the service");
		pg_assert_same(array(array('service' => $s['title'])), _test_aip_queries($tree, 'consultation'), "service_$key: one consultation link with the service");
		pg_assert_true(in_array($s['title'], $d['enquiry_service_choices'], true), "service_$key: an enquiry answer");
	}
	$types = array();
	$places = array();
	foreach (_test_aip_queries($pages['services']['tree'], 'projects') as $q) {
		pg_assert_same(1, count($q), 'one filter per link');
		if (isset($q['type'])) {
			pg_assert_true(in_array($q['type'], $d['type_titles'], true), 'a type tile names a Type answer');
			$types[$q['type']] = true;
		} else {
			pg_assert_true(isset($q['location']) && in_array($q['location'], $d['location_titles'], true), 'a place names a Location answer');
			$places[$q['location']] = true;
		}
	}
	pg_assert_same(count($d['project_types']), count($types), 'a tile per type');
	pg_assert_same(count($d['locations']), count($places), 'a button per place');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist, and no
// faded text.
function test_architecture_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_aip_pages() as $key => $p) {
		$nodes = array();
		_test_aip_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'ai-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// project a year later.
function test_architecture_public_no_fixed_dates()
{
	$json = json_encode(_test_aip_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a project reads "proje", an enquiry
// "talep", a consultation "görüşme".
function test_architecture_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/i', $key) && strpos($key, '-') === false) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		if (preg_match('/\benquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/\bconsultation\b/i', $key)) pg_assert_true((bool)preg_match('/görüşme|danışma/iu', $tr), "'$key' says görüşme");
	}
}

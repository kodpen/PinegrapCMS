<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's public pages
 * (includes/design_templates/pinegrap-recruitment/pages_public.php): the
 * plan's pages with a page per sector, each in the public folder with its
 * own address and a meta description that fits a result line, links only
 * to template pages, the home placing the featured jobs, the reviews and
 * the guides, a sector's page opening the job list and the staff request
 * with the sector chosen and showing its own consultant, the cities and
 * sectors linking their filtered lists, ids and classes the template's
 * own, no fixed date, and the Turkish of every literal.
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
function _test_rcp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/pages_public.php';
	$out = array('part' => $public_part, 'sectors' => $sectors, 'cities' => $cities, 'consultants' => $consultants,
		'sector_titles' => $sector_titles, 'city_titles' => $city_titles, 'enquiry_sector_choices' => $enquiry_sector_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_rcp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rcp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rcp_pages()
{
	$pages = array();
	foreach (_test_rcp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_rcp_queries($tree, $page)
{
	$nodes = array();
	_test_rcp_nodes($tree, $nodes);
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

function test_recruitment_public_pages_are_the_plans()
{
	$d = _test_rcp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'sectors');
	foreach (array_keys($d['sectors']) as $key) $expected[] = 'sector_' . $key;
	$expected = array_merge($expected, array('cities', 'candidates', 'employers', 'about', 'team', 'faq'));
	pg_assert_same($expected, array_keys(_test_rcp_pages()), 'the public pages, a page per sector');
	$names = array();
	foreach (_test_rcp_pages() as $key => $p) {
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
function test_recruitment_public_links_name_template_pages()
{
	// This file sorts before design_template_recruitment_test.php, so the
	// keys are listed here: the pages these ones may link.
	$keys = array(
		'home', 'sectors', 'cities', 'candidates', 'employers', 'about', 'team', 'faq',
		'jobs', 'job', 'apply', 'my_applications', 'hire', 'my_enquiries', 'privacy',
		'contact', 'newsletter', 'blog', 'blog_post', 'search', 'my_account', 'login', 'register',
	);
	foreach (array_keys(_test_rcp_load()['sectors']) as $key) $keys[] = 'sector_' . $key;
	$known = array_flip($keys);
	$json = json_encode(_test_rcp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured jobs, the Google reviews and the guides,
// the widgets the jobs and the standard parts define; this part defines
// none.
function test_recruitment_public_home_widgets()
{
	pg_assert_same(array(), _test_rcp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_rcp_pages() as $key => $p) {
		$nodes = array();
		_test_rcp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_jobs', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A sector's page opens the job list and the staff request with the sector
// chosen, in the words of their lists, shows its own consultant and the
// jobs by city; the sectors page links every sector's jobs, the cities
// page every city's.
function test_recruitment_public_links_choose()
{
	$d = _test_rcp_load();
	$pages = _test_rcp_pages();
	foreach ($d['sectors'] as $key => $s) {
		$tree = $pages['sector_' . $key]['tree'];
		pg_assert_same(array(array('sector' => $s['title'])), _test_rcp_queries($tree, 'hire'), "sector_$key: one staff request with the sector");
		pg_assert_true(in_array($s['title'], $d['enquiry_sector_choices'], true), "sector_$key: a staff request answer");
		$sectors = array();
		$cities = array();
		foreach (_test_rcp_queries($tree, 'jobs') as $q) {
			if (isset($q['sector'])) $sectors[$q['sector']] = true;
			if (isset($q['city'])) $cities[$q['city']] = true;
		}
		pg_assert_same(array($s['title']), array_keys($sectors), "sector_$key: the jobs of the sector only");
		pg_assert_same($d['city_titles'], array_keys($cities), "sector_$key: the jobs by city");
		$own = '';
		foreach ($d['consultants'] as $c) if ($c['sector'] === $key) $own = $c['name'];
		pg_assert_contains(json_encode($own), json_encode($tree), "sector_$key: its own consultant");
	}
	$seen = array();
	foreach (_test_rcp_queries($pages['sectors']['tree'], 'jobs') as $q) $seen[$q['sector']] = true;
	pg_assert_same($d['sector_titles'], array_keys($seen), 'the sectors page links every sector');
	$seen = array();
	foreach (_test_rcp_queries($pages['cities']['tree'], 'jobs') as $q) $seen[$q['city']] = true;
	pg_assert_same($d['city_titles'], array_keys($seen), 'the cities page links every city');
	$seen = array();
	foreach (_test_rcp_queries($pages['home']['tree'], 'jobs') as $q) {
		if (isset($q['city'])) $seen[$q['city']] = true;
	}
	pg_assert_same($d['city_titles'], array_keys($seen), 'the home opens the jobs of every city');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_recruitment_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_rcp_pages() as $key => $p) {
		$nodes = array();
		_test_rcp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psh|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'rc-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old job a
// year later.
function test_recruitment_public_no_fixed_dates()
{
	$json = json_encode(_test_rcp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; an application reads "başvuru", a
// candidate "aday", an employer "işveren", a consultant "danışman".
function test_recruitment_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	$words = array(
		'/\bapplications?\b/i' => 'başvuru',
		'/\bcandidates?\b/i'   => 'aday',
		'/\bemployers?\b/i'    => 'işveren',
		'/\bconsultants?\b/i'  => 'danışman',
	);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		// An address (a slug) is written without Turkish letters.
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		// The dotted capital İ does not fold to i in mb_stripos().
		$value = str_replace('İ', 'i', $map[$key]);
		foreach ($words as $re => $tr) {
			if (preg_match($re, $key)) pg_assert_true(mb_stripos($value, $tr, 0, 'UTF-8') !== false, "'$key' says $tr");
		}
	}
}

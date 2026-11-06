<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Private School design template's public pages
 * (includes/design_templates/pinegrap-school/pages_public.php): the pages
 * with their keys, a page per programme under a slug of its own, every
 * page link names a page of the finished template and none a shop page,
 * the widgets the home places are the ones the other parts define, the
 * programme pages open the application and the event list with the
 * programme chosen, the fees page names no price, search anchors and
 * classes are the template's own, no fixed date, and every literal has
 * its Turkish.
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
function _test_shp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/pages_public.php';
	$out = array('part' => $public_part, 'programmes' => $programmes,
		'application_programme_choices' => $application_programme_choices, 'event_programme_titles' => $event_programme_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_shp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_shp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_shp_pages()
{
	$pages = array();
	foreach (_test_shp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_shp_queries($tree, $page)
{
	$nodes = array();
	_test_shp_nodes($tree, $nodes);
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

function test_school_public_pages_are_the_plans()
{
	$d = _test_shp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'programmes');
	foreach (array_keys($d['programmes']) as $key) $expected[] = 'programme_' . $key;
	$expected = array_merge($expected, array('day', 'approach', 'campus', 'staff_team', 'about', 'fees', 'faq'));
	pg_assert_same($expected, array_keys(_test_shp_pages()), 'the public pages, a page per programme');
	$names = array();
	foreach (_test_shp_pages() as $key => $p) {
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
function test_school_public_links_name_template_pages()
{
	$keys = array(
		'home', 'programmes', 'day', 'approach', 'campus', 'staff_team', 'about', 'fees', 'faq',
		'events', 'event', 'apply', 'my_applications', 'visit', 'privacy',
		'contact', 'newsletter', 'blog', 'blog_post', 'search', 'my_account', 'login', 'register',
	);
	foreach (array_keys(_test_shp_load()['programmes']) as $key) $keys[] = 'programme_' . $key;
	$known = array_flip($keys);
	$json = json_encode(_test_shp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured events, the Google reviews and the news,
// the widgets the events and the standard parts define; this part defines
// none.
function test_school_public_home_widgets()
{
	pg_assert_same(array(), _test_shp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_shp_pages() as $key => $p) {
		$nodes = array();
		_test_shp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A programme's page opens the application and the event list with the
// programme chosen, in the words of the Programme lists.
function test_school_public_programme_links_choose()
{
	$d = _test_shp_load();
	$pages = _test_shp_pages();
	foreach ($d['programmes'] as $key => $p) {
		$tree = $pages['programme_' . $key]['tree'];
		pg_assert_same(array(array('programme' => $p['title'])), _test_shp_queries($tree, 'apply'), "programme_$key: one application link with the programme");
		pg_assert_true(in_array($p['title'], $d['application_programme_choices'], true), "programme_$key: an application answer");
		pg_assert_same(array(array('programme' => $p['title'])), _test_shp_queries($tree, 'events'), "programme_$key: the events of the programme");
		pg_assert_true(in_array($p['title'], $d['event_programme_titles'], true), "programme_$key: an event Programme answer");
	}
}

// Fees change every year: the fees page names no price, only the way to
// ask for this year's.
function test_school_public_fees_name_no_price()
{
	$json = json_encode(_test_shp_pages()['fees'], JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/[$€£₺]\s?[0-9]|[0-9]\s?(USD|EUR|TRY|TL)\b/u', $json), 'no price on the fees page');
	pg_assert_contains('{{page:apply}}', $json, 'the fees page opens the application');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_school_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_shp_pages() as $key => $p) {
		$nodes = array();
		_test_shp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'sh-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old event
// a year later.
function test_school_public_no_fixed_dates()
{
	$json = json_encode(_test_shp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a programme reads "program", an
// application "başvuru", a parent "veli".
function test_school_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProgrammes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'program', 0, 'UTF-8') !== false, "'$key' says program");
		if (preg_match('/\bApplications?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'başvuru', 0, 'UTF-8') !== false, "'$key' says başvuru");
		if (preg_match('/\bParents?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veli', 0, 'UTF-8') !== false, "'$key' says veli");
	}
}

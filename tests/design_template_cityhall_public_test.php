<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the City Hall design template's public pages
 * (includes/design_templates/pinegrap-cityhall/pages_public.php): the pages
 * with their keys, a page per service area under a slug of its own, every
 * page link names a page of the finished template and none a shop page,
 * the widgets the home places are the ones the other parts define, the
 * service-area pages open the request form with the area chosen and show
 * the department that runs the area, the council page names every member
 * and committee, the service points page shows every point, search anchors
 * and classes are the template's own, no fixed date, and every literal has
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
function _test_cyp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/pages_public.php';
	$out = array('part' => $public_part, 'service_areas' => $service_areas, 'departments' => $departments,
		'council' => $council, 'committees' => $committees, 'service_points' => $service_points,
		'useful_numbers' => $useful_numbers, 'mayor' => $mayor, 'request_topic_choices' => $request_topic_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_cyp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cyp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_cyp_pages()
{
	$pages = array();
	foreach (_test_cyp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_cyp_queries($tree, $page)
{
	$nodes = array();
	_test_cyp_nodes($tree, $nodes);
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

// The texts of a tree, in one string.
function _test_cyp_text($tree)
{
	return json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function test_cityhall_public_pages_are_the_plans()
{
	$d = _test_cyp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'services');
	foreach (array_keys($d['service_areas']) as $key) $expected[] = 'service_' . $key;
	$expected = array_merge($expected, array('mayor', 'council', 'departments', 'city', 'service_points', 'numbers', 'faq'));
	pg_assert_same($expected, array_keys(_test_cyp_pages()), 'the public pages, a page per service area');
	$names = array();
	foreach (_test_cyp_pages() as $key => $p) {
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
function test_cityhall_public_links_name_template_pages()
{
	$keys = array(
		'home', 'services', 'mayor', 'council', 'departments', 'city', 'service_points', 'numbers', 'faq',
		'notices', 'notice', 'events', 'event', 'request', 'my_requests', 'meet', 'privacy',
		'contact', 'newsletter', 'blog', 'blog_post', 'search', 'my_account', 'login', 'register',
	);
	foreach (array_keys(_test_cyp_load()['service_areas']) as $key) $keys[] = 'service_' . $key;
	$known = array_flip($keys);
	$json = json_encode(_test_cyp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured notices, the featured events and the news,
// the widgets the notices and the standard parts define; this part defines
// none.
function test_cityhall_public_home_widgets()
{
	pg_assert_same(array(), _test_cyp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_cyp_pages() as $key => $p) {
		$nodes = array();
		_test_cyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_notices', 'home_events', 'home_posts')), $widgets, 'only the home places widgets');
}

// A service area's page opens the request form with the area chosen, in
// the words of the Topic list, shows each of its services and names the
// department that runs it.
function test_cityhall_public_service_pages_choose_the_topic()
{
	$d = _test_cyp_load();
	$pages = _test_cyp_pages();
	foreach ($d['service_areas'] as $key => $a) {
		$tree = $pages['service_' . $key]['tree'];
		$queries = _test_cyp_queries($tree, 'request');
		pg_assert_true(count($queries) >= count($a['services']), "service_$key: a request link per service");
		foreach ($queries as $q) pg_assert_same(array('topic' => $a['title']), $q, "service_$key: the request opens on the area");
		pg_assert_true(in_array($a['title'], $d['request_topic_choices'], true), "service_$key: a Topic answer");
		$text = _test_cyp_text($tree);
		foreach ($a['services'] as $s) pg_assert_contains($s['title'], $text, "service_$key shows {$s['title']}");
		pg_assert_contains($d['departments'][$a['department']]['title'], $text, "service_$key names its department");
		pg_assert_contains('{{page:service_' . $key . '}}', _test_cyp_text($pages['services']['tree']), "services links service_$key");
	}
}

// The council, departments, service points and numbers pages show every
// member, committee, department, point and number; the mayor's page
// carries the mayor's words.
function test_cityhall_public_pages_show_their_data()
{
	$d = _test_cyp_load();
	$pages = _test_cyp_pages();
	$council = _test_cyp_text($pages['council']['tree']);
	foreach ($d['council'] as $m) pg_assert_contains($m['name'], $council, "council names {$m['name']}");
	foreach ($d['committees'] as $c) pg_assert_contains($c['title'], $council, "council names {$c['title']}");
	$departments = _test_cyp_text($pages['departments']['tree']);
	foreach ($d['departments'] as $dep) pg_assert_contains($dep['title'], $departments, "departments names {$dep['title']}");
	$points = _test_cyp_text($pages['service_points']['tree']);
	foreach ($d['service_points'] as $p) pg_assert_contains($p['title'], $points, "service points shows {$p['title']}");
	$numbers = _test_cyp_text($pages['numbers']['tree']);
	foreach ($d['useful_numbers'] as $u) pg_assert_contains($u['title'], $numbers, "numbers shows {$u['title']}");
	$mayor = _test_cyp_text($pages['mayor']['tree']);
	pg_assert_contains($d['mayor']['message'], $mayor, 'the mayor\'s words');
	pg_assert_contains('{{page:meet}}', $mayor, 'the mayor\'s page opens the meeting request');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_cityhall_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_cyp_pages() as $key => $p) {
		$nodes = array();
		_test_cyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|prt)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'cy-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as old a year
// later.
function test_cityhall_public_no_fixed_dates()
{
	$json = json_encode(_test_cyp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a request reads "talep", a department
// "müdürlük", the council "meclis".
function test_cityhall_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		// A capital İ lowers to i and a combining dot, so it is lowered here.
		$tr = str_replace('İ', 'i', $map[$key]);
		if (preg_match('/^(Make a Request|Send a Request)$/', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/^City Departments$/', $key)) pg_assert_true(mb_stripos($tr, 'müdür', 0, 'UTF-8') !== false, "'$key' says müdürlük");
		if (preg_match('/^(Council|City Council)$/', $key)) pg_assert_true(mb_stripos($tr, 'meclis', 0, 'UTF-8') !== false, "'$key' says meclis");
	}
}

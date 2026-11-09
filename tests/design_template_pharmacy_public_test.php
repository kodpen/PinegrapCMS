<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy design template's public pages
 * (includes/design_templates/pinegrap-pharmacy/pages_public.php): the
 * pages of the plan, one per service, in the public folder and without the
 * shop, links that name template pages and no shop page, the widgets only
 * the home places, every service with its own page and its next step,
 * every check booked from the health checks page, the on-duty page with
 * the emergency number and no booking band, the template's ids and
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

// The page and shop keys of the finished template (_test_rx_page_keys(),
// _test_rx_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_pharmacy_test.php';

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_rxp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/pages_public.php';
	$out = array('part' => $public_part, 'services' => $services, 'checks' => $checks, 'pharmacy' => $pharmacy,
		'duty_facts' => $duty_facts, 'duty_tips' => $duty_tips, 'request_choices' => $request_choices,
		'topic_choices' => $topic_choices, 'service_titles' => $service_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_rxp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rxp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rxp_pages()
{
	$pages = array();
	foreach (_test_rxp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_rxp_queries($tree, $page)
{
	$nodes = array();
	_test_rxp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_pharmacy_public_pages_are_the_plans()
{
	$part = _test_rxp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$expected = array('home', 'services');
	foreach (array_keys(_test_rxp_load()['services']) as $key) $expected[] = 'service_' . $key;
	$expected = array_merge($expected, array('health_checks', 'on_duty', 'about', 'faq', 'location'));
	pg_assert_same($expected, array_keys(_test_rxp_pages()), 'the public pages, a page per service');
	$known = array_flip(_test_rx_page_keys());
	$names = array();
	foreach (_test_rxp_pages() as $key => $p) {
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
	}
}

// Every link names a page the finished template carries (the step 1
// test's list); none names a page that only the shop makes.
function test_pharmacy_public_links_name_template_pages()
{
	$known = array_flip(_test_rx_page_keys());
	$json = json_encode(_test_rxp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_rx_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the team list, the Google reviews and the articles, the
// widgets the team and the standard parts define; this part defines none.
function test_pharmacy_public_home_widgets()
{
	pg_assert_same(array(), _test_rxp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_rxp_pages() as $key => $p) {
		$nodes = array();
		_test_rxp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_team', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// Each service page takes the service's next step (a prescription request
// with its own service chosen, a question on its topic, or online
// booking); the services page links every service page; every request,
// question and team link of the public pages names an answer of its list;
// the health checks page books every check.
function test_pharmacy_public_links_choose_in_the_request_forms()
{
	$d = _test_rxp_load();
	$pages = _test_rxp_pages();
	foreach ($d['services'] as $key => $s) {
		$tree = $pages['service_' . $key]['tree'];
		$rx = _test_rxp_queries($tree, 'prescription');
		if ($s['action'] === 'prescription') {
			pg_assert_same(1, count($rx), "service $key: one prescription link");
			pg_assert_same($s['title'], $rx[0]['service'], "service $key: names its service");
		} else {
			pg_assert_same(0, count($rx), "service $key: no prescription link");
		}
		if ($s['action'] === 'ask') pg_assert_same(1, count(_test_rxp_queries($tree, 'ask')), "service $key: one question on its topic");
		if ($s['action'] === 'book_online') pg_assert_contains('{{page:book_online}}', json_encode($tree), "service $key: books online");
		pg_assert_same($s['title'], $pages['service_' . $key]['title'], "service $key: titled by its service");
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($pages['services']['tree']), "services links $key");
	}
	foreach ($pages as $key => $p) {
		foreach (_test_rxp_queries($p['tree'], 'prescription') as $q) {
			pg_assert_true(in_array($q['service'], $d['request_choices'], true), "$key: names a Request answer");
		}
		foreach (_test_rxp_queries($p['tree'], 'ask') as $q) {
			pg_assert_true(in_array($q['topic'], $d['topic_choices'], true), "$key: names a Topic answer");
		}
		foreach (_test_rxp_queries($p['tree'], 'team') as $q) {
			pg_assert_true(in_array($q['service'], $d['service_titles'], true), "$key: filters the team by a Service answer");
		}
	}
	$checks = json_encode($pages['health_checks']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['checks'] as $key => $c) pg_assert_contains(json_encode($c['title'], JSON_UNESCAPED_UNICODE), $checks, "check $key listed");
	pg_assert_true(substr_count($checks, '{{page:book_online}}') > count($d['checks']), 'every check booked, and the page itself');
}

// The on-duty page calls the emergency number, gives every on-duty answer
// and what to bring at night, and ends without the booking band, which
// would send a night customer to a request form.
function test_pharmacy_public_on_duty_page()
{
	$d = _test_rxp_load();
	$json = json_encode(_test_rxp_pages()['on_duty']['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_contains('tel:' . $d['pharmacy']['emergency'], $json, 'calls the emergency number');
	foreach ($d['duty_facts'] as $f) pg_assert_contains(json_encode($f['title'], JSON_UNESCAPED_UNICODE), $json, 'gives the answer');
	foreach ($d['duty_tips'] as $t) pg_assert_contains(json_encode($t, JSON_UNESCAPED_UNICODE), $json, 'lists what to bring');
	pg_assert_false(strpos($json, 'booking_band') !== false, 'no booking band');
	pg_assert_contains('booking_band', json_encode(_test_rxp_pages()['home']['tree']), 'the home ends with the booking band');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_pharmacy_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_rxp_pages() as $key => $p) {
		$nodes = array();
		_test_rxp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pvt)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'rx-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later, and the duty rota changes every month.
function test_pharmacy_public_no_fixed_dates()
{
	$json = json_encode(_test_rxp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, in the template's words: a pharmacy is
// "eczane", a pharmacist "eczacı", a prescription "reçete", being on duty
// "nöbet", a vaccination "aşı". Page addresses are plain ASCII and are
// checked only for having their Turkish.
function test_pharmacy_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
		elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
		if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
		if (preg_match('/\bon[ -]duty\b|\bduty (nights|days|fee)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'nöbet', 0, 'UTF-8') !== false, "'$key' says nöbet");
		if (preg_match('/\bVaccinations?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'aşı', 0, 'UTF-8') !== false, "'$key' says aşı");
	}
	// Services reads Servisler.
	pg_assert_false(strpos($src, "lang('Services')") !== false, "the pages do not use lang('Services')");
}

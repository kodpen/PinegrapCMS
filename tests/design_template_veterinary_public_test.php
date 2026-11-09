<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Veterinary design template's public pages
 * (includes/design_templates/pinegrap-veterinary/pages_public.php): the
 * pages of the plan, one per service, in the public folder and without the
 * shop, links that name template pages and no shop page, the widgets only
 * the home places, every service with its own page that asks for an
 * appointment for it, every plan joined from the health plans page, the
 * emergency line on the emergency page, the template's ids and classes, no
 * fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template (_test_vt_page_keys(),
// _test_vt_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_veterinary_test.php';

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_vtp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/pages_public.php';
	$out = array('part' => $public_part, 'services' => $services, 'plans' => $plans, 'practice' => $practice,
		'reason_choices' => $reason_choices, 'plan_choices' => $plan_choices, 'species_titles' => $species_titles,
		'emergency_signs' => $emergency_signs);
	return $out;
}

// Every node of a tree, depth first.
function _test_vtp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vtp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_vtp_pages()
{
	$pages = array();
	foreach (_test_vtp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_vtp_queries($tree, $page)
{
	$nodes = array();
	_test_vtp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_veterinary_public_pages_are_the_plans()
{
	$part = _test_vtp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$expected = array('home', 'services');
	foreach (array_keys(_test_vtp_load()['services']) as $key) $expected[] = 'service_' . $key;
	$expected = array_merge($expected, array('pets', 'emergency', 'new_clients', 'health_plans', 'about', 'faq', 'location'));
	pg_assert_same($expected, array_keys(_test_vtp_pages()), 'the public pages, a page per service');
	$names = array();
	foreach (_test_vtp_pages() as $key => $p) {
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
function test_veterinary_public_links_name_template_pages()
{
	$known = array_flip(_test_vt_page_keys());
	$json = json_encode(_test_vtp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_vt_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the vets list, the Google reviews and the articles, the
// widgets the vets and the standard parts define; this part defines none.
function test_veterinary_public_home_widgets()
{
	pg_assert_same(array(), _test_vtp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_vtp_pages() as $key => $p) {
		$nodes = array();
		_test_vtp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_vets', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// Each service page asks for an appointment with its own service chosen;
// the services page links every service page; every appointment link of
// the public pages names an answer of the Service list; the health plans
// page joins every plan.
function test_veterinary_public_links_choose_in_the_request_forms()
{
	$d = _test_vtp_load();
	$pages = _test_vtp_pages();
	foreach ($d['services'] as $key => $s) {
		$q = _test_vtp_queries($pages['service_' . $key]['tree'], 'appointment');
		pg_assert_same(1, count($q), "service $key: one appointment link");
		pg_assert_same($s['title'], $q[0]['service'], "service $key: names its service");
		pg_assert_same($s['title'], $pages['service_' . $key]['title'], "service $key: titled by its service");
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($pages['services']['tree']), "services links $key");
	}
	foreach ($pages as $key => $p) {
		foreach (_test_vtp_queries($p['tree'], 'appointment') as $q) {
			if (isset($q['service'])) pg_assert_true(in_array($q['service'], $d['reason_choices'], true), "$key: names a Service answer");
		}
		foreach (_test_vtp_queries($p['tree'], 'vets') as $q) {
			pg_assert_true(in_array($q['species'], $d['species_titles'], true), "$key: filters the vets by a Pets answer");
		}
	}
	$joins = _test_vtp_queries($pages['health_plans']['tree'], 'plan_join');
	pg_assert_same(count($d['plans']), count($joins), 'a sign-up link per plan');
	foreach ($joins as $q) pg_assert_true(in_array($q['plan'], $d['plan_choices'], true), 'the link names a Plan answer');
}

// The emergency page calls the emergency line and lists every sign that
// cannot wait; it ends without the booking band, which would send an
// emergency to the request form.
function test_veterinary_public_emergency_page()
{
	$d = _test_vtp_load();
	$page = _test_vtp_pages()['emergency'];
	$json = json_encode($page['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['practice']['emergency']), $json, 'calls the emergency line');
	foreach ($d['emergency_signs'] as $s) pg_assert_contains(json_encode($s, JSON_UNESCAPED_UNICODE), $json, 'lists the sign');
	pg_assert_false(strpos($json, 'booking_band') !== false, 'no booking band');
	pg_assert_contains('booking_band', json_encode(_test_vtp_pages()['home']['tree']), 'the home ends with the booking band');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_veterinary_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_vtp_pages() as $key => $p) {
		$nodes = array();
		_test_vtp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'vt-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_veterinary_public_no_fixed_dates()
{
	$json = json_encode(_test_vtp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, in the template's words: a vet is
// "veteriner", a pet "evcil hayvan", an appointment "randevu", a health
// plan "sağlık planı", an emergency "acil"; shared keys whose Turkish names
// another thing are not used.
function test_veterinary_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bVets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner', 0, 'UTF-8') !== false, "'$key' says veteriner");
		if (preg_match('/\bPets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
		if (preg_match('/\bAppointments?\b/', $key)) pg_assert_true(mb_stripos($tr, 'randevu', 0, 'UTF-8') !== false, "'$key' says randevu");
		if (preg_match('/\bHealth Plans?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sağlık plan', 0, 'UTF-8') !== false, "'$key' says sağlık planı");
		if (preg_match('/\bEmergenc(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'acil', 0, 'UTF-8') !== false, "'$key' says acil");
	}
	// Services reads Servisler, How joining works speaks of a membership.
	foreach (array('Services', 'How joining works') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}

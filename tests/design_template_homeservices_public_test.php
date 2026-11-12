<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's public pages
 * (includes/design_templates/pinegrap-homeservices/pages_public.php): the
 * six pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the pages place are the ones
 * the other parts define, the price guide lists every service under its
 * category and opens the visit booking with the service chosen, the
 * call-out charge reads one amount and names the right areas, search
 * anchors and classes are the template's own, no fixed date, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_homeservices_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_hsp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/pages_public.php';
	$out = array('part' => $public_part, 'services' => $services, 'categories' => $categories, 'areas' => $areas,
		'service_choices' => $service_choices, 'price_text' => $price_text, 'call_out_text' => $money($call_out),
		'emergency' => $company['emergency']);
	return $out;
}

// Every node of a tree, depth first.
function _test_hsp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_hsp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_hsp_pages()
{
	$pages = array();
	foreach (_test_hsp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_homeservices_public_pages_are_the_plans()
{
	$part = _test_hsp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'areas', 'prices', 'faq', 'reviews'), array_keys(_test_hsp_pages()), 'the six public pages');
	$names = array();
	foreach (_test_hsp_pages() as $key => $p) {
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

// Every link names a page the finished template carries (the parts test's
// list); none names a page that only the shop makes.
function test_homeservices_public_links_name_template_pages()
{
	$known = array_flip(_test_hs_page_keys());
	$json = json_encode(_test_hsp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured services and team, the Google reviews and
// the tips, the reviews page the Google reviews: widgets the services and
// the standard parts define; this part defines none.
function test_homeservices_public_widgets()
{
	pg_assert_same(array(), _test_hsp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_hsp_pages() as $key => $p) {
		$nodes = array();
		_test_hsp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array(
		'home'    => array('home_services', 'home_team', 'google_reviews', 'home_posts'),
		'reviews' => array('google_reviews'),
	), $widgets, 'the widgets placed');
}

// A table per category with a row for each of its services, the price as
// the service card writes it and a button opening the visit booking with
// the service chosen in the words of the Service list.
function test_homeservices_public_price_guide_opens_the_booking()
{
	$d = _test_hsp_load();
	$nodes = array();
	_test_hsp_nodes(_test_hsp_pages()['prices']['tree'], $nodes);
	$chosen = array();
	$texts = array();
	$ids = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
		if (isset($n['props']['id'])) $ids[] = $n['props']['id'];
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:book}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:book}}?')), $q);
		pg_assert_true(isset($q['service']) && in_array($q['service'], $d['service_choices'], true), 'the link names a Service answer');
		$chosen[] = $q['service'];
	}
	pg_assert_same(count($d['services']), count(array_unique($chosen)), 'a row per sample service');
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(in_array(call_user_func($d['price_text'], $key), $texts, true), "service $key: its price in the guide");
	}
	foreach (array_keys($d['categories']) as $ckey) pg_assert_true(in_array('hs-prices-' . $ckey, $ids, true), "category $ckey: its table");
}

// The call-out charge reads one amount wherever it is named, and names
// the outer areas, never a core one; the emergency number is the
// company's.
function test_homeservices_public_call_out_and_emergency()
{
	$d = _test_hsp_load();
	$pages = _test_hsp_pages();
	foreach (array('areas', 'prices', 'faq') as $key) {
		pg_assert_contains($d['call_out_text'], json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "$key names the call-out charge");
	}
	$nodes = array();
	_test_hsp_nodes($pages['areas']['tree'], $nodes);
	foreach ($nodes as $n) {
		$text = (string)($n['props']['text'] ?? '');
		if (strpos($text, $d['call_out_text']) === false || strpos($text, ',') === false) continue;
		foreach ($d['areas'] as $key => $a) {
			if ($a['core']) pg_assert_false(strpos($text, $a['name']) !== false, "the charge line does not name core area $key");
			else pg_assert_contains($a['name'], $text, "the charge line names outer area $key");
		}
	}
	foreach (array('home', 'faq') as $key) {
		pg_assert_contains($d['emergency'], json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE), "$key gives the emergency line");
	}
	// The areas page shows a card per area, so the footer's anchors land.
	$ids = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['id'])) $ids[] = $n['props']['id'];
	}
	foreach (array_keys($d['areas']) as $key) pg_assert_true(in_array('hs-area-' . $key, $ids, true), "area $key: its card on the page");
}

// Search anchors and ids carry the template's prefix and appear once on a
// page, classes are its own or Bootstrap's, and no Bootstrap 5.3 class
// that does not exist.
function test_homeservices_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_hsp_pages() as $key => $p) {
		$nodes = array();
		_test_hsp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'hs-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_homeservices_public_no_fixed_dates()
{
	$json = json_encode(_test_hsp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a service reads "hizmet", a visit
// "ziyaret", an estimate "teklif", and none says "rezervasyon" but the
// name of the palette block a section is drawn on, which the editor's
// layer tree shows in the palette's own words.
function test_homeservices_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$block_names = array('Booking (Arrival Windows)');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ss]ervices?\b/', $key)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
		if (preg_match('/\b[Vv]isits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\b[Ee]stimates?\b/', $key)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		if (in_array($key, $block_names, true)) continue;
		pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
	}
}

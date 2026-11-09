<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy design template's team and online booking
 * (includes/design_templates/pinegrap-pharmacy/team.php): the four pages
 * and their folders, the Pharmacists form as a collection whose samples
 * fill its fields in the words of the pick lists, lists and the
 * pharmacist page that read only the form's fields, the list filtered by
 * ?service=, the pharmacist page's link that carries the record's name
 * into the prescription request, the booking widget on its own page beside
 * the request, the on-duty page and the emergency number, links to
 * template pages only, the template's ids and classes and every literal in
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

// The page and shop keys of the finished template (_test_rx_page_keys(),
// _test_rx_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_pharmacy_test.php';

// parts.php and the team part in one scope, as the template file reads
// them. Read once per run.
function _test_rxt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/parts.php';
	$team_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/team.php';
	$out = array('part' => $team_part, 'pharmacists' => $pharmacists, 'service_titles' => $service_titles,
		'day_titles' => $day_titles, 'pharmacist_choices' => $pharmacist_choices, 'pharmacy' => $pharmacy);
	return $out;
}

// Every node of a tree, depth first.
function _test_rxt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rxt_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rxt_pages()
{
	$pages = array();
	foreach (_test_rxt_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the Pharmacists form's fields, with their _cf settings.
function _test_rxt_form_fields()
{
	$nodes = array();
	_test_rxt_nodes(_test_rxt_load()['part']['widgets']['pharmacist_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name !== '') $fields[$name] = $n['props']['_cf'] ?? array();
	}
	return $fields;
}

function test_pharmacy_team_pages_and_folders()
{
	$part = _test_rxt_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_rxt_pages();
	pg_assert_same(array('team', 'pharmacist', 'book_online', 'pharmacist_new'), array_keys($pages), 'the four pages');
	foreach (array('team' => 'public', 'pharmacist' => 'public', 'book_online' => 'public', 'pharmacist_new' => 'private') as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
	}
	pg_assert_same(lang('book-online'), $pages['book_online']['name'], 'the booking page has the name the other booking templates use');
	foreach (array('search', 'sitemap') as $f) pg_assert_false($pages['pharmacist_new'][$f], "staff page: no $f");
	pg_assert_true($pages['pharmacist_new']['noindex'], 'staff page: noindex');
	pg_assert_false($pages['pharmacist']['sitemap'], 'the record page is not in the sitemap by itself');
}

// The Pharmacists form: a collection whose sample records fill exactly its
// fields, the service in the words of the Service list, years a number,
// the services in the words of the services, the days in the week's words
// and the photo an image kept in the public folder.
function test_pharmacy_team_form_is_a_collection()
{
	$d = _test_rxt_load();
	$form = _test_rxt_pages()['pharmacist_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same('{{tab:team}}', $form['confirmation_page_id'], 'back to the list');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_rxt_form_fields();
	pg_assert_same(array('name', 'service', 'years', 'role', 'summary', 'bio', 'services', 'days', 'photo'), array_keys($fields), 'the form fields');
	pg_assert_same('number', $fields['years']['kind'] ?? '', 'years is a number');
	pg_assert_same('image', $fields['photo']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('{{folder:public}}', $fields['photo']['upload_folder_id'] ?? '', 'photos in the public folder');
	pg_assert_same(count($d['pharmacists']), count($form['sample_records']), 'a sample record per pharmacist');
	foreach ($form['sample_records'] as $n => $r) {
		$want = array_keys($fields);
		$got = array_keys($r);
		sort($want);
		sort($got);
		pg_assert_same($want, $got, "sample $n fills every field");
		pg_assert_true(in_array($r['service'], $d['service_titles'], true), "sample $n: a Service answer");
		pg_assert_true(in_array($r['name'], $d['pharmacist_choices'], true), "sample $n: a Pharmacist answer of the prescription request");
		pg_assert_true(ctype_digit($r['years']), "sample $n: years a number");
		foreach (explode("\n", $r['services']) as $s) pg_assert_true(in_array($s, $d['service_titles'], true), "sample $n: a service of the pharmacy");
		foreach (explode(', ', $r['days']) as $day) pg_assert_true(in_array($day, $d['day_titles'], true), "sample $n: a day of the week");
	}
}

// The lists and the pharmacist page read only fields the form has; the
// list filters on the service from the address; the home shows four.
function test_pharmacy_team_widgets_read_the_form()
{
	$w = _test_rxt_load()['part']['widgets'];
	pg_assert_same(array('pharmacist_form', 'team_list', 'home_team', 'booking_slots', 'pharmacist_detail'), array_keys($w), 'the widgets');
	$fields = _test_rxt_form_fields();
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section', '__page_link');
	foreach (array('team_list', 'home_team', 'pharmacist_detail') as $key) {
		$nodes = array();
		_test_rxt_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the form");
				$read++;
			}
		}
		pg_assert_true($read >= 5, "$key reads the pharmacist");
		pg_assert_same('{{tab:pharmacist_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Pharmacists form");
	}
	$f = $w['team_list']['config']['filters'];
	pg_assert_same(1, count($f), 'one filter');
	pg_assert_same(array('service', 'url parameter', 'service'), array($f[0]['field'], $f[0]['dynamic_value'], $f[0]['dynamic_value_attribute']), 'service from ?service=');
	foreach ($w['team_list']['config']['search_fields'] as $s) pg_assert_true(isset($fields[$s]), "search field $s");
	pg_assert_same(4, $w['home_team']['config']['max_results'], 'four on the home');
	pg_assert_same('home', $w['home_team']['page'], 'the home places it');
	pg_assert_same('years', $w['team_list']['config']['order_by_field'], 'longest in practice first');
}

// The pharmacist page's link carries the record's name as ?pharmacist=
// into the prescription request (whose Pharmacist list holds the names),
// to a template page, read from a field of the form.
function test_pharmacy_team_page_links_carry_the_record()
{
	$fields = _test_rxt_form_fields();
	$nodes = array();
	_test_rxt_nodes(_test_rxt_load()['part']['widgets']['pharmacist_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		$spec = $n['props']['_bindParams']['href'];
		pg_assert_same(1, count($spec['params']), 'one value');
		pg_assert_true(isset($fields[$spec['params'][0]['token']]), 'a field of the form');
		$links[$spec['page_id']] = $spec['params'][0];
		pg_assert_same('{{page:' . substr($spec['page_id'], 6, -2) . '}}', $n['props']['href'], 'the bare page on the canvas');
	}
	pg_assert_same(array('{{tab:prescription}}' => array('name' => 'pharmacist', 'token' => 'name')), $links, 'sends a prescription to this pharmacist');
}

// A vaccination or a check: the booking widget in the editor's starter
// layout on its own public page, beside the prescription request, the
// on-duty page and the emergency number.
function test_pharmacy_team_book_online_places_the_booking_widget()
{
	$d = _test_rxt_load();
	$w = $d['part']['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page'], 'its page');
	pg_assert_same(array('regionType' => 'booking'), $w['config'], 'a booking widget');
	pg_assert_same('starter', $w['tree'], 'the starter layout');
	$json = json_encode(_test_rxt_pages()['book_online']['tree']);
	pg_assert_same(1, substr_count($json, '"templateWidget":"booking_slots"'), 'placed once');
	pg_assert_contains('{{page:prescription}}', $json, 'links the prescription request');
	pg_assert_contains('{{page:on_duty}}', $json, 'links the on-duty page');
	pg_assert_contains('tel:' . $d['pharmacy']['emergency'], $json, 'calls the emergency number');
}

// The team list's service links name a Service answer, and every link
// names a template page and no shop page.
function test_pharmacy_team_links()
{
	$d = _test_rxt_load();
	$json = json_encode(array(_test_rxt_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_rx_page_keys());
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_rx_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
	$nodes = array();
	_test_rxt_nodes(_test_rxt_pages()['team']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:team}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:team}}?')), $q);
		pg_assert_true(in_array($q['service'], $d['service_titles'], true), 'a Service answer');
		$seen[$q['service']] = true;
	}
	pg_assert_same(count($d['service_titles']), count($seen), 'a filter link per service');
}

// Ids carry rx-, classes are Bootstrap's or the template's, no faded text.
function test_pharmacy_team_ids_and_classes()
{
	$nodes = array();
	foreach (_test_rxt_pages() as $p) _test_rxt_nodes($p['tree'], $nodes);
	foreach (_test_rxt_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'])) _test_rxt_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pvt)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'rx-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a pharmacy reads "eczane", a pharmacist
// "eczacı", a prescription "reçete", being on duty "nöbet", a vaccination
// "aşı". Page addresses are plain ASCII and are checked only for having
// their Turkish.
function test_pharmacy_team_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/team.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
		elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
		if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
		if (preg_match('/\bon[ -]duty\b/i', $key)) pg_assert_true(mb_stripos($tr, 'nöbet', 0, 'UTF-8') !== false, "'$key' says nöbet");
		if (preg_match('/\bVaccinations?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'aşı', 0, 'UTF-8') !== false, "'$key' says aşı");
	}
}

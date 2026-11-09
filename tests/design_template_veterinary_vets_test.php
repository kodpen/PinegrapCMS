<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Veterinary design template's vets and online booking
 * (includes/design_templates/pinegrap-veterinary/vets.php): the four pages
 * and their folders, the Vets form as a collection whose samples fill its
 * fields in the words of the pick lists, lists and the vet page that read
 * only the form's fields, the list filtered by ?species=, the vet page's
 * links that carry the record's name and pets into the appointment
 * request and the articles, the booking widget on its own page beside the
 * request and the emergency line, links to template pages only, the
 * template's ids and classes and every literal in Turkish.
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

// parts.php and the vets part in one scope, as the template file reads
// them. Read once per run.
function _test_vtv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/parts.php';
	$vet_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/vets.php';
	$out = array('part' => $vet_part, 'vets' => $vets, 'species_titles' => $species_titles, 'service_titles' => $service_titles,
		'day_titles' => $day_titles, 'vet_choices' => $vet_choices, 'practice' => $practice);
	return $out;
}

// Every node of a tree, depth first.
function _test_vtv_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vtv_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_vtv_pages()
{
	$pages = array();
	foreach (_test_vtv_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the Vets form's fields, with their _cf settings.
function _test_vtv_form_fields()
{
	$nodes = array();
	_test_vtv_nodes(_test_vtv_load()['part']['widgets']['vet_form']['tree'], $nodes);
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

function test_veterinary_vets_pages_and_folders()
{
	$part = _test_vtv_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_vtv_pages();
	pg_assert_same(array('vets', 'vet', 'book_online', 'vet_new'), array_keys($pages), 'the four pages');
	foreach (array('vets' => 'public', 'vet' => 'public', 'book_online' => 'public', 'vet_new' => 'private') as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
	}
	pg_assert_same(lang('book-online'), $pages['book_online']['name'], 'the booking page has the name the other booking templates use');
	foreach (array('search', 'sitemap') as $f) pg_assert_false($pages['vet_new'][$f], "staff page: no $f");
	pg_assert_true($pages['vet_new']['noindex'], 'staff page: noindex');
	pg_assert_false($pages['vet']['sitemap'], 'the record page is not in the sitemap by itself');
}

// The Vets form: a collection whose sample records fill exactly its fields,
// the pets in the words of the Pets list, years a number, the services in
// the words of the services, the days in the week's words and the photo an
// image kept in the public folder.
function test_veterinary_vets_form_is_a_collection()
{
	$d = _test_vtv_load();
	$form = _test_vtv_pages()['vet_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same('{{tab:vets}}', $form['confirmation_page_id'], 'back to the list');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_vtv_form_fields();
	pg_assert_same(array('name', 'species', 'years', 'role', 'summary', 'bio', 'services', 'days', 'photo'), array_keys($fields), 'the form fields');
	pg_assert_same('number', $fields['years']['kind'] ?? '', 'years is a number');
	pg_assert_same('image', $fields['photo']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('{{folder:public}}', $fields['photo']['upload_folder_id'] ?? '', 'photos in the public folder');
	pg_assert_same(count($d['vets']), count($form['sample_records']), 'a sample record per vet');
	foreach ($form['sample_records'] as $n => $r) {
		$want = array_keys($fields);
		$got = array_keys($r);
		sort($want);
		sort($got);
		pg_assert_same($want, $got, "sample $n fills every field");
		pg_assert_true(in_array($r['species'], $d['species_titles'], true), "sample $n: a Pets answer");
		pg_assert_true(in_array($r['name'], $d['vet_choices'], true), "sample $n: a Vet answer of the appointment request");
		pg_assert_true(ctype_digit($r['years']), "sample $n: years a number");
		foreach (explode("\n", $r['services']) as $s) pg_assert_true(in_array($s, $d['service_titles'], true), "sample $n: a service of the practice");
		foreach (explode(', ', $r['days']) as $day) pg_assert_true(in_array($day, $d['day_titles'], true), "sample $n: a day of the week");
	}
}

// The lists and the vet page read only fields the form has; the list
// filters on the pets from the address; the home shows four.
function test_veterinary_vets_widgets_read_the_form()
{
	$w = _test_vtv_load()['part']['widgets'];
	pg_assert_same(array('vet_form', 'vets_list', 'home_vets', 'booking_slots', 'vet_detail'), array_keys($w), 'the widgets');
	$fields = _test_vtv_form_fields();
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section', '__page_link');
	foreach (array('vets_list', 'home_vets', 'vet_detail') as $key) {
		$nodes = array();
		_test_vtv_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the form");
				$read++;
			}
		}
		pg_assert_true($read >= 5, "$key reads the vet");
		pg_assert_same('{{tab:vet_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Vets form");
	}
	$f = $w['vets_list']['config']['filters'];
	pg_assert_same(1, count($f), 'one filter');
	pg_assert_same(array('species', 'url parameter', 'species'), array($f[0]['field'], $f[0]['dynamic_value'], $f[0]['dynamic_value_attribute']), 'pets from ?species=');
	foreach ($w['vets_list']['config']['search_fields'] as $s) pg_assert_true(isset($fields[$s]), "search field $s");
	pg_assert_same(4, $w['home_vets']['config']['max_results'], 'four on the home');
	pg_assert_same('home', $w['home_vets']['page'], 'the home places it');
	pg_assert_same('years', $w['vets_list']['config']['order_by_field'], 'longest in practice first');
}

// The vet page's links carry the record's values: its name as ?vet= into
// the appointment request (whose Vet list holds the names), its pets as
// ?species= into the articles; each to a template page, read from a field
// of the form.
function test_veterinary_vets_page_links_carry_the_record()
{
	$fields = _test_vtv_form_fields();
	$nodes = array();
	_test_vtv_nodes(_test_vtv_load()['part']['widgets']['vet_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		$spec = $n['props']['_bindParams']['href'];
		pg_assert_same(1, count($spec['params']), 'one value');
		pg_assert_true(isset($fields[$spec['params'][0]['token']]), 'a field of the form');
		$links[$spec['page_id']] = $spec['params'][0];
		pg_assert_same('{{page:' . substr($spec['page_id'], 6, -2) . '}}', $n['props']['href'], 'the bare page on the canvas');
	}
	pg_assert_same(array('name' => 'vet', 'token' => 'name'), $links['{{tab:appointment}}'] ?? null, 'books with this vet');
	pg_assert_same(array('name' => 'species', 'token' => 'species'), $links['{{tab:blog}}'] ?? null, 'the articles for these pets');
}

// A free time: the booking widget in the editor's starter layout on its own
// public page, beside the appointment request and the emergency line.
function test_veterinary_vets_book_online_places_the_booking_widget()
{
	$d = _test_vtv_load();
	$w = $d['part']['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page'], 'its page');
	pg_assert_same(array('regionType' => 'booking'), $w['config'], 'a booking widget');
	pg_assert_same('starter', $w['tree'], 'the starter layout');
	$json = json_encode(_test_vtv_pages()['book_online']['tree']);
	pg_assert_same(1, substr_count($json, '"templateWidget":"booking_slots"'), 'placed once');
	pg_assert_contains('{{page:appointment}}', $json, 'links the appointment request');
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['practice']['emergency']), $json, 'calls the emergency line');
}

// The vet list's pet links name a Pets answer, and every link names a
// template page and no shop page.
function test_veterinary_vets_links()
{
	$d = _test_vtv_load();
	$json = json_encode(array(_test_vtv_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_vt_page_keys());
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_vt_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
	$nodes = array();
	_test_vtv_nodes(_test_vtv_pages()['vets']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:vets}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:vets}}?')), $q);
		pg_assert_true(in_array($q['species'], $d['species_titles'], true), 'a Pets answer');
		$seen[$q['species']] = true;
	}
	pg_assert_same(count($d['species_titles']), count($seen), 'a filter link per pet');
}

// Ids carry vt-, classes are Bootstrap's or the template's, no faded text.
function test_veterinary_vets_ids_and_classes()
{
	$nodes = array();
	foreach (_test_vtv_pages() as $p) _test_vtv_nodes($p['tree'], $nodes);
	foreach (_test_vtv_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'])) _test_vtv_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'vt-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a vet reads "veteriner", a pet "evcil
// hayvan", an appointment "randevu", an emergency "acil".
function test_veterinary_vets_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/vets.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bVets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner', 0, 'UTF-8') !== false, "'$key' says veteriner");
		if (preg_match('/\bPets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
		if (preg_match('/\bAppointments?\b/', $key)) pg_assert_true(mb_stripos($tr, 'randevu', 0, 'UTF-8') !== false, "'$key' says randevu");
		if (preg_match('/\bEmergenc(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'acil', 0, 'UTF-8') !== false, "'$key' says acil");
	}
}

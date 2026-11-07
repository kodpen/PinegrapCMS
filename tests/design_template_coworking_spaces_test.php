<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's trainers and personal training
 * (includes/design_templates/pinegrap-coworking/spaces.php): the four pages
 * in their folders, the Trainers form a collection whose sample records
 * fill every field and whose number and image fields carry their kinds,
 * the lists and the trainer page reading only fields the form has, the
 * focus filter in the words of the Focus list, the booking widget in the
 * starter layout on its own public page, links to template pages, the
 * template's ids and classes, and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the spaces part in one scope, as the template file reads
// them. Read once per run.
function _test_cws_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	$space_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/spaces.php';
	$out = array('part' => $space_part, 'spaces' => $spaces, 'kind_titles' => $kind_titles, 'floor_titles' => $floor_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_cws_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cws_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_cws_pages()
{
	$pages = array();
	foreach (_test_cws_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the Spaces form's fields, with their _cf settings.
function _test_cws_form_fields()
{
	$nodes = array();
	_test_cws_nodes(_test_cws_load()['part']['widgets']['space_form']['tree'], $nodes);
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

function test_coworking_spaces_pages_and_folders()
{
	$part = _test_cws_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_cws_pages();
	pg_assert_same(array('spaces', 'space', 'book_online', 'space_new'), array_keys($pages), 'the four pages');
	foreach (array('spaces' => 'public', 'space' => 'public', 'book_online' => 'public', 'space_new' => 'private') as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
	}
	pg_assert_same(lang('book-online'), $pages['book_online']['name'], 'the booking page has the name the other booking templates use');
	foreach (array('search', 'sitemap') as $f) pg_assert_false($pages['space_new'][$f], "staff page: no $f");
	pg_assert_true($pages['space_new']['noindex'], 'staff page: noindex');
	pg_assert_false($pages['space']['sitemap'], 'the record page is not in the sitemap by itself');
}

// The Spaces form: a collection whose sample records fill exactly its
// fields, the kind and floor in the words of their lists, seats a number
// and the photo an image kept in the public folder.
function test_coworking_spaces_form_is_a_collection()
{
	$d = _test_cws_load();
	$form = _test_cws_pages()['space_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same('{{tab:spaces}}', $form['confirmation_page_id'], 'back to the list');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_cws_form_fields();
	pg_assert_same(array('name', 'kind', 'floor', 'seats', 'rate', 'summary', 'features', 'photo'), array_keys($fields), 'the form fields');
	pg_assert_same('number', $fields['seats']['kind'] ?? '', 'seats is a number');
	pg_assert_same('image', $fields['photo']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('{{folder:public}}', $fields['photo']['upload_folder_id'] ?? '', 'photos in the public folder');
	pg_assert_same(count($d['spaces']), count($form['sample_records']), 'a sample record per space');
	foreach ($form['sample_records'] as $n => $r) {
		$want = array_keys($fields);
		$got = array_keys($r);
		sort($want);
		sort($got);
		pg_assert_same($want, $got, "sample $n fills every field");
		pg_assert_true(in_array($r['kind'], $d['kind_titles'], true), "sample $n: a Kind answer");
		pg_assert_true(in_array($r['floor'], $d['floor_titles'], true), "sample $n: a Floor answer");
		pg_assert_true(ctype_digit($r['seats']), "sample $n: seats a number");
		pg_assert_true(trim($r['rate']) !== '' && trim($r['features']) !== '', "sample $n: a price and features");
	}
}

// The lists and the space page read only fields the form has; the list
// filters on the kind from the address; the home shows three.
function test_coworking_spaces_widgets_read_the_form()
{
	$w = _test_cws_load()['part']['widgets'];
	pg_assert_same(array('space_form', 'spaces_list', 'home_spaces', 'booking_slots', 'space_detail'), array_keys($w), 'the widgets');
	$fields = _test_cws_form_fields();
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section');
	foreach (array('spaces_list', 'home_spaces', 'space_detail') as $key) {
		$nodes = array();
		_test_cws_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the form");
				$read++;
			}
		}
		pg_assert_true($read >= 6, "$key reads the space");
		pg_assert_same('{{tab:space_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Spaces form");
	}
	$f = $w['spaces_list']['config']['filters'];
	pg_assert_same(1, count($f), 'one filter');
	pg_assert_same(array('kind', 'url parameter', 'kind'), array($f[0]['field'], $f[0]['dynamic_value'], $f[0]['dynamic_value_attribute']), 'kind from ?kind=');
	foreach ($w['spaces_list']['config']['search_fields'] as $s) pg_assert_true(isset($fields[$s]), "search field $s");
	pg_assert_same(3, $w['home_spaces']['config']['max_results'], 'three on the home');
	pg_assert_same('home', $w['home_spaces']['page'], 'the home places it');
	pg_assert_same('seats', $w['spaces_list']['config']['order_by_field'], 'the largest first');
}

// Meeting rooms: the booking widget in the editor's starter layout on its
// own public page, beside a link to the tour request.
function test_coworking_spaces_book_online_places_the_booking_widget()
{
	$w = _test_cws_load()['part']['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page'], 'its page');
	pg_assert_same(array('regionType' => 'booking'), $w['config'], 'a booking widget');
	pg_assert_same('starter', $w['tree'], 'the starter layout');
	$json = json_encode(_test_cws_pages()['book_online']['tree']);
	pg_assert_same(1, substr_count($json, '"templateWidget":"booking_slots"'), 'placed once');
	pg_assert_contains('{{page:tour}}', $json, 'links the tour request');
}

// The space list's kind links name a Kind answer, and every link names a
// template page and no shop page.
function test_coworking_spaces_links()
{
	$d = _test_cws_load();
	$json = json_encode(array(_test_cws_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'plans', 'amenities', 'about', 'faq', 'location',
		'spaces', 'space', 'space_new', 'book_online', 'tour', 'office_enquiry', 'privacy', 'contact', 'my_account', 'staff',
		'staff_tours', 'staff_enquiries', 'staff_signups', 'event_new', 'events', 'blog_new', 'inbox', 'my_tours', 'blog', 'newsletter'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	$nodes = array();
	_test_cws_nodes(_test_cws_pages()['spaces']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:spaces}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:spaces}}?')), $q);
		pg_assert_true(in_array($q['kind'], $d['kind_titles'], true), 'a Kind answer');
		$seen[$q['kind']] = true;
	}
	pg_assert_same(count($d['kind_titles']), count($seen), 'a filter link per kind');
}

// Ids carry cw-, classes are Bootstrap's or the template's, no faded text.
function test_coworking_spaces_ids_and_classes()
{
	$nodes = array();
	foreach (_test_cws_pages() as $p) _test_cws_nodes($p['tree'], $nodes);
	foreach (_test_cws_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'])) _test_cws_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pch|pcn|pmu|pph|psh|ptv|pwd|pmk|psc|pai|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'cw-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a desk reads "masa", a meeting room
// "toplantı odası", a tour "tur".
function test_coworking_spaces_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/spaces.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bDesks?\b/', $key)) pg_assert_true(mb_stripos($tr, 'masa', 0, 'UTF-8') !== false, "'$key' says masa");
		if (preg_match('/\bMeeting Rooms?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'toplantı oda', 0, 'UTF-8') !== false, "'$key' says toplantı odası");
		if (preg_match('/\b[Tt]ours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
	}
}

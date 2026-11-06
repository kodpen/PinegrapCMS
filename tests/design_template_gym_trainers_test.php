<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's trainers and personal training
 * (includes/design_templates/pinegrap-gym/trainers.php): the four pages
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

// parts.php and the trainers part in one scope, as the template file reads
// them. Read once per run.
function _test_gyt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php';
	$trainer_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/trainers.php';
	$out = array('part' => $trainer_part, 'trainers' => $trainers, 'focus_titles' => $focus_titles, 'class_names' => $class_names);
	return $out;
}

// Every node of a tree, depth first.
function _test_gyt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gyt_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_gyt_pages()
{
	$pages = array();
	foreach (_test_gyt_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the Trainers form's fields, with their _cf settings.
function _test_gyt_form_fields()
{
	$nodes = array();
	_test_gyt_nodes(_test_gyt_load()['part']['widgets']['trainer_form']['tree'], $nodes);
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

function test_gym_trainers_pages_and_folders()
{
	$part = _test_gyt_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_gyt_pages();
	pg_assert_same(array('trainers', 'trainer', 'book_online', 'trainer_new'), array_keys($pages), 'the four pages');
	foreach (array('trainers' => 'public', 'trainer' => 'public', 'book_online' => 'public', 'trainer_new' => 'private') as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
	}
	pg_assert_same(lang('book-online'), $pages['book_online']['name'], 'the booking page has the name the other booking templates use');
	foreach (array('search', 'sitemap') as $f) pg_assert_false($pages['trainer_new'][$f], "staff page: no $f");
	pg_assert_true($pages['trainer_new']['noindex'], 'staff page: noindex');
	pg_assert_false($pages['trainer']['sitemap'], 'the record page is not in the sitemap by itself');
}

// The Trainers form: a collection whose sample records fill exactly its
// fields, the focus in the words of the Focus list, years a number and the
// photo an image kept in the public folder.
function test_gym_trainers_form_is_a_collection()
{
	$d = _test_gyt_load();
	$form = _test_gyt_pages()['trainer_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same('{{tab:trainers}}', $form['confirmation_page_id'], 'back to the list');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_gyt_form_fields();
	pg_assert_same(array('name', 'focus', 'years', 'role', 'summary', 'bio', 'teaches', 'photo'), array_keys($fields), 'the form fields');
	pg_assert_same('number', $fields['years']['kind'] ?? '', 'years is a number');
	pg_assert_same('image', $fields['photo']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('{{folder:public}}', $fields['photo']['upload_folder_id'] ?? '', 'photos in the public folder');
	pg_assert_same(count($d['trainers']), count($form['sample_records']), 'a sample record per trainer');
	foreach ($form['sample_records'] as $n => $r) {
		$want = array_keys($fields);
		$got = array_keys($r);
		sort($want);
		sort($got);
		pg_assert_same($want, $got, "sample $n fills every field");
		pg_assert_true(in_array($r['focus'], $d['focus_titles'], true), "sample $n: a Focus answer");
		pg_assert_true(ctype_digit($r['years']), "sample $n: years a number");
		foreach (explode("\n", $r['teaches']) as $c) pg_assert_true(in_array($c, $d['class_names'], true), "sample $n: teaches a class of the timetable");
	}
}

// The lists and the trainer page read only fields the form has; the list
// filters on the focus from the address; the home shows four.
function test_gym_trainers_widgets_read_the_form()
{
	$w = _test_gyt_load()['part']['widgets'];
	pg_assert_same(array('trainer_form', 'trainers_list', 'home_trainers', 'booking_slots', 'trainer_detail'), array_keys($w), 'the widgets');
	$fields = _test_gyt_form_fields();
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section');
	foreach (array('trainers_list', 'home_trainers', 'trainer_detail') as $key) {
		$nodes = array();
		_test_gyt_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the form");
				$read++;
			}
		}
		pg_assert_true($read >= 5, "$key reads the trainer");
		pg_assert_same('{{tab:trainer_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Trainers form");
	}
	$f = $w['trainers_list']['config']['filters'];
	pg_assert_same(1, count($f), 'one filter');
	pg_assert_same(array('focus', 'url parameter', 'focus'), array($f[0]['field'], $f[0]['dynamic_value'], $f[0]['dynamic_value_attribute']), 'focus from ?focus=');
	foreach ($w['trainers_list']['config']['search_fields'] as $s) pg_assert_true(isset($fields[$s]), "search field $s");
	pg_assert_same(4, $w['home_trainers']['config']['max_results'], 'four on the home');
	pg_assert_same('home', $w['home_trainers']['page'], 'the home places it');
	pg_assert_same('years', $w['trainers_list']['config']['order_by_field'], 'longest coaching first');
}

// Personal training: the booking widget in the editor's starter layout on
// its own public page, beside a link to the group classes.
function test_gym_trainers_book_online_places_the_booking_widget()
{
	$w = _test_gyt_load()['part']['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page'], 'its page');
	pg_assert_same(array('regionType' => 'booking'), $w['config'], 'a booking widget');
	pg_assert_same('starter', $w['tree'], 'the starter layout');
	$json = json_encode(_test_gyt_pages()['book_online']['tree']);
	pg_assert_same(1, substr_count($json, '"templateWidget":"booking_slots"'), 'placed once');
	pg_assert_contains('{{page:timetable}}', $json, 'links the group classes');
}

// The trainer list's focus links name a Focus answer, and every link names
// a template page and no shop page.
function test_gym_trainers_links()
{
	$d = _test_gyt_load();
	$json = json_encode(array(_test_gyt_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location',
		'trainers', 'trainer', 'trainer_new', 'book_online', 'class_booking', 'join', 'privacy', 'contact', 'my_account', 'staff',
		'staff_bookings', 'staff_joins', 'blog_new', 'inbox', 'my_bookings', 'blog', 'newsletter'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	$nodes = array();
	_test_gyt_nodes(_test_gyt_pages()['trainers']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:trainers}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:trainers}}?')), $q);
		pg_assert_true(in_array($q['focus'], $d['focus_titles'], true), 'a Focus answer');
		$seen[$q['focus']] = true;
	}
	pg_assert_same(count($d['focus_titles']), count($seen), 'a filter link per focus');
}

// Ids carry gy-, classes are Bootstrap's or the template's, no faded text.
function test_gym_trainers_ids_and_classes()
{
	$nodes = array();
	foreach (_test_gyt_pages() as $p) _test_gyt_nodes($p['tree'], $nodes);
	foreach (_test_gyt_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'])) _test_gyt_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'gy-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a trainer reads "antrenör".
function test_gym_trainers_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/trainers.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bTrainers?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'antrenör', 0, 'UTF-8') !== false, "'$key' says antrenör");
		if (preg_match('/\bClass(es)?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}

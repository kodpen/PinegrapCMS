<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Wedding Planner and Venue design template's Real Weddings
 * (includes/design_templates/pinegrap-wedding/weddings.php): the three
 * pages and four widgets, the form with a control for every field the
 * cards and the record page read, the sample records written with the
 * form's own answers, the list filters read from ?space= and ?season= in
 * the words of the pick lists, the home showing featured weddings only,
 * the record page asking about the same space, the template's own ids and
 * every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the Real Weddings part in one scope, as the template file
// reads them. Read once per run.
function _test_wdw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/parts.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/weddings.php';
	$out = array('part' => $part, 'weddings' => $weddings, 'space_titles' => $space_titles, 'season_titles' => $season_titles,
		'space_choices' => $space_choices, 'suppliers_text' => $suppliers_text, 'card' => $wedding_card());
	return $out;
}

// Every node of a tree, depth first.
function _test_wdw_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_wdw_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_wdw_pages()
{
	$pages = array();
	foreach (_test_wdw_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The form controls of a tree, by their name attribute.
function _test_wdw_controls($tree)
{
	$nodes = array();
	_test_wdw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $out[$a['value']] = $n;
		}
	}
	return $out;
}

// The tokens a tree reads.
function _test_wdw_tokens($tree)
{
	$nodes = array();
	_test_wdw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_bindings'] ?? array() as $token) $out[$token] = true;
	}
	return $out;
}

function test_wedding_weddings_pages_and_widgets()
{
	$d = _test_wdw_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('weddings', 'wedding', 'wedding_new'), array_keys(_test_wdw_pages()), 'the three pages');
	pg_assert_same(array('wedding_form', 'weddings_list', 'home_weddings', 'wedding_detail'), array_keys($d['part']['widgets']), 'the four widgets');
	$pages = _test_wdw_pages();
	pg_assert_same('public', $pages['weddings']['folder'], 'the list is public');
	pg_assert_same('public', $pages['wedding']['folder'], 'the story is public');
	pg_assert_same('private', $pages['wedding_new']['folder'], 'the form is the staff\'s');
	pg_assert_true(!empty($pages['wedding_new']['noindex']) && $pages['wedding_new']['search'] === false, 'the form page stays out of search');
	foreach ($pages as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['part']['widgets'] as $key => $w) {
		pg_assert_true(isset($pages[$w['page']]) || $w['page'] === 'home', "$key sits on a known page");
	}
	pg_assert_same('home', $d['part']['widgets']['home_weddings']['page'], 'the home list is the home\'s');
}

// The form has a control for every field the card and the record page
// read, the collection's roles on its title, category, description and
// media, number and image kinds, and Featured for the office only.
function test_wedding_weddings_form_fields()
{
	$d = _test_wdw_load();
	$w = $d['part']['widgets'];
	$controls = _test_wdw_controls($w['wedding_form']['tree']);
	$fields = array('couple', 'space', 'season', 'guests', 'summary', 'story', 'quote', 'suppliers', 'photo_1', 'photo_2', 'photo_3', 'photo_4', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the form\'s fields in order');
	$read = array_merge(_test_wdw_tokens($d['card']), _test_wdw_tokens($w['wedding_detail']['tree']));
	foreach (array_keys($read) as $token) {
		if (strpos($token, '_') === 0 || in_array($token, array('form_item_view', 'not_found', 'record_found', 'record_not_found', 'can_edit', '__edit_url', '__page_link'), true)) continue;
		pg_assert_true(isset($controls[$token]), "a field for $token");
	}
	pg_assert_same('number', $controls['guests']['props']['_cf']['kind'] ?? '', 'guests is a number');
	foreach (array('photo_1', 'photo_2', 'photo_3', 'photo_4') as $f) pg_assert_same('image', $controls[$f]['props']['_cf']['kind'] ?? '', "$f is an image");
	pg_assert_true(!empty($controls['featured']['props']['_cf']['office_use_only']), 'featured is the office\'s');
	$page = _test_wdw_pages()['wedding_new'];
	pg_assert_same(1, $page['form']['collection'], 'a collection');
	pg_assert_same('photo_1', $page['form']['share_image_field'], 'the first photo is the share image');
}

// The sample records use the answers of the form's own pick lists, so the
// filters and the home find them.
function test_wedding_weddings_sample_records()
{
	$d = _test_wdw_load();
	$records = _test_wdw_pages()['wedding_new']['form']['sample_records'];
	pg_assert_same(count($d['weddings']), count($records), 'a record per sample wedding');
	$featured = 0;
	foreach (array_values($d['weddings']) as $n => $w) {
		$r = $records[$n];
		pg_assert_same($w['couple'], $r['couple'], "record $n: the couple");
		pg_assert_true(in_array($r['space'], $d['space_titles'], true), "record $n: a Space answer");
		pg_assert_true(in_array($r['season'], $d['season_titles'], true), "record $n: a Season answer");
		pg_assert_true(ctype_digit($r['guests']), "record $n: guests as a number");
		pg_assert_same(count($w['suppliers']), count(explode("\n", $r['suppliers'])), "record $n: a line per supplier");
		pg_assert_true(in_array($r['featured'], array(lang('Yes'), lang('No')), true), "record $n: a Featured answer");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured weddings for the home');
}

// The list reads ?space= and ?season=, and the chips above it send the
// words of the pick lists; the home shows featured weddings only.
function test_wedding_weddings_list_filters()
{
	$d = _test_wdw_load();
	$w = $d['part']['widgets'];
	$filters = array();
	foreach ($w['weddings_list']['config']['filters'] as $f) $filters[$f['field']] = $f['dynamic_value_attribute'] ?? '';
	pg_assert_same(array('space' => 'space', 'season' => 'season'), $filters, 'the list reads space and season from the address');
	foreach (array('weddings_list', 'home_weddings') as $key) {
		pg_assert_same('{{tab:wedding_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Real Weddings form");
		pg_assert_same('{{tab:wedding}}', $w[$key]['config']['detail_page_id'], "$key opens the story");
	}
	$home = $w['home_weddings']['config'];
	pg_assert_same(3, $home['max_results'], 'three on the home');
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $home['filters'], 'featured only');
	$nodes = array();
	_test_wdw_nodes(_test_wdw_pages()['weddings']['tree'], $nodes);
	$seen = array('space' => 0, 'season' => 0);
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:weddings}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:weddings}}?')), $q);
		if (isset($q['space'])) { pg_assert_true(in_array($q['space'], $d['space_titles'], true), 'a Space answer'); $seen['space']++; }
		if (isset($q['season'])) { pg_assert_true(in_array($q['season'], $d['season_titles'], true), 'a Season answer'); $seen['season']++; }
	}
	pg_assert_same(array('space' => count($d['space_titles']), 'season' => count($d['season_titles'])), $seen, 'a chip per answer');
}

// The story page asks about the same space: its link sends the record's
// Space answer to the enquiry as ?space=, an answer of the enquiry's list.
function test_wedding_weddings_detail_asks_about_the_space()
{
	$d = _test_wdw_load();
	$nodes = array();
	_test_wdw_nodes($d['part']['widgets']['wedding_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(array('page_id' => '{{tab:enquiry}}', 'params' => array(array('name' => 'space', 'token' => 'space')))), $links, 'one link to the enquiry with the space');
	foreach ($d['space_titles'] as $t) pg_assert_true(in_array($t, $d['space_choices'], true), "$t is an enquiry answer");
	pg_assert_true(isset(_test_wdw_tokens($d['part']['widgets']['wedding_detail']['tree'])['record_not_found']), 'a missing story says so');
}

// Ids carry the template's prefix and are unique per page; no class of
// another template.
function test_wedding_weddings_ids_and_classes()
{
	$d = _test_wdw_load();
	$trees = array();
	foreach (_test_wdw_pages() as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['part']['widgets'] as $key => $w) $trees[$key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_wdw_nodes($tree, $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "$key: class $c is not another template's");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'wd-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "$key: id $id once");
			$ids[$id] = true;
		}
	}
}

// Every literal has its Turkish; a wedding reads "düğün". Page names are
// address words and keep their own spelling.
function test_wedding_weddings_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/weddings.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\bweddings?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'düğün', 0, 'UTF-8') !== false, "'$key' says düğün");
	}
}

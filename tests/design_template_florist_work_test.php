<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's Our Work collection
 * (includes/design_templates/pinegrap-florist/work.php): its three pages
 * and four widgets, the form's fields covering everything the card and
 * the work page read, the sample records answering the form's own pick
 * lists, the list reading ?type= and ?season= with chips in the same
 * words, the home showing featured work only, the work page asking about
 * the same kind of event in the event enquiry's words, the template's own
 * ids and classes, and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the Our Work part in one scope, as the template file
// reads them. Read once per run.
function _test_flw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/parts.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/work.php';
	$out = array('part' => $part, 'works' => $works, 'event_titles' => $event_titles, 'season_titles' => $season_titles,
		'event_choices' => $event_choices, 'card' => $work_card());
	return $out;
}

// Every node of a tree, depth first.
function _test_flw_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_flw_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_flw_pages()
{
	$pages = array();
	foreach (_test_flw_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The form controls of a tree, by their name attribute.
function _test_flw_controls($tree)
{
	$nodes = array();
	_test_flw_nodes($tree, $nodes);
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
function _test_flw_tokens($tree)
{
	$nodes = array();
	_test_flw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_bindings'] ?? array() as $token) $out[$token] = true;
	}
	return $out;
}

function test_florist_work_pages_and_widgets()
{
	$d = _test_flw_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('work', 'work_item', 'work_new'), array_keys(_test_flw_pages()), 'the three pages');
	pg_assert_same(array('work_form', 'work_list', 'home_work', 'work_detail'), array_keys($d['part']['widgets']), 'the four widgets');
	$pages = _test_flw_pages();
	pg_assert_same('public', $pages['work']['folder'], 'the list is public');
	pg_assert_same('public', $pages['work_item']['folder'], 'the work page is public');
	pg_assert_same('private', $pages['work_new']['folder'], 'the form is the staff\'s');
	pg_assert_true(!empty($pages['work_new']['noindex']) && $pages['work_new']['search'] === false, 'the form page stays out of search');
	$names = array();
	foreach ($pages as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name is its own");
		$names[$p['name']] = true;
	}
	foreach ($d['part']['widgets'] as $key => $w) {
		pg_assert_true(isset($pages[$w['page']]) || $w['page'] === 'home', "$key sits on a known page");
	}
	pg_assert_same('home', $d['part']['widgets']['home_work']['page'], 'the home list is the home\'s');
}

// The form has a control for every field the card and the work page read,
// the collection's roles on its title, category, description and media,
// image kinds, and Featured for the office only.
function test_florist_work_form_fields()
{
	$d = _test_flw_load();
	$w = $d['part']['widgets'];
	$controls = _test_flw_controls($w['work_form']['tree']);
	$fields = array('title', 'type', 'season', 'summary', 'story', 'flowers', 'photo_1', 'photo_2', 'photo_3', 'photo_4', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the form\'s fields in order');
	$read = array_merge(_test_flw_tokens($d['card']), _test_flw_tokens($w['work_detail']['tree']));
	foreach (array_keys($read) as $token) {
		if (strpos($token, '_') === 0 || in_array($token, array('form_item_view', 'not_found', 'record_found', 'record_not_found', 'can_edit', '__edit_url', '__page_link'), true)) continue;
		pg_assert_true(isset($controls[$token]), "a field for $token");
	}
	foreach (array('photo_1', 'photo_2', 'photo_3', 'photo_4') as $f) pg_assert_same('image', $controls[$f]['props']['_cf']['kind'] ?? '', "$f is an image");
	pg_assert_true(!empty($controls['featured']['props']['_cf']['office_use_only']), 'featured is the office\'s');
	$page = _test_flw_pages()['work_new'];
	pg_assert_same(1, $page['form']['collection'], 'a collection');
	pg_assert_same('photo_1', $page['form']['share_image_field'], 'the first photo is the share image');
}

// The sample records use the answers of the form's own pick lists, so the
// filters and the home find them.
function test_florist_work_sample_records()
{
	$d = _test_flw_load();
	$records = _test_flw_pages()['work_new']['form']['sample_records'];
	pg_assert_same(count($d['works']), count($records), 'a record per sample work');
	$featured = 0;
	foreach (array_values($d['works']) as $n => $w) {
		$r = $records[$n];
		pg_assert_same($w['title'], $r['title'], "record $n: the title");
		pg_assert_true(in_array($r['type'], $d['event_titles'], true), "record $n: a Kind answer");
		pg_assert_true(in_array($r['season'], $d['season_titles'], true), "record $n: a Season answer");
		pg_assert_same(count($w['flowers']), count(explode("\n", $r['flowers'])), "record $n: a line per flower");
		pg_assert_true(in_array($r['featured'], array(lang('Yes'), lang('No')), true), "record $n: a Featured answer");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured works for the home');
}

// The list reads ?type= and ?season=, and the chips above it send the
// words of the pick lists; the home shows featured work only.
function test_florist_work_list_filters()
{
	$d = _test_flw_load();
	$w = $d['part']['widgets'];
	$filters = array();
	foreach ($w['work_list']['config']['filters'] as $f) $filters[$f['field']] = $f['dynamic_value_attribute'] ?? '';
	pg_assert_same(array('type' => 'type', 'season' => 'season'), $filters, 'the list reads type and season from the address');
	foreach (array('work_list', 'home_work') as $key) {
		pg_assert_same('{{tab:work_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Our Work form");
		pg_assert_same('{{tab:work_item}}', $w[$key]['config']['detail_page_id'], "$key opens the work page");
	}
	$home = $w['home_work']['config'];
	pg_assert_same(3, $home['max_results'], 'three on the home');
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $home['filters'], 'featured only');
	$nodes = array();
	_test_flw_nodes(_test_flw_pages()['work']['tree'], $nodes);
	$seen = array('type' => 0, 'season' => 0);
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:work}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:work}}?')), $q);
		if (isset($q['type'])) { pg_assert_true(in_array($q['type'], $d['event_titles'], true), 'a Kind answer'); $seen['type']++; }
		if (isset($q['season'])) { pg_assert_true(in_array($q['season'], $d['season_titles'], true), 'a Season answer'); $seen['season']++; }
	}
	pg_assert_same(array('type' => count($d['event_titles']), 'season' => count($d['season_titles'])), $seen, 'a chip per answer');
}

// The work page asks about the same kind of event: its link sends the
// record's Kind answer to the event enquiry as ?event=, an answer of the
// enquiry's list.
function test_florist_work_detail_asks_about_the_event()
{
	$d = _test_flw_load();
	$nodes = array();
	_test_flw_nodes($d['part']['widgets']['work_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(array('page_id' => '{{tab:event_enquiry}}', 'params' => array(array('name' => 'event', 'token' => 'type')))), $links, 'one link to the event enquiry with the kind');
	foreach ($d['event_titles'] as $t) pg_assert_true(in_array($t, $d['event_choices'], true), "$t is an event enquiry answer");
	pg_assert_true(isset(_test_flw_tokens($d['part']['widgets']['work_detail']['tree'])['record_not_found']), 'a missing work says so');
}

// Every page link names a page the finished template carries, none a shop
// page; ids carry the template's prefix and are unique per tree; no class
// of another template.
function test_florist_work_links_ids_and_classes()
{
	$d = _test_flw_load();
	$trees = array();
	foreach (_test_flw_pages() as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['part']['widgets'] as $key => $w) $trees[$key] = $w['tree'];
	$known = array_flip(array('home', 'events', 'work', 'work_item', 'work_new', 'event_enquiry', 'consultation'));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($trees, $d['part']['widgets'], _test_flw_pages())), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_flw_nodes($tree, $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmk|pmu|pmz|plx|pdl|plw|psl|psh|psc|pco|pcl|pcn|pgy|pch|ptv|pph|pwd|pai)-/', $c), "$key: class $c is not another template's");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'fl-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "$key: id $id once");
			$ids[$id] = true;
		}
	}
}

// Every literal has its Turkish. Page names are address words and keep
// their own spelling.
function test_florist_work_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/work.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
	}
}

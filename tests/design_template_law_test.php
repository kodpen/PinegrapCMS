<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Law Firm design template's parts
 * (includes/design_templates/pinegrap-law/): the firm's data hangs
 * together (practice areas led by a lawyer who works in them, lawyers in
 * known areas, results naming a known area, the consultation form's pick
 * lists), every page link of the shared parts names a page of the finished
 * template, and the shared parts carry no class of another template; the
 * public pages (pages_public.php): their keys, one page per area, links to
 * known pages and consultation links that name answers of the form's lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_law_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-law';
	include $dir . '/parts.php';
	$cards = array();
	foreach (array_keys($lawyers) as $key) $cards[] = $lawyer_card($key);
	$public = include $dir . '/pages_public.php';
	$out = array(
		'public'         => $public,
		'areas'          => $practice_areas,
		'lawyers'        => $lawyers,
		'figures'        => $figures,
		'results'        => $results,
		'fees'           => $fee_models,
		'area_titles'    => $area_titles,
		'lawyer_choices' => $lawyer_choices,
		'time_windows'   => $time_windows,
		'shared'         => array_merge(array($account_bar_tree(), $header(), $footer(), $consult_band(), $notice(), $staff_bar()), $cards),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_law_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_law_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_law_page_keys()
{
	$keys = array(
		'home', 'practice', 'lawyers', 'results', 'fees', 'about', 'faq', 'careers',
		'consultation', 'consultation_view', 'my_consultations', 'staff_consultations', 'privacy',
		'application', 'application_view', 'staff_applications',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array_keys(_test_law_load()['areas']) as $key) $keys[] = 'area_' . $key;
	return $keys;
}

function test_law_data_hangs_together()
{
	$d = _test_law_load();
	pg_assert_same(6, count($d['areas']), 'six practice areas');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['areas'] as $key => $a) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "area $key: key");
		pg_assert_true(isset($d['lawyers'][$a['lead']]), "area $key: led by a lawyer of the firm");
		pg_assert_true(in_array($key, $d['lawyers'][$a['lead']]['areas'], true), "area $key: its lead works in it");
		pg_assert_true(count($a['matters']) >= 3, "area $key: matters");
		pg_assert_true(strpos($a['icon'], 'bi-') === 0, "area $key: icon");
		pg_assert_true(trim($a['text']) !== '' && trim($a['topics']) !== '', "area $key: text and topics");
	}
	foreach ($d['lawyers'] as $key => $l) {
		pg_assert_true(count($l['areas']) > 0, "lawyer $key: areas");
		foreach ($l['areas'] as $a) pg_assert_true(isset($d['areas'][$a]), "lawyer $key: known area $a");
		foreach (array('name', 'role', 'bio', 'education', 'bar', 'languages') as $f) {
			pg_assert_true(trim($l[$f]) !== '', "lawyer $key: $f");
		}
	}
	foreach ($d['results'] as $n => $r) {
		pg_assert_true(isset($d['areas'][$r['area']]), "result $n: known area");
		pg_assert_true(trim($r['outcome']) !== '', "result $n: outcome");
	}
	pg_assert_true(count($d['fees']) >= 3, 'fee models');
	// The pick lists: one answer per area plus "something else", one per
	// lawyer plus "no preference"; no answer twice.
	pg_assert_same(count($d['areas']) + 1, count($d['area_titles']), 'an answer per area');
	pg_assert_same(count($d['lawyers']) + 1, count($d['lawyer_choices']), 'an answer per lawyer');
	pg_assert_same($d['area_titles'], array_values(array_unique($d['area_titles'])), 'area answers unique');
	pg_assert_same($d['lawyer_choices'], array_values(array_unique($d['lawyer_choices'])), 'lawyer answers unique');
	// The times of day sit inside the office hours (09:00–18:00).
	foreach ($d['time_windows'] as $w) {
		pg_assert_true((bool)preg_match('/\((\d\d):\d\d–(\d\d):\d\d\)/u', $w, $m), "time window: $w");
		pg_assert_true((int)$m[1] >= 9 && (int)$m[2] <= 18, "time window inside the hours: $w");
	}
}

function test_law_shared_parts_link_template_pages()
{
	$d = _test_law_load();
	$known = array_flip(_test_law_page_keys());
	$nodes = array();
	foreach ($d['shared'] as $tree) _test_law_nodes($tree, $nodes);
	$links = 0;
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $node['props'][$prop], $m);
			foreach ($m[1] as $key) {
				$links++;
				pg_assert_true(isset($known[$key]), "link to a page of the template: $key");
			}
		}
		$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/\b(psl|pco|pev|pcl)-[a-z]/', $class), 'no class of another template: ' . $class);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// A lawyer's card opens the consultation form with that lawyer chosen, in
// the words the Lawyer list uses (the form starts from ?lawyer=).
function test_law_lawyer_card_chooses_the_lawyer()
{
	$d = _test_law_load();
	$cards = array_slice($d['shared'], 6);
	$n = 0;
	foreach ($d['lawyers'] as $key => $l) {
		$nodes = array();
		_test_law_nodes($cards[$n++], $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:consultation}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "lawyer $key: one consultation link");
		parse_str(substr($hrefs[0], strlen('{{page:consultation}}?')), $q);
		pg_assert_true(in_array($q['lawyer'], $d['lawyer_choices'], true), "lawyer $key: the link names a Lawyer answer");
	}
}

// Every {{page:}} key a tree links to, and every consultation query.
function _test_law_links($tree, &$keys, &$queries)
{
	$nodes = array();
	_test_law_nodes($tree, $nodes);
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}(\?[^#"\s]*)?/', $node['props'][$prop], $m, PREG_SET_ORDER);
			foreach ($m as $hit) {
				$keys[] = $hit[1];
				if ($hit[1] === 'consultation' && !empty($hit[2])) $queries[] = substr($hit[2], 1);
			}
		}
	}
}

function test_law_public_pages()
{
	$d = _test_law_load();
	$keys = array();
	foreach ($d['public'] as $p) {
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true(trim($p['name']) !== '' && trim($p['title']) !== '' && trim($p['meta_description']) !== '', $p['key'] . ': name, title, description');
		pg_assert_true(!preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address: ' . $p['name']);
		$keys[] = $p['key'];
	}
	$expect = array('home', 'practice');
	foreach (array_keys($d['areas']) as $a) $expect[] = 'area_' . $a;
	$expect = array_merge($expect, array('lawyers', 'results', 'fees', 'about', 'faq'));
	pg_assert_same($expect, $keys, 'the public pages in order');
	$names = array();
	foreach ($d['public'] as $p) $names[] = $p['name'];
	pg_assert_same(count($names), count(array_unique($names)), 'page names unique');

	$known = array_flip(_test_law_page_keys());
	$links = array();
	$queries = array();
	foreach ($d['public'] as $p) _test_law_links($p['tree'], $links, $queries);
	foreach (array_unique($links) as $key) pg_assert_true(isset($known[$key]), "link to a page of the template: $key");
	// Each consultation link starts the form with an answer of its lists.
	pg_assert_true(count($queries) >= count($d['areas']) + count($d['lawyers']), 'consultation links with a choice');
	foreach ($queries as $q) {
		parse_str($q, $v);
		pg_assert_true(isset($v['area']) || isset($v['lawyer']), "query names a choice: $q");
		if (isset($v['area'])) pg_assert_true(in_array($v['area'], $d['area_titles'], true), 'area is an answer: ' . $v['area']);
		if (isset($v['lawyer'])) pg_assert_true(in_array($v['lawyer'], $d['lawyer_choices'], true), 'lawyer is an answer: ' . $v['lawyer']);
	}
}

// Each area's page lists every lawyer who works in it, and its search
// section carries the area's topics.
function test_law_area_pages_list_their_lawyers()
{
	$d = _test_law_load();
	$pages = array();
	foreach ($d['public'] as $p) $pages[$p['key']] = $p;
	foreach ($d['areas'] as $key => $a) {
		$nodes = array();
		_test_law_nodes($pages['area_' . $key]['tree'], $nodes);
		$cards = array();
		$topics = false;
		foreach ($nodes as $node) {
			if (($node['props']['_label'] ?? '') === lang('Lawyer Card')) $cards[] = $node;
			if (!empty($node['props']['searchSection']) && ($node['props']['searchKeywords'] ?? '') === $a['topics']) $topics = true;
		}
		$expect = 0;
		foreach ($d['lawyers'] as $l) if (in_array($key, $l['areas'], true)) $expect++;
		pg_assert_same($expect, count($cards), "area $key: a card per lawyer of the area");
		pg_assert_true($topics, "area $key: search section with the area's topics");
	}
}

// Search sections: an id each, never the same id twice on a page.
function test_law_public_search_sections()
{
	$d = _test_law_load();
	foreach ($d['public'] as $p) {
		$nodes = array();
		_test_law_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $node) {
			if (empty($node['props']['searchSection'])) continue;
			pg_assert_true(!empty($node['props']['id']), $p['key'] . ': search section has an id');
			$ids[] = $node['props']['id'];
		}
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': search section ids unique');
	}
}

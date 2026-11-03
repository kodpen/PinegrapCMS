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
 * known pages and consultation links that name answers of the form's lists;
 * the consultation and careers part (consultation.php): its pages, forms,
 * widgets, e-mails and the placeholders they name.
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
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$consultation = include $dir . '/consultation.php';
	$out = array(
		'public'         => $public,
		'consultation'   => $consultation,
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
		'email_consultation_new', 'email_consultation_received', 'email_application_new', 'email_application_received', 'email_reply',
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

// The values of a form's control: its options' values, in order.
function _test_law_options($node)
{
	$out = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $out[] = $a['value'];
		}
	}
	return $out;
}

// The form controls of a tree by their name attribute.
function _test_law_controls($tree)
{
	$nodes = array();
	_test_law_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $out[$a['value']][] = $node;
		}
	}
	return $out;
}

function test_law_consultation_pages_and_widgets()
{
	$d = _test_law_load();
	$c = $d['consultation'];
	$keys = array();
	foreach ($c['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same(array(
		'consultation', 'consultation_view', 'my_consultations', 'staff_consultations', 'privacy', 'careers', 'application',
		'application_view', 'staff_applications', 'email_consultation_new', 'email_consultation_received',
		'email_application_new', 'email_application_received', 'email_reply',
	), $keys, 'the consultation and careers pages in order');

	$known = array_flip(_test_law_page_keys());
	$pages = array();
	foreach ($c['pages'] as $p) {
		$pages[$p['key']] = $p;
		pg_assert_true(isset($known[$p['key']]), $p['key'] . ': a page the template carries');
		if (strpos($p['key'], 'email_') === 0 || strpos($p['key'], 'staff_') === 0) {
			pg_assert_same('private', $p['folder'], $p['key'] . ': private');
		}
	}
	pg_assert_same('registration', $pages['consultation_view']['folder'], 'a consultation needs an account');
	pg_assert_true(!empty($pages['consultation_view']['comments']['attachments']), 'messages may carry a file');

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($c['pages'] as $p) {
		$nodes = array();
		_test_law_nodes($p['tree'], $nodes);
		foreach ($nodes as $node) {
			if (isset($node['props']['templateWidget'])) $placed[$node['props']['templateWidget']][] = $p['key'];
		}
	}
	foreach ($c['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}

	// Every {{tab:}} and {{page:}} of the part names a page; every
	// {{folder:}} the private folder.
	$json = json_encode($c);
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'uploads go to the private folder');
}

// The consultation form's lists are the firm's own answers, start from the
// links' query (?area=, ?lawyer=), and the status is the staff's.
function test_law_consultation_form()
{
	$d = _test_law_load();
	$w = $d['consultation']['widgets'];
	$controls = _test_law_controls($w['consultation_form']['tree']);
	foreach (array('practice_area', 'lawyer', 'client_type', 'company', 'other_party', 'summary', 'deadline', 'documents',
	               'contact_method', 'preferred_time', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($controls[$name]), "consultation form: $name");
	}
	pg_assert_same($d['area_titles'], _test_law_options($controls['practice_area'][0]), 'area answers');
	pg_assert_same($d['lawyer_choices'], _test_law_options($controls['lawyer'][0]), 'lawyer answers');
	pg_assert_same($d['time_windows'], _test_law_options($controls['preferred_time'][0]), 'time answers');
	pg_assert_same('area', $controls['practice_area'][0]['props']['_cf']['url_param'], 'area from ?area=');
	pg_assert_same('lawyer', $controls['lawyer'][0]['props']['_cf']['url_param'], 'lawyer from ?lawyer=');
	pg_assert_true(!empty($controls['status'][0]['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same('{{folder:private}}', $controls['documents'][0]['props']['_cf']['upload_folder_id'], 'documents kept private');

	$app = _test_law_controls($w['application_form']['tree']);
	foreach (array('position', 'first_name', 'last_name', 'email', 'phone', 'cv', 'cover_letter', 'status', 'consent') as $name) {
		pg_assert_true(isset($app[$name]), "application form: $name");
	}
	pg_assert_true(!empty($app['status'][0]['props']['_cf']['office_use_only']), 'application status is office use only');
	pg_assert_same('position', $app['position'][0]['props']['_cf']['url_param'], 'position from ?position=');

	// The careers page's links name a position of the form's list.
	$positions = _test_law_options($app['position'][0]);
	foreach ($d['consultation']['pages'] as $p) {
		if ($p['key'] !== 'careers') continue;
		$nodes = array();
		_test_law_nodes($p['tree'], $nodes);
		$n = 0;
		foreach ($nodes as $node) {
			$href = $node['props']['href'] ?? '';
			if (strpos($href, '{{page:application}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:application}}?')), $q);
			pg_assert_true(in_array($q['position'], $positions, true), 'careers link names a position: ' . $q['position']);
			$n++;
		}
		pg_assert_same(count($positions), $n, 'a link per position and the open application');
	}
}

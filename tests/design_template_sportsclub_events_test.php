<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's club events and sponsorship
 * enquiry (includes/design_templates/pinegrap-sportsclub/events.php): the
 * pages and widgets of the plan, the Club Events form with one sample per
 * event of parts.php, the lists (?kind=, the featured events on the home),
 * the enquiry with ?package= read from the sponsors page's links, lists,
 * pages and e-mails that read only fields their form has, links that name
 * template pages, the template's ids and classes and every literal in
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

// The page keys of the finished template and the tree helpers of the
// collections test.
require_once __DIR__ . '/design_template_sportsclub_test.php';
require_once __DIR__ . '/design_template_sportsclub_collections_test.php';

// parts.php, squad.php, mail.php and events.php in one scope, as the
// template file reads them, and the sponsors page of pages_public.php.
// Read once per run.
function _test_spe_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	include $dir . '/blocks.php';
	include $dir . '/squad.php';
	include $dir . '/mail.php';
	$part = include $dir . '/events.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$public_pages = array();
	foreach ($public['pages'] as $p) $public_pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'public' => $public_pages,
		'events' => $events, 'event_kind_titles' => $event_kind_titles, 'package_titles' => $package_titles,
		'enquiry_statuses' => $enquiry_statuses, 'featured' => $sp_featured_choices);
	return $out;
}

function test_sportsclub_events_pages_and_widgets()
{
	$d = _test_spe_load();
	pg_assert_same(array('events', 'event', 'event_new', 'sponsor_enquiry', 'staff_sponsors', 'email_sponsor_new', 'email_sponsor_received'),
		array_keys($d['pages']));
	pg_assert_same(array('event_form', 'events_list', 'home_events', 'sponsor_form', 'staff_sponsors_list', 'email_sponsor_admin',
		'email_sponsor_sender', 'event_detail'), array_keys($d['widgets']));
	foreach (array('events', 'event', 'sponsor_enquiry') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('event_new', 'staff_sponsors', 'email_sponsor_new', 'email_sponsor_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach ($d['widgets'] as $key => $w) {
		$tree = $w['page'] === 'home' ? $d['public']['home']['tree'] : $d['pages'][$w['page']]['tree'];
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($tree), "$key is placed on its page");
	}
}

// The Club Events form is a collection with one sample per event; every
// sample field has its control and is filled, a pick list's value is one
// of its answers and featured is the club's.
function test_sportsclub_events_form_and_samples()
{
	$d = _test_spe_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$c = _test_spc_controls($d['widgets']['event_form']['tree']);
	pg_assert_same(array('title', 'kind', 'place', 'event_when', 'summary', 'details', 'featured'), array_keys($c), 'its fields');
	pg_assert_same($d['event_kind_titles'], $c['kind']['options'], 'the kinds');
	pg_assert_same('text', $c['event_when']['type'], 'when is words');
	pg_assert_true(!empty($c['featured']['cf']['office_use_only']), 'featured is the club\'s');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $name => $value) {
			pg_assert_true(isset($c[$name]), "sample $n: field $name exists");
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($c[$name]['tag'] === 'select') pg_assert_true(in_array($value, $c[$name]['options'], true), "sample $n: $name '$value' is an answer");
		}
		if ($r['featured'] === $d['featured'][0]) $featured++;
	}
	pg_assert_true($featured >= 1 && $featured <= 3, 'the home shows the featured samples');
}

// The lists: ?kind= on the events page with a link per kind, the featured
// events on the home; the enquiry with ?package= from the sponsors page.
function test_sportsclub_events_filters_and_links()
{
	$d = _test_spe_load();
	$w = $d['widgets'];
	$kind = array('field' => 'kind', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'kind');
	pg_assert_same(array($kind), $w['events_list']['config']['filters'], 'the events by kind');
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => '')),
		$w['home_events']['config']['filters'], 'the home shows the featured events');
	foreach (array('events_list', 'home_events') as $key) {
		pg_assert_same('{{tab:event_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Club Events form");
		pg_assert_same('{{tab:event}}', $w[$key]['config']['detail_page_id'], "$key opens the event page");
	}
	$links = function ($tree, $page_key) {
		$nodes = array();
		_test_spc_nodes($tree, $nodes);
		$out = array();
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page_key . '}}?')), $q);
			$out[] = $q;
		}
		return $out;
	};
	$seen = array();
	foreach ($links($d['pages']['events']['tree'], 'events') as $q) {
		pg_assert_same(array('kind'), array_keys($q), 'the events link sets ?kind=');
		pg_assert_true(in_array($q['kind'], $d['event_kind_titles'], true), "?kind={$q['kind']} is an answer");
		$seen[$q['kind']] = true;
	}
	pg_assert_same(count($d['event_kind_titles']), count($seen), 'a link per kind');
	$c = _test_spc_controls($w['sponsor_form']['tree']);
	pg_assert_same('package', $c['package']['cf']['url_param'] ?? '', 'the package reads ?package=');
	pg_assert_same($d['package_titles'], $c['package']['options'], 'the packages');
	$seen = array();
	foreach ($links($d['public']['sponsors']['tree'], 'sponsor_enquiry') as $q) {
		pg_assert_true(in_array($q['package'] ?? '', $c['package']['options'], true), 'a package link names an answer');
		$seen[$q['package']] = true;
	}
	pg_assert_same(count($d['package_titles']), count($seen), 'every package card opens the enquiry with its package');
}

// The enquiry form, its list and e-mails, and the event page read only
// fields of their form.
function test_sportsclub_events_enquiry_and_bindings()
{
	$d = _test_spe_load();
	$form = $d['pages']['sponsor_enquiry']['form'];
	pg_assert_false(isset($form['collection']) || isset($form['auto_registration']), 'an enquiry is a request without an account');
	pg_assert_same('{{tab:email_sponsor_new}}', $form['notify_page_id'], 'the club\'s e-mail');
	pg_assert_same('{{tab:email_sponsor_received}}', $form['confirm_page_id'], 'the sender\'s e-mail');
	$c = _test_spc_controls($d['widgets']['sponsor_form']['tree']);
	pg_assert_same(array('business', 'package', 'message', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($c), 'its fields');
	pg_assert_same($d['enquiry_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the club\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['sponsor_form']['tree']), 'the consent links the privacy page');
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found');
	$readers = array(
		'sponsor_form' => array('staff_sponsors_list', 'email_sponsor_admin', 'email_sponsor_sender'),
		'event_form'   => array('events_list', 'home_events', 'event_detail'),
	);
	foreach ($readers as $form_widget => $keys) {
		$fields = array_keys(_test_spc_controls($d['widgets'][$form_widget]['tree']));
		foreach ($keys as $key) {
			foreach (_test_spc_tokens($d['widgets'][$key]['tree']) as $t) {
				pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of $form_widget");
			}
			foreach ((array)($d['widgets'][$key]['config']['search_fields'] ?? array()) as $f) {
				pg_assert_true(in_array($f, $fields, true), "$key searches $f, a field of $form_widget");
			}
		}
	}
	pg_assert_false(isset($d['widgets']['staff_sponsors_list']['config']['detail_page_id']), 'an enquiry has no page of its own');
	foreach (array('email_sponsor_admin', 'email_sponsor_sender') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the enquiry just sent");
	}
	$json = json_encode($d['widgets']['event_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the event');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('__edit_url', $json, 'the staff\'s Edit button');
}

// Every link names a template page; ids and classes are the template's;
// no fixed date; every literal has its Turkish.
function test_sportsclub_events_links_ids_and_words()
{
	$d = _test_spe_load();
	$known = array_flip(array_merge(_test_sp_page_keys(), array_keys($d['pages'])));
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_sp_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_spc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_spc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sp-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/events.php');
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', preg_replace('/@copyright[^\n]*/', '', $src)), 'no year');
	$map = pg_test_language_map('tr');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (isset($map[$key]) && preg_match('/\bsponsor/i', $key)) pg_assert_true(mb_stripos($map[$key], 'sponsor', 0, 'UTF-8') !== false, "'$key' says sponsor");
	}
}

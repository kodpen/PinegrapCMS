<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Events and Conferences design template's parts
 * (includes/design_templates/pinegrap-events/): the conference's data hangs
 * together (tracks, speakers and their sessions, passes), the public pages
 * load with their keys and root trees, every page link names a page of the
 * finished template, the anchors a link or a search result opens exist and
 * are unique, no fixed date is written into the sample, and the home page
 * places the Google Reviews widget beside its sample testimonials.
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
function _test_events_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-events';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$out = array(
		'tracks'    => $tracks,
		'speakers'  => $speakers,
		'programme' => $programme,
		'kinds'     => $session_kinds,
		'passes'    => $passes,
		'sponsors'  => $sponsors,
		'pages'     => $public['pages'],
		'widgets'   => $public['widgets'],
		'shared'    => array($account_bar_tree(), $header(), $footer(), $register_band(), $notice()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_events_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_events_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_events_page_keys()
{
	return array(
		'home', 'programme', 'speakers', 'venue', 'about',
		'events', 'event_view', 'event_new', 'registration', 'registration_view', 'my_registrations', 'privacy',
		'tickets', 'product', 'cart',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_events_data_hangs_together()
{
	$d = _test_events_load();
	$sessions = array();
	foreach ($d['programme'] as $day => $p) {
		pg_assert_true(count($p['sessions']) > 3, "$day has sessions");
		foreach ($p['sessions'] as $s) {
			pg_assert_false(isset($sessions[$s['key']]), 'session key ' . $s['key'] . ' is unique');
			$sessions[$s['key']] = $s;
			pg_assert_true(isset($d['kinds'][$s['kind']]), $s['key'] . ': known kind');
			pg_assert_true($s['track'] === '' || isset($d['tracks'][$s['track']]), $s['key'] . ': known track');
			pg_assert_true($s['speaker'] === '' || isset($d['speakers'][$s['speaker']]), $s['key'] . ': known speaker');
			pg_assert_true((bool)preg_match('/^\d{2}:\d{2}$/', $s['time']), $s['key'] . ': time');
		}
	}
	foreach ($d['speakers'] as $key => $s) {
		pg_assert_true(isset($d['tracks'][$s['track']]), "speaker $key: known track");
		pg_assert_true(isset($sessions[$s['session']]), "speaker $key: session in the programme");
		pg_assert_same($key, $sessions[$s['session']]['speaker'], "speaker $key: the session is theirs");
	}
	$skus = array();
	foreach ($d['passes'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^EVENTS-PASS-[A-Z]+$/', $p['sku']), "pass $key: SKU");
		$skus[] = $p['sku'];
		pg_assert_true(count($p['items']) > 0, "pass $key: what it includes");
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'pass SKUs are unique');
	pg_assert_true(isset($d['sponsors']['headline'], $d['sponsors']['gold'], $d['sponsors']['partner']), 'sponsor tiers');
}

function test_events_public_pages_load()
{
	$d = _test_events_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['meta_description']), 'page has key, name, title, tree, description');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('home', 'programme', 'speakers', 'venue', 'about'), $keys);
}

// A {{page:<key>}} placeholder anywhere in a page or shared part names a
// page of the finished template.
function test_events_page_links_name_template_pages()
{
	$d = _test_events_load();
	$known = array_flip(_test_events_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$json = json_encode($trees);
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Every id on a page is unique, and a link to an anchor of one of these
// pages (#speaker-elif, #session-d1-keynote, #track-product, #sponsors)
// finds it there.
function test_events_anchors_exist_and_are_unique()
{
	$d = _test_events_load();
	$ids = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_events_nodes($p['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$ids[$p['key']] = $seen;
	}
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	preg_match_all('/\{\{page:(home|programme|speakers|venue|about)\}\}#([a-z0-9-]+)/', json_encode($trees), $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 8, 'anchor links found');
	foreach ($m as $hit) pg_assert_true(isset($ids[$hit[1]][$hit[2]]), "#{$hit[2]} exists on {$hit[1]}");
}

// The sample never reads as a past edition: no year and no calendar date
// is written into a page, and every widget the pages place is one of the
// finished template's.
function test_events_no_fixed_dates()
{
	$d = _test_events_load();
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

function test_events_home_places_widgets()
{
	$d = _test_events_load();
	$home = $d['pages'][0];
	$nodes = array();
	_test_events_nodes($home['tree'], $nodes);
	$placed = array();
	foreach ($nodes as $n) if (isset($n['props']['templateWidget'])) $placed[] = $n['props']['templateWidget'];
	foreach (array('google_reviews', 'home_events', 'home_posts', 'newsletter_band') as $w) {
		pg_assert_true(in_array($w, $placed, true), "home places $w");
	}
	$g = $d['widgets']['google_reviews'];
	pg_assert_same('google_reviews', $g['config']['regionType']);
	pg_assert_same('home', $g['page']);
	$json = json_encode($g['tree']);
	foreach (array('has_reviews', '__review_author_url', '__review_author_name', '__review_url', '__place_maps_url', '__google_attribution') as $token) {
		pg_assert_true(strpos($json, '"' . $token . '"') !== false, "google_reviews binds $token");
	}
}

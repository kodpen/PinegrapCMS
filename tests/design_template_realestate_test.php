<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Real Estate Agency design template's parts
 * (includes/design_templates/pinegrap-realestate/): the agency's data hangs
 * together (neighbourhoods known by an agent who works in them, agents in
 * known neighbourhoods, sample listings naming a known deal, type,
 * neighbourhood, status and agent, the request forms' pick lists), every
 * page link of the shared parts names a page of the finished template, the
 * shared parts carry no class of another template, a listing card reads
 * the Listings form's fields and an agent card opens the valuation form
 * with the agent chosen.
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
function _test_re_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate';
	include $dir . '/parts.php';
	$agent_cards = array();
	foreach (array_keys($agents) as $key) $agent_cards[$key] = $agent_card($key);
	$listing_cards = array();
	foreach (array_keys($listings) as $key) $listing_cards[$key] = $listing_card($key);
	$out = array(
		'areas'          => $areas,
		'deals'          => $deals,
		'types'          => $property_types,
		'statuses'       => $listing_statuses,
		'agents'         => $agents,
		'figures'        => $figures,
		'services'       => $services,
		'listings'       => $listings,
		'area_titles'    => $area_titles,
		'type_titles'    => $type_titles,
		'agent_choices'  => $agent_choices,
		'time_windows'   => $time_windows,
		'agent_cards'    => $agent_cards,
		'listing_cards'  => $listing_cards,
		'shared'         => array($account_bar_tree(), $header(), $footer(), $valuation_band(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_re_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_re_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_re_page_keys()
{
	$keys = array(
		'home', 'neighbourhoods', 'agents', 'services', 'about', 'faq', 'guide',
		'listing_new', 'buy', 'rent', 'listings', 'listing',
		'viewing', 'viewing_view', 'my_viewings', 'staff_viewings',
		'valuation', 'valuation_view', 'my_valuations', 'staff_valuations', 'privacy',
		'email_viewing_new', 'email_viewing_received', 'email_valuation_new', 'email_valuation_received', 'email_reply',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array_keys(_test_re_load()['areas']) as $key) $keys[] = 'area_' . $key;
	return $keys;
}

function test_realestate_data_hangs_together()
{
	$d = _test_re_load();
	pg_assert_same(5, count($d['areas']), 'five neighbourhoods');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['areas'] as $key => $a) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "area $key: key");
		pg_assert_true(isset($d['agents'][$a['agent']]), "area $key: known by an agent of the agency");
		pg_assert_true(in_array($key, $d['agents'][$a['agent']]['areas'], true), "area $key: its agent works in it");
		pg_assert_true(count($a['highlights']) >= 3, "area $key: highlights");
		pg_assert_true(strpos($a['icon'], 'bi-') === 0, "area $key: icon");
		foreach (array('title', 'text', 'price', 'topics') as $f) pg_assert_true(trim($a[$f]) !== '', "area $key: $f");
	}
	foreach ($d['agents'] as $key => $a) {
		pg_assert_true(count($a['areas']) > 0, "agent $key: areas");
		foreach ($a['areas'] as $k) pg_assert_true(isset($d['areas'][$k]), "agent $key: known area $k");
		foreach (array('name', 'role', 'bio', 'languages', 'phone') as $f) pg_assert_true(trim($a[$f]) !== '', "agent $key: $f");
		pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $a['phone']), "agent $key: phone number");
	}
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(in_array($s['page'], _test_re_page_keys(), true), "service $key: a page of the template");
		pg_assert_true(count($s['points']) >= 3, "service $key: points");
	}
	pg_assert_same(4, count($d['statuses']), 'four listing states');
	pg_assert_same(6, count($d['listings']), 'six sample listings');
	$deals = array();
	foreach ($d['listings'] as $key => $l) {
		pg_assert_true(isset($d['deals'][$l['deal']]), "listing $key: known deal");
		pg_assert_true(isset($d['types'][$l['type']]), "listing $key: known type");
		pg_assert_true(isset($d['areas'][$l['area']]), "listing $key: known neighbourhood");
		pg_assert_true(isset($d['agents'][$l['agent']]), "listing $key: known agent");
		pg_assert_true(isset($d['statuses'][$l['status']]), "listing $key: known status");
		// A home for sale is never shown as rented, nor one for rent as sold.
		pg_assert_true($l['status'] !== ($l['deal'] === 'sale' ? 3 : 2), "listing $key: a status of its deal");
		pg_assert_true(is_int($l['price']) && $l['price'] > 0, "listing $key: price is a number");
		pg_assert_true(strpos($l['price_label'], number_format($l['price'])) !== false, "listing $key: the label shows the price");
		foreach (array('bedrooms', 'bathrooms', 'size') as $f) pg_assert_true(is_int($l[$f]) && $l[$f] >= 0, "listing $key: $f");
		pg_assert_true(count($l['features']) >= 3, "listing $key: features");
		$deals[$l['deal']] = true;
	}
	pg_assert_same(2, count($deals), 'samples for sale and for rent');
	// The pick lists: one answer per neighbourhood and type, one per agent
	// plus "no preference"; no answer twice.
	pg_assert_same(count($d['areas']), count($d['area_titles']), 'an answer per neighbourhood');
	pg_assert_same(count($d['types']), count($d['type_titles']), 'an answer per type');
	pg_assert_same(count($d['agents']) + 1, count($d['agent_choices']), 'an answer per agent');
	foreach (array('area_titles', 'type_titles', 'agent_choices') as $list) {
		pg_assert_same($d[$list], array_values(array_unique($d[$list])), "$list unique");
	}
	// The times of day sit inside the office hours (09:00–19:00).
	foreach ($d['time_windows'] as $w) {
		pg_assert_true((bool)preg_match('/\((\d\d):\d\d–(\d\d):\d\d\)/u', $w, $m), "time window: $w");
		pg_assert_true((int)$m[1] >= 9 && (int)$m[2] <= 19, "time window inside the hours: $w");
	}
}

function test_realestate_shared_parts_link_template_pages()
{
	$d = _test_re_load();
	$known = array_flip(_test_re_page_keys());
	$nodes = array();
	foreach (array_merge($d['shared'], array_values($d['agent_cards']), array_values($d['listing_cards'])) as $tree) _test_re_nodes($tree, $nodes);
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
		pg_assert_false((bool)preg_match('/\b(plw|psl|pco|pev|pcl)-[a-z]/', $class), 'no class of another template: ' . $class);
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^lw-/', $id), 'no field id of another template: ' . $id);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// A listing card reads every value from the Listings form's fields, with the
// sample's own words on the canvas.
function test_realestate_listing_card_reads_the_listing()
{
	$d = _test_re_load();
	foreach ($d['listing_cards'] as $key => $card) {
		$nodes = array();
		_test_re_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('cover', 'title', 'form_item_view', 'deal', 'status', 'price_label', 'area', 'bedrooms', 'bathrooms', 'size') as $t) {
			pg_assert_true(isset($tokens[$t]), "listing $key: card reads $t");
		}
		$l = $d['listings'][$key];
		pg_assert_true(in_array($l['title'], $texts, true), "listing $key: sample title on the canvas");
		pg_assert_true(in_array($l['price_label'], $texts, true), "listing $key: sample price on the canvas");
		pg_assert_true(in_array($d['areas'][$l['area']]['title'], $texts, true), "listing $key: sample neighbourhood on the canvas");
	}
}

// An agent's card opens the valuation form with that agent chosen, in the
// words the Agent list uses (the form starts from ?agent=).
function test_realestate_agent_card_chooses_the_agent()
{
	$d = _test_re_load();
	foreach ($d['agent_cards'] as $key => $card) {
		$nodes = array();
		_test_re_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:valuation}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "agent $key: one valuation link");
		parse_str(substr($hrefs[0], strlen('{{page:valuation}}?')), $q);
		pg_assert_true(in_array($q['agent'], $d['agent_choices'], true), "agent $key: the link names an Agent answer");
	}
}

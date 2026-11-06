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
 * with the agent chosen; the public pages (pages_public.php): their keys,
 * one page per neighbourhood, links to known pages, filter links that name
 * answers of the Listings form's lists, the listing widgets they place; the
 * listings (listings.php): the Listings collection and its sample records,
 * the lists' filters and the widgets the other pages place; the requests
 * (mail.php, requests.php): their pages and folders, every widget placed
 * once on its page, the forms' fields, pick lists and URL choices, the
 * staff-only status, private uploads and the lists reading their form.
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
	$public = include $dir . '/pages_public.php';
	$listing_part = include $dir . '/listings.php';
	include $dir . '/mail.php';
	$requests = include $dir . '/requests.php';
	$out = array(
		'requests'       => $requests,
		'public'         => $public,
		'listing_part'   => $listing_part,
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
		'email_new_message', 'email_message_received', 'email_welcome',
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

// Every {{page:}} key a tree links to, with the query each link carries.
function _test_re_links($tree, &$keys, &$queries)
{
	$nodes = array();
	_test_re_nodes($tree, $nodes);
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}(\?[^#"\s]*)?/', $node['props'][$prop], $m, PREG_SET_ORDER);
			foreach ($m as $hit) {
				$keys[] = $hit[1];
				if (!empty($hit[2])) {
					parse_str(substr($hit[2], 1), $q);
					$queries[] = array($hit[1], $q);
				}
			}
		}
	}
}

// The template widgets a tree places.
function _test_re_widgets($tree)
{
	$nodes = array();
	_test_re_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

function test_realestate_public_pages()
{
	$d = _test_re_load();
	$keys = array();
	$names = array();
	foreach ($d['public'] as $p) {
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true(trim($p['name']) !== '' && trim($p['title']) !== '' && trim($p['meta_description']) !== '', $p['key'] . ': name, title, description');
		pg_assert_true(!preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address: ' . $p['name']);
		$keys[] = $p['key'];
		$names[] = $p['name'];
	}
	$expect = array('home', 'neighbourhoods');
	foreach (array_keys($d['areas']) as $a) $expect[] = 'area_' . $a;
	$expect = array_merge($expect, array('agents', 'services', 'about', 'faq', 'guide'));
	pg_assert_same($expect, $keys, 'the public pages in order');
	pg_assert_same(count($names), count(array_unique($names)), 'page names unique');

	$known = array_flip(_test_re_page_keys());
	$links = array();
	$queries = array();
	foreach ($d['public'] as $p) _test_re_links($p['tree'], $links, $queries);
	foreach ($links as $key) pg_assert_true(isset($known[$key]), "public pages link a page of the template: $key");
	// A filter or choice link names an answer of the list it fills, so the
	// list or the form starts from words it knows.
	$answers = array(
		'deal'  => array_values($d['deals']),
		'type'  => $d['type_titles'],
		'area'  => $d['area_titles'],
		'agent' => $d['agent_choices'],
	);
	pg_assert_true(count($queries) > 10, 'filter and choice links');
	foreach ($queries as $hit) {
		pg_assert_true(in_array($hit[0], array('listings', 'valuation'), true), 'a query only on the listings or the valuation form: ' . $hit[0]);
		foreach ($hit[1] as $param => $value) {
			pg_assert_true(isset($answers[$param]), "known parameter: $param");
			pg_assert_true(in_array($value, $answers[$param], true), "$param names an answer: $value");
		}
	}
}

// The home places the featured listings, each neighbourhood its own list;
// every neighbourhood page lists the agents who work there.
function test_realestate_public_pages_place_the_listings()
{
	$d = _test_re_load();
	$by_key = array();
	foreach ($d['public'] as $p) $by_key[$p['key']] = $p;
	$home = _test_re_widgets($by_key['home']['tree']);
	foreach (array('home_listings', 'home_posts', 'google_reviews', 'newsletter_band') as $w) {
		pg_assert_true(in_array($w, $home, true), "home places $w");
	}
	foreach ($d['areas'] as $key => $a) {
		pg_assert_true(in_array('area_listings_' . $key, _test_re_widgets($by_key['area_' . $key]['tree']), true), "area $key: its listings");
		$nodes = array();
		_test_re_nodes($by_key['area_' . $key]['tree'], $nodes);
		$cards = 0;
		foreach ($nodes as $node) {
			if (isset($node['props']['_label']) && $node['props']['_label'] === lang('Agent Card')) $cards++;
		}
		$expect = 0;
		foreach ($d['agents'] as $ag) $expect += in_array($key, $ag['areas'], true) ? 1 : 0;
		pg_assert_same($expect, $cards, "area $key: its agents");
	}
}

// The controls of a widget tree by field name: tag, kind, options.
function _test_re_controls($tree)
{
	$nodes = array();
	_test_re_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '') continue;
		$options = array();
		foreach ($node['children'] ?? array() as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $node['props']['tag'], 'kind' => $node['props']['_cf']['kind'] ?? '', 'options' => $options);
	}
	return $out;
}

function test_realestate_listings_form_and_samples()
{
	$d = _test_re_load();
	$pages = array();
	foreach ($d['listing_part']['pages'] as $p) $pages[$p['key']] = $p;
	pg_assert_same(array('buy', 'rent', 'listings', 'listing', 'listing_new'), array_keys($pages), 'the listing pages in order');
	$form = $pages['listing_new']['form'];
	pg_assert_same(1, $form['collection'], 'Listings is a collection');
	pg_assert_same('private', $pages['listing_new']['folder'], 'the staff page is private');
	$controls = _test_re_controls($d['listing_part']['widgets']['listing_form']['tree']);
	foreach (array('price', 'bedrooms', 'bathrooms', 'size') as $f) pg_assert_same('number', $controls[$f]['kind'], "$f is a number");
	foreach (array('cover', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['kind'], "$f is an image");
	pg_assert_same(array_values($d['deals']), $controls['deal']['options'], 'deal answers');
	pg_assert_same($d['type_titles'], $controls['type']['options'], 'type answers');
	pg_assert_same($d['area_titles'], $controls['area']['options'], 'neighbourhood answers');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'status answers');
	pg_assert_same(count($d['listings']), count($form['sample_records']), 'a record per sample listing');
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $field => $value) pg_assert_true(isset($controls[$field]), "sample $n: $field is a field of the form");
		foreach (array('deal', 'type', 'area', 'status', 'agent') as $f) {
			pg_assert_true(in_array($r[$f], $controls[$f]['options'], true), "sample $n: $f is an answer of its list");
		}
		foreach (array('price', 'bedrooms', 'bathrooms', 'size') as $f) pg_assert_true(ctype_digit($r[$f]), "sample $n: $f is a plain number");
	}
	pg_assert_same('cover', $form['share_image_field'], 'the cover is the share image');
}

// Every list reads the Listings form, filters on its fields, takes its
// URL values under names a list may read, and every listing widget the
// public pages place is defined here, on the page that places it.
function test_realestate_listing_widgets()
{
	$d = _test_re_load();
	$widgets = $d['listing_part']['widgets'];
	$controls = _test_re_controls($widgets['listing_form']['tree']);
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	$pages = array();
	foreach (array_merge($d['public'], $d['listing_part']['pages']) as $p) $pages[$p['key']] = $p;
	foreach ($widgets as $key => $w) {
		pg_assert_true(isset($pages[$w['page']]), "$key: on a page of the template");
		pg_assert_true(in_array($key, _test_re_widgets($pages[$w['page']]['tree']), true), "$key: placed by its page");
		if ($w['config']['regionType'] !== 'form_list_view') continue;
		pg_assert_same('{{tab:listing_new}}', $w['config']['custom_form_page_id'], "$key: reads Listings");
		pg_assert_same('{{tab:listing}}', $w['config']['detail_page_id'], "$key: opens the listing page");
		foreach ($w['config']['filters'] ?? array() as $f) {
			pg_assert_true(isset($controls[$f['field']]), "$key: filters on a field of the form: " . $f['field']);
			if ($f['dynamic_value'] === 'url parameter') {
				pg_assert_true((bool)preg_match('/^[a-z][a-z0-9_]{0,31}$/', $f['dynamic_value_attribute']), "$key: parameter name");
				pg_assert_false(in_array($f['dynamic_value_attribute'], $reserved, true), "$key: parameter not reserved");
			} elseif ($f['value'] !== '') {
				pg_assert_true(in_array($f['value'], $controls[$f['field']]['options'], true), "$key: fixed value is an answer: " . $f['value']);
			}
		}
		foreach ($w['config']['search_fields'] ?? array() as $f) pg_assert_true(isset($controls[$f]), "$key: searches a field: $f");
	}
	// The widgets the public pages place are all here.
	foreach ($d['public'] as $p) {
		foreach (_test_re_widgets($p['tree']) as $w) {
			if (strpos($w, 'listings') === false) continue;
			pg_assert_true(isset($widgets[$w]), "{$p['key']}: places a defined listing widget: $w");
		}
	}
	// The listing page's viewing button sends the listing's title.
	$nodes = array();
	_test_re_nodes($widgets['listing_detail']['tree'], $nodes);
	$sends = 0;
	foreach ($nodes as $node) {
		if (($node['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		pg_assert_same('{{tab:viewing}}', $node['props']['_bindParams']['href']['page_id'], 'viewing link');
		pg_assert_same(array(array('name' => 'listing', 'token' => 'title')), $node['props']['_bindParams']['href']['params'], 'sends the title');
		$sends++;
	}
	pg_assert_same(1, $sends, 'one viewing button');
	// The filter links of the list pages name answers of the lists.
	$links = array();
	$queries = array();
	foreach ($d['listing_part']['pages'] as $p) _test_re_links($p['tree'], $links, $queries);
	$known = array_flip(_test_re_page_keys());
	foreach ($links as $k) pg_assert_true(isset($known[$k]), "listing pages link a page of the template: $k");
	foreach ($queries as $hit) {
		foreach ($hit[1] as $param => $value) {
			pg_assert_true(isset($controls[$param]) && in_array($value, $controls[$param]['options'], true), "$param names an answer: $value");
		}
	}
}

// Sold and rented homes leave every list a visitor browses: the deal
// lists and All Listings hide both closed states, the home and
// neighbourhood lists show only homes on the market.
function test_realestate_lists_hide_closed_listings()
{
	$d = _test_re_load();
	$closed = array($d['statuses'][2], $d['statuses'][3]);
	foreach ($d['listing_part']['widgets'] as $key => $w) {
		if ($w['config']['regionType'] !== 'form_list_view') continue;
		$hidden = array();
		foreach ($w['config']['filters'] ?? array() as $f) {
			if ($f['field'] !== 'status' || $f['dynamic_value'] === 'url parameter') continue;
			if ($f['operator'] === 'is not equal to') $hidden[] = $f['value'];
			if ($f['operator'] === 'is equal to' && $f['value'] === $d['statuses'][0]) $hidden = $closed;
		}
		foreach ($closed as $status) pg_assert_true(in_array($status, $hidden, true), "$key: hides $status");
	}
}

// The props of each named control of a widget tree, by field name.
function _test_re_control_props($tree)
{
	$nodes = array();
	_test_re_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name' && in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) $out[$a['value']] = $node['props'];
		}
	}
	return $out;
}

function test_realestate_request_pages_and_widgets()
{
	$d = _test_re_load();
	$r = $d['requests'];
	$keys = array();
	foreach ($r['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same(array(
		'viewing', 'viewing_view', 'my_viewings', 'staff_viewings',
		'valuation', 'valuation_view', 'my_valuations', 'staff_valuations', 'privacy',
		'email_viewing_new', 'email_viewing_received', 'email_valuation_new', 'email_valuation_received', 'email_reply',
	), $keys, 'the request pages in order');

	$known = array_flip(_test_re_page_keys());
	$pages = array();
	foreach ($r['pages'] as $p) {
		$pages[$p['key']] = $p;
		pg_assert_true(isset($known[$p['key']]), $p['key'] . ': a page the template carries');
		pg_assert_true(trim($p['name']) !== '' && !preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address');
		if (strpos($p['key'], 'email_') === 0 || strpos($p['key'], 'staff_') === 0) {
			pg_assert_same('private', $p['folder'], $p['key'] . ': private');
		}
	}
	foreach (array('viewing', 'valuation') as $form) {
		pg_assert_same('registration', $pages[$form . '_view']['folder'], "$form: its page needs an account");
		pg_assert_same('registration', $pages['my_' . $form . 's']['folder'], "$form: My list needs an account");
		pg_assert_true(!empty($pages[$form . '_view']['comments']['attachments']), "$form: messages may carry a file");
		pg_assert_same('{{tab:email_reply}}', $pages[$form . '_view']['comments']['email_page'], "$form: messages mailed with the reply e-mail");
		$f = $pages[$form]['form'];
		pg_assert_same('public', $pages[$form]['folder'], "$form: anyone may ask");
		pg_assert_same(1, $f['auto_registration'], "$form: opens an account");
		pg_assert_same('{{tab:' . $form . '_view}}', $f['confirmation_page_id'], "$form: opens its page");
		pg_assert_same('{{tab:email_' . $form . '_new}}', $f['notify_page_id'], "$form: staff e-mail");
		pg_assert_same('{{tab:email_' . $form . '_received}}', $f['confirm_page_id'], "$form: client e-mail");
	}

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($r['pages'] as $p) {
		foreach (_test_re_widgets($p['tree']) as $w) $placed[$w][] = $p['key'];
	}
	foreach ($r['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}
	// The newsletter band of a public page is the standard part's.
	unset($placed['newsletter_band']);
	foreach ($placed as $key => $on) pg_assert_true(isset($r['widgets'][$key]), "placed widget $key is defined");

	// The lists and views read their own form and open its page.
	foreach ($r['widgets'] as $key => $w) {
		$type = $w['config']['regionType'];
		if ($type === 'form_list_view') {
			$form = strpos($key, 'viewing') !== false ? 'viewing' : 'valuation';
			pg_assert_same('{{tab:' . $form . '}}', $w['config']['custom_form_page_id'], "$key: reads its form");
			pg_assert_same('{{tab:' . $form . '_view}}', $w['config']['detail_page_id'], "$key: opens its page");
			$controls = _test_re_control_props($r['widgets'][$form . '_form']['tree']);
			foreach ($w['config']['search_fields'] ?? array() as $f) pg_assert_true(isset($controls[$f]), "$key: searches a field: $f");
		}
	}
	pg_assert_true(!empty($r['widgets']['my_viewings_list']['config']['viewer_filter_submitter']), 'My Viewings shows the client\'s own');
	pg_assert_same('submitter_only', $r['widgets']['valuation_detail']['config']['access_control'], 'a valuation is the owner\'s to see');

	// Every {{tab:}} and {{page:}} names a page; every {{folder:}} the
	// private folder.
	$json = json_encode($r);
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'uploads go to the private folder');
}

// The forms' lists are the agency's own answers, start from the links'
// query (?listing=, ?area=, ?agent=), and the status is the staff's.
function test_realestate_request_forms()
{
	$d = _test_re_load();
	$w = $d['requests']['widgets'];
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();

	$vw = _test_re_controls($w['viewing_form']['tree']);
	$vwp = _test_re_control_props($w['viewing_form']['tree']);
	foreach (array('listing', 'viewing_date', 'preferred_time', 'note', 'contact_method', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($vw[$name]), "viewing form: $name");
	}
	// The listing page's button sends ?listing= with the title.
	pg_assert_same('listing', $vwp['listing']['_cf']['url_param'], 'listing from ?listing=');
	pg_assert_same($d['time_windows'], $vw['preferred_time']['options'], 'viewing time answers');

	$va = _test_re_controls($w['valuation_form']['tree']);
	$vap = _test_re_control_props($w['valuation_form']['tree']);
	foreach (array('purpose', 'area', 'property_type', 'address', 'bedrooms', 'size', 'description', 'documents',
	               'agent', 'preferred_time', 'contact_method', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($va[$name]), "valuation form: $name");
	}
	pg_assert_same($d['area_titles'], $va['area']['options'], 'neighbourhood answers');
	pg_assert_same($d['type_titles'], $va['property_type']['options'], 'type answers');
	pg_assert_same($d['agent_choices'], $va['agent']['options'], 'agent answers');
	pg_assert_same('area', $vap['area']['_cf']['url_param'], 'area from ?area=');
	pg_assert_same('agent', $vap['agent']['_cf']['url_param'], 'agent from ?agent=');
	foreach (array('bedrooms', 'size') as $f) pg_assert_same('number', $va[$f]['kind'], "$f is a number");
	pg_assert_same('{{folder:private}}', $vap['documents']['_cf']['upload_folder_id'], 'papers kept private');

	foreach (array('viewing' => $vwp, 'valuation' => $vap) as $form => $props) {
		pg_assert_true(!empty($props['status']['_cf']['office_use_only']), "$form: status is office use only");
		foreach ($props as $name => $p) {
			if (isset($p['_cf']['url_param'])) pg_assert_false(in_array($p['_cf']['url_param'], $reserved, true), "$form: $name parameter not reserved");
		}
	}
	// Every field id carries the template's prefix and is unique.
	$ids = array();
	foreach (array('viewing_form', 'valuation_form') as $key) {
		$nodes = array();
		_test_re_nodes($w[$key]['tree'], $nodes);
		foreach ($nodes as $node) {
			if (isset($node['props']['id']) && in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) $ids[] = $node['props']['id'];
		}
	}
	foreach ($ids as $id) pg_assert_same(0, strpos($id, 're-'), "field id prefixed: $id");
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');
}

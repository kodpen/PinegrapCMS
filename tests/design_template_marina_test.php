<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marina design template's parts
 * (includes/design_templates/pinegrap-marina/): the harbour's data hangs
 * together (kinds of berth with their points, a rate for every kind and
 * season, sample charter boats naming a known type and skipper choice,
 * crews speaking about a kind, the forms' pick lists without an answer
 * twice), every page link of the shared parts names a page of the
 * finished template, the shared parts carry no cart, the footer opens the
 * boat list filtered and links every kind's page, the boat card reads its
 * form's fields, the rate table opens the berth request per season, the
 * classes are the template's own, the sample carries no fixed date and
 * every literal has its Turkish.
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
function _test_mn_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/parts.php';
	$kind_cards = array();
	$kind_fact_lists = array();
	foreach (array_keys($kinds) as $key) {
		$kind_cards[$key] = $kind_card($key);
		$kind_fact_lists[$key] = $kind_facts($key);
	}
	$boat_cards = array();
	$boat_fact_lists = array();
	$boat_words = array();
	foreach (array_keys($boats) as $key) {
		$boat_cards[$key] = $boat_card($key);
		$boat_fact_lists[$key] = $boat_facts($key);
		$boat_words[$key] = array('type' => $boat_type($key), 'skipper' => $boat_skipper($key), 'price' => $boat_price($key));
	}
	$feature_cards = array();
	foreach (array_merge($services, $facilities, $approach, $directions, $rules, $why_us) as $item) $feature_cards[] = $feature_card($item);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'harbour'           => $harbour,
		'kinds'             => $kinds,
		'seasons'           => $seasons,
		'rates'             => $rates,
		'extras'            => $extras,
		'rate_includes'     => $rate_includes,
		'rate_note'         => $rate_note,
		'money'             => $money,
		'season_rate'       => $season_rate,
		'from_rate'         => $from_rate,
		'boat_types'        => $boat_types,
		'boats'             => $boats,
		'features_text'     => $features_text,
		'boat_words'        => $boat_words,
		'services'          => $services,
		'facilities'        => $facilities,
		'approach'          => $approach,
		'directions'        => $directions,
		'rules'             => $rules,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'kind_titles'          => $kind_titles,
			'season_titles'        => $season_titles,
			'type_titles'          => $type_titles,
			'boat_titles'          => $boat_titles,
			'skipper_choices'      => $skipper_choices,
			'event_kinds'          => $event_kinds,
			'request_kind_choices' => $request_kind_choices,
			'vessel_choices'       => $vessel_choices,
			'power_choices'        => $power_choices,
			'experience_choices'   => $experience_choices,
			'charter_boat_choices' => $charter_boat_choices,
			'request_statuses'     => $request_statuses,
			'enquiry_statuses'     => $enquiry_statuses,
			'entry_statuses'       => $entry_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'kind_cards'        => $kind_cards,
		'kind_facts'        => $kind_fact_lists,
		'boat_cards'        => $boat_cards,
		'boat_facts'        => $boat_fact_lists,
		'feature_cards'     => $feature_cards,
		'testimonial_cards' => $testimonial_cards,
		'rate_table'        => $rate_table(),
		'rate_table_one'    => $rate_table('visitor'),
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $berth_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_mn_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mn_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_mn_trees()
{
	$d = _test_mn_load();
	return array_merge(array($d['header'], $d['footer'], $d['rate_table'], $d['rate_table_one']), $d['shared'],
		array_values($d['kind_cards']), array_values($d['kind_facts']), array_values($d['boat_cards']),
		array_values($d['boat_facts']), $d['feature_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_mn_page_keys()
{
	$keys = array(
		'home', 'berths', 'rates', 'services', 'facilities', 'arriving', 'rules', 'about', 'faq',
		'charters', 'boat', 'boat_new',
		'berth_request', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_reply',
		'charter_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'events', 'event', 'event_new', 'event_entry', 'entry_view', 'my_entries', 'staff_entries',
		'email_request_new', 'email_request_received', 'email_enquiry_new', 'email_enquiry_received',
		'email_entry_new', 'email_entry_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('visitor', 'seasonal', 'annual', 'mooring', 'drystack') as $key) $keys[] = 'berth_' . $key;
	return $keys;
}

function test_marina_data_hangs_together()
{
	$d = _test_mn_load();
	foreach (array('tagline', 'story', 'address', 'vhf', 'hours', 'opening', 'arrival') as $f) pg_assert_true(trim($d['harbour'][$f]) !== '', "harbour: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['harbour']['phone']), 'harbour: phone number');
	pg_assert_true(strpos($d['harbour']['email'], '@example.com') !== false, 'harbour: a sample address');
	pg_assert_true((bool)preg_match('/^-?[0-9]{1,2}\.[0-9]+, -?[0-9]{1,3}\.[0-9]+$/', $d['harbour']['coordinates']), 'harbour: coordinates');
	pg_assert_same(5, count($d['kinds']), 'five kinds of berth');
	foreach ($d['kinds'] as $key => $k) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "kind $key: a page key");
		foreach (array('icon', 'title', 'size', 'summary', 'text') as $f) pg_assert_true(trim($k[$f]) !== '', "kind $key: $f");
		pg_assert_same(4, count($k['points']), "kind $key: four points");
	}
	// A rate for every kind and season, in USD at least, never lower in the
	// high season; each kind says what its rate counts.
	pg_assert_same(2, count($d['seasons']), 'two seasons');
	foreach ($d['kinds'] as $key => $k) {
		pg_assert_contains('%s', $d['rates'][$key]['unit'], "rate $key: its words hold the figure");
		pg_assert_true(isset($d['rates'][$key]['low']['USD'], $d['rates'][$key]['high']['USD']), "rate $key in USD");
		pg_assert_true($d['rates'][$key]['high']['USD'] >= $d['rates'][$key]['low']['USD'], "rate $key: high season not lower");
	}
	foreach ($d['extras'] as $key => $x) {
		pg_assert_true(isset($x['price']['USD']) && trim($x['title']) !== '' && trim($x['unit']) !== '', "extra $key");
	}
	pg_assert_same(4, count($d['rate_includes']), 'four things every rate includes');
	pg_assert_true(trim($d['rate_note']) !== '', 'the rate note');
	pg_assert_same(6, count($d['boats']), 'six sample charter boats');
	$featured = 0;
	$types_seen = array();
	foreach ($d['boats'] as $key => $b) {
		pg_assert_true(isset($d['boat_types'][$b['type']]), "boat $key: known type");
		pg_assert_true(isset($d['lists']['skipper_choices'][$b['skipper']]), "boat $key: known skipper choice");
		foreach (array('length', 'guests', 'cabins') as $f) pg_assert_true(is_int($b[$f]) && $b[$f] >= 0, "boat $key: $f is a number");
		pg_assert_true($b['length'] > 0 && $b['guests'] > 0, "boat $key: length and guests above nought");
		pg_assert_true(isset($b['price']['USD']), "boat $key: a day's price in USD");
		foreach (array('name', 'summary', 'description') as $f) pg_assert_true(trim($b[$f]) !== '', "boat $key: $f");
		pg_assert_same(implode("\n", $b['features']), call_user_func($d['features_text'], $key), "boat $key: a feature line per item");
		$w = $d['boat_words'][$key];
		pg_assert_true(in_array($w['type'], $d['lists']['type_titles'], true), "boat $key: its type is an answer of the list");
		pg_assert_true(in_array($w['skipper'], $d['lists']['skipper_choices'], true), "boat $key: its skipper choice is an answer of the list");
		$types_seen[$b['type']] = true;
		if ($b['featured']) $featured++;
	}
	pg_assert_same(count($d['boat_types']), count($types_seen), 'a sample boat of every type');
	pg_assert_same(3, $featured, 'three boats on the home');
	pg_assert_same(6, count($d['services']), 'six services');
	foreach ($d['services'] as $n => $s) pg_assert_true(trim($s['when']) !== '', "service $n: when");
	pg_assert_same(8, count($d['facilities']), 'eight facilities');
	pg_assert_same(4, count($d['approach']), 'four notes on the approach');
	pg_assert_same(3, count($d['directions']), 'three ways by land');
	pg_assert_same(6, count($d['rules']), 'six harbour rules');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['kinds'][$t['kind']]), "testimonial $n: about a kind");
		pg_assert_contains($d['kinds'][$t['kind']]['title'], $t['role'], "testimonial $n: names the kind");
	}
	// The pick lists: one answer per kind, season, type and boat (plus "not
	// sure", "any boat"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['kinds']), count($l['kind_titles']), 'an answer per kind');
	pg_assert_same(count($d['seasons']), count($l['season_titles']), 'an answer per season');
	pg_assert_same(count($d['boat_types']), count($l['type_titles']), 'an answer per type');
	pg_assert_same(count($d['boats']), count($l['boat_titles']), 'an answer per boat');
	pg_assert_same(count($d['kinds']) + 1, count($l['request_kind_choices']), 'request: an answer per kind and not sure');
	pg_assert_same(count($d['boats']) + 1, count($l['charter_boat_choices']), 'enquiry: an answer per boat and any boat');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['request_statuses']), 'five states of a berth request');
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of a charter enquiry');
	pg_assert_same(4, count($l['entry_statuses']), 'four states of an event entry');
}

// A rate is the shop's figure for the base currency, in the kind's words.
function test_marina_rates_are_words()
{
	$d = _test_mn_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$figure = function ($prices) use ($base, $symbol) {
		return pg_money_text(isset($prices[$base]) ? $prices[$base] : $prices['USD'], $symbol);
	};
	$prices = $d['rates']['visitor']['high'];
	pg_assert_same($figure($prices), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s a metre a night'), $figure($prices)), call_user_func($d['season_rate'], 'visitor', 'high'), 'a season rate');
	pg_assert_same(sprintf(lang('From %s'), sprintf(lang('%s a month'), $figure($d['rates']['drystack']['low']))), call_user_func($d['from_rate'], 'drystack'), 'the lowest rate');
	pg_assert_same(sprintf(lang('From %s a day'), $figure($d['boats']['aurora']['price'])), $d['boat_words']['aurora']['price'], 'a boat\'s day price');
}

function test_marina_page_links_name_template_pages()
{
	$known = array_flip(_test_mn_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_mn_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The harbour sells nothing online: no shared part places the cart or
// links a shop page, and the menu marks the current page itself.
function test_marina_shared_parts_have_no_shop()
{
	$json = json_encode(_test_mn_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_mn_load();
	pg_assert_contains('{{page:berth_request}}', json_encode($d['header']), 'the header opens the berth request');
	pg_assert_contains('{{page:rates}}', json_encode($d['shared'][2]), 'the band shows the rates');
	$nodes = array();
	_test_mn_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that start with $prefix, as their query strings.
function _test_mn_queries($tree, $prefix)
{
	$nodes = array();
	_test_mn_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the boat list filtered on each type, in the words the
// Type list uses, and links every kind of berth's own page; the rate
// table opens the berth request with each season chosen. No link uses
// ?view=, which the lists keep for themselves.
function test_marina_links_filter_lists_and_forms()
{
	$d = _test_mn_load();
	$seen = array();
	foreach (_test_mn_queries($d['footer'], '{{page:charters}}?') as $q) {
		pg_assert_true(isset($q['type']) && in_array($q['type'], $d['lists']['type_titles'], true), 'the link names a type answer');
		$seen[$q['type']] = true;
	}
	pg_assert_same(count($d['boat_types']), count($seen), 'a link per type');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['kinds']) as $key) pg_assert_contains('{{page:berth_' . $key . '}}', $json, "the footer links berth_$key");
	$seen = array();
	foreach (_test_mn_queries($d['rate_table'], '{{page:berth_request}}?') as $q) {
		pg_assert_true(isset($q['season']) && in_array($q['season'], $d['lists']['season_titles'], true), 'the link names a season answer');
		$seen[$q['season']] = true;
	}
	pg_assert_same(count($d['seasons']), count($seen), 'a link per season');
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_mn_trees(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The rate table shows a row per kind (one for the kind given) and a rate
// per season.
function test_marina_rate_table()
{
	$d = _test_mn_load();
	foreach (array(array($d['rate_table'], count($d['kinds'])), array($d['rate_table_one'], 1)) as $case) {
		$nodes = array();
		_test_mn_nodes($case[0], $nodes);
		$rows = 0;
		$cells = 0;
		foreach ($nodes as $n) {
			if (($n['props']['tag'] ?? '') === 'tr') $rows++;
			if (($n['props']['tag'] ?? '') === 'td') $cells++;
		}
		pg_assert_same($case[1] + 1, $rows, 'a head row and a row per kind');
		pg_assert_same($case[1] * count($d['seasons']), $cells, 'a rate per kind and season');
	}
	pg_assert_contains(call_user_func($d['season_rate'], 'visitor', 'low'), json_encode($d['rate_table_one'], JSON_UNESCAPED_UNICODE), 'the visitor rate');
}

// The values a card reads from its form's fields, and its texts.
function _test_mn_card_reads($card)
{
	$nodes = array();
	_test_mn_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// A boat card reads every value from the Charter Boats form's fields, with
// the sample's own words on the canvas; the boat facts read every fact
// field. A kind's card opens the kind's page.
function test_marina_cards_read_their_records()
{
	$d = _test_mn_load();
	foreach ($d['boat_cards'] as $key => $card) {
		list($tokens, $texts) = _test_mn_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'type', 'guests', 'summary', 'price') as $t) {
			pg_assert_true(isset($tokens[$t]), "boat $key: card reads $t");
		}
		$b = $d['boats'][$key];
		pg_assert_true(in_array($b['name'], $texts, true), "boat $key: sample name on the canvas");
		pg_assert_true(in_array($d['boat_words'][$key]['type'], $texts, true), "boat $key: sample type on the canvas");
		pg_assert_true(in_array($d['boat_words'][$key]['price'], $texts, true), "boat $key: sample price on the canvas");
		list($tokens, $texts) = _test_mn_card_reads($d['boat_facts'][$key]);
		foreach (array('type', 'length', 'guests', 'skipper') as $t) pg_assert_true(isset($tokens[$t]), "boat $key: facts read $t");
		pg_assert_true(in_array((string)$b['length'], $texts, true), "boat $key: sample length on the canvas");
	}
	foreach ($d['kind_cards'] as $key => $card) {
		pg_assert_contains('{{page:berth_' . $key . '}}', json_encode($card), "kind $key: the card opens its page");
	}
}

// Classes the template adds of its own start with pmn-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with mn-.
function test_marina_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_mn_trees() as $t) _test_mn_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pmn-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mn-') === 0, "id $id is the template's");
	}
	foreach (array('pmn-brand', 'pmn-display', 'pmn-eyebrow', 'pmn-lift', 'pmn-stat', 'pmn-quote', 'pmn-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// season a year later; a season says "late spring", never its dates.
function test_marina_no_fixed_dates()
{
	$d = _test_mn_load();
	$json = json_encode(array($d['kinds'], $d['boats'], $d['seasons'], $d['harbour'], $d['services'], $d['rules'], _test_mn_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a berth is "bağlama yeri", a berth request
// "bağlama talebi", a charter enquiry "kiralama talebi", a season "sezon".
function test_marina_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBerth Requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'bağlama tale', 0, 'UTF-8') !== false, "'$key' says bağlama talebi");
		if (preg_match('/\bCharter Enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kiralama tale', 0, 'UTF-8') !== false, "'$key' says kiralama talebi");
		if (preg_match('/\bSeason\b/', $key)) pg_assert_true(mb_stripos($tr, 'sezon', 0, 'UTF-8') !== false, "'$key' says sezon");
		if (preg_match('/\b(Visitor|Seasonal|Annual) Berths\b/', $key)) pg_assert_true(mb_stripos($tr, 'bağlama yer', 0, 'UTF-8') !== false, "'$key' says bağlama yeri");
	}
}

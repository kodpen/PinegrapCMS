<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ferry design template's parts
 * (includes/design_templates/pinegrap-ferry/): the line's data hangs
 * together (routes between known ports with sailings for every season, a
 * fare for every kind on every band a route sails, travellers speaking
 * about a route, sample notices naming a known kind and route, the forms'
 * pick lists without an answer twice), every page link of the shared parts
 * names a page of the finished template, the shared parts carry no cart,
 * the footer opens the notice list filtered on each route and links every
 * route's page, the notice card reads its form's fields, the timetable and
 * fare tables open the booking request per route and per fare, the map
 * link opens in a new window, the classes are the template's own, the
 * sample carries no fixed date and every literal has its Turkish.
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
function _test_fy_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/parts.php';
	$route_cards = array();
	$route_fact_lists = array();
	$timetables = array();
	$route_words = array();
	foreach (array_keys($routes) as $key) {
		$route_cards[$key] = $route_card($key);
		$route_fact_lists[$key] = $route_facts($key);
		$timetables[$key] = $timetable($key);
		$route_words[$key] = array('ports' => $route_ports($key), 'from_fare' => $from_fare($key),
			'low' => $sailings_a_day($key, 'low'), 'times' => $sailing_times($key, 'high', 'out'));
	}
	$port_cards = array();
	foreach (array_keys($ports) as $key) $port_cards[$key] = $port_card($key);
	$notice_cards = array();
	$notice_words = array();
	foreach (array_keys($sample_notices) as $key) {
		$notice_cards[$key] = $notice_card($key);
		$notice_words[$key] = array('kind' => $notice_kind($key), 'route' => $notice_route($key));
	}
	$feature_cards = array();
	foreach (array_merge($onboard, $travel_tips, $why_us) as $item) $feature_cards[] = $feature_card($item);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'line'              => $line,
		'ports'             => $ports,
		'seasons'           => $seasons,
		'routes'            => $routes,
		'route_words'       => $route_words,
		'fare_kinds'        => $fare_kinds,
		'fares'             => $fares,
		'fare'              => $fare,
		'fare_includes'     => $fare_includes,
		'fare_note'         => $fare_note,
		'money'             => $money,
		'onboard'           => $onboard,
		'travel_tips'       => $travel_tips,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'notice_kinds'      => $notice_kinds,
		'sample_notices'    => $sample_notices,
		'notice_words'      => $notice_words,
		'lists'             => array(
			'route_titles'         => $route_titles,
			'port_titles'          => $port_titles,
			'season_titles'        => $season_titles,
			'fare_titles'          => $fare_titles,
			'notice_kinds'         => $notice_kinds,
			'notice_route_choices' => $notice_route_choices,
			'vehicle_choices'      => $vehicle_choices,
			'cabin_choices'        => $cabin_choices,
			'group_kind_choices'   => $group_kind_choices,
			'booking_statuses'     => $booking_statuses,
			'group_statuses'       => $group_statuses,
		),
		'radios'            => array('journey_choices' => $journey_choices, 'contact_choices' => $contact_choices),
		'route_cards'       => $route_cards,
		'route_facts'       => $route_fact_lists,
		'timetables'        => $timetables,
		'port_cards'        => $port_cards,
		'notice_cards'      => $notice_cards,
		'feature_cards'     => $feature_cards,
		'testimonial_cards' => $testimonial_cards,
		'fare_table'        => $fare_table(),
		'fare_table_one'    => $fare_table('bay_gull'),
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $booking_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_fy_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fy_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_fy_trees()
{
	$d = _test_fy_load();
	return array_merge(array($d['header'], $d['footer'], $d['fare_table'], $d['fare_table_one']), $d['shared'],
		array_values($d['route_cards']), array_values($d['route_facts']), array_values($d['timetables']),
		array_values($d['port_cards']), array_values($d['notice_cards']), $d['feature_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_fy_page_keys()
{
	$keys = array(
		'home', 'routes', 'ports', 'timetables', 'fares', 'onboard', 'travel', 'about', 'faq',
		'notices', 'notice', 'notice_new',
		'booking_request', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'group_enquiry', 'group_view', 'my_groups', 'staff_groups',
		'email_booking_new', 'email_booking_received', 'email_group_new', 'email_group_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('bay_gull', 'bay_cedar', 'north_cedar', 'gull_cedar') as $key) $keys[] = 'route_' . $key;
	return $keys;
}

function test_ferry_data_hangs_together()
{
	$d = _test_fy_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'opening', 'checkin') as $f) pg_assert_true(trim($d['line'][$f]) !== '', "line: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['line']['phone']), 'line: phone number');
	pg_assert_true(strpos($d['line']['email'], '@example.com') !== false, 'line: a sample address');
	pg_assert_same(4, count($d['ports']), 'four ports');
	foreach ($d['ports'] as $key => $p) {
		foreach (array('icon', 'title', 'summary', 'address', 'parking', 'transport') as $f) pg_assert_true(trim($p[$f]) !== '', "port $key: $f");
		pg_assert_true((bool)preg_match('/^-?[0-9]{1,2}\.[0-9]+, -?[0-9]{1,3}\.[0-9]+$/', $p['coordinates']), "port $key: coordinates");
		pg_assert_same(4, count($p['points']), "port $key: four points");
	}
	pg_assert_same(2, count($d['seasons']), 'two seasons');
	pg_assert_same(4, count($d['routes']), 'four routes');
	$ports_used = array();
	foreach ($d['routes'] as $key => $r) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "route $key: a page key");
		pg_assert_true(isset($d['ports'][$r['from']], $d['ports'][$r['to']]) && $r['from'] !== $r['to'], "route $key: between two known ports");
		$ports_used[$r['from']] = $ports_used[$r['to']] = true;
		pg_assert_true(in_array($r['band'], array('short', 'long'), true), "route $key: a fare band");
		foreach (array('icon', 'title', 'duration', 'distance', 'ship', 'summary', 'text') as $f) pg_assert_true(trim($r[$f]) !== '', "route $key: $f");
		pg_assert_same(4, count($r['points']), "route $key: four points");
		foreach (array_keys($d['seasons']) as $season) {
			$s = $r['sailings'][$season];
			pg_assert_true(count($s['out']) > 0, "route $key $season: sailings");
			pg_assert_same(count($s['out']), count($s['back']), "route $key $season: as many sailings each way");
			foreach (array_merge($s['out'], $s['back']) as $time) pg_assert_true((bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time), "route $key $season: $time is a time");
			$sorted = $s['out'];
			sort($sorted);
			pg_assert_same($sorted, $s['out'], "route $key $season: in order");
		}
		pg_assert_true(count($r['sailings']['high']['out']) >= count($r['sailings']['low']['out']), "route $key: no fewer sailings in summer");
		pg_assert_contains($d['ports'][$r['from']]['title'], $d['route_words'][$key]['ports'], "route $key: names its first port");
	}
	pg_assert_same(count($d['ports']), count($ports_used), 'every port is on a route');
	// A fare for every kind on every band, in USD at least; the cabin on the
	// long crossing only.
	pg_assert_same(8, count($d['fare_kinds']), 'eight kinds of fare');
	foreach ($d['fare_kinds'] as $kind => $f) {
		pg_assert_true(in_array($f['group'], array('foot', 'vehicle', 'extra'), true), "fare $kind: a group");
		foreach (array('icon', 'title', 'note') as $field) pg_assert_true(trim($f[$field]) !== '', "fare $kind: $field");
		pg_assert_true(isset($d['fares'][$kind]['long']['USD']), "fare $kind: long crossing in USD");
		if ($kind !== 'cabin') pg_assert_true(isset($d['fares'][$kind]['short']['USD']), "fare $kind: short crossing in USD");
		if (isset($d['fares'][$kind]['short'])) pg_assert_true($d['fares'][$kind]['long']['USD'] >= $d['fares'][$kind]['short']['USD'], "fare $kind: the long crossing not cheaper");
	}
	pg_assert_false(isset($d['fares']['cabin']['short']), 'no cabin on a short crossing');
	pg_assert_same(4, count($d['fare_includes']), 'four things every fare includes');
	pg_assert_true(trim($d['fare_note']) !== '', 'the fare note');
	pg_assert_same(8, count($d['onboard']), 'eight onboard services');
	foreach ($d['onboard'] as $n => $s) pg_assert_true(trim($s['where']) !== '', "onboard $n: where");
	pg_assert_same(6, count($d['travel_tips']), 'six travel tips');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['routes'][$t['route']]), "testimonial $n: about a route");
		pg_assert_contains($d['routes'][$t['route']]['title'], $t['role'], "testimonial $n: names the route");
	}
	pg_assert_same(4, count($d['sample_notices']), 'four sample notices');
	$featured = 0;
	foreach ($d['sample_notices'] as $key => $n) {
		pg_assert_true(isset($d['notice_kinds'][$n['kind']]), "notice $key: known kind");
		pg_assert_true($n['route'] === '' || isset($d['routes'][$n['route']]), "notice $key: known route or every route");
		foreach (array('title', 'when', 'summary', 'details') as $f) pg_assert_true(trim($n[$f]) !== '', "notice $key: $f");
		pg_assert_true(in_array($d['notice_words'][$key]['route'], $d['lists']['notice_route_choices'], true), "notice $key: its route is an answer of the list");
		if ($n['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three notices on the home');
	// The pick lists: one answer per route, port, season and fare (plus "all
	// routes" for a notice), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['routes']), count($l['route_titles']), 'an answer per route');
	pg_assert_same(count($d['ports']), count($l['port_titles']), 'an answer per port');
	pg_assert_same(count($d['seasons']), count($l['season_titles']), 'an answer per season');
	pg_assert_same(count($d['fare_kinds']), count($l['fare_titles']), 'an answer per fare');
	pg_assert_same(count($d['routes']) + 1, count($l['notice_route_choices']), 'notice: an answer per route and all routes');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['booking_statuses']), 'five states of a booking request');
	pg_assert_same(4, count($l['group_statuses']), 'four states of a group enquiry');
}

// A fare is the shop's figure for the base currency; a route's fares are
// those of its band.
function test_ferry_fares_are_words()
{
	$d = _test_fy_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$figure = function ($prices) use ($base, $symbol) {
		return pg_money_text(isset($prices[$base]) ? $prices[$base] : $prices['USD'], $symbol);
	};
	$prices = $d['fares']['car']['long'];
	pg_assert_same($figure($prices), call_user_func($d['money'], $prices));
	pg_assert_same($figure($prices), call_user_func($d['fare'], 'car', 'bay_cedar'), 'the long crossing\'s car fare');
	pg_assert_same($figure($d['fares']['car']['short']), call_user_func($d['fare'], 'car', 'bay_gull'), 'the short crossing\'s car fare');
	pg_assert_same('', call_user_func($d['fare'], 'cabin', 'bay_gull'), 'no cabin fare on a short crossing');
	pg_assert_same(sprintf(lang('Foot passengers from %s'), $figure($d['fares']['adult']['short'])), $d['route_words']['north_cedar']['from_fare'], 'a route\'s lowest fare');
	pg_assert_same(sprintf(lang('%d sailings a day each way'), 2), $d['route_words']['bay_cedar']['low'], 'sailings a day');
	pg_assert_same(implode(' · ', $d['routes']['gull_cedar']['sailings']['high']['out']), $d['route_words']['gull_cedar']['times'], 'the times in one line');
}

function test_ferry_page_links_name_template_pages()
{
	$known = array_flip(_test_fy_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_fy_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The line sells nothing online: no shared part places the cart or links a
// shop page, and the menu marks the current page itself.
function test_ferry_shared_parts_have_no_shop()
{
	$json = json_encode(_test_fy_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_fy_load();
	pg_assert_contains('{{page:booking_request}}', json_encode($d['header']), 'the header opens the booking request');
	pg_assert_contains('{{page:notices}}', json_encode($d['shared'][1]), 'the account bar line opens the travel notices');
	pg_assert_contains('{{page:timetables}}', json_encode($d['shared'][2]), 'the band shows the timetables');
	$nodes = array();
	_test_fy_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that start with $prefix, as their query strings.
function _test_fy_queries($tree, $prefix)
{
	$nodes = array();
	_test_fy_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the notice list filtered on each route, in the words the
// Route list uses, and links every route's own page; each timetable opens
// the booking request with its route chosen, the fare table with each fare
// chosen. No link uses ?view=, which the lists keep for themselves.
function test_ferry_links_filter_lists_and_forms()
{
	$d = _test_fy_load();
	$seen = array();
	foreach (_test_fy_queries($d['footer'], '{{page:notices}}?') as $q) {
		pg_assert_true(isset($q['route']) && in_array($q['route'], $d['lists']['notice_route_choices'], true), 'the link names a route answer');
		$seen[$q['route']] = true;
	}
	pg_assert_same(count($d['routes']), count($seen), 'a link per route');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['routes']) as $key) pg_assert_contains('{{page:route_' . $key . '}}', $json, "the footer links route_$key");
	foreach ($d['timetables'] as $key => $t) {
		$q = _test_fy_queries($t, '{{page:booking_request}}?');
		pg_assert_same(1, count($q), "timetable $key: one booking link");
		pg_assert_same($d['routes'][$key]['title'], $q[0]['route'] ?? '', "timetable $key: the route chosen");
	}
	$seen = array();
	foreach (_test_fy_queries($d['fare_table'], '{{page:booking_request}}?') as $q) {
		pg_assert_true(isset($q['fare']) && in_array($q['fare'], $d['lists']['fare_titles'], true), 'the link names a fare answer');
		$seen[$q['fare']] = true;
	}
	pg_assert_same(count($d['fare_kinds']), count($seen), 'a link per fare');
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_fy_trees(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The cells of a table tree by tag.
function _test_fy_count($tree, $tag)
{
	$nodes = array();
	_test_fy_nodes($tree, $nodes);
	$n = 0;
	foreach ($nodes as $node) if (($node['props']['tag'] ?? '') === $tag) $n++;
	return $n;
}

// The fare table shows a row per kind and a fare per route (one route when
// given); a timetable a row per season and the times from each port.
function test_ferry_tables()
{
	$d = _test_fy_load();
	$kinds = count($d['fare_kinds']);
	pg_assert_same($kinds + 1, _test_fy_count($d['fare_table'], 'tr'), 'a head row and a row per fare');
	pg_assert_same($kinds * count($d['routes']), _test_fy_count($d['fare_table'], 'td'), 'a fare per kind and route');
	pg_assert_same($kinds, _test_fy_count($d['fare_table_one'], 'td'), 'one route: a fare per kind');
	$json = json_encode($d['fare_table_one'], JSON_UNESCAPED_UNICODE);
	pg_assert_contains(call_user_func($d['fare'], 'car', 'bay_gull'), $json, 'the car fare');
	pg_assert_contains('"text":"–"', $json, 'a dash for the cabin a short crossing has not');
	foreach ($d['timetables'] as $key => $t) {
		pg_assert_same(count($d['seasons']) + 1, _test_fy_count($t, 'tr'), "timetable $key: a head row and a row per season");
		pg_assert_same(count($d['seasons']) * 2, _test_fy_count($t, 'td'), "timetable $key: the times from each port");
		pg_assert_contains($d['routes'][$key]['sailings']['high']['back'][0], json_encode($t), "timetable $key: a return time");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_fy_card_reads($card)
{
	$nodes = array();
	_test_fy_nodes($card, $nodes);
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

// A notice card reads every value from the Travel Notices form's fields,
// with the sample's own words on the canvas. A route's card opens the
// route's page; a port's map link opens in a new window.
function test_ferry_cards_read_their_records()
{
	$d = _test_fy_load();
	foreach ($d['notice_cards'] as $key => $card) {
		list($tokens, $texts) = _test_fy_card_reads($card);
		foreach (array('title', 'form_item_view', 'kind', 'route', 'when', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "notice $key: card reads $t");
		}
		pg_assert_true(in_array($d['sample_notices'][$key]['title'], $texts, true), "notice $key: sample title on the canvas");
		pg_assert_true(in_array($d['notice_words'][$key]['kind'], $texts, true), "notice $key: sample kind on the canvas");
		pg_assert_true(in_array($d['notice_words'][$key]['route'], $texts, true), "notice $key: sample route on the canvas");
	}
	foreach ($d['route_cards'] as $key => $card) {
		pg_assert_contains('{{page:route_' . $key . '}}', json_encode($card), "route $key: the card opens its page");
	}
	foreach ($d['port_cards'] as $key => $card) {
		$nodes = array();
		_test_fy_nodes($card, $nodes);
		$map = null;
		foreach ($nodes as $n) if (strpos((string)($n['props']['href'] ?? ''), 'https://www.openstreetmap.org/') === 0) $map = $n;
		pg_assert_true($map !== null, "port $key: a map link");
		pg_assert_same('_blank', $map['props']['target'] ?? '', "port $key: the map opens in a new window");
		pg_assert_false(strpos(json_encode($map['props']['_attrs'] ?? array()), 'target') !== false, "port $key: no target in _attrs");
	}
}

// Classes the template adds of its own start with pfy-; another template's
// prefix copied in would stay unstyled (its rules are not in this one).
// Field ids start with fy-.
function test_ferry_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_fy_trees() as $t) _test_fy_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmn|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pfy-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fy-') === 0, "id $id is the template's");
	}
	foreach (array('pfy-brand', 'pfy-display', 'pfy-eyebrow', 'pfy-lift', 'pfy-stat', 'pfy-quote', 'pfy-price', 'pfy-time') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// timetable a year later; a season says "late spring", never its dates.
function test_ferry_no_fixed_dates()
{
	$d = _test_fy_load();
	$json = json_encode(array($d['routes'], $d['ports'], $d['seasons'], $d['line'], $d['sample_notices'], $d['onboard'], _test_fy_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a booking request is "rezervasyon talebi", a
// group enquiry "grup talebi", a travel notice "sefer duyurusu", a
// timetable "sefer saatleri", a route "hat".
function test_ferry_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBooking Requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon tale', 0, 'UTF-8') !== false, "'$key' says rezervasyon talebi");
		if (preg_match('/\bGroup Enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'grup tale', 0, 'UTF-8') !== false, "'$key' says grup talebi");
		if (preg_match('/\bTravel Notices?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sefer duyuru', 0, 'UTF-8') !== false, "'$key' says sefer duyurusu");
		if (preg_match('/^Timetables?$/', $key)) pg_assert_true(mb_stripos($tr, 'sefer saat', 0, 'UTF-8') !== false, "'$key' says sefer saatleri");
		if (preg_match('/^Routes?$/', $key)) pg_assert_true(mb_stripos($tr, 'hat', 0, 'UTF-8') !== false, "'$key' says hat");
	}
}

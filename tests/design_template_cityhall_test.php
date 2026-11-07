<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the City Hall design template's parts
 * (includes/design_templates/pinegrap-cityhall/): the city's data hangs
 * together (service areas with their services naming a known department,
 * council members in a known committee, service points with hours, sample
 * notices and events of a known type and category, the forms' pick lists
 * without an answer twice), every page link of the shared parts names a
 * page of the finished template, the shared parts carry no cart, the
 * footer opens the notice list filtered and links every service area, the
 * service cards open the request with their area chosen, the notice and
 * event cards read their form's fields, the classes are the template's
 * own, the sample carries no fixed date and every literal has its Turkish.
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
function _test_cy_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/parts.php';
	$area_cards = array();
	$service_cards = array();
	foreach ($service_areas as $key => $a) {
		$area_cards[$key] = $area_card($key);
		foreach (array_keys($a['services']) as $n) $service_cards[$key][$n] = $service_card($key, $n);
	}
	$department_cards = array();
	foreach (array_keys($departments) as $key) $department_cards[$key] = $department_card($key);
	$council_cards = array();
	foreach (array_keys($council) as $n) $council_cards[] = $council_card($n);
	$point_cards = array();
	foreach (array_keys($service_points) as $key) $point_cards[$key] = $point_card($key);
	$number_cards = array();
	foreach (array_keys($useful_numbers) as $n) $number_cards[] = $number_card($n);
	$notice_cards = array();
	$notice_fact_lists = array();
	$notice_departments = array();
	foreach (array_keys($notices) as $key) {
		$notice_cards[$key] = $notice_card($key);
		$notice_fact_lists[$key] = $notice_facts($key);
		$notice_departments[$key] = $notice_department($key);
	}
	$event_cards = array();
	$event_fact_lists = array();
	foreach (array_keys($events) as $key) {
		$event_cards[$key] = $event_card($key);
		$event_fact_lists[$key] = $event_facts($key);
	}
	$out = array(
		'city'               => $city,
		'service_areas'      => $service_areas,
		'departments'        => $departments,
		'mayor'              => $mayor,
		'committees'         => $committees,
		'council'            => $council,
		'council_meetings'   => $council_meetings,
		'neighbourhoods'     => $neighbourhoods,
		'service_points'     => $service_points,
		'useful_numbers'     => $useful_numbers,
		'figures'            => $figures,
		'notice_types'       => $notice_types,
		'notices'            => $notices,
		'event_categories'   => $event_categories,
		'events'             => $events,
		'notice_details'     => $notice_details_text,
		'event_details'      => $event_details_text,
		'notice_departments' => $notice_departments,
		'lists'              => array(
			'notice_type_titles'       => $notice_type_titles,
			'event_category_titles'    => $event_category_titles,
			'department_titles'        => $department_titles,
			'notice_department_titles' => $notice_department_titles,
			'request_topic_choices'    => $request_topic_choices,
			'request_kinds'            => $request_kinds,
			'neighbourhood_choices'    => $neighbourhood_choices,
			'request_statuses'         => $request_statuses,
			'meeting_subjects'         => $meeting_subjects,
			'meeting_times'            => $meeting_times,
			'meeting_statuses'         => $meeting_statuses,
		),
		'radios'             => array('contact_choices' => $contact_choices),
		'area_cards'         => $area_cards,
		'service_cards'      => $service_cards,
		'department_cards'   => $department_cards,
		'council_cards'      => $council_cards,
		'point_cards'        => $point_cards,
		'number_cards'       => $number_cards,
		'notice_cards'       => $notice_cards,
		'notice_facts'       => $notice_fact_lists,
		'event_cards'        => $event_cards,
		'event_facts'        => $event_fact_lists,
		'header'             => $header(),
		'footer'             => $footer(),
		'shared'             => array($account_bar_tree(), $notice(), $request_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_cy_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cy_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_cy_trees()
{
	$d = _test_cy_load();
	$services = array();
	foreach ($d['service_cards'] as $cards) $services = array_merge($services, array_values($cards));
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['area_cards']), $services,
		array_values($d['department_cards']), $d['council_cards'], array_values($d['point_cards']), $d['number_cards'],
		array_values($d['notice_cards']), array_values($d['notice_facts']), array_values($d['event_cards']), array_values($d['event_facts']));
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_cy_page_keys()
{
	$keys = array(
		'home', 'services', 'mayor', 'council', 'departments', 'city', 'service_points', 'numbers', 'faq',
		'notices', 'notice', 'notice_new', 'events', 'event', 'event_new',
		'request', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_reply',
		'meet', 'meeting_view', 'my_meetings', 'staff_meetings',
		'email_request_new', 'email_request_received', 'email_meeting_new', 'email_meeting_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('building', 'roads', 'waste', 'social', 'culture', 'parks', 'civil') as $key) $keys[] = 'service_' . $key;
	return $keys;
}

function test_cityhall_data_hangs_together()
{
	$d = _test_cy_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'line_hours') as $f) pg_assert_true(trim($d['city'][$f]) !== '', "city: $f");
	foreach (array('phone', 'line') as $f) pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['city'][$f]), "city: $f number");
	pg_assert_true(strpos($d['city']['email'], '@example.com') !== false, 'city: a sample address');
	pg_assert_same(7, count($d['service_areas']), 'seven service areas');
	foreach ($d['service_areas'] as $key => $a) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "area $key: a page key");
		foreach (array('icon', 'title', 'summary', 'text') as $f) pg_assert_true(trim($a[$f]) !== '', "area $key: $f");
		pg_assert_true(isset($d['departments'][$a['department']]), "area $key: a known department");
		pg_assert_same(3, count($a['services']), "area $key: three services");
		foreach ($a['services'] as $n => $s) {
			foreach (array('title', 'text', 'docs') as $f) pg_assert_true(trim($s[$f]) !== '', "area $key service $n: $f");
		}
	}
	foreach ($d['departments'] as $key => $dep) {
		foreach (array('icon', 'title', 'head', 'floor', 'text') as $f) pg_assert_true(trim($dep[$f]) !== '', "department $key: $f");
		pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $dep['phone']), "department $key: phone");
	}
	foreach (array('name', 'role', 'message', 'bio') as $f) pg_assert_true(trim($d['mayor'][$f]) !== '', "mayor: $f");
	pg_assert_same(4, count($d['committees']), 'four committees');
	pg_assert_same(8, count($d['council']), 'eight council members');
	$in_committee = array();
	foreach ($d['council'] as $n => $m) {
		pg_assert_true(isset($d['committees'][$m['committee']]), "council $n: a known committee");
		pg_assert_true(trim($m['name']) !== '' && trim($m['role']) !== '', "council $n: name and role");
		$in_committee[$m['committee']] = true;
	}
	pg_assert_same(count($d['committees']), count($in_committee), 'every committee has members');
	pg_assert_true(trim($d['council_meetings']) !== '', 'when the council meets');
	pg_assert_same(8, count($d['neighbourhoods']), 'eight neighbourhoods');
	foreach ($d['service_points'] as $key => $p) {
		foreach (array('icon', 'title', 'address', 'text') as $f) pg_assert_true(trim($p[$f]) !== '', "point $key: $f");
		pg_assert_true(count($p['hours']) >= 2, "point $key: hours for the week");
		foreach ($p['hours'] as $h) pg_assert_true(trim($h[0]) !== '' && trim($h[1]) !== '', "point $key: days and hours");
	}
	pg_assert_same(6, count($d['useful_numbers']), 'six useful numbers');
	foreach ($d['useful_numbers'] as $n => $u) pg_assert_true((bool)preg_match('/^\+?[0-9 ]+$/', $u['number']), "number $n: a phone number");
	pg_assert_same(4, count($d['figures']), 'four figures');

	pg_assert_same(6, count($d['notices']), 'six sample notices');
	$refs = array();
	$featured = 0;
	foreach ($d['notices'] as $key => $n) {
		pg_assert_true(isset($d['notice_types'][$n['type']]), "notice $key: a known type");
		pg_assert_true(in_array($d['notice_departments'][$key], $d['lists']['notice_department_titles'], true), "notice $key: its department is an answer of the list");
		foreach (array('title', 'ref', 'published', 'deadline', 'summary') as $f) pg_assert_true(trim($n[$f]) !== '', "notice $key: $f");
		pg_assert_same(implode("\n", $n['details']), call_user_func($d['notice_details'], $key), "notice $key: a detail line per item");
		$refs[] = $n['ref'];
		if ($n['featured']) $featured++;
	}
	pg_assert_same($refs, array_values(array_unique($refs)), 'a reference number once');
	pg_assert_same(3, $featured, 'three notices on the home');
	pg_assert_same(6, count($d['events']), 'six sample events');
	$featured = 0;
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_categories'][$e['category']]), "event $key: a known category");
		foreach (array('title', 'when', 'place', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_same(implode("\n", $e['details']), call_user_func($d['event_details'], $key), "event $key: a detail line per item");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');

	$l = $d['lists'];
	pg_assert_same(count($d['notice_types']), count($l['notice_type_titles']), 'an answer per notice type');
	pg_assert_same(count($d['event_categories']), count($l['event_category_titles']), 'an answer per event category');
	pg_assert_same(count($d['departments']) + 1, count($l['notice_department_titles']), 'notices: an answer per department and the clerks');
	pg_assert_same(count($d['service_areas']) + 1, count($l['request_topic_choices']), 'request: an answer per area and something else');
	pg_assert_same(count($d['neighbourhoods']) + 1, count($l['neighbourhood_choices']), 'request: an answer per neighbourhood and outside');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['request_statuses']), 'five states of a request');
	pg_assert_same(4, count($l['meeting_statuses']), 'four states of a meeting request');
}

function test_cityhall_page_links_name_template_pages()
{
	$known = array_flip(_test_cy_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_cy_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The city hall sells nothing online: no shared part places the cart or
// links a shop page, and the menu marks the current page itself.
function test_cityhall_shared_parts_have_no_shop()
{
	$json = json_encode(_test_cy_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_cy_load();
	pg_assert_contains('{{page:request}}', json_encode($d['header']), 'the header opens the request form');
	pg_assert_contains('{{page:my_requests}}', json_encode($d['shared'][2]), 'the band opens My Requests');
	$nodes = array();
	_test_cy_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the notice list filtered on each type, in the words the
// Type list uses, and links every service area's own page.
function test_cityhall_footer_filters_notices_and_links_areas()
{
	$d = _test_cy_load();
	$nodes = array();
	_test_cy_nodes($d['footer'], $nodes);
	$seen = array();
	$prefix = '{{page:notices}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		pg_assert_true(isset($q['type']) && in_array($q['type'], $d['lists']['notice_type_titles'], true), 'the link names a type answer');
		$seen[$q['type']] = true;
	}
	pg_assert_same(count($d['lists']['notice_type_titles']), count($seen), 'a link per notice type');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['service_areas']) as $key) pg_assert_contains('{{page:service_' . $key . '}}', $json, "the footer links service_$key");
}

// A service card opens the request form with its area chosen, in the words
// of the Topic list; an area card opens the area's page.
function test_cityhall_service_cards_open_the_request()
{
	$d = _test_cy_load();
	foreach ($d['service_cards'] as $key => $cards) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($d['area_cards'][$key]), "area $key: the card opens its page");
		foreach ($cards as $n => $card) {
			$nodes = array();
			_test_cy_nodes($card, $nodes);
			$topic = null;
			foreach ($nodes as $node) {
				if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:request}}?') === 0) {
					parse_str(substr($node['props']['href'], strlen('{{page:request}}?')), $q);
					$topic = $q['topic'] ?? null;
				}
			}
			pg_assert_same($d['service_areas'][$key]['title'], $topic, "area $key service $n: the request opens on the area");
			pg_assert_true(in_array($topic, $d['lists']['request_topic_choices'], true), "area $key service $n: the topic is an answer");
		}
	}
	// A service point's map link opens in a new window by its target prop.
	foreach ($d['point_cards'] as $key => $card) {
		$nodes = array();
		_test_cy_nodes($card, $nodes);
		$map = null;
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], 'https://www.openstreetmap.org/') === 0) $map = $node;
		}
		pg_assert_true($map !== null, "point $key: a map link");
		pg_assert_same('_blank', $map['props']['target'] ?? '', "point $key: the map opens in a new window");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_cy_card_reads($card)
{
	$nodes = array();
	_test_cy_nodes($card, $nodes);
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

// A notice or event card reads every value from its form's fields, with the
// sample's own words on the canvas; the facts read every fact field.
function test_cityhall_cards_read_their_records()
{
	$d = _test_cy_load();
	foreach ($d['notice_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cy_card_reads($card);
		foreach (array('type', 'reference', 'title', 'form_item_view', 'summary', 'deadline') as $t) {
			pg_assert_true(isset($tokens[$t]), "notice $key: card reads $t");
		}
		$n = $d['notices'][$key];
		pg_assert_true(in_array($n['title'], $texts, true), "notice $key: sample title on the canvas");
		pg_assert_true(in_array($d['notice_types'][$n['type']], $texts, true), "notice $key: sample type on the canvas");
		list($tokens, $texts) = _test_cy_card_reads($d['notice_facts'][$key]);
		foreach (array('reference', 'type', 'department', 'published', 'deadline') as $t) pg_assert_true(isset($tokens[$t]), "notice $key: facts read $t");
		pg_assert_true(in_array($d['notice_departments'][$key], $texts, true), "notice $key: sample department on the canvas");
	}
	foreach ($d['event_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cy_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'category', 'when', 'place', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: card reads $t");
		}
		$e = $d['events'][$key];
		pg_assert_true(in_array($e['title'], $texts, true), "event $key: sample title on the canvas");
		pg_assert_true(in_array($d['event_categories'][$e['category']], $texts, true), "event $key: sample category on the canvas");
		list($tokens) = _test_cy_card_reads($d['event_facts'][$key]);
		foreach (array('when', 'place', 'category') as $t) pg_assert_true(isset($tokens[$t]), "event $key: facts read $t");
	}
}

// Classes the template adds of its own start with pcy-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with cy-.
function test_cityhall_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_cy_trees() as $t) _test_cy_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|prt)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pcy-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cy-') === 0, "id $id is the template's");
	}
	foreach (array('pcy-brand', 'pcy-display', 'pcy-eyebrow', 'pcy-lift', 'pcy-stat', 'pcy-ref') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old notice
// a year later; a tender says "bids close in four weeks", never its date.
function test_cityhall_no_fixed_dates()
{
	$d = _test_cy_load();
	$json = json_encode(array($d['notices'], $d['events'], $d['service_areas'], $d['city'], $d['council_meetings'], _test_cy_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a notice is "duyuru", a tender "ihale", a
// request "talep", a department "müdürlük", the council "meclis".
function test_cityhall_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		// A capital İ lowers to i and a combining dot, so it is lowered here.
		$tr = str_replace('İ', 'i', $map[$key]);
		if (preg_match('/^(Public Notices|All Notices|New Notice)$/', $key)) pg_assert_true(mb_stripos($tr, 'duyuru', 0, 'UTF-8') !== false, "'$key' says duyuru");
		if (preg_match('/^Tender\b/', $key)) pg_assert_true(mb_stripos($tr, 'ihale', 0, 'UTF-8') !== false, "'$key' says ihale");
		if (preg_match('/^(Make a Request|My Requests|Citizen Requests)$/', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/^(City Departments|Head of Department)$/', $key)) pg_assert_true(mb_stripos($tr, 'müdür', 0, 'UTF-8') !== false, "'$key' says müdürlük");
		if (preg_match('/^(Council|Council Member|Council Decision)$/', $key)) pg_assert_true(mb_stripos($tr, 'meclis', 0, 'UTF-8') !== false, "'$key' says meclis");
	}
}

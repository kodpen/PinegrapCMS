<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's parts
 * (includes/design_templates/pinegrap-library/): the library's data hangs
 * together (branches with hours, rooms in a known branch, sample titles
 * and events whose pick list answers are answers of their lists, readers
 * speaking about a branch, the forms' pick lists without an answer twice),
 * every page link of the shared parts names a page of the finished
 * template, the shared parts carry no cart, the footer opens the
 * catalogue and the event list filtered and links every branch's page,
 * a room card books its own room, the title and event cards read their
 * form's fields, the classes are the template's own, the sample carries
 * no fixed date and every literal has its Turkish.
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
function _test_lb_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/parts.php';
	$branch_cards = array();
	$hours_lists = array();
	foreach (array_keys($branches) as $key) {
		$branch_cards[$key] = $branch_card($key);
		$hours_lists[$key] = $hours_list($key);
	}
	$room_cards = array();
	foreach (array_keys($rooms) as $key) $room_cards[$key] = $room_card($key);
	$item_cards = array();
	$item_fact_lists = array();
	$item_answers = array();
	foreach (array_keys($catalogue_items) as $key) {
		$item_cards[$key] = $item_card($key);
		$item_fact_lists[$key] = $item_facts($key);
		$item_answers[$key] = array('format' => $item_format($key), 'audience' => $item_audience($key),
			'branch' => $item_branch($key), 'availability' => $item_availability($key));
	}
	$event_cards = array();
	$event_fact_lists = array();
	$event_answers = array();
	foreach (array_keys($events) as $key) {
		$event_cards[$key] = $event_card($key);
		$event_fact_lists[$key] = $event_facts($key);
		$event_answers[$key] = array('kind' => $event_kind($key), 'branch' => $event_branch($key), 'age_group' => $event_age($key));
	}
	$staff_cards = array();
	foreach (array_keys($staff) as $n) $staff_cards[] = $staff_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'library'           => $library,
		'branches'          => $branches,
		'services'          => $services,
		'digital'           => $digital,
		'formats'           => $formats,
		'audiences'         => $audiences,
		'items'             => $catalogue_items,
		'event_kinds'       => $event_kinds,
		'events'            => $events,
		'rooms'             => $rooms,
		'staff'             => $staff,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'details_text'      => $details_text,
		'item_answers'      => $item_answers,
		'event_answers'     => $event_answers,
		'lists'             => array(
			'branch_titles'        => $branch_titles,
			'format_titles'        => $format_titles,
			'audience_titles'      => $audience_titles,
			'availability_choices' => $availability_choices,
			'event_kind_titles'    => $event_kind_titles,
			'age_groups'           => $age_groups,
			'event_titles'         => $event_titles,
			'room_titles'          => $room_titles,
			'place_counts'         => $place_counts,
			'card_kinds'           => $card_kinds,
			'group_sizes'          => $group_sizes,
			'time_choices'         => $time_choices,
			'booking_statuses'     => $booking_statuses,
			'membership_statuses'  => $membership_statuses,
			'reservation_statuses' => $reservation_statuses,
			'room_statuses'        => $room_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'branch_cards'      => $branch_cards,
		'hours_lists'       => $hours_lists,
		'room_cards'        => $room_cards,
		'item_cards'        => $item_cards,
		'item_facts'        => $item_fact_lists,
		'event_cards'       => $event_cards,
		'event_facts'       => $event_fact_lists,
		'staff_cards'       => $staff_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'join_band'         => $join_band(),
		'shared'            => array($account_bar_tree(), $notice(), $join_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_lb_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_lb_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_lb_trees()
{
	$d = _test_lb_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['branch_cards']),
		array_values($d['hours_lists']), array_values($d['room_cards']), array_values($d['item_cards']),
		array_values($d['item_facts']), array_values($d['event_cards']), array_values($d['event_facts']),
		$d['staff_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_lb_page_keys()
{
	$keys = array(
		'home', 'visit', 'children', 'digital', 'spaces', 'about', 'faq',
		'catalogue', 'item', 'item_new',
		'events', 'event', 'event_new', 'event_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply',
		'join', 'membership_view', 'my_memberships', 'staff_memberships', 'email_membership_new', 'email_membership_received',
		'reserve', 'reservation_view', 'my_reservations', 'staff_reservations', 'email_reservation_new', 'email_reservation_received',
		'room_booking', 'room_booking_view', 'my_room_bookings', 'staff_room_bookings', 'email_room_booking_new', 'email_room_booking_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('central', 'riverside', 'hillside') as $key) $keys[] = 'branch_' . $key;
	return $keys;
}

function test_library_data_hangs_together()
{
	$d = _test_lb_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['library'][$f]) !== '', "library: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['library']['phone']), 'library: phone number');
	pg_assert_true(strpos($d['library']['email'], '@example.com') !== false, 'library: a sample address');
	pg_assert_same(3, count($d['branches']), 'three branches');
	foreach ($d['branches'] as $key => $b) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "branch $key: a page key");
		foreach (array('icon', 'title', 'address', 'short', 'summary', 'text') as $f) pg_assert_true(trim($b[$f]) !== '', "branch $key: $f");
		pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $b['phone']), "branch $key: phone number");
		pg_assert_same(4, count($b['points']), "branch $key: four points");
		pg_assert_true(count($b['hours']) >= 3, "branch $key: hours for the week");
		foreach ($b['hours'] as $n => $h) {
			pg_assert_true(trim($h['days']) !== '', "branch $key hours $n: days");
			pg_assert_true((bool)preg_match('/^[0-9]{1,2}:[0-9]{2} - [0-9]{1,2}:[0-9]{2}$/', $h['hours']) || $h['hours'] === lang('Closed'), "branch $key hours $n: times or closed");
		}
	}
	pg_assert_same(6, count($d['services']), 'six services');
	pg_assert_same(4, count($d['digital']), 'four digital services');
	pg_assert_same(4, count($d['rooms']), 'four rooms');
	foreach ($d['rooms'] as $key => $r) {
		pg_assert_true(isset($d['branches'][$r['branch']]), "room $key: known branch");
		foreach (array('icon', 'title', 'seats', 'summary') as $f) pg_assert_true(trim($r[$f]) !== '', "room $key: $f");
		pg_assert_same(3, count($r['equipment']), "room $key: three things it has");
	}
	pg_assert_same(4, count($d['staff']), 'four members of staff');
	foreach ($d['staff'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "staff $n: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['branches'][$t['branch']]), "testimonial $n: about a branch");
		pg_assert_contains($d['branches'][$t['branch']]['title'], $t['role'], "testimonial $n: names the branch");
	}
	$l = $d['lists'];
	pg_assert_same(6, count($d['items']), 'six sample titles');
	$new = 0;
	foreach ($d['items'] as $key => $t) {
		foreach (array('title', 'author', 'genre', 'shelf', 'summary') as $f) pg_assert_true(trim($t[$f]) !== '', "title $key: $f");
		$a = $d['item_answers'][$key];
		pg_assert_true(in_array($a['format'], $l['format_titles'], true), "title $key: format answer");
		pg_assert_true(in_array($a['audience'], $l['audience_titles'], true), "title $key: audience answer");
		pg_assert_true(in_array($a['branch'], $l['branch_titles'], true), "title $key: branch answer");
		pg_assert_true(in_array($a['availability'], $l['availability_choices'], true), "title $key: availability answer");
		if ($t['new_arrival']) $new++;
	}
	pg_assert_same(4, $new, 'four new arrivals on the home');
	pg_assert_same(6, count($d['events']), 'six sample events');
	$featured = 0;
	foreach ($d['events'] as $key => $e) {
		foreach (array('title', 'when', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		$a = $d['event_answers'][$key];
		pg_assert_true(in_array($a['kind'], $l['event_kind_titles'], true), "event $key: kind answer");
		pg_assert_true(in_array($a['branch'], $l['branch_titles'], true), "event $key: branch answer");
		pg_assert_true(in_array($a['age_group'], $l['age_groups'], true), "event $key: age group answer");
		pg_assert_same(implode("\n", $e['details']), call_user_func($d['details_text'], $key), "event $key: a detail line per item");
		pg_assert_same(4, count($e['details']), "event $key: four details");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');
	pg_assert_same(count($d['branches']), count($l['branch_titles']), 'an answer per branch');
	pg_assert_same(count($d['formats']), count($l['format_titles']), 'an answer per format');
	pg_assert_same(count($d['audiences']), count($l['audience_titles']), 'an answer per audience');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per kind');
	pg_assert_same(count($d['events']), count($l['event_titles']), 'an answer per event');
	pg_assert_same(count($d['rooms']), count($l['room_titles']), 'an answer per room');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

function test_library_page_links_name_template_pages()
{
	$known = array_flip(_test_lb_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_lb_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The library sells nothing online: no shared part places the cart or
// links a shop page, and the menu marks the current page itself.
function test_library_shared_parts_have_no_shop()
{
	$json = json_encode(_test_lb_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_lb_load();
	pg_assert_contains('{{page:join}}', json_encode($d['header']), 'the header opens the card request');
	pg_assert_contains('{{page:join}}', json_encode($d['join_band']), 'the band opens the card request');
	$nodes = array();
	_test_lb_nodes($d['header'], $nodes);
	$menu = null;
	$join = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		if (isset($n['props']['href']) && $n['props']['href'] === '{{page:join}}' && isset($n['props']['cssClass']) && strpos($n['props']['cssClass'], 'btn') !== false) $join = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
	pg_assert_true($join !== null, 'the header has a card button');
	pg_assert_contains('navbar-expand-xl', json_encode($d['header']), 'the bar opens from 1200 px');
	// The brand, seven links and the button need about 1,320 px: from 1200 to
	// 1399 px the button stays in the collapsed menu.
	pg_assert_contains('d-xl-none d-xxl-inline-flex', $join['props']['cssClass'], 'Join the Library waits for 1400 px in the open bar');
}

// The links of a tree that open $page with a query, as parsed queries.
function _test_lb_queries($tree, $page)
{
	$nodes = array();
	_test_lb_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the catalogue filtered on each format and the event
// list on each kind, in the words the lists use, and links every
// branch's own page.
function test_library_footer_filters_and_links_branches()
{
	$d = _test_lb_load();
	foreach (array(array('catalogue', 'format', 'format_titles'), array('events', 'kind', 'event_kind_titles')) as $c) {
		list($page, $param, $list) = $c;
		$seen = array();
		foreach (_test_lb_queries($d['footer'], $page) as $q) {
			pg_assert_true(isset($q[$param]) && in_array($q[$param], $d['lists'][$list], true), "$page: the link names a $param answer");
			$seen[$q[$param]] = true;
		}
		pg_assert_same(count($d['lists'][$list]), count($seen), "$page: a link per $param");
	}
	$json = json_encode($d['footer']);
	foreach (array_keys($d['branches']) as $key) pg_assert_contains('{{page:branch_' . $key . '}}', $json, "the footer links branch_$key");
}

// The values a card reads from its form's fields, and its texts.
function _test_lb_card_reads($card)
{
	$nodes = array();
	_test_lb_nodes($card, $nodes);
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

// A title card and an event card read every value from their form's
// fields, with the sample's own words on the canvas; the fact lists read
// every fact field. A branch card opens the branch's page; a room card
// opens the booking request with its own room chosen.
function test_library_cards_read_their_records()
{
	$d = _test_lb_load();
	foreach ($d['item_cards'] as $key => $card) {
		list($tokens, $texts) = _test_lb_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'format', 'author', 'availability') as $t) {
			pg_assert_true(isset($tokens[$t]), "title $key: card reads $t");
		}
		pg_assert_true(in_array($d['items'][$key]['title'], $texts, true), "title $key: sample title on the canvas");
		pg_assert_true(in_array($d['item_answers'][$key]['format'], $texts, true), "title $key: sample format on the canvas");
		list($tokens) = _test_lb_card_reads($d['item_facts'][$key]);
		foreach (array('format', 'audience', 'genre', 'shelf', 'branch') as $t) pg_assert_true(isset($tokens[$t]), "title $key: facts read $t");
	}
	foreach ($d['event_cards'] as $key => $card) {
		list($tokens, $texts) = _test_lb_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'kind', 'when', 'branch', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: card reads $t");
		}
		pg_assert_true(in_array($d['events'][$key]['title'], $texts, true), "event $key: sample title on the canvas");
		list($tokens) = _test_lb_card_reads($d['event_facts'][$key]);
		foreach (array('when', 'branch', 'age_group', 'kind') as $t) pg_assert_true(isset($tokens[$t]), "event $key: facts read $t");
	}
	foreach ($d['branch_cards'] as $key => $card) {
		pg_assert_contains('{{page:branch_' . $key . '}}', json_encode($card), "branch $key: the card opens its page");
	}
	foreach ($d['room_cards'] as $key => $card) {
		$q = _test_lb_queries($card, 'room_booking');
		pg_assert_same(1, count($q), "room $key: one booking link");
		pg_assert_same($d['rooms'][$key]['title'], $q[0]['room'] ?? '', "room $key: the link chooses its room");
	}
}

// Classes the template adds of its own start with plb-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with lib-.
function test_library_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_lb_trees() as $t) _test_lb_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pbs)-/', $c), "class $c is not another template's");
			if (strpos($c, 'plb-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'lib-') === 0, "id $id is the template's");
	}
	foreach (array('plb-brand', 'plb-display', 'plb-eyebrow', 'plb-lift', 'plb-stat', 'plb-quote', 'plb-cover', 'plb-hours') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old event
// a year later; an event says "a Saturday in spring", never its date.
function test_library_no_fixed_dates()
{
	$d = _test_lb_load();
	$json = json_encode(array($d['events'], $d['items'], $d['branches'], $d['rooms'], $d['staff'], $d['library'], _test_lb_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a branch is "şube", the catalogue "katalog",
// an event "etkinlik", a reservation "ayırtma".
function test_library_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBranch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'şube', 0, 'UTF-8') !== false, "'$key' says şube");
		if (preg_match('/\bCatalogue\b/', $key)) pg_assert_true(mb_stripos($tr, 'katalog', 0, 'UTF-8') !== false, "'$key' says katalog");
		if (preg_match('/^(Events?|All Events|New Event|Event \w+)$/', $key)) pg_assert_true(mb_stripos($tr, 'etkinlik', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ayırt', 0, 'UTF-8') !== false, "'$key' says ayırtma");
	}
}

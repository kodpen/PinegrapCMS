<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's parts
 * (includes/design_templates/pinegrap-museum/): the museum's data hangs
 * together (opening hours, tickets and tiers each with a price in every
 * currency, sample exhibitions, objects and events naming a known state,
 * department and kind, learning sessions, people, the forms' pick lists
 * without an answer twice), every page link of the shared parts names a
 * page of the finished template, the shared parts never link a shop page,
 * the footer opens the exhibition and collection lists filtered, the cards
 * read their form's fields, the classes are the template's own, the sample
 * carries no fixed date and every literal has its Turkish.
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
function _test_mz_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/parts.php';
	$cards = array('exhibition' => array(), 'exhibition_facts' => array(), 'object' => array(), 'object_facts' => array(),
		'event' => array(), 'event_facts' => array(), 'department' => array(), 'admission' => array(), 'tier' => array(),
		'session' => array(), 'person' => array(), 'testimonial' => array());
	$words = array('exhibition' => array(), 'object' => array(), 'event' => array());
	foreach (array_keys($exhibitions) as $key) {
		$cards['exhibition'][$key] = $exhibition_card($key);
		$cards['exhibition_facts'][$key] = $exhibition_facts($key);
		$words['exhibition'][$key] = $exhibition_status($key);
	}
	foreach (array_keys($objects) as $key) {
		$cards['object'][$key] = $object_card($key);
		$cards['object_facts'][$key] = $object_facts($key);
		$words['object'][$key] = $object_department($key);
	}
	foreach (array_keys($events) as $key) {
		$cards['event'][$key] = $event_card($key);
		$cards['event_facts'][$key] = $event_facts($key);
		$words['event'][$key] = $event_kind($key);
	}
	foreach (array_keys($departments) as $key) $cards['department'][$key] = $department_card($key);
	foreach (array_keys($admission) as $key) $cards['admission'][$key] = $admission_card($key);
	foreach (array_keys($tiers) as $key) $cards['tier'][$key] = $tier_card($key);
	foreach (array_keys($sessions) as $key) $cards['session'][$key] = $session_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	foreach (array_keys($testimonials) as $n) $cards['testimonial'][] = $testimonial_card($n);
	$out = array(
		'museum'          => $museum,
		'opening_hours'   => $opening_hours,
		'admission'       => $admission,
		'admission_prices'=> $admission_prices,
		'tiers'           => $tiers,
		'tier_prices'     => $tier_prices,
		'departments'     => $departments,
		'objects'         => $objects,
		'exhibitions'     => $exhibitions,
		'status_titles'   => $exhibition_status_titles,
		'event_kinds'     => $event_kinds,
		'events'          => $events,
		'sessions'        => $sessions,
		'facilities'      => $facilities,
		'getting_there'   => $getting_there,
		'people'          => $people,
		'figures'         => $figures,
		'testimonials'    => $testimonials,
		'details_text'    => $details_text,
		'price_texts'     => array($admission_price_text('adult'), $tier_price_text('individual')),
		'words'           => $words,
		'lists'           => array(
			'exhibition_statuses'    => $exhibition_statuses,
			'department_titles'      => $department_titles,
			'event_kind_titles'      => $event_kind_titles,
			'event_titles'           => $event_titles,
			'session_titles'         => $session_titles,
			'school_session_choices' => $school_session_choices,
			'place_counts'           => $place_counts,
			'year_groups'            => $year_groups,
			'visit_day_choices'      => $visit_day_choices,
			'booking_statuses'       => $booking_statuses,
			'school_visit_statuses'  => $school_visit_statuses,
		),
		'radios'          => array('contact_choices' => $contact_choices),
		'cards'           => $cards,
		'header'          => $header(),
		'footer'          => $footer(),
		'shared'          => array($account_bar_tree(), $notice(), $visit_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row(), $hours_list()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_mz_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mz_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_mz_trees()
{
	$d = _test_mz_load();
	$trees = array_merge(array($d['header'], $d['footer']), $d['shared']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_mz_page_keys()
{
	return array(
		'home', 'visit', 'support', 'about', 'faq',
		'exhibitions', 'exhibition', 'exhibition_new',
		'collection', 'object', 'object_new',
		'events', 'event', 'event_new', 'event_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply',
		'learning', 'school_visit', 'school_visit_view', 'my_school_visits', 'staff_school_visits',
		'email_school_visit_new', 'email_school_visit_received', 'book_online',
		'tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_museum_data_hangs_together()
{
	$d = _test_mz_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'last', 'free', 'licence') as $f) pg_assert_true(trim($d['museum'][$f]) !== '', "museum: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['museum']['phone']), 'museum: phone number');
	pg_assert_true(strpos($d['museum']['email'], '@example.com') !== false, 'museum: a sample address');
	pg_assert_same(4, count($d['opening_hours']), 'four lines of opening hours');
	foreach ($d['opening_hours'] as $n => $h) pg_assert_true(trim($h['days']) !== '' && trim($h['hours']) !== '', "hours $n: days and hours");
	// Every ticket and tier has a price in each currency the catalog knows.
	foreach (array('admission' => 'admission_prices', 'tiers' => 'tier_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	foreach ($d['admission'] as $key => $a) {
		foreach (array('title', 'who', 'includes') as $f) pg_assert_true(trim($a[$f]) !== '', "ticket $key: $f");
	}
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_true(trim($t['title']) !== '' && trim($t['who']) !== '', "tier $key: title and who");
		pg_assert_same(4, count($t['benefits']), "tier $key: four benefits");
	}
	foreach ($d['price_texts'] as $text) pg_assert_true((bool)preg_match('/[0-9]/', $text), 'a price is written with its figure');
	pg_assert_same(6, count($d['departments']), 'six departments');
	pg_assert_same(6, count($d['objects']), 'six sample objects');
	$featured = 0;
	foreach ($d['objects'] as $key => $o) {
		pg_assert_true(isset($d['departments'][$o['department']]), "object $key: known department");
		pg_assert_true(in_array($d['words']['object'][$key], $d['lists']['department_titles'], true), "object $key: its department is an answer of the list");
		foreach (array('title', 'maker', 'period', 'material', 'gallery', 'story') as $f) pg_assert_true(trim($o[$f]) !== '', "object $key: $f");
		if ($o['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three objects on the home');
	pg_assert_same(6, count($d['exhibitions']), 'six sample exhibitions');
	$featured = 0;
	$states = array();
	foreach ($d['exhibitions'] as $key => $x) {
		pg_assert_true(isset($d['status_titles'][$x['status']]), "exhibition $key: known state");
		pg_assert_true(in_array($d['words']['exhibition'][$key], $d['lists']['exhibition_statuses'], true), "exhibition $key: its state is an answer of the list");
		foreach (array('title', 'when', 'gallery', 'curator', 'summary') as $f) pg_assert_true(trim($x[$f]) !== '', "exhibition $key: $f");
		pg_assert_same(4, count($x['details']), "exhibition $key: four details");
		pg_assert_same(implode("\n", $x['details']), call_user_func($d['details_text'], $x['details']), "exhibition $key: a detail line per item");
		$states[$x['status']] = true;
		if ($x['featured']) $featured++;
	}
	pg_assert_same(3, count($states), 'a sample exhibition in every state');
	pg_assert_same(3, $featured, 'three exhibitions on the home');
	pg_assert_same(6, count($d['events']), 'six sample events');
	$featured = 0;
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_kinds'][$e['kind']]), "event $key: known kind");
		pg_assert_true(in_array($d['words']['event'][$key], $d['lists']['event_kind_titles'], true), "event $key: its kind is an answer of the list");
		foreach (array('title', 'when', 'place', 'price', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_same(4, count($e['details']), "event $key: four details");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');
	pg_assert_same(4, count($d['sessions']), 'four learning sessions');
	foreach ($d['sessions'] as $key => $s) {
		foreach (array('title', 'ages', 'length', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "session $key: $f");
	}
	pg_assert_same(6, count($d['facilities']), 'six facilities');
	pg_assert_same(4, count($d['getting_there']), 'four ways to get there');
	pg_assert_same(6, count($d['people']), 'six people');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_same(3, count($d['testimonials']), 'three visitors\' words');
	// The pick lists: one answer per state, department, kind, event and
	// session (plus "not sure"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['status_titles']), count($l['exhibition_statuses']), 'an answer per state');
	pg_assert_same(count($d['departments']), count($l['department_titles']), 'an answer per department');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per kind');
	pg_assert_same(count($d['events']), count($l['event_titles']), 'an answer per event');
	pg_assert_same(count($d['sessions']) + 1, count($l['school_session_choices']), 'school visit: an answer per session and not sure');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['booking_statuses']), 'four states of a place request');
	pg_assert_same(4, count($l['school_visit_statuses']), 'four states of a school visit request');
}

function test_museum_page_links_name_template_pages()
{
	$known = array_flip(_test_mz_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_mz_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Tickets and membership are the shop's: no shared part or card links a
// shop page, so every part stands on a site without the shop (the account
// bar's cart leaves by itself). The menu marks the current page itself.
function test_museum_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_mz_trees());
	foreach (array('tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_mz_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared'][0]), 'the account bar carries the cart');
	pg_assert_contains('{{page:book_online}}', json_encode($d['header']), 'the header books a timed entry');
	pg_assert_contains('{{page:visit}}', json_encode($d['shared'][2]), 'the band plans a visit');
	$nodes = array();
	_test_mz_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that open $page with a query, as name => values.
function _test_mz_query_links($tree, $page)
{
	$nodes = array();
	_test_mz_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

// The footer opens the exhibition list on each state and the highlights on
// each department, in the words the lists use; a department card and a
// session card open theirs the same way.
function test_museum_links_filter_in_the_lists_words()
{
	$d = _test_mz_load();
	$q = _test_mz_query_links($d['footer'], 'exhibitions');
	pg_assert_same($d['lists']['exhibition_statuses'], $q['status'], 'a footer link per exhibition state');
	$q = _test_mz_query_links($d['footer'], 'collection');
	pg_assert_same($d['lists']['department_titles'], $q['department'], 'a footer link per department');
	foreach ($d['cards']['department'] as $key => $card) {
		$q = _test_mz_query_links($card, 'collection');
		pg_assert_same(array($d['departments'][$key]['title']), $q['department'], "department $key: the card filters the highlights");
	}
	foreach ($d['cards']['session'] as $key => $card) {
		$q = _test_mz_query_links($card, 'school_visit');
		pg_assert_true(in_array($q['session'][0], $d['lists']['school_session_choices'], true), "session $key: the card opens the request with an answer of the list");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_mz_card_reads($card)
{
	$nodes = array();
	_test_mz_nodes($card, $nodes);
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

// Each record card reads every value from its form's fields, with the
// sample's own words on the canvas; the facts lists read every fact field.
function test_museum_cards_read_their_records()
{
	$d = _test_mz_load();
	$reads = array(
		'exhibition' => array('exhibitions', array('photo_1', 'title', 'form_item_view', 'status', 'when', 'gallery', 'summary'), array('when', 'gallery', 'curator')),
		'object'     => array('objects', array('photo_1', 'title', 'form_item_view', 'department', 'maker', 'period'), array('maker', 'period', 'material', 'department', 'gallery')),
		'event'      => array('events', array('photo_1', 'title', 'form_item_view', 'kind', 'when', 'place', 'price', 'summary'), array('when', 'place', 'price', 'kind')),
	);
	foreach ($reads as $kind => $r) {
		foreach ($d['cards'][$kind] as $key => $card) {
			list($tokens, $texts) = _test_mz_card_reads($card);
			foreach ($r[1] as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: card reads $t");
			pg_assert_true(in_array($d[$r[0]][$key]['title'], $texts, true), "$kind $key: sample title on the canvas");
			pg_assert_true(in_array($d['words'][$kind][$key], $texts, true), "$kind $key: sample pick list answer on the canvas");
			list($tokens) = _test_mz_card_reads($d['cards'][$kind . '_facts'][$key]);
			foreach ($r[2] as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: facts read $t");
		}
	}
}

// Classes the template adds of its own start with pmz-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with mz-.
function test_museum_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_mz_trees() as $t) _test_mz_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pmz-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
	foreach (array('pmz-brand', 'pmz-display', 'pmz-eyebrow', 'pmz-lift', 'pmz-stat', 'pmz-quote', 'pmz-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// exhibition a year later; an exhibition says "until the end of spring"
// and an object's period is words.
function test_museum_no_fixed_dates()
{
	$d = _test_mz_load();
	$json = json_encode(array($d['exhibitions'], $d['events'], $d['objects'], $d['sessions'], $d['museum'], _test_mz_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: an exhibition is "sergi", an event
// "etkinlik", a member "üye", a school visit "okul ziyareti".
function test_museum_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bExhibitions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sergi', 0, 'UTF-8') !== false, "'$key' says sergi");
		if (preg_match('/^(Events?|New Event|Event \w+)$/', $key)) pg_assert_true(mb_stripos($tr, 'etkinlik', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\bMembers?\b/', $key)) pg_assert_true(mb_stripos($tr, 'üye', 0, 'UTF-8') !== false, "'$key' says üye");
		if (preg_match('/\bSchool Visits?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'okul ziyaret', 0, 'UTF-8') !== false, "'$key' says okul ziyareti");
	}
}

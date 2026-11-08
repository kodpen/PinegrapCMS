<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's data and page helpers
 * (includes/design_templates/pinegrap-zoo/parts.php): the zoo's data hangs
 * together (animals naming a known habitat, class and conservation level;
 * the schedule naming known animals in time order; every ticket,
 * membership, adoption level and visit with a price in every currency;
 * events with a known kind falling after today), the pick lists follow the
 * data without an answer twice, a price is written as the shop writes it,
 * the page shell places the shared parts, the helpers link only template
 * pages, the classes and ids are the template's own, the sample carries no
 * fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The data and helpers as the template file reads them. Read once per run.
function _test_zoo_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/parts.php';
	$words = array();
	foreach (array_keys($animals) as $key) {
		$words[$key] = array($animal_habitat($key), $animal_class($key), $animal_status($key), $animal_place($key));
	}
	$dates = array();
	foreach ($events as $key => $e) $dates[$key] = $event_on($e['weekday'], $e['weeks']);
	$out = array(
		'zoo'               => $zoo,
		'opening_hours'     => $opening_hours,
		'habitats'          => $habitats,
		'animal_classes'    => $animal_classes,
		'conservation'      => $conservation,
		'animals'           => $animals,
		'schedule_kinds'    => $schedule_kinds,
		'schedule'          => $schedule,
		'tickets'           => $tickets,
		'ticket_prices'     => $ticket_prices,
		'memberships'       => $memberships,
		'membership_prices' => $membership_prices,
		'adoption_levels'   => $adoption_levels,
		'adoption_prices'   => $adoption_prices,
		'visits'            => $visits,
		'visit_prices'      => $visit_prices,
		'age_groups'        => $age_groups,
		'event_kinds'       => $event_kinds,
		'events'            => $events,
		'event_dates'       => $dates,
		'people'            => $people,
		'figures'           => $figures,
		'details_text'      => $details_text,
		'money'             => $money,
		'query'             => $query,
		'price_texts'       => array(
			'membership' => $membership_price_text('family'),
			'adoption'   => $adoption_price_text('keeper'),
			'visit'      => $visit_price_text('school'),
		),
		'words'             => $words,
		'lists'             => array(
			'habitat_titles'    => $habitat_titles,
			'class_titles'      => $class_titles,
			'status_titles'     => $status_titles,
			'age_titles'        => $age_titles,
			'visit_titles'      => $visit_titles,
			'visit_choices'     => $visit_choices,
			'group_kinds'       => $group_kinds,
			'visitor_counts'    => $visitor_counts,
			'event_kind_titles' => $event_kind_titles,
			'visit_statuses'    => $visit_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices, 'workshop_choices' => $workshop_choices),
		'helpers'           => array(
			'page'           => $page(array($para('x')), true),
			'form_page'      => $page(array($para('x')), false),
			'page_header'    => $page_header(lang('Animals'), lang('Tickets')),
			'section_header' => $section_header(lang('Animals'), lang('Tickets'), lang('All Animals'), '{{page:animals}}'),
			'pin'            => $pin('3', 'savanna'),
			'icon_tile'      => $icon_tile('bi-tree'),
			'step'           => $step(1, lang('Animals'), lang('Tickets')),
			'check_list'     => $check_list($memberships['family']['benefits']),
			'faq'            => $faq(1, lang('Animals'), lang('Tickets')),
			'rise'           => $rise($el('div', 'row', lang('Row'))),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_zoo_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_zoo_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_zoo_page_keys()
{
	return array_merge(array(
		'home', 'today', 'map', 'tickets', 'memberships', 'adopt', 'about', 'faq',
		'animals', 'animal', 'animal_new',
		'visit_request', 'visit_view', 'my_visits', 'staff_visits', 'privacy', 'email_reply',
		'email_visit_new', 'email_visit_received',
		'events', 'event', 'event_new',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_zoo_shop_keys());
}

// The pages that exist only with the shop.
function _test_zoo_shop_keys()
{
	return array('buy_tickets', 'adopt_shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_zoo_data_hangs_together()
{
	$d = _test_zoo_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'season', 'size', 'licence') as $f) pg_assert_true(trim($d['zoo'][$f]) !== '', "zoo: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['zoo']['phone']), 'zoo: a phone number');
	pg_assert_true(strpos($d['zoo']['email'], '@example.com') !== false, 'zoo: a sample address');
	pg_assert_same(4, count($d['opening_hours']), 'four lines of hours');
	foreach ($d['opening_hours'] as $n => $h) pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2} - [0-9]{2}:[0-9]{2}$/', $h['hours']), "hours $n: from and to");
	pg_assert_same(array('lc', 'nt', 'vu', 'en', 'cr', 'ew', 'ex'), array_keys($d['conservation']), 'the seven red-list levels in order');
	foreach ($d['conservation'] as $key => $c) pg_assert_same(strtoupper($key), $c['code'], "level $key: its international code");
	foreach ($d['habitats'] as $key => $h) {
		foreach (array('icon', 'title', 'summary') as $f) pg_assert_true(trim($h[$f]) !== '', "habitat $key: $f");
		pg_assert_true((bool)preg_match('/^text-bg-(primary|secondary|success|info|warning|danger)$/', $h['pin']), "habitat $key: a theme colour for its pin");
	}
	$used = array('habitat' => array(), 'class' => array());
	$numbers = array();
	$featured = 0;
	foreach ($d['animals'] as $key => $a) {
		pg_assert_true(isset($d['habitats'][$a['habitat']]), "animal $key: a known habitat");
		pg_assert_true(isset($d['animal_classes'][$a['class']]), "animal $key: a known class");
		pg_assert_true(isset($d['conservation'][$a['status']]), "animal $key: a known conservation level");
		foreach (array('title', 'species', 'diet', 'lifespan', 'summary') as $f) pg_assert_true(trim($a[$f]) !== '', "animal $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{1,2}$/', $a['number']), "animal $key: a habitat number");
		pg_assert_same(4, count($a['details']), "animal $key: four details");
		pg_assert_same(implode("\n", $a['details']), call_user_func($d['details_text'], $a['details']), "animal $key: a detail line per item");
		list($habitat, $class, $status, $place) = $d['words'][$key];
		pg_assert_true(in_array($habitat, $d['lists']['habitat_titles'], true), "animal $key: its habitat is an answer of the list");
		pg_assert_true(in_array($class, $d['lists']['class_titles'], true), "animal $key: its class is an answer of the list");
		pg_assert_true(in_array($status, $d['lists']['status_titles'], true), "animal $key: its level is an answer of the list");
		pg_assert_true(strpos($place, $a['number']) !== false && strpos($place, $habitat) !== false, "animal $key: its place says number and habitat");
		pg_assert_false(isset($numbers[$a['number']]), "animal $key: its habitat number is its own");
		$numbers[$a['number']] = true;
		$used['habitat'][$a['habitat']] = true;
		$used['class'][$a['class']] = true;
		if ($a['featured']) $featured++;
	}
	pg_assert_same(count($d['habitats']), count($used['habitat']), 'an animal in each habitat');
	pg_assert_same(count($d['animal_classes']), count($used['class']), 'an animal of each class');
	pg_assert_same(3, $featured, 'three animals on the home');
	pg_assert_true(count(array_filter($d['animals'], function ($a) { return $a['adopt']; })) >= 3, 'at least three animals to adopt');
	$last = '';
	$kinds = array();
	foreach ($d['schedule'] as $n => $s) {
		pg_assert_true(isset($d['schedule_kinds'][$s['kind']]), "slot $n: a known kind");
		pg_assert_true(isset($d['animals'][$s['animal']]), "slot $n: a known animal");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $s['time']), "slot $n: a time");
		pg_assert_true($s['time'] > $last, "slot $n: after the one before");
		pg_assert_true(trim($s['title']) !== '', "slot $n: a title");
		$last = $s['time'];
		$kinds[$s['kind']] = true;
	}
	pg_assert_same(count($d['schedule_kinds']), count($kinds), 'a feeding and a keeper talk on the schedule');
	// Every ticket, membership, adoption level and visit has a price in
	// each currency the catalog knows.
	foreach (array('tickets' => 'ticket_prices', 'memberships' => 'membership_prices', 'adoption_levels' => 'adoption_prices',
		'visits' => 'visit_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	foreach ($d['tickets'] as $key => $t) pg_assert_true(trim($t['title']) !== '' && trim($t['who']) !== '', "ticket $key: title and who");
	foreach ($d['memberships'] as $key => $m) {
		pg_assert_true(trim($m['title']) !== '' && trim($m['who']) !== '', "membership $key: title and who");
		pg_assert_same(4, count($m['benefits']), "membership $key: four benefits");
	}
	foreach ($d['adoption_levels'] as $key => $a) pg_assert_same(3, count($a['benefits']), "adoption $key: three benefits");
	foreach ($d['visits'] as $key => $v) {
		foreach (array('icon', 'title', 'who', 'length', 'summary') as $f) pg_assert_true(trim($v[$f]) !== '', "visit $key: $f");
	}
	foreach ($d['price_texts'] as $kind => $text) pg_assert_true((bool)preg_match('/[0-9]/', $text), "$kind: a price is written with its figure");
	$featured = 0;
	$today = date('Y-m-d');
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_kinds'][$e['kind']]), "event $key: a known kind");
		foreach (array('title', 'place', 'price', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $e['time']), "event $key: a time");
		$date = $d['event_dates'][$key];
		pg_assert_true($date > $today, "event $key: still to come");
		pg_assert_same((int)$e['weekday'], (int)date('N', strtotime($date)), "event $key: on its weekday");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');
	pg_assert_same(4, count($d['people']), 'four people');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
}

// The pick lists: one answer per habitat, class, level, age group and
// visit (plus the extra answer), no answer twice.
function test_zoo_pick_lists_follow_the_data()
{
	$d = _test_zoo_load();
	$l = $d['lists'];
	pg_assert_same(count($d['habitats']), count($l['habitat_titles']), 'an answer per habitat');
	pg_assert_same(count($d['animal_classes']), count($l['class_titles']), 'an answer per class');
	pg_assert_same(count($d['conservation']), count($l['status_titles']), 'an answer per conservation level');
	pg_assert_same(count($d['age_groups']), count($l['age_titles']), 'an answer per age group');
	pg_assert_same(count($d['visits']) + 1, count($l['visit_choices']), 'visit: each by title and something else');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per event kind');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['visit_statuses']), 'four states of a visit request');
	// A form link's query keeps the answer's own words.
	$params = array('visit' => $l['visit_titles'][0], 'group' => $l['group_kinds'][0], 'age' => $l['age_titles'][1], 'habitat' => $l['habitat_titles'][5]);
	parse_str(call_user_func($d['query'], $params), $q);
	pg_assert_same($params, $q, 'the choices survive the query');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_zoo_price_is_written_as_the_shop_writes_it()
{
	$d = _test_zoo_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['ticket_prices']['adult'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	$year = $d['membership_prices']['family'];
	$amount = isset($year[$base]) ? $year[$base] : $year['USD'];
	pg_assert_same(sprintf(lang('%s a year'), pg_money_text($amount, $symbol)), $d['price_texts']['membership'], 'the membership price a year');
}

// The page shell places the shared parts by key, the band only on pages
// that are not forms; the helpers link only template pages.
function test_zoo_page_shell_and_helpers()
{
	$d = _test_zoo_load();
	$h = $d['helpers'];
	$shared = function ($tree) {
		$out = array();
		foreach ($tree['children'] as $c) if ($c['type'] === 'shared_ref') $out[] = $c['props']['templateShared'];
		return $out;
	};
	pg_assert_same(array('account_bar', 'header', 'visit_band', 'footer'), $shared($h['page']), 'a page with the visit band');
	pg_assert_same(array('account_bar', 'header', 'footer'), $shared($h['form_page']), 'a form page without it');
	$known = array_flip(_test_zoo_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($h), $m);
	pg_assert_true(count($m[1]) > 0, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_same(count(_test_zoo_page_keys()), count(array_unique(_test_zoo_page_keys())), 'no page key twice');
	$nodes = array();
	_test_zoo_nodes($h['faq'], $nodes);
	pg_assert_same('zoo-faq-1-item', $nodes[0]['props']['id'], 'a question is its own search section');
	pg_assert_true(!empty($nodes[0]['props']['searchSection']), 'searchable');
	pg_assert_same('pg-zoo-pin text-bg-warning', $h['pin']['props']['cssClass'], 'a pin takes the block family\'s rule and its zone\'s colour');
	pg_assert_same('3', $h['pin']['props']['text'], 'a pin says its number');
	pg_assert_same(array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 80), $h['rise']['props']['_fx'], 'an entrance through the interactions');
	$fx = pg_fx_normalize($h['rise']['props']['_fx']);
	pg_assert_same(array('slide-up', 'view', 80), array($fx['effect'], $fx['trigger'], $fx['stagger']), 'the entrance survives the normalizer');
}

// Classes the template adds of its own start with pzo-; field and section
// ids with zoo-. No eyebrow line: the zoo's sections are told apart by
// their grounds.
function test_zoo_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_zoo_load()['helpers'] as $t) _test_zoo_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|psk)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "no eyebrow line ($c)");
			if (strpos($c, 'pzo-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'zoo-') === 0, "id $id is the template's");
	}
	foreach (array('pzo-display', 'pzo-hero', 'pzo-mark') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The sample is never dated: a year or a month would read as an old
// season a year later. Event dates are counted at opening and not part of
// the data.
function test_zoo_no_fixed_dates()
{
	$d = _test_zoo_load();
	$json = json_encode(array($d['zoo'], $d['animals'], $d['schedule'], $d['tickets'], $d['memberships'], $d['adoption_levels'],
		$d['visits'], $d['events'], $d['people'], $d['helpers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a habitat is "yaşam alanı", a feeding
// "besleme", a membership "üyelik", a ticket "bilet", an adoption
// "sahiplen…", a school trip "okul gezisi".
function test_zoo_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^Habitat\b/', $key)) pg_assert_true(mb_stripos($tr, 'yaşam alan', 0, 'UTF-8') !== false, "'$key' says yaşam alanı");
		if (preg_match('/\bfeeding\b/i', $key)) pg_assert_true(mb_stripos($tr, 'besleme', 0, 'UTF-8') !== false, "'$key' says besleme");
		if (preg_match('/\bMembership\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyeli', 0, 'UTF-8') !== false, "'$key' says üyelik");
		if (preg_match('/\bTicket\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
		if (preg_match('/\badoption\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sahiplen', 0, 'UTF-8') !== false, "'$key' says sahiplenme");
		if (preg_match('/\bSchool Trip\b/', $key)) pg_assert_true(mb_stripos($tr, 'okul gezisi', 0, 'UTF-8') !== false, "'$key' says okul gezisi");
	}
}

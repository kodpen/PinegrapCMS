<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Private School design template's parts
 * (includes/design_templates/pinegrap-school/): the school's data hangs
 * together (programmes with ages, groups, hours and points, classes and
 * sample events naming a known programme, a day in order, staff, parents
 * speaking about a programme, the forms' pick lists without an answer
 * twice), every page link of the shared parts names a page of the
 * finished template, the shared parts carry no cart, the footer opens the
 * event list filtered and links every programme's page, the event card
 * reads its form's fields, the classes are the template's own, the sample
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
function _test_sh_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/parts.php';
	$programme_cards = array();
	$programme_fact_lists = array();
	foreach (array_keys($programmes) as $key) {
		$programme_cards[$key] = $programme_card($key);
		$programme_fact_lists[$key] = $programme_facts($key);
	}
	$event_cards = array();
	$event_fact_lists = array();
	$event_programmes = array();
	foreach (array_keys($events) as $key) {
		$event_cards[$key] = $event_card($key);
		$event_fact_lists[$key] = $event_facts($key);
		$event_programmes[$key] = $event_programme($key);
	}
	$staff_cards = array();
	foreach (array_keys($staff) as $n) $staff_cards[] = $staff_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'school'            => $school,
		'programmes'        => $programmes,
		'fee_text'          => $fee_text,
		'classes'           => $classes,
		'day'               => $day,
		'approach'          => $approach,
		'facilities'        => $facilities,
		'staff'             => $staff,
		'accreditations'    => $accreditations,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'events'            => $events,
		'whole_school'      => $whole_school,
		'details_text'      => $details_text,
		'event_programmes'  => $event_programmes,
		'lists'             => array(
			'programme_titles'              => $programme_titles,
			'event_programme_titles'        => $event_programme_titles,
			'application_programme_choices' => $application_programme_choices,
			'start_choices'                 => $start_choices,
			'relation_choices'              => $relation_choices,
			'application_statuses'          => $application_statuses,
			'visit_times'                   => $visit_times,
			'visitor_counts'                => $visitor_counts,
			'visit_statuses'                => $visit_statuses,
			'event_titles'                  => $event_titles,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'programme_cards'   => $programme_cards,
		'programme_facts'   => $programme_fact_lists,
		'event_cards'       => $event_cards,
		'event_facts'       => $event_fact_lists,
		'staff_cards'       => $staff_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $admissions_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_sh_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sh_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_sh_trees()
{
	$d = _test_sh_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['programme_cards']),
		array_values($d['programme_facts']), array_values($d['event_cards']), array_values($d['event_facts']),
		$d['staff_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_sh_page_keys()
{
	$keys = array(
		'home', 'programmes', 'day', 'approach', 'campus', 'staff_team', 'about', 'fees', 'faq',
		'events', 'event', 'event_new',
		'apply', 'application_view', 'my_applications', 'staff_applications', 'privacy', 'email_reply',
		'visit', 'staff_visits', 'email_application_new', 'email_application_received', 'email_visit_new', 'email_visit_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('nursery', 'kindergarten', 'primary', 'clubs', 'summer') as $key) $keys[] = 'programme_' . $key;
	return $keys;
}

function test_school_data_hangs_together()
{
	$d = _test_sh_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['school'][$f]) !== '', "school: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['school']['phone']), 'school: phone number');
	pg_assert_true(strpos($d['school']['email'], '@example.com') !== false, 'school: a sample address');
	pg_assert_true(trim($d['fee_text']) !== '', 'the fee line');
	pg_assert_same(5, count($d['programmes']), 'five programmes');
	foreach ($d['programmes'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "programme $key: a page key");
		foreach (array('icon', 'title', 'ages', 'group', 'hours', 'summary', 'text') as $f) pg_assert_true(trim($p[$f]) !== '', "programme $key: $f");
		pg_assert_same(4, count($p['points']), "programme $key: four points");
	}
	foreach ($d['classes'] as $n => $c) {
		pg_assert_true(isset($d['programmes'][$c['programme']]), "class $n: known programme");
		pg_assert_true(trim($c['name']) !== '' && trim($c['ages']) !== '', "class $n: name and ages");
	}
	// The day runs in order, hour by hour.
	$last = -1;
	foreach ($d['day'] as $n => $s) {
		pg_assert_true((bool)preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $s['time'], $m), "day $n: a time");
		$minutes = (int)$m[1] * 60 + (int)$m[2];
		pg_assert_true($minutes > $last, "day $n: after the one before");
		$last = $minutes;
	}
	pg_assert_same(4, count($d['approach']), 'four points of the approach');
	pg_assert_same(6, count($d['facilities']), 'six places on the campus');
	pg_assert_same(6, count($d['staff']), 'six members of staff');
	foreach ($d['staff'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "staff $n: $f");
	}
	pg_assert_same(4, count($d['accreditations']), 'four accreditations');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['programmes'][$t['programme']]), "testimonial $n: about a programme");
		pg_assert_contains($d['programmes'][$t['programme']]['title'], $t['role'], "testimonial $n: names the programme");
	}
	pg_assert_same(6, count($d['events']), 'six sample events');
	$featured = 0;
	foreach ($d['events'] as $key => $e) {
		pg_assert_true($e['programme'] === 'all' || isset($d['programmes'][$e['programme']]), "event $key: known programme");
		pg_assert_true(in_array($d['event_programmes'][$key], $d['lists']['event_programme_titles'], true), "event $key: its programme is an answer of the list");
		foreach (array('title', 'when', 'place', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_same(implode("\n", $e['details']), call_user_func($d['details_text'], $key), "event $key: a detail line per item");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');
	// The pick lists: one answer per programme (plus "whole school", "not
	// sure"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['programmes']), count($l['programme_titles']), 'an answer per programme');
	pg_assert_same($d['whole_school'], $l['event_programme_titles'][0], 'events: the whole school first');
	pg_assert_same(count($d['programmes']) + 1, count($l['event_programme_titles']), 'events: an answer per programme and the whole school');
	pg_assert_same(count($d['programmes']) + 1, count($l['application_programme_choices']), 'application: an answer per programme and not sure');
	pg_assert_same(count($d['events']), count($l['event_titles']), 'an answer per event');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['application_statuses']), 'five states of an application');
	pg_assert_same(3, count($l['visit_statuses']), 'three states of a visit request');
}

function test_school_page_links_name_template_pages()
{
	$known = array_flip(_test_sh_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_sh_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The school sells nothing online: no shared part places the cart or links
// a shop page, and the menu marks the current page itself.
function test_school_shared_parts_have_no_shop()
{
	$json = json_encode(_test_sh_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_sh_load();
	pg_assert_contains('{{page:apply}}', json_encode($d['header']), 'the header opens the application');
	pg_assert_contains('{{page:visit}}', json_encode($d['shared'][2]), 'the band books a visit');
	$nodes = array();
	_test_sh_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the event list filtered on each programme, in the words
// the Programme list uses, and links every programme's own page.
function test_school_footer_filters_events_and_links_programmes()
{
	$d = _test_sh_load();
	$nodes = array();
	_test_sh_nodes($d['footer'], $nodes);
	$seen = array();
	$prefix = '{{page:events}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		pg_assert_true(isset($q['programme']) && in_array($q['programme'], $d['lists']['event_programme_titles'], true), 'the link names a programme answer');
		$seen[$q['programme']] = true;
	}
	pg_assert_same(count($d['lists']['event_programme_titles']), count($seen), 'a link per programme and the whole school');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['programmes']) as $key) pg_assert_contains('{{page:programme_' . $key . '}}', $json, "the footer links programme_$key");
}

// The values a card reads from its form's fields, and its texts.
function _test_sh_card_reads($card)
{
	$nodes = array();
	_test_sh_nodes($card, $nodes);
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

// An event card reads every value from the Events form's fields, with the
// sample's own words on the canvas; the event facts read every fact field.
// A programme card opens the programme's page.
function test_school_cards_read_their_records()
{
	$d = _test_sh_load();
	foreach ($d['event_cards'] as $key => $card) {
		list($tokens, $texts) = _test_sh_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'programme', 'when', 'place', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: card reads $t");
		}
		$e = $d['events'][$key];
		pg_assert_true(in_array($e['title'], $texts, true), "event $key: sample title on the canvas");
		pg_assert_true(in_array($d['event_programmes'][$key], $texts, true), "event $key: sample programme on the canvas");
		list($tokens) = _test_sh_card_reads($d['event_facts'][$key]);
		foreach (array('when', 'place', 'programme') as $t) pg_assert_true(isset($tokens[$t]), "event $key: facts read $t");
	}
	foreach ($d['programme_cards'] as $key => $card) {
		pg_assert_contains('{{page:programme_' . $key . '}}', json_encode($card), "programme $key: the card opens its page");
	}
}

// Classes the template adds of its own start with psh-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with sh-.
function test_school_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_sh_trees() as $t) _test_sh_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc)-/', $c), "class $c is not another template's");
			if (strpos($c, 'psh-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sh-') === 0, "id $id is the template's");
	}
	foreach (array('psh-brand', 'psh-display', 'psh-eyebrow', 'psh-lift', 'psh-stat', 'psh-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old event
// a year later; an event says "a Saturday in spring", never its date.
function test_school_no_fixed_dates()
{
	$d = _test_sh_load();
	$json = json_encode(array($d['events'], $d['programmes'], $d['staff'], $d['school'], $d['day'], _test_sh_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a programme is "program", an application
// "başvuru", an event "etkinlik", a parent "veli".
function test_school_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProgrammes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'program', 0, 'UTF-8') !== false, "'$key' says program");
		if (preg_match('/\bApplications?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'başvuru', 0, 'UTF-8') !== false, "'$key' says başvuru");
		if (preg_match('/^(Events?|All Events|New Event|Event \w+)$/', $key)) pg_assert_true(mb_stripos($tr, 'etkinlik', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\bParents?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veli', 0, 'UTF-8') !== false, "'$key' says veli");
	}
}

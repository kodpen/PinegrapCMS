<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's shared parts and cards
 * (includes/design_templates/pinegrap-skiresort/cards.php): every page link
 * names a page of the finished template, the shared parts never link a
 * shop page, the footer and the difficulty and area cards open the slope
 * list filtered in the list's own words, a lesson card and a stay card open
 * their request with answers of its lists, the record cards read their
 * form's fields, the classes are the template's own, the sample carries no
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

require_once(__DIR__ . '/design_template_skiresort_test.php');

// The parts as the template file reads them: parts.php, then cards.php in
// the same scope. Read once per run.
function _test_skc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$cards = array('difficulty' => array(), 'area' => array(), 'slope' => array(), 'slope_facts' => array(), 'lift' => array(),
		'event' => array(), 'lift_pass' => array(), 'season_pass' => array(), 'rental' => array(), 'lesson' => array(),
		'stay' => array(), 'person' => array(), 'testimonial' => array());
	foreach (array_keys($difficulties) as $key) $cards['difficulty'][$key] = $difficulty_card($key);
	foreach (array_keys($areas) as $key) $cards['area'][$key] = $area_card($key);
	foreach (array_keys($slopes) as $key) {
		$cards['slope'][$key] = $slope_card($key);
		$cards['slope_facts'][$key] = $slope_facts($key);
	}
	foreach (array_keys($lifts) as $key) $cards['lift'][$key] = $lift_card($key);
	foreach (array_keys($events) as $key) $cards['event'][$key] = $event_card($key);
	foreach (array_keys($lift_passes) as $key) $cards['lift_pass'][$key] = $lift_pass_card($key);
	foreach (array_keys($season_passes) as $key) $cards['season_pass'][$key] = $season_pass_card($key);
	foreach (array_keys($rental) as $key) $cards['rental'][$key] = $rental_card($key);
	foreach (array_keys($lessons) as $key) $cards['lesson'][$key] = $lesson_card($key);
	foreach (array_keys($lodging) as $key) $cards['stay'][$key] = $stay_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	foreach (array_keys($testimonials) as $n) $cards['testimonial'][] = $testimonial_card($n);
	$words = array(
		'difficulty_titles' => $difficulty_titles, 'area_titles' => $area_titles, 'lesson_choices' => $lesson_choices,
		'level_titles' => $level_titles, 'age_titles' => $age_titles, 'stay_choices' => $stay_choices,
	);
	$out = array(
		'slopes'   => $slopes,
		'lifts'    => $lifts,
		'events'   => $events,
		'difficulties' => $difficulties,
		'areas'    => $areas,
		'words'    => $words,
		'cards'    => $cards,
		'header'   => $header(),
		'footer'   => $footer(),
		'shared'   => array(
			'account_bar' => $account_bar_tree(), 'notice' => $notice(), 'snow_band' => $snow_band(), 'staff_bar' => $staff_bar(),
		),
		'parts'    => array($post_card(), $ai_summary(), $figure_row(), $hours_list(), $conditions_grid(),
			$comment_thread(array('title' => lang('Messages'), 'empty' => lang('No messages yet.'), 'heading' => lang('Send a Message'),
				'label' => lang('Your Message'), 'sign_in' => lang('Sign in to send a message.'), 'submit' => lang('Send'),
				'watch_box' => lang('E-mail me when a new message arrives'), 'follow' => lang('Follow'), 'unfollow' => lang('Unfollow'),
				'close' => lang('Close'), 'reopen' => lang('Reopen'), 'reply' => true, 'watch' => true)),
			$newsletter_line('skr-newsletter-email'), $newsletter_consent('skr-newsletter-consent'),
			$field('skr-x', lang('Name'), $input('skr-x', 'text', 'name')), $select('skr-y', 'level', lang('Choose'), $level_titles),
			$radios('skr-z', 'sport', lang('Ski'), $sport_choices, lang('Ski')), $file('skr-f', 'file', 'private')),
	);
	return $out;
}

// Every tree the parts and cards make.
function _test_skc_trees()
{
	$d = _test_skc_load();
	$trees = array_merge(array($d['header'], $d['footer']), array_values($d['shared']), $d['parts']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The links of a tree that open $page with a query, as name => values.
function _test_skc_query_links($tree, $page)
{
	$nodes = array();
	_test_sk_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_skiresort_card_links_name_template_pages()
{
	$known = array_flip(_test_sk_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_skc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Passes and rental are the shop's only with the shop on: no shared part
// or card links a shop page, so every part stands on a site without it
// (the account bar's cart leaves by itself). The menu marks the current
// page itself.
function test_skiresort_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_skc_trees());
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no part links to $key");
	$d = _test_skc_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared']['account_bar']), 'the account bar carries the cart');
	pg_assert_contains('"templateShared":"notice"', json_encode($d['shared']['account_bar']), 'the account bar places the notice');
	pg_assert_contains('{{page:lesson_request}}', json_encode($d['header']), 'the header books a lesson');
	pg_assert_contains('{{page:conditions}}', json_encode($d['shared']['snow_band']), 'the band shows the conditions');
	$nodes = array();
	_test_sk_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the slope list on each difficulty, in the words the
// list uses; a difficulty card and an area card open theirs the same way;
// a lesson card opens the request with an answer of each of its lists, a
// stay card the enquiry with its stay.
function test_skiresort_links_filter_in_the_lists_words()
{
	$d = _test_skc_load();
	$w = $d['words'];
	$q = _test_skc_query_links($d['footer'], 'slopes');
	pg_assert_same($w['difficulty_titles'], $q['difficulty'], 'a footer link per difficulty');
	foreach ($d['cards']['difficulty'] as $key => $card) {
		$q = _test_skc_query_links($card, 'slopes');
		pg_assert_same(array($d['difficulties'][$key]['title']), $q['difficulty'], "difficulty $key: the card filters the slopes");
	}
	foreach ($d['cards']['area'] as $key => $card) {
		$q = _test_skc_query_links($card, 'slopes');
		pg_assert_same(array($d['areas'][$key]['title']), $q['area'], "area $key: the card filters the slopes");
		pg_assert_true(in_array($q['area'][0], $w['area_titles'], true), "area $key: an answer of the list");
	}
	foreach ($d['cards']['lesson'] as $key => $card) {
		$q = _test_skc_query_links($card, 'lesson_request');
		pg_assert_true(in_array($q['lesson'][0], $w['lesson_choices'], true), "lesson $key: the lesson is an answer of the list");
		pg_assert_true(in_array($q['level'][0], $w['level_titles'], true), "lesson $key: the level is an answer of the list");
		pg_assert_true(in_array($q['age'][0], $w['age_titles'], true), "lesson $key: the age is an answer of the list");
	}
	foreach ($d['cards']['stay'] as $key => $card) {
		$q = _test_skc_query_links($card, 'group_enquiry');
		pg_assert_true(in_array($q['stay'][0], $w['stay_choices'], true), "stay $key: the stay is an answer of the list");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_skc_card_reads($card)
{
	$nodes = array();
	_test_sk_nodes($card, $nodes);
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
// sample's own words on the canvas; the facts list reads every fact field.
function test_skiresort_cards_read_their_records()
{
	$d = _test_skc_load();
	$reads = array(
		'slope' => array('slopes', array('photo_1', 'title', 'form_item_view', 'difficulty', 'status', 'area', 'length', 'summary')),
		'lift'  => array('lifts', array('title', 'kind', 'status', 'area', 'ride', 'capacity')),
		'event' => array('events', array('photo_1', 'title', 'form_item_view', 'event_date', 'kind', 'event_time', 'place', 'price', 'summary')),
	);
	foreach ($reads as $kind => $r) {
		foreach ($d['cards'][$kind] as $key => $card) {
			list($tokens, $texts) = _test_skc_card_reads($card);
			foreach ($r[1] as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: card reads $t");
			pg_assert_true(in_array($d[$r[0]][$key]['title'], $texts, true), "$kind $key: sample title on the canvas");
		}
	}
	foreach ($d['cards']['slope_facts'] as $key => $card) {
		list($tokens) = _test_skc_card_reads($card);
		foreach (array('difficulty', 'area', 'length', 'drop', 'lift', 'status') as $t) pg_assert_true(isset($tokens[$t]), "slope $key: facts read $t");
	}
	// A lift has no page of its own: its card links nowhere.
	foreach ($d['cards']['lift'] as $key => $card) {
		list($tokens) = _test_skc_card_reads($card);
		pg_assert_false(isset($tokens['form_item_view']), "lift $key: no record link");
	}
}

// Classes the template adds of its own start with psk-; another template's
// prefix copied in would stay unstyled. Field and section ids start with
// skr-; no text is faded.
function test_skiresort_card_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_skc_trees() as $t) _test_sk_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'psk-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'skr-') === 0, "id $id is the template's");
	}
	foreach (array('psk-brand', 'psk-display', 'psk-eyebrow', 'psk-lift', 'psk-stat', 'psk-quote', 'psk-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The parts are never dated. An event card's date leaf shows the sample's
// date counted from today; everything else has no year or month.
function test_skiresort_cards_no_fixed_dates()
{
	$d = _test_skc_load();
	$trees = _test_skc_trees();
	$plain = array();
	foreach ($trees as $t) {
		if (in_array($t, $d['cards']['event'], true)) continue;
		$plain[] = $t;
	}
	// The comment stars' SVG names its namespace (www.w3.org/2000/svg),
	// which is no date; links are read decoded ("Ages 7 to 12", not
	// "Ages%207%20to%2012").
	$json = rawurldecode(str_replace('http://www.w3.org/2000/svg', '', json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the parts');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the parts');
	foreach ($d['cards']['event'] as $key => $card) {
		$json = json_encode($card);
		pg_assert_true(strpos($json, '"event_date"') !== false, "event $key: the leaf reads the record's date");
	}
}

// Every literal of the cards has its Turkish, and the Turkish names the
// template's things one way: a slope is "pist", a lift pass "skipass",
// the ski school "kayak okulu", a lesson "ders".
function test_skiresort_cards_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/cards.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bSlopes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'pist', 0, 'UTF-8') !== false, "'$key' says pist");
		if (preg_match('/^Lift Passes$/', $key)) pg_assert_true(mb_stripos($tr, 'skipass', 0, 'UTF-8') !== false, "'$key' says skipass");
		if (preg_match('/\bSki School\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kayak okul', 0, 'UTF-8') !== false, "'$key' says kayak okulu");
		if (preg_match('/\bLesson\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's shared parts and cards
 * (includes/design_templates/pinegrap-zoo/cards.php): every page link names
 * a page of the finished template, the shared parts never link a shop
 * page, the footer, the habitat cards and the class pills open the animal
 * list filtered in the list's own words, a visit card opens the request
 * with an answer of its list, the record cards read their form's fields,
 * the classes are the template's own, the sample carries no fixed date and
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

require_once(__DIR__ . '/design_template_zoo_test.php');

// The parts as the template file reads them: parts.php, then cards.php in
// the same scope. Read once per run.
function _test_zooc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$cards = array('habitat' => array(), 'animal' => array(), 'animal_facts' => array(), 'event' => array(), 'ticket' => array(),
		'membership' => array(), 'adoption' => array(), 'visit' => array(), 'person' => array());
	foreach (array_keys($habitats) as $key) $cards['habitat'][$key] = $habitat_card($key);
	foreach (array_keys($animals) as $key) {
		$cards['animal'][$key] = $animal_card($key);
		$cards['animal_facts'][$key] = $animal_facts($key);
	}
	foreach (array_keys($events) as $key) $cards['event'][$key] = $event_card($key);
	foreach (array_keys($tickets) as $key) $cards['ticket'][$key] = $ticket_card($key);
	foreach (array_keys($memberships) as $key) $cards['membership'][$key] = $membership_card($key, $key === 'family');
	foreach (array_keys($adoption_levels) as $key) $cards['adoption'][$key] = $adoption_card($key);
	foreach (array_keys($visits) as $key) $cards['visit'][$key] = $visit_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	$words = array(
		'habitat_titles' => $habitat_titles, 'class_titles' => $class_titles, 'visit_choices' => $visit_choices,
	);
	$out = array(
		'animals'  => $animals,
		'events'   => $events,
		'habitats' => $habitats,
		'words'    => $words,
		'cards'    => $cards,
		'header'   => $header(),
		'footer'   => $footer(),
		'class_pills' => $class_pills(),
		'shared'   => array(
			'account_bar' => $account_bar_tree(), 'notice' => $notice(), 'visit_band' => $visit_band(), 'staff_bar' => $staff_bar(),
		),
		'parts'    => array($post_card(), $ai_summary(), $figure_row(), $hours_list(), $status_badge(lang('Endangered')),
			$comment_thread(array('title' => lang('Messages'), 'empty' => lang('No messages yet.'), 'heading' => lang('Send a Message'),
				'label' => lang('Your Message'), 'sign_in' => lang('Sign in to send a message.'), 'submit' => lang('Send'),
				'watch_box' => lang('E-mail me when a new message arrives'), 'follow' => lang('Follow'), 'unfollow' => lang('Unfollow'),
				'close' => lang('Close'), 'reopen' => lang('Reopen'), 'reply' => true, 'watch' => true)),
			$newsletter_line('zoo-newsletter-email'), $newsletter_consent('zoo-newsletter-consent'),
			$field('zoo-x', lang('Name'), $input('zoo-x', 'text', 'name')), $select('zoo-y', 'age', lang('Choose'), $age_titles),
			$radios('zoo-z', 'workshop', lang('Workshop'), $workshop_choices, $workshop_choices[0][0]), $file('zoo-f', 'file', 'private')),
	);
	return $out;
}

// Every tree the parts and cards make.
function _test_zooc_trees()
{
	$d = _test_zooc_load();
	$trees = array_merge(array($d['header'], $d['footer'], $d['class_pills']), array_values($d['shared']), $d['parts']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The links of a tree that open $page with a query, as name => values.
function _test_zooc_query_links($tree, $page)
{
	$nodes = array();
	_test_zoo_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_zoo_card_links_name_template_pages()
{
	$known = array_flip(_test_zoo_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_zooc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Tickets, memberships and adoptions are the shop's only with the shop on:
// no shared part or card links a shop page, so every part stands on a site
// without it (the account bar's cart leaves by itself). The menu marks the
// current page itself.
function test_zoo_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_zooc_trees());
	foreach (_test_zoo_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no part links to $key");
	$d = _test_zooc_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared']['account_bar']), 'the account bar carries the cart');
	pg_assert_contains('"templateShared":"notice"', json_encode($d['shared']['account_bar']), 'the account bar places the notice');
	pg_assert_contains('{{page:today}}', json_encode($d['shared']['notice']), 'the notice opens today\'s schedule');
	pg_assert_contains('{{page:visit_request}}', json_encode($d['header']), 'the header asks for a school or group visit');
	foreach (array('today', 'map', 'tickets') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', json_encode($d['shared']['visit_band']), "the visit band opens $key");
	}
	$nodes = array();
	_test_zoo_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the animal list on each habitat, in the words the list
// uses; a habitat card does the same, the class pills open it on each
// class; a visit card opens the request with an answer of its list.
function test_zoo_links_filter_in_the_lists_words()
{
	$d = _test_zooc_load();
	$w = $d['words'];
	$q = _test_zooc_query_links($d['footer'], 'animals');
	pg_assert_same($w['habitat_titles'], $q['habitat'], 'a footer link per habitat');
	foreach ($d['cards']['habitat'] as $key => $card) {
		$q = _test_zooc_query_links($card, 'animals');
		pg_assert_same(array($d['habitats'][$key]['title']), $q['habitat'], "habitat $key: the card filters the animals");
	}
	$q = _test_zooc_query_links($d['class_pills'], 'animals');
	pg_assert_same($w['class_titles'], $q['class'], 'a pill per class');
	foreach ($d['cards']['visit'] as $key => $card) {
		$q = _test_zooc_query_links($card, 'visit_request');
		pg_assert_true(in_array($q['visit'][0], $w['visit_choices'], true), "visit $key: the visit is an answer of the list");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_zooc_card_reads($card)
{
	$nodes = array();
	_test_zoo_nodes($card, $nodes);
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
function test_zoo_cards_read_their_records()
{
	$d = _test_zooc_load();
	$reads = array(
		'animal' => array('animals', array('photo_1', 'title', 'form_item_view', 'species', 'habitat', 'habitat_number', 'conservation_status', 'summary')),
		'event'  => array('events', array('title', 'form_item_view', 'event_date', 'kind', 'event_time', 'place', 'price', 'summary')),
	);
	foreach ($reads as $kind => $r) {
		foreach ($d['cards'][$kind] as $key => $card) {
			list($tokens, $texts) = _test_zooc_card_reads($card);
			foreach ($r[1] as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: card reads $t");
			pg_assert_true(in_array($d[$r[0]][$key]['title'], $texts, true), "$kind $key: sample title on the canvas");
		}
	}
	foreach ($d['cards']['animal_facts'] as $key => $card) {
		list($tokens) = _test_zooc_card_reads($card);
		foreach (array('habitat', 'habitat_number', 'animal_class', 'diet', 'lifespan') as $t) pg_assert_true(isset($tokens[$t]), "animal $key: facts read $t");
	}
	// The habitat pin on an animal card takes its zone's colour and the
	// block family's round marker.
	$json = json_encode($d['cards']['animal']['penguin']);
	pg_assert_contains('pg-zoo-pin ' . $d['habitats']['polar']['pin'], $json, 'the penguin\'s pin is the Polar Coast colour');
	// One membership is set apart.
	$featured = 0;
	foreach ($d['cards']['membership'] as $card) if (strpos(json_encode($card), 'border-success border-2') !== false) $featured++;
	pg_assert_same(1, $featured, 'one membership is set apart');
}

// Classes the template adds of its own start with pzo-; another template's
// prefix copied in would stay unstyled. Field and section ids start with
// zoo-; no text is faded; no eyebrow line.
function test_zoo_card_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_zooc_trees() as $t) _test_zoo_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "no eyebrow line ($c)");
			if (strpos($c, 'pzo-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'zoo-') === 0, "id $id is the template's");
	}
	foreach (array('pzo-brand', 'pzo-display', 'pzo-stat', 'pzo-price', 'pzo-zone') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The parts are never dated. An event card's date block shows the
// sample's date counted from today; everything else has no year or month.
function test_zoo_cards_no_fixed_dates()
{
	$d = _test_zooc_load();
	$plain = array();
	foreach (_test_zooc_trees() as $t) {
		if (in_array($t, $d['cards']['event'], true)) continue;
		$plain[] = $t;
	}
	// The comment stars' SVG names its namespace (www.w3.org/2000/svg),
	// which is no date; links are read decoded.
	$json = rawurldecode(str_replace('http://www.w3.org/2000/svg', '', json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the parts');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the parts');
	foreach ($d['cards']['event'] as $key => $card) {
		pg_assert_true(strpos(json_encode($card), '"event_date"') !== false, "event $key: the date block reads the record's date");
	}
}

// Every literal of the cards has its Turkish, and the Turkish names the
// template's things one way: an animal is "hayvan", a habitat "yaşam
// alanı", an adoption "sahiplen…", a ticket "bilet", a feeding "besleme".
function test_zoo_cards_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/cards.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bAnimals?\b/', $key)) pg_assert_true(mb_stripos($tr, 'hayvan', 0, 'UTF-8') !== false, "'$key' says hayvan");
		if (preg_match('/^Habitat\b/', $key)) pg_assert_true(mb_stripos($tr, 'yaşam alan', 0, 'UTF-8') !== false, "'$key' says yaşam alanı");
		if (preg_match('/^Adopt\b/', $key)) pg_assert_true(mb_stripos($tr, 'sahiplen', 0, 'UTF-8') !== false, "'$key' says sahiplen");
		if (preg_match('/\bTickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
		if (preg_match('/\bFeeding\b/i', $key)) pg_assert_true(mb_stripos($tr, 'besleme', 0, 'UTF-8') !== false, "'$key' says besleme");
	}
}

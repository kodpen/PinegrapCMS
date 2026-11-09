<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's shared parts and cards
 * (includes/design_templates/pinegrap-planetarium/cards.php): every page
 * link names a page of the finished template, the shared parts never link
 * a shop page, the header and bars sit on the night ground, the footer and
 * the audience cards open the show list filtered in the list's own words,
 * a visit card opens the request with an answer of its list, the record
 * cards read their form's fields, the classes are the template's own, the
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

require_once(__DIR__ . '/design_template_planetarium_test.php');

// The parts as the template file reads them: parts.php, then cards.php in
// the same scope. Read once per run.
function _test_plc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$cards = array('audience' => array(), 'show' => array(), 'show_facts' => array(), 'night' => array(), 'night_facts' => array(),
		'telescope' => array(), 'ticket' => array(), 'membership' => array(), 'visit' => array(), 'person' => array());
	foreach (array_keys($audiences) as $key) $cards['audience'][$key] = $audience_card($key);
	foreach (array_keys($shows) as $key) {
		$cards['show'][$key] = $show_card($key);
		$cards['show_facts'][$key] = $show_facts($key);
	}
	foreach (array_keys($nights) as $key) {
		$cards['night'][$key] = $night_card($key);
		$cards['night_facts'][$key] = $night_facts($key);
	}
	foreach (array_keys($telescopes) as $n) $cards['telescope'][] = $telescope_card($n);
	foreach (array_keys($tickets) as $key) $cards['ticket'][$key] = $ticket_card($key);
	foreach (array_keys($memberships) as $key) $cards['membership'][$key] = $membership_card($key);
	foreach (array_keys($visits) as $key) $cards['visit'][$key] = $visit_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	$out = array(
		'shows'     => $shows,
		'nights'    => $nights,
		'audiences' => $audiences,
		'visits'    => $visits,
		'words'     => array('audience_titles' => $audience_titles, 'visit_choices' => $visit_choices),
		'prices'    => array('ticket' => $ticket_prices, 'membership' => $membership_prices, 'money' => $money),
		'cards'     => $cards,
		'header'    => $header(),
		'footer'    => $footer(),
		'shared'    => array('account_bar' => $account_bar_tree(), 'notice' => $notice(), 'staff_bar' => $staff_bar()),
		'staff_page' => $staff_page(array()),
		'parts'     => array($post_card(), $ai_summary(), $hours_list(),
			$comment_thread(array('title' => lang('Messages'), 'empty' => lang('No messages yet.'), 'heading' => lang('Send a Message'),
				'label' => lang('Your Message'), 'sign_in' => lang('Sign in to send a message.'), 'submit' => lang('Send'),
				'watch_box' => lang('E-mail me when a new message arrives'), 'follow' => lang('Follow'), 'unfollow' => lang('Unfollow'),
				'close' => lang('Close'), 'reopen' => lang('Reopen'), 'reply' => true, 'watch' => true)),
			$newsletter_line('plm-newsletter-email'), $newsletter_consent('plm-newsletter-consent'),
			$field('plm-x', lang('Name'), $input('plm-x', 'text', 'name')), $select('plm-y', 'grade', lang('Choose'), $grade_titles),
			$radios('plm-z', 'contact', lang('Contact'), $contact_choices, lang('E-mail')), $file('plm-f', 'file', 'private'),
			$textarea('plm-t', 'notes', 4, false), $agree('plm-a', 'consent', lang('Consent'), true), $form_step(1, lang('Your School')),
			$captcha(), $submit(lang('Send')), $edit_button(), $comments_section(lang('Messages'), 'visit_comments')),
	);
	return $out;
}

// Every tree the parts and cards make.
function _test_plc_trees()
{
	$d = _test_plc_load();
	$trees = array_merge(array($d['header'], $d['footer'], $d['staff_page']), array_values($d['shared']), $d['parts']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The links of a tree that open $page with a query, as name => values.
function _test_plc_query_links($tree, $page)
{
	$nodes = array();
	_test_planetarium_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_planetarium_card_links_name_template_pages()
{
	$known = array_flip(_test_planetarium_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_plc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Tickets and memberships are the shop's only with the shop on: no shared
// part or card links a shop page, so every part stands on a site without
// it (the account bar's cart leaves by itself). The menu marks the current
// page itself; the bar, the header and the footer sit on the night ground.
function test_planetarium_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_plc_trees());
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no part links to $key");
	$d = _test_plc_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared']['account_bar']), 'the account bar carries the cart');
	pg_assert_contains('"templateShared":"notice"', json_encode($d['shared']['account_bar']), 'the account bar places the notice');
	pg_assert_contains('{{page:tonight}}', json_encode($d['shared']['notice']), 'the notice opens tonight\'s sky');
	pg_assert_contains('{{page:visit_request}}', json_encode($d['header']), 'the header requests a school visit');
	pg_assert_contains('"templateShared":"staff_bar"', json_encode($d['staff_page']), 'a staff page places the staff bar');
	foreach (array('account bar' => $d['shared']['account_bar'], 'header' => $d['header'], 'footer' => $d['footer']) as $name => $tree) {
		pg_assert_same('dark', _test_planetarium_attr($tree, 'data-bs-theme'), "the $name sits on the night ground");
	}
	$nodes = array();
	_test_planetarium_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the show list on each audience, in the words the list
// uses; an audience card opens its own; a visit card opens the request
// with an answer of its list.
function test_planetarium_links_filter_in_the_lists_words()
{
	$d = _test_plc_load();
	$w = $d['words'];
	$q = _test_plc_query_links($d['footer'], 'shows');
	pg_assert_same($w['audience_titles'], $q['audience'], 'a footer link per audience');
	foreach ($d['cards']['audience'] as $key => $card) {
		$q = _test_plc_query_links($card, 'shows');
		pg_assert_same(array($d['audiences'][$key]['title']), $q['audience'], "audience $key: the card filters the shows");
	}
	foreach ($d['cards']['visit'] as $key => $card) {
		$q = _test_plc_query_links($card, 'visit_request');
		pg_assert_same(array($d['visits'][$key]['title']), $q['visit'], "visit $key: the card opens its request");
		pg_assert_true(in_array($q['visit'][0], $w['visit_choices'], true), "visit $key: an answer of the list");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_plc_card_reads($card)
{
	$nodes = array();
	_test_planetarium_nodes($card, $nodes);
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
function test_planetarium_cards_read_their_records()
{
	$d = _test_plc_load();
	$reads = array(
		'show'  => array('shows', array('photo_1', 'title', 'form_item_view', 'show_date', 'audience', 'showtimes', 'length', 'age', 'language', 'summary')),
		'night' => array('nights', array('photo_1', 'title', 'form_item_view', 'night_date', 'place', 'status', 'start_time', 'target', 'places', 'summary')),
	);
	foreach ($reads as $kind => $r) {
		foreach ($d['cards'][$kind] as $key => $card) {
			list($tokens, $texts) = _test_plc_card_reads($card);
			foreach ($r[1] as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: card reads $t");
			pg_assert_true(in_array($d[$r[0]][$key]['title'], $texts, true), "$kind $key: sample title on the canvas");
		}
	}
	$facts = array(
		'show_facts'  => array('show_date', 'showtimes', 'audience', 'length', 'age', 'language'),
		'night_facts' => array('night_date', 'start_time', 'target', 'place', 'places', 'status'),
	);
	foreach ($facts as $kind => $list) {
		foreach ($d['cards'][$kind] as $key => $card) {
			list($tokens) = _test_plc_card_reads($card);
			foreach ($list as $t) pg_assert_true(isset($tokens[$t]), "$kind $key: reads $t");
		}
	}
	// Prices are the table's, as the shop writes them.
	foreach ($d['cards']['ticket'] as $key => $card) {
		list(, $texts) = _test_plc_card_reads($card);
		pg_assert_true(in_array($d['prices']['money']($d['prices']['ticket'][$key]), $texts, true), "ticket $key: its price");
	}
}

// Classes the template adds of its own start with ppm-; another template's
// prefix copied in would stay unstyled. Field and section ids start with
// plm-; no text is faded.
function test_planetarium_card_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_plc_trees() as $t) _test_planetarium_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			if (preg_match('/^([a-z]{3})-/', $c, $p) && $p[1][0] === 'p' && !in_array($p[1], array('ppm'), true)) {
				pg_assert_true(false, "class $c is not another template's");
			}
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'ppm-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'plm-') === 0, "id $id is the template's");
	}
	foreach (array('ppm-brand', 'ppm-display', 'ppm-eyebrow', 'ppm-card', 'ppm-time', 'ppm-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The parts are never dated. A show's or a night's date leaf and facts
// show the sample's date counted from today; everything else has no year
// or month.
function test_planetarium_cards_no_fixed_dates()
{
	$d = _test_plc_load();
	$dated = array_merge(array_values($d['cards']['show']), array_values($d['cards']['show_facts']),
		array_values($d['cards']['night']), array_values($d['cards']['night_facts']));
	$plain = array();
	foreach (_test_plc_trees() as $t) {
		if (in_array($t, $dated, true)) continue;
		$plain[] = $t;
	}
	// The comment stars' SVG names its namespace (www.w3.org/2000/svg),
	// which is no date; links are read decoded.
	$json = rawurldecode(str_replace('http://www.w3.org/2000/svg', '', json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the parts');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the parts');
	$today = date('d.m.Y');
	pg_assert_contains($today, json_encode($d['cards']['show_facts']['solar_tour']), 'a show of today is dated today');
}

// Every literal of the cards has its Turkish, and the Turkish names the
// template's things one way: the dome "kubbe", the observatory
// "gözlemevi", a telescope night "teleskop gecesi", news "Haberler".
function test_planetarium_cards_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/cards.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bDome\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kubbe', 0, 'UTF-8') !== false, "'$key' says kubbe");
		if (preg_match('/\bObservatory\b/i', $key)) pg_assert_true(mb_stripos($tr, 'gözlemev', 0, 'UTF-8') !== false, "'$key' says gözlemevi");
		if (preg_match('/\bTelescope Nights?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teleskop gece', 0, 'UTF-8') !== false, "'$key' says teleskop gecesi");
		if ($key === 'News') pg_assert_same('Haberler', $tr, 'News is Haberler');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's parts
 * (includes/design_templates/pinegrap-sportsclub/): the club's data hangs
 * together (teams naming a known sport and competition, players of an
 * adult team in a position of its sport with no shirt number twice,
 * matches naming known teams, opponents and competitions, played matches
 * with a score and an outcome and matches to come without, products with
 * the template's SKU and a price, the forms' pick lists without an answer
 * twice), the sample matches fall before or after today as they should, a
 * price is written as the shop writes it, every page link of the shared
 * parts names a page of the finished template and none names a shop page,
 * the footer opens every team's page, the player, match and event cards
 * read their form's fields, a package card makes its choice in the
 * enquiry, the classes are the template's own, the sample carries no
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

// The parts as the template file reads them. Read once per run.
function _test_sp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/parts.php';
	$team_cards = array();
	foreach (array_keys($teams) as $key) $team_cards[$key] = $team_card($key);
	$player_cards = array();
	foreach (array_keys($players) as $key) $player_cards[$key] = $player_card($key);
	$match_rows = array();
	foreach (array_keys($matches) as $key) $match_rows[$key] = $match_row($key);
	$sponsor_tiles = array();
	foreach (array_keys($sponsors) as $key) $sponsor_tiles[$key] = $sponsor_tile($key);
	$package_cards = array();
	foreach (array_keys($sponsor_packages) as $key) $package_cards[$key] = $package_card($key);
	$event_cards = array();
	foreach (array_keys($events) as $key) $event_cards[$key] = $event_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'club'              => $club,
		'ground'            => $ground,
		'sports'            => $sports,
		'competitions'      => $competitions,
		'teams'             => $teams,
		'positions'         => $positions,
		'players'           => $players,
		'opponents'         => $opponents,
		'venues'            => $venues,
		'outcomes'          => $outcomes,
		'matches'           => $matches,
		'match_on'          => $match_on,
		'match_title'       => $match_title,
		'honours'           => $honours,
		'academy_points'    => $academy_points,
		'memberships'       => $memberships,
		'season_tickets'    => $season_tickets,
		'kit'               => $kit,
		'money'             => $money,
		'season_price'      => $season_price,
		'sponsor_tiers'     => $sponsor_tiers,
		'sponsors'          => $sponsors,
		'sponsor_packages'  => $sponsor_packages,
		'event_kinds'       => $event_kinds,
		'events'            => $events,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'team_titles'           => $team_titles,
			'youth_team_titles'     => $youth_team_titles,
			'position_titles'       => $position_titles,
			'competition_titles'    => $competition_titles,
			'venue_titles'          => $venue_titles,
			'outcome_titles'        => $outcome_titles,
			'event_kind_titles'     => $event_kind_titles,
			'package_titles'        => $package_titles,
			'trial_team_choices'    => $trial_team_choices,
			'registration_statuses' => $registration_statuses,
			'trial_statuses'        => $trial_statuses,
			'enquiry_statuses'      => $enquiry_statuses,
		),
		'radios'            => array('experience_choices' => $experience_choices, 'contact_choices' => $contact_choices),
		'team_cards'        => $team_cards,
		'player_cards'      => $player_cards,
		'match_rows'        => $match_rows,
		'sponsor_tiles'     => $sponsor_tiles,
		'package_cards'     => $package_cards,
		'event_cards'       => $event_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $join_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_sp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sp_nodes($child, $out);
	}
}

// All the trees the parts make, cards included; $dated adds the match
// rows, whose sample dates are counted from today.
function _test_sp_trees($dated = true)
{
	$d = _test_sp_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['team_cards']),
		array_values($d['player_cards']), $dated ? array_values($d['match_rows']) : array(), array_values($d['sponsor_tiles']),
		array_values($d['package_cards']), array_values($d['event_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_sp_page_keys()
{
	$keys = array(
		'home', 'teams', 'club', 'ground', 'academy', 'sponsors', 'faq',
		'squad', 'player', 'player_new', 'fixtures', 'results', 'match', 'match_new',
		'academy_register', 'registration_view', 'my_registrations', 'staff_registrations',
		'trial', 'trial_view', 'staff_trials', 'privacy', 'email_reply',
		'events', 'event', 'event_new', 'sponsor_enquiry', 'staff_sponsors', 'members',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array_keys(_test_sp_load()['teams']) as $key) $keys[] = 'team_' . $key;
	return array_merge($keys, _test_sp_shop_keys());
}

// The pages that exist only with the shop.
function _test_sp_shop_keys()
{
	return array('kit_shop', 'season_tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_sportsclub_data_hangs_together()
{
	$d = _test_sp_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['club'][$f]) !== '', "club: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['club']['phone']), 'club: phone number');
	pg_assert_true(strpos($d['club']['email'], '@example.com') !== false, 'club: a sample address');
	pg_assert_true(count($d['ground']['points']) >= 3, 'ground: points');
	$sports = array();
	$youth = 0;
	foreach ($d['teams'] as $key => $t) {
		pg_assert_true((bool)preg_match('/^[a-z0-9]+$/', $key), "team $key: a key fit for a page key");
		pg_assert_true(isset($d['sports'][$t['sport']]), "team $key: known sport");
		pg_assert_true(isset($d['competitions'][$t['competition']]), "team $key: known competition");
		foreach (array('title', 'group', 'coach', 'training', 'summary', 'icon') as $f) pg_assert_true(trim($t[$f]) !== '', "team $key: $f");
		$sports[$t['sport']] = true;
		if ($t['youth']) $youth++;
	}
	pg_assert_same(count($d['sports']), count($sports), 'a team of every sport');
	pg_assert_true($youth >= 2, 'youth teams for the academy');
	foreach ($d['positions'] as $key => $p) pg_assert_true(isset($d['sports'][$p['sport']]), "position $key: known sport");
	// Players: an adult team (youth players are never named in the
	// sample), a position of the team's sport, no shirt number twice in a
	// team.
	$numbers = array();
	foreach ($d['players'] as $key => $p) {
		pg_assert_true(isset($d['teams'][$p['team']]), "player $key: known team");
		pg_assert_false($d['teams'][$p['team']]['youth'], "player $key: not a youth player");
		pg_assert_true(isset($d['positions'][$p['position']]), "player $key: known position");
		pg_assert_same($d['teams'][$p['team']]['sport'], $d['positions'][$p['position']]['sport'], "player $key: a position of the team's sport");
		pg_assert_true(is_int($p['number']) && $p['number'] > 0 && $p['number'] < 100, "player $key: shirt number");
		pg_assert_false(isset($numbers[$p['team']][$p['number']]), "player $key: shirt number not taken");
		$numbers[$p['team']][$p['number']] = true;
		foreach (array('name', 'summary', 'bio') as $f) pg_assert_true(trim($p[$f]) !== '', "player $key: $f");
	}
	// Matches: known team, opponent, competition and venue; a played match
	// has its score and outcome, one to come has neither; both kinds are
	// in the sample.
	$played = 0;
	foreach ($d['matches'] as $key => $m) {
		pg_assert_true(isset($d['teams'][$m['team']]), "match $key: known team");
		pg_assert_true(isset($d['opponents'][$m['opponent']]), "match $key: known opponent");
		pg_assert_true(isset($d['competitions'][$m['competition']]), "match $key: known competition");
		pg_assert_true(isset($d['venues'][$m['venue']]), "match $key: home or away");
		pg_assert_true(is_int($m['weekday']) && $m['weekday'] >= 1 && $m['weekday'] <= 7, "match $key: weekday");
		pg_assert_true((bool)preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $m['kickoff']), "match $key: kick-off");
		pg_assert_true(trim($m['report']) !== '', "match $key: report");
		if ($m['weeks'] < 0) {
			$played++;
			pg_assert_true((bool)preg_match('/^[0-9]+–[0-9]+$/u', $m['score']), "match $key: a score");
			pg_assert_true(isset($d['outcomes'][$m['outcome']]), "match $key: an outcome");
		} else {
			pg_assert_same('', $m['score'], "match $key: no score before it is played");
			pg_assert_same('', $m['outcome'], "match $key: no outcome before it is played");
		}
	}
	pg_assert_true($played >= 2 && $played < count($d['matches']) - 1, 'results and fixtures both in the sample');
	// Products: the template's SKU, no SKU twice, a price.
	$skus = array();
	foreach (array('memberships', 'season_tickets', 'kit') as $group) {
		foreach ($d[$group] as $key => $p) {
			pg_assert_true((bool)preg_match('/^CLUB-[A-Z0-9-]+$/', $p['sku']), "$group $key: the template's SKU");
			pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "$group $key: a price");
			pg_assert_false(isset($skus[$p['sku']]), "$group $key: SKU not taken");
			$skus[$p['sku']] = true;
		}
	}
	foreach ($d['memberships'] as $key => $p) {
		pg_assert_true(is_int($p['days']) && $p['days'] > 0, "membership $key: a term in days");
		pg_assert_true(count($p['points']) >= 3, "membership $key: points");
	}
	$tiers = array();
	foreach ($d['sponsors'] as $key => $s) {
		pg_assert_true(isset($d['sponsor_tiers'][$s['tier']]), "sponsor $key: known tier");
		$tiers[$s['tier']] = true;
	}
	pg_assert_same(count($d['sponsor_tiers']), count($tiers), 'a sponsor in every tier');
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_kinds'][$e['kind']]), "event $key: known kind");
		foreach (array('title', 'when', 'place', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['teams'][$t['team']]), "testimonial $n: about a team");
	}
	// The pick lists: one answer per team, position, competition, kind and
	// package, no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['teams']), count($l['team_titles']), 'an answer per team');
	pg_assert_same($youth, count($l['youth_team_titles']), 'an answer per youth team');
	pg_assert_same(count($d['positions']), count($l['position_titles']), 'an answer per position');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per event kind');
	pg_assert_same(count($d['sponsor_packages']), count($l['package_titles']), 'an answer per package');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A match to come falls after today, a played one before it, and the
// match title puts the home side first.
function test_sportsclub_matches_fall_around_today()
{
	$d = _test_sp_load();
	$today = date('Y-m-d');
	foreach ($d['matches'] as $key => $m) {
		$day = call_user_func($d['match_on'], $m['weekday'], $m['weeks']);
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $day), "match $key: an ISO date");
		pg_assert_same($m['weekday'], (int)date('N', strtotime($day)), "match $key: on its weekday");
		if ($m['weeks'] < 0) pg_assert_true($day < $today, "match $key: played before today");
		else pg_assert_true($day > $today, "match $key: still to come");
		$title = call_user_func($d['match_title'], $key);
		$opponent = $d['opponents'][$m['opponent']];
		pg_assert_contains($opponent, $title, "match $key: the title names the opponent");
		$us = strpos($title, '{{site_name}}');
		pg_assert_true($m['venue'] === 'home' ? $us === 0 : $us > 0, "match $key: the home side first");
	}
}

// A price shows one amount: the base currency's figure, the USD figure
// for a currency the table does not list.
function test_sportsclub_price_is_written_as_the_shop_writes_it()
{
	$d = _test_sp_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['memberships']['adult']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s a season'), pg_money_text($amount, $symbol)), call_user_func($d['season_price'], $prices), 'the price line');
}

function test_sportsclub_page_links_name_template_pages()
{
	$known = array_flip(_test_sp_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_sp_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_sportsclub_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_sp_trees());
	foreach (_test_sp_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_sp_load();
	pg_assert_contains('{{page:trial}}', json_encode($d['header']), 'the header asks for a trial');
	$nodes = array();
	_test_sp_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer and the team cards open every team's own page.
function test_sportsclub_team_pages_are_linked()
{
	$d = _test_sp_load();
	$footer = json_encode($d['footer']);
	foreach ($d['team_cards'] as $key => $card) {
		pg_assert_contains('{{page:team_' . $key . '}}', $footer, "the footer links team $key");
		pg_assert_contains('{{page:team_' . $key . '}}', json_encode($card), "the card links team $key");
	}
}

// The tokens a card binds and the texts it shows on the canvas.
function _test_sp_tokens($tree)
{
	$nodes = array();
	_test_sp_nodes($tree, $nodes);
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

// The player, match and event cards read every value from their form's
// fields, with the sample's own words on the canvas.
function test_sportsclub_cards_read_their_records()
{
	$d = _test_sp_load();
	foreach ($d['player_cards'] as $key => $card) {
		list($tokens, $texts) = _test_sp_tokens($card);
		foreach (array('photo', 'name', 'form_item_view', 'position', 'team', 'shirt_number', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "player $key: card reads $t");
		}
		$p = $d['players'][$key];
		pg_assert_true(in_array($p['name'], $texts, true), "player $key: sample name on the canvas");
		pg_assert_true(in_array($d['positions'][$p['position']]['title'], $texts, true), "player $key: sample position on the canvas");
	}
	foreach ($d['match_rows'] as $key => $row) {
		list($tokens, $texts) = _test_sp_tokens($row);
		foreach (array('match_date', 'kickoff', 'competition', 'team', 'title', 'form_item_view', 'venue', 'score', 'outcome') as $t) {
			pg_assert_true(isset($tokens[$t]), "match $key: row reads $t");
		}
		$m = $d['matches'][$key];
		pg_assert_true(in_array(call_user_func($d['match_title'], $key), $texts, true), "match $key: sample title on the canvas");
		if ($m['score'] !== '') pg_assert_true(in_array($m['score'], $texts, true), "match $key: sample score on the canvas");
	}
	foreach ($d['event_cards'] as $key => $card) {
		list($tokens, $texts) = _test_sp_tokens($card);
		foreach (array('kind', 'title', 'form_item_view', 'summary', 'event_when', 'place') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: card reads $t");
		}
		pg_assert_true(in_array($d['events'][$key]['title'], $texts, true), "event $key: sample title on the canvas");
	}
}

// A package card opens the sponsorship enquiry with the package chosen,
// in the words the Package list uses (the form starts from ?package=).
function test_sportsclub_package_links_choose_in_the_enquiry()
{
	$d = _test_sp_load();
	foreach ($d['package_cards'] as $key => $card) {
		$nodes = array();
		_test_sp_nodes($card, $nodes);
		$found = array();
		foreach ($nodes as $node) {
			if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:sponsor_enquiry}}?') !== 0) continue;
			parse_str(substr($node['props']['href'], strlen('{{page:sponsor_enquiry}}?')), $q);
			$found[] = $q;
		}
		pg_assert_same(1, count($found), "package $key: one enquiry link");
		pg_assert_same($d['sponsor_packages'][$key]['title'], $found[0]['package'], "package $key: the link names the package");
		pg_assert_true(in_array($found[0]['package'], $d['lists']['package_titles'], true), "package $key: a Package answer");
	}
}

// Classes the template adds of its own start with psp-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with sp-.
function test_sportsclub_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_sp_trees() as $t) _test_sp_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'psp-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sp-') === 0, "id $id is the template's");
	}
	foreach (array('psp-brand', 'psp-display', 'psp-eyebrow', 'psp-lift', 'psp-score', 'psp-crest') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later. The match rows' sample dates are counted from
// today and left out.
function test_sportsclub_no_fixed_dates()
{
	$d = _test_sp_load();
	$json = json_encode(array($d['club'], $d['teams'], $d['players'], $d['honours'], $d['events'], $d['memberships'],
		$d['season_tickets'], $d['kit'], $d['sponsors'], _test_sp_trees(false)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a team is "takım", a squad "kadro", the
// youth academy "altyapı", a season ticket "kombine", a membership
// "üyelik".
function test_sportsclub_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bTeams?\b/', $key)) pg_assert_true(mb_stripos($tr, 'takım', 0, 'UTF-8') !== false, "'$key' says takım");
		if (preg_match('/\bSquads?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kadro', 0, 'UTF-8') !== false, "'$key' says kadro");
		if (preg_match('/\bYouth Academy\b/', $key)) pg_assert_true(mb_stripos($tr, 'altyapı', 0, 'UTF-8') !== false, "'$key' says altyapı");
		if (preg_match('/\bSeason Ticket\b/', $key)) pg_assert_true(mb_stripos($tr, 'kombine', 0, 'UTF-8') !== false, "'$key' says kombine");
		if (preg_match('/\bMembership\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyeli', 0, 'UTF-8') !== false, "'$key' says üyelik");
	}
}

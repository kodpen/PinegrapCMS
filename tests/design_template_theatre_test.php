<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's parts
 * (includes/design_templates/pinegrap-theatre/): the theatre's data hangs
 * together (box office hours, ticket bands and friends tiers each with a
 * price in every currency, sample productions and performances naming a
 * known genre, state, stage, production and access mark, performances
 * counted from today, youth groups, workshops, spaces, people, the forms'
 * pick lists without an answer twice), every page link of the shared parts
 * names a page of the finished template, the shared parts never link a
 * shop page, the footer opens the season filtered, the cards read their
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

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_th_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/parts.php';
	$cards = array('production' => array(), 'production_facts' => array(), 'performance' => array(), 'performance_facts' => array(),
		'genre' => array(), 'access' => array(), 'ticket' => array(), 'tier' => array(), 'group' => array(), 'workshop' => array(),
		'space' => array(), 'person' => array(), 'testimonial' => array());
	$words = array('production' => array(), 'genre' => array(), 'performance' => array(), 'access' => array(), 'state' => array());
	foreach (array_keys($productions) as $key) {
		$cards['production'][$key] = $production_card($key);
		$cards['production_facts'][$key] = $production_facts($key);
		$words['production'][$key] = $production_status($key);
		$words['genre'][$key] = $production_genre($key);
	}
	$dates = array();
	foreach (array_keys($performances) as $key) {
		$cards['performance'][$key] = $performance_row($key);
		$cards['performance_facts'][$key] = $performance_facts($key);
		$words['performance'][$key] = $performance_production($key);
		$words['access'][$key] = $performance_access($key);
		$words['state'][$key] = $performance_state($key);
		$dates[$key] = $perf_on($performances[$key]['weekday'], $performances[$key]['weeks']);
	}
	foreach (array_keys($genres) as $key) $cards['genre'][$key] = $genre_card($key);
	foreach (array_keys($access_types) as $key) $cards['access'][$key] = $access_card($key);
	foreach (array_keys($tickets) as $key) $cards['ticket'][$key] = $ticket_card($key);
	foreach (array_keys($tiers) as $key) $cards['tier'][$key] = $tier_card($key);
	foreach (array_keys($youth_groups) as $key) $cards['group'][$key] = $group_card($key);
	foreach (array_keys($workshops) as $key) $cards['workshop'][$key] = $workshop_card($key);
	foreach (array_keys($spaces) as $key) $cards['space'][$key] = $space_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	foreach (array_keys($testimonials) as $n) $cards['testimonial'][] = $testimonial_card($n);
	$out = array(
		'theatre'         => $theatre,
		'box_office_hours'=> $box_office_hours,
		'stages'          => $stages,
		'genres'          => $genres,
		'status_titles'   => $production_status_titles,
		'productions'     => $productions,
		'access_types'    => $access_types,
		'ticket_states'   => $ticket_states,
		'performances'    => $performances,
		'dates'           => $dates,
		'tickets'         => $tickets,
		'ticket_prices'   => $ticket_prices,
		'tiers'           => $tiers,
		'tier_prices'     => $tier_prices,
		'youth_groups'    => $youth_groups,
		'workshops'       => $workshops,
		'spaces'          => $spaces,
		'facilities'      => $facilities,
		'getting_there'   => $getting_there,
		'people'          => $people,
		'figures'         => $figures,
		'testimonials'    => $testimonials,
		'details_text'    => $details_text,
		'price_texts'     => array($ticket_price_text('stalls'), $tier_price_text('friend')),
		'words'           => $words,
		'lists'           => array(
			'production_statuses'     => $production_statuses,
			'genre_titles'            => $genre_titles,
			'stage_titles'            => $stage_titles,
			'access_titles'           => $access_titles,
			'ticket_state_titles'     => $ticket_state_titles,
			'group_titles'            => $group_titles,
			'workshop_titles'         => $workshop_titles,
			'school_workshop_choices' => $school_workshop_choices,
			'space_titles'            => $space_titles,
			'space_choices'           => $space_choices,
			'hire_kinds'              => $hire_kinds,
			'guest_counts'            => $guest_counts,
			'year_groups'             => $year_groups,
			'visit_day_choices'       => $visit_day_choices,
			'hire_statuses'           => $hire_statuses,
			'school_group_statuses'   => $school_group_statuses,
			'place_statuses'          => $place_statuses,
		),
		'radios'          => array('contact_choices' => $contact_choices),
		'cards'           => $cards,
		'header'          => $header(),
		'footer'          => $footer(),
		'shared'          => array($account_bar_tree(), $notice(), $box_office_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row(), $hours_list()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_th_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_th_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_th_trees()
{
	$d = _test_th_load();
	$trees = array_merge(array($d['header'], $d['footer']), $d['shared']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_th_page_keys()
{
	return array(
		'home', 'visit', 'support', 'about', 'faq',
		'season', 'production', 'production_new',
		'whats_on', 'past_performances', 'performance', 'performance_new',
		'hire', 'hire_view', 'my_hire', 'staff_hire', 'privacy', 'email_hire_new', 'email_hire_received', 'email_reply',
		'learning', 'school_group', 'school_group_view', 'my_school_groups', 'staff_school_groups',
		'email_school_group_new', 'email_school_group_received',
		'youth_place', 'youth_place_view', 'my_youth_places', 'staff_youth_places',
		'email_youth_place_new', 'email_youth_place_received',
		'tickets', 'friends', 'product', 'cart', 'checkout', 'order', 'email_order',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_theatre_data_hangs_together()
{
	$d = _test_th_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'doors', 'late', 'licence') as $f) pg_assert_true(trim($d['theatre'][$f]) !== '', "theatre: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['theatre']['phone']), 'theatre: phone number');
	pg_assert_true(strpos($d['theatre']['email'], '@example.com') !== false, 'theatre: a sample address');
	pg_assert_same(4, count($d['box_office_hours']), 'four lines of box office hours');
	foreach ($d['box_office_hours'] as $n => $h) pg_assert_true(trim($h['days']) !== '' && trim($h['hours']) !== '', "hours $n: days and hours");
	// Every ticket band and tier has a price in each currency the catalog knows.
	foreach (array('tickets' => 'ticket_prices', 'tiers' => 'tier_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	foreach ($d['tickets'] as $key => $t) {
		foreach (array('title', 'who', 'view') as $f) pg_assert_true(trim($t[$f]) !== '', "ticket $key: $f");
	}
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_true(trim($t['title']) !== '' && trim($t['who']) !== '', "tier $key: title and who");
		pg_assert_same(4, count($t['benefits']), "tier $key: four benefits");
	}
	foreach ($d['price_texts'] as $text) pg_assert_true((bool)preg_match('/[0-9]/', $text), 'a price is written with its figure');
	pg_assert_same(2, count($d['stages']), 'two stages');
	pg_assert_same(6, count($d['genres']), 'six genres');
	pg_assert_same(6, count($d['productions']), 'six sample productions');
	$featured = 0;
	$states = array();
	foreach ($d['productions'] as $key => $p) {
		pg_assert_true(isset($d['status_titles'][$p['status']]), "production $key: known state");
		pg_assert_true(isset($d['genres'][$p['genre']]), "production $key: known genre");
		pg_assert_true(isset($d['stages'][$p['stage']]), "production $key: known stage");
		pg_assert_true(in_array($d['words']['production'][$key], $d['lists']['production_statuses'], true), "production $key: its state is an answer of the list");
		pg_assert_true(in_array($d['words']['genre'][$key], $d['lists']['genre_titles'], true), "production $key: its genre is an answer of the list");
		foreach (array('title', 'run', 'length', 'age', 'credits', 'summary') as $f) pg_assert_true(trim($p[$f]) !== '', "production $key: $f");
		pg_assert_same(4, count($p['cast']), "production $key: four lines of cast");
		pg_assert_same(implode("\n", $p['cast']), call_user_func($d['details_text'], $p['cast']), "production $key: a cast line per item");
		$states[$p['status']] = true;
		if ($p['featured']) $featured++;
	}
	pg_assert_same(3, count($states), 'a sample production in every state');
	pg_assert_same(3, $featured, 'three productions on the home');
	// Performances: of a known production that plays now, with a known
	// access mark and ticket state; the ones to come lie after today, the
	// past ones before it.
	pg_assert_same(10, count($d['performances']), 'ten sample performances');
	$today = date('Y-m-d');
	$featured = 0;
	$past = 0;
	$access = array();
	foreach ($d['performances'] as $key => $s) {
		pg_assert_true(isset($d['productions'][$s['production']]), "performance $key: known production");
		pg_assert_same('now', $d['productions'][$s['production']]['status'], "performance $key: of a production playing now");
		pg_assert_true(isset($d['access_types'][$s['access']]), "performance $key: known access");
		pg_assert_true(isset($d['ticket_states'][$s['state']]), "performance $key: known ticket state");
		pg_assert_same($d['productions'][$s['production']]['title'], $d['words']['performance'][$key], "performance $key: names its production by title");
		pg_assert_true(in_array($d['words']['access'][$key], $d['lists']['access_titles'], true), "performance $key: its access is an answer of the list");
		pg_assert_true(in_array($d['words']['state'][$key], $d['lists']['ticket_state_titles'], true), "performance $key: its ticket state is an answer of the list");
		pg_assert_true((bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $s['time']), "performance $key: curtain time");
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['dates'][$key]), "performance $key: a date");
		if ($s['weeks'] < 0) {
			$past++;
			pg_assert_true($d['dates'][$key] < $today, "performance $key: played before today");
			pg_assert_false($s['featured'], "performance $key: a past one is not on the home");
		} else {
			pg_assert_true($d['dates'][$key] > $today, "performance $key: still to come");
		}
		$access[$s['access']] = true;
		if ($s['featured']) $featured++;
	}
	pg_assert_same(2, $past, 'two past performances');
	pg_assert_same(3, $featured, 'three performances on the home');
	pg_assert_same(count($d['access_types']), count($access), 'a sample performance of every access kind');
	foreach ($d['access_types'] as $key => $a) pg_assert_true(trim($a['title']) !== '' && trim($a['text']) !== '', "access $key: title and text");
	pg_assert_same(4, count($d['youth_groups']), 'four youth theatre groups');
	foreach ($d['youth_groups'] as $key => $g) {
		foreach (array('title', 'ages', 'when', 'summary') as $f) pg_assert_true(trim($g[$f]) !== '', "group $key: $f");
	}
	pg_assert_same(4, count($d['workshops']), 'four school workshops');
	foreach ($d['workshops'] as $key => $w) {
		foreach (array('title', 'ages', 'length', 'summary') as $f) pg_assert_true(trim($w[$f]) !== '', "workshop $key: $f");
	}
	pg_assert_same(4, count($d['spaces']), 'four spaces for hire');
	foreach ($d['spaces'] as $key => $s) {
		foreach (array('icon', 'title', 'capacity', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "space $key: $f");
	}
	pg_assert_same(6, count($d['facilities']), 'six facilities');
	pg_assert_same(4, count($d['getting_there']), 'four ways to get there');
	pg_assert_same(6, count($d['people']), 'six people');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_same(3, count($d['testimonials']), 'three audience quotes');
	// The pick lists: one answer per state, genre, stage, access kind, ticket state, group, workshop (plus "not sure") and
	// space (plus "not sure"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['status_titles']), count($l['production_statuses']), 'an answer per state');
	pg_assert_same(count($d['genres']), count($l['genre_titles']), 'an answer per genre');
	pg_assert_same(count($d['stages']), count($l['stage_titles']), 'an answer per stage');
	pg_assert_same(count($d['access_types']), count($l['access_titles']), 'an answer per access kind');
	pg_assert_same(count($d['ticket_states']), count($l['ticket_state_titles']), 'an answer per ticket state');
	pg_assert_same(count($d['youth_groups']), count($l['group_titles']), 'an answer per group');
	pg_assert_same(count($d['workshops']) + 1, count($l['school_workshop_choices']), 'school group: an answer per workshop and not sure');
	pg_assert_same(count($d['spaces']) + 1, count($l['space_choices']), 'hire: an answer per space and not sure');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	foreach (array('hire_statuses', 'school_group_statuses', 'place_statuses') as $name) pg_assert_same(4, count($l[$name]), "$name: four states");
}

function test_theatre_page_links_name_template_pages()
{
	$known = array_flip(_test_th_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_th_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Tickets and friends are the shop's: no shared part or card links a shop
// page, so every part stands on a site without the shop (the account bar's
// cart leaves by itself). The menu marks the current page itself.
function test_theatre_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_th_trees());
	foreach (array('tickets', 'friends', 'product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_th_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared'][0]), 'the account bar carries the cart');
	pg_assert_contains('{{page:whats_on}}', json_encode($d['header']), 'the header finds a performance');
	pg_assert_contains('{{page:visit}}', json_encode($d['shared'][2]), 'the band plans a visit');
	$nodes = array();
	_test_th_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that open $page with a query, as name => values.
function _test_th_query_links($tree, $page)
{
	$nodes = array();
	_test_th_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		$q = substr($node['props']['href'], strlen($prefix));
		if (strpos($q, '#') !== false) $q = substr($q, 0, strpos($q, '#'));
		parse_str($q, $params);
		foreach ($params as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

// The footer opens the season on each state and each genre, in the words
// the lists use; a genre card, an access card, a group card, a workshop
// card and a space card open theirs the same way.
function test_theatre_links_filter_in_the_lists_words()
{
	$d = _test_th_load();
	$q = _test_th_query_links($d['footer'], 'season');
	pg_assert_same($d['lists']['production_statuses'], $q['status'], 'a footer link per production state');
	pg_assert_same($d['lists']['genre_titles'], $q['genre'], 'a footer link per genre');
	$cases = array(
		'genre'    => array('season', 'genre', 'genre_titles'),
		'access'   => array('whats_on', 'access', 'access_titles'),
		'group'    => array('youth_place', 'group', 'group_titles'),
		'workshop' => array('school_group', 'workshop', 'school_workshop_choices'),
		'space'    => array('hire', 'space', 'space_choices'),
	);
	foreach ($cases as $kind => $c) {
		foreach ($d['cards'][$kind] as $key => $card) {
			$q = _test_th_query_links($card, $c[0]);
			pg_assert_true(isset($q[$c[1]][0]) && in_array($q[$c[1]][0], $d['lists'][$c[2]], true), "$kind $key: the card opens {$c[0]} with an answer of the list");
		}
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_th_card_reads($card)
{
	$nodes = array();
	_test_th_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			// A field of the record a reference points at counts as a
			// read of the referencing field (the performance's production).
			foreach ($node['props']['_bindings'] as $prop => $token) {
				if ($token === '__ref') $token = $node['props']['_bindParams'][$prop]['field'];
				$tokens[$token] = true;
			}
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// Each record card reads every value from its form's fields, with the
// sample's own words on the canvas; the facts lists read every fact field.
function test_theatre_cards_read_their_records()
{
	$d = _test_th_load();
	foreach ($d['cards']['production'] as $key => $card) {
		list($tokens, $texts) = _test_th_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'genre', 'status', 'run', 'stage', 'summary') as $t) pg_assert_true(isset($tokens[$t]), "production $key: card reads $t");
		pg_assert_true(in_array($d['productions'][$key]['title'], $texts, true), "production $key: sample title on the canvas");
		pg_assert_true(in_array($d['words']['production'][$key], $texts, true), "production $key: sample state on the canvas");
		pg_assert_true(in_array($d['words']['genre'][$key], $texts, true), "production $key: sample genre on the canvas");
		list($tokens) = _test_th_card_reads($d['cards']['production_facts'][$key]);
		foreach (array('run', 'stage', 'running_time', 'age_guidance') as $t) pg_assert_true(isset($tokens[$t]), "production $key: facts read $t");
	}
	foreach ($d['cards']['performance'] as $key => $card) {
		list($tokens, $texts) = _test_th_card_reads($card);
		foreach (array('perf_date', 'perf_time', 'production', 'form_item_view', 'stage', 'access', 'ticket_state') as $t) pg_assert_true(isset($tokens[$t]), "performance $key: row reads $t");
		pg_assert_true(in_array($d['words']['performance'][$key], $texts, true), "performance $key: sample production on the canvas");
		pg_assert_true(in_array($d['dates'][$key], $texts, true), "performance $key: sample date on the canvas");
		list($tokens) = _test_th_card_reads($d['cards']['performance_facts'][$key]);
		foreach (array('production', 'perf_date', 'perf_time', 'stage', 'access', 'ticket_state') as $t) pg_assert_true(isset($tokens[$t]), "performance $key: facts read $t");
	}
}

// Classes the template adds of its own start with pth-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with th-.
function test_theatre_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_th_trees() as $t) _test_th_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pth-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'th-') === 0, "id $id is the template's");
	}
	foreach (array('pth-brand', 'pth-display', 'pth-eyebrow', 'pth-lift', 'pth-stat', 'pth-quote', 'pth-price', 'pth-date') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated by hand: a year or a month would read as an
// old season a year later; a production's run is words and a performance's
// date is counted from today.
function test_theatre_no_fixed_dates()
{
	$d = _test_th_load();
	$json = json_encode(array($d['productions'], $d['performances'], $d['youth_groups'], $d['workshops'], $d['spaces'], $d['theatre'], $d['tickets'], $d['tiers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/parts.php');
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}-[01][0-9]-[0-3][0-9]\b/', $src), 'no fixed date in the source');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a production is "oyun" or "yapım", a
// performance "temsil", a friend "dost", a stage "sahne", a youth theatre
// "gençlik tiyatrosu", hire "kiralama".
function test_theatre_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProductions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'oyun', 0, 'UTF-8') !== false || mb_stripos($tr, 'yapım', 0, 'UTF-8') !== false, "'$key' says oyun or yapım");
		if (preg_match('/\bPerformances?\b/', $key)) pg_assert_true(mb_stripos($tr, 'temsil', 0, 'UTF-8') !== false, "'$key' says temsil");
		if (preg_match('/\b(Friends?|Friends\')\b/', $key) && !preg_match('/Best Friends|Family Friends/', $key)) pg_assert_true(mb_stripos($tr, 'dost', 0, 'UTF-8') !== false, "'$key' says dost");
		if (preg_match('/\bYouth Theatre\b/', $key)) pg_assert_true(mb_stripos($tr, 'gençlik tiyatro', 0, 'UTF-8') !== false, "'$key' says gençlik tiyatrosu");
		if (preg_match('/^Hire\b|\bHire (a Space|Enquir)/', $key)) pg_assert_true(mb_stripos($tr, 'kirala', 0, 'UTF-8') !== false, "'$key' says kiralama");
	}
}

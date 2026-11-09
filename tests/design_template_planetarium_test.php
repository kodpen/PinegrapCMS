<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's data and page helpers
 * (includes/design_templates/pinegrap-planetarium/parts.php): the data
 * hangs together (shows naming a known audience, with times in order and
 * three of them today; nights naming a known place and state, still to
 * come; every ticket and membership with a price in every currency), the
 * pick lists follow the data without an answer twice, a price is written
 * as the shop writes it, the page shell places the shared parts, night
 * sections and headers take the dark colour mode, the helpers link only
 * template pages, the classes and ids are the template's own, the sample
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

// The data and helpers as the template file reads them. Read once per run.
function _test_planetarium_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/parts.php';
	$show_words = array();
	$show_dates = array();
	foreach ($shows as $key => $s) {
		$show_words[$key] = $show_audience($key);
		$show_dates[$key] = $show_on($s['day']);
	}
	$night_words = array();
	$night_dates = array();
	foreach ($nights as $key => $n) {
		$night_words[$key] = array($night_place($key), $night_status($key));
		$night_dates[$key] = $show_on($n['day']);
	}
	$out = array(
		'planetarium'       => $planetarium,
		'opening_hours'     => $opening_hours,
		'audiences'         => $audiences,
		'shows'             => $shows,
		'show_words'        => $show_words,
		'show_dates'        => $show_dates,
		'night_places'      => $night_places,
		'night_statuses'    => $night_statuses,
		'nights'            => $nights,
		'night_words'       => $night_words,
		'night_dates'       => $night_dates,
		'telescopes'        => $telescopes,
		'tickets'           => $tickets,
		'ticket_prices'     => $ticket_prices,
		'memberships'       => $memberships,
		'membership_prices' => $membership_prices,
		'visits'            => $visits,
		'grades'            => $grades,
		'people'            => $people,
		'details_text'      => $details_text,
		'money'             => $money,
		'query'             => $query,
		'membership_price'  => $membership_price_text('family'),
		'length_text'       => $length_text(40),
		'age_texts'         => array($age_text(0), $age_text(12)),
		'lists'             => array(
			'audience_titles'     => $audience_titles,
			'place_titles'        => $place_titles,
			'night_status_titles' => $night_status_titles,
			'grade_titles'        => $grade_titles,
			'visit_titles'        => $visit_titles,
			'visit_choices'       => $visit_choices,
			'pupil_counts'        => $pupil_counts,
			'visit_statuses'      => $visit_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'helpers'           => array(
			'page'           => $page(array($para('x'))),
			'night'          => $night(array($para('x')), 'py-5', lang('Section')),
			'page_header'    => $page_header(lang('Dome Shows'), lang('Tickets')),
			'day_header'     => $page_header(lang('Visit'), lang('Tickets'), array(), false),
			'section_header' => $section_header(lang('Dome Shows'), lang('Tickets'), lang('All Shows'), '{{page:shows}}'),
			'time'           => $time('20:30'),
			'status_pill'    => $status_pill('off'),
			'fact'           => $fact('bi-clock', $length_text(40)),
			'icon_tile'      => $icon_tile('bi-binoculars'),
			'step'           => $step(1, lang('Dome Shows'), lang('Tickets')),
			'check_list'     => $check_list($memberships['family']['benefits']),
			'faq'            => $faq(1, lang('Dome Shows'), lang('Tickets')),
			'rise'           => $rise($el('div', 'row', lang('Row'))),
			'fade_in'        => $fade_in($el('div', 'row', lang('Row'))),
			'drift'          => $drift($el('div', '', lang('Row'))),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_planetarium_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_planetarium_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_planetarium_page_keys()
{
	return array_merge(array(
		'home', 'tonight', 'visit', 'tickets', 'memberships', 'observatory', 'faq',
		'shows', 'show', 'show_new', 'nights', 'night', 'night_new',
		'visit_request', 'visit_view', 'my_visits', 'staff_visits', 'privacy', 'email_reply',
		'email_visit_new', 'email_visit_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_planetarium_shop_keys());
}

// The pages that exist only with the shop.
function _test_planetarium_shop_keys()
{
	return array('buy_tickets', 'product', 'cart', 'checkout', 'order', 'email_order');
}

// The value of a node's attribute, null without it.
function _test_planetarium_attr($node, $name)
{
	foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

function test_planetarium_data_hangs_together()
{
	$d = _test_planetarium_load();
	foreach (array('tagline', 'story', 'dome', 'address', 'hours', 'nights', 'darksky') as $f) pg_assert_true(trim($d['planetarium'][$f]) !== '', "planetarium: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['planetarium']['phone']), 'planetarium: a phone number');
	pg_assert_true(strpos($d['planetarium']['email'], '@example.com') !== false, 'planetarium: a sample address');
	pg_assert_same(4, count($d['opening_hours']), 'four lines of hours');
	foreach ($d['opening_hours'] as $n => $h) pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2} - [0-9]{2}:[0-9]{2}$/', $h['hours']), "hours $n: from and to");
	$today = date('Y-m-d');
	$used = array();
	$featured = 0;
	foreach ($d['shows'] as $key => $s) {
		pg_assert_true(isset($d['audiences'][$s['audience']]), "show $key: a known audience");
		foreach (array('title', 'language', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "show $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}(, [0-9]{2}:[0-9]{2})*$/', $s['times']), "show $key: showtimes as words");
		$times = explode(', ', $s['times']);
		$sorted = $times;
		sort($sorted);
		pg_assert_same($sorted, $times, "show $key: showtimes in order");
		pg_assert_same(count($times), count(array_unique($times)), "show $key: no showtime twice");
		pg_assert_true(is_int($s['length']) && $s['length'] >= 20 && $s['length'] <= 90, "show $key: a length in minutes");
		pg_assert_true(is_int($s['age']) && $s['age'] >= 0 && $s['age'] < 18, "show $key: a minimum age");
		pg_assert_same(4, count($s['details']), "show $key: four details");
		pg_assert_same(implode("\n", $s['details']), call_user_func($d['details_text'], $s['details']), "show $key: a detail line per item");
		pg_assert_true(in_array($d['show_words'][$key], $d['lists']['audience_titles'], true), "show $key: its audience is an answer of the list");
		$date = $d['show_dates'][$key];
		pg_assert_true($date >= $today, "show $key: from today on");
		pg_assert_same(date('Y-m-d', strtotime('+' . $s['day'] . ' days', strtotime($today))), $date, "show $key: counted from today");
		$used[$s['audience']] = true;
		if ($s['featured']) {
			$featured++;
			pg_assert_same(0, $s['day'], "show $key: a show on the home is today's");
		}
	}
	pg_assert_same(count($d['audiences']), count($used), 'a show for each audience');
	pg_assert_same(3, $featured, 'three shows today on the home');
	$places = array();
	foreach ($d['nights'] as $key => $n) {
		pg_assert_true(isset($d['night_places'][$n['place']]), "night $key: a known place");
		pg_assert_true(isset($d['night_statuses'][$n['status']]), "night $key: a known state");
		foreach (array('title', 'target', 'places', 'summary') as $f) pg_assert_true(trim($n[$f]) !== '', "night $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $n['time']), "night $key: a start time");
		pg_assert_true($d['night_dates'][$key] > $today, "night $key: still to come");
		list($place, $status) = $d['night_words'][$key];
		pg_assert_true(in_array($place, $d['lists']['place_titles'], true), "night $key: its place is an answer of the list");
		pg_assert_true(in_array($status, $d['lists']['night_status_titles'], true), "night $key: its state is an answer of the list");
		pg_assert_same('on', $n['status'], "night $key: a sample night is on");
		$places[$n['place']] = true;
	}
	pg_assert_same(count($d['night_places']), count($places), 'a night at each place');
	foreach ($d['night_statuses'] as $key => $s) {
		pg_assert_true((bool)preg_match('/^text-bg-(success|danger|warning|info|secondary)$/', $s['pill']), "state $key: a theme colour");
		pg_assert_true(strpos($s['icon'], 'bi-') === 0, "state $key: an icon beside its word");
	}
	pg_assert_same(3, count($d['telescopes']), 'three telescopes');
	foreach ($d['telescopes'] as $n => $t) {
		foreach (array('icon', 'title', 'kind', 'summary') as $f) pg_assert_true(trim($t[$f]) !== '', "telescope $n: $f");
	}
	// Every ticket and membership has a price in each currency the catalog
	// knows.
	foreach (array('tickets' => 'ticket_prices', 'memberships' => 'membership_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && is_int($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	pg_assert_same(array('dome_adult', 'dome_child', 'dome_family', 'night'), array_keys($d['tickets']), 'the dome show tickets and the night ticket');
	foreach ($d['tickets'] as $key => $t) pg_assert_true(trim($t['title']) !== '' && trim($t['who']) !== '', "ticket $key: title and who");
	foreach ($d['memberships'] as $key => $m) {
		pg_assert_true(trim($m['title']) !== '' && trim($m['who']) !== '', "membership $key: title and who");
		pg_assert_same(4, count($m['benefits']), "membership $key: four benefits");
	}
	foreach ($d['visits'] as $key => $v) {
		foreach (array('icon', 'title', 'who', 'length', 'summary') as $f) pg_assert_true(trim($v[$f]) !== '', "visit $key: $f");
	}
	pg_assert_same(4, count($d['people']), 'four astronomers');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(sprintf(lang('%d minutes'), 40), $d['length_text'], 'a length in minutes');
	pg_assert_same(array(lang('All ages'), sprintf(lang('Ages %d and over'), 12)), $d['age_texts'], 'all ages without a minimum');
}

// The pick lists: one answer per audience, place, state, grade and visit
// (plus the extra answer), no answer twice.
function test_planetarium_pick_lists_follow_the_data()
{
	$d = _test_planetarium_load();
	$l = $d['lists'];
	pg_assert_same(count($d['audiences']), count($l['audience_titles']), 'an answer per audience');
	pg_assert_same(count($d['night_places']), count($l['place_titles']), 'an answer per place');
	pg_assert_same(count($d['night_statuses']), count($l['night_status_titles']), 'an answer per night state');
	pg_assert_same(count($d['grades']), count($l['grade_titles']), 'an answer per grade');
	pg_assert_same(count($d['visits']) + 1, count($l['visit_choices']), 'visit: each by title and something else');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['visit_statuses']), 'four states of a visit request');
	// A link's query keeps the answer's own words.
	$params = array('visit' => $l['visit_titles'][1], 'grade' => $l['grade_titles'][2], 'audience' => $l['audience_titles'][3]);
	parse_str(call_user_func($d['query'], $params), $q);
	pg_assert_same($params, $q, 'the choices survive the query');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_planetarium_price_is_written_as_the_shop_writes_it()
{
	$d = _test_planetarium_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['ticket_prices']['dome_adult'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	$year = $d['membership_prices']['family'];
	$amount = isset($year[$base]) ? $year[$base] : $year['USD'];
	pg_assert_same(sprintf(lang('%s a year'), pg_money_text($amount, $symbol)), $d['membership_price'], 'the membership price a year');
}

// The page shell places the shared parts by key; night sections and the
// sky pages' header take the dark colour mode, the day header does not;
// the helpers link only template pages.
function test_planetarium_page_shell_and_helpers()
{
	$d = _test_planetarium_load();
	$h = $d['helpers'];
	$shared = array();
	foreach ($h['page']['children'] as $c) if ($c['type'] === 'shared_ref') $shared[] = $c['props']['templateShared'];
	pg_assert_same(array('account_bar', 'header', 'footer'), $shared, 'the shared parts around the page');
	pg_assert_same('dark', _test_planetarium_attr($h['night'], 'data-bs-theme'), 'a night section is dark');
	pg_assert_same('bg-body text-body py-5', $h['night']['props']['cssClass'], 'a night section takes the mode\'s own ground and text');
	pg_assert_same('dark', _test_planetarium_attr($h['page_header'], 'data-bs-theme'), 'a sky page header is dark');
	pg_assert_true(strpos($h['page_header']['props']['cssClass'], 'bg-body text-body') !== false, 'on the mode\'s own ground');
	pg_assert_same(null, _test_planetarium_attr($h['day_header'], 'data-bs-theme'), 'a day page header is light');
	$known = array_flip(_test_planetarium_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($h), $m);
	pg_assert_true(count($m[1]) > 0, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_same(count(_test_planetarium_page_keys()), count(array_unique(_test_planetarium_page_keys())), 'no page key twice');
	$nodes = array();
	_test_planetarium_nodes($h['faq'], $nodes);
	pg_assert_same('plm-faq-1-item', $nodes[0]['props']['id'], 'a question is its own search section');
	pg_assert_true(!empty($nodes[0]['props']['searchSection']), 'searchable');
	// The night state pill: an icon hidden from readers and the word.
	$nodes = array();
	_test_planetarium_nodes($h['status_pill'], $nodes);
	pg_assert_true(strpos($nodes[0]['props']['cssClass'], 'text-bg-danger') !== false, 'a called-off night in the danger colour');
	pg_assert_same('true', _test_planetarium_attr($nodes[1], 'aria-hidden'), 'the icon is decoration');
	pg_assert_same(lang('Called Off'), $nodes[2]['props']['text'], 'the word says the state');
	pg_assert_same('ppm-time', $h['time']['props']['cssClass'], 'a time in tabular figures');
	pg_assert_same('20:30', $h['time']['props']['text'], 'a time says itself');
	pg_assert_same(array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 90), $h['rise']['props']['_fx'], 'a rise on view');
	pg_assert_same(array('effect' => 'fade', 'trigger' => 'view', 'stagger' => 90), $h['fade_in']['props']['_fx'], 'a fade on view');
	pg_assert_same(array('effect' => 'parallax', 'speed' => 15), $h['drift']['props']['_fx'], 'a slow parallax');
	foreach (array('rise' => array('slide-up', 'view', 90), 'fade_in' => array('fade', 'view', 90)) as $name => $want) {
		$fx = pg_fx_normalize($h[$name]['props']['_fx']);
		pg_assert_same($want, array($fx['effect'], $fx['trigger'], $fx['stagger']), "$name survives the normalizer");
	}
	pg_assert_same(array('effect' => 'parallax', 'speed' => 15), pg_fx_normalize($h['drift']['props']['_fx']), 'the parallax survives the normalizer');
}

// Classes the template adds of its own start with ppm-; field and section
// ids with plm-. No eyebrow line and no faded text.
function test_planetarium_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_planetarium_load()['helpers'] as $t) _test_planetarium_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? '')) as $c) {
			if ($c === '') continue;
			pg_assert_false((bool)preg_match('/^(pzo|psk|prt|pmu|pmz|pth|pvt|pgl|pmn|plb|prx|pph|pre|pev)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "no eyebrow line ($c)");
			if (strpos($c, 'ppm-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'plm-') === 0, "id $id is the template's");
	}
	foreach (array('ppm-display', 'ppm-hero', 'ppm-time', 'ppm-rule') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The sample is never dated: a year or a month would read as an old
// season a year later. Show and night dates are counted at opening and
// are not part of the data.
function test_planetarium_no_fixed_dates()
{
	$d = _test_planetarium_load();
	$json = json_encode(array($d['planetarium'], $d['shows'], $d['nights'], $d['telescopes'], $d['tickets'], $d['memberships'],
		$d['visits'], $d['people'], $d['helpers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a dome "kubbe", a telescope "teleskop" ("teleskobu"), an
// astronomer "gökbilimci", a membership "üyelik", a ticket "bilet", the
// observatory "gözlemevi".
function test_planetarium_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		$says = function ($pattern, $word) use ($key, $tr) {
			if (preg_match($pattern, $key)) pg_assert_true(mb_stripos($tr, $word, 0, 'UTF-8') !== false, "'$key' says $word");
		};
		$says('/\bDome\b/', 'kubbe');
		$says('/\bTelescope\b/', 'telesk');
		$says('/\bAstronomer\b/', 'gökbilimci');
		$says('/\bMembership\b/', 'üyeli');
		$says('/\bTicket\b/', 'bilet');
		$says('/\bObservatory\b/', 'gözlemevi');
	}
}

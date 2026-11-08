<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Craft Brewery design template's parts
 * (includes/design_templates/pinegrap-brewery/): the brewery's data hangs
 * together (sample beers naming a known style and availability, sample
 * events naming a known kind, some to come and some held, tours, packs,
 * gift cards and club terms with the template's SKUs, drinkers' words
 * about a sample beer, the forms' pick lists without an answer twice), a
 * beer's strength line, a price is written as the shop writes it, the
 * event days count from today, every page link of the shared parts names
 * a page of the finished template and none names a shop page, the footer
 * opens the beers filtered, the cards read their form's fields, the tour
 * cards open the booking with the tour chosen, the classes are the
 * template's own, the source carries no fixed date and every literal has
 * its Turkish.
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
function _test_bw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/parts.php';
	$beer_cards = array();
	foreach (array_keys($beers) as $key) $beer_cards[$key] = $beer_card($key);
	$event_rows = array();
	foreach (array_keys($events) as $key) $event_rows[$key] = $event_row($key);
	$tour_cards = array();
	foreach (array_keys($tours) as $key) $tour_cards[$key] = $tour_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'brewery'        => $brewery,
		'styles'         => $styles,
		'availabilities' => $availabilities,
		'beers'          => $beers,
		'strength_text'  => $strength_text,
		'event_kinds'    => $event_kinds,
		'event_on'       => $event_on,
		'events'         => $events,
		'tours'          => $tours,
		'hours'          => $hours,
		'food'           => $food,
		'packs'          => $packs,
		'gift_cards'     => $gift_cards,
		'club_terms'     => $club_terms,
		'club_perks'     => $club_perks,
		'money'          => $money,
		'testimonials'   => $testimonials,
		'figures'        => $figures,
		'lists'          => array(
			'style_titles'        => $style_titles,
			'availability_titles' => $availability_titles,
			'event_kind_titles'   => $event_kind_titles,
			'tour_titles'         => $tour_titles,
			'tour_times'          => $tour_times,
			'group_sizes'         => $group_sizes,
			'booking_statuses'    => $booking_statuses,
		),
		'radios'         => array('contact_choices' => $contact_choices),
		'beer_cards'     => $beer_cards,
		'event_rows'     => $event_rows,
		'tour_cards'     => $tour_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'         => $header(),
		'footer'         => $footer(),
		'shared'         => array($account_bar_tree(), $notice(), $news_band(), $staff_bar(), $post_card(), $ai_summary(), $social_links()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_bw_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bw_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_bw_trees()
{
	$d = _test_bw_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['beer_cards']),
		array_values($d['event_rows']), array_values($d['tour_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_bw_page_keys()
{
	return array_merge(array(
		'home', 'taproom', 'about', 'faq',
		'beers', 'beer', 'beer_new', 'events', 'past_events', 'event', 'event_new',
		'tours', 'tour_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply', 'book_online',
		'email_booking_new', 'email_booking_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_bw_shop_keys());
}

// The pages that exist only with the shop.
function _test_bw_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order', 'club', 'club_home', 'club_releases');
}

function test_brewery_data_hangs_together()
{
	$d = _test_bw_load();
	foreach (array('tagline', 'story', 'address', 'phone', 'adults') as $f) pg_assert_true(trim($d['brewery'][$f]) !== '', "brewery: $f");
	foreach (array('email', 'tours') as $f) pg_assert_true(strpos($d['brewery'][$f], '@example.com') !== false, "brewery: a sample $f address");
	pg_assert_same(6, count($d['beers']), 'six sample beers');
	$styles = array();
	$availabilities = array();
	$featured = 0;
	foreach ($d['beers'] as $key => $b) {
		pg_assert_true(isset($d['styles'][$b['style']]), "beer $key: known style");
		pg_assert_true(isset($d['availabilities'][$b['availability']]), "beer $key: known availability");
		foreach (array('name', 'summary', 'notes', 'pairing', 'grain') as $f) pg_assert_true(trim($b[$f]) !== '', "beer $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{1,2}\.[0-9]%$/', $b['abv']), "beer $key: strength");
		pg_assert_true((bool)preg_match('/^[0-9]{1,3}$/', $b['ibu']), "beer $key: bitterness");
		if ($b['featured']) $featured++;
		$styles[$b['style']] = true;
		$availabilities[$b['availability']] = true;
	}
	pg_assert_same(count($d['styles']), count($styles), 'a beer in every style');
	pg_assert_same(count($d['availabilities']), count($availabilities), 'a beer for every availability');
	pg_assert_same(3, $featured, 'three beers on the home');
	$upcoming = 0;
	$past = 0;
	$kinds = array();
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_kinds'][$e['kind']]), "event $key: known kind");
		foreach (array('title', 'entry', 'text') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_true((bool)preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $e['starts']), "event $key: start time");
		pg_assert_true(is_int($e['weekday']) && $e['weekday'] >= 1 && $e['weekday'] <= 7, "event $key: ISO weekday");
		if ($e['weeks'] >= 0) $upcoming++; else $past++;
		if ($e['weeks'] < 0) pg_assert_false($e['featured'], "event $key: a past event is not on the home");
		$kinds[$e['kind']] = true;
	}
	pg_assert_same(5, $upcoming, 'five events to come');
	pg_assert_same(3, $past, 'three events held');
	pg_assert_same(count($d['event_kinds']), count($kinds), 'an event of every kind');
	foreach ($d['tours'] as $key => $t) {
		foreach (array('icon', 'title', 'length', 'text') as $f) pg_assert_true(trim($t[$f]) !== '', "tour $key: $f");
		pg_assert_true(isset($t['price']['USD']) && $t['price']['USD'] > 0, "tour $key: a price");
	}
	pg_assert_same(5, count($d['hours']), 'the opening hours cover the week');
	foreach ($d['food'] as $n => $f) pg_assert_true(isset($f['price']['USD']) && trim($f['title']) !== '', "food $n");
	$skus = array();
	foreach (array_merge($d['packs'], $d['gift_cards'], $d['club_terms']) as $key => $g) {
		pg_assert_true((bool)preg_match('/^BREWERY-[A-Z0-9-]+$/', $g['sku']), "product $key: the template's SKU");
		pg_assert_true(isset($g['price']['USD']) && $g['price']['USD'] > 0, "product $key: a price");
		foreach (array('title', 'text') as $f) pg_assert_true(trim($g[$f]) !== '', "product $key: $f");
		$skus[$g['sku']] = true;
	}
	pg_assert_same(count($d['packs']) + count($d['gift_cards']) + count($d['club_terms']), count($skus), 'no SKU twice');
	$days = array();
	foreach ($d['club_terms'] as $key => $t) {
		pg_assert_true(is_int($t['days']) && $t['days'] > 0, "club term $key: membership days");
		$days[] = $t['days'];
	}
	$sorted = $days;
	sort($sorted);
	pg_assert_same($sorted, $days, 'club terms from the shortest to the longest');
	pg_assert_same(4, count($d['club_perks']), 'four club perks');
	foreach ($d['testimonials'] as $n => $q) pg_assert_true(isset($d['beers'][$q['beer']]), "testimonial $n: about a sample beer");
	pg_assert_same(4, count($d['figures']), 'four figures');
	// The pick lists: one answer per style, availability, event kind and
	// tour; no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['styles']), count($l['style_titles']), 'an answer per style');
	pg_assert_same(count($d['availabilities']), count($l['availability_titles']), 'an answer per availability');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per event kind');
	pg_assert_same(count($d['tours']), count($l['tour_titles']), 'an answer per tour');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['booking_statuses']), 'four states of a tour booking');
}

// A beer's strength line names its strength and its bitterness.
function test_brewery_strength_text()
{
	$d = _test_bw_load();
	pg_assert_same(sprintf(lang('%1$s ABV · %2$s IBU'), '6.5%', '60'), call_user_func($d['strength_text'], 'lighthouse'));
}

// An event's day: the first day after today on its weekday, then its weeks
// on or back, so the coming events are always to come and the past ones
// behind them.
function test_brewery_event_days_count_from_today()
{
	$d = _test_bw_load();
	$today = (new DateTime('today'))->format('Y-m-d');
	foreach ($d['events'] as $key => $e) {
		$day = call_user_func($d['event_on'], $e['weekday'], $e['weeks']);
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $day), "event $key: a date");
		pg_assert_same($e['weekday'], (int)(new DateTime($day))->format('N'), "event $key: on its weekday");
		if ($e['weeks'] >= 0) pg_assert_true($day > $today, "event $key: still to come");
		else pg_assert_true($day < $today, "event $key: already held");
	}
	$next = call_user_func($d['event_on'], 4, 0);
	$later = call_user_func($d['event_on'], 4, 2);
	pg_assert_same(14, (int)(new DateTime($next))->diff(new DateTime($later))->days, 'two weeks on');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_brewery_price_is_written_as_the_shop_writes_it()
{
	$d = _test_bw_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['tours']['tasting']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
}

function test_brewery_page_links_name_template_pages()
{
	$known = array_flip(_test_bw_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_bw_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_brewery_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_bw_trees());
	foreach (_test_bw_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_bw_load();
	pg_assert_contains('{{page:tour_booking}}', json_encode($d['header']), 'the header opens the tour booking');
	pg_assert_contains($d['brewery']['adults'], json_encode($d['footer'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'the footer says the beers are for adults');
	$nodes = array();
	_test_bw_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the beers filtered on each style, in the words the
// Style list uses; each tour card opens the booking with its tour chosen.
function test_brewery_links_carry_their_choice()
{
	$d = _test_bw_load();
	$nodes = array();
	_test_bw_nodes($d['footer'], $nodes);
	$seen = array();
	foreach ($nodes as $node) {
		$href = (string)($node['props']['href'] ?? '');
		if (strpos($href, '{{page:beers}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:beers}}?')), $q);
		pg_assert_true(isset($q['style']) && in_array($q['style'], $d['lists']['style_titles'], true), 'the link names a style answer');
		$seen[$q['style']] = true;
	}
	pg_assert_same(count($d['styles']), count($seen), 'a link per style');
	foreach ($d['tour_cards'] as $key => $card) {
		$nodes = array();
		_test_bw_nodes($card, $nodes);
		$found = false;
		foreach ($nodes as $node) {
			$href = (string)($node['props']['href'] ?? '');
			if (strpos($href, '{{page:tour_booking}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:tour_booking}}?')), $q);
			pg_assert_same($d['tours'][$key]['title'], $q['tour'] ?? '', "tour $key: the booking opens with the tour chosen");
			$found = true;
		}
		pg_assert_true($found, "tour $key: a booking link");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_bw_card_reads($card)
{
	$nodes = array();
	_test_bw_nodes($card, $nodes);
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

// A beer card reads every value from the Beers form's fields, an event row
// from the Taproom Events form's, with the sample's own words on the canvas.
function test_brewery_cards_read_their_records()
{
	$d = _test_bw_load();
	foreach ($d['beer_cards'] as $key => $card) {
		list($tokens, $texts) = _test_bw_card_reads($card);
		foreach (array('picture', 'name', 'form_item_view', 'style', 'strength', 'summary', 'availability') as $t) {
			pg_assert_true(isset($tokens[$t]), "beer $key: card reads $t");
		}
		$b = $d['beers'][$key];
		pg_assert_true(in_array($b['name'], $texts, true), "beer $key: sample name on the canvas");
		pg_assert_true(in_array($d['styles'][$b['style']]['title'], $texts, true), "beer $key: sample style on the canvas");
		pg_assert_true(in_array($d['availabilities'][$b['availability']]['title'], $texts, true), "beer $key: sample availability on the canvas");
		pg_assert_true(in_array(call_user_func($d['strength_text'], $key), $texts, true), "beer $key: sample strength on the canvas");
	}
	foreach ($d['event_rows'] as $key => $row) {
		list($tokens, $texts) = _test_bw_card_reads($row);
		foreach (array('event_date', 'title', 'form_item_view', 'kind', 'starts', 'entry', 'ticket_url') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: row reads $t");
		}
		$e = $d['events'][$key];
		pg_assert_true(in_array($e['title'], $texts, true), "event $key: sample title on the canvas");
		pg_assert_true(in_array($d['event_kinds'][$e['kind']]['title'], $texts, true), "event $key: sample kind on the canvas");
	}
	// The ticket link opens another site's page in a new window, the window
	// given as the link's target, never in its attributes, and leaves when
	// the event has no ticket address.
	$nodes = array();
	_test_bw_nodes($d['event_rows']['quiz'], $nodes);
	$found = false;
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== 'ticket_url') continue;
		$found = true;
		pg_assert_same('_blank', $n['props']['target'] ?? '', 'the ticket link opens a new window');
		pg_assert_same('ticket_url', $n['props']['_bindings']['show_if'] ?? '', 'the ticket link leaves without an address');
	}
	pg_assert_true($found, 'a ticket link');
}

// Classes the template adds of its own start with pbw-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with bw-.
function test_brewery_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_bw_trees() as $t) _test_bw_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|pmz|pai)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pbw-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bw-') === 0, "id $id is the template's");
	}
	foreach (array('pbw-brand', 'pbw-display', 'pbw-eyebrow', 'pbw-lift', 'pbw-date', 'pbw-abv', 'pbw-price', 'pbw-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The source is never dated: a year or a month would read as a past
// season a year later; the events are counted from today and a seasonal
// beer names its season in words.
function test_brewery_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/parts.php');
	// The header block's copyright years are not a date of the sample.
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	// Nor are the prices of a price table ('USD' => 2000).
	$src = preg_replace("/'[A-Z]{3}' => [0-9]+/", '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a beer is "bira", the taproom "tadım salonu",
// a tour "tur".
function test_brewery_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^(Our |All |New )?Beers?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bira', 0, 'UTF-8') !== false, "'$key' says bira");
		if (preg_match('/\bTaproom\b/', $key)) pg_assert_true(mb_stripos($tr, 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
		if (preg_match('/\bTours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
	}
}

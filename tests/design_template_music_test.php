<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music Artist design template's parts
 * (includes/design_templates/pinegrap-music/): the artist's data hangs
 * together (sample releases naming a known format with their tracks, sample
 * shows naming a known country and ticket state, some to come and some
 * played, merch and gift cards with the template's SKUs, press quotes and
 * videos about a sample release, the forms' pick lists without an answer
 * twice), a release's length and track list are written from its tracks,
 * a price is written as the shop writes it, the show days count from today,
 * every page link of the shared parts names a page of the finished template
 * and none names a shop page, the footer opens the lists filtered, the
 * cards read their form's fields, the classes are the template's own, the
 * source carries no fixed date and every literal has its Turkish.
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
function _test_mu_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/parts.php';
	$release_cards = array();
	foreach (array_keys($releases) as $key) $release_cards[$key] = $release_card($key);
	$show_rows = array();
	foreach (array_keys($shows) as $key) $show_rows[$key] = $show_row($key);
	$member_cards = array();
	foreach (array_keys($members) as $key) $member_cards[] = $member_card($key);
	$press_cards = array();
	foreach (array_keys($press_quotes) as $n) $press_cards[] = $press_quote_card($n);
	$video_cards = array();
	foreach (array_keys($videos) as $n) $video_cards[] = $video_card($n);
	$out = array(
		'artist'        => $artist,
		'members'       => $members,
		'formats'       => $formats,
		'releases'      => $releases,
		'length_text'   => $length_text,
		'tracks_text'   => $tracks_text,
		'ticket_states' => $ticket_states,
		'countries'     => $countries,
		'show_on'       => $show_on,
		'shows'         => $shows,
		'where_text'    => $where_text,
		'merch'         => $merch,
		'gift_cards'    => $gift_cards,
		'money'         => $money,
		'press_quotes'  => $press_quotes,
		'videos'        => $videos,
		'listen_on'     => $listen_on,
		'figures'       => $figures,
		'lists'         => array(
			'format_titles'       => $format_titles,
			'release_titles'      => $release_titles,
			'country_titles'      => $country_titles,
			'ticket_state_titles' => $ticket_state_titles,
			'event_kinds'         => $event_kinds,
			'capacity_choices'    => $capacity_choices,
			'fee_choices'         => $fee_choices,
			'booking_statuses'    => $booking_statuses,
		),
		'radios'        => array('contact_choices' => $contact_choices),
		'release_cards' => $release_cards,
		'show_rows'     => $show_rows,
		'member_cards'  => $member_cards,
		'press_cards'   => $press_cards,
		'video_cards'   => $video_cards,
		'header'        => $header(),
		'footer'        => $footer(),
		'shared'        => array($account_bar_tree(), $notice(), $list_band(), $staff_bar(), $post_card(), $ai_summary(), $track_list(), $listen_links()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_mu_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mu_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_mu_trees()
{
	$d = _test_mu_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['release_cards']),
		array_values($d['show_rows']), $d['member_cards'], $d['press_cards'], $d['video_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_mu_page_keys()
{
	return array_merge(array(
		'home', 'about', 'videos', 'faq',
		'discography', 'release', 'release_new', 'tour', 'past_shows', 'show', 'show_new',
		'press', 'press_file_new', 'booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'email_booking_new', 'email_booking_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_mu_shop_keys());
}

// The pages that exist only with the shop.
function _test_mu_shop_keys()
{
	return array('merch', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_music_data_hangs_together()
{
	$d = _test_mu_load();
	foreach (array('tagline', 'story', 'based', 'label') as $f) pg_assert_true(trim($d['artist'][$f]) !== '', "artist: $f");
	foreach (array('booking', 'management', 'press') as $f) pg_assert_true(strpos($d['artist'][$f], '@example.com') !== false, "artist: a sample $f address");
	pg_assert_same(4, count($d['members']), 'four band members');
	foreach ($d['members'] as $key => $m) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($m[$f]) !== '', "member $key: $f");
	}
	pg_assert_same(5, count($d['releases']), 'five sample releases');
	$formats = array();
	$featured = 0;
	foreach ($d['releases'] as $key => $r) {
		pg_assert_true(isset($d['formats'][$r['format']]), "release $key: known format");
		foreach (array('title', 'note', 'summary', 'credits') as $f) pg_assert_true(trim($r[$f]) !== '', "release $key: $f");
		pg_assert_true(count($r['tracks']) >= 2, "release $key: tracks");
		foreach ($r['tracks'] as $n => $t) {
			pg_assert_true(trim($t[0]) !== '', "release $key: track " . ($n + 1) . ' title');
			pg_assert_true((bool)preg_match('/^[0-9]{1,2}:[0-5][0-9]$/', $t[1]), "release $key: track " . ($n + 1) . ' length');
		}
		if ($r['featured']) $featured++;
		$formats[$r['format']] = true;
	}
	pg_assert_same(count($d['formats']), count($formats), 'a release in every format');
	pg_assert_same(3, $featured, 'three releases on the home');
	$upcoming = 0;
	$past = 0;
	$countries = array();
	foreach ($d['shows'] as $key => $s) {
		pg_assert_true(isset($d['countries'][$s['country']]), "show $key: known country");
		pg_assert_true(isset($d['ticket_states'][$s['state']]), "show $key: known ticket state");
		foreach (array('city', 'venue') as $f) pg_assert_true(trim($s[$f]) !== '', "show $key: $f");
		pg_assert_true((bool)preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $s['doors']), "show $key: doors time");
		pg_assert_true(is_int($s['weekday']) && $s['weekday'] >= 1 && $s['weekday'] <= 7, "show $key: ISO weekday");
		if ($s['weeks'] >= 0) $upcoming++; else $past++;
		if ($s['weeks'] < 0) pg_assert_same('sold_out', $s['state'], "show $key: a past show reads sold out");
		$countries[$s['country']] = true;
	}
	pg_assert_same(6, $upcoming, 'six shows to come');
	pg_assert_same(3, $past, 'three shows played');
	pg_assert_same(count($d['countries']), count($countries), 'a show in every country');
	$skus = array();
	foreach (array_merge($d['merch'], $d['gift_cards']) as $key => $g) {
		pg_assert_true((bool)preg_match('/^MUSIC-[A-Z0-9-]+$/', $g['sku']), "product $key: the template's SKU");
		pg_assert_true(isset($g['price']['USD']) && $g['price']['USD'] > 0, "product $key: a price");
		foreach (array('title', 'text') as $f) pg_assert_true(trim($g[$f]) !== '', "product $key: $f");
		$skus[$g['sku']] = true;
	}
	pg_assert_same(count($d['merch']) + count($d['gift_cards']), count($skus), 'no SKU twice');
	foreach (array_merge($d['press_quotes'], $d['videos']) as $n => $q) {
		pg_assert_true(isset($d['releases'][$q['release']]), "quote or video $n: about a sample release");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_same(4, count($d['listen_on']), 'four listening services');
	// The pick lists: one answer per format, release, country and ticket
	// state; no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['formats']), count($l['format_titles']), 'an answer per format');
	pg_assert_same(count($d['releases']), count($l['release_titles']), 'an answer per release');
	pg_assert_same(count($d['countries']), count($l['country_titles']), 'an answer per country');
	pg_assert_same(count($d['ticket_states']), count($l['ticket_state_titles']), 'an answer per ticket state');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['booking_statuses']), 'four states of a booking enquiry');
	pg_assert_same(4, count($l['fee_choices']), 'four fee bands');
}

// A release's length counts its tracks and adds their minutes; the track
// list is one numbered line per track.
function test_music_release_length_and_tracks()
{
	$d = _test_mu_load();
	pg_assert_same(sprintf(lang('%1$d tracks, %2$d min'), 2, 7), call_user_func($d['length_text'], 'rooftop'), 'the single: 3:33 + 3:07');
	foreach ($d['releases'] as $key => $r) {
		$lines = explode("\n", call_user_func($d['tracks_text'], $key));
		pg_assert_same(count($r['tracks']), count($lines), "release $key: a line per track");
		foreach ($r['tracks'] as $n => $t) {
			pg_assert_same(sprintf(lang('%1$d. %2$s — %3$s'), $n + 1, $t[0], $t[1]), $lines[$n], "release $key: track " . ($n + 1));
		}
	}
}

// A show's day: the first day after today on its weekday, then its weeks
// on or back, so the tour is always to come and the past shows behind it.
function test_music_show_days_count_from_today()
{
	$d = _test_mu_load();
	$today = (new DateTime('today'))->format('Y-m-d');
	foreach ($d['shows'] as $key => $s) {
		$day = call_user_func($d['show_on'], $s['weekday'], $s['weeks']);
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $day), "show $key: a date");
		pg_assert_same($s['weekday'], (int)(new DateTime($day))->format('N'), "show $key: on its weekday");
		if ($s['weeks'] >= 0) pg_assert_true($day > $today, "show $key: still to come");
		else pg_assert_true($day < $today, "show $key: already played");
	}
	$next = call_user_func($d['show_on'], 5, 0);
	$later = call_user_func($d['show_on'], 5, 2);
	pg_assert_same(14, (int)(new DateTime($next))->diff(new DateTime($later))->days, 'two weeks on');
	pg_assert_same(sprintf(lang('%1$s, %2$s'), $d['shows']['istanbul']['venue'], $d['shows']['istanbul']['city']), call_user_func($d['where_text'], 'istanbul'));
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_music_price_is_written_as_the_shop_writes_it()
{
	$d = _test_mu_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['merch']['vinyl']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
}

function test_music_page_links_name_template_pages()
{
	$known = array_flip(_test_mu_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_mu_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_music_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_mu_trees());
	foreach (_test_mu_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_mu_load();
	pg_assert_contains('{{page:tour}}', json_encode($d['header']), 'the header opens the tour dates');
	$nodes = array();
	_test_mu_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the discography filtered on each format and the tour on
// each country, in the words the Format and Country lists use.
function test_music_footer_filters_the_lists()
{
	$d = _test_mu_load();
	$nodes = array();
	_test_mu_nodes($d['footer'], $nodes);
	$seen = array('format' => array(), 'country' => array());
	$lists = array('{{page:discography}}?' => array('format', 'format_titles'), '{{page:tour}}?' => array('country', 'country_titles'));
	foreach ($nodes as $node) {
		if (!isset($node['props']['href'])) continue;
		foreach ($lists as $prefix => $w) {
			if (strpos($node['props']['href'], $prefix) !== 0) continue;
			parse_str(substr($node['props']['href'], strlen($prefix)), $q);
			pg_assert_true(isset($q[$w[0]]) && in_array($q[$w[0]], $d['lists'][$w[1]], true), "the link names a {$w[0]} answer");
			$seen[$w[0]][$q[$w[0]]] = true;
		}
	}
	pg_assert_same(count($d['formats']), count($seen['format']), 'a link per format');
	pg_assert_same(count($d['countries']), count($seen['country']), 'a link per country');
}

// The values a card reads from its form's fields, and its texts.
function _test_mu_card_reads($card)
{
	$nodes = array();
	_test_mu_nodes($card, $nodes);
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

// A release card reads every value from the Releases form's fields, a show
// row from the Shows form's, with the sample's own words on the canvas.
function test_music_cards_read_their_records()
{
	$d = _test_mu_load();
	foreach ($d['release_cards'] as $key => $card) {
		list($tokens, $texts) = _test_mu_card_reads($card);
		foreach (array('cover', 'title', 'form_item_view', 'format', 'note', 'length') as $t) {
			pg_assert_true(isset($tokens[$t]), "release $key: card reads $t");
		}
		$r = $d['releases'][$key];
		pg_assert_true(in_array($r['title'], $texts, true), "release $key: sample title on the canvas");
		pg_assert_true(in_array($d['formats'][$r['format']]['title'], $texts, true), "release $key: sample format on the canvas");
		pg_assert_true(in_array(call_user_func($d['length_text'], $key), $texts, true), "release $key: sample length on the canvas");
	}
	foreach ($d['show_rows'] as $key => $row) {
		list($tokens, $texts) = _test_mu_card_reads($row);
		foreach (array('show_date', 'city', 'form_item_view', 'country', 'venue', 'doors', 'ticket_state', 'ticket_url') as $t) {
			pg_assert_true(isset($tokens[$t]), "show $key: row reads $t");
		}
		$s = $d['shows'][$key];
		pg_assert_true(in_array($s['city'], $texts, true), "show $key: sample city on the canvas");
		pg_assert_true(in_array($d['ticket_states'][$s['state']]['title'], $texts, true), "show $key: sample ticket state on the canvas");
	}
	// The ticket link opens the ticket seller's page in a new window, the
	// window given as the link's target, never in its attributes.
	$nodes = array();
	_test_mu_nodes($d['show_rows']['istanbul'], $nodes);
	$found = false;
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== 'ticket_url') continue;
		$found = true;
		pg_assert_same('_blank', $n['props']['target'] ?? '', 'the ticket link opens a new window');
	}
	pg_assert_true($found, 'a ticket link');
}

// Classes the template adds of its own start with pmu-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with mu-.
function test_music_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_mu_trees() as $t) _test_mu_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pmu-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mu-') === 0, "id $id is the template's");
	}
	foreach (array('pmu-brand', 'pmu-display', 'pmu-eyebrow', 'pmu-lift', 'pmu-date', 'pmu-quote', 'pmu-tracks') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The source is never dated: a year or a month would read as a past
// season a year later; the shows are counted from today and the releases
// are placed in the band's story in words.
function test_music_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/parts.php');
	// The header block's copyright years are not a date of the sample.
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	// Nor are the prices of a price table ('USD' => 2000).
	$src = preg_replace("/'[A-Z]{3}' => [0-9]+/", '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a show is "konser", the tour "turne", a track
// "parça".
function test_music_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bShows?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser', 0, 'UTF-8') !== false, "'$key' says konser");
		if (preg_match('/^Tour\b/', $key)) pg_assert_true(mb_stripos($tr, 'turne', 0, 'UTF-8') !== false, "'$key' says turne");
		if (preg_match('/\bTracks?\b/', $key)) pg_assert_true(mb_stripos($tr, 'parça', 0, 'UTF-8') !== false, "'$key' says parça");
	}
}

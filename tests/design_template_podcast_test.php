<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's parts
 * (includes/design_templates/pinegrap-podcast/): the network's data hangs
 * together (sample podcasts naming a known category and host, sample
 * episodes naming a known podcast with their chapters, no episode number
 * twice in a season, sponsorship packages and supporters' tiers with their
 * prices and the template's SKUs, the forms' pick lists without an answer
 * twice), an episode's number, length and chapters are written from its
 * data, a price is written as the shop writes it, the episode days count
 * back from today, every page link of the shared parts names a page of the
 * finished template and none names a shop page, the footer opens the
 * podcasts filtered, the cards read their form's fields, the player loads
 * nothing until pressed, the classes are the template's own, the source
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
function _test_ppd_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php';
	$podcast_cards = array();
	foreach (array_keys($podcasts) as $key) $podcast_cards[$key] = $podcast_card($key);
	$episode_rows = array();
	foreach (array_keys($episodes) as $key) $episode_rows[$key] = $episode_row($key);
	$host_cards = array();
	foreach (array_keys($hosts) as $key) $host_cards[] = $host_card($key);
	$package_cards = array();
	foreach (array_keys($packages) as $key) $package_cards[] = $package_card($key);
	$tier_cards = array();
	foreach (array_keys($tiers) as $key) $tier_cards[] = $tier_card($key, $link(lang('Support Us'), '{{page:support}}', 'btn btn-primary rounded-pill mt-auto'));
	$quote_cards = array();
	foreach (array_keys($sponsor_quotes) as $n) $quote_cards[] = $sponsor_quote_card($n);
	$figure_tiles = array();
	foreach (array_keys($audience) as $n) $figure_tiles[] = $figure_tile($n);
	$out = array(
		'network'          => $network,
		'hosts'            => $hosts,
		'categories'       => $categories,
		'podcasts'         => $podcasts,
		'episodes'         => $episodes,
		'aired_on'         => $aired_on,
		'number_text'      => $episode_number_text,
		'length_text'      => $length_text,
		'chapters_text'    => $chapters_text,
		'apps'             => $apps,
		'audience'         => $audience,
		'money'            => $money,
		'packages'         => $packages,
		'sponsor_quotes'   => $sponsor_quotes,
		'tiers'            => $tiers,
		'gift_memberships' => $gift_memberships,
		'membership_days'  => $membership_days,
		'bonus_episodes'   => $bonus_episodes,
		'lists'            => array(
			'category_titles'  => $category_titles,
			'podcast_titles'   => $podcast_titles,
			'package_titles'   => $package_titles,
			'sponsor_goals'    => $sponsor_goals,
			'budget_choices'   => $budget_choices,
			'enquiry_statuses' => $enquiry_statuses,
			'pitch_statuses'   => $pitch_statuses,
		),
		'radios'           => array('contact_choices' => $contact_choices),
		'podcast_cards'    => $podcast_cards,
		'episode_rows'     => $episode_rows,
		'host_cards'       => $host_cards,
		'package_cards'    => $package_cards,
		'tier_cards'       => $tier_cards,
		'quote_cards'      => $quote_cards,
		'figure_tiles'     => $figure_tiles,
		'player'           => $player(lang('Outtakes from the Kitchen Table')),
		'listen_buttons'   => $listen_buttons(),
		'header'           => $header(),
		'footer'           => $footer(),
		'shared'           => array($account_bar_tree(), $notice(), $subscribe_band(), $staff_bar(), $post_card(), $ai_summary(), $chapter_list(), $app_links()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ppd_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ppd_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_ppd_trees()
{
	$d = _test_ppd_load();
	return array_merge(array($d['header'], $d['footer'], $d['player'], $d['listen_buttons']), $d['shared'],
		array_values($d['podcast_cards']), array_values($d['episode_rows']), $d['host_cards'], $d['package_cards'],
		$d['tier_cards'], $d['quote_cards'], $d['figure_tiles']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ppd_page_keys()
{
	return array_merge(array(
		'home', 'about', 'hosts', 'listen', 'faq',
		'podcasts', 'podcast', 'podcast_new', 'episodes', 'episode', 'episode_new',
		'sponsor', 'sponsor_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'guest_pitch', 'pitch_view', 'staff_pitches', 'privacy', 'email_reply',
		'support', 'supporters_home', 'bonus_outtakes', 'bonus_askus', 'bonus_live',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_ppd_shop_keys());
}

// The pages that exist only with the shop.
function _test_ppd_shop_keys()
{
	return array('product', 'cart', 'checkout', 'order', 'email_order');
}

function test_podcast_data_hangs_together()
{
	$d = _test_ppd_load();
	foreach (array('tagline', 'story', 'based', 'feed') as $f) pg_assert_true(trim($d['network'][$f]) !== '', "network: $f");
	foreach (array('hello', 'sponsorship', 'press') as $f) pg_assert_true(strpos($d['network'][$f], '@example.com') !== false, "network: a sample $f address");
	pg_assert_same(4, count($d['hosts']), 'four hosts');
	foreach ($d['hosts'] as $key => $h) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($h[$f]) !== '', "host $key: $f");
	}
	$categories = array();
	$hosted = array();
	$featured = 0;
	foreach ($d['podcasts'] as $key => $p) {
		pg_assert_true(isset($d['categories'][$p['category']]), "podcast $key: known category");
		pg_assert_true(isset($d['hosts'][$p['host']]), "podcast $key: known host");
		foreach (array('title', 'schedule', 'summary') as $f) pg_assert_true(trim($p[$f]) !== '', "podcast $key: $f");
		$categories[$p['category']] = true;
		$hosted[$p['host']] = true;
		if ($p['featured']) $featured++;
	}
	pg_assert_same(count($d['categories']), count($categories), 'a podcast in every category');
	pg_assert_same(count($d['hosts']), count($hosted), 'every host hosts a podcast');
	pg_assert_same(3, $featured, 'three podcasts on the home');
	$numbers = array();
	$with_episodes = array();
	$featured = 0;
	foreach ($d['episodes'] as $key => $e) {
		pg_assert_true(isset($d['podcasts'][$e['podcast']]), "episode $key: known podcast");
		pg_assert_same($e['podcast'] . '-' . $e['number'], $key, "episode $key: the key is its podcast and number");
		foreach (array('title', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "episode $key: $f");
		pg_assert_true(is_int($e['season']) && $e['season'] >= 1, "episode $key: season");
		pg_assert_true(is_int($e['minutes']) && $e['minutes'] > 0, "episode $key: length");
		pg_assert_true(is_int($e['days']) && $e['days'] >= 0, "episode $key: already out");
		$id = $e['podcast'] . '/' . $e['season'] . '/' . $e['number'];
		pg_assert_false(isset($numbers[$id]), "episode $key: no number twice in a season");
		$numbers[$id] = true;
		$with_episodes[$e['podcast']] = true;
		if ($e['featured']) $featured++;
		pg_assert_true(count($e['chapters']) >= 3, "episode $key: chapters");
		$last = -1;
		foreach ($e['chapters'] as $n => $c) {
			pg_assert_true((bool)preg_match('/^[0-9]{1,2}:[0-5][0-9]$/', $c[0]), "episode $key: chapter " . ($n + 1) . ' start');
			list($m, $s) = explode(':', $c[0]);
			$at = 60 * (int)$m + (int)$s;
			pg_assert_true($at > $last && $at < 60 * $e['minutes'], "episode $key: chapter " . ($n + 1) . ' in order and inside the episode');
			$last = $at;
			pg_assert_true(trim($c[1]) !== '', "episode $key: chapter " . ($n + 1) . ' title');
		}
		pg_assert_same('0:00', $e['chapters'][0][0], "episode $key: the first chapter starts the episode");
	}
	pg_assert_same(count($d['podcasts']), count($with_episodes), 'every podcast has an episode');
	pg_assert_same(3, $featured, 'three episodes on the home');
	// A newer episode of a podcast has a higher number.
	foreach ($d['episodes'] as $a => $ea) {
		foreach ($d['episodes'] as $b => $eb) {
			if ($ea['podcast'] !== $eb['podcast'] || $ea['number'] <= $eb['number']) continue;
			pg_assert_true($ea['days'] < $eb['days'], "episode $a came out after $b");
			pg_assert_true($ea['season'] >= $eb['season'], "episode $a is not in an earlier season than $b");
		}
	}
	// Packages and tiers: a price, words and what they give; tiers and the
	// gift sold with the template's SKUs.
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "package $key: a price");
		foreach (array('title', 'text', 'per') as $f) pg_assert_true(trim($p[$f]) !== '', "package $key: $f");
		pg_assert_true(count($p['perks']) >= 3, "package $key: what it includes");
	}
	$skus = array();
	foreach (array_merge($d['tiers'], $d['gift_memberships']) as $key => $t) {
		pg_assert_true((bool)preg_match('/^PODCAST-[A-Z0-9-]+$/', $t['sku']), "product $key: the template's SKU");
		pg_assert_true(isset($t['price']['USD']) && $t['price']['USD'] > 0, "product $key: a price");
		foreach (array('title', 'text') as $f) pg_assert_true(trim($t[$f]) !== '', "product $key: $f");
		$skus[$t['sku']] = true;
	}
	pg_assert_same(count($d['tiers']) + count($d['gift_memberships']), count($skus), 'no SKU twice');
	$prev = 0;
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_true($t['price']['USD'] > $prev, "tier $key: dearer than the one before");
		$prev = $t['price']['USD'];
		pg_assert_true(count($t['perks']) >= 2, "tier $key: what it gives");
	}
	pg_assert_true(is_int($d['membership_days']) && $d['membership_days'] > 0, 'a membership runs some days');
	foreach ($d['sponsor_quotes'] as $n => $q) pg_assert_true(isset($d['podcasts'][$q['podcast']]), "sponsor quote $n: about a sample podcast");
	foreach ($d['bonus_episodes'] as $key => $b) {
		pg_assert_true(isset($d['podcasts'][$b['podcast']]), "bonus $key: from a sample podcast");
		pg_assert_true(in_array('bonus_' . $key, _test_ppd_page_keys(), true), "bonus $key: has its page");
	}
	pg_assert_same(4, count($d['audience']), 'four figures');
	pg_assert_same(4, count($d['apps']), 'four podcast apps');
	// The pick lists: one answer per category, podcast and package; no
	// answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['categories']), count($l['category_titles']), 'an answer per category');
	pg_assert_same(count($d['podcasts']), count($l['podcast_titles']), 'an answer per podcast');
	pg_assert_same(count($d['packages']), count($l['package_titles']), 'an answer per package');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of a sponsorship enquiry');
	pg_assert_same(4, count($l['pitch_statuses']), 'four states of a guest pitch');
	pg_assert_same(4, count($l['budget_choices']), 'four budget bands');
}

// An episode's place, length and chapters are written from its data.
function test_podcast_episode_texts()
{
	$d = _test_ppd_load();
	pg_assert_same(sprintf(lang('Season %1$d, Episode %2$d'), 2, 12), call_user_func($d['number_text'], 'kitchen-12'));
	pg_assert_same(sprintf(lang('%d min'), 58), call_user_func($d['length_text'], 'kitchen-12'));
	foreach ($d['episodes'] as $key => $e) {
		$lines = explode("\n", call_user_func($d['chapters_text'], $key));
		pg_assert_same(count($e['chapters']), count($lines), "episode $key: a line per chapter");
		foreach ($e['chapters'] as $n => $c) {
			pg_assert_same(sprintf(lang('%1$s — %2$s'), $c[0], $c[1]), $lines[$n], "episode $key: chapter " . ($n + 1));
		}
	}
}

// An episode's day: its days before today, so the newest is always recent.
function test_podcast_episode_days_count_back_from_today()
{
	$d = _test_ppd_load();
	$today = new DateTime('today');
	foreach ($d['episodes'] as $key => $e) {
		$day = call_user_func($d['aired_on'], $e['days']);
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $day), "episode $key: a date");
		pg_assert_same($e['days'], (int)$today->diff(new DateTime($day))->days, "episode $key: its days back");
		pg_assert_true($day <= $today->format('Y-m-d'), "episode $key: already out");
	}
	pg_assert_same($today->format('Y-m-d'), call_user_func($d['aired_on'], 0), 'zero days is today');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_podcast_price_is_written_as_the_shop_writes_it()
{
	$d = _test_ppd_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['tiers']['friend']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
}

function test_podcast_page_links_name_template_pages()
{
	$known = array_flip(_test_ppd_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_ppd_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_podcast_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_ppd_trees());
	foreach (_test_ppd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_ppd_load();
	pg_assert_contains('{{page:support}}', json_encode($d['header']), 'the header opens the support page');
	$nodes = array();
	_test_ppd_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the podcasts filtered on each category, in the words
// the Category list uses; a package card opens the enquiry with its
// package chosen.
function test_podcast_links_open_filtered()
{
	$d = _test_ppd_load();
	$nodes = array();
	_test_ppd_nodes($d['footer'], $nodes);
	$seen = array();
	foreach ($nodes as $node) {
		$href = (string)($node['props']['href'] ?? '');
		if (strpos($href, '{{page:podcasts}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:podcasts}}?')), $q);
		pg_assert_true(isset($q['category']) && in_array($q['category'], $d['lists']['category_titles'], true), 'the link names a category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($seen), 'a link per category');
	$seen = array();
	foreach ($d['package_cards'] as $card) {
		$nodes = array();
		_test_ppd_nodes($card, $nodes);
		foreach ($nodes as $node) {
			$href = (string)($node['props']['href'] ?? '');
			if (strpos($href, '{{page:sponsor_enquiry}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:sponsor_enquiry}}?')), $q);
			pg_assert_true(in_array($q['package'] ?? '', $d['lists']['package_titles'], true), 'the link names a package answer');
			$seen[$q['package']] = true;
		}
	}
	pg_assert_same(count($d['packages']), count($seen), 'a link per package');
}

// The values a card reads from its form's fields, and its texts.
function _test_ppd_card_reads($card)
{
	$nodes = array();
	_test_ppd_nodes($card, $nodes);
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

// A podcast card reads every value from the Podcasts form's fields, an
// episode row from the Episodes form's, with the sample's own words on the
// canvas.
function test_podcast_cards_read_their_records()
{
	$d = _test_ppd_load();
	foreach ($d['podcast_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ppd_card_reads($card);
		foreach (array('cover', 'title', 'form_item_view', 'category', 'hosted_by', 'schedule') as $t) {
			pg_assert_true(isset($tokens[$t]), "podcast $key: card reads $t");
		}
		$p = $d['podcasts'][$key];
		pg_assert_true(in_array($p['title'], $texts, true), "podcast $key: sample title on the canvas");
		pg_assert_true(in_array($d['categories'][$p['category']]['title'], $texts, true), "podcast $key: sample category on the canvas");
	}
	foreach ($d['episode_rows'] as $key => $row) {
		list($tokens, $texts) = _test_ppd_card_reads($row);
		foreach (array('cover', 'aired', 'podcast', 'episode_number', 'title', 'form_item_view', 'summary', 'length') as $t) {
			pg_assert_true(isset($tokens[$t]), "episode $key: row reads $t");
		}
		$e = $d['episodes'][$key];
		pg_assert_true(in_array($e['title'], $texts, true), "episode $key: sample title on the canvas");
		pg_assert_true(in_array(call_user_func($d['number_text'], $key), $texts, true), "episode $key: sample number on the canvas");
		pg_assert_true(in_array(call_user_func($d['length_text'], $key), $texts, true), "episode $key: sample length on the canvas");
	}
	// The listen buttons open the record's file and app address in a new
	// window, given as the link's target, and only when the record has one.
	$nodes = array();
	_test_ppd_nodes($d['listen_buttons'], $nodes);
	$found = array();
	foreach ($nodes as $n) {
		$token = $n['props']['_bindings']['href'] ?? '';
		if ($token === '') continue;
		$found[$token] = true;
		pg_assert_same('_blank', $n['props']['target'] ?? '', "$token opens a new window");
		pg_assert_same($token, $n['props']['_bindings']['show_if'] ?? '', "$token shows only when the record has it");
	}
	pg_assert_same(array('audio' => true, 'listen_url' => true), $found, 'the file and the app address');
}

// The page player is the browser's own, loading nothing until pressed,
// with words for a browser that cannot play it.
function test_podcast_player_loads_on_demand()
{
	$d = _test_ppd_load();
	$nodes = array();
	_test_ppd_nodes($d['player'], $nodes);
	$audio = null;
	foreach ($nodes as $n) if (($n['props']['tag'] ?? '') === 'audio') $audio = $n;
	pg_assert_true($audio !== null, 'an audio element');
	$attrs = array();
	foreach ($audio['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_true(isset($attrs['controls']), 'with the browser\'s controls');
	pg_assert_same('none', $attrs['preload'] ?? '', 'nothing loads before it is pressed');
	pg_assert_false(isset($attrs['autoplay']), 'never plays by itself');
	pg_assert_true(!empty($audio['children']), 'words for a browser that cannot play it');
}

// Classes the template adds of its own start with ppd-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with pd-.
function test_podcast_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_ppd_trees() as $t) _test_ppd_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			if (strpos($c, 'ppd-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
	foreach (array('ppd-brand', 'ppd-display', 'ppd-eyebrow', 'ppd-lift', 'ppd-date', 'ppd-quote', 'ppd-chapters', 'ppd-wave', 'ppd-thumb', 'ppd-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The source is never dated: a year or a month would read as an old
// season a year later; the episodes are counted back from today.
function test_podcast_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php');
	// The header block's copyright years are not a date of the sample.
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	// Nor are the prices of a price table ('USD' => 2000).
	$src = preg_replace("/'[A-Z]{3}' => [0-9]+/", '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: an episode is "bölüm", a host "sunucu", a
// season "sezon". The keys that read otherwise elsewhere (Show, Season,
// Show Notes) are never used.
function test_podcast_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		pg_assert_false(in_array($key, array('Show', 'Shows', 'All Shows', 'Season', 'Show Notes', 'Host', 'Welcome', 'From'), true), "'$key' reads otherwise in Turkish");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bEpisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "'$key' says bölüm");
		if (preg_match('/^Hosts?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sunucu', 0, 'UTF-8') !== false, "'$key' says sunucu");
		if (preg_match('/\bSeason\b/', $key)) pg_assert_true(mb_stripos($tr, 'sezon', 0, 'UTF-8') !== false, "'$key' says sezon");
	}
}

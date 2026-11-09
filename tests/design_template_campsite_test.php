<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Campsite design template's parts
 * (includes/design_templates/pinegrap-campsite/): the park's data hangs
 * together (kinds of stay with their points, sample pitches and cabins
 * naming a known kind and setting, a price for every kind and season,
 * guests speaking about a kind, the forms' pick lists without an answer
 * twice), every page link of the shared parts names a page of the
 * finished template, the shared parts carry no cart, the footer opens the
 * unit list filtered and links every kind's page, the unit card reads its
 * form's fields, the price table opens the stay request per season, the
 * classes are the template's own, the sample carries no fixed date and
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

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_cp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/parts.php';
	$kind_cards = array();
	$kind_fact_lists = array();
	foreach (array_keys($kinds) as $key) {
		$kind_cards[$key] = $kind_card($key);
		$kind_fact_lists[$key] = $kind_facts($key);
	}
	$unit_cards = array();
	$unit_fact_lists = array();
	$unit_words = array();
	foreach (array_keys($units) as $key) {
		$unit_cards[$key] = $unit_card($key);
		$unit_fact_lists[$key] = $unit_facts($key);
		$unit_words[$key] = array('kind' => $unit_kind($key), 'setting' => $unit_setting($key), 'hookup' => $unit_hookup($key));
	}
	$feature_cards = array();
	foreach (array_merge($facilities, $activities, $area, $rules, $why_us, $directions) as $item) $feature_cards[] = $feature_card($item);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'park'              => $park,
		'kinds'             => $kinds,
		'settings'          => $settings,
		'units'             => $units,
		'features_text'     => $features_text,
		'seasons'           => $seasons,
		'season_prices'     => $season_prices,
		'extras'            => $extras,
		'price_includes'    => $price_includes,
		'price_note'        => $price_note,
		'money'             => $money,
		'season_price'      => $season_price,
		'from_price'        => $from_price,
		'facilities'        => $facilities,
		'activities'        => $activities,
		'area'              => $area,
		'directions'        => $directions,
		'rules'             => $rules,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'unit_words'        => $unit_words,
		'lists'             => array(
			'kind_titles'          => $kind_titles,
			'setting_titles'       => $setting_titles,
			'unit_titles'          => $unit_titles,
			'season_titles'        => $season_titles,
			'request_kind_choices' => $request_kind_choices,
			'request_unit_choices' => $request_unit_choices,
			'hookup_choices'       => $hookup_choices,
			'outfit_choices'       => $outfit_choices,
			'pet_choices'          => $pet_choices,
			'request_statuses'     => $request_statuses,
			'group_kinds'          => $group_kinds,
			'group_sizes'          => $group_sizes,
			'enquiry_statuses'     => $enquiry_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'kind_cards'        => $kind_cards,
		'kind_facts'        => $kind_fact_lists,
		'unit_cards'        => $unit_cards,
		'unit_facts'        => $unit_fact_lists,
		'feature_cards'     => $feature_cards,
		'testimonial_cards' => $testimonial_cards,
		'season_table'      => $season_table(),
		'season_table_one'  => $season_table('cabin'),
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $stay_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_cp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cp_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_cp_trees()
{
	$d = _test_cp_load();
	return array_merge(array($d['header'], $d['footer'], $d['season_table'], $d['season_table_one']), $d['shared'],
		array_values($d['kind_cards']), array_values($d['kind_facts']), array_values($d['unit_cards']),
		array_values($d['unit_facts']), $d['feature_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_cp_page_keys()
{
	$keys = array(
		'home', 'stays', 'facilities', 'activities', 'prices', 'location', 'rules', 'about', 'faq',
		'units', 'unit', 'unit_new',
		'book', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_reply',
		'groups', 'group_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_request_new', 'email_request_received', 'email_enquiry_new', 'email_enquiry_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('tent', 'touring', 'glamping', 'cabin') as $key) $keys[] = 'stay_' . $key;
	return $keys;
}

function test_campsite_data_hangs_together()
{
	$d = _test_cp_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'opening', 'arrival') as $f) pg_assert_true(trim($d['park'][$f]) !== '', "park: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['park']['phone']), 'park: phone number');
	pg_assert_true(strpos($d['park']['email'], '@example.com') !== false, 'park: a sample address');
	pg_assert_true((bool)preg_match('/^-?[0-9]{1,2}\.[0-9]+, -?[0-9]{1,3}\.[0-9]+$/', $d['park']['coordinates']), 'park: coordinates');
	pg_assert_same(4, count($d['kinds']), 'four kinds of stay');
	foreach ($d['kinds'] as $key => $k) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "kind $key: a page key");
		foreach (array('icon', 'title', 'sleeps', 'summary', 'text') as $f) pg_assert_true(trim($k[$f]) !== '', "kind $key: $f");
		pg_assert_same(4, count($k['points']), "kind $key: four points");
	}
	pg_assert_same(8, count($d['units']), 'eight sample pitches and cabins');
	$featured = 0;
	$kinds_seen = array();
	foreach ($d['units'] as $key => $u) {
		pg_assert_true(isset($d['kinds'][$u['kind']]), "unit $key: known kind");
		pg_assert_true(isset($d['settings'][$u['setting']]), "unit $key: known setting");
		pg_assert_true(is_int($u['sleeps']) && $u['sleeps'] > 0 && is_int($u['size']) && $u['size'] > 0, "unit $key: sleeps and size are numbers");
		foreach (array('name', 'summary', 'description') as $f) pg_assert_true(trim($u[$f]) !== '', "unit $key: $f");
		pg_assert_same(implode("\n", $u['features']), call_user_func($d['features_text'], $key), "unit $key: a feature line per item");
		$w = $d['unit_words'][$key];
		pg_assert_true(in_array($w['kind'], $d['lists']['kind_titles'], true), "unit $key: its kind is an answer of the list");
		pg_assert_true(in_array($w['setting'], $d['lists']['setting_titles'], true), "unit $key: its setting is an answer of the list");
		pg_assert_true(in_array($w['hookup'], $d['lists']['hookup_choices'], true), "unit $key: its hook-up is an answer of the list");
		$kinds_seen[$u['kind']] = true;
		if ($u['featured']) $featured++;
	}
	pg_assert_same(count($d['kinds']), count($kinds_seen), 'a sample unit of every kind');
	pg_assert_same(4, $featured, 'four units on the home');
	// A price for every kind and season, in USD at least, rising with the
	// season.
	pg_assert_same(3, count($d['seasons']), 'three seasons');
	foreach ($d['kinds'] as $key => $k) {
		$last = 0;
		foreach (array_keys($d['seasons']) as $season) {
			pg_assert_true(isset($d['season_prices'][$key][$season]['USD']), "price $key/$season in USD");
			$usd = $d['season_prices'][$key][$season]['USD'];
			pg_assert_true($usd > $last, "price $key/$season above the season before");
			$last = $usd;
		}
	}
	foreach ($d['extras'] as $key => $x) {
		pg_assert_true(isset($x['price']['USD']) && trim($x['title']) !== '' && trim($x['unit']) !== '', "extra $key");
	}
	pg_assert_same(4, count($d['price_includes']), 'four things every price includes');
	pg_assert_true(trim($d['price_note']) !== '', 'the price note');
	pg_assert_same(8, count($d['facilities']), 'eight facilities');
	pg_assert_same(6, count($d['activities']), 'six activities');
	foreach ($d['activities'] as $n => $a) pg_assert_true(trim($a['when']) !== '', "activity $n: when");
	pg_assert_same(4, count($d['area']), 'four places nearby');
	foreach ($d['area'] as $n => $a) pg_assert_true((bool)preg_match('/^[0-9]+ km$/', $a['distance']), "area $n: a distance");
	pg_assert_same(3, count($d['directions']), 'three ways to get here');
	pg_assert_same(6, count($d['rules']), 'six park rules');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['kinds'][$t['kind']]), "testimonial $n: about a kind");
		pg_assert_contains($d['kinds'][$t['kind']]['title'], $t['role'], "testimonial $n: names the kind");
	}
	// The pick lists: one answer per kind, setting, unit and season (plus
	// "not sure", "any place"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['kinds']), count($l['kind_titles']), 'an answer per kind');
	pg_assert_same(count($d['settings']), count($l['setting_titles']), 'an answer per setting');
	pg_assert_same(count($d['units']), count($l['unit_titles']), 'an answer per unit');
	pg_assert_same(count($d['seasons']), count($l['season_titles']), 'an answer per season');
	pg_assert_same(count($d['kinds']) + 1, count($l['request_kind_choices']), 'request: an answer per kind and not sure');
	pg_assert_same(count($d['units']) + 1, count($l['request_unit_choices']), 'request: an answer per unit and any place');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['request_statuses']), 'five states of a stay request');
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of a group enquiry');
}

// A price is the shop's figure for the base currency, as words.
function test_campsite_prices_are_words()
{
	$d = _test_cp_load();
	$prices = $d['season_prices']['cabin']['high'];
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s a night'), pg_money_text($amount, $symbol)), call_user_func($d['season_price'], 'cabin', 'high'), 'a season price');
	$low = $d['season_prices']['tent']['low'];
	$amount = isset($low[$base]) ? $low[$base] : $low['USD'];
	pg_assert_same(sprintf(lang('From %s a night'), pg_money_text($amount, $symbol)), call_user_func($d['from_price'], 'tent'), 'the lowest price');
}

function test_campsite_page_links_name_template_pages()
{
	$known = array_flip(_test_cp_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_cp_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The park sells nothing online: no shared part places the cart or links a
// shop page, and the menu marks the current page itself.
function test_campsite_shared_parts_have_no_shop()
{
	$json = json_encode(_test_cp_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_cp_load();
	pg_assert_contains('{{page:book}}', json_encode($d['header']), 'the header opens the stay request');
	pg_assert_contains('{{page:prices}}', json_encode($d['shared'][2]), 'the band shows the prices');
	$nodes = array();
	_test_cp_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that start with $prefix, as their query strings.
function _test_cp_queries($tree, $prefix)
{
	$nodes = array();
	_test_cp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the unit list filtered on each kind, in the words the
// Kind list uses, and links every kind's own page; the price table opens
// the stay request with each season chosen. No link uses ?view=, which
// the lists keep for themselves.
function test_campsite_links_filter_lists_and_forms()
{
	$d = _test_cp_load();
	$seen = array();
	foreach (_test_cp_queries($d['footer'], '{{page:units}}?') as $q) {
		pg_assert_true(isset($q['kind']) && in_array($q['kind'], $d['lists']['kind_titles'], true), 'the link names a kind answer');
		$seen[$q['kind']] = true;
	}
	pg_assert_same(count($d['kinds']), count($seen), 'a link per kind');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['kinds']) as $key) pg_assert_contains('{{page:stay_' . $key . '}}', $json, "the footer links stay_$key");
	$seen = array();
	foreach (_test_cp_queries($d['season_table'], '{{page:book}}?') as $q) {
		pg_assert_true(isset($q['season']) && in_array($q['season'], $d['lists']['season_titles'], true), 'the link names a season answer');
		$seen[$q['season']] = true;
	}
	pg_assert_same(count($d['seasons']), count($seen), 'a link per season');
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_cp_trees(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The price table shows a row per kind (one for the kind given) and a
// price per season.
function test_campsite_season_table()
{
	$d = _test_cp_load();
	foreach (array(array($d['season_table'], 4), array($d['season_table_one'], 1)) as $case) {
		$nodes = array();
		_test_cp_nodes($case[0], $nodes);
		$rows = 0;
		$cells = 0;
		foreach ($nodes as $n) {
			if (($n['props']['tag'] ?? '') === 'tr') $rows++;
			if (($n['props']['tag'] ?? '') === 'td') $cells++;
		}
		pg_assert_same($case[1] + 1, $rows, 'a head row and a row per kind');
		pg_assert_same($case[1] * count($d['seasons']), $cells, 'a price per kind and season');
	}
	pg_assert_contains(call_user_func($d['season_price'], 'cabin', 'low'), json_encode($d['season_table_one'], JSON_UNESCAPED_UNICODE), 'the cabin price');
}

// The values a card reads from its form's fields, and its texts.
function _test_cp_card_reads($card)
{
	$nodes = array();
	_test_cp_nodes($card, $nodes);
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

// A unit card reads every value from the Pitches and Cabins form's fields,
// with the sample's own words on the canvas; the unit facts read every
// fact field. A kind's card opens the kind's page.
function test_campsite_cards_read_their_records()
{
	$d = _test_cp_load();
	foreach ($d['unit_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cp_card_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'kind', 'setting', 'summary', 'price') as $t) {
			pg_assert_true(isset($tokens[$t]), "unit $key: card reads $t");
		}
		$u = $d['units'][$key];
		pg_assert_true(in_array($u['name'], $texts, true), "unit $key: sample name on the canvas");
		pg_assert_true(in_array($d['unit_words'][$key]['kind'], $texts, true), "unit $key: sample kind on the canvas");
		list($tokens, $texts) = _test_cp_card_reads($d['unit_facts'][$key]);
		foreach (array('kind', 'setting', 'sleeps', 'hookup') as $t) pg_assert_true(isset($tokens[$t]), "unit $key: facts read $t");
		pg_assert_true(in_array((string)$u['sleeps'], $texts, true), "unit $key: sample sleeps on the canvas");
	}
	foreach ($d['kind_cards'] as $key => $card) {
		pg_assert_contains('{{page:stay_' . $key . '}}', json_encode($card), "kind $key: the card opens its page");
	}
}

// Classes the template adds of its own start with pcp-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with cp-.
function test_campsite_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_cp_trees() as $t) _test_cp_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pcp-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cp-') === 0, "id $id is the template's");
	}
	foreach (array('pcp-brand', 'pcp-display', 'pcp-eyebrow', 'pcp-lift', 'pcp-stat', 'pcp-quote', 'pcp-price') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// season a year later; a season says "early summer", never its dates.
function test_campsite_no_fixed_dates()
{
	$d = _test_cp_load();
	$json = json_encode(array($d['kinds'], $d['units'], $d['seasons'], $d['park'], $d['activities'], $d['rules'], _test_cp_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a stay request is "konaklama talebi", a
// group enquiry "grup talebi", a season "sezon", a pitch "yer" and a
// cabin "kulübe".
function test_campsite_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bStay Requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'konaklama tale', 0, 'UTF-8') !== false, "'$key' says konaklama talebi");
		if (preg_match('/\bGroup Enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'grup tale', 0, 'UTF-8') !== false, "'$key' says grup talebi");
		if (preg_match('/\bSeason\b/', $key)) pg_assert_true(mb_stripos($tr, 'sezon', 0, 'UTF-8') !== false, "'$key' says sezon");
		if (preg_match('/\bPitch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'yer', 0, 'UTF-8') !== false, "'$key' says yer");
		if (preg_match('/\bCabins?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kulübe', 0, 'UTF-8') !== false, "'$key' says kulübe");
	}
}

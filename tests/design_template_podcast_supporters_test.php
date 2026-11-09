<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's supporters and shop
 * (includes/design_templates/pinegrap-podcast/supporters.php): the pages
 * and widgets with their keys, folders and shop requirement, the catalog
 * (a group per tier with its membership product, the gift without
 * membership days, prices from parts.php), the support page showing every
 * tier with its buy box and the contact page beside it, the supporters'
 * area with a page and a player for every bonus episode, the receipt,
 * every page link naming a page of the finished template and only the
 * shop's pages naming a shop page, the template's own ids and classes, no
 * fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages and the node
// walk.
require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php, mail.php and supporters.php in one scope, as the template
// file reads them. Read once per run.
function _test_ppdu_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/supporters.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'catalog' => $part['catalog'],
		'tiers' => $tiers, 'gift_memberships' => $gift_memberships, 'membership_days' => $membership_days,
		'bonus_episodes' => $bonus_episodes, 'podcasts' => $podcasts, 'money' => $money);
	return $out;
}

// The page keys a tree links ({{page:…}}) and names in settings ({{tab:…}}).
function _test_ppdu_links($tree)
{
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($tree), $m);
	return array_values(array_unique($m[1]));
}

// The shared_ref nodes of a tree: the widgets it places.
function _test_ppdu_widgets_placed($tree)
{
	$nodes = array();
	_test_ppd_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) $out[] = $n['props']['templateWidget'];
	}
	return $out;
}

function test_podcast_supporters_pages_and_widgets()
{
	$d = _test_ppdu_load();
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($d['part']), 'the part returns pages, widgets and the catalog');
	$folders = array('support' => 'public', 'supporters_home' => 'supporters', 'bonus_outtakes' => 'supporters', 'bonus_askus' => 'supporters',
		'bonus_live' => 'supporters', 'product' => 'public', 'cart' => 'public', 'checkout' => 'public', 'order' => 'public', 'email_order' => 'private');
	pg_assert_same(array_keys($folders), array_keys($d['pages']));
	pg_assert_same(array('buy_listener', 'buy_friend', 'buy_patron', 'buy_gift', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'),
		array_keys($d['widgets']));
	$known = array_flip(_test_ppd_page_keys());
	$shop = _test_ppd_shop_keys();
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
		pg_assert_same(in_array($key, $shop, true), ($p['requires'] ?? '') === 'ecommerce', "$key needs the shop only if it is a shop page");
		if ($p['folder'] === 'supporters') {
			pg_assert_false($p['search'], "$key: a supporters' page stays out of the search");
			pg_assert_false($p['sitemap'], "$key: a supporters' page stays out of the sitemap");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'], "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of the part");
	}
}

// A group per tier holds its one product, which renews the membership; the
// gift has no membership days (it would renew the buyer's own).
function test_podcast_supporters_catalog()
{
	$d = _test_ppdu_load();
	$groups = $d['catalog']['groups'];
	pg_assert_same('root', $groups['supporters']['parent'], 'the Supporters group sits under the top group');
	$skus = array();
	foreach ($d['tiers'] as $key => $t) {
		$g = $groups['support_' . $key];
		pg_assert_same('supporters', $g['parent'], "$key: under Supporters");
		pg_assert_same(1, count($g['products']), "$key: one product, so its buy box lists it alone");
		$p = $g['products'][0];
		pg_assert_same($t['sku'], $p['sku'], "$key: the tier's SKU");
		pg_assert_same($t['price'], $p['price'], "$key: the tier's prices");
		pg_assert_same($d['membership_days'], $p['membership_days'], "$key: renews the membership");
		pg_assert_false($p['shippable'], "$key: not shipped");
		$skus[] = $p['sku'];
	}
	$gift = $groups['support_gift']['products'][0];
	pg_assert_same($d['gift_memberships']['gift']['sku'], $gift['sku'], 'the gift\'s SKU');
	pg_assert_same($d['gift_memberships']['gift']['price'], $gift['price'], 'the gift\'s prices');
	pg_assert_false(isset($gift['membership_days']), 'the gift does not renew the buyer\'s membership');
	pg_assert_false($gift['shippable'], 'the gift is not shipped');
	$skus[] = $gift['sku'];
	pg_assert_same(count($skus), count(array_unique($skus)), 'every SKU is its own');
	foreach ($skus as $sku) pg_assert_true(strpos($sku, 'PODCAST-') === 0, "$sku names the template");
	foreach ($d['widgets'] as $key => $w) {
		if (strpos($key, 'buy_') !== 0) continue;
		$group = substr($key, 4);
		pg_assert_same('{{product_group:support_' . $group . '}}', $w['config']['product_group_id'], "$key lists its group");
		pg_assert_same(1, $w['config']['max_results'], "$key lists one product");
		pg_assert_same('{{tab:cart}}', $w['config']['add_to_cart_next_page_id'], "$key opens the cart");
	}
	pg_assert_same('{{tab:support}}', $d['widgets']['product_detail']['config']['catalog_listing_page_id'], 'the product page leads back to the support page');
	pg_assert_same('{{tab:email_order}}', $d['widgets']['checkout']['config']['order_receipt_email_page_id'], 'the checkout sends the receipt');
}

// Every tier is on the support page with its price, its buy box and the
// contact page beside it; the gift has its own buy box.
function test_podcast_supporters_support_page()
{
	$d = _test_ppdu_load();
	$tree = $d['pages']['support']['tree'];
	$json = json_encode($tree);
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_contains(json_encode($t['title']), $json, "$key is on the support page");
		pg_assert_contains(json_encode($d['money']($t['price'])), $json, "$key: its price as the shop writes it");
	}
	pg_assert_contains(json_encode($d['money']($d['gift_memberships']['gift']['price'])), $json, 'the gift\'s price');
	pg_assert_same(array('buy_listener', 'buy_friend', 'buy_patron', 'buy_gift'), _test_ppdu_widgets_placed($tree), 'a buy box per tier and the gift');
	pg_assert_true(in_array('contact', _test_ppdu_links($tree), true), 'without the shop the support page links the contact page');
	foreach (_test_ppd_shop_keys() as $key) {
		pg_assert_false(in_array($key, _test_ppdu_links($tree), true), "the support page never links $key");
	}
}

// The supporters' home links every bonus episode; each has its own page
// with an audio player that loads nothing until it is pressed.
function test_podcast_supporters_bonus_episodes()
{
	$d = _test_ppdu_load();
	$home = _test_ppdu_links($d['pages']['supporters_home']['tree']);
	foreach ($d['bonus_episodes'] as $key => $b) {
		pg_assert_true(in_array('bonus_' . $key, $home, true), "the supporters' home links $key");
		$p = $d['pages']['bonus_' . $key];
		pg_assert_same($b['title'], $p['title'], "$key: its title");
		$json = json_encode($p['tree']);
		pg_assert_contains(json_encode($d['podcasts'][$b['podcast']]['title']), $json, "$key: names its podcast");
		$nodes = array();
		_test_ppd_nodes($p['tree'], $nodes);
		$audio = array();
		foreach ($nodes as $n) {
			if (($n['props']['tag'] ?? '') === 'audio') $audio[] = $n;
		}
		pg_assert_same(1, count($audio), "$key: one player");
		$attrs = array();
		foreach ($audio[0]['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
		pg_assert_true(isset($attrs['controls']), "$key: the player has its controls");
		pg_assert_same('none', $attrs['preload'], "$key: loads nothing until pressed");
		pg_assert_true(in_array('supporters_home', _test_ppdu_links($p['tree']), true), "$key leads back to the other bonus episodes");
	}
}

// The receipt reads the order's tokens and leads into the supporters'
// area; its colours are the e-mails' sunset palette.
function test_podcast_supporters_receipt()
{
	$d = _test_ppdu_load();
	$tree = $d['widgets']['email_order']['tree'];
	$json = json_encode($tree);
	foreach (array('__order_no', '__order_date', '__item_short_description', '__item_qty', '__item_total', '__order_subtotal', '__order_total', '__billing_address_html') as $token) {
		pg_assert_contains('"' . $token . '"', $json, "the receipt reads $token");
	}
	pg_assert_true(in_array('supporters_home', _test_ppdu_links($tree), true), 'the receipt leads into the supporters\' area');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/supporters.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#c2410c', '#fed7aa', '#fff7ed', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
}

function test_podcast_supporters_links_ids_and_classes()
{
	$d = _test_ppdu_load();
	$known = array_flip(_test_ppd_page_keys());
	$shop = _test_ppd_shop_keys();
	foreach ($d['pages'] as $key => $p) {
		foreach (_test_ppdu_links($p['tree']) as $link) {
			pg_assert_true(isset($known[$link]), "$key: {{page:$link}} names a template page");
			if (in_array($link, $shop, true)) pg_assert_true(in_array($key, $shop, true), "$key links the shop page $link only as a shop page");
		}
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ppd_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) {
		if (is_array($w['tree'])) _test_ppd_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
}

function test_podcast_supporters_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/supporters.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal has its Turkish; a supporter reads "destekçi", an episode
// "bölüm", and a page's address has no Turkish letters.
function test_podcast_supporters_turkish_words()
{
	$map = pg_test_language_map('tr');
	$d = _test_ppdu_load();
	$names = array();
	foreach ($d['pages'] as $p) $names[$p['name']] = true;
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/supporters.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		pg_assert_false(in_array($key, array('Show', 'Shows', 'Season', 'Show Notes', 'Guest', 'Guests', 'Host', 'Listening'), true), "'$key' reads otherwise in Turkish");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) {
			// A page name is an address; a widget's slug may be a shared word.
			if (isset($names[$key])) pg_assert_true((bool)preg_match('/^[a-z-]+$/', $tr), "the address '$tr' has no Turkish letters");
			continue;
		}
		if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "'$key' says bölüm");
		if (preg_match('/\b[Ss]upporters?\b/', $key)) pg_assert_true(mb_stripos($tr, 'destek', 0, 'UTF-8') !== false, "'$key' says destekçi");
	}
}

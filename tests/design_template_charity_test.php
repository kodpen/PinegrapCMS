<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's parts
 * (includes/design_templates/pinegrap-charity/): the charity's data hangs
 * together (every campaign has a known cause, a goal with whole amounts in
 * every currency and a percent between 0 and 100, SKUs are unique, the
 * spending adds up to 100), an amount is written the way the shop writes
 * it, what a campaign has raised follows its percent, the shared parts
 * load, every page link names a page of the finished template and the
 * shared parts never send a visitor to a page that needs the shop, the
 * header leads to the donate page, a progress bar carries its figure and
 * a record's token, the classes are the template's own, no date is
 * written into the sample, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The parts as the template file reads them: parts.php first, the page
// files after it in the same scope. Read once per run.
function _test_charity_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	$cards = array();
	foreach (array_keys($campaigns) as $key) $cards[] = $campaign_card($key);
	foreach (array_keys($causes) as $key) $cards[] = $cause_tile($key);
	foreach (array_keys($gift_amounts) as $n) $cards[] = $gift_card($n);
	$out = array(
		'causes'       => $causes,
		'campaigns'    => $campaigns,
		'sku'          => $campaign_sku,
		'general_sku'  => $general_sku,
		'gifts'        => $gift_amounts,
		'impact'       => $impact,
		'spending'     => $spending,
		'promises'     => $donor_promises,
		'money'        => $money,
		'raised'       => $raised,
		'progress'     => $progress,
		'campaigns_in' => $campaigns_in,
		'header'       => $header(),
		'footer'       => $footer(),
		'shared'       => array($account_bar_tree(), $header(), $footer(), $donate_band(), $notice(), $staff_bar()),
		'parts'        => array_merge($cards, array(
			$impact_row(), $promise_row(), $spending_bars(),
			$page(array()), $staff_page(array()), $page_header(lang('Campaigns'), lang('Text')),
			$section_header(lang('Campaigns'), lang('Campaigns'), lang('See our campaigns'), '{{page:campaigns}}'),
			$faq(1, lang('Question'), lang('Answer')), $post_card(), $ai_summary(),
			$comment_thread(array('title' => 'T', 'empty' => 'E', 'heading' => 'H', 'label' => 'L', 'sign_in' => 'S', 'submit' => 'B',
				'watch_box' => 'W', 'follow' => 'F', 'unfollow' => 'U', 'close' => 'C', 'reopen' => 'R', 'reply' => true, 'watch' => true)),
		)),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_charity_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_charity_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_charity_page_keys()
{
	return array(
		'home', 'about', 'our_work', 'impact', 'transparency', 'faq',
		'donate', 'pledge_view', 'product', 'cart', 'checkout', 'order', 'email_order',
		'campaigns', 'campaign', 'campaign_new',
		'volunteer', 'volunteer_view', 'my_volunteering', 'staff_volunteers', 'privacy', 'email_reply',
		'email_volunteer_new', 'email_volunteer_received', 'email_pledge_new', 'email_pledge_received',
		'events', 'event_view', 'event_new', 'report_new',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

// The pages that need the shop: a link to one of them from a part every
// page shows would lead nowhere on a site without it.
function _test_charity_shop_keys()
{
	return array('product', 'cart', 'checkout', 'order', 'email_order');
}

function test_charity_campaigns_and_gifts_hang_together()
{
	$d = _test_charity_load();
	pg_assert_same(5, count($d['causes']), 'five causes');
	pg_assert_same(6, count($d['campaigns']), 'six campaigns');
	$featured = 0;
	$skus = array($d['general_sku']);
	$used = array();
	$titles = array();
	foreach ($d['campaigns'] as $key => $c) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "$key: a key the SKU can carry");
		pg_assert_true(isset($d['causes'][$c['cause']]), "$key: known cause");
		foreach (array('title', 'place', 'summary', 'story') as $f) pg_assert_true($c[$f] !== '', "$key: $f written");
		pg_assert_true(is_int($c['percent']) && $c['percent'] > 0 && $c['percent'] < 100, "$key: a percent still to raise");
		foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) {
			$v = $c['goal'][$cur];
			pg_assert_true(is_int($v) && $v > 0, "$key $cur: a whole positive goal");
		}
		if ($c['featured']) $featured++;
		$used[$c['cause']] = true;
		$titles[] = $c['title'];
		$sku = call_user_func($d['sku'], $key);
		pg_assert_true((bool)preg_match('/^CH-[A-Z]+$/', $sku), "SKU $sku");
		$skus[] = $sku;
	}
	pg_assert_same(3, $featured, 'three campaigns on the home page first');
	pg_assert_same(count($d['causes']), count($used), 'every cause has a campaign');
	pg_assert_same(count($skus), count(array_unique($skus)), 'SKUs are unique, the general fund\'s too');
	pg_assert_same(count($titles), count(array_unique($titles)), 'campaign titles are unique');

	pg_assert_same(4, count($d['gifts']), 'four gift amounts');
	$last = 0;
	foreach ($d['gifts'] as $n => $g) {
		pg_assert_true($g['text'] !== '', "gift $n: what it pays for");
		foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) {
			pg_assert_true(is_int($g['price'][$cur]) && $g['price'][$cur] > 0, "gift $n $cur: a whole positive amount");
		}
		pg_assert_true($g['price']['USD'] > $last, "gift $n: smallest first");
		$last = $g['price']['USD'];
	}
	$sum = 0;
	foreach ($d['spending'] as $s) $sum += $s['percent'];
	pg_assert_same(100, $sum, 'the spending adds up to 100');
	pg_assert_same(4, count($d['impact']), 'four impact figures');
	pg_assert_same(4, count($d['promises']), 'four promises');
}

// The cards and the shop show one amount: the base currency's figure, the
// USD figure for a currency the table does not list; what is raised is the
// goal times the percent.
function test_charity_amounts_are_written_as_the_shop_writes_them()
{
	$d = _test_charity_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['gifts'][0]['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(pg_money_text($prices['USD'], $symbol), call_user_func($d['money'], array('USD' => $prices['USD'])), 'USD for a currency not listed');
	pg_assert_same(array('USD' => 43200, 'TRY' => 1872000), call_user_func($d['raised'], array('USD' => 60000, 'TRY' => 2600000), 72));
	pg_assert_same(array('USD' => 1), call_user_func($d['raised'], array('USD' => 3), 33), 'rounded to whole units');
}

function test_charity_page_links_name_template_pages()
{
	$d = _test_charity_load();
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['shared'], $d['parts'])), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

function test_charity_shared_parts_and_cards_stand_without_the_shop()
{
	$d = _test_charity_load();
	$json = json_encode(array($d['shared'], $d['parts']));
	foreach (_test_charity_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts and cards do not link to $key");
	}
	$nodes = array();
	_test_charity_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
	pg_assert_contains('{{page:donate}}', json_encode($d['header']), 'the header leads to the donate page');
	pg_assert_contains('{{page:volunteer}}', json_encode($d['header']), 'the header invites volunteers');
}

// A campaign's cause link opens the list filtered to its title, the value
// the Campaigns form stores.
function test_charity_campaigns_link_filters_by_cause()
{
	$d = _test_charity_load();
	$url = call_user_func($d['campaigns_in'], 'water');
	pg_assert_same('{{page:campaigns}}?cause=' . rawurlencode($d['causes']['water']['title']), $url);
}

// A progress bar is a <progress> out of 100 whose value is the figure, or
// the record's token on a campaign page ('value' is an attribute a record
// may fill); its text says the same where the bar is not drawn.
function test_charity_progress_bar_carries_its_figure()
{
	$d = _test_charity_load();
	foreach (array(72, '{{percent}}') as $value) {
		$bar = call_user_func($d['progress'], $value, 'Wells');
		pg_assert_same('progress', $bar['props']['tag']);
		$attrs = array();
		foreach ($bar['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
		pg_assert_same((string)$value, $attrs['value']);
		pg_assert_same('100', $attrs['max']);
		pg_assert_same('Wells', $attrs['aria-label']);
		pg_assert_same($value . '%', $bar['props']['text']);
		pg_assert_true(pg_sw_attr_name_ok('value'), 'a record may fill value');
	}
}

// Classes the template adds of its own start with pch-; another template's
// prefix copied in would stay unstyled (its rules are not in this one).
function test_charity_classes_are_its_own()
{
	$d = _test_charity_load();
	$nodes = array();
	foreach (array_merge($d['shared'], $d['parts']) as $t) _test_charity_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pch-') === 0) $own[$c] = true;
		}
	}
	foreach (array('pch-brand', 'pch-eyebrow', 'pch-figure', 'pch-lift', 'pch-band', 'pch-progress') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The campaigns are open now, never dated: a year or a month in the sample
// would read as stale a year later.
function test_charity_no_fixed_dates()
{
	$d = _test_charity_load();
	$json = json_encode(array($d['campaigns'], $d['causes'], $d['gifts'], $d['impact'], $d['spending'], $d['promises'], $d['shared'], $d['parts']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way (a gift is "bağış", a campaign "kampanya").
function test_charity_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bcampaigns?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'kampanya', 0, 'UTF-8') !== false, "'$key' says kampanya");
		}
		if (preg_match('/\b(donate|gifts?)\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'bağış', 0, 'UTF-8') !== false, "'$key' says bağış");
		}
	}
}

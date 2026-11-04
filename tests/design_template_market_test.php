<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplace design template's parts
 * (includes/design_templates/pinegrap-market/): the market's data hangs
 * together (every maker has a known category and city, three products with
 * whole prices in every currency, SKUs are unique), a price is written the
 * way the shop writes it, the shared parts load, every page link names a
 * page of the finished template and the shared parts never send a visitor
 * to a page that needs the shop, the header's search goes to the site
 * search, the classes are the template's own, no date is written into
 * the sample, and every literal has its Turkish.
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
function _test_market_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	$cards = array();
	foreach (array_keys($sellers) as $key) {
		$cards[] = $product_card($key, 0);
	}
	foreach (array_keys($categories) as $key) $cards[] = $category_tile($key);
	$out = array(
		'categories' => $categories,
		'cities'     => $cities,
		'sellers'    => $sellers,
		'products'   => $market_products,
		'sku'        => $product_sku,
		'promises'   => $market_promises,
		'steps'      => $market_steps,
		'money'      => $money,
		'makers_in'  => $makers_in,
		'header'     => $header(),
		'footer'     => $footer(),
		'shared'     => array($account_bar_tree(), $header(), $footer(), $maker_band(), $notice(), $staff_bar()),
		'parts'      => array_merge($cards, array(
			$promise_row(),
			$page(array()), $staff_page(array()), $page_header(lang('Makers'), lang('Text')),
			$section_header(lang('Makers'), lang('Makers'), lang('All Makers'), '{{page:sellers}}'),
			$faq(1, lang('Question'), lang('Answer')), $post_card(), $ai_summary(),
			$comment_thread(array('title' => 'T', 'empty' => 'E', 'heading' => 'H', 'label' => 'L', 'sign_in' => 'S', 'submit' => 'B',
				'watch_box' => 'W', 'follow' => 'F', 'unfollow' => 'U', 'close' => 'C', 'reopen' => 'R', 'reply' => true, 'watch' => true)),
		)),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_market_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_market_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_market_page_keys()
{
	return array(
		'home', 'categories', 'how_it_works', 'sell', 'about', 'faq', 'gift_guide',
		'shop', 'product', 'cart', 'checkout', 'order', 'email_order',
		'sellers', 'seller', 'seller_new',
		'apply', 'application_view', 'my_applications', 'staff_applications',
		'help', 'help_view', 'my_help', 'staff_help', 'privacy', 'email_reply',
		'email_application_new', 'email_application_received', 'email_help_new', 'email_help_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

// The pages that need the shop: a link to one of them from a part every
// page shows would lead nowhere on a site without it.
function _test_market_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_market_sellers_and_products_hang_together()
{
	$d = _test_market_load();
	pg_assert_same(5, count($d['categories']), 'five categories');
	pg_assert_same(6, count($d['sellers']), 'six makers');
	$featured = 0;
	$skus = array();
	$used_categories = array();
	$names = array();
	foreach ($d['sellers'] as $key => $s) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "$key: a key the SKU can carry");
		pg_assert_true(isset($d['categories'][$s['category']]), "$key: known category");
		pg_assert_true(isset($d['cities'][$s['city']]), "$key: known city");
		foreach (array('name', 'maker', 'tagline', 'story') as $f) pg_assert_true($s[$f] !== '', "$key: $f written");
		if ($s['featured']) $featured++;
		$used_categories[$s['category']] = true;
		$names[] = $s['name'];
		pg_assert_true(isset($d['products'][$key]), "$key: products");
		pg_assert_same(3, count($d['products'][$key]), "$key: three products");
		foreach (array_values($d['products'][$key]) as $n => $p) {
			pg_assert_true($p['title'] !== '' && $p['text'] !== '', "$key product $n: title and text");
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) {
				$v = $p['price'][$cur];
				pg_assert_true(is_int($v) && $v > 0, "$key product $n $cur: a whole positive price");
			}
			$sku = call_user_func($d['sku'], $key, $n + 1);
			pg_assert_true((bool)preg_match('/^MK-[A-Z]+-[1-3]$/', $sku), "SKU $sku");
			$skus[] = $sku;
		}
		pg_assert_true(!empty($d['products'][$key][0]['featured']), "$key: its first product is featured");
	}
	pg_assert_same(3, $featured, 'three makers on the home page first');
	pg_assert_same(count($d['categories']), count($used_categories), 'every category has a maker');
	pg_assert_same(count($skus), count(array_unique($skus)), 'SKUs are unique');
	pg_assert_same(count($names), count(array_unique($names)), 'shop names are unique');
	pg_assert_same(array(), array_diff_key($d['products'], $d['sellers']), 'no products without a maker');
	pg_assert_same(4, count($d['promises']), 'four promises');
	pg_assert_same(4, count($d['steps']), 'four steps');
}

// The cards and the shop show one amount: the base currency's figure, the
// USD figure for a currency the table does not list.
function test_market_price_is_written_as_the_shop_writes_it()
{
	$d = _test_market_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['products']['clay'][0]['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(pg_money_text($prices['USD'], $symbol), call_user_func($d['money'], array('USD' => $prices['USD'])), 'USD for a currency not listed');
}

function test_market_page_links_name_template_pages()
{
	$d = _test_market_load();
	$known = array_flip(_test_market_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['shared'], $d['parts'])), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

function test_market_shared_parts_and_cards_stand_without_the_shop()
{
	$d = _test_market_load();
	$json = json_encode(array($d['shared'], $d['parts']));
	foreach (_test_market_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts and cards do not link to $key");
	}
	$nodes = array();
	_test_market_nodes($d['header'], $nodes);
	$menu = null;
	$search = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		if (($n['props']['tag'] ?? '') === 'form') $search = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
	pg_assert_true($search !== null, 'the header has a search');
	$attrs = array();
	foreach ($search['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_same('{{page:search}}', $attrs['action'], 'the search goes to the site search');
	pg_assert_same('get', $attrs['method']);
	pg_assert_contains('"name","value":"query"', json_encode($search), 'the box sends ?query=, what the site search reads');
	pg_assert_contains('{{page:sell}}', json_encode($d['header']), 'the header invites makers');
}

// A category's maker link opens the list filtered to its title, the value
// the Sellers form stores.
function test_market_makers_link_filters_by_category()
{
	$d = _test_market_load();
	$url = call_user_func($d['makers_in'], 'home');
	pg_assert_same('{{page:sellers}}?category=' . rawurlencode($d['categories']['home']['title']), $url);
}

// Classes the template adds of its own start with pmk-; another template's
// prefix copied in would stay unstyled (its rules are not in this one).
function test_market_classes_are_its_own()
{
	$d = _test_market_load();
	$nodes = array();
	foreach (array_merge($d['shared'], $d['parts']) as $t) _test_market_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pmk-') === 0) $own[$c] = true;
		}
	}
	foreach (array('pmk-brand', 'pmk-eyebrow', 'pmk-price', 'pmk-lift', 'pmk-band', 'pmk-search') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The makers are "new to the market", never dated: a year or a month in the
// sample would read as stale a year later.
function test_market_no_fixed_dates()
{
	$d = _test_market_load();
	$json = json_encode(array($d['sellers'], $d['products'], $d['categories'], $d['promises'], $d['steps'], $d['shared'], $d['parts']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way (a maker is "üretici", a shop name keeps its
// own name).
function test_market_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bmakers?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'üretici', 0, 'UTF-8') !== false, "'$key' says üretici");
		}
	}
	foreach (array('Inkwell Press', 'North Loom') as $name) pg_assert_same($name, $map[$name] ?? '', "$name keeps its name");
}

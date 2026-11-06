<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's giving
 * (includes/design_templates/pinegrap-charity/donate.php, with forms.php
 * and mail.php): the catalog holds a gift to where it is needed most and
 * one per campaign, each a donation that is neither taxed nor shipped, with
 * the suggested amount the donate page names; only the shop's pages and
 * widgets need the shop, the donate page and the pledge stand without it;
 * the pledge form stores its kind, campaign and status (the staff's), asks
 * for consent and opens the pledge's page; every page link names a page of
 * the finished template; the classes are the template's own and every
 * literal has its Turkish.
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
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_charity_test.php');

// The giving part as the template file reads it: parts.php, mail.php and
// forms.php first, then donate.php in the same scope. Read once per run.
function _test_charity_donate_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$part = include $dir . '/donate.php';
	$out = array('part' => $part, 'campaigns' => $campaigns, 'gifts' => $gift_amounts, 'sku' => $campaign_sku, 'general_sku' => $general_sku);
	return $out;
}

function _test_charity_donate_pages()
{
	$d = _test_charity_donate_load();
	$pages = array();
	foreach ($d['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_charity_donate_catalog_holds_the_gifts()
{
	$d = _test_charity_donate_load();
	$groups = $d['part']['catalog']['groups'];
	pg_assert_same(array('donate'), array_keys($groups), 'one Donate group');
	pg_assert_same('root', $groups['donate']['parent']);
	$skus = array();
	foreach ($groups['donate']['products'] as $p) {
		$skus[] = $p['sku'];
		pg_assert_true($p['donation'] === true, $p['sku'] . ': a donation');
		pg_assert_same($d['gifts'][1]['price'], $p['price'], $p['sku'] . ': the suggested amount the donate page names');
		pg_assert_same(array('selection_type' => 'donation', 'taxable' => '0', 'shippable' => '0'), _pg_tpl_product_kind($p), $p['sku'] . ': typed amount, not taxed, not shipped');
		pg_assert_true($p['title'] !== '' && $p['description'] !== '', $p['sku'] . ': title and description');
	}
	$expected = array($d['general_sku']);
	foreach (array_keys($d['campaigns']) as $key) $expected[] = call_user_func($d['sku'], $key);
	pg_assert_same($expected, $skus, 'the general gift first, then one per campaign');
}

function test_charity_donate_only_the_shop_needs_the_shop()
{
	$d = _test_charity_donate_load();
	$pages = _test_charity_donate_pages();
	pg_assert_same(array('donate', 'pledge_view', 'product', 'cart', 'checkout', 'order', 'email_order', 'email_pledge_new', 'email_pledge_received'), array_keys($pages));
	foreach ($pages as $key => $p) {
		$needs = in_array($key, _test_charity_shop_keys(), true);
		pg_assert_same($needs, isset($p['requires']) && $p['requires'] === 'ecommerce', "$key: needs the shop only if it is a shop page");
	}
	foreach ($d['part']['widgets'] as $key => $w) {
		$shop = in_array($key, array('donate_catalog', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'), true);
		pg_assert_same($shop, isset($w['requires']) && $w['requires'] === 'ecommerce', "widget $key: needs the shop only if it is the shop's");
	}
	// The donate page links to no page of the shop: its gift list is the
	// widget that leaves without it.
	$json = json_encode(array($pages['donate'], $pages['pledge_view']));
	foreach (_test_charity_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "donate and pledge do not link to $key");
	foreach (array('donate_catalog', 'pledge_form') as $w) pg_assert_contains('"templateWidget":"' . $w . '"', json_encode($pages['donate']), "donate places $w");
}

function test_charity_donate_pledge_form()
{
	$d = _test_charity_donate_load();
	$pages = _test_charity_donate_pages();
	$form = $pages['donate']['form'];
	pg_assert_same('{{tab:pledge_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_pledge_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_pledge_received}}', $form['confirm_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	$nodes = array();
	_test_charity_nodes($d['part']['widgets']['pledge_form']['tree'], $nodes);
	$names = array();
	$status = null;
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) if ($a['name'] === 'name') $names[$a['value']] = $n;
		if (isset($n['props']['id'])) pg_assert_true(strpos($n['props']['id'], 'ch-pl-') === 0, 'field id ' . $n['props']['id'] . ' is the pledge\'s');
	}
	foreach (array('pledge_kind', 'campaign', 'amount', 'message', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $f) {
		pg_assert_true(isset($names[$f]), "the form has $f");
	}
	pg_assert_true(!empty($names['status']['props']['_cf']['office_use_only']), 'the status is the staff\'s');
	pg_assert_same('campaign', $names['campaign']['props']['_cf']['url_param'], 'a campaign page can choose the campaign');
	pg_assert_contains(json_encode($d['campaigns']['wells']['title']), json_encode($names['campaign']), 'the campaigns are the choices');
	pg_assert_same('submitter_only', $d['part']['widgets']['pledge_detail']['config']['access_control'], 'a pledge is its sender\'s');
	pg_assert_same('{{tab:email_reply}}', $pages['pledge_view']['comments']['email_page']);
}

function test_charity_donate_links_classes_and_words()
{
	$d = _test_charity_donate_load();
	$json = json_encode($d['part']);
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_charity_nodes($p['tree'], $nodes);
	foreach ($d['part']['widgets'] as $w) if (is_array($w['tree'])) _test_charity_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
		}
	}
	pg_assert_false(strpos($json, '#c2410c') !== false || strpos($json, '#fed7aa') !== false, 'the e-mails use the template\'s own colours');
	$map = pg_test_language_map('tr');
	foreach (array('donate.php', 'forms.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
		}
	}
}

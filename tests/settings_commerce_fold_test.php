<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce as folded rows: the eleven rows' order, names
 * and icons, each part's line of state and pill (every pill Off and no row
 * asking for attention while the store is off), the pane drawn as one list
 * of rows under Enable Commerce, Gift cards and Rewards as two cards, and the
 * names that stay (card ids, field names, the save, the anchors).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');

function settings_commerce_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of commerce.php from one card's id to the start of the next card.
function settings_commerce_fold_test_card($id)
{
	$source = settings_commerce_fold_test_source('includes/settings/commerce.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, 'class="pg-set-card">', $start + 30);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_settings_commerce_fold_order_and_names()
{
	pg_assert_same(
		array('store', 'payments', 'shipping', 'address-autocomplete', 'stock-locations', 'giftcards', 'rewards', 'reference-price', 'affiliate', 'referral', 'feeds'),
		pg_commerce_fold_order(),
		'a shop\'s set-up first, its programmes after'
	);

	$names = array();

	foreach (pg_commerce_fold_order() as $key) {
		$face = pg_commerce_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_true(strpos($face['tone'], 'pg-tone-') === 0, $key . ': a tone');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(
		array(lang('Store'), lang('Payment methods'), lang('Shipping'), lang('Address autocomplete'), lang('Stock locations'), lang('Gift cards'), lang('Rewards'), lang('Reference price'), lang('Affiliate programme'), lang('Referral programme'), lang('Product feeds')),
		$names,
		'the rows\' names in sentence case'
	);
}

function test_settings_commerce_fold_store_off_quiets_every_row()
{
	$rows = array(
		pg_commerce_fold_store(false, 10234, true),
		pg_commerce_fold_payments(false, array(), ''),
		pg_commerce_fold_payments(false, array('Stripe'), 'Stripe'),
		pg_commerce_fold_shipping(false, true, false, array('UPS')),
		pg_commerce_fold_address(false, 'Mapbox'),
		pg_commerce_fold_stock(false, true, 3, 1),
		pg_commerce_fold_giftcards(false, true, 365),
		pg_commerce_fold_rewards(false, true, 100),
		pg_commerce_fold_reference(false, 'lowest_30d'),
		pg_commerce_fold_affiliate(false, true, '10'),
		pg_commerce_fold_referral(false, true, '25', 'TRY'),
		pg_commerce_fold_feeds(false, true, 0, 0, 0, 1000),
	);

	foreach ($rows as $index => $row) {
		pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $row['pill'], 'row ' . $index . ': the pill is Off while the store is off');
		pg_assert_false($row['attention'], 'row ' . $index . ': no row asks for attention');
		pg_assert_true(trim($row['line']) !== '', 'row ' . $index . ': the line says its own state still');
	}

	pg_assert_same(lang('The store is off: no feed is built'), pg_commerce_fold_feeds(false, true, 1, 1000, 412, 2000)['line'], 'the feeds say no feed is built');
	pg_assert_same(lang('No payment method is on'), pg_commerce_fold_payments(false, array(), '')['line'], 'payments say their own state');
}

function test_settings_commerce_fold_store_line()
{
	$row = pg_commerce_fold_store(true, 10234, true);
	pg_assert_same(lang(array('string' => 'Next order number {var:1}', 'vars' => array(10234))) . ' · ' . lang('tax-exempt orders allowed'), $row['line'], 'the next number and tax-exempt');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $row['pill'], 'pill On');
	pg_assert_same(lang(array('string' => 'Next order number {var:1}', 'vars' => array(7))), pg_commerce_fold_store(true, 7, false)['line'], 'no tax-exempt part');
}

function test_settings_commerce_fold_payments_line()
{
	$on = pg_commerce_fold_payments(true, array('Stripe', 'PayPal Express Checkout', lang('Offline payments')), '');
	pg_assert_same('Stripe, PayPal Express Checkout, ' . lang('Offline payments'), $on['line'], 'the methods that are on by name');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $on['pill'], 'On');
	pg_assert_false($on['attention'], 'nothing to do');

	$none = pg_commerce_fold_payments(true, array(), '');
	pg_assert_same(lang('No payment method is on'), $none['line'], 'none on');
	pg_assert_same(array(lang('None'), 'pg-tone-amber'), $none['pill'], 'amber None');
	pg_assert_true($none['attention'], 'asks for attention');

	$missing = pg_commerce_fold_payments(true, array('iyzico'), 'iyzico');
	pg_assert_same(lang(array('string' => 'Connect {var:1} in Connections', 'vars' => array('iyzico'))), $missing['line'], 'the gateway to connect');
	pg_assert_same('pg-tone-amber', $missing['pill'][1], 'an amber pill');
	pg_assert_true($missing['attention'], 'asks for attention');
}

function test_settings_commerce_fold_shipping_and_address_lines()
{
	pg_assert_same(lang('One recipient an order'), pg_commerce_fold_shipping(true, true, false, array())['line'], 'single recipient');
	pg_assert_same(lang('Several recipients an order') . ' · UPS, Geliver', pg_commerce_fold_shipping(true, true, true, array('UPS', 'Geliver'))['line'], 'the carriers connected by name');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_commerce_fold_shipping(true, false, false, array())['pill'], 'shipping off');
	pg_assert_same(lang('Off'), pg_commerce_fold_shipping(true, false, false, array())['line'], 'shipping off says Off');

	pg_assert_same('Mapbox', pg_commerce_fold_address(true, 'Mapbox')['line'], 'the provider');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), pg_commerce_fold_address(true, 'Mapbox')['pill'], 'On');
	pg_assert_same(lang('Off'), pg_commerce_fold_address(true, '')['line'], 'no provider');
}

function test_settings_commerce_fold_stock_line()
{
	pg_assert_same(lang(array('string' => '{var:1} locations, {var:2} off', 'vars' => array(3, 1))), pg_commerce_fold_stock(true, true, 3, 1)['line'], 'how many, how many off');
	pg_assert_same(lang(array('string' => '{var:1} locations', 'vars' => array(3))), pg_commerce_fold_stock(true, true, 3, 0)['line'], 'none off');
	pg_assert_same(lang('1 location'), pg_commerce_fold_stock(true, true, 1, 0)['line'], 'one');
	pg_assert_same(lang('Not in use'), pg_commerce_fold_stock(true, true, 0, 0)['line'], 'no location');
	pg_assert_same(lang('Not in use'), pg_commerce_fold_stock(true, false, 0, 0)['line'], 'before the upgrade');
	pg_assert_same(null, pg_commerce_fold_stock(true, true, 3, 1)['pill'], 'no pill while the store is on');
}

function test_settings_commerce_fold_programme_lines()
{
	pg_assert_same(lang(array('string' => 'Accepted, valid {var:1} days', 'vars' => array(365))), pg_commerce_fold_giftcards(true, true, 365)['line'], 'valid days');
	pg_assert_same(lang('Accepted, never expire'), pg_commerce_fold_giftcards(true, true, '')['line'], 'blank never expires');
	pg_assert_same(lang('Off'), pg_commerce_fold_giftcards(true, false, 365)['line'], 'off');

	pg_assert_same(lang(array('string' => 'On: {var:1} points to the goal', 'vars' => array(100))), pg_commerce_fold_rewards(true, true, 100)['line'], 'the goal');
	pg_assert_same(lang('Off'), pg_commerce_fold_rewards(true, false, 100)['line'], 'off');

	pg_assert_same(lang('Lowest price of the last 30 days'), pg_commerce_fold_reference(true, 'lowest_30d')['line'], 'the rule in its own words');
	pg_assert_same(lang('Lowest price of the last 10 days'), pg_commerce_fold_reference(true, 'lowest_10d')['line'], 'ten days');
	pg_assert_same(lang('Compare-at price'), pg_commerce_fold_reference(true, 'manual')['line'], 'compare-at');

	pg_assert_same(lang(array('string' => 'On, {var:1}% commission', 'vars' => array('10'))), pg_commerce_fold_affiliate(true, true, '10')['line'], 'the rate');
	pg_assert_same(lang('On'), pg_commerce_fold_affiliate(true, true, '0')['line'], 'no rate');
	pg_assert_same(lang('Off'), pg_commerce_fold_affiliate(true, false, '10')['line'], 'off');

	pg_assert_same(lang(array('string' => 'On, {var:1} {var:2} for each friend', 'vars' => array('25', 'TRY'))), pg_commerce_fold_referral(true, true, '25', 'TRY')['line'], 'the reward');
	pg_assert_same(lang('On'), pg_commerce_fold_referral(true, true, '', 'TRY')['line'], 'no reward set');
	pg_assert_same(lang('Off'), pg_commerce_fold_referral(true, false, '25', 'TRY')['line'], 'off');
}

function test_settings_commerce_fold_feeds_line()
{
	$now = mktime(15, 0, 0, 11, 26, 2026);
	$today = mktime(3, 10, 0, 11, 26, 2026);
	$before = mktime(3, 10, 0, 11, 20, 2026);

	pg_assert_same(lang(array('string' => 'Built today {var:1} · {var:2} products', 'vars' => array(date('H:i', $today), 412))), pg_commerce_fold_feeds(true, true, 1, $today, 412, $now)['line'], 'built today');
	pg_assert_same(lang(array('string' => 'Built {var:1} · {var:2} products', 'vars' => array(date('j/n H:i', $before), 412))), pg_commerce_fold_feeds(true, true, 1, $before, 412, $now)['line'], 'built before today');
	pg_assert_same(lang(array('string' => 'Built today {var:1} · {var:2} products', 'vars' => array(date('g:i A', $today), 412))), pg_commerce_fold_feeds(true, true, 1, $today, 412, $now, 'g:i A', 'n/j')['line'], 'on the site\'s clock');
	pg_assert_same(lang('Not built yet'), pg_commerce_fold_feeds(true, true, 1, 0, 0, $now)['line'], 'not built yet');
	pg_assert_same(lang('No feed is switched on'), pg_commerce_fold_feeds(true, true, 0, 0, 0, $now)['line'], 'none on');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_commerce_fold_feeds(true, true, 0, 0, 0, $now)['pill'], 'none on is Off');
	pg_assert_same(lang('Not built yet'), pg_commerce_fold_feeds(true, false, 0, 0, 0, $now)['line'], 'before the upgrade');
}

function test_settings_commerce_fold_pane_is_rows()
{
	$pane = settings_commerce_fold_test_source('includes/settings/commerce.php');

	pg_assert_contains('$pg_settings_cards[] = $commerce_head . pg_settings_fold($commerce_rows);', $pane, 'the pane is Enable Commerce, then one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');
	pg_assert_true(strpos($pane, 'name="ecommerce"') < strpos($pane, '<div id="pgset-store" class="pg-set-card">'), 'Enable Commerce stands above the rows');
	pg_assert_false(strpos(settings_commerce_fold_test_card('pgset-store'), 'name="ecommerce"') !== false, 'and not inside Store');

	foreach (array('pgset-store', 'pgset-payments', 'pgset-shipping', 'pgset-address-autocomplete', 'pgset-stock-locations', 'pgset-giftcards', 'pgset-rewards', 'pgset-reference-price', 'pgset-affiliate', 'pgset-referral', 'pgset-feeds') as $id) {
		pg_assert_same(1, substr_count($pane, '<div id="' . $id . '" class="pg-set-card">'), $id . ' is drawn once, inside its row');
	}
}

function test_settings_commerce_fold_gift_cards_and_rewards_apart()
{
	$gift = settings_commerce_fold_test_card('pgset-giftcards');
	$rewards = settings_commerce_fold_test_card('pgset-rewards');

	foreach (array('ecommerce_gift_card', 'ecommerce_gift_card_validity_days', 'ecommerce_givex', 'credit_refund_bonus') as $name) {
		pg_assert_contains('name="' . $name . '"', $gift, $name . ' stays in Gift cards');
		pg_assert_false(strpos($rewards, 'name="' . $name . '"') !== false, $name . ' is not in Rewards');
	}

	foreach (array('ecommerce_reward_program', 'ecommerce_reward_program_points', 'credit_points_per_unit', 'credit_points_smallest', 'ecommerce_reward_program_membership', 'ecommerce_reward_program_membership_days', 'ecommerce_reward_program_email', 'ecommerce_reward_program_email_bcc_email_address', 'ecommerce_reward_program_email_subject') as $name) {
		pg_assert_contains('name="' . $name . '"', $rewards, $name . ' is in Rewards');
		pg_assert_false(strpos($gift, 'name="' . $name . '"') !== false, $name . ' left Gift cards');
	}

	pg_assert_contains("'ecommerce_reward_program_email_page_id'", $rewards, 'the e-mail page is in Rewards');

	$anchors = pg_settings_legacy_anchors();

	foreach (array('ecommerce_reward_program', 'credit_points_per_unit', 'pgsub-rewards') as $anchor) {
		pg_assert_same(array('commerce', 'pgset-rewards'), $anchors[$anchor] ?? null, $anchor . ' opens Rewards');
	}

	pg_assert_same(array('commerce', 'pgset-giftcards'), $anchors['pgsub-giftcards'] ?? null, 'the old gift card anchor still opens Gift cards');
}

function test_settings_commerce_fold_registry()
{
	$source = settings_commerce_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'commerce' => array(");
	$commerce = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("lang('How the shop sells, takes payment and ships.')", $commerce, 'the purpose line');

	preg_match_all("/'(pgset-[a-z-]+)'\s*=> lang\(/", substr($commerce, 0, strpos($commerce, "'keywords'")), $matches);
	pg_assert_same(
		array('pgset-store', 'pgset-payments', 'pgset-shipping', 'pgset-address-autocomplete', 'pgset-stock-locations', 'pgset-giftcards', 'pgset-rewards', 'pgset-reference-price', 'pgset-affiliate', 'pgset-referral', 'pgset-feeds'),
		$matches[1],
		'the sections in the rows\' order'
	);

	pg_assert_contains("'pgset-rewards'", substr($commerce, strpos($commerce, "'keywords'")), 'Rewards has search words');
	pg_assert_contains("'pgset-giftcards' => lang('Gift cards')", $commerce, 'Gift cards is its own section');
}

function test_settings_commerce_fold_names_stay()
{
	$pane = settings_commerce_fold_test_source('includes/settings/commerce.php');

	foreach (array('ecommerce', 'ecommerce_next_order_number', 'ecommerce_tax_exempt_label', 'ecommerce_payment_gateway', 'ecommerce_offline_payment', 'ecommerce_recipient_mode', 'ecommerce_reference_price_rule', 'affiliate_program', 'referral_on', 'product_feed_seller_name') as $name) {
		// A switch is drawn by pg_commerce_settings_switch() or
		// pg_commerce_settings_group_switch(), named in their first argument.
		$drawn = (strpos($pane, 'name="' . $name . '"') !== false) || (preg_match("/pg_commerce_settings_(group_)?switch\\('" . preg_quote($name, '/') . "'/", $pane) === 1);
		pg_assert_true($drawn, $name . ' posts under its name');
	}

	$save = settings_commerce_fold_test_source('includes/settings/commerce.save.php');
	pg_assert_false(strpos($save, 'pg_settings_fold(') !== false || strpos($save, 'pgset-rewards') !== false, 'the save knows nothing of rows');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a marketplace account's Pricing tab and a listing's own price
 * (includes/fn/marketplace_pricing_settings.php): the posted tab read into
 * rule rows, whether a save changes the prices, the one-time requeue's
 * state, the strategies a marketplace offers and a listing's typed price.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_pricing_settings.php');

// The tab as posted with nothing touched.
function marketplace_pricing_settings_test_post($changes = array())
{
	return array_merge(array(
		'pr_percent' => '', 'pr_fixed' => '', 'pr_rounding' => 'none',
		'pr_try' => '1', 'pr_step' => '1,00', 'pr_margin' => '10', 'pr_grow' => '10',
	), $changes);
}

// The account row and the rules come out in the table's units: percents in
// hundredths, money in minor units, try first as robot 1, sending as 2.
function test_marketplace_pricing_settings_parse_units()
{
	$parsed = mp_pricing_settings_parse(marketplace_pricing_settings_test_post(array(
		'pr_percent' => '12,5', 'pr_fixed' => '-3,90', 'pr_rounding' => '99', 'pr_try' => '',
		'pr_step' => '2,50', 'pr_margin' => '15', 'pr_grow' => '8',
		'pr_rule_scope' => array('c:7', 'b: Acme ', ''),
		'pr_rule_percent' => array('20', '-5', '9'),
		'pr_rule_fixed' => array('', '1', ''),
		'pr_rule_rounding' => array('whole', 'bogus', 'none'),
	)), array(7));

	pg_assert_same(array(), $parsed['errors'], 'no errors');
	pg_assert_same(3, count($parsed['rules']), 'account row and two rules; the empty row is dropped');

	$account = $parsed['rules'][0];
	pg_assert_same(MP_PRICING_SCOPE_ACCOUNT, $account['scope'], 'account first');
	pg_assert_same(1250, $account['percent'], 'percent');
	pg_assert_same(-390, $account['fixed'], 'fixed');
	pg_assert_same('99', $account['rounding'], 'rounding');
	pg_assert_same(MP_PRICING_ROBOT_ON, $account['robot'], 'try first off sends');
	pg_assert_same(250, $account['step'], 'step');
	pg_assert_same(1500, $account['min_margin'], 'margin');
	pg_assert_same(800, $account['grow_cap'], 'grow cap');

	pg_assert_same(array(MP_PRICING_SCOPE_CATEGORY, 7, '', 2000, 0, 'whole'), array(
		$parsed['rules'][1]['scope'], $parsed['rules'][1]['scope_id'], $parsed['rules'][1]['scope_key'],
		$parsed['rules'][1]['percent'], $parsed['rules'][1]['fixed'], $parsed['rules'][1]['rounding'],
	), 'category rule');
	pg_assert_same(array(MP_PRICING_SCOPE_BRAND, 0, 'Acme', -500, 100, 'none'), array(
		$parsed['rules'][2]['scope'], $parsed['rules'][2]['scope_id'], $parsed['rules'][2]['scope_key'],
		$parsed['rules'][2]['percent'], $parsed['rules'][2]['fixed'], $parsed['rules'][2]['rounding'],
	), 'brand rule, trimmed, unknown rounding as none');
}

// Empty boxes take the defaults; try first is on unless switched off.
function test_marketplace_pricing_settings_parse_defaults()
{
	$parsed = mp_pricing_settings_parse(array(), array());
	$account = $parsed['rules'][0];

	pg_assert_same(array(), $parsed['errors'], 'no errors');
	pg_assert_same(array(0, 0, 'none', MP_PRICING_ROBOT_TRY, 100, 1000, 1000), array(
		$account['percent'], $account['fixed'], $account['rounding'], $account['robot'],
		$account['step'], $account['min_margin'], $account['grow_cap'],
	), 'defaults');
	pg_assert_true(mp_pricing_settings_is_default($parsed['rules']), 'nothing typed is the default');
	pg_assert_false(mp_pricing_settings_is_default(mp_pricing_settings_parse(
		marketplace_pricing_settings_test_post(array('pr_try' => '')), array())['rules']), 'try first switched off');
	pg_assert_false(mp_pricing_settings_is_default(mp_pricing_settings_parse(
		marketplace_pricing_settings_test_post(array('pr_rule_scope' => array('b:Acme'))), array())['rules']), 'a rule');
}

// Out-of-range figures, an unknown category and a scope named twice are
// refused, each under its own field.
function test_marketplace_pricing_settings_parse_refuses()
{
	$parsed = mp_pricing_settings_parse(marketplace_pricing_settings_test_post(array(
		'pr_percent' => '-95', 'pr_step' => '0', 'pr_margin' => '95',
		'pr_rule_scope' => array('c:8', 'b:acme', 'b:ACME '),
	)), array(7));

	foreach (array('pr_percent', 'pr_step', 'pr_margin', 'pr_rule_0', 'pr_rule_2') as $field) {
		pg_assert_true(isset($parsed['errors'][$field]), $field);
	}

	pg_assert_false(isset($parsed['errors']['pr_rule_1']), 'the first of two brands stands');
}

// A save changes the prices when a price-moving figure of any rule changes,
// a rule comes or goes, or the margin moves; the robot's own settings and a
// brand's case do not.
function test_marketplace_pricing_settings_changed()
{
	$before = mp_pricing_settings_parse(marketplace_pricing_settings_test_post(array(
		'pr_percent' => '5', 'pr_rule_scope' => array('b:Acme'), 'pr_rule_percent' => array('10'),
	)), array())['rules'];

	$same = mp_pricing_settings_parse(marketplace_pricing_settings_test_post(array(
		'pr_percent' => '5,00', 'pr_try' => '', 'pr_step' => '3', 'pr_grow' => '5',
		'pr_rule_scope' => array('b:ACME'), 'pr_rule_percent' => array('10'),
	)), array())['rules'];

	pg_assert_false(mp_pricing_settings_changed($before, $same), 'robot settings and brand case only');
	pg_assert_true(mp_pricing_settings_changed(array(), $before), 'rules where there were none');
	pg_assert_true(mp_pricing_settings_changed($before, array()), 'every rule gone');

	foreach (array(
		'percent' => array('pr_percent' => '6'),
		'margin' => array('pr_margin' => '12'),
		'rule rounding' => array('pr_rule_rounding' => array('90')),
		'rule gone' => array('pr_rule_scope' => array()),
		'rule added' => array('pr_rule_scope' => array('b:Acme', 'b:Other'), 'pr_rule_percent' => array('10', '1')),
	) as $label => $change) {
		$after = mp_pricing_settings_parse(marketplace_pricing_settings_test_post(array_merge(array(
			'pr_percent' => '5', 'pr_rule_scope' => array('b:Acme'), 'pr_rule_percent' => array('10'),
		), $change)), array())['rules'];

		pg_assert_true(mp_pricing_settings_changed($before, $after), $label);
	}
}

// The requeue's state: a change marks the account from its first listing;
// a step moves it on, a pass through drops it, and a step of a pass the
// person changed the rules again during leaves the new mark alone.
function test_marketplace_pricing_requeue_state()
{
	$state = mp_pricing_requeue_mark(array(), 4, 1000);
	pg_assert_same(array('4' => array('after' => 0, 'at' => 1000)), $state, 'marked');

	$state = mp_pricing_requeue_step($state, 4, 1000, 250, false);
	pg_assert_same(array('4' => array('after' => 250, 'at' => 1000)), $state, 'moved on');

	$again = mp_pricing_requeue_mark($state, 4, 1200);
	pg_assert_same(array('4' => array('after' => 0, 'at' => 1200)), $again, 'a new change starts over');
	pg_assert_same($again, mp_pricing_requeue_step($again, 4, 1000, 500, true), 'an older pass leaves the new mark');

	pg_assert_same(array(), mp_pricing_requeue_step($state, 4, 1000, 500, true), 'through');
	pg_assert_same(array('9' => array('after' => 0, 'at' => 5)),
		mp_pricing_requeue_step(array('9' => array('after' => 0, 'at' => 5)), 4, 1000, 1, true), 'another account kept');
}

// Win and Grow while winning need a buybox read: Trendyol only; n11 and the
// rest offer Fixed alone, and a strategy they cannot do falls back to it.
function test_marketplace_pricing_strategies()
{
	pg_assert_same(array(MP_PRICING_STRATEGY_FIXED, MP_PRICING_STRATEGY_WIN, MP_PRICING_STRATEGY_GROW),
		array_keys(mp_pricing_strategies('trendyol')), 'trendyol');
	pg_assert_same(array(MP_PRICING_STRATEGY_FIXED), array_keys(mp_pricing_strategies('n11')), 'n11');
	pg_assert_same(MP_PRICING_STRATEGY_FIXED, mp_pricing_listing_parse('', '2', 'n11')['strategy'], 'n11 grow refused to fixed');
	pg_assert_same(MP_PRICING_STRATEGY_WIN, mp_pricing_listing_parse('', '1', 'trendyol')['strategy'], 'win');
	pg_assert_same(MP_PRICING_STRATEGY_FIXED, mp_pricing_listing_parse('', 'x', 'trendyol')['strategy'], 'junk');
}

// A listing's own price: empty is by the rules (0), a typed one in minor
// units, a negative or absurd one refused.
function test_marketplace_pricing_listing_own_price()
{
	pg_assert_same(0, mp_pricing_listing_parse('', '0', 'n11')['own_price'], 'empty');
	pg_assert_same(12990, mp_pricing_listing_parse('129,90', '0', 'n11')['own_price'], 'decimal comma');
	pg_assert_same(123456, mp_pricing_listing_parse('1.234,56', '0', 'n11')['own_price'], 'thousands');
	pg_assert_same('', mp_pricing_listing_parse('129,90', '0', 'n11')['error'], 'no error');
	pg_assert_true(mp_pricing_listing_parse('-5', '0', 'n11')['error'] !== '', 'negative');
	pg_assert_true(mp_pricing_listing_parse('0', '0', 'n11')['error'] !== '', 'zero typed');
	pg_assert_true(mp_pricing_listing_parse('99999999', '0', 'n11')['error'] !== '', 'too large');
}

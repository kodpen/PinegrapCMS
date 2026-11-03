<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the offers list: the Flow column chips say "gift" only for a
 * free product and show both usage limits, the optional status chips are
 * always drawn (hidden at 0) so recount() can show one a toggle fills, and
 * the Turkish offer texts read as conditions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');

function offers_list_chips_products()
{
	return array(array('id' => 5, 'label' => 'Novel'));
}

function offers_list_chips_gift($unit, $value)
{
	return array('type' => 'add product', 'unit' => $unit, 'value' => $value, 'product_id' => 5, 'quantity' => 2);
}

function test_offers_list_gift_chip_only_for_a_free_product()
{
	$p = offers_list_chips_products();
	pg_assert_same('2 × Novel ' . lang('gift'), _pg_offer_action_chip(offers_list_chips_gift('percent', 100), $p));
	pg_assert_same('2 × Novel ' . lang('full price'), _pg_offer_action_chip(offers_list_chips_gift('percent', 0), $p));
	$half = _pg_offer_action_chip(offers_list_chips_gift('percent', 50), $p);
	pg_assert_same('2 × Novel ' . _pg_offer_percent_text(50), $half);
	pg_assert_same(false, strpos($half, lang('gift')), 'a discounted product is not a gift');
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
	pg_assert_contains("return \$chip . ((\$action['unit'] == 'amount') ? '−' : '') . _pg_offer_value_text(\$action);", $src, 'an amount off reads −₺5,25');
}

function test_offers_list_usage_chip_shows_both_limits()
{
	$both = array('type' => 'usage limit', 'total_limit' => 100, 'customer_limit' => 2);
	pg_assert_same(lang(array('string' => 'max {var:1} use(s)', 'vars' => 100)) . ' · ' . lang(array('string' => '{var:1}× per customer', 'vars' => 2)), _pg_offer_condition_chip($both, array()));
	$total = array('type' => 'usage limit', 'total_limit' => 100, 'customer_limit' => 0);
	pg_assert_same(lang(array('string' => 'max {var:1} use(s)', 'vars' => 100)), _pg_offer_condition_chip($total, array()));
	$customer = array('type' => 'usage limit', 'total_limit' => 0, 'customer_limit' => 2);
	pg_assert_same(lang(array('string' => '{var:1}× per customer', 'vars' => 2)), _pg_offer_condition_chip($customer, array()));
}

function test_offers_list_optional_chips_follow_their_count()
{
	$tpl = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/view_offers.php');
	pg_assert_same(false, strpos($tpl, "if (!\$f['always'] && \$f['count'] == 0) { continue; }"), 'an empty chip is drawn, not skipped');
	pg_assert_contains("<?php if (!\$f['always'] && \$f['count'] == 0): ?> d-none<?php endif ?>", $tpl);
	pg_assert_contains("data-optional=\"1\"", $tpl);
	pg_assert_contains("chip.toggleClass('d-none', n === 0 && !chip.hasClass('active'));", $tpl, 'recount() shows and hides optional chips');
}

function test_offers_turkish_texts_read_as_conditions()
{
	$tr = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$tr += (array) json_decode((string) file_get_contents($file), true);
	}
	pg_assert_same('sepette {var:2} grubundan en az {var:1} ürün varsa', $tr['cart has at least {var:1} from {var:2}']);
	pg_assert_same('müşteri başına en çok {var:1} kez kullanılmışsa', $tr['at most {var:1} time(s) per customer']);
	pg_assert_same('Kargo {var:1}', $tr['Shipping {var:1}']);
	pg_assert_same('Süresi doldu', $tr['Expired']);
}

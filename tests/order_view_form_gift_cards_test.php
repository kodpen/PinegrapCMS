<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the order screen: the product form heading closes its
 * own div, an unlimited recurring line reads a translated "No limit", and
 * the applied gift card rows start from an empty string and show the
 * Givex authorization only for a Givex card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_view_form_gift_cards_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_order.php');
}

function test_order_view_form_heading_closes_its_div()
{
	$source = order_view_form_gift_cards_source();
	pg_assert_false(strpos($source, '</legend>') !== false, 'view_order.php: a div heading closed with </legend>');
	pg_assert_contains("'<div class=\"card-header bg-reset border-0 text-uppercase h5 fg-secondary fw-bold\">' . \$output_legend_content . '</div>'", $source, 'view_order.php');
}

function test_order_view_no_limit_is_translated()
{
	$source = order_view_form_gift_cards_source();
	pg_assert_false(strpos($source, "'[no limit]'") !== false, 'view_order.php: untranslated [no limit]');
	pg_assert_true(substr_count($source, "\$output_recurring_number_of_payments = lang('No limit');") === 2, 'view_order.php: both recurring lines use lang(\'No limit\')');
}

function test_order_view_gift_card_rows_initialised_and_givex_only()
{
	$source = order_view_form_gift_cards_source();
	$init = strpos($source, "\$output_applied_gift_card_rows = '';");
	$loop = strpos($source, 'foreach ($applied_gift_cards as $applied_gift_card)');
	pg_assert_true($init !== false && $loop !== false && $init < $loop, 'view_order.php: gift card rows initialised before the loop');

	$auth = strpos($source, "lang('Givex Auth')");
	$guard = strpos($source, "if (\$applied_gift_card['givex'] != 0) {", $loop);
	pg_assert_true($auth !== false && $guard !== false && $guard < $auth, 'view_order.php: Givex Auth row only for a Givex card');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a refused cart add (the gift card line limit) leaves the order
 * as it was: add_order_item() looks the recipient up without creating it and
 * creates it only after the add is accepted, so no empty recipient stays
 * behind. Also tests the recipient name an add resolves to. No database is
 * used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_ship_to_name_for_add()
{
	pg_assert_true(function_exists('pg_ship_to_name_for_add'), 'the recipient name has a helper');

	if (!function_exists('pg_ship_to_name_for_add')) {
		return;
	}

	$myself = lang('myself');
	pg_assert_same('Ali', pg_ship_to_name_for_add('Veli', 'Ali'), 'the typed name wins over the pick list');
	pg_assert_same('Veli', pg_ship_to_name_for_add('Veli', ''), 'a picked name');
	pg_assert_same($myself, pg_ship_to_name_for_add('', ''), 'nothing given is the buyer');
	pg_assert_same($myself, pg_ship_to_name_for_add(lang('- add name below -'), ''), 'the pick list prompt is the buyer');
	pg_assert_same($myself, pg_ship_to_name_for_add('myself', ''), 'the pick list value of the buyer');
	pg_assert_same($myself, pg_ship_to_name_for_add('', 'Myself'), 'a typed "myself" is the buyer');
}

function test_add_order_item_refuses_before_creating_the_recipient()
{
	$function = new ReflectionFunction('add_order_item');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$refusal = strpos($source, 'pg_gift_card_quantity_refusal(true)');
	$create = strpos($source, 'create_or_get_ship_to(');

	pg_assert_true($refusal !== false, 'add_order_item() refuses an over-limit gift card line');
	pg_assert_true($create !== false, 'add_order_item() creates the recipient');
	pg_assert_true($refusal !== false && $create !== false && $refusal < $create, 'the refusal comes before the recipient is created');
}

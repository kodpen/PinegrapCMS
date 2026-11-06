<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * add_order_item() refuses a gift card line over its unit limit before it
 * creates a recipient, so a refused add leaves no empty ship_tos row behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// add_order_item()'s source without its comment lines.
function gift_card_refused_recipient_test_source()
{
	$reflection = new ReflectionFunction('add_order_item');
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_add_order_item_refuses_the_gift_card_before_creating_a_recipient()
{
	$source = gift_card_refused_recipient_test_source();

	$refusal = strpos($source, 'pg_gift_card_quantity_refusal(');
	$create = strpos($source, 'create_or_get_ship_to(');

	pg_assert_true($refusal !== false, 'the gift card refusal is in add_order_item()');
	pg_assert_true($create !== false, 'add_order_item() still creates the recipient');
	pg_assert_true($refusal < $create, 'the refusal comes before the recipient is created');
	pg_assert_false((bool) preg_match('~INSERT\s+INTO\s+ship_tos~i', substr($source, 0, $refusal)), 'no recipient is inserted before the refusal');
}

function test_ship_to_lookup_does_not_insert()
{
	$reflection = new ReflectionFunction('pg_ship_to_find');
	$lines = file($reflection->getFileName());
	$source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	pg_assert_false((bool) preg_match('~INSERT\s+INTO~i', $source), 'the lookup creates nothing');
	pg_assert_false(strpos($source, 'add_recipient(') !== false, 'the lookup adds nothing to the pick lists');
}

function test_ship_to_name_resolution()
{
	pg_assert_same('Ali', pg_ship_to_name('', 'Ali'), 'the add name wins');
	pg_assert_same('Ayse', pg_ship_to_name('Ayse', ''), 'a picked name is used');
	pg_assert_same(lang('myself'), pg_ship_to_name('', ''), 'no name is the buyer');
	pg_assert_same(lang('myself'), pg_ship_to_name('Myself', ''), 'the English option value is the buyer');
}

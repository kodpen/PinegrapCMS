<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The erasure of a person's store credit: every card with a balance is
 * voided, once more on a fresh read when an order spent part of it
 * meanwhile, and only cards brought to zero stop naming the account. A card
 * that still holds a balance keeps its owner, so it never turns into a
 * plain gift card that pays for whoever knows the code (#10261).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function privacy_erase_store_credit_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A void that lost a race with an order is tried once more, and only once.
function test_privacy_erase_store_credit_retries_the_void_once()
{
	$source = privacy_erase_store_credit_test_source('_pg_privacy_erase_apply_store_credit');

	pg_assert_same(2, substr_count($source, 'pg_credit_void('), 'the void is called, then once more');
	pg_assert_contains("lang('Personal data erased')", $source, 'the void note names the reason');
	pg_assert_true(preg_match('/!\s*pg_credit_void\(/', $source) === 1, 'the second call runs only when the first failed');
	pg_assert_true(preg_match('/\b(while|do)\b/', $source) === 0, 'no loop: one retry at most');
}

// Only cards with no balance left lose the account.
function test_privacy_erase_store_credit_clears_only_closed_cards()
{
	$source = privacy_erase_store_credit_test_source('_pg_privacy_erase_apply_store_credit');

	$void = strrpos($source, 'pg_credit_void(');
	$write = strpos($source, '_pg_privacy_erase_write(');
	pg_assert_true(($void !== false) && ($write !== false) && ($void < $write), 'voided first, then cleared');
	pg_assert_contains("_pg_privacy_erase_write('gift_cards', '(' . \$where . ') AND (balance = 0)', \$fields, \$name)", $source, 'the write is limited to cards at zero');
	pg_assert_true(preg_match('/_pg_privacy_erase_write\(\s*\'gift_cards\',\s*\$where\s*,/', $source) === 0, 'never a write over every card of the person');
}

// The entry itself is unchanged: found by the accounts, the account cleared.
function test_privacy_erase_store_credit_entry_unchanged()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_same('gift_cards', $tables['store_credit']['table'], 'the gift_cards table');
	pg_assert_same(array('user_id' => 'user_ids'), $tables['store_credit']['find'], 'found by the person\'s accounts');
	pg_assert_same(array('user_id' => 0), $tables['store_credit']['fields'], 'the account is cleared');
}

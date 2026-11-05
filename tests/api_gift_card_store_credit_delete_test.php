<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that DELETE /gift-cards/{id} (api_gift_cards_delete() in
 * includes/api/resources/gift_cards.php) refuses a store credit card with
 * 409 store_credit_not_deletable before any write. No database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/gift_cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function test_api_gift_card_is_store_credit()
{
	pg_assert_true(api_gift_card_is_store_credit(array('id' => '5', 'user_id' => '17')), 'bound to an account');
	pg_assert_true(!api_gift_card_is_store_credit(array('id' => '5', 'user_id' => '0')), 'an ordinary gift card');
	pg_assert_true(!api_gift_card_is_store_credit(array('id' => '5', 'user_id' => null)), 'null account');
	pg_assert_true(!api_gift_card_is_store_credit(array('id' => '5')), 'a site before store credit');
}

function test_api_gift_cards_delete_refuses_store_credit_before_writing()
{
	$function = new ReflectionFunction('api_gift_cards_delete');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$check = strpos($source, 'api_gift_card_is_store_credit($row)');
	$fail = strpos($source, "api_fail(409, 'store_credit_not_deletable'");
	$dry = strpos($source, 'api_dry_run_stop(');
	$delete = strpos($source, 'DELETE FROM gift_cards');

	pg_assert_true($check !== false && $fail !== false, 'the refusal is there');
	pg_assert_true($dry !== false && $delete !== false, 'the delete is there');
	pg_assert_true($fail < $dry, 'refused before the dry-run stop');
	pg_assert_true($fail < $delete, 'refused before the delete');
	pg_assert_contains('/customers/{id}/credit/{card_id}/void', $source, 'the message names the void route');
}

function test_api_gift_cards_delete_schema_names_the_refusal()
{
	$found = 0;

	foreach (api_schema() as $endpoint) {
		if (in_array($endpoint['id'], array('gift_cards.delete', 'gift_cards.delete_post'), true)) {
			pg_assert_contains('409 store_credit_not_deletable', $endpoint['description'], $endpoint['id']);
			$found++;
		}
	}

	pg_assert_same(2, $found, 'both delete routes');
}

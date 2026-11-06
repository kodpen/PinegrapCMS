<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that POST /gift-cards/{id} (api_gift_cards_update() in
 * includes/api/resources/gift_cards.php) writes a balance only over the
 * balance it read, answers 409 balance_changed when an order spent meanwhile,
 * and records a changed store credit balance in the audit trail as the gift
 * cards screen does. No database is used.
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

// escape() falls back to addslashes() without a connection.
if (!class_exists('db')) {
	class db
	{
		public static $con;
	}
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/gift_cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/store_credit.php');

function test_api_gift_card_update_sql_guards_the_balance()
{
	$sql = api_gift_card_update_sql(5, array('balance' => '700'), 1000, 3);

	pg_assert_contains("balance = '700'", $sql, 'the new balance is written');
	pg_assert_contains("WHERE id = '5' AND balance = '1000'", $sql, 'only over the balance read');
	pg_assert_contains("last_modified_user_id = '3'", $sql, 'the owner is recorded');

	$sql = api_gift_card_update_sql(5, array('notes' => 'x'), 1000, 3);

	pg_assert_true(strpos($sql, 'AND balance') === false, 'no guard when the balance is not sent');
}

function test_api_gift_card_credit_audit()
{
	$credit = array('id' => '5', 'user_id' => '17', 'kind' => 'manual', 'amount' => '1000', 'balance' => '1000',
		'reason' => 'Late delivery', 'issued_by' => '2', 'expires_at' => '0', 'code' => '1234567812345678');

	$audit = api_gift_card_credit_audit($credit, array('balance' => '400'));

	pg_assert_true(is_array($audit), 'a changed store credit balance is recorded');
	pg_assert_same(1000, $audit['before']['balance'], 'balance before');
	pg_assert_same(400, $audit['after']['balance'], 'balance after');
	pg_assert_same(17, $audit['after']['user_id'], 'the account stays');
	pg_assert_same('Late delivery', $audit['after']['reason'], 'the reason stays');

	pg_assert_same(null, api_gift_card_credit_audit($credit, array('balance' => '1000')), 'unchanged balance');
	pg_assert_same(null, api_gift_card_credit_audit($credit, array('notes' => 'x')), 'balance not sent');
	pg_assert_same(null, api_gift_card_credit_audit(array('user_id' => '0', 'balance' => '1000', 'amount' => '1000'), array('balance' => '400')), 'an ordinary gift card');
	pg_assert_same(null, api_gift_card_credit_audit(array('balance' => '1000', 'amount' => '1000'), array('balance' => '400')), 'a site before store credit');
}

function test_api_gift_cards_update_uses_the_guard_and_the_audit()
{
	$function = new ReflectionFunction('api_gift_cards_update');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$dry = strpos($source, 'api_dry_run_stop(');
	$write = strpos($source, 'api_gift_card_update_sql(');
	$refuse = strpos($source, "api_fail(409, 'balance_changed'");
	$audit = strpos($source, "pg_audit('update', 'store_credit'");

	pg_assert_true($dry !== false && $write !== false, 'the guarded write is used');
	pg_assert_true($dry < $write, 'written after the dry-run stop');
	pg_assert_true($refuse !== false && $write < $refuse, 'a lost race is refused');
	pg_assert_true($audit !== false && $refuse < $audit, 'store credit is audited after the write landed');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock and price queue's lease (includes/api/outbound/sync.php,
 * connectors/base.php): a change that arrives while a batch is out is not
 * folded into the row already being sent, and a batch sends a product once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');

function test_marketplace_sync_lease_waiting_row_takes_a_change()
{
	pg_assert_true(mp_enqueue_absorbs(array('status' => 'waiting', 'remote_task_id' => '')));
}

// The batch read the product before this change: folding the change into
// that row would mark it sent with the old stock and never send the new one.
function test_marketplace_sync_lease_leased_row_does_not_take_a_change()
{
	pg_assert_false(mp_enqueue_absorbs(array('status' => 'waiting', 'remote_task_id' => mp_sync_lease_token())));
	pg_assert_false(mp_enqueue_absorbs(array('status' => 'sent', 'remote_task_id' => '123')));
	pg_assert_false(mp_enqueue_absorbs(array()));
}

function test_marketplace_sync_lease_tokens_differ_and_fit_the_column()
{
	$one = mp_sync_lease_token();
	$two = mp_sync_lease_token();

	pg_assert_true($one !== $two, 'two passes never share a lease');
	pg_assert_true(strlen($one) <= 64, 'remote_task_id is VARCHAR(64)');
	pg_assert_true(mp_sync_is_lease($one));
	pg_assert_false(mp_sync_is_lease('8823411'), 'a marketplace task id is not a lease');
	pg_assert_false(mp_sync_is_lease(''));
}

// A row left by a pass that died and the row written for a later change
// both come due for the same product: it is sent once, both rows close.
function test_marketplace_sync_lease_batch_sends_a_product_once()
{
	$rows = array(
		array('id' => 4, 'product_id' => 10),
		array('id' => 9, 'product_id' => 11),
		array('id' => 12, 'product_id' => 10)
	);

	pg_assert_same(array(4 => true, 9 => true, 12 => false), mp_sync_first_per_product($rows));
}

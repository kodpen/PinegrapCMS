<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for _pg_order_status_stage() (includes/fn/ecommerce.php), which
 * turns a raw orders.status into the stage the order view's status badge is
 * drawn for. A negated word holds its positive one ('incomplete' holds
 * 'complete', 'unpaid' holds 'paid', 'tamamlanmadı' holds 'tamam'), so a
 * saved basket must not read as a complete order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_status_stage_stored_values()
{
	pg_assert_same('pending', _pg_order_status_stage('incomplete'), 'incomplete');
	pg_assert_same('complete', _pg_order_status_stage('complete'), 'complete');
	pg_assert_same('complete', _pg_order_status_stage('exported'), 'exported');
	pg_assert_same('cancelled', _pg_order_status_stage('cancelled'), 'cancelled');
	pg_assert_same('cancelled', _pg_order_status_stage('canceled'), 'canceled');
	pg_assert_same('default', _pg_order_status_stage(''), 'empty');
}

function test_order_status_stage_negated_words_are_not_complete()
{
	pg_assert_same('pending', _pg_order_status_stage('Incomplete '), 'incomplete, other case and a space');
	pg_assert_same('pending', _pg_order_status_stage('unpaid'), 'unpaid');
	pg_assert_same('pending', _pg_order_status_stage('Tamamlanmadı'), 'Turkish not completed');
	pg_assert_same('complete', _pg_order_status_stage('paid'), 'paid');
}

function test_order_status_stage_custom_values_by_their_words()
{
	pg_assert_same('complete', _pg_order_status_stage('Tamamlandı'), 'Turkish completed');
	pg_assert_same('complete', _pg_order_status_stage('Ödendi'), 'Turkish paid');
	pg_assert_same('shipped', _pg_order_status_stage('Kargoya verildi'), 'Turkish shipped');
	pg_assert_same('refunded', _pg_order_status_stage('refunded'), 'refunded');
	pg_assert_same('cancelled', _pg_order_status_stage('iptal edildi'), 'Turkish cancelled');
	pg_assert_same('pending', _pg_order_status_stage('Beklemede'), 'Turkish pending');
	pg_assert_same('default', _pg_order_status_stage('on hold'), 'unknown word');
}

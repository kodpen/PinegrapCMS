<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the overdue receivable text message (includes/erp/notify.php)
 * that need no database: which number an account is texted at or why it is
 * not, the notice stage, and the queue references the invoice screen reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/notify.php');

// An account as the digest reads it.
function erp_overdue_sms_test_account($extra = array())
{
	return array_merge(array(
		'account_phone'   => '0532 111 22 33',
		'contact_mobile'  => '',
		'customer_wanted' => true,
	), $extra);
}

function test_erp_overdue_sms_uses_account_phone()
{
	pg_assert_same(array('number' => '905321112233', 'reason' => ''), erp_overdue_sms_decide(erp_overdue_sms_test_account(), true, true));
}

// A landline on the account falls back to the linked contact's mobile.
function test_erp_overdue_sms_falls_back_to_contact_mobile()
{
	$decision = erp_overdue_sms_decide(erp_overdue_sms_test_account(array('account_phone' => '0212 444 55 66', 'contact_mobile' => '+90 (544) 999 88 77')), true, true);

	pg_assert_same(array('number' => '905449998877', 'reason' => ''), $decision);
}

function test_erp_overdue_sms_reasons()
{
	pg_assert_same('no_phone', erp_overdue_sms_decide(erp_overdue_sms_test_account(array('account_phone' => ' ')), true, true)['reason']);
	pg_assert_same('not_mobile', erp_overdue_sms_decide(erp_overdue_sms_test_account(array('account_phone' => '0212 444 55 66')), true, true)['reason']);
	pg_assert_same('account_off', erp_overdue_sms_decide(erp_overdue_sms_test_account(array('customer_wanted' => false)), true, true)['reason']);
	pg_assert_same('event_off', erp_overdue_sms_decide(erp_overdue_sms_test_account(), true, false)['reason']);
	pg_assert_same('module_off', erp_overdue_sms_decide(erp_overdue_sms_test_account(), false, true)['reason']);
	pg_assert_same('', erp_overdue_sms_decide(erp_overdue_sms_test_account(array('customer_wanted' => false)), true, false)['number']);
}

// A run that lists any document a second time sends the second notice.
function test_erp_overdue_sms_stage()
{
	pg_assert_same('first', erp_overdue_sms_stage(array(array('notified_at' => 0), array('notified_at' => 0))));
	pg_assert_same('second', erp_overdue_sms_stage(array(array('notified_at' => 0), array('notified_at' => 1761800000))));
}

// One reference per account, stage and day: a second run on the same day
// queues nothing new, and the invoice screen finds it from the stamps.
function test_erp_overdue_sms_references()
{
	$now = mktime(9, 0, 0, 10, 30, 2026);

	pg_assert_same('erp_overdue:9:first:20261030', erp_overdue_sms_reference(9, 'first', $now));
	pg_assert_same(1, preg_match('/^[A-Za-z0-9:._\\/-]{1,64}$/', erp_overdue_sms_reference(4294967295, 'second', $now)));

	pg_assert_same(array(
		'erp_overdue:9:first:20261030',
		'erp_overdue:9:second:20261030',
	), erp_overdue_sms_references(9, $now, 0));

	pg_assert_same(4, count(erp_overdue_sms_references(9, $now, $now + 30 * 86400)));
	pg_assert_same(array(), erp_overdue_sms_references(9, 0, 0));
	pg_assert_same(array(), erp_overdue_sms_references(0, $now, 0));
}

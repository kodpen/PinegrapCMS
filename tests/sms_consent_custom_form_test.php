<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS marketing question on custom forms: the opt_in_sms
 * contact binding, which is no contacts column, and the answer a submission
 * records as a consent_log row on the sms channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// opt_in_sms is kept only in the consent log, never in contacts.
function test_sms_consent_custom_form_column_skip()
{
	pg_assert_false(pg_cf_contact_column('opt_in_sms'), 'opt_in_sms is no contacts column');
	pg_assert_false(pg_cf_contact_column(''), 'no binding');
	pg_assert_true(pg_cf_contact_column('opt_in'), 'opt_in is a column');
	pg_assert_true(pg_cf_contact_column('mobile_phone'), 'mobile_phone is a column');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS campaign screen's layout (edit_sms_campaign.php): the
 * text and the contact groups in the form column, the delivery in the
 * sticky status column, the sticky save bar. Reads the source; no database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');

function test_sms_campaign_screen_uses_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');

	pg_assert_contains('pg_email_campaign_layout(', $source, 'the screen draws the shared columns and save bar');
	pg_assert_false(strpos($source, '<nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;"') !== false, 'no save bar of its own');
	pg_assert_false(strpos($source, 'md:col-6') !== false, 'no half-width rows left in the narrow status column');
}

function test_sms_campaign_status_row()
{
	$row = pg_sms_campaign_status_row('Send <Time>', '<b>now</b>');
	pg_assert_contains('class="col-12 d-flex justify-content-between gap-3 py-2 border-bottom"', $row, 'a full-width row inside the card row');
	pg_assert_contains('<span>Send &lt;Time&gt;</span>', $row, 'the label is escaped');
	pg_assert_contains('<span class="fw-bold text-end text-break"><b>now</b></span>', $row, 'the value is HTML');
}

function test_sms_campaign_screen_columns()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');
	$layout = substr($source, strpos($source, 'pg_email_campaign_layout('));

	pg_assert_true(strpos($layout, "lang('SMS Message To My Contact Groups')") < strpos($layout, "lang('SMS Message Delivery')"), 'the groups stay in the form column, the delivery goes to the status column');
	pg_assert_contains("(\$id ? pg_sms_campaign_status_row(lang('Status')", $source, 'a saved campaign shows its status above the send choice');
}

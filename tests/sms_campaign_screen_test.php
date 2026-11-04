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

function test_sms_campaign_screen_uses_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_sms_campaign.php');

	pg_assert_contains('pg_email_campaign_layout(', $source, 'the screen draws the shared columns and save bar');
	pg_assert_false(strpos($source, '<nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;"') !== false, 'no save bar of its own');
	pg_assert_false(strpos($source, 'md:col-6') !== false, 'no half-width rows left in the narrow status column');
}

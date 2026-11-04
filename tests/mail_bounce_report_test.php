<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the bounce record is shown (includes/fn/mail_bounce_report.php):
 * the campaign count queries, the Suppressed contact view and the export's
 * status column, the campaign delivery cell, the screens that call them and
 * the personal data export and erasure of the bounce rows. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only positive ids reach the query, each once; none leaves no query.
function test_mail_bounce_campaign_id_list()
{
	pg_assert_same('3, 7', pg_mail_bounce_campaign_id_list(array('3', 7, '3', 0, -2, 'x')));
	pg_assert_same('', pg_mail_bounce_campaign_id_list(array()));
	pg_assert_same('', pg_mail_bounce_campaign_counts_sql(array(0, 'a'), true));
	pg_assert_same('', pg_mail_bounce_campaign_bounces_sql(array()));
}

// With the retry columns a suppressed recipient (failed, no attempt) is
// counted apart and never as delivered.
function test_mail_bounce_campaign_counts_sql_with_retry_columns()
{
	pg_assert_same(
		"SELECT email_campaign_id, SUM((complete = '1') AND (failed = '0')) AS delivered, SUM((complete = '1') AND (failed = '1') AND (attempts = 0)) AS suppressed"
		. " FROM email_recipients WHERE email_campaign_id IN (4, 9) GROUP BY email_campaign_id",
		pg_mail_bounce_campaign_counts_sql(array(4, '9'), true)
	);
}

// Before the retry columns every complete recipient was delivered and none
// was suppressed.
function test_mail_bounce_campaign_counts_sql_without_retry_columns()
{
	pg_assert_same(
		"SELECT email_campaign_id, SUM(complete = '1') AS delivered, 0 AS suppressed"
		. " FROM email_recipients WHERE email_campaign_id IN (4) GROUP BY email_campaign_id",
		pg_mail_bounce_campaign_counts_sql(array(4), false)
	);
}

// Hard and soft bounces count as bounced, complaints apart; a manual block
// is no answer from a mailbox.
function test_mail_bounce_campaign_bounces_sql()
{
	pg_assert_same(
		"SELECT campaign_id, SUM(kind IN ('hard', 'soft')) AS bounced, SUM(kind = 'complaint') AS complained"
		. " FROM mail_bounces WHERE campaign_id IN (5, 2) GROUP BY campaign_id",
		pg_mail_bounce_campaign_bounces_sql(array(5, '2', '5'))
	);
}

function test_mail_bounce_contact_conditions()
{
	pg_assert_same(
		'(EXISTS (SELECT 1 FROM mail_bounces WHERE (mail_bounces.address = contacts.email_address) AND (mail_bounces.suppressed = 1)))',
		pg_mail_bounce_suppressed_sql('contacts.email_address')
	);
	pg_assert_same(
		'(SELECT mail_bounces.kind FROM mail_bounces WHERE (mail_bounces.address = contacts.email_address) AND (mail_bounces.suppressed = 1) LIMIT 1)',
		pg_mail_bounce_status_sql('contacts.email_address')
	);
}

// The delivered count always, a badge only for a count above zero.
function test_mail_bounce_campaign_cell()
{
	$cell = pg_mail_bounce_campaign_cell(array('delivered' => 1200, 'suppressed' => 3, 'bounced' => 0, 'complained' => 1));

	pg_assert_contains('delivered', $cell);
	pg_assert_contains('suppressed', $cell);
	pg_assert_contains('complained', $cell);
	pg_assert_true(strpos($cell, 'bounced') === false, 'no badge for zero bounces');
	pg_assert_same(2, substr_count($cell, 'class="badge'), 'two badges');

	pg_assert_true(strpos(pg_mail_bounce_campaign_cell(array()), 'badge') === false, 'empty counts: no badge');
}

// Only a suppressing record draws the badge and its button.
function test_mail_bounce_contact_badge()
{
	pg_assert_same('', pg_mail_bounce_contact_badge(null));
	pg_assert_same('', pg_mail_bounce_contact_badge(array('kind' => 'hard', 'suppressed' => 0, 'last_at' => 0)));

	$badge = pg_mail_bounce_contact_badge(array('kind' => 'complaint', 'suppressed' => '1', 'last_at' => 0));

	pg_assert_contains('name="submit_allow_again"', $badge);
	pg_assert_contains(h(pg_mail_bounce_kind_label('complaint')), $badge);
	pg_assert_same('', pg_mail_bounce_kind_label('later'));
}

function test_mail_bounce_suppressed_contact_view()
{
	pg_assert_true(isset(pg_contact_list_filters(3)['suppressed']), 'every role sees the view');
	pg_assert_same('suppressed', pg_contact_list_filter_name('suppressed'));

	$view = pg_contact_list_view('suppressed');

	// No database in the tests: before the table exists the view lists nobody.
	pg_assert_same('(1 = 0)', $view['condition']);
}

// The screens read the counts once per list, the card offers the button and
// lifts the block from the stored address, the export carries the status.
function test_mail_bounce_screens_call_the_helpers()
{
	// Both campaign lists build their rows in includes/fn/email_campaign_list.php.
	foreach (array('includes/fn/email_campaign_list.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_mail_bounce_campaign_counts(', $source, $file);
		pg_assert_contains('pg_mail_bounce_campaign_cell(', $source, $file);
	}

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact.php');
	pg_assert_contains('pg_mail_bounce_contact_badge(', $card);
	pg_assert_contains('pg_mail_bounce_allow_contact(', $card);

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_contacts.php');
	pg_assert_contains('pg_mail_bounce_status_sql(', $list);
	pg_assert_contains(',mail_status', $list);
}

// The bounce rows are the person's: exported after the messages, deleted
// with them.
function test_mail_bounce_privacy_export_and_erasure()
{
	$keys = array_keys(pg_privacy_export_sections());
	$at = array_search('mail_bounces', $keys, true);

	pg_assert_true($at !== false, 'an export section');
	pg_assert_same('mail', $keys[$at - 1], 'after the messages');

	$tables = pg_privacy_erase_tables();

	pg_assert_true(isset($tables['mail_bounces']), 'erased');
	pg_assert_same('delete', $tables['mail_bounces']['action']);
	pg_assert_same(array('address' => 'emails'), $tables['mail_bounces']['find']);
}

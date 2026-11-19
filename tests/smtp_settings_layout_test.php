<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * SMTP Settings on the wide edit layout: the settings cards in the form
 * column, the Sending domain card alone in the status column, the save bar
 * in its own sticky column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php';

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once PG_FUNCTIONS_DIR . '/includes/settings/registry.php';

// SMTP Settings' five cards are Site Settings › E-mail sending's, in the
// screen's order, each under its section id; the Sending domain card comes
// unwrapped inside its section.
function test_smtp_settings_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
	$at = -1;

	foreach (array('pgset-mail-transport', 'pgset-mail-system', 'pgset-mail-campaign', 'pgset-mail-dkim', 'pgset-mail-domain', 'pgset-mailchimp') as $section) {
		$here = strpos($source, '<div id="' . $section . '" class="pg-set-card">');
		pg_assert_true(($here !== false) && ($here > $at), $section . ' in its place');
		$at = $here;
	}

	pg_assert_contains('pg_mail_domain_health_card(pg_mail_domain_health(), false)', $source, 'the Sending domain card comes unwrapped');
	pg_assert_contains('id="mail_transport_card"', $source, 'the old anchor of the Sending service card stays');
	pg_assert_false(strpos($source, 'class="buttons navigation') !== false, 'no save bar of its own: the dialog and the page have theirs');
}

function test_mail_domain_health_card_column_wrapper()
{
	$health = array(
		'domain' => 'example.com',
		'selector' => 'pg',
		'rates' => array('judged' => false, 'sent' => 0, 'state' => 'ok'),
	);

	foreach (array('spf', 'dkim', 'dmarc') as $record) {
		$health[$record] = array('state' => 'ok', 'found' => '', 'copy' => '', 'reason' => '');
	}

	$wrapped = pg_mail_domain_health_card($health);
	$alone = pg_mail_domain_health_card($health, false);

	pg_assert_contains('<div class="col-12">', $wrapped, 'inside a row the card gets its column');
	pg_assert_false(strpos($alone, 'col-12') !== false, 'in a column of its own the card comes alone');
	pg_assert_contains('id="mail_domain_card"', $alone, 'the ops alert link target stays');
}

// Communication's two campaign texts sit in the Campaign card, outside the
// job's fold, and MailChimp is the category's last card; Communication holds
// neither, and its old anchors land on the new cards.
function test_mail_sending_holds_the_campaign_texts_and_mailchimp()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
	$campaign = strpos($source, 'id="pgset-mail-campaign"');
	$dkim = strpos($source, 'id="pgset-mail-dkim"');

	foreach (array('name="opt_in_label"', 'name="plain_text_email_campaign_footer"') as $field) {
		$at = strpos($source, $field);
		pg_assert_true(($at > $campaign) && ($at < $dkim), $field . ' in the Campaign card');
		pg_assert_true($at > strpos($source, 'id="email_campaign_job_row"'), $field . ' after the job\'s fold');
	}

	foreach (array('mailchimp', 'mailchimp_key', 'mailchimp_list_id', 'mailchimp_store_id', 'mailchimp_sync_days', 'mailchimp_sync_limit', 'mailchimp_automation') as $field) {
		pg_assert_contains('name="' . $field . '"', $source, $field . ' is posted by E-mail sending');
	}

	$contact = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/contact.php') . (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/contact.save.php');

	foreach (array('opt_in_label', 'plain_text_email_campaign_footer', 'mailchimp', 'pgset-campaigns') as $gone) {
		pg_assert_false(strpos($contact, $gone) !== false, $gone . ' left Communication');
	}

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('mail_sending', 'pgset-mail-campaign'), $anchors['pgset-campaigns'], 'an old Campaigns link');
	pg_assert_same(array('mail_sending', 'pgset-mailchimp'), $anchors['pgset-mailchimp'], 'an old MailChimp link');
}

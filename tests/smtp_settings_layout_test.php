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

// The category speaks only of sending: its first card says so and links
// Mailboxes for incoming e-mail, the mirror of the Mailboxes card's line.
// The cards are look A: the settings rows, look buttons and fields, no
// Bootstrap button, input group or badge.
function test_mail_sending_in_the_panel_look()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
	$transport = strpos($source, 'id="pgset-mail-transport"');
	$system = strpos($source, 'id="pgset-mail-system"');
	$line = strpos($source, "'For incoming e-mail: {var:1}'");

	pg_assert_true(($line > $transport) && ($line < $system), 'the purpose line opens the first card');
	pg_assert_contains("pg_settings_url('mailboxes')", $source, 'it links Mailboxes');

	$parts = array(
		$source,
		(string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_transport.php'),
		(string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php'),
		(string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php'),
		(string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_webhook.php'),
	);

	foreach ($parts as $part) {
		foreach (array('btn btn-', 'nav-tabs', 'input-group', 'class="badge', 'list-group', 'style="', 'class="form-label">', ' me-1" aria-hidden') as $old) {
			pg_assert_false(strpos($part, $old) !== false, $old . ' left the category');
		}
	}

	foreach (array('id="mail_transport_card"', "pg_settings_tile('megaphone', 'team')", "pg_settings_tile('envelope-paper', 'team')", 'class="pg-input font-monospace"', 'class="pg-checkbox"') as $kept) {
		pg_assert_contains($kept, $source, $kept);
	}
}

function test_mail_domain_health_rows_and_pills()
{
	pg_assert_same('<span class="pg-pill pg-tone-teal">Good</span>', pg_mail_health_badge('ok'), 'a good record');
	pg_assert_same('<span class="pg-pill pg-tone-rose">Over the limit</span>', pg_mail_health_badge('error', 'Over the limit'), 'an error with its own label');
	pg_assert_contains('pg-tone-gray', pg_mail_health_badge('nonsense'), 'an unknown state');

	$health = array(
		'domain' => 'example.com',
		'selector' => 'pg',
		'rates' => array('judged' => false, 'sent' => 0, 'state' => 'ok'),
	);

	foreach (array('spf', 'dkim', 'dmarc') as $record) {
		$health[$record] = array('state' => 'error', 'found' => '', 'copy' => 'v=spf1 -all', 'reason' => '');
	}

	$card = pg_mail_domain_health_card($health, false);

	pg_assert_same(4, substr_count($card, '<div class="pg-list-row">'), 'three records and the rates, each a list row');
	pg_assert_contains('class="card-header bg-reset border-0 pg-title-section"', $card, 'the settings card title');
	pg_assert_contains('class="pg-input pg-input-sm font-monospace" value="v=spf1 -all" readonly', $card, 'the record to publish');
	pg_assert_contains('data-pg-copy-record="mail_domain_spf"', $card, 'its Copy button');
}

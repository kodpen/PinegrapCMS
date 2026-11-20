<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The DKIM card of Site Settings › E-mail sending (includes/fn/mail_settings.php):
 * "Save and test" passes only when a published TXT record carries the whole
 * generated key, and the record the card shows is named after the saved
 * selector and domain.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php';

function mail_settings_dkim_stored()
{
	return 'pinegrap._domainkey.example.com IN TXT "v=DKIM1; k=rsa; p=' . str_repeat('MIIBIjANBgkqhkiG9w0B', 20) . 'IDAQAB"';
}

function mail_settings_dkim_key()
{
	return str_repeat('MIIBIjANBgkqhkiG9w0B', 20) . 'IDAQAB';
}

function test_mail_settings_dkim_test_needs_the_whole_key()
{
	$key = mail_settings_dkim_key();

	pg_assert_true(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => 'v=DKIM1; k=rsa; p=' . $key))), 'the published record of the generated key passes');
	// A DNS panel that cuts a long value at 255 characters publishes the
	// record's start; it is no key and mailbox providers fail the signature.
	pg_assert_false(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => substr('v=DKIM1; k=rsa; p=' . $key, 0, 255)))), 'a record cut at 255 characters fails');
	pg_assert_false(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => 'v=DKIM1'))), 'a record with no key fails');
	pg_assert_false(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => 'v=DKIM1; k=rsa; p='))), 'an empty key fails');
}

function test_mail_settings_dkim_test_reads_each_record_and_its_spacing()
{
	$key = mail_settings_dkim_key();

	// Two records under the name: the right one passes, as a DNS lookup does.
	pg_assert_true(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(
		array('txt' => 'v=DKIM1; k=rsa; p=OLDKEY'),
		array('txt' => 'v=DKIM1; k=rsa; p=' . $key),
	)), 'one matching record among two passes');
	// Tags without spaces, and a key split by a space a DNS panel added.
	pg_assert_true(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => 'v=DKIM1;k=rsa;p=' . substr($key, 0, 200) . ' ' . substr($key, 200)))), 'spacing is not part of the key');
	pg_assert_false(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array(array('txt' => 'v=DKIM1; k=rsa; p=OTHER' . $key))), 'another key fails');
	pg_assert_false(pg_mail_settings_dkim_matches(mail_settings_dkim_stored(), array()), 'no record fails');
	pg_assert_false(pg_mail_settings_dkim_matches('', array(array('txt' => 'v=DKIM1; k=rsa; p=' . $key))), 'no generated key fails');
}

function test_mail_settings_dkim_card_names_the_record_after_the_saved_selector()
{
	$key = mail_settings_dkim_key();

	// The key was generated for pinegrap._domainkey.example.com; mail is
	// signed with the selector and domain saved since, so the record to
	// publish is named after them.
	pg_assert_same('mail2026._domainkey.example.org IN TXT "v=DKIM1; k=rsa; p=' . $key . '"', pg_mail_settings_dkim_shown_record(mail_settings_dkim_stored(), 'mail2026', 'example.org', 'info@example.net'), 'the saved selector and domain name the record');
	// Blank boxes sign with the selector "pinegrap" and the domain of the
	// site's e-mail address (email()).
	pg_assert_same('pinegrap._domainkey.example.net IN TXT "v=DKIM1; k=rsa; p=' . $key . '"', pg_mail_settings_dkim_shown_record(mail_settings_dkim_stored(), '', '', 'info@example.net'), 'blank boxes name what email() signs with');
	pg_assert_same('', pg_mail_settings_dkim_shown_record('', 'mail2026', 'example.org', 'info@example.net'), 'no key, no record');
}

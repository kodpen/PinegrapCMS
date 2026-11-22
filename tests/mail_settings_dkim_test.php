<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The DKIM row of Site Settings › E-mail sending › Sending domain
 * (includes/fn/mail_settings.php): the record it offers is named after the
 * saved selector and domain.
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

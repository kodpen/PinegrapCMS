<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designed welcome and password reset e-mails
 * (includes/fn/widgets_account.php): which widget setting answers, when a
 * page can be sent, the subject line, the Account E-mail widget's values,
 * the reset link check, the reset address match, and the 'secret' mail
 * property that keeps a reset link out of the failure log
 * (includes/fn/mail.php). forgot_password.php is checked by its source: the
 * reset e-mail goes to the stored address and is marked secret.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only letter case may differ; an accent-folded look-alike does not match.
function test_account_email_reset_address_matches()
{
	pg_assert_true(pg_password_reset_address_matches('jane@example.com', 'jane@example.com'), 'same');
	pg_assert_true(pg_password_reset_address_matches('Jane@Example.COM', 'jane@example.com'), 'case only');
	pg_assert_true(pg_password_reset_address_matches(' jane@example.com ', 'jane@example.com'), 'spaces');
	pg_assert_false(pg_password_reset_address_matches('jane@exámple.com', 'jane@example.com'), 'accent');
	pg_assert_false(pg_password_reset_address_matches('jäne@example.com', 'jane@example.com'), 'accent in the name');
	pg_assert_false(pg_password_reset_address_matches('', 'jane@example.com'), 'empty typed');
	pg_assert_false(pg_password_reset_address_matches('jane@example.com', ''), 'empty stored');
	pg_assert_false(pg_password_reset_address_matches(array('jane@example.com'), 'jane@example.com'), 'not a string');
}

function test_account_email_keys()
{
	pg_assert_same(array('welcome_email_page_id', 'welcome_email_subject'), pg_account_email_keys('welcome'));
	pg_assert_same(array('reset_email_page_id', 'reset_email_subject'), pg_account_email_keys('reset'));
}

// The posted widget decides, even with no page; otherwise the first
// candidate naming a page.
function test_account_email_pick()
{
	$none = array('widget_id' => 0, 'page_id' => 0, 'subject' => '');
	pg_assert_same($none, pg_account_email_pick(null, array(), 'welcome'), 'nothing');

	$posted = array('id' => 7, 'cfg' => array('regionType' => 'registration', 'welcome_email_page_id' => 12, 'welcome_email_subject' => '  Hi there  '));
	pg_assert_same(array('widget_id' => 7, 'page_id' => 12, 'subject' => 'Hi there'), pg_account_email_pick($posted, array(), 'welcome'), 'posted');

	$posted_none = array('id' => 7, 'cfg' => array('regionType' => 'registration', 'welcome_email_page_id' => 0));
	$other = array('id' => 9, 'cfg' => array('regionType' => 'registration', 'welcome_email_page_id' => 30));
	pg_assert_same(array('widget_id' => 7, 'page_id' => 0, 'subject' => ''), pg_account_email_pick($posted_none, array($other), 'welcome'), 'posted none is respected');

	$blank = array('id' => 3, 'cfg' => array('regionType' => 'registration'));
	$tab   = array('id' => 4, 'cfg' => array('regionType' => 'registration', 'welcome_email_page_id' => 'tab:email_welcome'));
	pg_assert_same(array('widget_id' => 9, 'page_id' => 30, 'subject' => ''), pg_account_email_pick(null, array($blank, $tab, $other), 'welcome'), 'first candidate naming a page');

	$reset = array('id' => 5, 'cfg' => array('regionType' => 'forgot_password', 'reset_email_page_id' => '41', 'reset_email_subject' => 'Reset'));
	pg_assert_same(array('widget_id' => 5, 'page_id' => 41, 'subject' => 'Reset'), pg_account_email_pick(null, array($reset), 'reset'), 'reset keys');
	pg_assert_same($none, pg_account_email_pick(null, array($reset), 'welcome'), 'reset keys do not answer a welcome');
}

function test_account_email_page_usable()
{
	pg_assert_true(pg_account_email_page_usable(array('page_id' => 12, 'page_folder' => 3), array(90), 91), 'good');
	pg_assert_false(pg_account_email_page_usable(null, array(90), 91), 'missing');
	pg_assert_false(pg_account_email_page_usable(array('page_id' => 0, 'page_folder' => 3), array(), 0), 'no id');
	pg_assert_false(pg_account_email_page_usable(array('page_id' => 12, 'page_folder' => '90'), array(90), 91), 'binned');
	pg_assert_false(pg_account_email_page_usable(array('page_id' => 12, 'page_folder' => 91), array(90), 91), 'draft');
	pg_assert_true(pg_account_email_page_usable(array('page_id' => 12, 'page_folder' => 0), array(), 0), 'no drafts folder');
}

function test_account_email_subject()
{
	pg_assert_same('Welcome aboard', pg_account_email_subject('  Welcome aboard ', 'Fallback'));
	pg_assert_same('Fallback', pg_account_email_subject('', 'Fallback'), 'empty');
	pg_assert_same('Fallback', pg_account_email_subject(null, 'Fallback'), 'null');
	pg_assert_same('Fallback', pg_account_email_subject('^^__user_name^^', 'Fallback'), 'token only');
	pg_assert_same('Hello', pg_account_email_subject('Hello ^^first_name^^', 'Fallback'), 'token dropped');
	pg_assert_same('One Bcc: x@example.com two', pg_account_email_subject("One\r\nBcc: x@example.com\ntwo", 'Fallback'), 'line breaks become spaces');
}

function test_account_email_values()
{
	$member = array('user_username' => 'jcooper', 'user_email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Cooper');
	$links  = array('__login_url' => 'https://example.com/sign-in', '__my_account_url' => 'https://example.com/account');

	$w = pg_account_email_values('welcome', $member, $links, 'https://example.com/set?k=abc', 'ACME');
	pg_assert_same(array('is_welcome' => true, 'is_password_reset' => false), $w['flags'], 'welcome flags');
	pg_assert_same('Jane Cooper', $w['tokens']['__user_name']);
	pg_assert_same('Jane', $w['tokens']['__user_first_name']);
	pg_assert_same('jane@example.com', $w['tokens']['__user_email']);
	pg_assert_same('jcooper', $w['tokens']['__username']);
	pg_assert_same('ACME', $w['tokens']['__site_name']);
	pg_assert_same('https://example.com/account', $w['tokens']['__my_account_url']);
	pg_assert_same('', $w['tokens']['__set_password_url'], 'no reset link in a welcome');

	$r = pg_account_email_values('password_reset', $member, $links, 'https://example.com/set?k=abc', 'ACME');
	pg_assert_same(array('is_welcome' => false, 'is_password_reset' => true), $r['flags'], 'reset flags');
	pg_assert_same('https://example.com/set?k=abc', $r['tokens']['__set_password_url']);

	$p = pg_account_email_values('preview', $member, $links, '#', 'ACME');
	pg_assert_same(array('is_welcome' => true, 'is_password_reset' => true), $p['flags'], 'preview shows both parts');

	$only_user = pg_account_email_values('welcome', array('user_username' => 'jcooper'), array(), '', '');
	pg_assert_same('jcooper', $only_user['tokens']['__user_name'], 'name falls back to the user name');
	pg_assert_same('jcooper', $only_user['tokens']['__user_first_name'], 'first name falls back to the user name');
	$last_only = pg_account_email_values('welcome', array('user_username' => 'jcooper', 'last_name' => 'Cooper'), array(), '', '');
	pg_assert_same('Cooper', $last_only['tokens']['__user_first_name'], 'first name falls back to the full name');
	pg_assert_same('', $last_only['tokens']['__login_url'], 'no link given');

	foreach (array('', 'email', null) as $kind) {
		$x = pg_account_email_values($kind, $member, $links, 'https://example.com/set?k=abc', 'ACME');
		pg_assert_same(array('is_welcome' => false, 'is_password_reset' => false), $x['flags'], 'no context: no flags');
		pg_assert_same(array(''), array_values(array_unique($x['tokens'])), 'no context: every token empty');
	}
}

function test_account_email_has_link()
{
	$token = 'Ab12Cd34Ef56Gh78';
	pg_assert_true(pg_account_email_has_link('<a href="https://example.com/set-pass?k=' . $token . '">Set</a>', $token), 'present');
	pg_assert_true(pg_account_email_has_link('<a href="https://example.com/tr/sifre-belirle?k=' . $token . '">Set</a>', $token), 'language prefix');
	pg_assert_true(pg_account_email_has_link('<a href="https://example.com/x?a=1&amp;k=' . $token . '">Set</a>', $token), 'second parameter');
	pg_assert_false(pg_account_email_has_link('<a href="https://example.com/set-pass">Set</a>', $token), 'absent');
	pg_assert_false(pg_account_email_has_link('<p>Hello</p>', ''), 'empty token');
	pg_assert_false(pg_account_email_has_link('', $token), 'empty body');
	pg_assert_false(pg_account_email_has_link('<a href="/s?k=' . $token . 'X">x</a>', $token), 'longer token');
	pg_assert_false(pg_account_email_has_link('<p>k=' . $token . '</p>', $token), 'not a query parameter');
}

function test_account_email_mail_failure_body_shown()
{
	pg_assert_true(pg_mail_failure_body_shown(array('body' => 'x')), 'plain text by default');
	pg_assert_true(pg_mail_failure_body_shown(array('format' => 'plain_text')), 'plain text');
	pg_assert_false(pg_mail_failure_body_shown(array('format' => 'html')), 'html');
	pg_assert_false(pg_mail_failure_body_shown(array('secret' => true)), 'secret plain text');
	pg_assert_false(pg_mail_failure_body_shown(array('format' => 'html', 'secret' => true)), 'secret html');
	pg_assert_false(pg_mail_failure_body_shown(null), 'no properties');
}

// email() asks the helper, not the format alone.
function test_account_email_mail_failure_uses_helper()
{
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	pg_assert_true(strpos($mail, 'if (pg_mail_failure_body_shown($properties)) {') !== false, 'failure log condition');
	pg_assert_false(strpos($mail, "if (\$format !== 'html') {\n            \$log .= lang('Body')") !== false, 'old condition gone');
}

// The reset e-mail - built-in and designed - goes to the stored address and
// is marked secret; a typed address that is not the stored one is treated
// as unknown.
function test_account_email_forgot_password_source()
{
	$fp = (string) file_get_contents(PG_FUNCTIONS_DIR . '/forgot_password.php');
	pg_assert_same(0, preg_match("/'to'\\s*=>\\s*\\\$email\\b/", $fp), 'never the typed address');
	pg_assert_same(1, preg_match_all("/'to'\\s*=>\\s*\\\$user\\['email'\\]/", $fp), 'built-in e-mail to the stored address');
	pg_assert_same(1, preg_match_all("/'secret'\\s*=>\\s*true/", $fp), 'built-in e-mail is secret');
	pg_assert_true(strpos($fp, "pg_account_email_send_reset(\$user['id'], \$user['email'],") !== false, 'designed e-mail to the stored address');
	pg_assert_true(strpos($fp, "user_email AS email") !== false, 'lookup reads the stored address');
	pg_assert_true(strpos($fp, "!pg_password_reset_address_matches(\$email, \$user['email'])") !== false, 'mismatch is unknown');

	// Both answers pay for finding and drawing the designed page: the setting
	// is found before the lookup, the unknown answer draws the page unsent.
	$setting = strpos($fp, "pg_account_email_setting('forgot_password', 'reset', \$pg_widget_id)");
	$lookup  = strpos($fp, 'user_email AS email');
	pg_assert_true($setting !== false && $lookup !== false && $setting < $lookup, 'setting found before the lookup');
	$unknown = strpos($fp, "!pg_password_reset_address_matches(");
	$unsent  = strpos($fp, 'pg_account_email_reset_unsent($pg_reset_email);');
	$notice  = ($unknown === false) ? false : strpos($fp, '$form->add_notice(', $unknown);
	pg_assert_true($unsent !== false && $unknown !== false && $notice !== false && $unknown < $unsent && $unsent < $notice, 'unknown answer draws the page');

	$acct = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	$start = strpos($acct, 'function pg_account_email_send_reset(');
	$end = ($start === false) ? false : strpos($acct, "\n}", $start);
	pg_assert_true($start !== false && $end !== false, 'designed sender found');
	$body = ($start !== false && $end !== false) ? substr($acct, $start, $end - $start) : '';
	pg_assert_same(1, preg_match_all("/'secret'\\s*=>\\s*true/", $body), 'designed e-mail is secret');
	pg_assert_same(1, preg_match_all("/'to'\\s*=>\\s*\\(string\\)\\\$to/", $body), 'designed e-mail to the address it is given');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Staff e-mails a visitor's request sends (comment moderation, affiliate
 * approval, firewall ban notice, gift card shortfall) are built in the
 * software language through pg_staff_lang(), not in the page's language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The part of a file's source between two markers, '' when either is missing.
 *
 * @param string $file
 * @param string $from
 * @param string $to
 * @return string
 */
function staff_mail_language_part($file, $from, $to)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, $from);
	$end = ($start === false) ? false : strpos($source, $to, $start);

	return (($start === false) || ($end === false)) ? '' : substr($source, $start, $end - $start);
}

// The moderator e-mail: subject and body built under the hold, sent after it.
function test_staff_mail_comment_moderator()
{
	$part = staff_mail_language_part('add_comment.php', 'if (count($administrator_email_addresses) > 0) {', "'to' => \$administrator_email_addresses");

	pg_assert_true($part !== '', 'the moderator e-mail is found');
	pg_assert_contains('pg_staff_lang(function ()', $part, 'built in the software language');
	pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, "A {var:1} has been added and published."), 'the subject inside the builder');
	pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, "lang('Attachment')"), 'the attachment line inside the builder');
}

// The affiliate approval e-mail.
function test_staff_mail_affiliate_approval()
{
	$part = staff_mail_language_part('affiliate_sign_up_form.php', 'else automatic approval is not on', "remove_form('affiliate_sign_up_form')");

	pg_assert_true($part !== '', 'the approval e-mail is found');
	pg_assert_contains('pg_staff_lang(function ()', $part, 'built in the software language');
	pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, "lang('Affiliate Approval Needed')"), 'the subject inside the builder');
}

// The firewall ban notice, sent from whatever request comes next.
function test_staff_mail_waf_ban_notice()
{
	$part = staff_mail_language_part('includes/fn/waf_notice.php', 'foreach ($plan as $notice) {', 'try {');

	pg_assert_true($part !== '', 'the notice loop is found');
	pg_assert_contains('pg_staff_lang(function ()', $part, 'built in the software language');
	pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, "the firewall banned {var:2}"), 'the subject inside the builder');
	pg_assert_true(strpos($part, 'pg_staff_lang(') < strpos($part, 'pg_waf_ban_notice_email_html('), 'the body inside the builder');
}

// The gift card shortfall e-mail: translated, with a link to the order.
function test_staff_mail_gift_card_shortfall()
{
	$part = staff_mail_language_part('includes/fn/order_completion.php', 'function pg_order_complete_gift_cards(', 'function pg_order_gift_card_error_message(');

	pg_assert_true($part !== '', 'the shortfall e-mail is found');
	pg_assert_same(false, strpos($part, "'subject' => 'Gift card error'"), 'no bare English subject');
	pg_assert_contains("'Gift card error'", $part, 'the subject still says it');
	pg_assert_contains("/view_order.php?id=' . (int) \$order_id", $part, 'a link to the order');
	pg_assert_contains('URL_SCHEME . HOSTNAME_SETTING', $part, 'on the site address');
	pg_assert_contains('log_activity($error_message', $part, 'the log line has no link');
}

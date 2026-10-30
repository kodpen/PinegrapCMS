<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail campaign and contact wording: the campaign profile's schedule label
 * built from whole keys with the time last, and the opt-in texts in the
 * panel's "ileti izni" wording.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_schedule.php');

function test_email_campaign_schedule_label_immediately_with_time()
{
	$label = pg_email_campaign_profile_schedule_label(0, 'days', 'after', 'action', '10:00');

	pg_assert_same(lang(array('string' => 'Schedule: {var:1} {var:2} {var:3}, at {var:4}', 'vars' => array(lang('immediately'), lang('after'), lang('from action'), '10:00'))), $label);
	pg_assert_same('10:00', substr($label, -5), 'the time is the last piece');
}

function test_email_campaign_schedule_label_one_day_with_time()
{
	$label = pg_email_campaign_profile_schedule_label(1, 'days', 'after', 'action', '10:00');

	pg_assert_same(lang(array('string' => 'Schedule: {var:1} {var:2} {var:3}, at {var:4}', 'vars' => array('1 ' . lang('day'), lang('after'), lang('from action'), '10:00'))), $label);
	pg_assert_same('10:00', substr($label, -5), 'the time is the last piece');
	pg_assert_false(strpos($label, '(') !== false, 'no parenthesis');
	pg_assert_false(strpos($label, '  ') !== false, 'no double space');
}

function test_email_campaign_schedule_label_hours_before_event_without_time()
{
	$label = pg_email_campaign_profile_schedule_label(2, 'hours', 'before', 'calendar_event_start_time', '');

	pg_assert_same(lang(array('string' => 'Schedule: {var:1} {var:2} {var:3}', 'vars' => array('2 ' . lang('hours'), lang('before'), lang('from calendar event start time')))), $label);
	pg_assert_false(strpos($label, '(') !== false, 'no parenthesis');
	pg_assert_false(strpos($label, '  ') !== false, 'no double space');
	pg_assert_same($label, trim($label), 'no edge space');
}

function test_email_campaign_schedule_turkish_values()
{
	$map = pg_test_language_map();

	pg_assert_same('Zamanlama: {var:3} {var:1} {var:2}', $map['Schedule: {var:1} {var:2} {var:3}'] ?? null);
	pg_assert_same('Zamanlama: {var:3} {var:1} {var:2}, saat {var:4}', $map['Schedule: {var:1} {var:2} {var:3}, at {var:4}'] ?? null);
}

function test_email_campaign_wording_opt_in_values()
{
	$map = pg_test_language_map();
	$expected = array(
		'Also send message to the following e-mail address' => 'Mesajı ayrıca şu e-posta adresine de gönder',
		"send email to opted-in contacts only. Example: 'We have an offer for you'" => "yalnızca ileti izni veren kişilere e-posta gönderir. Örnek: 'Size bir teklifimiz var'",
		"send email, regardless of opt-in. Example: 'Your order has been shipped'" => "ileti izninden bağımsız olarak e-posta gönderir. Örnek: 'Siparişiniz gönderildi'",
		'WARNING: The selected {var:1} will be opted-in.' => 'UYARI: Seçilen {var:1} için ileti izni verilecek.',
		'WARNING: The selected {var:1} will be opted-out.' => 'UYARI: Seçilen {var:1} için ileti izni kaldırılacak.',
		'{var:1} contact(s) were opted-in' => '{var:1} kişiye ileti izni verildi.',
		'{var:1} contact(s) were opted-out' => '{var:1} kişinin ileti izni kaldırıldı.',
		'Opt-Out' => 'İleti İzni Yok',
		'Opt-Out Selected' => 'Seçilenlerin İleti İznini Kaldır',
	);

	foreach ($expected as $key => $value) {
		$actual = $map[$key] ?? '';

		pg_assert_same($value, $actual, $key);
		pg_assert_false((bool) preg_match('/Opt-in|Opt-Out|opted|adresinede/i', $actual), $key . ' has no English opt-in word');
	}
}

function test_email_campaign_profiles_screen_uses_schedule_label()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_email_campaign_profiles.php');

	pg_assert_true(strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_schedule.php')") !== false, 'module loaded');
	pg_assert_true(strpos($source, 'h(pg_email_campaign_profile_schedule_label(') !== false, 'label printed through h()');
	pg_assert_false(strpos($source, 'Schedule: {var:1}{var:2}{var:3}') !== false, 'old glued key gone');
}

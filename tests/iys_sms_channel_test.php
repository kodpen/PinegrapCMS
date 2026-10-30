<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the İYS MESAJ channel (includes/iys/client.php): the schema step
 * of contacts.iys_sms_status, the consent an SMS consent_log row becomes and
 * the number it is for, how İYS's SMS changes are read and matched to the
 * contacts' numbers, and when a pulled refusal is recorded. İYS is not called
 * and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

function iys_sms_test_answer($status, $data)
{
	return pg_iys_answer(array('status' => $status, 'body' => (string) json_encode($data), 'error' => ''));
}

function iys_sms_test_function_source($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');
	pg_assert_true($start !== false, $function . ' exists');
	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

// The column is added by the open version's step with the idempotent helpers,
// in the shape of iys_email_status, and the step is called.
function test_iys_sms_migration_step()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_contact_iys_sms_status();', $file, 'the step is called');

	$step = iys_sms_test_function_source('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_contact_iys_sms_status');
	pg_assert_contains("'iys_sms_status' => \"VARCHAR(16) NOT NULL DEFAULT ''\"", $step);
	pg_assert_contains("install_add_column('contacts', \$column, \$definition)", $step);
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no bare ALTER');
	pg_assert_false(stripos($step, 'db(') !== false, 'no direct query');
}

// İYS takes a Turkish mobile number as +905xxxxxxxxx; nothing else.
function test_iys_sms_recipient()
{
	pg_assert_same('+905321234567', pg_iys_sms_recipient('0532 123 45 67'));
	pg_assert_same('+905321234567', pg_iys_sms_recipient('+90 (532) 123-45-67'));
	pg_assert_same('+905321234567', pg_iys_sms_recipient('905321234567'));
	pg_assert_same('', pg_iys_sms_recipient('0212 123 45 67'), 'landline');
	pg_assert_same('', pg_iys_sms_recipient('+44 7700 900123'), 'abroad');
	pg_assert_same('', pg_iys_sms_recipient(''), 'none');
	pg_assert_same('', pg_iys_sms_recipient(null), 'null');
}

// An SMS answer becomes a MESAJ consent for the number; the e-mail body is
// unchanged.
function test_iys_sms_consent_body()
{
	pg_assert_same(array(
		'consentDate'   => '2026-10-26 13:00:00',
		'source'        => 'HS_WEB',
		'recipient'     => '+905321234567',
		'recipientType' => 'BIREYSEL',
		'status'        => 'ONAY',
		'type'          => 'MESAJ',
	), pg_iys_sms_consent_body('0532 123 45 67', 'granted', 1793008800));
	pg_assert_same('RET', pg_iys_sms_consent_body('05321234567', 'refused', 1793008800)['status']);
	pg_assert_same(null, pg_iys_sms_consent_body('0212 123 45 67', 'granted', 1793008800), 'landline');
	pg_assert_same(null, pg_iys_sms_consent_body('', 'granted', 1793008800), 'no number');
	pg_assert_same(null, pg_iys_sms_consent_body('05321234567', 'pending', 1793008800), 'pending');
	pg_assert_same(null, pg_iys_sms_consent_body('05321234567', 'granted', 0), 'no date');

	pg_assert_same('EPOSTA', pg_iys_consent_body('ayse@example.com', 'granted', 1793008800)['type'], 'the e-mail body stays');
}

// The number a row is for: the order's billing phone for a checkout answer
// (the box asked about that number), else the contact's mobile number.
function test_iys_sms_push_number()
{
	pg_assert_same('0532 123 45 67', pg_iys_push_number(array('source' => 'preferences', 'phone' => '0532 123 45 67', 'order_phone' => '0533 000 00 00')));
	pg_assert_same('0533 000 00 00', pg_iys_push_number(array('source' => 'checkout', 'phone' => '0532 123 45 67', 'order_phone' => '0533 000 00 00')));
	pg_assert_same('0532 123 45 67', pg_iys_push_number(array('source' => 'checkout', 'phone' => '0532 123 45 67', 'order_phone' => '0212 000 00 00')), 'a billing landline falls back');
	pg_assert_same('0532 123 45 67', pg_iys_push_number(array('source' => 'checkout', 'phone' => '0532 123 45 67', 'order_phone' => null)), 'no order');
	pg_assert_same('', pg_iys_push_number(array('source' => 'registration', 'phone' => null, 'order_phone' => null)));
}

// The push reads the SMS rows only when the column exists; the e-mail rows
// as before.
function test_iys_sms_push_rows_query()
{
	$rows = iys_sms_test_function_source('includes/iys/client.php', 'pg_iys_push_rows');
	pg_assert_contains("consent_log.channel IN ('email', 'sms')", $rows);
	pg_assert_contains("(consent_log.source <> 'iys')", $rows);
	pg_assert_contains("(consent_log.purpose = 'marketing')", $rows);

	$push = iys_sms_test_function_source('includes/iys/client.php', 'pg_iys_push');
	pg_assert_contains('iys_sms_status', $push);
	pg_assert_true(strpos($push, "if (!\$app['live'])") < strpos($push, 'pg_iys_token('), 'a dry run returns before any call');

	$run = iys_sms_test_function_source('includes/iys/client.php', 'pg_iys_run');
	pg_assert_true(strpos($run, "if (!\$app['live'])") < strpos($run, 'pg_iys_pull('), 'a dry run pulls nothing');
}

// Only MESAJ changes for a Turkish mobile number with a known status are
// read; the e-mail reader leaves them out as before.
function test_iys_sms_changes_parse()
{
	$answer = iys_sms_test_answer(200, array(
		'after' => 'cursor-2',
		'list'  => array(
			array('type' => 'MESAJ', 'status' => 'RET', 'recipient' => '+905321234567', 'consentDate' => '2026-10-26 13:00:00'),
			array('type' => 'MESAJ', 'status' => 'ONAY', 'recipient' => '+905339876543'),
			array('type' => 'EPOSTA', 'status' => 'RET', 'recipient' => 'ayse@example.com'),
			array('type' => 'ARAMA', 'status' => 'RET', 'recipient' => '+905321234567'),
			array('type' => 'MESAJ', 'status' => 'BEKLIYOR', 'recipient' => '+905321234567'),
			array('type' => 'MESAJ', 'status' => 'RET', 'recipient' => '+442071234567'),
			array('type' => 'MESAJ', 'status' => 'RET', 'recipient' => array('x')),
			'junk',
		),
	));

	pg_assert_same(array(
		array('number' => '905321234567', 'status' => 'refused', 'at' => 1793008800),
		array('number' => '905339876543', 'status' => 'approved', 'at' => 0),
	), pg_iys_sms_changes_parse($answer));
	pg_assert_same(array(array('email' => 'ayse@example.com', 'status' => 'refused', 'at' => 0)), pg_iys_changes_parse($answer)['changes'], 'the e-mail reader is unchanged');
	pg_assert_same(array(), pg_iys_sms_changes_parse(iys_sms_test_answer(500, array())));
}

// The stored forms a number may take once separators are dropped, so one
// query finds the contacts; pg_sms_number() confirms each.
function test_iys_sms_number_variants()
{
	$variants = pg_iys_number_variants('905321234567');

	foreach (array('5321234567', '05321234567', '905321234567', '+905321234567', '00905321234567', '+9005321234567') as $form) {
		pg_assert_true(in_array($form, $variants, true), $form);
		pg_assert_same('905321234567', pg_sms_number($form), $form . ' reads back');
	}

	pg_assert_same(array(), pg_iys_number_variants('447700900123'), 'only Turkish mobiles');
	pg_assert_same(array(), pg_iys_number_variants(''), 'none');
}

// A pulled refusal is recorded only for a contact whose last SMS answer here
// is a yes, so sending stops; an approval never records a yes.
function test_iys_sms_refusal_needed()
{
	pg_assert_true(pg_iys_sms_refusal_needed(array('sms:marketing' => array('status' => 'granted'))));
	pg_assert_false(pg_iys_sms_refusal_needed(array('sms:marketing' => array('status' => 'refused'))), 'already refused');
	pg_assert_false(pg_iys_sms_refusal_needed(array()), 'never asked');
	pg_assert_false(pg_iys_sms_refusal_needed(array('email:marketing' => array('status' => 'granted'))), 'the e-mail answer is not the SMS one');

	$apply = iys_sms_test_function_source('includes/iys/client.php', 'pg_iys_apply_sms_changes');
	pg_assert_contains("'channel'    => 'sms'", $apply);
	pg_assert_contains("'source'     => 'iys'", $apply);
	pg_assert_contains("\$change['status'] === 'refused'", $apply);
}

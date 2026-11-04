<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-mails of a submitted custom form
 * (includes/fn/form_mails.php): who gets them at submit and when the
 * record's fee is paid, the administrators of the chosen options and the
 * sender's address read back from a stored record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_mails_plan()
{
	$settings = array(
		'submitter_email'                       => '1',
		'administrator_email'                   => '1',
		'administrator_email_to_email_address'  => 'office@example.com',
		'administrator_email_bcc_email_address' => '',
	);
	$mail = array(
		'submitter_email_address'       => 'visitor@example.com',
		'watcher_email_address'         => 'watcher@example.com',
		'administrator_email_addresses' => array('sales@example.com', 'office@example.com'),
		'fee_owed'                      => false,
	);

	pg_assert_same(array(
		'submitter'          => array('visitor@example.com', 'watcher@example.com'),
		'administrator'      => array('sales@example.com', 'office@example.com'),
		'administrator_send' => true,
	), pg_cf_form_mails_plan($settings, $mail), 'no fee: both e-mails at submit, the To address once');

	pg_assert_same(array('submitter' => array(), 'administrator' => array(), 'administrator_send' => false),
		pg_cf_form_mails_plan($settings, array('fee_owed' => true) + $mail),
		'a record that owes a fee sends nothing until it is paid');

	$plan = pg_cf_form_mails_plan(array('submitter_email' => '0', 'administrator_email' => '1',
		'administrator_email_to_email_address' => '', 'administrator_email_bcc_email_address' => 'archive@example.com'),
		array('submitter_email_address' => 'visitor@example.com', 'administrator_email_addresses' => array()));
	pg_assert_same(array(), $plan['submitter'], 'the sender copy switched off');
	pg_assert_true($plan['administrator_send'] && ($plan['administrator'] === array()), 'a Bcc alone sends the administrator e-mail');

	$plan = pg_cf_form_mails_plan(array('submitter_email' => '1', 'administrator_email' => '1',
		'administrator_email_to_email_address' => '', 'administrator_email_bcc_email_address' => ''),
		array('submitter_email_address' => '', 'watcher_email_address' => ''));
	pg_assert_same(array(), $plan['submitter'], 'no address: no sender copy');
	pg_assert_true(!$plan['administrator_send'], 'no To, Bcc or option address: no administrator e-mail');
}

function test_form_mails_option_admin_addresses()
{
	pg_assert_same(array('a@example.com', 'b@example.com', 'c@example.com'),
		pg_cf_option_admin_addresses(array('a@example.com, b@example.com', 'b@example.com', ' c@example.com ')),
		'comma lists split and trimmed, each address once');
	pg_assert_same(array(), pg_cf_option_admin_addresses(array()), 'no option chosen');
}

function test_form_mails_record_submitter_address()
{
	pg_assert_same('contact@example.com', pg_cf_record_submitter_address('contact@example.com', 'user@example.com', array(1 => 'one@example.com')),
		'the field connected to the contact first');
	pg_assert_same('user@example.com', pg_cf_record_submitter_address('', 'user@example.com', array(1 => 'one@example.com')),
		'then the signed-in submitter');
	pg_assert_same('one@example.com', pg_cf_record_submitter_address('', '', array(7 => 'one@example.com')),
		'then the only e-mail address field');
	pg_assert_same('', pg_cf_record_submitter_address('', '', array(1 => 'a@example.com', 2 => 'b@example.com')),
		'two e-mail address fields: no sender address');
}

function test_form_mails_wiring()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');
	pg_assert_contains('pg_cf_form_mails_send((int) $_POST[\'page_id\'], (int) $form_id,', $form, 'the submit sends through the function');
	pg_assert_true(strpos($form, "'format' => \$submitter_email_format") === false, 'no second copy of the mail block');

	$payments = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/form_payments.php');
	pg_assert_contains("pg_announce('form.paid', pg_form_paid_event_payload(\$row));\n\n        pg_cf_form_mails_send_for_record(\$row);", $payments,
		'the e-mails go once, when the record is marked paid');

	$ecommerce = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_contains('pg_form_payment_mark_paid($order_id);', $ecommerce, 'an offline order recorded as paid marks its record');
}

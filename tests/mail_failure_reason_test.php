<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_mail_failure_sentence() (includes/fn/mail_queue.php): the
 * sentence a screen shows when email() returned false, with the mail
 * server's reason when email() left one, and a scan that the SMTP test and
 * the ERP document e-mails use it instead of pointing at the log.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// With a reason stored, the sentence carries it.
function test_mail_failure_sentence_gives_the_reason()
{
	pg_mail_last_error('SMTP Error: Could not authenticate.');

	pg_assert_same('The e-mail could not be sent: SMTP Error: Could not authenticate.', pg_mail_failure_sentence('Fallback.'));
	pg_assert_same('The test e-mail could not be sent: SMTP Error: Could not authenticate.', pg_mail_failure_sentence('Fallback.', true));

	pg_mail_last_error('');
}

// Without a reason (or only white space) the caller's sentence stays.
function test_mail_failure_sentence_falls_back()
{
	pg_mail_last_error('');
	pg_assert_same('Fallback.', pg_mail_failure_sentence('Fallback.'));

	pg_mail_last_error("  \n ");
	pg_assert_same('Fallback.', pg_mail_failure_sentence('Fallback.', true));

	pg_mail_last_error('');
}

// The reason is returned raw: the screens escape it where they print it.
function test_mail_failure_sentence_is_not_escaped()
{
	pg_mail_last_error('Bad <address> & co');
	pg_assert_same('The e-mail could not be sent: Bad <address> & co', pg_mail_failure_sentence('Fallback.'));
	pg_mail_last_error('');
}

// The SMTP test and the ERP senders read the reason; none points at a log.
function test_mail_failure_senders_use_the_reason()
{
	$files = array(
		'smtp_settings.php',
		'includes/erp/mail.php',
		'includes/erp/quotes.php',
		'includes/erp/purchase_orders.php',
		'includes/erp/reconciliation.php',
		'includes/erp/accountant.php',
	);

	foreach ($files as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, 'pg_mail_failure_sentence(') !== false, $file . ' uses pg_mail_failure_sentence()');
		pg_assert_true(strpos($source, 'details are in the activity log') === false, $file . ' still points at the activity log');
		pg_assert_true(strpos($source, 'Check logs for details') === false, $file . ' still points at the logs');
	}
}

// A send failure is not marked on the recipient box: the senders say which
// field a refusal belongs to, and the reconciliation screen escapes it.
function test_mail_failure_marked_on_the_named_field()
{
	$invoice = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');
	pg_assert_true(strpos($invoice, "mark_error('mail_to', h(\$sent['error']))") === false, 'edit_erp_invoice.php marks every failure on To');
	pg_assert_true(strpos($invoice, "mark_error(\$sent['field'], h(\$sent['error']))") !== false, 'edit_erp_invoice.php uses the field the sender names');

	$letter = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_reconciliation.php');
	pg_assert_true(strpos($letter, "mark_error('to', \$result['error'])") === false, 'erp_reconciliation.php marks every failure on To, unescaped');
	pg_assert_true(strpos($letter, "mark_error(\$result['field'], h(\$result['error']))") !== false, 'erp_reconciliation.php uses the field the sender names');

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/mail.php');
	pg_assert_true(strpos($mail, "return \$fail(\$addresses['error'], 'mail_to');") !== false, 'erp_mail_invoice_send() names the To field for an address refusal');

	$reconciliation = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/reconciliation.php');
	pg_assert_true(strpos($reconciliation, "'field' => 'to'") !== false, 'erp_reconciliation_send() names the To field for an address refusal');
}

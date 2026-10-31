<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how email() tells a failure of the PHP mail() transport from
 * other failures (pg_mail_send_built(), includes/fn/mail.php), what it
 * records for it (pg_mail_failure_outcome()) and when the mail queue screen
 * warns about it (pg_mail_transport_warning_shown(),
 * includes/fn/mail_queue.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A PHPMailer whose two halves of send() answer as told, without building
// or sending anything.
class PgTestTransportMailer extends \PHPMailer\PHPMailer\PHPMailer
{
	public $pre = true;
	public $post = true;
	public $post_throws = false;

	public function preSend()
	{
		return $this->pre;
	}

	public function postSend()
	{
		if ($this->post_throws) {
			throw new \PHPMailer\PHPMailer\Exception('Could not instantiate mail function.');
		}

		return $this->post;
	}
}

function pg_test_transport_mailer($mailer, $pre, $post, $post_throws = false)
{
	$mail = new PgTestTransportMailer(true);
	$mail->Mailer = $mailer;
	$mail->pre = $pre;
	$mail->post = $post;
	$mail->post_throws = $post_throws;

	return $mail;
}

// PHP mail() refusing the built message, by exception or by false, is a
// transport failure.
function test_mail_transport_mail_failure_is_transport()
{
	$transport = false;
	$thrown = false;

	try {
		pg_mail_send_built(pg_test_transport_mailer('mail', true, false, true), $transport);
	} catch (\Exception $e) {
		$thrown = true;
	}

	pg_assert_true($thrown);
	pg_assert_true($transport);

	pg_assert_false(pg_mail_send_built(pg_test_transport_mailer('mail', true, false), $transport));
	pg_assert_true($transport);
}

// An SMTP server's failure is not one: it is retried as before.
function test_mail_transport_smtp_failure_is_not_transport()
{
	$transport = true;
	$thrown = false;

	try {
		pg_mail_send_built(pg_test_transport_mailer('smtp', true, false, true), $transport);
	} catch (\Exception $e) {
		$thrown = true;
	}

	pg_assert_true($thrown);
	pg_assert_false($transport);

	pg_assert_false(pg_mail_send_built(pg_test_transport_mailer('smtp', true, false), $transport));
	pg_assert_false($transport);
}

// A message that could not be built never reached mail().
function test_mail_transport_presend_failure_is_not_transport()
{
	$transport = true;

	pg_assert_false(pg_mail_send_built(pg_test_transport_mailer('mail', false, true), $transport));
	pg_assert_false($transport);
}

// A sent message leaves no failure.
function test_mail_transport_sent_message()
{
	$transport = true;

	pg_assert_true(pg_mail_send_built(pg_test_transport_mailer('mail', true, true), $transport));
	pg_assert_false($transport);
}

// The transport failure stores the product's sentence, is permanent and
// sends no notice; other failures keep their reason, flag and notice.
function test_mail_transport_failure_outcome()
{
	$outcome = pg_mail_failure_outcome('Could not instantiate mail function.', false, true);

	pg_assert_same(pg_mail_transport_failure_sentence(), $outcome['error']);
	pg_assert_true($outcome['permanent']);
	pg_assert_false($outcome['notify']);

	$outcome = pg_mail_failure_outcome('SMTP connect() failed.', false, false);

	pg_assert_same('SMTP connect() failed.', $outcome['error']);
	pg_assert_false($outcome['permanent']);
	pg_assert_true($outcome['notify']);

	$outcome = pg_mail_failure_outcome('Invalid address', true, false);

	pg_assert_true($outcome['permanent']);
	pg_assert_true($outcome['notify']);
}

// The screen warns while the newest tried message failed on the transport.
function test_mail_transport_warning_rule()
{
	pg_assert_true(pg_mail_transport_warning_shown(array('status' => 'failed', 'last_error' => pg_mail_transport_failure_sentence())));
	pg_assert_true(pg_mail_transport_warning_shown(array('status' => 'failed', 'last_error' => 'Email could not be sent because the PHP mail() function is disabled.')));

	pg_assert_false(pg_mail_transport_warning_shown(array('status' => 'sent', 'last_error' => '')));
	pg_assert_false(pg_mail_transport_warning_shown(array('status' => 'failed', 'last_error' => 'SMTP connect() failed.')));
	pg_assert_false(pg_mail_transport_warning_shown(array('status' => 'queued', 'last_error' => pg_mail_transport_failure_sentence())));
	pg_assert_false(pg_mail_transport_warning_shown(null));
}

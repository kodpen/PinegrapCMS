<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of the outgoing mail queue in
 * includes/fn/mail_queue.php: the retry schedule, the stored form of an
 * email() call, the "is the general job alive" decision, the List-Unsubscribe
 * headers, and what makes the queue give a message up at once and tell the
 * panel about it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Attempts 1 to 6 wait one minute, five minutes, half an hour, two hours,
// six hours and a day.
function test_mail_queue_backoff_schedule()
{
	pg_assert_same(60, pg_mail_backoff(1));
	pg_assert_same(300, pg_mail_backoff(2));
	pg_assert_same(1800, pg_mail_backoff(3));
	pg_assert_same(7200, pg_mail_backoff(4));
	pg_assert_same(21600, pg_mail_backoff(5));
	pg_assert_same(86400, pg_mail_backoff(6));
}

// Past the last step there is no further attempt.
function test_mail_queue_backoff_ends_after_six()
{
	pg_assert_same(0, pg_mail_backoff(7));
	pg_assert_same(6, pg_mail_max_attempts());
}

// Zero or a negative count is treated as the first attempt.
function test_mail_queue_backoff_floor()
{
	pg_assert_same(60, pg_mail_backoff(0));
	pg_assert_same(60, pg_mail_backoff(-3));
}

// The queue flag is not stored, so the job's own email() call sends.
function test_mail_queue_encode_drops_queue()
{
	$json = pg_mail_properties_encode(array('to' => 'a@example.test', 'subject' => 'Hi', 'queue' => true));

	pg_assert_true(is_string($json));

	$properties = pg_mail_properties_decode($json);

	pg_assert_false(array_key_exists('queue', $properties));
	pg_assert_same('a@example.test', $properties['to']);
	pg_assert_same('Hi', $properties['subject']);
}

// Every other key comes back as it went in, including a list of recipients,
// non-ASCII text and booleans.
function test_mail_queue_encode_round_trip()
{
	$properties = array(
		'type' => 'system',
		'to' => array('a@example.test', 'b@example.test'),
		'to_name' => 'Ayşe Öztürk',
		'bcc' => '',
		'from_name' => 'Mağaza',
		'from_email_address' => 'shop@example.test',
		'reply_to' => 'reply@example.test',
		'subject' => 'Siparişiniz #1001',
		'format' => 'html',
		'body' => '<p>Teşekkürler — çok sağ olun</p>',
		'notify_sender' => false,
		'purpose' => 'commercial',
	);

	pg_assert_same($properties, pg_mail_properties_decode(pg_mail_properties_encode($properties)));
}

// Attachment bytes that are not valid UTF-8 survive byte for byte; json_encode
// would refuse them as they are.
function test_mail_queue_encode_binary_attachment()
{
	$bytes = "%PDF-1.4\xFF\xFE\x00\x80binary";

	$json = pg_mail_properties_encode(array(
		'to' => 'a@example.test',
		'attachments' => array(
			array('name' => 'invoice.pdf', 'content' => $bytes, 'type' => 'application/pdf'),
		),
	));

	pg_assert_true(is_string($json));
	pg_assert_false(strpos($json, '"content"') !== false, 'raw content key stored');

	$properties = pg_mail_properties_decode($json);

	pg_assert_same($bytes, $properties['attachments'][0]['content']);
	pg_assert_same('invoice.pdf', $properties['attachments'][0]['name']);
	pg_assert_same('application/pdf', $properties['attachments'][0]['type']);
	pg_assert_false(array_key_exists('content_base64', $properties['attachments'][0]));
}

// An attachment given by path travels as the path.
function test_mail_queue_encode_path_attachment()
{
	$attachment = array('path' => '/tmp/report.pdf', 'name' => 'Report.pdf');

	$properties = pg_mail_properties_decode(pg_mail_properties_encode(array('to' => 'a@example.test', 'attachments' => array($attachment))));

	pg_assert_same(array($attachment), $properties['attachments']);
}

// A stored value that is not a JSON object is refused.
function test_mail_queue_decode_refuses_garbage()
{
	pg_assert_false(pg_mail_properties_decode('not json'));
	pg_assert_false(pg_mail_properties_decode('"a string"'));
}

// Queue only while the general job finished in the last fifteen minutes.
function test_mail_queue_should_queue_window()
{
	$now = 1800000000;

	pg_assert_false(pg_mail_should_queue(0, $now), 'never ran');
	pg_assert_true(pg_mail_should_queue($now - 899, $now), '899 seconds ago');
	pg_assert_true(pg_mail_should_queue($now - 900, $now), '900 seconds ago');
	pg_assert_false(pg_mail_should_queue($now - 901, $now), '901 seconds ago');
}

// No URL, no header: there would be nothing to post the one click to.
function test_mail_queue_list_unsubscribe_needs_url()
{
	pg_assert_same(array(), pg_mail_list_unsubscribe_headers('list@example.test', ''));
}

// Both headers, the mailto first, and the exact RFC 8058 Post value.
function test_mail_queue_list_unsubscribe_headers()
{
	$headers = pg_mail_list_unsubscribe_headers('list@example.test', 'https://example.test/u?id=x&sig=y&unsubscribe=1');

	pg_assert_same(2, count($headers));
	pg_assert_same('<mailto:list@example.test?subject=unsubscribe>, <https://example.test/u?id=x&sig=y&unsubscribe=1>', $headers['List-Unsubscribe']);
	pg_assert_same('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
}

// Without a mailto address the header carries the URL alone.
function test_mail_queue_list_unsubscribe_without_mailto()
{
	$headers = pg_mail_list_unsubscribe_headers('', 'https://example.test/u');

	pg_assert_same('<https://example.test/u>', $headers['List-Unsubscribe']);
	pg_assert_same('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
}

// A message with no address in 'to' can never be sent.
function test_mail_queue_permanent_problem_no_recipient()
{
	pg_assert_same('no_recipient', pg_mail_permanent_problem(array('body' => 'Hi')));
	pg_assert_same('no_recipient', pg_mail_permanent_problem(array('to' => '  ', 'body' => 'Hi')));
	pg_assert_same('no_recipient', pg_mail_permanent_problem(array('to' => array('', ' '), 'body' => 'Hi')));
}

// A body that is empty or only white space, plain or HTML, is nothing to send.
function test_mail_queue_permanent_problem_empty_body()
{
	pg_assert_same('empty_body', pg_mail_permanent_problem(array('to' => 'a@example.test')));
	pg_assert_same('empty_body', pg_mail_permanent_problem(array('to' => 'a@example.test', 'body' => " \r\n\t")));
	pg_assert_same('empty_body', pg_mail_permanent_problem(array('to' => 'a@example.test', 'body' => '', 'format' => 'html')));
	pg_assert_same('empty_body', pg_mail_permanent_problem(array('to' => 'a@example.test', 'body' => array('x'))));
}

// The recipient is named before the body: a message to nobody is wrong
// whatever it says.
function test_mail_queue_permanent_problem_recipient_first()
{
	pg_assert_same('no_recipient', pg_mail_permanent_problem(array('to' => '', 'body' => '')));
}

// A message with a recipient and a body is left to the sender; whether the
// address is well formed is PHPMailer's question.
function test_mail_queue_permanent_problem_none()
{
	pg_assert_same('', pg_mail_permanent_problem(array('to' => 'a@example.test', 'body' => 'Hi')));
	pg_assert_same('', pg_mail_permanent_problem(array('to' => array('', 'b@example.test'), 'body' => '<p>Hi</p>', 'format' => 'html')));
	pg_assert_same('', pg_mail_permanent_problem(array('to' => 'not-an-address', 'body' => '0')));
}

// The flag beside pg_mail_last_error() keeps its value until it is set again.
function test_mail_queue_last_error_permanent_flag()
{
	pg_assert_true(pg_mail_last_error_permanent(true));
	pg_assert_true(pg_mail_last_error_permanent());
	pg_assert_false(pg_mail_last_error_permanent(false));
	pg_assert_false(pg_mail_last_error_permanent());
}

// A permanent failure ends the message on its first attempt; any other
// failure is retried until the sixth.
function test_mail_queue_gives_up_permanent_at_once()
{
	pg_assert_true(pg_mail_queue_gives_up(1, 6, true));
	pg_assert_false(pg_mail_queue_gives_up(1, 6, false));
	pg_assert_false(pg_mail_queue_gives_up(5, 6, false));
	pg_assert_true(pg_mail_queue_gives_up(6, 6, false));
	pg_assert_true(pg_mail_queue_gives_up(7, 6, false));
}

// A failed row that did not use up its attempts was given up as permanent.
function test_mail_queue_failed_permanently()
{
	pg_assert_true(pg_mail_failed_permanently('failed', 1));
	pg_assert_true(pg_mail_failed_permanently('failed', '5'));
	pg_assert_false(pg_mail_failed_permanently('failed', 6));
	pg_assert_false(pg_mail_failed_permanently('queued', 1));
	pg_assert_false(pg_mail_failed_permanently('sent', 1));
}

// Up to the limit, one notification per message: the reason in title, the
// recipient in order_total, the queue row in form_id.
function test_mail_queue_given_up_notices_each()
{
	pg_assert_same(array(), pg_mail_given_up_notices(array()));

	$notices = pg_mail_given_up_notices(array(
		array('id' => 7, 'recipient' => 'a@example.test', 'reason' => 'Message body empty'),
		array('id' => 9, 'recipient' => 'b@example.test', 'reason' => ''),
	));

	pg_assert_same(2, count($notices));
	pg_assert_same('mail_failed', $notices[0]['action']);
	pg_assert_same('error', $notices[0]['type']);
	pg_assert_same('Message body empty', $notices[0]['title']);
	pg_assert_same('a@example.test', $notices[0]['order_total']);
	pg_assert_same(7, $notices[0]['form_id']);
	pg_assert_same('system', $notices[0]['user']);
	pg_assert_same('-', $notices[1]['title']);
	pg_assert_same(9, $notices[1]['form_id']);
}

// Past the limit the run is told in one row carrying the count, without a
// queue row.
function test_mail_queue_given_up_notices_summary()
{
	$given_up = array();

	for ($i = 1; $i <= 4; $i++) {
		$given_up[] = array('id' => $i, 'recipient' => 'r' . $i . '@example.test', 'reason' => 'x');
	}

	$notices = pg_mail_given_up_notices($given_up);

	pg_assert_same(1, count($notices));
	pg_assert_same('mail_failed', $notices[0]['action']);
	pg_assert_same('4', $notices[0]['title']);
	pg_assert_false(isset($notices[0]['form_id']));

	pg_assert_same(3, count(pg_mail_given_up_notices(array_slice($given_up, 0, 3))));
}

// The stored reason fits notifications.title and the recipient order_total.
function test_mail_queue_given_up_notices_clip()
{
	$notices = pg_mail_given_up_notices(array(
		array('id' => 1, 'recipient' => str_repeat('a', 150), 'reason' => str_repeat('ş', 400)),
	));

	pg_assert_same(255, mb_strlen($notices[0]['title'], 'UTF-8'));
	pg_assert_same(100, strlen($notices[0]['order_total']));
}

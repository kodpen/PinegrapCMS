<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a task reminder's e-mail (includes/workspace/reminders.php): the
 * channel line is written only with the name the caller passes, so a person
 * on the task who cannot read its private channel is not told its name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array('URL_SCHEME' => 'https://', 'HOSTNAME_SETTING' => 'example.com', 'PATH' => '/', 'SOFTWARE_DIRECTORY' => 'pinegrap') as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/reminders.php');

// A task in a channel, due on a day at a time.
function ws_task_reminder_mail_test_task()
{
	return array(
		'id'         => 42,
		'title'      => 'Call the supplier',
		'channel_id' => 7,
		'priority'   => 'normal',
		'due_date'   => '2026-11-20',
		'due_time'   => '14:30:00',
	);
}

// A recipient who cannot read the channel gets no channel line.
function test_ws_task_reminder_mail_leaves_out_an_unreadable_channel()
{
	$mail = ws_task_reminder_mail(ws_task_reminder_mail_test_task(), '');

	pg_assert_true(strpos($mail['body'], '#') === false, 'no channel name in the body');
	pg_assert_true(strpos($mail['body'], 'Call the supplier') !== false, 'the title is there');
	pg_assert_true(strpos($mail['body'], '20.11.2026 14:30') !== false, 'the due time is there');
}

// A recipient who reads the channel is told its name.
function test_ws_task_reminder_mail_names_a_readable_channel()
{
	$mail = ws_task_reminder_mail(ws_task_reminder_mail_test_task(), 'purchasing');

	pg_assert_true(strpos($mail['body'], '#purchasing') !== false, 'the channel name is there');
}

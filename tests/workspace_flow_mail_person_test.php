<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow e-mail box writing to the person of the run's record
 * (includes/workspace/flow_mail_person.php): the settings kept, the record
 * a run is about (a contact of a run started by hand or for a customer, the
 * record of the event that started it, a received e-mail's conversation),
 * no typed address needed, and the unsubscribe link a marketing e-mail
 * carries.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_person.php');

function test_workspace_flow_mail_person_settings()
{
	$email = ws_step_config_normalize('email', array('recipient' => 'record_person', 'purpose' => 'marketing', 'subject' => 'Hi', 'body' => 'Text'));

	pg_assert_same(array('record_person', 'marketing'), array($email['recipient'], $email['purpose']));

	$odd = ws_step_config_normalize('email', array('recipient' => 'anyone', 'purpose' => 'spam'));

	pg_assert_same(array('typed', 'info'), array($odd['recipient'], $odd['purpose']), 'unknown values kept as typed and info');

	$problems = function ($config) {
		return ws_step_config_problems('email', ws_step_config_normalize('email', $config + array('subject' => 'Hi', 'body' => 'Text')), 'Mail');
	};

	pg_assert_same(array(), $problems(array('recipient' => 'record_person')), 'no address typed for the record\'s person');
	pg_assert_same(1, count($problems(array())), 'typed needs an address');

	$values = ws_flow_setting_values();

	pg_assert_same(array('typed', 'record_person'), $values['email.recipient'] ?? null, 'the catalogue names the recipients');
	pg_assert_same(array('info', 'marketing'), $values['email.purpose'] ?? null, 'and the purposes');
}

// The record a run is about, by how it started.
function test_workspace_flow_mail_person_source()
{
	pg_assert_same(array('contact', 7), ws_flow_mail_person_source('contact', 7, array()), 'a run started by hand for a contact');
	pg_assert_same(array('order', 12), ws_flow_mail_person_source('', 0, array('event' => 'order.shipped', 'event_payload' => array('id' => 12))), 'an order event');
	pg_assert_same(array('form', 3), ws_flow_mail_person_source('', 0, array('event' => 'form.submitted', 'event_payload' => array('id' => 3))), 'a submitted form');
	pg_assert_same(array('booking', 9), ws_flow_mail_person_source('', 0, array('event' => 'booking.confirmed', 'event_payload' => array('id' => 9))), 'a booking');
	pg_assert_same(array('mail', 4), ws_flow_mail_person_source('', 0, array('event' => 'workspace.mail.received', 'event_payload' => array('id' => 4, 'conversation_id' => 4, 'channel_id' => 2))), 'a received e-mail\'s conversation');
	pg_assert_same(array('', 0), ws_flow_mail_person_source('', 0, array('event' => 'stock.low', 'event_payload' => array('id' => 5))), 'a record with no person');
	pg_assert_same(array('', 0), ws_flow_mail_person_source('', 0, array('trigger' => 'schedule')), 'a run at a time');
	pg_assert_same(array('', 0), ws_flow_mail_person_source('', 0, array('event' => 'order.shipped', 'event_payload' => array())), 'an event without its id');
}

function test_workspace_flow_mail_person_unsubscribe_footer()
{
	$url = 'https://example.com/email_preferences.php?id=x&sig=y';
	$plain = ws_flow_mail_unsubscribe_footer("Hello\n\n", 'plain_text', $url);

	pg_assert_same("Hello\n\nUpdate email preferences or unsubscribe:\n" . $url, $plain, 'a line at the end of plain text');

	$html = ws_flow_mail_unsubscribe_footer('<html><body><p>Hi</p></body></html>', 'html', $url);

	pg_assert_contains('<a href="https://example.com/email_preferences.php?id=x&amp;sig=y">', $html, 'a link in HTML');
	pg_assert_true(strpos($html, '</a></p></body>') !== false, 'before the body ends');
	pg_assert_same('<p>Hi</p>', ws_flow_mail_unsubscribe_footer('<p>Hi</p>', 'html', ''), 'no link without a key to sign it');
}

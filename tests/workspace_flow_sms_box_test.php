<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow Send an SMS box (includes/workspace/flow_sms_box.php):
 * the settings kept, what keeps it from being published, the kind it queues
 * with, the number read from a record's row, the queue's reference and
 * options, the number a run's sentence shows, and the places the new kind
 * is named (quick setup group, canvas, tiers, consent rule, queue only).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_workspace_flow_sms_box_settings()
{
	$sms = ws_step_config_normalize('sms', array('recipient' => 'record_person', 'purpose' => 'marketing', 'text' => " Hello\n", 'extra' => 1));

	pg_assert_same(array('to', 'recipient', 'purpose', 'text'), array_keys($sms), 'only its own keys');
	pg_assert_same(array('record_person', 'marketing', 'Hello'), array($sms['recipient'], $sms['purpose'], $sms['text']));

	$odd = ws_step_config_normalize('sms', array('recipient' => 'anyone', 'purpose' => 'spam', 'text' => str_repeat('a', 2000)));

	pg_assert_same(array('typed', 'info'), array($odd['recipient'], $odd['purpose']), 'unknown words become the first');
	pg_assert_same(WS_STEP_SMS_TEXT_MAX, mb_strlen($odd['text']), 'the text is cut');
	pg_assert_same(array('typed', 'record_person'), ws_flow_setting_values()['sms.recipient']);
	pg_assert_same(array('info', 'marketing'), ws_flow_setting_values()['sms.purpose']);
}

function test_workspace_flow_sms_box_problems()
{
	$problems = function ($config) {
		return ws_step_config_problems('sms', ws_step_config_normalize('sms', $config), 'B');
	};

	pg_assert_same(2, count($problems(array())), 'a number and a text');
	pg_assert_same(array(), $problems(array('to' => '0532 111 22 33', 'text' => 'Hi')), 'a Turkish mobile number');
	pg_assert_same(array(), $problems(array('to' => '+44 7700 900123', 'text' => 'Hi')), 'a foreign one with its code');
	pg_assert_same(1, count($problems(array('to' => '0212 111 22 33', 'text' => 'Hi'))), 'a landline cannot take an SMS');
	pg_assert_same(1, count($problems(array('to' => '{{trigger.phone}}', 'text' => 'Hi'))), 'no value in the number');
	pg_assert_same(array(), $problems(array('recipient' => 'record_person', 'text' => 'Hi')), 'the record\'s person needs no number');
}

function test_workspace_flow_sms_box_kind_and_queue()
{
	pg_assert_same('marketing', ws_step_sms_kind('marketing'));
	pg_assert_same('flow', ws_step_sms_kind('info'));
	pg_assert_same(false, pg_sms_kinds()['flow'], 'an informational flow message is no commercial one');
	pg_assert_true(isset(pg_sms_queue_list_kinds()['flow']), 'the queue screen names it');

	pg_assert_same('flow:12:3:n-4', ws_step_sms_reference(12, 'n 4', 3));
	pg_assert_true((bool) preg_match('/^[A-Za-z0-9:._\/-]{1,64}$/', ws_step_sms_reference(99999999, str_repeat('ğx', 60), 2)), 'a reference the queue takes');
	pg_assert_same(array('reference' => 'r'), ws_step_sms_queue_options('info', 'r', 1000));
	pg_assert_same(array('reference' => 'r', 'expires_at' => 1000 + 6 * 3600), ws_step_sms_queue_options('marketing', 'r', 1000), 'a promotion expires as a campaign\'s');

	pg_assert_same('…2233', ws_step_sms_number_shown('905321112233'), 'a sentence keeps the last digits only');
}

function test_workspace_flow_sms_box_phone_of_a_record()
{
	pg_assert_same('0532 111 22 33', ws_step_sms_phone_of('order', array('billing_phone_number' => ' 0532 111 22 33 ', 'type' => '')));
	pg_assert_same('', ws_step_sms_phone_of('order', array('billing_phone_number' => '05321112233', 'type' => 'marketplace')), 'the marketplace tells its buyer');
	pg_assert_same('05321112233', ws_step_sms_phone_of('booking', array('phone' => '05321112233')));
	pg_assert_same('05321112233', ws_step_sms_phone_of('contact', array('mobile_phone' => '05321112233')));
	pg_assert_same('', ws_step_sms_phone_of('contact', null));
	pg_assert_same('', ws_step_sms_phone_of('task', array('phone' => '05321112233')));
}

// Queued only, through the layer, with the campaigns' consent rule; the
// run's texts filled where it is queued.
function test_workspace_flow_sms_box_queues_through_the_layer()
{
	$do = pg_test_function_source('ws_step_sms_do');

	pg_assert_contains('pg_sms_enqueue(', $do);
	pg_assert_contains('ws_step_sms_marketing_refusal(', $do);
	pg_assert_contains('ws_scheduled_fill(', $do);
	pg_assert_false(strpos($do, 'pg_sms_send(') !== false, 'never sent here');
	pg_assert_false(strpos($do, 'INSERT') !== false, 'never written to the queue by hand');
	pg_assert_contains('pg_sms_marketing_permission(', pg_test_function_source('ws_step_sms_marketing_refusal'));
	pg_assert_contains('pg_sms_ready()', pg_test_function_source('ws_step_sms_check'));
	pg_assert_contains('ws_step_sms_person(', pg_test_function_source('ws_step_run'));
	pg_assert_contains('ws_step_sms_person(', pg_test_function_source('ws_flow_box_try'));
}

// Where the new kind is named beside the e-mail box.
function test_workspace_flow_sms_box_named()
{
	pg_assert_true(in_array('sms', ws_flow_quick_groups()['message']['kinds'], true), 'a quick setup step under Message');
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_flow_box_tiers()['sms'], 'a message out cannot be taken back');
	pg_assert_true(in_array('sms', ws_flow_card_outbound_kinds(), true));
	pg_assert_false(in_array('sms', ws_flow_kinds_workspace(), true), 'it runs with the Workspace off');
	pg_assert_contains('pg_sms_ready()', pg_test_function_source('ws_flow_quick_fill'), 'offered where the site can send SMS');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_contains("sms: { name: 'fl_kind_sms', group: 'message'", $js);
	pg_assert_contains("((item[0] !== 'sms') || stepLists().sms)", $js, 'the palette offers it only where SMS is set up');
	pg_assert_contains("sms: ['M", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), 'its line icon');
}

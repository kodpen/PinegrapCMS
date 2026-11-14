<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow Send a WhatsApp message box
 * (includes/workspace/flow_whatsapp_box.php): the settings kept, the
 * template named by name and language, what keeps it from being published,
 * the queue's reference and options, and whom the e-mail in the message's
 * place goes to.
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

function test_workspace_flow_whatsapp_box_settings()
{
	$box = ws_step_whatsapp_normalize(array('recipient' => 'record_person', 'template' => 'order_ready|tr', 'v1' => " {{trigger.name}}\n", 'fallback' => '1', 'extra' => 1));

	pg_assert_same(array_merge(array('to', 'recipient', 'template'), ws_step_whatsapp_var_keys(), array('fallback', 'fallback_to', 'fallback_subject', 'fallback_body')), array_keys($box), 'only its own keys');
	pg_assert_same(array('record_person', 'order_ready|tr', '{{trigger.name}}', 1), array($box['recipient'], $box['template'], $box['v1'], $box['fallback']));
	pg_assert_same(WS_STEP_WHATSAPP_VARS, count(ws_step_whatsapp_var_keys()));
	pg_assert_same('v20', ws_step_whatsapp_var_keys()[19]);

	$odd = ws_step_whatsapp_normalize(array('recipient' => 'anyone', 'template' => 'Bad Name|tr', 'v2' => str_repeat('a', 2000), 'fallback' => 0));

	pg_assert_same(array('typed', '', 0), array($odd['recipient'], $odd['template'], $odd['fallback']), 'unknown words become the first; a template that is no name is dropped');
	pg_assert_same(WS_STEP_WHATSAPP_VAR_MAX, mb_strlen($odd['v2']), 'a variable is cut');
	pg_assert_same(ws_step_whatsapp_normalize(array()), ws_step_whatsapp_normalize('not an array'));
}

function test_workspace_flow_whatsapp_box_template_key()
{
	pg_assert_same('hello|en_US', ws_step_whatsapp_template_key('hello', 'en_US'));
	pg_assert_same(array('name' => 'hello', 'language' => 'en_US'), ws_step_whatsapp_template_split('hello|en_US'));
	pg_assert_same(array('name' => '', 'language' => ''), ws_step_whatsapp_template_split('hello'), 'no language');
	pg_assert_same(array('name' => '', 'language' => ''), ws_step_whatsapp_template_split('a|b|c'));
	pg_assert_same(array('name' => '', 'language' => ''), ws_step_whatsapp_template_split('hello|english'), 'not a language code');
}

function test_workspace_flow_whatsapp_box_problems()
{
	$problems = function ($config) {
		return ws_step_whatsapp_problems(ws_step_whatsapp_normalize($config), 'B');
	};

	pg_assert_same(2, count($problems(array())), 'a number and a template');
	pg_assert_same(array(), $problems(array('to' => '0532 111 22 33', 'template' => 'hi|tr')), 'a Turkish mobile number');
	pg_assert_same(1, count($problems(array('to' => '0212 111 22 33', 'template' => 'hi|tr'))), 'a landline has no WhatsApp');
	pg_assert_same(1, count($problems(array('to' => '{{trigger.phone}}', 'template' => 'hi|tr'))), 'no value in the number');
	pg_assert_same(array(), $problems(array('recipient' => 'record_person', 'template' => 'hi|tr')), 'the record\'s person needs no number');
	pg_assert_same(2, count($problems(array('to' => '05321112233', 'template' => 'hi|tr', 'fallback' => 1))), 'a typed number\'s e-mail needs an address, a subject and a text');
	pg_assert_same(1, count($problems(array('recipient' => 'record_person', 'template' => 'hi|tr', 'fallback' => 1, 'fallback_subject' => 'S'))), 'the record\'s person\'s e-mail needs its text');
	pg_assert_same(array(), $problems(array('recipient' => 'record_person', 'template' => 'hi|tr', 'fallback' => 1, 'fallback_subject' => 'S', 'fallback_body' => 'T')));
}

function test_workspace_flow_whatsapp_box_queue()
{
	pg_assert_same('flow:12:3:n-4', ws_step_whatsapp_reference(12, 'n 4', 3));
	pg_assert_same(64, strlen(ws_step_whatsapp_reference(1, str_repeat('x', 100), 0)), 'at most 64 characters');
	pg_assert_same(array('kind' => 'flow', 'reference' => 'r'), ws_step_whatsapp_queue_options('UTILITY', 'r', 1000));
	pg_assert_same(array('kind' => 'flow', 'reference' => 'r', 'expires_at' => 1000 + WS_STEP_WHATSAPP_MARKETING_HOURS * 3600), ws_step_whatsapp_queue_options('marketing', 'r', 1000), 'a promotion expires');
}

function test_workspace_flow_whatsapp_box_fallback_to()
{
	$never = function ($address) {
		return 'no consent';
	};
	$always = function ($address) {
		return '';
	};
	$person = array('recipient' => 'record_person', 'category' => 'UTILITY');

	pg_assert_same(array('to' => array('a@example.com'), 'marketing' => false, 'error' => ''), ws_step_whatsapp_fallback_to($person, 'a@example.com', $never), 'information needs no consent');
	pg_assert_same('no consent', ws_step_whatsapp_fallback_to(array('category' => 'MARKETING') + $person, 'a@example.com', $never)['error'], 'a promotion needs e-mail consent');
	pg_assert_same(array('to' => array('a@example.com'), 'marketing' => true, 'error' => ''), ws_step_whatsapp_fallback_to(array('category' => 'MARKETING') + $person, 'a@example.com', $always), 'and carries the unsubscribe link');
	pg_assert_true(ws_step_whatsapp_fallback_to($person, '', $always)['error'] !== '', 'no address');

	$typed = ws_step_whatsapp_fallback_to(array('recipient' => 'typed', 'fallback_to' => 'b@example.com', 'category' => 'MARKETING'), '', $never);

	pg_assert_same(array(array('b@example.com'), false, ''), array($typed['to'], $typed['marketing'], $typed['error']), 'a typed address is the owner\'s choice');
	pg_assert_true(ws_step_whatsapp_fallback_to(array('recipient' => 'typed', 'fallback_to' => 'not an address'), '', $never)['error'] !== '');
}

function test_workspace_flow_whatsapp_received_event()
{
	$event = pg_whatsapp_received_event('7', array('from' => '+90 532 111 22 33', 'profile_name' => ' Ayşe ', 'type' => 'text', 'body' => str_repeat('a', 1200), 'media_id' => 'm1', 'wamid' => 'w1'), 5);

	pg_assert_same(array('id', 'from', 'profile_name', 'type', 'body', 'contact_id'), array_keys($event), 'single values, never the media or the wamid');
	pg_assert_same(array(7, '905321112233', 'Ayşe', 5), array($event['id'], $event['from'], $event['profile_name'], $event['contact_id']));
	pg_assert_same(1000, mb_strlen($event['body']), 'the text is cut');
	pg_assert_same(0, pg_whatsapp_received_event(1, 'nothing', -3)['contact_id']);

	pg_assert_true(in_array('whatsapp.received', pg_event_workspace_events(), true), 'the workspace keeps it');
	pg_assert_same(array('contact', 5), ws_events_record_of('whatsapp.received', $event), 'about the contact of the number');
	pg_assert_same(array('', 0), ws_events_record_of('whatsapp.received', array('contact_id' => 0)), 'a number no contact has');
	pg_assert_same(array('from', 'profile_name', 'type', 'body', 'contact_id'), array_column(ws_flow_event_outputs()['whatsapp.received'], 'key'), 'what a start box reads');
}

// An approved template with a text header, two message variables and a
// button whose link takes one.
function workspace_flow_whatsapp_box_test_template($status = 'APPROVED')
{
	return array('id' => 4, 'name' => 'order_ready', 'language' => 'tr', 'category' => 'UTILITY', 'status' => $status, 'parameter_format' => 'POSITIONAL', 'components' => array(
		array('type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Order {{1}}'),
		array('type' => 'BODY', 'text' => 'Hello {{1}}, your order {{2}} is ready.'),
		array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'URL', 'text' => 'Track', 'url' => 'https://example.com/t/{{1}}'))),
	));
}

function test_workspace_flow_whatsapp_box_template_problem()
{
	$filled = array('v1' => 'A', 'v2' => 'B', 'v3' => 'C', 'v4' => 'D');
	$template = workspace_flow_whatsapp_box_test_template();

	pg_assert_same('', ws_step_whatsapp_template_problem($filled, $template, 'B'));
	pg_assert_true(ws_step_whatsapp_template_problem($filled, null, 'B') !== '', 'a template the account no longer has');
	pg_assert_true(ws_step_whatsapp_template_problem($filled, workspace_flow_whatsapp_box_test_template('PAUSED'), 'B') !== '', 'not approved');
	pg_assert_contains('4', ws_step_whatsapp_template_problem(array('v4' => '') + $filled, $template, 'B'), 'names the empty variable');

	$image = $template;
	$image['components'][0] = array('type' => 'HEADER', 'format' => 'IMAGE');
	pg_assert_true(ws_step_whatsapp_template_problem($filled, $image, 'B') !== '', 'a media header takes no text value');
}

function test_workspace_flow_whatsapp_box_components()
{
	$built = ws_step_whatsapp_components(workspace_flow_whatsapp_box_test_template(), array('v1' => '438', 'v2' => 'Ayşe', 'v3' => '438', 'v4' => 'a b'));

	pg_assert_true($built['ok']);
	pg_assert_same(array('header', 'body', 'button'), array_column($built['components'], 'type'), 'in the template\'s order');
	pg_assert_same(array('Ayşe', '438'), array_column($built['components'][1]['parameters'], 'text'), 'v2 and v3 fill the message');
	pg_assert_same('a%20b', $built['components'][2]['parameters'][0]['text'], 'a link\'s value is encoded');

	$rows = ws_step_whatsapp_offered_rows(pg_whatsapp_message_offered(array(workspace_flow_whatsapp_box_test_template())));

	pg_assert_same('order_ready|tr', $rows[0]['key']);
	pg_assert_same(array(1, 2, 3, 4), array_column($rows[0]['vars'], 'n'));
	pg_assert_same(array('header', 'body', 'body', 'button'), array_column($rows[0]['vars'], 'part'));

	foreach ($rows[0]['vars'] as $var) {
		pg_assert_false(strpos($var['label'], '{{') !== false, 'a variable is never named by its placeholder');
	}
}

// The webhook receiver announces each new customer message once, after it
// is kept and the bell is told.
function test_workspace_flow_whatsapp_received_is_announced()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');

	pg_assert_true((bool) preg_match('~function pg_whatsapp_inbox_receive\(.*?mysqli_insert_id.*?create_notification\(.*?pg_announce\(\'whatsapp\.received\', pg_whatsapp_received_event\(\$inbox_id,~s', $source), 'announced after the row is written and the bell told');
	pg_assert_same(1, substr_count($source, "pg_announce('whatsapp.received'"), 'from one place');
}

// A variable's chip names what it stands for: the designer's name of a
// message variable, else "Variable N"; never Meta's {{n}}.
function test_workspace_flow_whatsapp_box_chip_names()
{
	pg_assert_same(array('1' => 'Order number', '3' => 'Customer\'s name'), ws_step_whatsapp_sample_labels('{"body":[{"label":"Order number","value":"438"},{"label":" ","value":"x"},{"label":"Customer\'s name","value":"Ayşe"}]}'));
	pg_assert_same(array(), ws_step_whatsapp_sample_labels(null), 'a template read from Meta has no names');
	pg_assert_same(array(), ws_step_whatsapp_sample_labels('{"body":"x"}'));

	$offered = pg_whatsapp_message_offered(array(workspace_flow_whatsapp_box_test_template()));
	$rows = ws_step_whatsapp_offered_rows($offered, array('order_ready|tr' => array('1' => 'Customer\'s name', '2' => 'Order number')));

	pg_assert_same(array('Variable 1', 'Customer\'s name', 'Order number', 'Variable 4'), array_column($rows[0]['vars'], 'chip'), 'the header and the button take no designer name');
	pg_assert_same(array('Variable 1', 'Variable 2', 'Variable 3', 'Variable 4'), array_column(ws_step_whatsapp_offered_rows($offered)[0]['vars'], 'chip'));
}

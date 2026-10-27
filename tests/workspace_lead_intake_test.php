<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lead box of the workspace's flows and its scheduled-action
 * step (includes/workspace/scheduled.php, steps.php, customer.php): a form
 * submission brings its customer into a channel, an open one about the
 * customer reused or a new one opened with the "New customer" template; the
 * settings the box keeps, how the contact is found and the channel named,
 * and, read from the source, the hourly cap and the event it needs.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/customer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_lead_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The lead box is a step box of its own, the scheduled action's lead step.
function test_workspace_lead_is_a_step_box()
{
	pg_assert_true(isset(ws_step_kinds()['lead']), 'a kind of step box');
	pg_assert_same('lead', ws_step_kinds()['lead']['type']);
	pg_assert_true(ws_flow_kinds()['lead']['step']);
	pg_assert_same(array('type' => 'lead', 'private' => true, 'user_ids' => array(4)), ws_step_action('lead', array('private' => true, 'user_ids' => array(4))));
}

// Its settings: a private channel or not, and up to 50 people put in it.
function test_workspace_lead_config()
{
	pg_assert_same(array('private' => false, 'user_ids' => array()), ws_step_config_normalize('lead', array()));
	pg_assert_same(array('private' => true, 'user_ids' => array(3, 5)), ws_step_config_normalize('lead', array('private' => 1, 'user_ids' => array('3', 5, 3, -1, 'x'), 'other' => 'dropped')));
	pg_assert_same(50, count(ws_step_config_normalize('lead', array('user_ids' => range(1, 80)))['user_ids']));
	pg_assert_same(array(), ws_step_config_problems('lead', ws_step_config_normalize('lead', array()), 'L'), 'nothing to fill in');
}

// Only a submitted form brings a lead: the submission and its contact.
function test_workspace_lead_submission_of()
{
	pg_assert_same(array('form_id' => 12, 'contact_id' => 7), ws_lead_submission_of('form.submitted', array('id' => 12, 'form_id' => 3, 'contact_id' => 7)));
	pg_assert_same(array('form_id' => 12, 'contact_id' => 0), ws_lead_submission_of('form.submitted', array('id' => '12', 'contact_id' => null)));
	pg_assert_same(null, ws_lead_submission_of('form.submitted', array('id' => 0)));
	pg_assert_same(null, ws_lead_submission_of('order.created', array('id' => 12, 'contact_id' => 7)));
	pg_assert_same(null, ws_lead_submission_of('', array()));
}

// The e-mail of a submission: the field tied to the contact's address
// first, else the first e-mail field with an address in it.
function test_workspace_lead_email_pick()
{
	pg_assert_same('b@example.com', ws_lead_email_pick(array(
		array('type' => 'email address', 'contact_field' => '', 'data' => 'a@example.com'),
		array('type' => 'text box', 'contact_field' => 'email_address', 'data' => ' B@example.com '),
	)));
	pg_assert_same('a@example.com', ws_lead_email_pick(array(
		array('type' => 'text box', 'contact_field' => '', 'data' => 'c@example.com'),
		array('type' => 'email address', 'contact_field' => '', 'data' => 'not an address'),
		array('type' => 'email address', 'contact_field' => '', 'data' => 'a@example.com'),
	)));
	pg_assert_same('', ws_lead_email_pick(array(array('type' => 'text box', 'contact_field' => '', 'data' => 'c@example.com'))));
	pg_assert_same('', ws_lead_email_pick(array()));
}

// The channel is named after the customer: the name, else the company,
// else the address.
function test_workspace_lead_channel_name()
{
	pg_assert_same('Ayşe Yılmaz', ws_lead_channel_name(array('first_name' => ' Ayşe ', 'last_name' => 'Yılmaz', 'company' => 'Acme', 'email_address' => 'a@example.com'), 9));
	pg_assert_same('Acme', ws_lead_channel_name(array('first_name' => '', 'last_name' => null, 'company' => 'Acme', 'email_address' => 'a@example.com'), 9));
	pg_assert_same('a@example.com', ws_lead_channel_name(array('first_name' => null, 'company' => '', 'email_address' => 'a@example.com'), 9));
	pg_assert_same('Contact #9', ws_lead_channel_name(array(), 9));
	pg_assert_same(80, mb_strlen(ws_lead_channel_name(array('company' => str_repeat('x', 120)), 9)));
}

// A name another channel holds is tried again with a number.
function test_workspace_lead_channel_names_tried()
{
	pg_assert_same(array('Acme', 'Acme (2)', 'Acme (3)'), array_slice(ws_lead_channel_names('Acme'), 0, 3));
	pg_assert_same(9, count(ws_lead_channel_names('Acme')));
	pg_assert_same(80, mb_strlen(ws_lead_channel_names(str_repeat('y', 80))[1]), 'the number fits the 80 characters');
}

// The channel reused: the newest open public or private one about the
// customer, never a discussion or a guest channel.
function test_workspace_lead_channel_pick()
{
	pg_assert_same(5, ws_lead_channel_pick(array(
		array('id' => 3, 'kind' => 'thread'),
		array('id' => 4, 'kind' => 'guest'),
		array('id' => 5, 'kind' => 'private'),
		array('id' => 6, 'kind' => 'public'),
	)));
	pg_assert_same(0, ws_lead_channel_pick(array(array('id' => 3, 'kind' => 'thread'))));
	pg_assert_same(0, ws_lead_channel_pick(array()));
}

// Read from the source: the step is a branch of ws_scheduled_do(), the
// channel found through ws_customer_channels(), opened with the "New
// customer" template under the hourly cap, and the box hands the run's
// event to the step.
function test_workspace_lead_runs_through_the_customer_seams()
{
	pg_assert_contains("case 'lead':", ws_lead_test_source('ws_scheduled_do'));
	pg_assert_contains("case 'lead':", ws_lead_test_source('ws_scheduled_action_input'));
	pg_assert_contains("case 'lead':", ws_lead_test_source('ws_scheduled_action_text'));

	$run = ws_lead_test_source('ws_scheduled_lead');

	pg_assert_contains('ws_lead_submission_of(', $run);
	pg_assert_contains("ws_customer_channels('contact'", $run);
	pg_assert_contains('WS_SCHEDULED_LEAD_HOURLY', $run);
	pg_assert_contains("ws_template('builtin:new_customer')", $run);
	pg_assert_contains('ws_channel_create(', $run);
	pg_assert_contains('ws_template_apply(', $run);
	pg_assert_true(strpos($run, 'WS_SCHEDULED_LEAD_HOURLY') < strpos($run, 'ws_channel_create('), 'the cap is asked before a channel is made');
	pg_assert_true(strpos($run, "ws_customer_channels('contact'") < strpos($run, 'WS_SCHEDULED_LEAD_HOURLY'), 'a channel reused is not held by the cap');
	pg_assert_same(20, WS_SCHEDULED_LEAD_HOURLY);

	pg_assert_contains("'event_payload'", ws_lead_test_source('ws_step_run'));
}

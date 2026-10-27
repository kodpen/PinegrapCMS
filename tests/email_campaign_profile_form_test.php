<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the campaign profile form shared by the create and the edit
 * screens (includes/forms/email_campaign_profile_form.php): which rows an
 * action opens, which record an action is tied to, the schedule values a
 * profile is saved with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');

function test_email_campaign_profile_action_item_field()
{
	pg_assert_same('calendar_event_id', pg_email_campaign_profile_action_item_field('calendar_event_reserved'), 'calendar event');
	pg_assert_same('custom_form_page_id', pg_email_campaign_profile_action_item_field('custom_form_submitted'), 'custom form');
	pg_assert_same('email_campaign_profile_id', pg_email_campaign_profile_action_item_field('email_campaign_sent'), 'campaign sent');
	pg_assert_same('product_id', pg_email_campaign_profile_action_item_field('product_ordered'), 'product');
	pg_assert_same('', pg_email_campaign_profile_action_item_field('order_completed'), 'order action has no item');
	pg_assert_same('', pg_email_campaign_profile_action_item_field(''), 'no action');
}

function test_email_campaign_profile_action_rows()
{
	$rows = pg_email_campaign_profile_action_rows('calendar_event_reserved');
	pg_assert_same('show', $rows['calendar_event_id'], 'calendar picker shown');
	pg_assert_same('show', $rows['calendar_schedule'], 'before/after shown');
	pg_assert_same('', $rows['standard_schedule'], 'after action label hidden');

	$rows = pg_email_campaign_profile_action_rows('product_ordered');
	pg_assert_same('show', $rows['product_id'], 'product picker shown');
	pg_assert_same('', $rows['calendar_event_id'], 'calendar picker hidden');
	pg_assert_same('', $rows['calendar_schedule'], 'before/after hidden');
	pg_assert_same('show', $rows['standard_schedule'], 'after action label shown');

	$rows = pg_email_campaign_profile_action_rows('order_shipped');
	pg_assert_same(array('', '', '', ''), array($rows['calendar_event_id'], $rows['custom_form_page_id'], $rows['email_campaign_profile_id'], $rows['product_id']), 'order action opens no picker');
	pg_assert_same('show', $rows['standard_schedule'], 'order action: after action label');

	$rows = pg_email_campaign_profile_action_rows('');
	pg_assert_same('show', $rows['standard_schedule'], 'no action: after action label');
	pg_assert_same('', $rows['calendar_schedule'], 'no action: before/after hidden');
}

function test_email_campaign_profile_action_values()
{
	$fields = array(
		'calendar_event_id' => '7',
		'custom_form_page_id' => '8',
		'email_campaign_profile_id' => '9',
		'product_id' => '10',
		'schedule_period' => 'before',
		'schedule_base' => 'calendar_event_start_time',
	);

	pg_assert_same(
		array('action_item_id' => '7', 'schedule_period' => 'before', 'schedule_base' => 'calendar_event_start_time'),
		pg_email_campaign_profile_action_values('calendar_event_reserved', $fields),
		'calendar event keeps the chosen period and base');

	pg_assert_same(
		array('action_item_id' => '8', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('custom_form_submitted', $fields),
		'custom form');

	pg_assert_same(
		array('action_item_id' => '9', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('email_campaign_sent', $fields),
		'campaign sent');

	pg_assert_same(
		array('action_item_id' => '10', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('product_ordered', $fields),
		'product ordered');

	foreach (array('order_abandoned', 'order_completed', 'order_review_request', 'order_shipped', 'order_delivered') as $action) {
		pg_assert_same(
			array('action_item_id' => '0', 'schedule_period' => 'after', 'schedule_base' => 'action'),
			pg_email_campaign_profile_action_values($action, $fields),
			$action);
	}

	pg_assert_same(
		array('action_item_id' => '', 'schedule_period' => '', 'schedule_base' => ''),
		pg_email_campaign_profile_action_values('unknown', $fields),
		'unknown action');
}

function test_email_campaign_profile_schedule_time_midnight()
{
	pg_assert_same('00:01:00', pg_email_campaign_profile_schedule_time('12:00 AM'), '12:00 AM is not "any time"');
	pg_assert_same('00:01:00', pg_email_campaign_profile_schedule_time('12:00 am'), 'lower case');
}

function test_email_campaign_profile_screens_use_shared_form()
{
	foreach (array('add_email_campaign_profile.php', 'edit_email_campaign_profile.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/email_campaign_profile_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_email_campaign_profile_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_email_campaign_profile_form_validate(', $source, $file . ' validates with the shared rules');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
	}
}

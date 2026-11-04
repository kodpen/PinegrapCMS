<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the campaign-profile action "joined a group"
 * (contact_group_joined): one campaign call per contact a
 * contact_group.members_changed event put into a group, the group picker of
 * the profile form, and the action in the lists the import and the API take.
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

function test_joined_campaign_calls_one_per_added_contact()
{
	$calls = pg_contact_group_joined_campaign_calls('contact_group.members_changed', array(
		'id' => 12,
		'name' => 'Newsletter',
		'added' => array(5, '7', 5, 0, -3),
		'removed' => array(9),
	));

	pg_assert_same(array(
		array('action' => 'contact_group_joined', 'action_item_id' => 12, 'contact_id' => 5),
		array('action' => 'contact_group_joined', 'action_item_id' => 12, 'contact_id' => 7),
	), $calls, 'each added contact once, removed ones not');
}

function test_joined_campaign_calls_nothing_to_send()
{
	pg_assert_same(array(), pg_contact_group_joined_campaign_calls('customer.created', array('id' => 12, 'added' => array(5))), 'another event');
	pg_assert_same(array(), pg_contact_group_joined_campaign_calls('contact_group.members_changed', array('id' => 12, 'added' => array(), 'removed' => array(5))), 'only removed');
	pg_assert_same(array(), pg_contact_group_joined_campaign_calls('contact_group.members_changed', array('id' => 0, 'added' => array(5))), 'no group');
	pg_assert_same(array(), pg_contact_group_joined_campaign_calls('contact_group.members_changed', null), 'no payload');
}

function test_joined_campaign_action_in_profile_rules()
{
	pg_assert_same(array('contact_group_joined'), pg_email_campaign_profile_group_actions(), 'group actions');
	pg_assert_same('contact_group_id', pg_email_campaign_profile_action_item_field('contact_group_joined'), 'item field');

	$rows = pg_email_campaign_profile_action_rows('contact_group_joined');
	pg_assert_same('show', $rows['contact_group_id'], 'group picker shown');
	pg_assert_same('show', $rows['standard_schedule'], 'after action label shown');
	pg_assert_same('', $rows['product_id'], 'product picker hidden');

	pg_assert_same('', pg_email_campaign_profile_action_rows('product_ordered')['contact_group_id'], 'group picker hidden for another action');

	pg_assert_same(
		array('action_item_id' => '4', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('contact_group_joined', array('contact_group_id' => '4', 'product_id' => '10', 'schedule_period' => 'before')),
		'saved with the group, after the action'
	);
}

function test_joined_campaign_action_accepted_by_values_check()
{
	$values = array(
		'name' => 'Welcome',
		'enabled' => '1',
		'action' => 'contact_group_joined',
		'action_item_id' => '4',
		'format' => 'plain_text',
		'page_id' => '0',
		'from_email_address' => 'shop@example.com',
		'reply_email_address' => '',
		'bcc_email_address' => '',
		'schedule_time' => '',
		'schedule_length' => '0',
		'schedule_unit' => 'days',
		'schedule_period' => '',
		'schedule_base' => '',
		'purpose' => 'commercial',
	);

	pg_assert_same('', pg_email_campaign_profile_values_problem($values, false), 'valid without commerce access');

	$values['action_item_id'] = '';
	pg_assert_true(pg_email_campaign_profile_values_problem($values, false) !== '', 'the group is required');
}

function test_joined_campaign_action_listed_everywhere()
{
	$root = PG_FUNCTIONS_DIR;

	pg_assert_true(strpos(file_get_contents($root . '/includes/api/resources/campaign_profiles.php'), 'pg_email_campaign_profile_group_actions()') !== false, 'API action list');
	pg_assert_true(substr_count(file_get_contents($root . '/includes/api/schema.php'), "'booking_cancelled', 'contact_group_joined')") === 3, 'API schema enums');
	pg_assert_true(strpos(file_get_contents($root . '/view_email_campaign_profiles.php'), "'contact_group_joined' =>") !== false, 'list label');
	pg_assert_true(strpos(file_get_contents($root . '/assets/js/backend.src.js'), "contact_group_joined: 'contact_group_id'") !== false, 'form script');
	pg_assert_true(strpos(file_get_contents($root . '/includes/fn/events.php'), "pg_contact_group_joined_heard(") !== false, 'announce seam');
	pg_assert_true(strpos(file_get_contents($root . '/includes/migrations/2026.11.0.php'), "function upgrade_2026_11_0_contact_group_joined_campaigns()") !== false, 'migration step');
}

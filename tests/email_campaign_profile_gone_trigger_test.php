<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a campaign profile whose trigger item is gone: the list and the
 * CSV export mark it "(deleted)" instead of an empty cell, the edit screen
 * says why the picker is empty, and Delete counts the profiles that follow
 * the profile it removes.
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

function test_email_campaign_profile_action_item_label()
{
	pg_assert_same('Concert', pg_email_campaign_profile_action_item_label('calendar_event_reserved', 5, array('name' => 'Concert')), 'calendar event name');
	pg_assert_same('contact-us', pg_email_campaign_profile_action_item_label('custom_form_submitted', 5, array('page_name' => 'contact-us')), 'custom form page name');
	pg_assert_same('Welcome', pg_email_campaign_profile_action_item_label('email_campaign_sent', 5, array('name' => 'Welcome')), 'followed profile name');
	pg_assert_same('Mug - Blue mug', pg_email_campaign_profile_action_item_label('product_ordered', 5, array('name' => 'Mug', 'short_description' => 'Blue mug')), 'product name and description');
	pg_assert_same('Mug', pg_email_campaign_profile_action_item_label('product_ordered', 5, array('name' => 'Mug', 'short_description' => 'Mug')), 'description equal to the name is not repeated');
	pg_assert_same('Blue mug', pg_email_campaign_profile_action_item_label('product_ordered', 5, array('name' => '', 'short_description' => 'Blue mug')), 'description alone');

	$deleted = lang('(deleted)');

	foreach (array('calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent', 'product_ordered') as $action) {
		pg_assert_same($deleted, pg_email_campaign_profile_action_item_label($action, 5, null), $action . ': gone row (null) is marked');
		pg_assert_same($deleted, pg_email_campaign_profile_action_item_label($action, 5, false), $action . ': gone row (false) is marked');
		pg_assert_same('', pg_email_campaign_profile_action_item_label($action, 0, null), $action . ': no stored item is not called deleted');
		pg_assert_true(pg_email_campaign_profile_action_item_gone($action, 5, null), $action . ': gone');
		pg_assert_true(!pg_email_campaign_profile_action_item_gone($action, 5, array('name' => 'x', 'page_name' => 'x')), $action . ': row present');
	}

	pg_assert_same('', pg_email_campaign_profile_action_item_label('order_completed', 0, null), 'order action has no item');
	pg_assert_true(!pg_email_campaign_profile_action_item_gone('order_completed', 3, null), 'order action never has a gone item');
}

function test_email_campaign_profile_follower_count()
{
	$follows = array(2 => 1, 3 => 1, 4 => 2, 5 => 0);

	pg_assert_same(2, pg_email_campaign_profile_follower_count(1, $follows), 'direct followers only');
	pg_assert_same(1, pg_email_campaign_profile_follower_count(2, $follows), 'one follower');
	pg_assert_same(0, pg_email_campaign_profile_follower_count(4, $follows), 'no follower');
	pg_assert_same(0, pg_email_campaign_profile_follower_count(0, $follows), 'no profile');
	pg_assert_same(1, pg_email_campaign_profile_follower_count(6, array(6 => 6)), 'a stored self-loop counts once');
}

function test_email_campaign_profile_delete_confirmation()
{
	pg_assert_same(lang('WARNING: This campaign profile will be permanently deleted.'), pg_email_campaign_profile_delete_confirmation(0), 'no follower: plain warning');
	pg_assert_same(lang('WARNING: This campaign profile will be permanently deleted. 1 campaign profile follows it and will send nothing until it is given another trigger.'), pg_email_campaign_profile_delete_confirmation(1), 'one follower');
	pg_assert_same(lang(array('string' => 'WARNING: This campaign profile will be permanently deleted. {var:1} campaign profiles follow it and will send nothing until they are given another trigger.', 'vars' => array(3))), pg_email_campaign_profile_delete_confirmation(3), 'several followers');
	pg_assert_true(strpos(pg_email_campaign_profile_delete_confirmation(3), '3') !== false, 'the count is in the sentence');
}

function test_email_campaign_profile_screens_use_gone_trigger_helpers()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$view = (string) file_get_contents($root . 'view_email_campaign_profiles.php');
	$edit = (string) file_get_contents($root . 'edit_email_campaign_profile.php');
	$form = (string) file_get_contents($root . 'includes/forms/email_campaign_profile_form.php');

	pg_assert_same(2, substr_count($view, 'pg_email_campaign_profile_action_item_label('), 'list rows and CSV export use the label helper');
	pg_assert_true(strpos($view, "\$product['name']") === false, 'no product field is read on a possibly null row');
	pg_assert_true(strpos($edit, 'pg_email_campaign_profile_delete_confirmation(') !== false, 'Delete names the followers');
	pg_assert_true(strpos($edit, 'pg_email_campaign_profile_action_item_gone(') !== false, 'edit screen checks the stored trigger');
	pg_assert_true(strpos($form, 'action_item_gone') !== false, 'form prints the gone-trigger notice');
}

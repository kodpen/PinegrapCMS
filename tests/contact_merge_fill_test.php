<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a contact merge carries into the contact that stays: its
 * blank columns filled from the merged contacts in both branches (with and
 * without a user account), an integer column at 0 (the picture's file_id)
 * counted as blank, and a missing opt_in row of a subscription group read as
 * subscribed in both branches.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_contact_merge_fill_takes_the_picture_file_id()
{
	// Rows come through db_q(), so every value is a string.
	$keep = array('id' => '8', 'file_id' => '0', 'image' => '', 'mobile_phone' => '', 'opt_in' => '1', 'user_id' => '', 'timestamp' => '200');
	$merged = array(array('id' => '3', 'file_id' => '55', 'image' => 'a.jpg', 'mobile_phone' => '0532', 'opt_in' => '1', 'user_id' => '', 'timestamp' => '100'));

	$filled = pg_contact_merge_filled_columns($keep, $merged, array('id', 'user_id', 'opt_in'));

	pg_assert_same('55', $filled['file_id'] ?? null, 'the picture link reaches the contact that stays');
	pg_assert_same('a.jpg', $filled['image'] ?? null, 'a blank text column is filled');
	pg_assert_same('0532', $filled['mobile_phone'] ?? null, 'the phone is filled');
	pg_assert_false(isset($filled['id']) || isset($filled['opt_in']) || isset($filled['user_id']), 'left-out columns are never written');
	pg_assert_false(isset($filled['timestamp']), 'a column the staying contact holds is not written');
}

function test_contact_merge_fill_keeps_values_and_takes_the_newest_first()
{
	$keep = array('id' => '8', 'company' => 'Kodpen', 'tax_number' => '', 'expiration_date' => '0000-00-00', 'affiliate_commission_rate' => '0.00');
	$merged = array(
		array('id' => '5', 'company' => 'Other', 'tax_number' => '1234567890', 'expiration_date' => '2027-01-01', 'affiliate_commission_rate' => '5.00'),
		array('id' => '3', 'company' => 'Oldest', 'tax_number' => '9999999999', 'expiration_date' => '2026-01-01', 'affiliate_commission_rate' => '7.00'),
	);

	$filled = pg_contact_merge_filled_columns($keep, $merged, array('id'));

	pg_assert_false(isset($filled['company']), 'a column with a value keeps it');
	pg_assert_same('1234567890', $filled['tax_number'] ?? null, 'the first merged contact (the newest) wins');
	pg_assert_same('2027-01-01', $filled['expiration_date'] ?? null, 'an empty date is filled');
	pg_assert_same('5.00', $filled['affiliate_commission_rate'] ?? null, 'a zero rate is filled');
}

function test_contact_merge_fill_skips_blank_merged_values()
{
	$filled = pg_contact_merge_filled_columns(
		array('id' => '8', 'file_id' => '0', 'website' => ''),
		array(array('id' => '3', 'file_id' => '0', 'website' => ''), array('id' => '2', 'website' => null)),
		array('id')
	);

	pg_assert_same(array(), $filled, 'nothing to carry, nothing written');
}

function test_contact_merge_group_opt_out_only_for_an_explicit_zero()
{
	pg_assert_false(pg_contact_merge_group_opted_out(null), 'no opt_in row reads as subscribed');
	pg_assert_true(pg_contact_merge_group_opted_out('0'), 'an opt_in row of 0 is an opt-out');
	pg_assert_true(pg_contact_merge_group_opted_out(0), 'an integer 0 is an opt-out');
	pg_assert_false(pg_contact_merge_group_opted_out('1'), 'an opt_in row of 1 is subscribed');
}

function test_merge_contacts_uses_the_shared_rules_in_both_branches()
{
	$source = pg_test_function_source('merge_contacts');

	pg_assert_same(2, substr_count($source, 'pg_contact_merge_filled_columns('), 'both branches fill the blank columns');
	pg_assert_same(2, substr_count($source, 'pg_contact_merge_group_opted_out('), 'both branches read a missing opt_in row the same way');
	pg_assert_false(strpos($source, "\$contact_group['opt_in'] == 0") !== false, 'no loose null == 0 test is left');
}

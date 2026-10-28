<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for contact lines written on the storefront and by the contact
 * merge: pg_audit_as_site() switches the trail's source to "site" (the
 * member, nobody for a guest; a job stays a job), the source is offered by
 * the audit screen, its filter and the API, every storefront contact writer
 * records through pg_audit_site_record*(), and merge_contacts() writes its
 * lines in both of its merge branches. The writers need a database and are
 * checked in their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function contact_audit_site_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_contact_audit_site_switch_names_a_site_visitor()
{
	pg_assert_false(pg_audit_as_site(), 'off by default');
	pg_assert_false(pg_audit_as_site(true), 'turning it on returns the previous state');
	pg_assert_same(array('source' => 'site', 'user_id' => 0, 'username' => ''), pg_audit_actor(), 'a guest on the site');

	pg_audit_as_job(true);
	pg_assert_same('job', pg_audit_actor()['source'], 'a job stays a job');
	pg_audit_as_job(false);

	pg_assert_true(pg_audit_as_site(false), 'turning it off returns the previous state');
	pg_assert_same('job', pg_audit_actor()['source'], 'off again: the command line is a job');
}

function test_contact_audit_site_source_is_offered_everywhere()
{
	pg_assert_true(in_array('site', pg_audit_sources(), true), 'site is a source');
	pg_assert_true(max(array_map('strlen', pg_audit_sources())) <= 8, 'every source fits audit_log.source VARCHAR(8)');

	$filters = pg_audit_view_filters(array('source' => 'site'));
	pg_assert_same('site', $filters['source'], 'the screen filter keeps site');
	pg_assert_true(pg_audit_view_label('source', 'site') !== 'site', 'site has a label');

	pg_assert_true(strpos(contact_audit_site_test_source('view_audit_log.php'), 'foreach (pg_audit_sources() as $audit_view_source)') !== false, 'the screen lists pg_audit_sources()');
	pg_assert_true(strpos(contact_audit_site_test_source('includes/api/schema.php'), "'values' => array('panel', 'api', 'job', 'site')") !== false, 'the API names site');
}

function test_contact_audit_site_storefront_writers_record_their_contacts()
{
	$writers = array(
		'includes/fn/auth.php'      => 4,
		'includes/fn/signin.php'    => 1,
		'my_account_profile.php'    => 2,
		'billing_information.php'   => 2,
		'express_order.php'         => 2,
		'submit_order.php'          => 5,
		'email_preferences.php'     => 3,
		'affiliate_sign_up_form.php' => 2,
	);

	foreach ($writers as $file => $count) {
		pg_assert_same($count, preg_match_all('/pg_audit_site_record(?:_many)?\(/', contact_audit_site_test_source($file)), $file . ' records its contact writes as a site visitor');
	}
}

function test_contact_audit_site_merge_writes_its_lines_in_both_branches()
{
	$source = contact_audit_site_test_source('includes/fn/contacts.php');

	pg_assert_same(2, substr_count($source, '$audit_before = pg_contact_merge_audit_before('), 'the rows are read before each branch writes');
	pg_assert_same(1, substr_count($source, 'pg_contact_merge_audit($child[\'id\'], $audit_before);'), 'the child branch writes its lines');
	pg_assert_same(1, substr_count($source, 'pg_contact_merge_audit($remaining_orphan[\'id\'], $audit_before);'), 'the orphan branch writes its lines');
	pg_assert_true(strpos($source, "pg_audit('merge', 'contact'") !== false, 'one merge line for the contact that stays');
	pg_assert_true(pg_audit_view_label('action', 'merge') !== 'merge', 'merge has a label');
}

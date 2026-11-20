<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a release bundle's publish time (includes/fn/page_bundles.php,
 * job.php): the general job publishes a due bundle once, in the name of the
 * person who set the time, after the publish's checks; a bundle that cannot
 * go out whole goes back to open and tells its people by a bell line. The
 * runner has no connection, so the pure rules are called and the code's
 * shape is read.
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
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

function test_page_bundle_time_held_reason()
{
	$plan = array('ok' => false, 'pages' => array(
		array('item_id' => 1, 'name' => 'spring-offers', 'state' => 'ready', 'text' => ''),
		array('item_id' => 2, 'name' => 'home', 'state' => 'locked', 'text' => ''),
		array('item_id' => 3, 'name' => 'about-us', 'state' => 'changed', 'text' => ''),
		array('item_id' => 4, 'name' => 'contact', 'state' => 'refused', 'text' => 'The page name is reserved.'),
	));
	$reason = pg_page_bundle_time_held_reason($plan, '');

	pg_assert_contains('home', $reason, 'a page open in the editor is named');
	pg_assert_contains('about-us', $reason, 'a page changed since is named');
	pg_assert_contains('contact', $reason, 'a refused page is named');
	pg_assert_contains('The page name is reserved.', $reason, 'with the save\'s answer');
	pg_assert_true(strpos($reason, 'spring-offers') === false, 'a ready page is not');

	pg_assert_same('The bundle has no page to publish.', pg_page_bundle_time_held_reason(array('ok' => false, 'pages' => array()), 'The bundle has no page to publish.'),
		'the check\'s own error when no page is in the way');
	pg_assert_same('', pg_page_bundle_time_held_reason(array('ok' => true, 'pages' => array()), ''), 'nothing in the way');
}

function test_page_bundle_time_people()
{
	pg_assert_same(array(7, 3), pg_page_bundle_time_people(array('scheduled_by' => 7, 'created_by' => 3)), 'who set the time, then who made it');
	pg_assert_same(array(7), pg_page_bundle_time_people(array('scheduled_by' => '7', 'created_by' => 7)), 'once each');
	pg_assert_same(array(3), pg_page_bundle_time_people(array('scheduled_by' => 0, 'created_by' => 3)), 'nobody unknown');
}

function test_page_bundle_time_person()
{
	pg_assert_same(array('id' => 7, 'role' => 1, 'username' => 'ayse'),
		pg_page_bundle_time_person(array('user_id' => '7', 'user_role' => '1', 'user_username' => 'ayse')), 'a designer publishes in their own name');
	pg_assert_same(null, pg_page_bundle_time_person(array('user_id' => 7, 'user_role' => 2, 'user_username' => 'ali')), 'a manager since cannot');
	pg_assert_same(null, pg_page_bundle_time_person(null), 'a person deleted since cannot');
}

// B13: published once; one that cannot go out whole stays open.
function test_page_bundle_time_reopens()
{
	pg_assert_false(pg_page_bundle_time_reopens('published'), 'published');
	pg_assert_false(pg_page_bundle_time_reopens('partial'), 'partly published keeps its own status');
	pg_assert_true(pg_page_bundle_time_reopens('taken_back'), 'a failure taken back goes back to open');
	pg_assert_true(pg_page_bundle_time_reopens(''), 'a bundle the checks held goes back to open');
}

function test_page_bundle_job_shape()
{
	$job = file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_contains('pg_page_bundle_job_run(', $job, 'the general job runs the pass');

	$run = pg_test_function_source('pg_page_bundle_job_run');
	pg_assert_contains('publish_at = 0', $run, 'the time is cleared first');
	pg_assert_contains("AND status = 'scheduled' AND publish_at = ?", $run, 'by one run only: the time read is in the WHERE');
	pg_assert_contains('pg_page_bundle_publish(', $run, 'through the publish and its checks');
	pg_assert_contains('pg_page_bundle_as_person(', $run, 'the save\'s log lines in the person\'s name');
	$as = pg_test_function_source('pg_page_bundle_as_person');
	pg_assert_contains("\$_SESSION['sessionusername'] = ", $as, 'the name is set');
	pg_assert_contains("unset(\$_SESSION['sessionusername'])", $as, 'and given back');
	pg_assert_contains('pg_page_bundle_bell(', $run, 'its people are told');

	// The used replacements go to the Recycle Bin from the job too, where
	// nobody is signed in: the access asked is that of the person given.
	$recycle = pg_test_function_source('pg_recycle_page');
	pg_assert_contains('pg_folder_edit_access(', $recycle, 'the person given');
	pg_assert_true(strpos($recycle, 'check_edit_access(') === false, 'not the signed-in visitor');
}

function test_page_bundle_bell_row()
{
	pg_assert_same('content', pg_notification_module('bundle_held'), 'a bundle line is Content');
	pg_assert_contains("\$action == 'bundle_held'", pg_test_function_source('pg_notification_display'), 'the bell draws it');
	pg_assert_contains("'bundle_held'", pg_test_function_source('pg_notification_visible'), 'its person sees it');
}

// A due bundle whose lock is held (somebody removing a page, a person's
// publish) is not skipped for good: its time was cleared to claim it, so the
// time is given back and the next pass of the job publishes it.
function test_page_bundle_job_busy_gives_the_time_back()
{
	$run = pg_test_function_source('pg_page_bundle_job_run');
	$start = strpos($run, "if (!empty(\$published['busy']))");
	pg_assert_true($start !== false, 'the busy answer is read');

	$busy = substr($run, $start, strpos($run, 'continue;', $start) - $start);
	pg_assert_contains("UPDATE page_bundle SET publish_at = ?", $busy, 'the time read is put back');
	pg_assert_contains("AND status = 'scheduled' AND publish_at = 0", $busy, 'only while the bundle still waits with no time');
}

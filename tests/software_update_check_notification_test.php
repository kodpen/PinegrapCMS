<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for software_update_check.php: the update server's answer
 * replaces the "Software update available" notification instead of adding
 * one more row on every check and every opening of the Software Updater.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The part of the check that reads the Kodpen API's answer, from its
// decision up to the config write.
function software_update_check_answer_branch()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/software_update_check.php');
	$start = strpos($source, "\$decision = \$server['decision'];");
	pg_assert_true($start !== false, 'answer branch found');
	$end = strpos($source, "db(\"UPDATE config SET software_update_available = '\" . (\$flag ? 1 : 0) . \"'\");", (int) $start);
	pg_assert_true($end !== false, 'answer branch ends with the config write');

	return substr($source, (int) $start, (int) $end - (int) $start);
}

function test_software_update_check_settles_one_notification()
{
	$branch = software_update_check_answer_branch();

	pg_assert_contains('pg_update_notice_set($flag);', $branch, 'the answer settles the notification');
	pg_assert_false(strpos($branch, 'create_notification(') !== false, 'no row written beside the kept one');
	pg_assert_false(strpos($branch, 'DELETE FROM notifications') !== false, 'the kept row is not deleted first');

	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/software_update_check.php');
	pg_assert_false(strpos($source, 'users_messages_xref') !== false, 'no api2 message feed');
	pg_assert_false(strpos($source, "db(\"DELETE FROM notifications WHERE action = 'software_update'\");") !== false, 'rows go with their read marks');
}

// A check that finds the update again keeps the row it wrote, read or not;
// only extra rows go, and an answer offering nothing takes them all.
function test_software_update_notice_plan()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

	pg_assert_same(array('create' => true, 'delete' => array()), pg_update_notice_plan(true, array()), 'first offer writes one row');
	pg_assert_same(array('create' => false, 'delete' => array()), pg_update_notice_plan(true, array('12')), 'the next check keeps it');
	pg_assert_same(array('create' => false, 'delete' => array(9, 4)), pg_update_notice_plan(true, array(4, '12', 9, 12, 0)), 'the newest stays, extras go');
	pg_assert_same(array('create' => false, 'delete' => array(12, 4)), pg_update_notice_plan(false, array(4, 12)), 'nothing offered takes them all');
	pg_assert_same(array('create' => false, 'delete' => array()), pg_update_notice_plan(false, null), 'nothing there, nothing to do');
}

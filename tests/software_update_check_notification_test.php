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

// The part of the check that reads a version answer of the update server,
// from the decoded response up to the config write.
function software_update_check_answer_branch()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/software_update_check.php');
	$start = strpos($source, "\$details['response'] = \$response;");
	pg_assert_true($start !== false, 'answer branch found');
	$end = strpos($source, "db(\"UPDATE config SET software_update_available = '\" . \$software_update_available . \"'\");", (int) $start);
	pg_assert_true($end !== false, 'answer branch ends with the config write');

	return substr($source, (int) $start, (int) $end - (int) $start);
}

function test_software_update_check_clears_the_notification_before_it_adds_one()
{
	$branch = software_update_check_answer_branch();
	$delete = strpos($branch, "db(\"DELETE FROM notifications WHERE action = 'software_update'\");");
	$create = strpos($branch, "create_notification(array('action' => 'software_update'");

	pg_assert_true($delete !== false, 'the answer clears the old notification');
	pg_assert_true($create !== false, 'the answer still announces an update');
	pg_assert_true(($delete !== false) && ($create !== false) && ($delete < $create), 'cleared before the new one is written');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screens the bell, the device banner and System Status link
 * to: 'Software files were changed' opens Repair Software only for an
 * administrator on a site that is not hosted (pg_software_repair_open_to(),
 * pg_ops_alert_reader_url(), includes/fn/ops_alerts.php), and 'A custom form
 * was submitted' reaches only whoever may edit the form's folder
 * (pg_notification_form_visible(), includes/notifications.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

// Repair Software is an administrator's screen and closed on a hosted site.
function test_notification_links_software_repair_open_to()
{
	pg_assert_true(pg_software_repair_open_to(0, false), 'administrator');
	pg_assert_true(pg_software_repair_open_to('0', false), 'role as read from the user row');
	pg_assert_false(pg_software_repair_open_to(0, true), 'hosted site');
	pg_assert_false(pg_software_repair_open_to(1, false), 'designer');
	pg_assert_false(pg_software_repair_open_to(2, false), 'manager');
	pg_assert_false(pg_software_repair_open_to(null, false), 'a caller with no role');
	pg_assert_false(pg_software_repair_open_to('', false), 'an empty role');
}

// The integrity alert opens Repair Software for whoever may use it, the
// dashboard for everyone else; the other codes keep their own screen.
function test_notification_links_ops_reader_url()
{
	pg_assert_same('software_repair.php', pg_ops_alert_reader_url('integrity', 0, false));
	pg_assert_same('welcome.php', pg_ops_alert_reader_url('integrity', 2, false));
	pg_assert_same('welcome.php', pg_ops_alert_reader_url('integrity', 1, false));
	pg_assert_same('welcome.php', pg_ops_alert_reader_url('integrity', 0, true));
	pg_assert_same('welcome.php', pg_ops_alert_reader_url('integrity', null, false));
	pg_assert_same('backups.php', pg_ops_alert_reader_url('backup_stale', 2, false));
	pg_assert_same('welcome.php', pg_ops_alert_reader_url('no_such_code', 0, false));
}

// The bell row and the banner take the reader's role.
function test_notification_links_display_takes_the_reader()
{
	$row = array('action' => 'ops', 'order_total' => 'integrity', 'title' => 'integrity');

	pg_assert_same('software_repair.php', pg_notification_display($row, 0)['url'], 'administrator');
	pg_assert_same('welcome.php', pg_notification_display($row, 2)['url'], 'manager');
	pg_assert_same('welcome.php', pg_notification_display($row)['url'], 'no reader');
}

// The panel's bell and banner pass the reader; the System Status chip drops
// its link where the screen would refuse.
function test_notification_links_callers_pass_the_reader()
{
	$panel = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');
	pg_assert_same(2, substr_count($panel, "pg_notification_display(\$notification, \$user['role'])"), 'bell and banner');

	$widget = file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_2.php');
	pg_assert_true(strpos($widget, "pg_software_repair_open_to(\$user['role'], pg_hosted())") !== false, 'System Status chip');
}

// A form submission is for whoever may edit the form's folder; a manager
// and above passes without a query.
function test_notification_links_form_submitted_folder()
{
	$rights = array('id' => 5, 'role' => 2, 'manage_forms' => true, 'manage_ecommerce' => false, 'manage_erp' => false);

	if (defined('FORMS') && (FORMS === true)) {
		pg_assert_true(pg_notification_visible(array('action' => 'form_submited', 'form_id' => 9), $rights));
	}

	pg_assert_true(pg_notification_form_visible(array('form_id' => 9), $rights));

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/notifications.php');
	pg_assert_true(strpos($source, '$rights[\'manage_forms\'] && pg_notification_form_visible($notification, $rights))') !== false, 'ladder branch');
	pg_assert_true(strpos($source, "'notifications.id, notifications.action, notifications.comment_id, notifications.form_id'") !== false, 'badge columns');
	pg_assert_true(strpos($source, "'id, action, comment_id, form_id, readed'") !== false, 'badge columns before the reads table');
	pg_assert_true(strpos($source, "'id, action, comment_id, form_id'") !== false, 'ladder columns');

	$core = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	pg_assert_true(strpos($core, "'form_id'    => \$form_id") !== false, 'push queue row');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the operations alerts (includes/fn/ops_alerts.php): who an alert
 * e-mail goes to, the once-a-day rule, the stored detail, the state file's
 * shape, when a backup is stale, which scheduled jobs count as stopped - and
 * pg_backup_newest() (includes/fn/backup.php), the one reading of the backup
 * folder the alert and the System Status tile share.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A typed list is used as typed: any separator, repeats and invalid entries out.
function test_ops_alerts_recipients_typed_list()
{
	pg_assert_same(
		array('a@example.com', 'b@example.com', 'c@example.com'),
		pg_ops_alert_recipients(" a@example.com, b@example.com;c@example.com  A@EXAMPLE.COM, not-an-address ", array('site@example.com'))
	);
}

// Nothing typed: the first valid fallback alone, never the whole list.
function test_ops_alerts_recipients_fallback_is_one_address()
{
	pg_assert_same(array('site@example.com'), pg_ops_alert_recipients('', array('', 'broken', 'site@example.com', 'other@example.com')));
	pg_assert_same(array(), pg_ops_alert_recipients('   ', array('')));
}

// The settings screen refuses what is not an address, and only that.
function test_ops_alerts_recipients_invalid_entries()
{
	pg_assert_same(array('nobody', 'x@'), pg_ops_alert_recipients_invalid('a@example.com, nobody; x@ ,'));
	pg_assert_same(array(), pg_ops_alert_recipients_invalid(''));
	pg_assert_same(array(), pg_ops_alert_recipients_invalid('a@example.com b@example.com'));
}

// Once per code per day; a clock set back does not hold an alert for ever.
function test_ops_alerts_due_once_a_day()
{
	$now = 2000000000;

	pg_assert_true(pg_ops_alert_due(0, $now));
	pg_assert_false(pg_ops_alert_due($now - 3600, $now));
	pg_assert_false(pg_ops_alert_due($now - 86399, $now));
	pg_assert_true(pg_ops_alert_due($now - 86400, $now));
	pg_assert_false(pg_ops_alert_due($now + 3600, $now));
	pg_assert_true(pg_ops_alert_due($now + 90000, $now));
	pg_assert_true(pg_ops_alert_due($now - 700, $now, 600));
}

// The detail is plain text on one line, short enough for notifications.title.
function test_ops_alerts_clean_detail()
{
	pg_assert_same('Disk <full> & more', pg_ops_alert_clean_detail("<b>Disk</b>\n  &lt;full&gt; &amp;\tmore "));

	$long = pg_ops_alert_clean_detail(str_repeat('ş', 400));

	pg_assert_same(250, mb_strlen($long, 'UTF-8'));
	pg_assert_true(strlen($long) <= 1000);
	pg_assert_same('…', mb_substr($long, -1, 1, 'UTF-8'));

	pg_assert_same('', pg_ops_alert_clean_detail('   '));
}

// Whatever the file held, the state has its keys, typed, and no foreign codes.
function test_ops_alerts_state_normalize()
{
	pg_assert_same(array('sent' => array(), 'pass' => 0), pg_ops_alert_state_normalize(null));
	pg_assert_same(array('sent' => array(), 'pass' => 0), pg_ops_alert_state_normalize('garbage'));

	$state = pg_ops_alert_state_normalize(array(
		'sent' => array('backup_stale' => '1700000000', '../x' => 5, 'Upper' => 3, 7 => 9),
		'pass' => '1700000500',
		'other' => 'kept',
	));

	pg_assert_same(array('backup_stale' => 1700000000), $state['sent']);
	pg_assert_same(1700000500, $state['pass']);
	pg_assert_same('kept', $state['other']);
}

// A backup is stale after eight days; never having one is not this alert.
function test_ops_alerts_backup_stale()
{
	$now = 2000000000;

	pg_assert_false(pg_ops_backup_stale(0, $now));
	pg_assert_false(pg_ops_backup_stale($now - (7 * 86400), $now));
	pg_assert_false(pg_ops_backup_stale($now - 691200, $now));
	pg_assert_true(pg_ops_backup_stale($now - 691201, $now));
}

// A catalogue shaped like pg_cron_jobs(), without its lang() calls.
function pg_test_ops_alerts_jobs()
{
	return array(
		'job'             => array('stale_after' => 7200, 'dispatch' => false),
		'auto_backup'     => array('stale_after' => 1209600, 'dispatch' => true),
		'api_webhook_job' => array('stale_after' => 3600, 'dispatch' => true, 'inline' => true),
		'mail_job'        => array('stale_after' => 3600, 'dispatch' => false, 'inline' => true),
		'seo_score_job'   => array('stale_after' => 172800, 'dispatch' => true),
	);
}

// A stopped general job is the only answer: the rest stopped because it did.
function test_ops_alerts_cron_general_job_first()
{
	$now = 2000000000;

	$runs = array(
		'job'             => $now - 7201,
		'api_webhook_job' => $now - 7201,
		'mail_job'        => $now - 7201,
	);

	pg_assert_same(array('job'), pg_ops_cron_stalled(pg_test_ops_alerts_jobs(), $runs, $now, array('api_webhook_job'), true));

	$runs['job'] = $now - 7200;

	pg_assert_same(array('api_webhook_job', 'mail_job'), pg_ops_cron_stalled(pg_test_ops_alerts_jobs(), $runs, $now, array('api_webhook_job'), true));
}

// Never run is not a failure; a job silent for a week past its limit is retired.
function test_ops_alerts_cron_never_and_retired()
{
	$now = 2000000000;
	$jobs = pg_test_ops_alerts_jobs();

	pg_assert_same(array(), pg_ops_cron_stalled($jobs, array(), $now, array(), false));
	pg_assert_same(array(), pg_ops_cron_stalled($jobs, array('job' => $now - 7200 - 604801), $now, array(), false));
	pg_assert_same(array('job'), pg_ops_cron_stalled($jobs, array('job' => $now - 7200 - 604800), $now, array(), false));
}

// An inline job counts while it is switched on; the mail pass always is.
function test_ops_alerts_cron_inline_switch()
{
	$now = 2000000000;
	$jobs = pg_test_ops_alerts_jobs();

	$runs = array(
		'job'             => $now - 60,
		'api_webhook_job' => $now - 4000,
		'mail_job'        => $now - 4000,
	);

	pg_assert_same(array('mail_job'), pg_ops_cron_stalled($jobs, $runs, $now, array(), true));
	pg_assert_same(array('mail_job'), pg_ops_cron_stalled($jobs, $runs, $now, array(), false));
	pg_assert_same(array('api_webhook_job', 'mail_job'), pg_ops_cron_stalled($jobs, $runs, $now, array('api_webhook_job'), true));
}

// A dispatched job counts when it is selected, or while dispatching is off
// (then it has a schedule of its own); deselected, it was switched off.
function test_ops_alerts_cron_dispatched_selection()
{
	$now = 2000000000;
	$jobs = pg_test_ops_alerts_jobs();

	$runs = array(
		'job'           => $now - 60,
		'seo_score_job' => $now - 172801,
	);

	pg_assert_same(array('seo_score_job'), pg_ops_cron_stalled($jobs, $runs, $now, array('seo_score_job'), true));
	pg_assert_same(array(), pg_ops_cron_stalled($jobs, $runs, $now, array('auto_backup'), true));
	pg_assert_same(array('seo_score_job'), pg_ops_cron_stalled($jobs, $runs, $now, array(), false));
}

// The general job's limit is two hours, the number the alert and the System
// Status tile read from the same catalogue.
function test_ops_alerts_general_job_limit_in_catalogue()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/cron.php');

	pg_assert_true(preg_match("/'job' => array\\(\\s*'label'\\s*=> lang\\('General job'\\),\\s*'script'\\s*=> 'job\\.php',\\s*'interval'\\s*=> 60,\\s*'stale_after' => 7200,/", $source) === 1);
}

// The folder reading: sub-folders and automatic archives count, the install
// fixtures and stray files do not, and the newest stamp wins.
function test_ops_alerts_backup_newest_reads_the_folder()
{
	$directory = sys_get_temp_dir() . '/pg_backup_newest_' . getmypid() . '_' . mt_rand(1000, 9999);

	pg_assert_same(array('count' => 0, 'newest' => 0), pg_backup_newest($directory));

	mkdir($directory);
	mkdir($directory . '/english_default');
	mkdir($directory . '/manual_backup');
	file_put_contents($directory . '/auto_backup_2026-10-42.zip', 'x');
	file_put_contents($directory . '/notes.txt', 'x');

	touch($directory . '/english_default', 1900000000);
	touch($directory . '/manual_backup', 1700000000);
	touch($directory . '/auto_backup_2026-10-42.zip', 1800000000);
	touch($directory . '/notes.txt', 1950000000);

	$found = pg_backup_newest($directory);

	unlink($directory . '/auto_backup_2026-10-42.zip');
	unlink($directory . '/notes.txt');
	rmdir($directory . '/manual_backup');
	rmdir($directory . '/english_default');
	rmdir($directory);

	pg_assert_same(array('count' => 2, 'newest' => 1800000000), $found);
}

// An alert row reaches the bell of a manager and above, nobody below.
function test_ops_alerts_notification_visibility()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

	$rights = function ($role) {
		return array('id' => 5, 'role' => $role, 'manage_ecommerce' => true, 'manage_forms' => true, 'manage_erp' => true, 'manage_erp_cash' => true);
	};

	pg_assert_true(pg_notification_visible(array('action' => 'ops'), $rights(0)));
	pg_assert_true(pg_notification_visible(array('action' => 'ops'), $rights(2)));
	pg_assert_false(pg_notification_visible(array('action' => 'ops'), $rights(3)));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance, the Scheduled jobs row opened: the
 * general job's command on one line, then one row a job with its line, its
 * schedule, its switch and the numbered steps of its own task; the names the
 * save reads stay.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_cron_list.php');

function test_settings_maintenance_cron_command()
{
	pg_assert_same('/usr/local/bin/php -q /srv/pg/job.php >/dev/null 2>&1', pg_maintenance_cron_command('Linux', '/srv/pg', 'job.php'), 'a unix-like server');
	pg_assert_same('/usr/local/bin/php -q /srv/pg/auto_backup.php >/dev/null 2>&1', pg_maintenance_cron_command('BSD', '/srv/pg', 'auto_backup.php'), 'every family but Windows takes the same form');
	pg_assert_same('C:\PHP\php.exe -q D:\pg\job.php', pg_maintenance_cron_command('Windows', 'D:\pg', 'job.php'), 'Windows');
}

function test_settings_maintenance_cron_every_job_has_a_line()
{
	foreach (pg_cron_jobs() as $name => $job) {
		if (empty($job['dispatch'])) {
			continue;
		}

		$line = pg_maintenance_cron_job_line($name);
		pg_assert_true($line !== '', $name . ': one line of what it does');
		pg_assert_true(mb_strlen($line) <= 110, $name . ': one short line');
		pg_assert_same(false, strpos(rtrim($line, '.'), '. '), $name . ': one sentence');
	}

	pg_assert_same('', pg_maintenance_cron_job_line('no_such_job'), 'an unknown job has no line');
}

function test_settings_maintenance_cron_rows()
{
	$jobs = array(
		'job'    => array('label' => 'General job', 'interval' => 60, 'dispatch' => false),
		'backup' => array('label' => 'Auto backup', 'interval' => 604800, 'dispatch' => true),
		'hook'   => array('label' => 'Webhook delivery', 'interval' => 60, 'dispatch' => true, 'inline' => true),
		'seo'    => array('label' => 'SEO scores', 'interval' => 86400, 'dispatch' => true),
	);

	$rows = pg_maintenance_cron_rows($jobs, array('seo', 'gone'));

	pg_assert_same(array('backup', 'hook', 'seo'), array_keys($rows), 'the jobs with a switch, in the catalogue\'s order');
	pg_assert_same(false, $rows['backup']['on'], 'not selected');
	pg_assert_same(true, $rows['seo']['on'], 'selected');
	pg_assert_same('Auto backup', $rows['backup']['name'], 'its name');
	pg_assert_same('Recommended: once a week', $rows['backup']['schedule'], 'its schedule');
	pg_assert_same('With every run of the general job', $rows['hook']['schedule'], 'an inline job runs on every run');
	pg_assert_same('pgset-cron-auto-backup-guide', pg_maintenance_cron_guide_id('auto_backup'), 'the drawer id');
}

function test_settings_maintenance_cron_steps()
{
	$job = array('label' => 'SEO structure', 'interval' => 86400, 'dispatch' => true);
	$steps = pg_maintenance_cron_steps('seo_analyze_job', $job, 'Linux', '/usr/local/bin/php -q /x/seo_analyze_job.php');

	pg_assert_true(count($steps) >= 4, 'open, add, schedule, its note and the general job');

	$commands = array();

	foreach ($steps as $step) {
		pg_assert_true(($step['title'] ?? '') !== '', 'every step has a title');
		pg_assert_true(mb_strlen($step['text'] ?? '') <= 160, 'a short step: ' . ($step['title'] ?? ''));

		if (!empty($step['command'])) {
			$commands[] = $step['command'];
		}
	}

	pg_assert_same(array('/usr/local/bin/php -q /x/seo_analyze_job.php'), $commands, 'the command once, in its own step');
	pg_assert_contains('once a day', $steps[2]['text'], 'the schedule step names it');
	pg_assert_contains('SEO score', implode(' ', array_column($steps, 'text')), 'its note: after the SEO score job');
	pg_assert_contains('Task Scheduler', pg_maintenance_cron_steps('seo_analyze_job', $job, 'Windows', 'x')[0]['text'], 'Windows says where');

	$inline = pg_maintenance_cron_steps('api_webhook_job', array('label' => 'Webhook delivery', 'interval' => 60, 'dispatch' => true, 'inline' => true), 'Linux', 'x');
	pg_assert_same('Usually not needed', $inline[0]['title'], 'an inline job first says it rarely needs a task');
}

function test_settings_maintenance_cron_list_markup()
{
	$rows = array(
		'seo_score_job' => array('name' => 'SEO scores', 'line' => 'Line.', 'schedule' => 'Recommended: once a day', 'on' => true, 'active' => true),
		'email_campaign_job' => array('name' => 'Email <Campaigns>', 'line' => 'Line.', 'schedule' => 'Recommended: every 5 minutes', 'on' => false, 'active' => false),
	);

	$html = pg_maintenance_cron_list_html($rows, false);

	pg_assert_contains('name="job_dispatch_job[seo_score_job]"', $html, 'the field name the save reads');
	pg_assert_contains('id="job_dispatch_job_seo_score_job"', $html, 'its id');
	pg_assert_contains('value="1" checked="checked"', $html, 'a selected job stays selected');
	pg_assert_contains('data-pg-cron-switch class="d-none', $html, 'the switch hidden while the master is off');
	pg_assert_same(false, strpos($html, 'disabled="'), 'never disabled: the selection stays posted');
	pg_assert_contains('Email &lt;Campaigns&gt;', $html, 'names escaped');
	pg_assert_contains('disabled in config.php', $html, 'a job config.php has switched off says so');
	pg_assert_contains('data-bs-target="#pgset-cron-seo-score-job-guide"', $html, 'How to schedule it opens its drawer');
	pg_assert_same(false, strpos(pg_maintenance_cron_list_html($rows, true), 'data-pg-cron-switch class="d-none'), 'shown while the master is on');
}

function test_settings_maintenance_cron_drawer()
{
	$job = array('label' => 'Auto backup', 'interval' => 604800, 'dispatch' => true);
	$html = pg_maintenance_cron_guide_drawer('auto_backup', $job, 'Linux', '/usr/local/bin/php -q /x/auto_backup.php', true, 'Enable it.');

	pg_assert_contains('id="pgset-cron-auto-backup-guide"', $html, 'its id');
	pg_assert_contains('stepper pg-stepper-cards', $html, 'the numbered steps');
	pg_assert_contains('data-pg-copy="/usr/local/bin/php -q /x/auto_backup.php"', $html, 'Copy');
	pg_assert_contains('Enable it.', $html, 'a missing extension at the top');
	pg_assert_contains('data-pgdrawer-close', $html, 'Close inside the dialog');
}

function test_settings_maintenance_cron_card()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/maintenance.php');

	pg_assert_contains('name="job_dispatch_enabled"', $card, 'the master keeps its name');
	pg_assert_contains("lang('Run the jobs with the general job')", $card, 'the master\'s label');
	pg_assert_contains('pg_maintenance_cron_list_html(', $card, 'the list');
	pg_assert_contains('pg_maintenance_cron_guide_drawer(', $card, 'a drawer a job');
	pg_assert_same(false, strpos($card, "pg_settings_drawer('cron_jobs'"), 'the long instructions drawer is gone');
	pg_assert_same(false, strpos($card, 'pg_code_editor_attach(array(\'id\' => \'cron_job_general_dispatch\''), 'the command is a line, not an editor');
}

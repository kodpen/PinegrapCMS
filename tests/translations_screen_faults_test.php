<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen (translations.php, translations_action.php,
 * assets/js/translations.js, includes/translate/jobs.php): a server engine's
 * job worked by one run at a time. Checked by its source where the path
 * needs the database or a browser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');

// The body of function $name in $file, up to the next top-level function.
function translations_faults_test_body($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' exists in ' . $file);
	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function translations_faults_test_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/translations.js');
}

// A job's lock is named by the job and the database, so two sites on one
// database server do not hold each other's jobs; the name stays within the
// 64 characters GET_LOCK takes.
function test_translations_job_lock_name()
{
	pg_assert_same("CONCAT('pg_tr_job_', MD5(CONCAT(DATABASE(), ':', 12)))", pg_tr_job_lock_sql(12), 'job 12');
	pg_assert_same("CONCAT('pg_tr_job_', MD5(CONCAT(DATABASE(), ':', 0)))", pg_tr_job_lock_sql('12; DROP'), 'only a number reaches the SQL');
	pg_assert_true(strlen('pg_tr_job_') + 32 <= 64, 'within the lock name limit');
}

// The screen's run calls and the scheduled job (every five minutes) work the
// same open job: without a lock both read the same waiting texts and send
// each to the paid engine twice. A run that finds the job taken works
// nothing and says so.
function test_translations_server_job_runs_one_at_a_time()
{
	$body = translations_faults_test_body('includes/translate/jobs.php', 'pg_tr_job_run_server');
	$lock = strpos($body, 'GET_LOCK(');
	$work = strpos($body, 'pg_tr_job_waiting(');

	pg_assert_true($lock !== false, 'the run takes the job\'s lock');
	pg_assert_true(($lock !== false) && ($work !== false) && ($lock < $work), 'before it reads the waiting texts');
	pg_assert_contains("\$result['busy'] = true;", $body, 'a run that finds the job taken says so');
	pg_assert_contains('RELEASE_LOCK(', $body, 'the lock is let go when the run ends');
	pg_assert_contains("'busy' => false", $body, 'a run answers busy false by default');
}

// The screen waits a few seconds and looks again while another run works
// the job, instead of asking again at once.
function test_translations_screen_waits_while_the_job_is_busy()
{
	$js = translations_faults_test_js();

	pg_assert_true((bool) preg_match('/if \(data\.busy\) \{[^}]*window\.setTimeout\(resolve, 5000\)/', $js), 'a busy answer waits five seconds');
}

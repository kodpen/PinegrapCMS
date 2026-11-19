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

// A row's review button on a box with typed, unsaved text: the click marked
// the stored text reviewed and cleared the row's unsaved mark, so the typed
// wording was lost on the next load and the old one went out as reviewed.
// An edited row is saved with the new status instead.
function test_translations_review_on_an_edited_row_saves_the_typed_text()
{
	$js = translations_faults_test_js();
	$start = strpos($js, "var reviewRow = review.closest('.pg-tr-row');");
	pg_assert_true($start !== false, 'the review click exists');
	$branch = substr($js, (int) $start, 600);

	pg_assert_contains("classList.contains('pg-tr-dirty')", $branch, 'the review click looks for unsaved text first');
	pg_assert_contains('saveRow(reviewRow, review, next)', $branch, 'and saves it with the new status');
	pg_assert_true((bool) preg_match("/function saveRow\\(row, button, status\\) \\{[\\s\\S]*?action: 'save'[\\s\\S]*?status: status/", $js), 'the save sends the status it is given');
}

// A CSV row whose translation is the one already stored is left as it is.
function test_translations_csv_import_unchanged_row()
{
	pg_assert_true(pg_tr_csv_import_unchanged('Merhaba', 'Merhaba'), 'the same text');
	pg_assert_true(!pg_tr_csv_import_unchanged('Merhaba', 'Merhaba dünya'), 'an edited text');
	pg_assert_true(!pg_tr_csv_import_unchanged(null, 'Merhaba'), 'no translation stored yet');
	pg_assert_true(!pg_tr_csv_import_unchanged('Merhaba ', 'Merhaba'), 'a changed space is a change');
}

// The export lists every text with its translation, so a file sent back
// after fixing a few rows carries every other row untouched. The import
// wrote them all again: by default each untouched machine translation was
// marked reviewed (the Machine filter emptied without anyone reading them),
// with "Import as machine translations" each reviewed one went back to
// machine, and every row was relabelled as a CSV import. Untouched rows are
// now counted as unchanged and not written.
function test_translations_csv_import_leaves_unchanged_rows()
{
	$body = translations_faults_test_body('includes/translate/jobs.php', 'pg_tr_csv_import');
	$same = strpos($body, 'pg_tr_csv_import_unchanged(');
	$write = strpos($body, 'pg_tr_save_translation(');

	pg_assert_true(($same !== false) && ($write !== false) && ($same < $write), 'an unchanged row is found before anything is written');
	pg_assert_contains("\$summary['unchanged']++;", $body, 'and counted');
	pg_assert_contains("'unchanged' => 0", $body, 'the summary has the count');

	$action = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations_action.php');
	pg_assert_contains("{var:2} unchanged", $action, 'the import notice names the unchanged rows');
	pg_assert_contains("\$summary['unchanged']", $action, 'with their count');
}

// Typed, unsaved translations were lost without a word on a filter chip, a
// page link, a language switch or the screen's own reload after a job. The
// browser now asks while a row is marked unsaved.
function test_translations_screen_asks_before_leaving_unsaved_rows()
{
	$js = translations_faults_test_js();

	pg_assert_true((bool) preg_match("/addEventListener\\('beforeunload', function \\(event\\) \\{[\\s\\S]{0,300}?querySelector\\('\\.pg-tr-row\\.pg-tr-dirty'\\)[\\s\\S]{0,200}?event\\.preventDefault\\(\\);[\\s\\S]{0,80}?event\\.returnValue = '';/", $js), 'a row with unsaved text makes the browser ask');
}

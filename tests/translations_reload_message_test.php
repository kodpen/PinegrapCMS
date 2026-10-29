<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen (translations.php, assets/js/translations.js),
 * checked by its source: every message shown right before the screen
 * reloads is stored for the next load and shown again there, a job's error
 * comes back as an error, and a recent job's error is printed as visible
 * text in its row instead of a title on a hidden icon.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function translations_reload_message_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/translations.js');
}

// The helper writes sessionStorage inside try/catch and the next load reads
// it once, removes it and shows it with toast().
function test_translations_reload_message_helper_and_read()
{
	$js = translations_reload_message_js();

	pg_assert_true((bool) preg_match('/function afterReload\(message, kind\) \{\s*try \{\s*window\.sessionStorage\.setItem\(/', $js), 'afterReload() writes sessionStorage inside try');
	pg_assert_true((bool) preg_match('/sessionStorage\.getItem\([^)]*\);\s*if \(pending\) \{\s*window\.sessionStorage\.removeItem\(/', $js), 'the next load reads the message once');
	pg_assert_contains('toast(pending.m, pending.k)', $js, 'the stored message is shown with its kind');
}

// Every timed reload that follows a message stores that message first.
function test_translations_reload_message_every_timed_reload_stores_its_message()
{
	$js = translations_reload_message_js();

	pg_assert_same(0, preg_match_all('/toast\(([^;]+)\);\s*window\.setTimeout\(function \(\) \{ window\.location\.reload\(\); \}, 800\);/', $js), 'no toast is followed straight by a reload without storing it');
	pg_assert_same(5, preg_match_all('/toast\(([^;]+)\);\s*afterReload\(\1\);\s*window\.setTimeout\(function \(\) \{ window\.location\.reload\(\); \}, 800\);/', $js), 'nothing pending, pending by hand, Review All, Clear, Accept source');
}

// finish() stores the job's error, or the counts as an error when a text failed.
function test_translations_reload_message_finish_keeps_the_job_error()
{
	$js = translations_reload_message_js();

	pg_assert_true((bool) preg_match('/function finish\(done, failed, error\) \{/', $js), 'finish() takes the job error');
	pg_assert_contains("afterReload(error, 'error');", $js, 'a job error comes back as an error');
	pg_assert_contains("afterReload(text('finished', done, failed), (failed > 0) ? 'error' : 'success');", $js, 'failed texts come back as an error');
	pg_assert_same(2, substr_count($js, 'finish(running.done, running.failed, current.error);'), 'server and queue jobs pass their error');
	pg_assert_same(0, substr_count($js, "toast(current.error, 'error');"), 'no error toast lost in the reload');
}

// The recent jobs row prints the error as text, not as a hover title.
function test_translations_reload_message_job_error_is_visible_text()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');

	pg_assert_same(0, substr_count($php, 'title="\' . h($job[\'error\'])'), 'no title-only error');
	pg_assert_contains('<span class="fg-danger small text-break">\' . h($job[\'error\']) . \'</span>', $php, 'error printed as text');
}

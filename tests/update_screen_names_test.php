<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Software Updater, Repair Software and Backup Manager:
 * every update step ends with a message when its request fails, step text
 * sits below the progress bar, icon ligatures stay out of accessible names,
 * Return in Backup Name starts the backup and buttons keep their labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function update_screen_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_update_screen_steps_each_have_an_error_path()
{
	$source = update_screen_names_source('software_update.php');

	pg_assert_contains('function fail(message)', $source, 'update fail()');
	pg_assert_contains('function run(index)', $source, 'update run()');
	foreach (array('check', 'download', 'backup', 'replace') as $step) {
		pg_assert_contains('{ step: "' . $step . '"', $source, 'update step ' . $step);
	}
	pg_assert_same(1, substr_count($source, 'action: "software_update"'), 'one request for every step');
	pg_assert_same(1, substr_count($source, 'error: function()'), 'the one request has an error path');
}

function test_update_screen_button_keeps_label_and_icon()
{
	$source = update_screen_names_source('software_update.php');

	pg_assert_false(strpos($source, 'update_btn.html(') !== false, 'update button markup not replaced');
	pg_assert_false(strpos($source, 'update_btn.text(') !== false, 'update button label not replaced');
	pg_assert_false(strpos($source, 'Retry Update') !== false, 'no Retry Update label');
	pg_assert_contains('update_button.prop("disabled", false)', $source, 'update button enabled again');

	$repair = update_screen_names_source('software_repair.php');
	pg_assert_false(strpos($repair, "lang('Repairing')") !== false, 'repair label kept while it works');
	pg_assert_false(strpos($repair, 'repair_label.text(') !== false, 'repair label never replaced');

	$update_fn = update_screen_names_source('includes/fn/update.php');
	pg_assert_false(strpos($update_fn, 'data-busy-label') !== false, 'permissions button label kept');
}

function test_update_screen_step_text_below_the_bar()
{
	foreach (array('software_update.php', 'software_repair.php') as $path) {
		$source = update_screen_names_source($path);

		pg_assert_false(
			(bool) preg_match('/class="progress-bar[^"]*"[^>]*>\s*<span class="logbox/', $source),
			$path . ' log text outside the bar'
		);
		pg_assert_true(
			(bool) preg_match('/<div class="logbox[^"]*" role="status"><\/div>/', $source),
			$path . ' log line is a status'
		);
		pg_assert_contains('.attr("aria-valuenow", ', $source, $path . ' aria-valuenow follows the width');
	}

	$backend = update_screen_names_source('assets/js/backend.src.js');
	pg_assert_contains('Progress.attr("style", "width: " + percent + "%").attr("aria-valuenow", percent)', $backend, 'backup bar value');
	pg_assert_contains('<div class="logbox form-text" role="status"></div>', update_screen_names_source('backups.php'), 'backup log line is a status');
}

function test_update_screen_ligatures_hidden()
{
	$source = update_screen_names_source('software_update.php');

	foreach (array('browser_updated', 'arrow_right_alt', 'sync') as $ligature) {
		pg_assert_true(
			(bool) preg_match('/<span class="[^"]*material-icons[^"]*"[^>]*aria-hidden="true"[^>]*>' . $ligature . '<\/span>/', $source),
			$ligature . ' ligature aria-hidden'
		);
	}
}

function test_backup_name_return_starts_the_backup()
{
	$source = update_screen_names_source('backups.php');

	pg_assert_false(strpos($source, 'addEventListener("keyup"') !== false, 'no keyup Return handler');
	pg_assert_contains('addEventListener("keydown"', $source, 'Return caught on keydown');
	pg_assert_contains('event.isComposing', $source, 'composition left alone');

	$backend = update_screen_names_source('assets/js/backend.src.js');
	pg_assert_false(strpos($backend, '$("#backup_folder_name").on("keyup"') !== false, 'no second Return handler');
	pg_assert_false(strpos($backend, '"Unexpected error"') !== false, 'error text translated');
	pg_assert_false(strpos($backend, 'lang("Backing up")') !== false, 'backup label kept while it works');
	pg_assert_false(strpos($backend, 'lang("Retry Backup")') !== false, 'no Retry Backup label');
}

function test_backup_rows_name_their_backup_and_times_follow_the_user()
{
	$source = update_screen_names_source('backups.php');

	pg_assert_contains("'string' => 'Download {var:1}'", $source, 'download button named by backup');
	pg_assert_contains("'string' => 'Delete {var:1}'", $source, 'delete button named by backup');
	pg_assert_true(
		(bool) preg_match('/<i class="bi bi-download" aria-hidden="true"><\/i>/', $source),
		'download icon hidden'
	);

	foreach (array('backups.php', 'software_repair.php') as $path) {
		pg_assert_false(strpos(update_screen_names_source($path), "date('d.m.Y H:i'") !== false, $path . ' no fixed time format');
	}
}

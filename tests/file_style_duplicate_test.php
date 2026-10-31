<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two panel Duplicate buttons: Edit File's copy keeps the
 * picture's optimisation flag and cached size the way the File Manager's
 * copy does, and Edit Custom Page Style tells the user when they are on the
 * copy and when Save worked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a panel screen.
function file_style_duplicate_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The DUPLICATE branch of edit_file.php, from its copy() to its redirect.
function file_style_duplicate_edit_file_branch()
{
	$source = file_style_duplicate_source('edit_file.php');
	$start = strpos($source, "copy(FILE_DIRECTORY_PATH . '/' . \$original_name, \$new_file_path);");
	$end = strpos($source, "A new copy of the file has been created.", (int) $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the Duplicate branch of Edit File is found');

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

function test_edit_file_duplicate_reads_the_optimisation_columns()
{
	$branch = file_style_duplicate_edit_file_branch();

	pg_assert_contains('SELECT folder, description, design, optimized, image_width, image_height, optimization_percent FROM files', $branch, 'the source row is read with its optimisation columns');
}

function test_edit_file_duplicate_writes_the_optimisation_columns()
{
	$branch = file_style_duplicate_edit_file_branch();

	pg_assert_same(1, preg_match('~INSERT INTO files \(\s*name, folder, description, type, size, design, optimized, image_width, image_height, optimization_percent, user, timestamp\s*\)~', $branch), 'the copy carries optimized, image_width, image_height and optimization_percent');

	pg_assert_contains("\$sql_optimization = \"'\" . (int) \$row['optimized'] . \"', \";", $branch, 'the optimized flag is copied');
	pg_assert_contains("foreach (array('image_width', 'image_height', 'optimization_percent') as \$column) {", $branch, 'the cached size and saving are copied');
	pg_assert_contains("((\$row[\$column] !== null && \$row[\$column] !== '') ? \"'\" . escape(\$row[\$column]) . \"'\" : 'NULL')", $branch, 'an empty size or saving is written as NULL');
}

function test_duplicate_style_queues_the_duplicate_notice_for_the_custom_style_screen()
{
	$source = file_style_duplicate_source('duplicate_style.php');
	$notice = strpos($source, "->add_notice(lang(array('string' => 'The {var:1} has been duplicated, and you are now editing the duplicate.', 'vars' => lang('page style'))));");
	$redirect = strpos($source, "header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_' . \$style['type'] . '_style.php?id=' . \$new_style_id);");

	pg_assert_contains("new liveform('edit_custom_style')", $source, 'the notice waits for Edit Custom Page Style');
	pg_assert_true(($notice !== false) && ($redirect !== false) && ($notice < $redirect), 'the notice is queued before the redirect');
}

function test_edit_custom_style_prints_its_notices_and_clears_them()
{
	$source = file_style_duplicate_source('edit_custom_style.php');
	$get = (string) substr($source, 0, (int) strpos($source, 'validate_token_field();'));

	pg_assert_contains("\$liveform = new liveform('edit_custom_style');", $get, 'the screen reads its own notices');
	pg_assert_contains("' . \$liveform->output_notices() . '", $get, 'the notices are printed');
	pg_assert_contains('$liveform->remove_form();', $get, 'the notices are cleared once printed');
	pg_assert_true(strpos($get, '$liveform->output_notices()') > strpos($get, 'header-content-for-add-page'), 'the notices sit under the heading');
}

function test_edit_custom_style_save_queues_its_notice()
{
	$source = file_style_duplicate_source('edit_custom_style.php');
	$save = (string) substr($source, (int) strpos($source, "if ((\$_POST['submit_save'] ?? '') == 'Save') {"));
	$save = (string) substr($save, 0, (int) strpos($save, 'exit();'));

	pg_assert_contains("(new liveform('edit_custom_style'))->add_notice(\$notice);", $save, 'Save confirms on the screen it reloads');
}

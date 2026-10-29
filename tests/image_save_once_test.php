<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the two screens that turn a picture into file data in
 * the browser before posting it: the Image Editor's Save Option buttons
 * stay disabled until the edited image is in the form and send once, the
 * server refuses an empty image before it touches the original, and Edit
 * File's SVG conversion runs only for Save and Save & Return, once, through
 * requestSubmit() so the double-submit guard and the button name apply.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_save_once_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_image_editor_buttons_start_disabled()
{
	$source = image_save_once_source('image_editor_edit.php');

	pg_assert_true((bool) preg_match('/<button type="button" id="image_save_keep" [^>]*disabled/', $source), 'Keep Original And Save starts disabled');
	pg_assert_true((bool) preg_match('/<button type="button" id="image_save_replace" [^>]*disabled/', $source), 'Replace starts disabled');
	pg_assert_contains('id="image_save_status" role="status"', $source);
}

function test_image_editor_sends_through_submit_event()
{
	$source = image_save_once_source('image_editor_edit.php');

	// jQuery .submit() calls the native form.submit(): no submit event, so
	// the window guard never sees a second click.
	pg_assert_true(strpos($source, "\$(\\'form#image_form\\').submit()") === false, 'jQuery submit() on the image form');
	pg_assert_contains('form.requestSubmit()', $source);
	pg_assert_contains('.catch(function', $source);
	pg_assert_contains('e.originalEvent.persisted', $source);
}

function test_image_editor_server_refuses_empty_image()
{
	$source = image_save_once_source('image_editor_edit.php');
	$check = strpos($source, "trim(\$image_data) === ''");
	$unlink = strpos($source, "unlink(FILE_DIRECTORY_PATH . '/' . \$file_name)");

	pg_assert_true($check !== false, 'empty image_file refused');
	pg_assert_true($unlink !== false && $check < $unlink, 'refused before the original is deleted');
}

function test_svg_conversion_only_for_save_and_once()
{
	$source = image_save_once_source('edit_file.php');
	$start = strpos($source, 'getElementById("convert_svg_raster")');
	pg_assert_true($start !== false, 'conversion script');
	$script = substr($source, $start, strpos($source, '</script>', $start) - $start);

	pg_assert_contains('submit_save_and_return', $script);
	pg_assert_contains('e.submitter', $script);
	pg_assert_contains('form.requestSubmit(submitter)', $script);
	pg_assert_contains('if (busy)', $script);
	pg_assert_true(strpos($script, 'stopImmediatePropagation') === false, 'conversion hides the submit from the guard');
	// form.submit() drops the button name; only the fallback for browsers
	// without requestSubmit() may use it, after adding the name itself.
	$plain = strpos($script, 'form.submit()');
	pg_assert_true($plain === false || ($plain > strpos($script, 'field.name = submitter.name')), 'form.submit() without the button name');
}

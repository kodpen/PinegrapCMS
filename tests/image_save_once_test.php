<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Edit File, which turns a picture into file data in the
 * browser before posting it: its SVG conversion runs only for Save and
 * Save & Return, once, through requestSubmit() so the double-submit guard
 * and the button name apply.
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

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins: Add Theme File's CSS picker and Import Tracking Numbers' CSV
 * picker carry required, the theme file pickers' labels name their own
 * pickers, and the API application update refuses a blank name instead of
 * saving it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function upload_and_name_required_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function upload_and_name_required_input($source, $name)
{
	if (preg_match('/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/', $source, $match)) {
		return $match[0];
	}

	return '';
}

function test_upload_and_name_required_theme_css_picker()
{
	$source = upload_and_name_required_source('add_theme_file.php');
	$tag = upload_and_name_required_input($source, 'custom_css_file');

	pg_assert_true($tag !== '', 'custom_css_file picker not found');
	pg_assert_true(strpos($tag, 'required') !== false, 'custom_css_file picker without required');
	pg_assert_true(strpos($tag, 'accept=".css,text/css"') !== false, 'custom_css_file picker without accept');

	// The System branch sits in a disabled fieldset: its boxes stay optional.
	pg_assert_true(strpos(upload_and_name_required_input($source, 'system_theme_csv_file'), 'required') === false, 'system_theme_csv_file must stay optional');
}

function test_upload_and_name_required_theme_labels_name_their_pickers()
{
	$source = upload_and_name_required_source('add_theme_file.php');

	pg_assert_true(strpos($source, '<label class="form-label" for="custom_css_file">\' . lang(\'Local CSS Theme File\')') !== false, 'Local CSS Theme File label does not point at its picker');
	pg_assert_true(strpos($source, '<label class="form-label" for="system_theme_csv_file">\' . lang(\'Local CSV Theme File\')') !== false, 'Local CSV Theme File label does not point at its picker');
}

function test_upload_and_name_required_import_shipments_picker()
{
	$tag = upload_and_name_required_input(upload_and_name_required_source('import_shipments.php'), 'file');

	pg_assert_true($tag !== '', 'import_shipments file picker not found');
	pg_assert_true(strpos($tag, 'required') !== false, 'import_shipments file picker without required');
}

function test_upload_and_name_required_api_app_update_refuses_blank_name()
{
	$source = upload_and_name_required_source('api_settings.php');
	$start = strpos($source, "} elseif (\$action === 'update' && \$app_id > 0) {");
	$end = strpos($source, "} elseif (\$action === 'rotate'", (int) $start);

	pg_assert_true($start !== false && $end !== false, 'update branch not found');

	$branch = substr($source, $start, $end - $start);
	$check = strpos($branch, "add_error(lang('Application Name is a required field!'))");
	$write = strpos($branch, 'UPDATE api_apps SET');

	pg_assert_true($check !== false, 'update branch does not refuse a blank name');
	pg_assert_true($write !== false && $check < $write, 'blank name check must come before the write');
	pg_assert_true(strpos($branch, "escape(\$name)") !== false, 'update writes the checked name');
}

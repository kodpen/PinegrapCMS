<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the import and connect screens: the import file pickers
 * and the custom form select are marked required, Import Design's Website
 * URL carries required instead of a hand-written marker, the Migration
 * labels point at their own fields, and the source-site and marketplace
 * secret boxes keep the browser's saved panel sign-in out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function import_screen_fields_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_import_screen_fields_file_pickers_required()
{
	$liveform_screens = array(
		'import_products.php',
		'import_email_campaign_profiles.php',
		'import_submitted_forms.php',
		'import_users.php',
		'import_zip.php',
	);
	foreach ($liveform_screens as $path) {
		$source = import_screen_fields_source($path);
		pg_assert_true(
			(bool) preg_match("/output_field\(array\('type'=>'file'[^)]*'required'=>'required'/", $source),
			$path . ' marks its file picker required'
		);
	}

	foreach (array('import_contacts.php', 'import_key_codes.php') as $path) {
		$source = import_screen_fields_source($path);
		pg_assert_true(
			(bool) preg_match('/<input[^>]*type="file"[^>]*\srequired[\s>\/]/', $source),
			$path . ' marks its file picker required'
		);
	}

	$source = import_screen_fields_source('import_submitted_forms.php');
	pg_assert_contains("'name'=>'custom_form_page_id', 'id'=>'custom_form_page_id', 'class'=>'form-control', 'required'=>'required'", $source, 'the custom form select is marked required');
}

function test_import_screen_fields_design_url()
{
	$source = import_screen_fields_source('import_design.php');

	pg_assert_contains("'name'=>'url','id'=>'url','class'=>'form-control','required'=>'required','inputmode'=>'url'", $source);
	pg_assert_false(strpos($source, "lang('Website URL') . ' <span class=\"fg-danger\">*</span>") !== false, 'no hand-written marker beside the required one');
}

function test_import_screen_fields_migration()
{
	$source = import_screen_fields_source('migration.php');

	pg_assert_false(strpos($source, 'address_name') !== false, 'no label points at a missing id');
	pg_assert_contains('<label for="url" class="form-label">', $source);
	pg_assert_contains('<label class="form-label" for="password">', $source);
	pg_assert_contains("'name'=>'url', 'id'=>'url', 'value' => 'https://', 'class'=>'form-control', 'required'=>'required'", $source);
	pg_assert_contains("'name'=>'username', 'id'=>'username', 'class'=>'form-control', 'size'=>'30', 'required'=>'required', 'autocomplete'=>'new-password'", $source);
	pg_assert_contains("'name'=>'password', 'id'=>'password', 'class'=>'form-control', 'size'=>'30', 'required'=>'required', 'autocomplete'=>'new-password'", $source);
}

function test_import_screen_fields_marketplace_secret()
{
	$source = import_screen_fields_source('marketplace_settings.php');

	pg_assert_contains("(!empty(\$meta['secret']) ? 'new-password' : 'off')", $source, 'a secret box asks for no saved sign-in');
}

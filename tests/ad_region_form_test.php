<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ad region form shared by the create and the edit screens
 * (includes/forms/ad_region_form.php): the defaults a new region starts
 * with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');

function test_ad_region_form_defaults_only_on_create()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad_region.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad_region.php');
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');

	pg_assert_contains("maxlength=\"100\" />', true)", $add, 'create checks the defaults and keeps its plain name input');
	pg_assert_contains('pg_ad_region_form_card($liveform, $output_name_field_or_value)', $edit, 'edit shows the stored values');
	pg_assert_same(3, substr_count($form, '$checked + array('), 'static, slide and continuous play are the defaults');
}

function test_ad_region_form_screens_use_shared_form()
{
	foreach (array('add_ad_region.php', 'edit_ad_region.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/ad_region_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_ad_region_form_card($liveform, ', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_ad_region_form_validate_name($liveform, ', $source, $file . ' checks the name the shared way');
		pg_assert_contains('pg_ad_region_form_values($liveform)', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Region Behavior') !== false, $file . ' keeps no copy of the card');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ad region form shared by the create and the edit screens
 * (includes/forms/ad_region_form.php): the defaults a new region starts
 * with, what a refused create comes back with, and that both screens use
 * the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');

function test_ad_region_form_defaults_only_on_create()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad_region.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad_region.php');
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');

	pg_assert_contains("pg_ad_region_form_card(\$liveform, \$liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')), \$liveform->prefill())", $add, 'create draws the name as a field and checks the defaults only on a first draw');
	pg_assert_false(strpos($add, '<input name="name"') !== false, 'create keeps no plain name input');
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

// Runs $callback with a liveform on an empty session and puts the previous
// session back.
function _test_ad_region_form_with_session($callback)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$callback(new liveform('ad_region_form_test'));

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_ad_region_form_first_draw_checks_continuous_play()
{
	_test_ad_region_form_with_session(function ($liveform) {
		$card = pg_ad_region_form_card($liveform, '', $liveform->prefill());

		pg_assert_true((bool) preg_match('/id="slideshow_continuous"[^>]*checked="checked"/', $card), 'a new region plays continuously');
		pg_assert_true((bool) preg_match('/id="static"[^>]*checked="checked"/', $card), 'a new region is static');
	});
}

function test_ad_region_form_refused_create_keeps_an_unticked_box()
{
	_test_ad_region_form_with_session(function ($liveform) {
		// A refused create: the posted fields are in the session, the
		// unticked Play Continuously box is not.
		$saved_request = $_REQUEST;
		$_REQUEST = array('name' => 'top', 'display_type' => 'dynamic', 'transition_type' => 'fade');
		$liveform->add_fields_to_session();
		$_REQUEST = $saved_request;

		$card = pg_ad_region_form_card($liveform, '', $liveform->prefill());

		pg_assert_false((bool) preg_match('/id="slideshow_continuous"[^>]*checked/', $card), 'Play Continuously stays unticked');
		pg_assert_true((bool) preg_match('/id="dynamic"[^>]*checked="checked"/', $card), 'Dynamic stays chosen');
		pg_assert_false((bool) preg_match('/id="static"[^>]*checked/', $card), 'Static is not chosen again');
		pg_assert_true((bool) preg_match('/id="fade"[^>]*checked="checked"/', $card), 'Fade stays chosen');
	});
}

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

function test_ad_region_name_error_refuses_a_long_name()
{
	$free = function ($name) { return false; };
	$taken = function ($name) { return true; };

	pg_assert_same('', pg_ad_region_name_error(str_repeat('x', 100), null, $free), '100 characters pass');
	pg_assert_contains('100', pg_ad_region_name_error(str_repeat('x', 101), null, $free), '101 characters are refused, not cut');
	pg_assert_same(lang('Name is required.'), pg_ad_region_name_error('', null, $free), 'a blank name is required');
	pg_assert_same(lang('The name that you entered is already in use, so please enter a different name.'), pg_ad_region_name_error('top', null, $taken), 'a taken name is refused');

	$asked = array();
	$spy = function ($name) use (&$asked) { $asked[] = $name; return false; };
	pg_ad_region_name_error(str_repeat('x', 101), null, $spy);
	pg_assert_same(array(), $asked, 'a long name is refused before the clash check');
}

function test_ad_region_behavior_errors()
{
	$dynamic = array('display_type' => 'dynamic', 'transition_type' => 'slide', 'transition_duration' => '', 'slideshow' => '1', 'slideshow_interval' => '5');

	pg_assert_same(array(), pg_ad_region_behavior_errors($dynamic), 'a valid dynamic region passes');
	pg_assert_same(array(), pg_ad_region_behavior_errors(array('display_type' => 'static', 'transition_duration' => '-250')), 'a static region does not check hidden fields');
	pg_assert_same(array('display_type'), array_keys(pg_ad_region_behavior_errors(array('display_type' => 'bogus'))), 'an unknown display type is refused');
	pg_assert_same(array('transition_type'), array_keys(pg_ad_region_behavior_errors(array('transition_type' => 'bogus') + $dynamic)), 'an unknown transition type is refused');
	pg_assert_same(array('transition_duration'), array_keys(pg_ad_region_behavior_errors(array('transition_duration' => '-250') + $dynamic)), 'a negative duration is refused');
	pg_assert_same(array('transition_duration'), array_keys(pg_ad_region_behavior_errors(array('transition_duration' => '10000') + $dynamic)), 'a duration over 9999 is refused');
	pg_assert_same(array(), pg_ad_region_behavior_errors(array('transition_duration' => '0') + $dynamic), 'a zero duration passes');
	pg_assert_same(array('slideshow_interval'), array_keys(pg_ad_region_behavior_errors(array('slideshow_interval' => '2.5') + $dynamic)), 'a decimal interval is refused');
	pg_assert_same(array('slideshow_interval'), array_keys(pg_ad_region_behavior_errors(array('slideshow_interval' => '0') + $dynamic)), 'a zero interval is refused');
	pg_assert_same(array('slideshow_interval'), array_keys(pg_ad_region_behavior_errors(array('slideshow_interval' => '-9') + $dynamic)), 'a negative interval is refused');
	pg_assert_same(array(), pg_ad_region_behavior_errors(array('slideshow' => '', 'slideshow_interval' => '-9') + $dynamic), 'autoplay off does not check the interval');
}

function test_ad_region_form_values_write_only_allowed_values()
{
	_test_ad_region_form_with_session(function ($liveform) {
		$saved_request = $_REQUEST;
		$_REQUEST = array('display_type' => 'bogus', 'transition_type' => 'bogus', 'transition_duration' => '-250', 'slideshow' => '7', 'slideshow_interval' => '-9', 'slideshow_continuous' => '1');
		$liveform->add_fields_to_session();
		$_REQUEST = $saved_request;

		pg_assert_same(array('static', 'slide', '0', '0', '0', '1'), pg_ad_region_form_values($liveform), 'unknown types and out-of-range numbers are written as defaults');
	});
}

function test_ad_region_form_numbers_take_no_minus_and_no_decimal()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');

	pg_assert_same(2, substr_count($form, "'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false'"), 'duration and interval are whole numbers without a minus');
	pg_assert_false(strpos($form, "'data-inputmask-alias'=>'decimal'") !== false, 'no decimal mask on the interval');

	foreach (array('add_ad_region.php', 'edit_ad_region.php') as $file) {
		pg_assert_contains('pg_ad_region_form_validate_behavior($liveform)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' checks the behaviour values');
	}
}

function test_ad_region_form_columns_and_save_bar()
{
	_test_ad_region_form_with_session(function ($liveform) {
		$card = pg_ad_region_form_card($liveform, '', $liveform->prefill());
		$status = pg_ad_region_form_status($liveform, $liveform->prefill());
		$layout = pg_ad_region_form_layout($card . $status, '<button>x</button>');

		pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $card, 'the form column');
		pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $status, 'the status column');
		pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $status, 'the status box sticks under the top bar');
		pg_assert_contains('col-12 order-3 position-sticky', $layout, 'the sticky save bar');
		pg_assert_true(strpos($layout, 'order-1') < strpos($layout, 'order-2'), 'the status column comes after the form column');
	});

	foreach (array('add_ad_region.php', 'edit_ad_region.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_ad_region_form_layout(', $source, $file . ' draws the shared layout');
		pg_assert_contains('pg_ad_region_form_status($liveform, ', $source, $file . ' draws the Summary');
		pg_assert_false(strpos($source, 'pg_screen_toolbar(') !== false, $file . ' has no toolbar: the body tag is in the Summary');
	}
}

function test_ad_region_form_summary_follows_the_values()
{
	_test_ad_region_form_with_session(function ($liveform) {
		$status = pg_ad_region_form_status($liveform, true);

		pg_assert_contains('id="ad_region_summary_display">' . h(lang('Static')) . '</dd>', $status, 'a new region is static');
		pg_assert_true((bool) preg_match('/border-bottom d-none">\s*<dt class="fw-normal">' . preg_quote(h(lang('Transition Type')), '/') . '/', $status), 'a static region hides the transition');

		$saved_request = $_REQUEST;
		$_REQUEST = array('name' => 'top<b>', 'display_type' => 'dynamic', 'transition_type' => 'fade', 'slideshow' => '1', 'slideshow_interval' => '7');
		$liveform->add_fields_to_session();
		$_REQUEST = $saved_request;

		$status = pg_ad_region_form_status($liveform, false, pg_ad_region_form_status_row('Ads', '3'));

		pg_assert_contains('<code id="ad_region_summary_tag">' . h('<ad>top<b></ad>') . '</code>', $status, 'the body tag is escaped');
		pg_assert_contains('id="ad_region_summary_display">' . h(lang('Dynamic')) . '</dd>', $status, 'a dynamic region');
		pg_assert_contains('id="ad_region_summary_transition">' . h(lang('Fade')) . '</dd>', $status, 'the transition');
		pg_assert_contains('id="ad_region_summary_autoplay">' . h(lang('On') . ' (7 ' . lang('seconds') . ')') . '</dd>', $status, 'autoplay with its interval');
		pg_assert_false(strpos($status, ' d-none"') !== false, 'a dynamic region shows every row');
		pg_assert_contains('<dt class="fw-normal">Ads</dt>', $status, 'the screen rows follow');
	});
}

function test_ad_region_form_autoplay_text()
{
	pg_assert_same(lang('Off'), pg_ad_region_form_autoplay_text(false, '5'), 'off');
	pg_assert_same(lang('On'), pg_ad_region_form_autoplay_text(true, ''), 'on without an interval');
	pg_assert_same(lang('On') . ' (12 ' . lang('seconds') . ')', pg_ad_region_form_autoplay_text(true, '012'), 'on with an interval');
}

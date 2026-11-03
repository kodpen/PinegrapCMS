<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the alert liveform prints around errors, warnings and notices:
 * the panel gets Bootstrap 6's alert component (theme-<tone>, content first,
 * the close button at the end of the row), site output keeps Bootstrap 5's
 * alert-<tone> alert-dismissible markup.
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

// Panel markup: v6 classes only, the close button after the content.
function test_liveform_alert_box_panel_is_bootstrap_6()
{
	$html = liveform::alert_box('software_notice', 'success', '<h5 class="alert-heading">Notice:</h5><p class="notice mb-0">Saved.</p>', true);

	pg_assert_contains('class="software_notice alert theme-success d-flex align-items-start gap-3"', $html, 'v6 theme and flex row');
	pg_assert_true(strpos($html, 'alert-success') === false, 'no v5 tone class');
	pg_assert_true(strpos($html, 'alert-dismissible') === false, 'no v5 dismissible class');
	pg_assert_true(strpos($html, 'display:none') === false, 'close button visible');
	pg_assert_contains('data-bs-dismiss="alert"', $html, 'dismiss trigger');
	pg_assert_true(strpos($html, 'Saved.') < strpos($html, 'btn-close'), 'close button after the content');
}

// Site markup stays on Bootstrap 5.3.8.
function test_liveform_alert_box_site_keeps_bootstrap_5()
{
	$html = liveform::alert_box('software_error', 'danger', '<p class="error">Bad.</p>', false);

	pg_assert_contains('class="software_error alert alert-danger alert-dismissible show"', $html, 'v5 classes');
	pg_assert_true(strpos($html, 'theme-danger') === false, 'no v6 theme class');
	pg_assert_true(strpos($html, 'btn-close') < strpos($html, 'Bad.'), 'v5 close button first');
}

// The heading icon follows each version's spacing scale (0.5rem both ways).
function test_liveform_alert_icon_spacing()
{
	pg_assert_contains('me-3', liveform::alert_icon('bi-check-circle-fill', true), 'panel');
	pg_assert_contains('me-2', liveform::alert_icon('bi-check-circle-fill', false), 'site');
}

// output_notices() without a panel frame prints site markup.
function test_liveform_output_notices_site_without_panel_frame()
{
	if (defined('PG_PANEL_OUTPUT')) {
		return;
	}

	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('liveform_alert_test');
	$form->add_notice('Saved.');
	$html = $form->output_notices();

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	pg_assert_contains('alert-success alert-dismissible', $html, 'site notice');
	pg_assert_contains('Saved.', $html, 'notice text');
}

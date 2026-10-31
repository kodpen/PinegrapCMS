<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the panel's hand-written Copy buttons: the clipboard API
 * only in a secure context, a refused write handled, execCommand's answer
 * deciding success, a polite status region beside the button that says
 * Copied or asks the person to copy the selected text, and a button that
 * keeps its label while only its icon changes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function copy_buttons_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * Screens whose copy handler follows the recipe: [handler file, status file].
 */
function copy_buttons_screens()
{
	return array(
		array('erp_accountant.php', 'erp_accountant.php'),
		array('includes/workspace/claude.php', 'includes/workspace/claude.php'),
		array('includes/translate/engines.php', 'includes/translate/engines.php'),
		array('view_log.php', 'includes/fn/site_log_list.php'),
		array('connections.php', 'connections.php'),
		array('workspace_calendar_feed.php', 'workspace_calendar_feed.php'),
		array('api_settings.php', 'api_settings.php'),
		array('mfa.php', 'mfa.php'),
	);
}

function test_copy_buttons_secure_context_and_refusal()
{
	foreach (copy_buttons_screens() as $screen) {
		$source = copy_buttons_source($screen[0]);

		pg_assert_contains('navigator.clipboard && window.isSecureContext', $source, $screen[0]);
		pg_assert_true(preg_match('/\.writeText\([^;]*\)\.then\(done, fallback\)/', $source) === 1, $screen[0] . ': a refused write falls back');
		pg_assert_true(preg_match('/copied = document\.execCommand\(\\\\?["\']copy\\\\?["\']\)/', $source) === 1, $screen[0] . ': execCommand decides success');
		pg_assert_true(preg_match('/execCommand\(\\\\?["\']copy\\\\?["\']\);\s*done\(\)/', $source) === 0, $screen[0] . ': no success after an unchecked execCommand');
		pg_assert_true(strpos($source, '.catch(function () {})') === false, $screen[0] . ': a refusal is not swallowed');
		pg_assert_contains('getAttribute("data-refused")', str_replace("\\'", '"', $source), $screen[0]);
	}
}

function test_copy_buttons_status_region()
{
	$refused = "data-refused=\"' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '\"";

	foreach (copy_buttons_screens() as $screen) {
		$source = copy_buttons_source($screen[1]);

		pg_assert_contains('class="visually-hidden" role="status" aria-live="polite"', $source, $screen[1]);
		pg_assert_contains("data-copied=\"' . h(lang('Copied')) . '\"", $source, $screen[1]);
		pg_assert_contains($refused, $source, $screen[1]);
	}
}

function test_copy_buttons_keep_their_label()
{
	pg_assert_true(strpos(copy_buttons_source('workspace_calendar_feed.php'), 'copy.innerHTML') === false, 'the calendar feed button keeps its label');
	pg_assert_true(strpos(copy_buttons_source('view_log.php'), 'copy_label.textContent') === false, 'the site log button keeps its label');
	pg_assert_true(strpos(copy_buttons_source('view_log.php'), '!navigator.clipboard) {') === false, 'site log copy works without the clipboard API');
	pg_assert_true(strpos(copy_buttons_source('includes/translate/engines.php'), 'onclick=') === false, 'the browser page copy runs in the shared handler');
	pg_assert_contains('<i class="bi bi-clipboard me-1" aria-hidden="true"></i><span class="pg-log-copy-label">', copy_buttons_source('includes/fn/site_log_list.php'));
}

function test_copy_buttons_status_beside_button()
{
	// The status sits in the same wrapper as the button, so a modal dialog
	// or open sheet that makes the rest of the page inert still hears it.
	pg_assert_contains("aria-label=\"' . h(lang('Copy')) . '\"><i class=\"bi bi-clipboard\" aria-hidden=\"true\"></i></button>'\n            . '<span class=\"visually-hidden\" role=\"status\" aria-live=\"polite\"", copy_buttons_source('includes/workspace/claude.php'));
	pg_assert_contains('trigger.parentNode.querySelector(\\\'[role="status"]\\\')', copy_buttons_source('includes/workspace/claude.php'));
}

function test_copy_buttons_api_settings_names()
{
	$source = copy_buttons_source('api_settings.php');

	// Every Copy button reads its own caption: "Copy Key", "Copy Secret".
	pg_assert_true(strpos($source, 'button.innerHTML') === false, 'the API copy button keeps its label');
	pg_assert_true(preg_match_all('/class="btn[^"]*api-copy"/', $source) === 1, 'API copy buttons come from one helper');
	pg_assert_contains('aria-labelledby="\' . $id . \'_copy \' . $caption_id . \'"', $source);
	pg_assert_contains('<label class="form-label small" id="d_key_label">', $source);
	pg_assert_contains('<label class="form-label small text-uppercase opacity-75" id="nc_secret_label">', $source);
	pg_assert_contains('<summary id="\' . $id_prefix . \'_c_\' . $key . \'_label">', $source);

	// A modal drawer or an open sheet makes the rest of the page inert, so
	// the status sits beside the button inside it.
	pg_assert_contains('button.parentNode.querySelector("[role=status]")', $source);
}

function test_copy_buttons_mfa_selects_and_says()
{
	$source = copy_buttons_source('mfa.php');

	pg_assert_true(strpos($source, '.catch(function () { field.select(); })') === false, 'a refused write is announced, not only selected');
	pg_assert_contains('id="mfa_copy_status"', $source);
}

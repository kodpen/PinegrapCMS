<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two panel controls that act without a button. Developer Lock
 * sends its form as soon as the PIN field holds every digit, so a hint under
 * the slots says so and the PIN input points at it with aria-describedby.
 * Site Log's Show Error Logs switch posts its form when it changes; the
 * redirect after that post carries focus=error_log_toggle (not after a Delete
 * button) and the switch prints autofocus, so keyboard focus comes back to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_developer_lock_hint_says_page_opens_on_last_digit()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/developer_lock.php');

	pg_assert_true(preg_match('/<input type="text" id="pin"[^>]*aria-describedby="pin_hint"/', $source) === 1,
		'the PIN input points at the hint');
	pg_assert_true(preg_match('/id="pin_hint"[^>]*>\' \. lang\(\'The page opens as soon as every digit is entered\.\'\)/', $source) === 1,
		'the hint says the page opens on the last digit');
}

function test_developer_lock_hint_view_log_switch_gets_focus_back()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_log.php');

	pg_assert_true(preg_match('/if \(!isset\(\$_POST\[\'delete_all_site_logs\'\]\) && !isset\(\$_POST\[\'delete_all_error_logs\'\]\)\) \{\s*\$redirect_query = \'\?focus=error_log_toggle\';/', $source) === 1,
		'only the switch post asks for focus');
	pg_assert_contains("/view_log.php' . \$redirect_query);", $source,
		'the redirect carries the focus target');
	pg_assert_true(preg_match('/id="error_log_toggle" \' \. \$checked \. \$error_log_autofocus \. \'>/', $source) === 1,
		'the switch prints autofocus');
	pg_assert_true(preg_match('/\$error_log_autofocus = \(isset\(\$_GET\[\'focus\'\]\) && \$_GET\[\'focus\'\] === \'error_log_toggle\'\) \? \' autofocus\' : \'\';/', $source) === 1,
		'autofocus only when the redirect asked for it');
}

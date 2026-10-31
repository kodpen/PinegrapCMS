<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the answer a panel request gets when its session has ended
 * or its page token no longer matches the session: the panel language and a
 * way out (reload the page), never the bare English "Invalid login." or
 * "Invalid token.". A request that sent a user name and password keeps
 * "Invalid login." in api.php, since there the password was wrong.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_session_refusal_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_session_refusal_no_bare_login_or_token_text()
{
	$paths = array(
		'api.php',
		'includes/panel/notifications.php',
		'includes/panel/dashboard.php',
		'includes/panel/chat.php',
		'includes/panel/workspace.php',
		'includes/panel/commerce.php',
		'includes/panel/software.php',
		'translations_action.php',
		'barcode_increase_inventory.php',
		'barcode_decrease_inventory.php',
	);
	foreach ($paths as $path) {
		$source = panel_session_refusal_source($path);
		pg_assert_true(strpos($source, "'Invalid token.'") === false, $path . ': a token refusal without a way out');
		// api.php keeps one, for a request that sent a wrong password
		// (test_panel_session_refusal_api_gate pins it to that branch).
		pg_assert_same(
			$path === 'api.php' ? 1 : 0,
			preg_match_all("/'message' => (lang\\()?'Invalid login\\.'/", $source),
			$path . ': a session refusal without a way out'
		);
	}
}

function test_panel_session_refusal_api_gate()
{
	$source = panel_session_refusal_source('api.php');
	// Only the credential branch keeps "Invalid login.", and translated.
	pg_assert_same(1, substr_count($source, "lang('Invalid login.')"), 'api.php: one credential refusal');
	pg_assert_contains("defined('API_USERNAME')", $source, 'api.php: credential branch');
	pg_assert_contains("lang('Your session has expired. Reload the page and sign in again.')", $source, 'api.php');
	pg_assert_contains("'code' => 'session'", $source, 'api.php');
	pg_assert_contains("lang('The User must have a Designer or Administrator role.')", $source, 'api.php: role gate');
	pg_assert_contains("lang('Your session expired. Reload the page and try again.')", $source, 'api.php: token refusal');
	pg_assert_true(strpos($source, "'message' => 'The User must") === false, 'api.php: role gate outside lang()');

	foreach (array('notifications', 'dashboard', 'chat', 'workspace', 'commerce', 'software') as $group) {
		$path = 'includes/panel/' . $group . '.php';
		pg_assert_contains('respond(panel_login_refusal());', panel_session_refusal_source($path), $path);
	}
}

function test_panel_session_refusal_side_doors()
{
	pg_assert_contains(
		"lang('Your session expired. Reload the page and try again.')",
		panel_session_refusal_source('translations_action.php'),
		'translations_action.php'
	);
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $path) {
		$source = panel_session_refusal_source($path);
		pg_assert_contains("lang('Your session has expired. Reload the page and sign in again.')", $source, $path);
		pg_assert_contains("lang('Your session expired. Reload the page and try again.')", $source, $path);
		// No answer may come after the stock was already written, so a plain
		// "try again" could change it twice.
		pg_assert_true(strpos($source, "lang('Please try again.')") === false, $path . ': a blind retry after no answer');
		pg_assert_contains("lang('No answer came, so the inventory quantity may or may not have changed. Check the inventory of the product before scanning again.')", $source, $path);
	}
}

function panel_session_refusal_handler_body($source, $handler)
{
	$start = strpos($source, 'function ' . $handler . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + 1);
	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_panel_session_refusal_exempt_handlers_refuse_before_validate_user()
{
	// Exempt handlers check the session themselves. validate_user() answers
	// an ended session with a redirect to the sign-in page, which XHR and
	// fetch follow and hand over as HTML, so the JSON refusal must come first.
	$handlers = array(
		'includes/panel/explorer.php' => array('pg_panel_file_explorer'),
		'includes/panel/explorer_side.php' => array('pg_panel_explorer_side'),
		'includes/panel/software.php' => array('pg_panel_software_backup', 'pg_panel_software_update'),
		'includes/panel/dashboard.php' => array('pg_panel_contact_quick_save', 'pg_panel_tour_seen', 'pg_panel_user_pinned_app_update'),
		'includes/panel/commerce.php' => array('pg_panel_get_unselected_products', 'pg_panel_erp_refund_preview', 'pg_panel_get_product_barcodes'),
		'includes/panel/system.php' => array(
			'pg_panel_database_deep_check',
			'pg_panel_server_config_repair',
			'pg_panel_write_permissions_repair',
			'pg_panel_purge_cache',
		),
		'includes/panel/notifications.php' => array('pg_panel_check_unread_notifications', 'pg_panel_push_config'),
	);
	$guard = "if (!USER_LOGGED_IN) {\n        respond(panel_login_refusal());\n    }";
	foreach ($handlers as $path => $names) {
		$source = str_replace("\r\n", "\n", panel_session_refusal_source($path));
		foreach ($names as $name) {
			$body = panel_session_refusal_handler_body($source, $name);
			pg_assert_true($body !== '', $path . ': ' . $name . ' not found');
			$guard_at = strpos($body, $guard);
			$validate_at = strpos($body, '= validate_user();');
			pg_assert_true($guard_at !== false, $path . ': ' . $name . ' has no session refusal');
			pg_assert_true($validate_at !== false, $path . ': ' . $name . ' has no validate_user()');
			pg_assert_true($guard_at !== false && $guard_at < $validate_at, $path . ': ' . $name . ' calls validate_user() before the session refusal');
		}
	}
}

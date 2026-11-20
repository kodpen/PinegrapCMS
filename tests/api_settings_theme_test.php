<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the API applications screen (api_settings.php) on the panel
 * theme: the permission control's filled positions print --pg-on-accent rather
 * than a fixed white on the primary fill, the implied read position stays a
 * tint that reads in both themes, and its icon-only controls carry a name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function api_settings_theme_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/api_settings.php')
		. (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/api/app_new_view.php')
		. (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/api/app_drawer_view.php');
}

function api_settings_theme_style()
{
	$source = api_settings_theme_source();
	pg_assert_true((bool) preg_match('~<style>(.*?)</style>~s', $source, $match), 'no style block');

	return $match[1];
}

function test_api_settings_theme_no_fixed_white_on_primary()
{
	$style = api_settings_theme_style();

	foreach (preg_split('/\}/', $style) as $rule) {
		if (strpos($rule, 'var(--pg-primary)') !== false) {
			pg_assert_true(!preg_match('/(^|[;{\s])color:\s*#fff/i', $rule), 'fixed white on the primary fill: ' . trim($rule));
		}
	}

	pg_assert_contains('.api-seg .btn-check:checked + .btn { color: var(--pg-on-accent);', $style);
}

function test_api_settings_theme_implied_position_is_a_tint()
{
	$style = api_settings_theme_style();
	pg_assert_true((bool) preg_match('/\.api-seg \.btn\.implied \{([^}]*)\}/', $style, $match), 'no implied rule');
	pg_assert_contains('var(--pg-primary-bg-subtle)', $match[1]);
	pg_assert_contains('var(--pg-primary-fg-emphasis)', $match[1]);
	pg_assert_true(strpos($match[1], 'opacity') === false, 'the implied position fades the whole button, ink included');
}

function test_api_settings_theme_close_buttons_named()
{
	$source = api_settings_theme_source();
	pg_assert_true(preg_match_all('/<button[^>]*class="btn-close[^"]*"[^>]*>/', $source, $matches) >= 2, 'close buttons not found');

	foreach ($matches[0] as $tag) {
		pg_assert_contains("aria-label=\"' . h(lang('Close')) . '\"", $tag);
	}

	pg_assert_contains("id=\"api_filter\" aria-label=\"' . h(lang('Search application')) . '\"", $source);
}

function test_api_settings_theme_new_application_fields_named()
{
	$source = api_settings_theme_source();

	pg_assert_contains("<label class=\"form-label pg-field-label\" for=\"n_name\">' . lang('Application name') . '</label>", $source);
	pg_assert_contains('name="name" id="n_name" maxlength="190" required', $source);
	pg_assert_contains("<label class=\"form-label pg-field-label\" for=\"n_description\">' . lang('Description') . ' <span class=\"fg-2 fw-normal\">' . lang('optional') . '</span></label>", $source);
	pg_assert_contains('name="description" id="n_description" maxlength="500"', $source);
	pg_assert_true(strpos($source, '<span class="fg-danger">*</span>') === false, 'a hand-written marker doubles the injected one');
}

function test_api_settings_theme_permission_groups_named()
{
	// The rows are drawn by the permission picker (app_permissions_view.php).
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/app_permissions_view.php');

	pg_assert_contains("<b id=\"' . \$id . '_label\">", $source);
	pg_assert_contains("role=\"radiogroup\" aria-labelledby=\"' . \$id . '_label\"", $source);
}

function test_api_settings_theme_dialogs_named()
{
	$source = api_settings_theme_source();

	pg_assert_contains('id="api_drawer" aria-labelledby="d_title"', $source);
	pg_assert_contains('id="d_title"', $source);
	pg_assert_contains('id="api_new" aria-labelledby="api_new_title"', $source);
	pg_assert_contains('<h5 class="drawer-title mb-0" id="api_new_title">', $source);

	// The one-time values are step 3 of the New application sheet, not a
	// dialog of their own.
	pg_assert_contains('<div id="n_step_3" data-step="3">', $source, 'step 3 holds what Create made');
	pg_assert_true(strpos($source, 'api_sheet_secret') === false, 'no separate one-time dialog is left');

	pg_assert_true(preg_match_all('/<dialog\b[^>]*>/', $source, $matches) >= 2, 'dialogs not found');
	foreach ($matches[0] as $tag) {
		pg_assert_contains('aria-labelledby="', $tag);
	}
}

function test_api_settings_theme_toolbar_wraps()
{
	// The screen toolbar (.pg-toolbar) wraps its slots onto more lines.
	pg_assert_contains('pg_screen_toolbar(', api_settings_theme_source());
}

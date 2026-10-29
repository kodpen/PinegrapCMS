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
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/api_settings.php');
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

	pg_assert_contains("<label class=\"form-label small\" for=\"n_name\">' . lang('Name') . '</label>", $source);
	pg_assert_contains('name="name" id="n_name" maxlength="190" required', $source);
	pg_assert_contains("<label class=\"form-label small\" for=\"n_description\">' . lang('Description') . '</label>", $source);
	pg_assert_contains('name="description" id="n_description" maxlength="500"', $source);
	pg_assert_true(strpos($source, '<span class="fg-danger">*</span>') === false, 'a hand-written marker doubles the injected one');
}

function test_api_settings_theme_permission_groups_named()
{
	$source = api_settings_theme_source();

	pg_assert_contains("<b id=\"' . \$id . '_label\">", $source);
	pg_assert_contains("role=\"group\" aria-labelledby=\"' . \$id . '_label\"", $source);
}

function test_api_settings_theme_dialogs_named()
{
	$source = api_settings_theme_source();

	pg_assert_contains('id="api_drawer" aria-labelledby="d_title"', $source);
	pg_assert_contains('id="d_title"', $source);
	pg_assert_contains('id="api_new" aria-labelledby="api_new_title"', $source);
	pg_assert_contains('<h5 class="dialog-title" id="api_new_title">', $source);

	foreach (array('api_sheet_oauth', 'api_sheet_device', 'api_sheet_secret') as $id) {
		pg_assert_contains('id="' . $id . '" aria-labelledby="' . $id . '_title" open>', $source);
		pg_assert_contains('<h5 class="dialog-title" id="' . $id . '_title">', $source);
	}

	pg_assert_true(preg_match_all('/<dialog\b[^>]*>/', $source, $matches) >= 5, 'dialogs not found');
	foreach ($matches[0] as $tag) {
		pg_assert_contains('aria-labelledby="', $tag);
	}
}

function test_api_settings_theme_toolbar_wraps()
{
	pg_assert_contains('<div class="d-flex flex-wrap align-items-center gap-3 mb-5">', api_settings_theme_source());
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the toolbar a signed-in editor sees over a site page
 * (toolbar.php inside the #software_toolbar iframe). Bootstrap 6 writes its
 * colours as light-dark() and resolves them from color-scheme, so the
 * document root may be kept light for a transparent frame, but the body
 * must follow the panel theme, and the toolbar's own styles may only read
 * tokens Bootstrap 6 or the panel define.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function site_toolbar_theme_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The root stays light for the frame's transparency, the body follows the theme.
function test_site_toolbar_body_follows_theme()
{
	$source = site_toolbar_theme_source('toolbar.php');

	pg_assert_true(
		preg_match('/:root\s*\{\s*color-scheme:\s*light;/', $source) === 1,
		'toolbar.php keeps its root light so the frame stays transparent');
	pg_assert_true(
		preg_match('/:root\[data-bs-theme="dark"\]\s+body\s*\{\s*color-scheme:\s*dark;?\s*\}/', $source) === 1,
		'toolbar.php gives the body the dark scheme when the panel is dark');
}

// An inline color-scheme on <html> made light-dark() resolve light for the
// whole toolbar, dark backgrounds and all.
function test_site_toolbar_script_leaves_scheme_alone()
{
	pg_assert_true(
		strpos(site_toolbar_theme_source('assets/js/backend.src.js'), 'color-scheme:light') === false,
		'backend.src.js does not force color-scheme on the toolbar document');
}

// Bootstrap 5 names Bootstrap 6 dropped turn the SEO panel's colours invalid.
function test_site_toolbar_reads_no_dropped_v5_variables()
{
	$source = site_toolbar_theme_source('toolbar.php');

	foreach (array('--bs-body-color', '--bs-secondary-color', '--bs-tertiary-color', '--bs-tertiary-bg', '--bs-secondary-bg', '--bs-emphasis-color', '--bs-body-bg') as $name) {
		pg_assert_true(strpos($source, $name) === false, 'toolbar.php reads ' . $name);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the editing button strip over a site page
 * (#software_pinegrap_button_container in get_page.php): a vertical strip on
 * the right edge, centred, so it leaves the top corner (account menu, cart,
 * language switch) to the theme; collapsed by default on a narrow screen; the
 * SEO panel and the inline save/cancel pair beside it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function site_button_strip_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The CSS block that follows $selector, up to its closing brace.
function site_button_strip_block($source, $selector)
{
	$start = strpos($source, $selector);

	return ($start === false) ? '' : substr($source, $start, strpos($source, '}', $start) - $start);
}

// A column on the right edge, centred by insets and auto margins: a transform
// would turn the strip into the containing block of the fixed pair inside it.
function test_site_button_strip_is_a_centred_column()
{
	$box = site_button_strip_block(site_button_strip_source('get_page.php'), "#software_pinegrap_button_container{\n");

	pg_assert_true($box !== '', 'the strip rule is found');
	pg_assert_true(strpos($box, 'flex-direction:column;') !== false, 'the buttons are stacked');
	pg_assert_true(strpos($box, 'bottom: 0px;') !== false && strpos($box, 'margin: auto 0;') !== false, 'the strip is centred on the edge');
	pg_assert_true(strpos($box, 'transform') === false, 'the strip has no transform');
}

// Collapsed by default under 768 px; the session choice wins once made.
function test_site_button_strip_starts_collapsed_on_a_narrow_screen()
{
	$page = site_button_strip_source('get_page.php');

	pg_assert_true(strpos($page, 'window.matchMedia("(max-width: 767.98px)").matches ? "hide" : "show"') !== false, 'a narrow screen starts collapsed');
	pg_assert_true(strpos($page, 'if (state !== "show" && state !== "hide") {') !== false, 'the default applies only without a stored choice');
	pg_assert_true(strpos($page, 'role="toolbar" aria-orientation="vertical"') !== false, 'the strip is a labelled toolbar');
	pg_assert_true(strpos($page, 'setAttribute("aria-expanded"') !== false, 'the arrow says whether the strip is open');
}

// The SEO panel sits beside the strip on the site, under the bar in the toolbar.
function test_site_button_strip_seo_panel_beside_it()
{
	$panel = site_button_strip_block(site_button_strip_source('seo.php'), "#software_seo_panel{\n                position: fixed;");
	$toolbar = site_button_strip_source('toolbar.php');

	pg_assert_true(strpos($panel, 'right: 48px;') !== false, 'the panel clears the strip');
	pg_assert_true(strpos($panel, 'transform: translateY(-50%);') !== false, 'the panel is centred on the strip');
	pg_assert_true(strpos($toolbar, "right: 0;\n                transform: none;") !== false, 'the toolbar copy keeps its column place');
}

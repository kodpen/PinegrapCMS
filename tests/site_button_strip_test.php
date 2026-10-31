<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the editing button box over a site page
 * (#software_pinegrap_button_container in get_page.php): a row in the
 * top-right corner, collapsed to its arrow by default on a narrow screen,
 * with the SEO panel and the inline save/cancel pair opening under it.
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

// A row in the top-right corner. No transform: it would turn the box into
// the containing block of the fixed pair and arrow inside it.
function test_site_button_box_sits_in_the_top_right_corner()
{
	$page = site_button_strip_source('get_page.php');
	$box = site_button_strip_block($page, "#software_pinegrap_button_container{\n");
	$pair = site_button_strip_block($page, "#software_inline_editing_buttons{");

	pg_assert_true($box !== '', 'the box rule is found');
	pg_assert_true(strpos($box, 'flex-direction:row;') !== false, 'the buttons sit in a row');
	pg_assert_true(strpos($box, 'top: 0px;') !== false, 'the box sits at the top');
	pg_assert_true(strpos($box, 'bottom: 0px;') === false && strpos($box, 'margin: auto 0;') === false, 'the box is not centred on the edge');
	pg_assert_true(strpos($box, 'transform') === false, 'the box has no transform');
	pg_assert_true($pair !== '' && strpos($pair, 'top: 50%') === false, 'the save/cancel pair is not centred on the edge');
}

// Collapsed by default under 768 px; the session choice wins once made.
function test_site_button_box_starts_collapsed_on_a_narrow_screen()
{
	$page = site_button_strip_source('get_page.php');

	pg_assert_true(strpos($page, 'window.matchMedia("(max-width: 767.98px)").matches ? "hide" : "show"') !== false, 'a narrow screen starts collapsed');
	pg_assert_true(strpos($page, 'if (state !== "show" && state !== "hide") {') !== false, 'the default applies only without a stored choice');
	pg_assert_true(strpos($page, 'id="software_pinegrap_button_container" role="toolbar" aria-label=') !== false, 'the box is a labelled toolbar');
	pg_assert_true(strpos($page, 'aria-orientation="vertical"') === false, 'the box is not a vertical toolbar');
	pg_assert_true(strpos($page, 'setAttribute("aria-expanded"') !== false, 'the arrow says whether the box is open');
}

// The SEO panel opens under the box on the site, under the bar in the toolbar.
function test_site_button_box_seo_panel_under_it()
{
	$panel = site_button_strip_block(site_button_strip_source('seo.php'), "#software_seo_panel{\n                position: fixed;");
	$toolbar = site_button_strip_source('toolbar.php');

	pg_assert_true(strpos($panel, 'top: 50px;') !== false, 'the panel opens under the box');
	pg_assert_true(strpos($panel, 'right: 0;') !== false, 'the panel keeps to the right edge');
	pg_assert_true(strpos($panel, 'translateY') === false, 'the panel is not centred on the edge');
	pg_assert_true(strpos($toolbar, "right: 0;\n                transform: none;") !== false, 'the toolbar copy keeps its column place');
}

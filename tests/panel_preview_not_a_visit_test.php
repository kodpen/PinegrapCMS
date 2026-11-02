<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's own page previews: a page the File Manager loads in
 * its preview pane or Quick look carries pg_preview=1, and get_page.php
 * neither opens a visitor record nor counts a page view for it when a panel
 * user is signed in (pg_request_is_panel_preview()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_preview_needs_the_marker_and_a_panel_user()
{
	pg_assert_true(pg_request_is_panel_preview(array('pg_preview' => '1'), true), 'marker + signed in');
	pg_assert_false(pg_request_is_panel_preview(array('pg_preview' => '1'), false), 'a visitor with the marker is counted');
	pg_assert_false(pg_request_is_panel_preview(array(), true), 'a panel user browsing the site is counted');
	pg_assert_false(pg_request_is_panel_preview(array('pg_preview' => '0'), true));
	pg_assert_false(pg_request_is_panel_preview(array('pg_preview' => array('1')), true), 'an array is not the marker');
}

function test_get_page_skips_tracking_for_a_panel_preview()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');

	pg_assert_contains("\$pg_panel_preview = pg_request_is_panel_preview(\$_GET, isset(\$_SESSION['sessionusername']));", $source);
	pg_assert_contains("if (defined('VISITOR_TRACKING') && VISITOR_TRACKING && !\$pg_panel_preview)", $source, 'no visitor record');
	pg_assert_contains("if ((VISITOR_TRACKING == true) && !\$pg_panel_preview)", $source, 'no page view');
}

function test_explorer_page_previews_carry_the_marker()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains("return url + ((url.indexOf('?') === -1) ? '?' : '&') + 'pg_preview=1';", $source);
	pg_assert_same(2, substr_count($source, "'<iframe loading=\"lazy\" src=\"' + esc(pagePreviewUrl(item.url)) + '\""), 'preview pane and Quick look');
	pg_assert_false(strpos($source, "item.kind === 'page') { return '<iframe loading=\"lazy\" src=\"' + esc(item.url)") !== false, 'Quick look has no bare page URL');
}

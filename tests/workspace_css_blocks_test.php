<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source check for backend.src.css: the top-level .ws-channel-link, .ws-tabs
 * and .ws-center rule blocks are written once, and each single block holds
 * the declarations a second block used to add further down. A second block
 * of the same selector splits one component's rules across the file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_css_blocks_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

// The body of the only top-level block of the selector, or '' when absent.
function workspace_css_blocks_body($css, $selector)
{
	if (!preg_match('/\n' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_css_blocks_written_once()
{
	$css = workspace_css_blocks_source();

	foreach (array('.ws-channel-link', '.ws-tabs', '.ws-center') as $selector) {
		$count = preg_match_all('/\n' . preg_quote($selector, '/') . ' \{/', $css);
		pg_assert_same(1, $count, $selector . ' has one top-level block');
	}
}

function test_workspace_css_blocks_keep_merged_declarations()
{
	$css = workspace_css_blocks_source();

	$link = workspace_css_blocks_body($css, '.ws-channel-link');
	pg_assert_contains('display: flex;', $link, 'the channel link block is the base block');
	pg_assert_contains('position: relative;', $link, 'the channel link is positioned');
	pg_assert_contains('transition: background .12s ease;', $link, 'and keeps its hover transition');

	$tabs = workspace_css_blocks_body($css, '.ws-tabs');
	pg_assert_contains('overflow-x: auto;', $tabs, 'the tabs block is the base block');
	pg_assert_contains('scrollbar-width: none;', $tabs, 'the tab strip hides its scrollbar');
	pg_assert_contains('.ws-tabs::-webkit-scrollbar', $css, 'and so does WebKit');

	$center = workspace_css_blocks_body($css, '.ws-center');
	pg_assert_contains('flex-direction: column;', $center, 'the center block is the base block');
	pg_assert_contains('position: relative;', $center, 'the center anchors the drop overlay');
	pg_assert_contains("/* Files dragged over a conversation. */\n.ws-drop-overlay {", $css, 'the overlay keeps its comment');
}

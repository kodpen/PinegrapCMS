<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A path segment after the page name that nothing on the page reads
 * (/about/xyz) answers 404: the decision, the widgets that mark the segment
 * read, and the check after the widget pass in get_page_content().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function unread_segment_404_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of function $name in $source, up to the next function.
function unread_segment_404_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) return '';
	$next = strpos($source, "\nfunction ", $start + 1);
	return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_unread_segment_404_decision()
{
	pg_assert_false(pg_sw_unread_segment_missing('', false, 'standard'), 'no segment');
	pg_assert_true(pg_sw_unread_segment_missing('xyz', false, 'standard'), 'unread segment on a plain page');
	pg_assert_true(pg_sw_unread_segment_missing('xyz', false, 'photo gallery'), 'unread segment on a classic page');
	pg_assert_false(pg_sw_unread_segment_missing('xyz', true, 'standard'), 'a widget read it');
	pg_assert_false(pg_sw_unread_segment_missing('xyz', false, 'catalog'), 'classic catalog reads it itself');
	pg_assert_false(pg_sw_unread_segment_missing('xyz', false, 'catalog detail'), 'classic catalog detail reads it itself');
}

function test_unread_segment_404_read_flag()
{
	pg_sw_segment_read(false);
	pg_assert_false(pg_sw_segment_read(), 'cleared');
	pg_assert_false(pg_sw_segment_read(true), 'marking returns the value before');
	pg_assert_true(pg_sw_segment_read(), 'marked');
	pg_assert_true(pg_sw_segment_read(false), 'clearing returns the value before');
	pg_assert_false(pg_sw_segment_read(), 'cleared again');
}

// The widgets that read the segment mark it read.
function test_unread_segment_404_readers_mark()
{
	$catalog = unread_segment_404_source('includes/fn/widgets_catalog.php');
	pg_assert_contains('pg_sw_segment_read(true)', unread_segment_404_function_body($catalog, '_render_system_widget_catalog_listing'), 'catalog listing');
	pg_assert_contains('pg_sw_segment_read(true)', unread_segment_404_function_body($catalog, '_render_system_widget_catalog_item_view'), 'catalog item view');

	$widgets = unread_segment_404_source('includes/fn/widgets.php');
	$item_view = unread_segment_404_function_body($widgets, '_render_system_widget_form_item_view');
	pg_assert_contains('pg_sw_segment_read(true)', $item_view, 'form item view');
	// A segment is an asked record with pretty URLs off too.
	pg_assert_false(strpos($item_view, "!\$asked && pg_sw_request_segment() !== '' && pg_sw_form_pretty_urls(") !== false, 'segment asked without pretty URLs');
}

// get_page_content() clears the flag before the widget pass and asks after it,
// for the requested page only, never in an e-mail or edit mode.
function test_unread_segment_404_page_check()
{
	$source = unread_segment_404_source('get_page_content.php');
	$clear = strpos($source, 'pg_sw_segment_read(false)');
	$widgets = strpos($source, '_expand_system_widgets($content');
	$check = strpos($source, 'pg_sw_unread_segment_missing(');
	pg_assert_true($clear !== false && $widgets !== false && $check !== false, 'all three present');
	pg_assert_true($clear < $widgets && $widgets < $check, 'clear, widget pass, check');
	$block = substr($source, (int) strrpos(substr($source, 0, (int) $check), 'if ('), 600);
	pg_assert_contains('!$email', $block, 'not in an e-mail');
	pg_assert_contains("\$mode != 'edit'", $block, 'not in edit mode');
	pg_assert_contains('_pg_sw_current_page_id()', $block, 'only the requested page');
}

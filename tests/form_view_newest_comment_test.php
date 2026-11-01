<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the newest comment a form item view or form list view prints for
 * ^^newest_comment^^ (includes/fn/forms.php): the message is cut to 100
 * characters before it is escaped, so the cut never splits an entity or the
 * <br /> of a line break.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A short message stays as it is.
function test_form_view_newest_comment_short_message_unchanged()
{
	pg_assert_same('Thanks & see you', pg_form_view_newest_comment('Thanks & see you'), 'short');
	pg_assert_same('', pg_form_view_newest_comment(''), 'empty');
	pg_assert_same('', pg_form_view_newest_comment(null), 'no comment');
	pg_assert_same(str_repeat('a', 100), pg_form_view_newest_comment(str_repeat('a', 100)), 'exactly 100');
}

// A long message keeps its first 100 characters and gets "...".
function test_form_view_newest_comment_long_message_cut()
{
	pg_assert_same(str_repeat('ş', 100) . '...', pg_form_view_newest_comment(str_repeat('ş', 150)), 'multibyte');
}

// An "&" near the cut is escaped whole: "&amp;", never "&am".
function test_form_view_newest_comment_cut_keeps_entity_whole()
{
	$message = str_repeat('a', 98) . ' & b and more text after the cut';
	$output = prepare_form_data_for_output(pg_form_view_newest_comment($message), '', true);

	pg_assert_same(str_repeat('a', 98) . ' &amp;...', $output, 'entity whole');
}

// A line break near the cut prints a whole <br />, not a broken "<br" tag
// that swallows the layout markup after it.
function test_form_view_newest_comment_cut_keeps_line_break_whole()
{
	$message = str_repeat('a', 99) . "\nsecond line";
	$output = prepare_form_data_for_output(pg_form_view_newest_comment($message), '', true);

	pg_assert_same(str_repeat('a', 99) . "<br />\n...", $output, 'line break whole');
}

// The renderers cut the raw message, never the escaped one.
function test_form_view_newest_comment_renderers_cut_before_escaping()
{
	foreach (array('get_form_item_view.php', 'get_form_list_view.php', 'includes/fn/content.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(strpos($source, 'pg_form_view_newest_comment(') !== false, $file . ' uses the helper');
		pg_assert_true(strpos($source, "mb_substr(\$data, 0, 100) . '...'") === false, $file . ' no longer cuts the escaped text');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments system widget (includes/fn/widgets_comments.php):
 * the per-pass record of the pages whose thread a widget drew, which the
 * classic comments block of get_page_content.php reads to leave those pages
 * alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// -- slice 8: the hand-off ----------------------------------------------------

// A page is claimed once per pass; a second widget on it gets false. The
// record a pass kept comes back when the outer pass restores its own.
function test_comments_widget_registry_claims_once()
{
	$found = pg_comments_widget_pages(array());

	pg_assert_true(pg_comments_widget_claim(12), 'first claim');
	pg_assert_false(pg_comments_widget_claim(12), 'second claim of the same page');
	pg_assert_false(pg_comments_widget_claim('12'), 'the same page as a digit string');
	pg_assert_true(pg_comments_widget_drawn(12), 'drawn');
	pg_assert_true(pg_comments_widget_drawn('12'), 'drawn, asked with a digit string');
	pg_assert_false(pg_comments_widget_drawn(13), 'another page');

	foreach (array(0, -3, 'x', '12abc', '', ' 12', array(5), true, null, 1.5) as $bad) {
		pg_assert_false(pg_comments_widget_claim($bad), 'not a page id: ' . var_export($bad, true));
		pg_assert_false(pg_comments_widget_drawn($bad), 'never drawn: ' . var_export($bad, true));
	}

	pg_assert_true(pg_comments_widget_claim('14'), 'a digit string is a page id');

	$prev = pg_comments_widget_pages(array());
	pg_assert_same(array(12 => true, 14 => true), $prev, 'the pass kept its pages');
	pg_assert_false(pg_comments_widget_drawn(12), 'a new pass starts empty');
	pg_assert_true(pg_comments_widget_claim(12), 'and claims again');

	$pass = pg_comments_widget_pages($prev);
	pg_assert_same(array(12 => true), $pass, 'the inner pass hands back its own record');
	pg_assert_true(pg_comments_widget_drawn(12, $pass), 'drawn in the record handed over');
	pg_assert_false(pg_comments_widget_drawn(14, $pass), 'not in that record');
	pg_assert_true(pg_comments_widget_drawn(14), 'the outer record is back');
	pg_assert_false(pg_comments_widget_drawn(14, array()), 'an empty record names nothing');

	pg_comments_widget_pages($found);
}

// get_page_content.php tells the widgets which page is drawn, keeps one
// record per pass and lets the classic block skip a page a widget drew.
function test_comments_widget_page_hand_off_wiring()
{
	$page = comments_widget_test_source('get_page_content.php');

	$context = strpos($page, 'pg_sw_render_context(array(');
	$close = ($context === false) ? false : strpos($page, ';', $context);
	pg_assert_true(($context !== false) && ($close !== false), 'the context is set');
	if (($context !== false) && ($close !== false)) {
		pg_assert_contains("'page_id' => (int)\$page_id", substr($page, $context, $close - $context), 'the drawn page goes into the context');
	}

	$open = strpos($page, 'pg_comments_widget_pages(array())');
	$expand = strpos($page, '_expand_system_widgets($content');
	$nested = strpos($page, 'pg_expand_nested_components($content');
	$restore = strpos($page, 'pg_comments_widget_pages($pg_cw_prev)');
	pg_assert_true(($open !== false) && ($expand !== false) && ($open < $expand), 'a fresh record before the widget pass');
	pg_assert_true(($restore !== false) && ($nested !== false) && ($nested < $restore), 'the outer record back after the nested pass');
	pg_assert_same(1, substr_count($page, '$pg_cw_pages = array();'), 'the pass starts with no page');

	$skip = "pg_comments_widget_drawn(\$system_region_properties['page_id'], \$pg_cw_pages)";
	pg_assert_same(1, substr_count($page, $skip), 'the classic block asks once');
	$record = strpos($page, 'pg_sw_record_comment_context($system_region_properties');
	$at = strpos($page, $skip);
	$item = strpos($page, "\$item_id = '0';");
	pg_assert_true(($record !== false) && ($at !== false) && ($item !== false) && ($record < $at) && ($at < $item), 'in the condition that opens the block');

	pg_assert_contains("str_replace('<!--pg-comments-block-->', \$comments_block_html", $page, 'a leftover marker is still emptied');
}

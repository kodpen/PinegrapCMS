<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the bell notification title: one short line in look A,
 * the text under it carrying the rest. The mail queue's cells are pinned by
 * tests/mail_queue_list_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_bell_notification_title_one_line()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	$start = strpos($source, 'function pg_bell_row(');
	$row = ($start === false) ? '' : substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	// Look A: the title is one line with the time at its right; it is short
	// ("New order #1044") and the text under it carries the rest. The recipe
	// row's title ends in an ellipsis when it does not fit.
	pg_assert_true($row !== '', 'the bell row is drawn by pg_bell_row()');
	pg_assert_same(false, strpos($row, " title=\"' + "), 'bell title has no hover-only title attribute');
	pg_assert_contains('pg-list-row-title pg-bell-title', $row, 'the title is the recipe row\'s title');
	$at = strpos($css, "\n.pg-list-row-title {");
	$rule = ($at === false) ? '' : substr($css, $at, strpos($css, '}', $at) - $at);
	pg_assert_contains('white-space: nowrap;', $rule, 'one line');
	pg_assert_contains('text-overflow: ellipsis;', $rule, 'ending in an ellipsis');
	pg_assert_same(false, strpos($css, "#notifications .list-group-item .notification-title {\n  text-overflow: ellipsis;"), 'the old nowrap rule is gone');
}

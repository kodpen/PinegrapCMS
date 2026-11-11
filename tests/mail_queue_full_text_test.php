<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for long texts the panel shows in full: the bell notification
 * title wraps instead of ending in an ellipsis. The mail queue's cells are
 * pinned by tests/mail_queue_list_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_bell_notification_title_wraps()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	$start = strpos($source, 'function pg_bell_row(');
	$row = ($start === false) ? '' : substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$at = strpos($css, "\n#notifications .pg-bell-title {");
	$rule = ($at === false) ? '' : substr($css, $at, strpos($css, '}', $at) - $at);

	pg_assert_true($row !== '', 'the bell row is drawn by pg_bell_row()');
	pg_assert_same(false, strpos($row, " title=\"' + "), 'bell title has no hover-only title attribute');
	pg_assert_contains('white-space: normal;', $rule, 'bell title wraps');
	pg_assert_contains('overflow-wrap: anywhere;', $rule, 'a long word breaks too');
	pg_assert_same(false, strpos($rule, 'ellipsis'), 'and never ends in an ellipsis');
	pg_assert_same(false, strpos($css, "#notifications .list-group-item .notification-title {\n  text-overflow: ellipsis;"), 'the old nowrap rule is gone');
}

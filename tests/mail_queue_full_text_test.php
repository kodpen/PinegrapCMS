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

	pg_assert_same(false, strpos($source, '<small class="notification-title" title='), 'bell title has no hover-only title attribute');
	pg_assert_true(strpos($source, '<small class="notification-title text-wrap text-break w-100">') !== false, 'bell title wraps');
}

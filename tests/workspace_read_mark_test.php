<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the channel read mark (ws_channel_members.last_read_id):
 * GREATEST() gets the new message id as a bare integer. A quoted id makes
 * MySQL 8 compare the two as text, so '9' stays above '157' and the mark,
 * the unread counts and the catch-up line never move once ids gain a digit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_read_mark_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);
}

function test_workspace_read_mark_greatest_compares_numbers()
{
	foreach (array('channels.php', 'eras.php') as $file) {
		$source = workspace_read_mark_test_source($file);

		pg_assert_true(strpos($source, 'GREATEST(last_read_id, ') !== false, $file . ' moves the read mark with GREATEST()');
		pg_assert_same(0, preg_match("/GREATEST\\(last_read_id,\\s*'/", $source), $file . ' passes the message id to GREATEST() unquoted');
	}
}

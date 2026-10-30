<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer a Workspace customer visit is about
 * (ws_events.contact_id, includes/workspace/schedule.php): the upgrade step
 * adds the column and its index with the idempotent helpers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_visit_contact_step_adds_the_column_and_index()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(preg_match('~^\s*upgrade_2026_11_0_workspace_visit_contact\(\);\s*// 11\.0\.118$~m', $file) === 1, 'the step is called, labelled 11.0.118');
	pg_assert_true(preg_match('~function upgrade_2026_11_0_workspace_visit_contact\(\) \{(.*?)\n\}~s', $file, $match) === 1, 'the step exists');
	pg_assert_true(strpos($match[1], "install_add_column('ws_events', 'contact_id', \"INT UNSIGNED NOT NULL DEFAULT 0\");") !== false, 'the column through install_add_column()');
	pg_assert_true(strpos($match[1], "install_add_index('ws_events', 'idx_contact', \"INDEX idx_contact (contact_id)\");") !== false, 'the index through install_add_index()');
	pg_assert_true(stripos($match[1], 'ALTER') === false, 'no ALTER of its own');
}

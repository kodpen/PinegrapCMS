<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking back an applied content change
 * (includes/fn/content_proposals.php): refused once the page was saved
 * after the apply, its settings included.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// An applied change of the page title from "Old" to "Editor title".
function _content_proposals_revert_test_row()
{
	$ops = pg_content_proposals_ops(null, null, array('page_title' => 'Old', 'page_noindex' => 0), array('page_title' => 'Editor title', 'page_noindex' => true));

	return array('status' => 'applied', 'applied_to' => 'page', 'before_tree' => '{}', 'decided_by' => 5, 'after_hash' => 'abc',
		'ops' => (string) json_encode($ops));
}

// Nothing saved since the apply: the change may be taken back.
function test_content_proposals_revert_allowed_when_unchanged()
{
	$row = _content_proposals_revert_test_row();
	$now = array('page_title' => 'Editor title', 'page_noindex' => 1, 'page_meta_description' => 'Other setting');

	pg_assert_same('', pg_content_proposals_revert_check($row, 5, 'abc', $now), 'settings as the apply wrote them');
	pg_assert_same('', pg_content_proposals_revert_check($row, 5, 'abc'), 'no settings given');
}

// A setting the change wrote, changed by a later save that kept the tree
// (Mass Edit of the title): refused as saved since.
function test_content_proposals_revert_refused_after_settings_save()
{
	$row = _content_proposals_revert_test_row();

	pg_assert_same('saved', pg_content_proposals_revert_check($row, 5, 'abc', array('page_title' => 'New title', 'page_noindex' => 1)), 'title changed');
	pg_assert_same('saved', pg_content_proposals_revert_check($row, 5, 'abc', array('page_title' => 'Editor title', 'page_noindex' => 0)), 'switch changed');
	pg_assert_same('other', pg_content_proposals_revert_check($row, 6, 'abc', array('page_title' => 'New title')), 'another designer first');
}

// A tree saved since still refuses, whatever the settings hold.
function test_content_proposals_revert_refused_after_tree_save()
{
	$row = _content_proposals_revert_test_row();

	pg_assert_same('saved', pg_content_proposals_revert_check($row, 5, 'later', array('page_title' => 'Editor title', 'page_noindex' => 1)));
}

// The take back reads the page's settings now and refuses with the saved
// again message.
function test_content_proposals_revert_reads_settings()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

	pg_assert_contains("pg_content_proposals_stored_hash((int) \$row['page_id']), \$page ? \$page['fields'] : null)", $module);
	pg_assert_contains("lang('The page was saved again after this change, so it cannot be taken back here.", $module);
}

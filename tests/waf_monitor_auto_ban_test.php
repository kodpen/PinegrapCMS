<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the block list by firewall mode (waf.php): in Monitor mode only
 * the hand-written entries are enforced, the firewall's own automatic bans
 * wait, still stored, until the mode is Block again.
 * waf_ip_lists_from_rows() splits the rows by source and
 * waf_ip_block_entries() picks the entries a mode enforces.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

function waf_monitor_auto_ban_test_rows()
{
	return array(
		array('ip_address' => '203.0.113.7', 'list_type' => 'block', 'source' => 'auto'),
		array('ip_address' => '2001:db8:1:2::/64', 'list_type' => 'block', 'source' => 'auto'),
		array('ip_address' => '198.51.100.0/24', 'list_type' => 'block', 'source' => 'manual'),
		array('ip_address' => '192.0.2.10', 'list_type' => 'allow', 'source' => 'manual'),
		// A table from before the source column: every entry is hand-written.
		array('ip_address' => '192.0.2.99', 'list_type' => 'block'),
	);
}

function test_waf_ip_lists_from_rows_split_by_source()
{
	$lists = waf_ip_lists_from_rows(waf_monitor_auto_ban_test_rows());

	pg_assert_same(array('192.0.2.10'), $lists['allow'], 'allow');
	pg_assert_same(array('203.0.113.7', '2001:db8:1:2::/64', '198.51.100.0/24', '192.0.2.99'), $lists['block'], 'every block entry');
	pg_assert_same(array('198.51.100.0/24', '192.0.2.99'), $lists['block_manual'], 'hand-written block entries');
}

// Block mode enforces every entry; Monitor mode the hand-written ones only.
function test_waf_ip_block_entries_by_mode()
{
	$lists = waf_ip_lists_from_rows(waf_monitor_auto_ban_test_rows());

	pg_assert_same($lists['block'], waf_ip_block_entries($lists, 'block'), 'block');
	pg_assert_same(array('198.51.100.0/24', '192.0.2.99'), waf_ip_block_entries($lists, 'monitor'), 'monitor');
	pg_assert_same($lists['block'], waf_ip_block_entries($lists, 'off'), 'off: callers outside waf_run() keep the list');

	// A list built before this field existed reads as all hand-written.
	pg_assert_same(array('192.0.2.1'), waf_ip_block_entries(array('allow' => array(), 'block' => array('192.0.2.1')), 'monitor'), 'old shape');
}

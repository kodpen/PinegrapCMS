<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a ban writes: the pre-database shield's mirror lines with
 * the ban's end (waf_ban_shield_block_line() in waf.php,
 * pg_ban_shield_block_entry() in includes/db_guard.php), its page and its
 * pending line with a reference (pg_ban_shield_page(),
 * waf_shield_pending_line()), the rule in the ban note (waf_ban_note()) and
 * the path and status on data/firewall.log lines.
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
require_once(PG_FUNCTIONS_DIR . '/includes/db_guard.php');

// A block line carries the ban's end; a permanent entry carries 0.
function test_waf_shield_block_line_carries_the_end()
{
	pg_assert_same("B 1.2.3.4 1700003600\n", waf_ban_shield_block_line('1.2.3.4', 1700003600));
	pg_assert_same("B 2001:db8::/64 0\n", waf_ban_shield_block_line('2001:db8::/64', 0));
}

// The shield reads the end, skips an expired line and still reads a line
// written before the end was added.
function test_waf_shield_block_entry_parse()
{
	$now = 1700000000;

	pg_assert_same(array('pattern' => '1.2.3.4', 'expires' => 1700003600), pg_ban_shield_block_entry('1.2.3.4 1700003600', $now), 'a running ban');
	pg_assert_same(null, pg_ban_shield_block_entry('1.2.3.4 1699999999', $now), 'an expired ban');
	pg_assert_same(null, pg_ban_shield_block_entry('1.2.3.4 1700000000', $now), 'a ban ending now');
	pg_assert_same(array('pattern' => '1.2.3.0/24', 'expires' => 0), pg_ban_shield_block_entry('1.2.3.0/24 0', $now), 'a permanent ban');
	pg_assert_same(array('pattern' => '1.2.3.4', 'expires' => 0), pg_ban_shield_block_entry('1.2.3.4', $now), 'an old line without an end');
	pg_assert_same(array('pattern' => '1.2.3.4', 'expires' => 0), pg_ban_shield_block_entry('1.2.3.4 soon', $now), 'an unreadable end is no end');
	pg_assert_same(null, pg_ban_shield_block_entry('', $now), 'an empty line');
}

// The shield's page names the firewall, the ban's end and the reference.
function test_waf_shield_page_names_end_and_reference()
{
	$end = mktime(14, 30, 0, 10, 27, 2026);
	$page = pg_ban_shield_page('ABC123DEF456', $end);

	pg_assert_contains('<!DOCTYPE html>', $page);
	pg_assert_contains('firewall', $page);
	pg_assert_contains('ABC123DEF456', $page);
	pg_assert_contains(date('Y-m-d H:i', $end), $page);

	$permanent = pg_ban_shield_page('ABC123DEF456', 0);
	pg_assert_contains('ABC123DEF456', $permanent);
	pg_assert_false(strpos($permanent, date('Y-m-d H:i', $end)) !== false, 'no end for a permanent ban');

	pg_assert_contains('&lt;x&gt;', pg_ban_shield_page('<x>', 0), 'the reference is escaped');
}

// The shield's reference is the firewall's shape: twelve upper-case hex.
function test_waf_shield_reference_shape()
{
	pg_assert_true((bool) preg_match('/^[0-9A-F]{12}$/', pg_ban_shield_reference()));
}

// A pending line carries the reference; a line written before it still
// drains, with none.
function test_waf_shield_pending_line_parse()
{
	pg_assert_same(
		array('when' => 1700000000, 'ip' => '1.2.3.4', 'path' => '/a', 'reference' => 'ABC123DEF456'),
		waf_shield_pending_line("1700000000\t1.2.3.4\t/a\tABC123DEF456")
	);
	pg_assert_same(
		array('when' => 1700000000, 'ip' => '1.2.3.4', 'path' => '/a', 'reference' => ''),
		waf_shield_pending_line("1700000000\t1.2.3.4\t/a")
	);
	pg_assert_same(null, waf_shield_pending_line("0\t1.2.3.4\t/a"), 'no time');
	pg_assert_same(null, waf_shield_pending_line("1700000000\tnot-an-ip\t/a"), 'no address');
	pg_assert_same(null, waf_shield_pending_line(''), 'an empty line');
}

// The ban note names the rule that brought the address to the threshold.
function test_waf_shield_ban_note_names_the_rule()
{
	pg_assert_same('Automatic: rate-sensitive, 5+ firewall events', waf_ban_note('rate-sensitive', 5));
	pg_assert_same('Automatic: 5+ firewall events', waf_ban_note('', 5), 'no rule known');
	pg_assert_same('Automatic: 5+ firewall events', waf_ban_note('auto-ban', 5), 'the ban itself is not a rule');
}

// Every firewall.log line carries the request path, without its query.
function test_waf_shield_text_log_path()
{
	$saved = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

	$_SERVER['REQUEST_URI'] = '/get_file.php?name=a b.jpg';
	pg_assert_same('/get_file.php', waf_text_log_path());

	$_SERVER['REQUEST_URI'] = '/a b/c';
	pg_assert_same('/a_b/c', waf_text_log_path(), 'one token');

	unset($_SERVER['REQUEST_URI']);
	pg_assert_same('-', waf_text_log_path(), 'no request');

	if ($saved !== null) {
		$_SERVER['REQUEST_URI'] = $saved;
	}

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'path=' . waf_text_log_path()", $waf, 'waf_text_log() writes the path');
	pg_assert_contains("'rule' => \$rule", $waf, 'the BAN line names the rule');
}

// The rate refusals that write their own DENY line say what they answered.
function test_waf_shield_rate_callers_carry_status()
{
	$callers = array(
		'consent.php'         => 'rate-consent-answer',
		'stock_notify.php'    => 'rate-stock-notify',
		'wishlist.php'        => 'rate-wishlist',
		'shipment_lookup.php' => 'rate-shipment-lookup',
	);

	foreach ($callers as $file => $rule) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
		pg_assert_contains("array('status' => 429, 'rule' => '" . $rule . "')", $source, $file);
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
	pg_assert_contains('array(\'status\' => 429, \'rule\' => $rule)', $form, 'custom_form.php');
}

// The general job refreshes the mirror, so an ended ban leaves it on a
// quiet site.
function test_waf_shield_job_refreshes_the_mirror()
{
	pg_assert_contains('waf_ban_shield_refresh(true);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'));
}

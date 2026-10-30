<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guest links of sales quotes (includes/erp/quote_links.php,
 * erp_quote.php) that need no database: where a link stands, the kept parts
 * of its token, its end of day, issuing and accepting as the code reads,
 * the migration step, get_tables() and the firewall's list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/quote_links.php');
require_once(PG_FUNCTIONS_DIR . '/waf.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function erp_quote_links_test_link(array $over = array())
{
	return array_merge(array('expires_at' => 0, 'used_at' => 0, 'revoked_at' => 0), $over);
}

function erp_quote_links_test_quote(array $over = array())
{
	return array_merge(array('status' => 'open', 'valid_until' => date('Y-m-d', strtotime('+10 days')), 'invoice_status' => ''), $over);
}

function test_erp_quote_link_state_of_the_link()
{
	$now = time();
	$quote = erp_quote_links_test_quote();

	pg_assert_same('open', erp_quote_link_state(erp_quote_links_test_link(), $quote, $now), 'fresh');
	pg_assert_same('open', erp_quote_link_state(erp_quote_links_test_link(array('expires_at' => $now)), $quote, $now), 'its last second');
	pg_assert_same('used', erp_quote_link_state(erp_quote_links_test_link(array('used_at' => $now - 5)), $quote, $now), 'used');
	pg_assert_same('revoked', erp_quote_link_state(erp_quote_links_test_link(array('revoked_at' => $now - 5)), $quote, $now), 'revoked');
	pg_assert_same('revoked', erp_quote_link_state(erp_quote_links_test_link(array('revoked_at' => 1, 'used_at' => 1)), $quote, $now), 'revoked wins');
	pg_assert_same('expired', erp_quote_link_state(erp_quote_links_test_link(array('expires_at' => $now - 1)), $quote, $now), 'expired by date');
}

function test_erp_quote_link_state_asks_the_quote()
{
	$now = time();
	$link = erp_quote_links_test_link();
	$past = date('Y-m-d', strtotime('-2 days'));

	pg_assert_same('closed', erp_quote_link_state($link, erp_quote_links_test_quote(array('valid_until' => $past)), $now), 'expired quote');
	pg_assert_same('closed', erp_quote_link_state($link, erp_quote_links_test_quote(array('status' => 'invoiced', 'invoice_status' => 'issued')), $now), 'invoiced');
	pg_assert_same('closed', erp_quote_link_state($link, erp_quote_links_test_quote(array('status' => 'cancelled')), $now), 'cancelled');
	pg_assert_same('closed', erp_quote_link_state($link, erp_quote_links_test_quote(array('status' => 'rejected')), $now), 'rejected');
	pg_assert_same('accepted', erp_quote_link_state($link, erp_quote_links_test_quote(array('status' => 'accepted')), $now), 'accepted another way');
}

function test_erp_quote_link_token_parts_and_shape()
{
	$token = pg_short_link_new_token();
	$parts = erp_quote_link_token_parts($token);

	pg_assert_true(erp_quote_link_token_shape($token), 'a new token');
	pg_assert_false(erp_quote_link_token_shape(substr($token, 0, 42)), 'one short');
	pg_assert_false(erp_quote_link_token_shape(array($token)), 'a list');
	pg_assert_same(hash('sha256', $token), $parts['hash']);
	pg_assert_same(64, strlen($parts['hash']), 'fits CHAR(64)');
	pg_assert_same(substr($token, 0, 6), $parts['hint']);
	pg_assert_true(strlen($parts['hint']) <= 8, 'fits VARCHAR(8)');
}

function test_erp_quote_link_expires_at_end_of_day()
{
	pg_assert_same((int) mktime(23, 59, 59, 11, 30, 2026), erp_quote_link_expires_at('2026-11-30'));
	pg_assert_same(0, erp_quote_link_expires_at('0000-00-00'), 'no date');
	pg_assert_same(0, erp_quote_link_expires_at('2026-02-30'), 'no such day');
}

// The lookup compares by hash with hash_equals.
function test_erp_quote_link_lookup_by_hash()
{
	$source = pg_test_function_source('erp_quote_link_for_token');

	pg_assert_contains('erp_quote_link_token_shape(', $source);
	pg_assert_contains('token_hash = ', $source);
	pg_assert_contains('hash_equals(', $source);
}

// A new link revokes the earlier live ones before it is written.
function test_erp_quote_link_issue_revokes_earlier_links()
{
	$issue = pg_test_function_source('erp_quote_link_issue');
	$revoke = pg_test_function_source('erp_quote_link_revoke');

	pg_assert_true(strpos($issue, 'erp_quote_link_revoke(') !== false, 'revokes');
	pg_assert_true(strpos($issue, 'erp_quote_link_revoke(') < strpos($issue, 'INSERT INTO erp_quote_links'), 'before the new row');
	pg_assert_contains('erp_quote_link_expires_at(', $issue, 'expires with valid_until');
	pg_assert_contains('SET revoked_at = UNIX_TIMESTAMP()', $revoke);
	pg_assert_contains('revoked_at = 0 AND used_at = 0', $revoke, 'only live links');
	pg_assert_false(stripos($revoke, 'DELETE') !== false, 'rows are never deleted');
}

// Acceptance goes through the refusal, the signature store and the panel's writer.
function test_erp_quote_link_accept_path()
{
	$source = pg_test_function_source('erp_quote_link_accept');

	pg_assert_contains('erp_quote_status_refusal($quote, \'accepted\')', $source);
	pg_assert_contains('erp_signature_store(\'quote\'', $source);
	pg_assert_contains('erp_signature_document_hash_quote(', $source);
	pg_assert_contains('erp_quote_set_status(', $source, 'the panel\'s writer');
	pg_assert_contains('SET used_at = UNIX_TIMESTAMP()', $source, 'the link is marked used');
	pg_assert_false(strpos($source, 'UPDATE erp_quotes') !== false, 'no bare status flip');
}

function test_erp_quote_links_migration_step()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$start = strpos($file, 'function upgrade_2026_11_0_erp_quote_links()');

	pg_assert_true($start !== false, 'the step exists');
	pg_assert_contains('upgrade_2026_11_0_erp_quote_links();        // 11.0.64', $file, 'called on its ERP label');

	$step = substr($file, $start, strpos($file, "\n}\n", $start) - $start);

	pg_assert_contains("install_create_table('erp_quote_links'", $step);
	foreach (array(
		'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'quote_id INT UNSIGNED NOT NULL',
		"token_hash CHAR(64) NOT NULL DEFAULT ''",
		"token_hint VARCHAR(8) NOT NULL DEFAULT ''",
		'expires_at INT UNSIGNED NOT NULL DEFAULT 0',
		'used_at INT UNSIGNED NOT NULL DEFAULT 0',
		'revoked_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at INT UNSIGNED NOT NULL DEFAULT 0',
		'created_by INT UNSIGNED NOT NULL DEFAULT 0',
		'UNIQUE KEY idx_token_hash (token_hash)',
		'KEY idx_quote (quote_id)',
	) as $column) {
		pg_assert_contains($column, $step, $column);
	}
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no bare ALTER');
	pg_assert_false(strpos($step, 'db(') !== false, 'no direct query');
}

function test_erp_quote_links_in_get_tables()
{
	pg_assert_contains("\t\t'erp_quote_links',\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'));
}

function test_erp_quote_page_is_sensitive_and_offers_no_payment()
{
	pg_assert_true(in_array('erp_quote.php', waf_sensitive_scripts(), true), 'on the sensitive allowance');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_quote.php');

	pg_assert_contains('noindex', $page);
	pg_assert_contains('validate_token_field()', $page, 'the POST carries the form token');
	pg_assert_contains('erp_quote_link_accept(', $page);
	foreach (array('payment', 'checkout', 'pay_', 'iyzico', 'stripe', 'collection') as $word) {
		pg_assert_false(stripos(preg_replace('#/\*.*?\*/#s', '', $page), $word) !== false, 'no ' . $word);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's status read at the firewall: the GET that
 * only reads where a number stands (queue.php?n=&t=&read=1, every 15
 * seconds per open page) leaves the sign-in, forms and checkout allowance
 * and the cap on open requests (waf_sensitive_request_counts()) for a
 * bucket of its own (waf_queue_status_read()); taking a number, leaving
 * and opening the page stay sensitive.
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

// About 60 people at one read every 15 seconds on one address.
function test_waf_queue_status_limit()
{
	pg_assert_same(240, WAF_QUEUE_STATUS_RATE_LIMIT);
}

// Only a GET (or HEAD) of queue.php naming a number and asking for the read.
function test_waf_queue_status_read_detected()
{
	$read = array('n' => '12', 't' => 'abc', 'read' => '1');

	pg_assert_true(waf_queue_status_read('GET', 'queue.php', $read), 'the read');
	pg_assert_true(waf_queue_status_read('get', 'queue.php', $read), 'method case');
	pg_assert_true(waf_queue_status_read('HEAD', 'queue.php', $read), 'HEAD');

	pg_assert_false(waf_queue_status_read('POST', 'queue.php', $read), 'a POST (Leave the queue)');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('n' => '12', 't' => 'abc')), 'opening the page');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('n' => '12', 't' => 'abc', 'read' => '0')), 'read=0');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('n' => '12', 't' => 'abc', 'read' => '')), 'read empty');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('t' => 'abc', 'read' => '1')), 'no number');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('n' => array('12'), 'read' => '1')), 'number as an array');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('n' => '12', 'read' => array('1'))), 'read as an array');
	pg_assert_false(waf_queue_status_read('GET', 'queue.php', array('place' => '3', 'read' => '1')), 'the take page');
	pg_assert_false(waf_queue_status_read('GET', 'feedback.php', $read), 'another script');
	pg_assert_false(waf_queue_status_read('GET', 'Queue.php', $read), 'name case');
}

// The lobby screen's read shares the bucket; opening the lobby does not.
function test_waf_queue_status_read_takes_the_lobby_read()
{
	$lobby = array('place' => '3', 'k' => 'abc', 'read' => '1');

	pg_assert_true(waf_queue_status_read('GET', 'queue_lobby.php', $lobby), 'the lobby read');
	pg_assert_false(waf_queue_status_read('GET', 'queue_lobby.php', array('place' => '3', 'k' => 'abc')), 'opening the lobby');
	pg_assert_false(waf_queue_status_read('GET', 'queue_lobby.php', array('n' => '3', 'read' => '1')), 'no place');
	pg_assert_false(waf_queue_status_read('POST', 'queue_lobby.php', $lobby), 'a POST');
	pg_assert_false(waf_queue_status_read('GET', 'queue_kiosk.php', $lobby), 'the kiosk');
	pg_assert_false(waf_sensitive_request_counts('GET', 'queue_lobby.php', 'empty', '*/*', $lobby), 'off the sensitive allowance');
}

// The read is off the sensitive allowance; everything else on queue.php stays on it.
function test_waf_queue_status_leaves_sensitive_allowance()
{
	$read = array('n' => '12', 't' => 'abc', 'read' => '1');

	pg_assert_false(waf_sensitive_request_counts('GET', 'queue.php', 'empty', '*/*', $read), 'the read');
	pg_assert_true(waf_sensitive_request_counts('GET', 'queue.php', 'document', 'text/html', array('n' => '12', 't' => 'abc')), 'opening the page');
	pg_assert_true(waf_sensitive_request_counts('GET', 'queue.php', 'document', 'text/html', array('place' => '3')), 'the take page');
	pg_assert_true(waf_sensitive_request_counts('POST', 'queue.php', 'document', 'text/html', $read), 'a POST with the same query');
	pg_assert_true(waf_sensitive_request_counts('GET', 'queue.php', 'empty', '*/*'), 'no query given');
}

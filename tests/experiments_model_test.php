<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page experiment model in includes/fn/experiments.php: the
 * statuses and their moves, the weighted draw over fixed randoms, the pg_exp
 * bucket cookie's value both ways, and which requests never join an
 * experiment.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Draft starts; running pauses or ends; paused runs again or ends; ended is final.
function test_experiment_status_moves()
{
	pg_assert_same(array('draft', 'running', 'paused', 'ended'), pg_experiment_statuses());

	pg_assert_true(pg_experiment_may_move('draft', 'running'), 'start');
	pg_assert_true(pg_experiment_may_move('running', 'paused'), 'pause');
	pg_assert_true(pg_experiment_may_move('running', 'ended'), 'end');
	pg_assert_true(pg_experiment_may_move('paused', 'running'), 'resume');
	pg_assert_true(pg_experiment_may_move('paused', 'ended'), 'end a paused one');

	pg_assert_false(pg_experiment_may_move('draft', 'paused'), 'a draft never ran');
	pg_assert_false(pg_experiment_may_move('draft', 'ended'), 'a draft is deleted, not ended');
	pg_assert_false(pg_experiment_may_move('ended', 'running'), 'ended is final');
	pg_assert_false(pg_experiment_may_move('running', 'running'), 'no move to itself');
	pg_assert_false(pg_experiment_may_move('running', 'draft'), 'never back to draft');
	pg_assert_false(pg_experiment_may_move('bogus', 'running'), 'unknown status');
}

// Equal weights split the range in two; the random's place picks the key.
function test_experiment_draw_equal_weights()
{
	$weights = array('a' => 50, 'b' => 50);

	pg_assert_same('a', pg_experiment_draw($weights, 0.0), 'bottom');
	pg_assert_same('a', pg_experiment_draw($weights, 0.4999), 'just under the half');
	pg_assert_same('b', pg_experiment_draw($weights, 0.5), 'the half');
	pg_assert_same('b', pg_experiment_draw($weights, 0.9999), 'top');
}

// Uneven weights and three keys follow the given order.
function test_experiment_draw_uneven_weights()
{
	$weights = array('a' => 20, 'b' => 30, 'c' => 50);

	pg_assert_same('a', pg_experiment_draw($weights, 0.19), 'a below 0.2');
	pg_assert_same('b', pg_experiment_draw($weights, 0.2), 'b from 0.2');
	pg_assert_same('b', pg_experiment_draw($weights, 0.49), 'b below 0.5');
	pg_assert_same('c', pg_experiment_draw($weights, 0.5), 'c from 0.5');

	// Weights need not add up to 100.
	pg_assert_same('b', pg_experiment_draw(array('a' => 1, 'b' => 3), 0.3), 'b holds three quarters');
}

// A zero, negative or broken weight never wins; random outside [0, 1) is held in.
function test_experiment_draw_edges()
{
	pg_assert_same('b', pg_experiment_draw(array('a' => 0, 'b' => 50), 0.0), 'zero weight never drawn');
	pg_assert_same('b', pg_experiment_draw(array('a' => -10, 'b' => 5), 0.0), 'negative is zero');
	pg_assert_same('b', pg_experiment_draw(array('a' => 'x', 'b' => 5), 0.0), 'not a number is zero');
	pg_assert_same('', pg_experiment_draw(array('a' => 0, 'b' => 0), 0.5), 'nothing to draw');
	pg_assert_same('', pg_experiment_draw(array(), 0.5), 'no variants');
	pg_assert_same('b', pg_experiment_draw(array('a' => 50, 'b' => 50), 1.0), 'one is held under the top');
	pg_assert_same('a', pg_experiment_draw(array('a' => 50, 'b' => 50), -3), 'below zero is held at zero');
	pg_assert_same('b', pg_experiment_draw(array('a' => 50, 'b' => 50, 'c' => 0), 0.99), 'a trailing zero is skipped');
}

// The cookie value: pairs of experiment id and key, read back as written.
function test_experiment_bucket_round_trip()
{
	$pairs = array(12 => 'a', 7 => 'b2');

	$value = pg_experiment_bucket_format($pairs);
	pg_assert_same('7:b2.12:a', $value, 'sorted by id');
	pg_assert_same(array(7 => 'b2', 12 => 'a'), pg_experiment_bucket_parse($value), 'read back');

	pg_assert_same('', pg_experiment_bucket_format(array()), 'empty');
	pg_assert_same(array(), pg_experiment_bucket_parse(''), 'empty back');
}

// A value someone wrote by hand keeps only well-formed pairs.
function test_experiment_bucket_parse_drops_bad_pairs()
{
	pg_assert_same(array(3 => 'a'), pg_experiment_bucket_parse('3:a.x:b.4:.5:TOOLONGKEY.:c.0:d.6:a-b'), 'bad pairs dropped');
	pg_assert_same(array(3 => 'b'), pg_experiment_bucket_parse('3:a.3:b'), 'the later pair wins');
	pg_assert_same(array(), pg_experiment_bucket_parse(array('3:a')), 'an array is no value');
	pg_assert_same(array(9 => 'b'), pg_experiment_bucket_parse('9:B'), 'keys are lower case');
	pg_assert_same(array(), pg_experiment_bucket_parse(str_repeat('1:a.', 2000)), 'an oversized value is ignored');
}

// Writing drops bad pairs and keeps at most the newest experiments.
function test_experiment_bucket_format_limits()
{
	pg_assert_same('2:a', pg_experiment_bucket_format(array(2 => 'a', 0 => 'b', 3 => '', 4 => 'a b')), 'bad pairs never written');

	$many = array();
	for ($id = 1; $id <= PG_EXPERIMENT_COOKIE_PAIRS + 5; $id++) {
		$many[$id] = 'a';
	}

	$kept = pg_experiment_bucket_parse(pg_experiment_bucket_format($many));
	pg_assert_same(PG_EXPERIMENT_COOKIE_PAIRS, count($kept), 'capped');
	pg_assert_same(6, (int) key($kept), 'the oldest experiments drop first');
}

// The cookie lives 90 days.
function test_experiment_bucket_lifetime()
{
	pg_assert_same('pg_exp', PG_EXPERIMENT_COOKIE);
	pg_assert_same(90, PG_EXPERIMENT_COOKIE_DAYS);
	pg_assert_same(1000 + 90 * 86400, pg_experiment_bucket_expires(1000));
}

// Bots, staff, the designer, Preview and prototype play get the live page.
function test_experiment_request_excluded()
{
	$visitor = array('bot' => false, 'staff' => false, 'designer' => false, 'preview' => false, 'prototype' => false);

	pg_assert_same('', pg_experiment_request_excluded($visitor), 'a visitor joins');
	pg_assert_same('bot', pg_experiment_request_excluded(array_merge($visitor, array('bot' => true))), 'bot');
	pg_assert_same('staff', pg_experiment_request_excluded(array_merge($visitor, array('staff' => true))), 'staff');
	pg_assert_same('designer', pg_experiment_request_excluded(array_merge($visitor, array('designer' => true))), 'designer');
	pg_assert_same('preview', pg_experiment_request_excluded(array_merge($visitor, array('preview' => true))), 'preview');
	pg_assert_same('prototype', pg_experiment_request_excluded(array_merge($visitor, array('prototype' => true))), 'prototype play');
	pg_assert_same('bot', pg_experiment_request_excluded(array('bot' => true, 'staff' => true)), 'the first reason');
	pg_assert_same('', pg_experiment_request_excluded(array()), 'missing keys are a visitor');
}

// The bucket cookie keeps a page cacheable: it is not a personal Set-Cookie.
function test_experiment_cookie_not_a_personal_set_cookie()
{
	pg_assert_same(
		array('PHPSESSID'),
		pg_page_cache_set_cookie_names(array(
			'Set-Cookie: PHPSESSID=abc; path=/',
			'Set-Cookie: pg_exp=3%3Aa; expires=Thu, 01 Jan 2037 00:00:00 GMT; path=/; SameSite=Lax',
		)));
}

// The notice lists the bucket cookie as necessary.
function test_experiment_cookie_in_the_consent_catalogue()
{
	$names = array();

	foreach (pg_consent_catalogue('<p>page</p>')['necessary'] as $row) {
		$names[] = $row[0];
	}

	pg_assert_true(in_array(PG_EXPERIMENT_COOKIE, $names, true), 'pg_exp is listed');
}

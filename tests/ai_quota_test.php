<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Pinegrap AI's daily token counter (includes/fn/ai_quota.php):
 * the figure read from the gateway's licence answer, the kept row read back,
 * when the gateway is asked again, what the counter shows as the day ends,
 * its tone, and the card under the licence days.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Midnight in Istanbul (+03:00) on 2026-11-28, as the gateway sends it.
function ai_quota_test_reset()
{
	return strtotime('2026-11-27T21:00:00Z');
}

// A kept counter.
function ai_quota_test_quota($overrides = array())
{
	return array_merge(array(
		'enabled'   => true,
		'limit'     => 5000,
		'used'      => 1200,
		'resets_at' => ai_quota_test_reset(),
		'checked'   => ai_quota_test_reset() - 3600,
	), $overrides);
}

function test_ai_quota_from_reads_the_ai_block()
{
	$json = array('valid' => true, 'ai' => array('enabled' => true, 'daily_tokens' => 5000, 'unlimited' => false,
		'used' => 1234, 'remaining' => 3766, 'day' => '2026-11-27', 'resets_at' => '2026-11-28T00:00:00+03:00'));

	pg_assert_same(array('enabled' => true, 'limit' => 5000, 'used' => 1234, 'resets_at' => ai_quota_test_reset(), 'checked' => 100),
		pg_ai_quota_from($json, 100), 'the licence answer');
}

function test_ai_quota_from_without_a_block_is_not_shown()
{
	$off = array('valid' => false, 'status' => 'invalid', 'reason' => 'ai_not_enabled');

	pg_assert_same(array('enabled' => false, 'limit' => 0, 'used' => 0, 'resets_at' => 0, 'checked' => 7), pg_ai_quota_from($off, 7), 'AI off');
	pg_assert_false(pg_ai_quota_from(array('valid' => true, 'ai' => 'yes'), 7)['enabled'], 'a block that is not an object');
	pg_assert_false(pg_ai_quota_from(null, 7)['enabled'], 'no answer');
}

function test_ai_quota_from_keeps_odd_values_in_range()
{
	$quota = pg_ai_quota_from(array('ai' => array('enabled' => 1, 'daily_tokens' => -5, 'used' => '-3', 'resets_at' => 'not a date')), 1);

	pg_assert_same(0, $quota['limit'], 'a negative limit');
	pg_assert_same(0, $quota['used'], 'a negative count');
	pg_assert_same(0, $quota['resets_at'], 'an unreadable time');
	pg_assert_same(1800000000, pg_ai_quota_from(array('ai' => array('resets_at' => '1800000000')), 1)['resets_at'], 'a unix time');
}

function test_ai_quota_decode_reads_back_what_was_kept()
{
	$quota = ai_quota_test_quota();

	pg_assert_same($quota, pg_ai_quota_decode(json_encode($quota)), 'round trip');
	pg_assert_same(null, pg_ai_quota_decode(''), 'nothing kept');
	pg_assert_same(null, pg_ai_quota_decode('{"used":5}'), 'no check time');
	pg_assert_same(null, pg_ai_quota_decode('not json'), 'broken');
}

function test_ai_quota_due()
{
	$quota = ai_quota_test_quota();
	$checked = $quota['checked'];

	pg_assert_true(pg_ai_quota_due(null, $checked), 'nothing kept');
	pg_assert_false(pg_ai_quota_due($quota, $checked + PG_AI_QUOTA_TTL - 1), 'still fresh');
	pg_assert_true(pg_ai_quota_due($quota, $checked + PG_AI_QUOTA_TTL), 'grown old');
	pg_assert_true(pg_ai_quota_due(ai_quota_test_quota(array('checked' => ai_quota_test_reset() - 1)), ai_quota_test_reset()), 'the day ended');
	pg_assert_false(pg_ai_quota_due(ai_quota_test_quota(array('resets_at' => 0)), $checked + 1), 'no reset known');
}

function test_ai_quota_view_during_the_day()
{
	$view = pg_ai_quota_view(ai_quota_test_quota(), ai_quota_test_reset() - 60);

	pg_assert_same(array('used' => 1200, 'limit' => 5000, 'percent' => 24, 'tone' => 'success', 'resets_at' => ai_quota_test_reset()), $view, 'a quarter used');
	pg_assert_same('warning', pg_ai_quota_view(ai_quota_test_quota(array('used' => 4000)), 0)['tone'], 'at 80%');
	pg_assert_same('danger', pg_ai_quota_view(ai_quota_test_quota(array('used' => 5000)), 0)['tone'], 'the limit reached');
	pg_assert_same(100, pg_ai_quota_view(ai_quota_test_quota(array('used' => 9000)), 0)['percent'], 'past the limit');
}

function test_ai_quota_view_starts_a_new_day_at_zero()
{
	$view = pg_ai_quota_view(ai_quota_test_quota(array('used' => 5000)), ai_quota_test_reset());

	pg_assert_same(0, $view['used'], 'the count starts again');
	pg_assert_same('success', $view['tone'], 'and its tone');
	pg_assert_same(ai_quota_test_reset() + 86400, $view['resets_at'], 'the next midnight');
	pg_assert_same(ai_quota_test_reset() + 3 * 86400, pg_ai_quota_view(ai_quota_test_quota(), ai_quota_test_reset() + 2 * 86400 + 5)['resets_at'], 'days later');
}

function test_ai_quota_view_unlimited_and_off()
{
	$view = pg_ai_quota_view(ai_quota_test_quota(array('limit' => 0, 'used' => 999999)), 0);

	pg_assert_same(0, $view['percent'], 'no bar without a limit');
	pg_assert_same('success', $view['tone'], 'never over');
	pg_assert_same(null, pg_ai_quota_view(ai_quota_test_quota(array('enabled' => false)), 0), 'AI off');
	pg_assert_same(null, pg_ai_quota_view(null, 0), 'nothing kept');
}

function test_ai_quota_card_html()
{
	$view = pg_ai_quota_view(ai_quota_test_quota(array('used' => 4500)), 0);
	$html = pg_ai_quota_card_html($view);

	pg_assert_contains(h(lang('Pinegrap AI today')), $html, 'the title');
	pg_assert_contains(h(pg_ai_quota_line($view)), $html, 'the figures');
	pg_assert_contains(pg_ai_quota_number(4500), pg_ai_quota_line($view), 'the count');
	pg_assert_contains(pg_ai_quota_number(5000), pg_ai_quota_line($view), 'the limit');
	pg_assert_contains('progress-bar bg-warning', $html, 'the bar in its tone');
	pg_assert_contains('style="width:90%"', $html, 'the bar at its share');
	pg_assert_contains(h(lang(array('string' => 'Starts again at {var:1}', 'vars' => date('H:i', ai_quota_test_reset())))), $html, 'when it starts again');

	$unlimited = pg_ai_quota_card_html(pg_ai_quota_view(ai_quota_test_quota(array('limit' => 0, 'resets_at' => 0)), 0));

	pg_assert_false(strpos($unlimited, 'progress') !== false, 'no bar without a limit');
	pg_assert_contains(h(lang(array('string' => '{var:1} tokens, no daily limit', 'vars' => pg_ai_quota_number(1200)))), $unlimited, 'the count alone');
	pg_assert_false(strpos($unlimited, 'mt-1" style="font-size') !== false, 'no reset line when not known');
	pg_assert_same('', pg_ai_quota_card_html(null), 'nothing to show');
}

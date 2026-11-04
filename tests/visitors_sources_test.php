<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sources section of the Visitors overview
 * (includes/visitors/sources.php): the channel rows and their shares, the
 * top lists with the earlier period's figure, the summary days line and the
 * escaping of the bar and the lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/sources.php');

function test_visitors_sources_channel_rows_keep_the_rule_order_and_shares()
{
	$order = array('paid', 'email', 'ai', 'organic', 'social', 'referral', 'direct');
	$now = array(
		array('value' => 'direct', 'visits' => 50),
		array('value' => 'organic', 'visits' => 30),
		array('value' => 'Social', 'visits' => 20),
	);
	$before = array(
		array('value' => 'direct', 'visits' => 40),
		array('value' => 'email', 'visits' => 5),
	);

	$rows = pg_visitors_sources_channel_rows($now, $before, $order);

	pg_assert_same(array('email', 'organic', 'social', 'direct'), array_column($rows, 'channel'), 'rule order; a channel seen only before stays in, unseen ones go');
	pg_assert_same(array(0, 30, 20, 50), array_column($rows, 'visits'));
	pg_assert_same(array(0.0, 30.0, 20.0, 50.0), array_column($rows, 'share'));
	pg_assert_same(array(5, 0, 0, 40), array_column($rows, 'before'));
}

function test_visitors_sources_channel_rows_without_compare_and_unknown_values()
{
	$rows = pg_visitors_sources_channel_rows(array(
		array('value' => 'direct', 'visits' => 2),
		array('value' => 'pay_per_click', 'visits' => 1),
	), null, array('organic', 'direct'));

	pg_assert_same(array('direct', 'pay_per_click'), array_column($rows, 'channel'), 'a value the rule does not know comes last');
	pg_assert_same(array(null, null), array_column($rows, 'before'), 'no earlier figure when not compared');
	pg_assert_same(66.7, $rows[0]['share']);
	pg_assert_same(array(), pg_visitors_sources_channel_rows(array(), array(), array('direct')), 'nothing in either period');
}

function test_visitors_sources_top_rows_drop_empty_values_and_match_case()
{
	$rows = pg_visitors_sources_top_rows(array(
		array('value' => '', 'visits' => 90),
		array('value' => 'Google.com', 'visits' => 8),
		array('value' => 'bing.com', 'visits' => 4),
		array('value' => 'x.com', 'visits' => 1),
	), array(
		array('value' => 'google.com', 'visits' => 6),
	), 2);

	pg_assert_same(array(
		array('value' => 'Google.com', 'visits' => 8, 'before' => 6),
		array('value' => 'bing.com', 'visits' => 4, 'before' => 0),
	), $rows, 'a visit without a value is not a source; the earlier figure is found without regard to case');

	pg_assert_same(null, pg_visitors_sources_top_rows(array(array('value' => 'a', 'visits' => 1)), null, 5)[0]['before']);
}

function test_visitors_sources_days_text_names_the_sources()
{
	pg_assert_same('', pg_visitors_sources_days_text(array('summary_days' => 0, 'raw_days' => 0, 'summary_to' => null)));
	pg_assert_same(lang('Every day of this period was counted from the visit records.'), pg_visitors_sources_days_text(array('summary_days' => 0, 'raw_days' => 1, 'summary_to' => null)));
	pg_assert_same(lang('Every day of this period was counted from the visit records.'), pg_visitors_sources_days_text(array('summary_days' => 3, 'raw_days' => 1, 'summary_to' => null)), 'summary days with no visit in them count for nothing');

	$date = prepare_form_data_for_output('2026-11-03', 'date', false);
	$mixed = pg_visitors_sources_days_text(array('summary_days' => 6, 'raw_days' => 1, 'summary_to' => '2026-11-03'));
	pg_assert_contains($date, $mixed);
	pg_assert_contains(pg_format_number(6, 0), $mixed);

	$all = pg_visitors_sources_days_text(array('summary_days' => 30, 'raw_days' => 0, 'summary_to' => '2026-11-03'));
	pg_assert_contains($date, $all);
}

function test_visitors_sources_markup_escapes_values_and_draws_the_bar()
{
	$labels = pg_visitors_sources_channel_labels();
	$rows = pg_visitors_sources_channel_rows(array(
		array('value' => 'organic', 'visits' => 3),
		array('value' => 'direct', 'visits' => 1),
	), array(array('value' => 'organic', 'visits' => 1)), array('organic', 'direct'));

	$stack = pg_visitors_sources_stack($rows, $labels);
	pg_assert_contains('is-organic', $stack);
	pg_assert_contains('width:75.0%', $stack);
	pg_assert_contains('width:25.0%', $stack);

	$table = pg_visitors_sources_channel_table($rows, $labels, true);
	pg_assert_contains(h($labels['organic']), $table);
	pg_assert_contains('is-good', $table, 'the change against the earlier period');

	$plain = pg_visitors_sources_channel_table($rows, $labels, false);
	pg_assert_true(strpos($plain, h(lang('Previous period'))) === false, 'no earlier column when not compared');

	$list = pg_visitors_sources_top_table('Terms', array(array('value' => '<b>x</b>', 'visits' => 2, 'before' => null)), false);
	pg_assert_contains('&lt;b&gt;x&lt;/b&gt;', $list);
	pg_assert_true(strpos($list, '<b>x</b>') === false);

	pg_assert_same('', pg_visitors_sources_top_table('Terms', array(), true), 'an empty list is left out');
}

function test_visitors_sources_lists_show_the_change_without_an_earlier_column()
{
	$rows = array();

	for ($i = 1; $i <= 8; $i++) {
		$rows[] = array('value' => 'site' . $i, 'visits' => 10 * $i, 'before' => 5 * $i);
	}

	$html = pg_visitors_sources_top_table('Referring sites', $rows, true);

	pg_assert_contains(h(lang('Change vs. earlier')), $html, 'one change column');
	pg_assert_false(strpos($html, '>' . h(lang('Previous period')) . '</th>') !== false, 'no column of earlier figures');
	pg_assert_contains(h(lang(array('string' => 'Previous period: {var:1}', 'vars' => array(pg_format_number(5, 0))))), $html, 'the earlier figure on hover');
	pg_assert_same(3, substr_count($html, 'pg-visitors-extra'), 'five rows show, three wait');
	pg_assert_contains('data-pg-more=', $html);
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Value')) . '</span>', $html, 'the title names the first column');
}

function test_visitors_sources_campaign_lists_are_folded()
{
	foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'tracking_code', 'affiliate_code') as $dimension) {
		pg_assert_true(pg_visitors_sources_is_campaign($dimension), $dimension);
	}

	foreach (array('referring_host_name', 'referring_search_engine', 'referring_search_terms') as $dimension) {
		pg_assert_false(pg_visitors_sources_is_campaign($dimension), $dimension);
	}

	foreach (array_keys(pg_visitors_sources_lists(true)) as $dimension) {
		pg_assert_true(is_bool(pg_visitors_sources_is_campaign($dimension)), 'every list is placed');
	}
}

function test_visitors_sources_lists_show_affiliate_codes_only_with_the_program()
{
	pg_assert_true(!isset(pg_visitors_sources_lists(false)['affiliate_code']));
	pg_assert_true(isset(pg_visitors_sources_lists(true)['affiliate_code']));
	pg_assert_true(isset(pg_visitors_sources_lists(false)['referring_search_terms']));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visitors overview's frame (includes/visitors/shell.php):
 * the change against the earlier period, the section frame, the table and
 * bar escaping, the chart's data and hidden table, and the section list.
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

function test_visitors_change_data_reads_up_down_and_none()
{
	pg_assert_same(array('direction' => 'up', 'percent' => 50, 'good' => true), pg_visitors_change_data(150, 100));
	pg_assert_same(array('direction' => 'down', 'percent' => -25, 'good' => false), pg_visitors_change_data(75, 100));
	pg_assert_same(array('direction' => 'down', 'percent' => -25, 'good' => true), pg_visitors_change_data(75, 100, true), 'a fall is good when lower is better');
	pg_assert_same(array('direction' => 'same', 'percent' => 0, 'good' => null), pg_visitors_change_data(100, 100));
	pg_assert_same('same', pg_visitors_change_data(1002, 1000)['direction'], 'under half a percent rounds to no change');
	pg_assert_same('same', pg_visitors_change_data(0, 0)['direction'], 'nothing against nothing');

	foreach (array(array(5, 0), array(5, null), array(null, 5), array('x', 5)) as $pair) {
		pg_assert_same('none', pg_visitors_change_data($pair[0], $pair[1])['direction'], json_encode($pair));
	}
}

function test_visitors_change_markup_never_says_zero_percent_for_none()
{
	$none = pg_visitors_change(12, 0);
	pg_assert_contains('is-none', $none);
	pg_assert_contains(h(lang('No earlier figure')), $none);
	pg_assert_true(strpos($none, '%') === false, 'no percent without an earlier figure');

	$bad = pg_visitors_change(80, 100);
	pg_assert_contains('is-bad', $bad);
	pg_assert_contains('bi-arrow-down-right', $bad);
	pg_assert_contains(pg_format_number(20, 0) . ' %', $bad);
}

function test_visitors_section_frame_and_escaping()
{
	$html = pg_visitors_section('sources"x', 'A & B', '<p>body</p>', '<a href="#">x</a>');
	pg_assert_contains('id="sourcesx"', $html, 'the anchor keeps only safe characters');
	pg_assert_contains('A &amp; B', $html);
	pg_assert_contains('aria-labelledby="sourcesx_heading"', $html);
	pg_assert_contains('pg-visitors-section-aside', $html);

	$table = pg_visitors_table(array('Page', array('label' => 'Visits', 'end' => true)), array(
		array('<b>x</b>', array('html' => '<a href="/">1</a>', 'sort' => 1)),
	));
	pg_assert_contains('&lt;b&gt;x&lt;/b&gt;', $table, 'text cells are escaped');
	pg_assert_contains('<td class="text-end" data-sort="1"><a href="/">1</a></td>', $table);

	pg_assert_contains('pg-visitors-empty', pg_visitors_table(array('x'), array()));
	pg_assert_contains('pg-visitors-empty', pg_visitors_bars(array()));

	$bars = pg_visitors_bars(array(array('label' => '<i>', 'value' => 50), array('label' => 'b', 'value' => 100)));
	pg_assert_contains('&lt;i&gt;', $bars);
	pg_assert_contains('width:50%', $bars);
	pg_assert_contains('width:100%', $bars);
}

function test_visitors_chart_carries_its_data_and_a_text_table()
{
	$html = pg_visitors_chart('traffic_chart', array(
		'labels'  => array('Mon', '</script>'),
		'series'  => array(array('label' => 'Visits', 'data' => array(3, null)), array('label' => 'Views', 'data' => array(7, 2.5), 'dashed' => true, 'axis' => 'right')),
		'caption' => 'Visits by day',
	));

	pg_assert_contains('data-pg-chart="traffic_chart_data"', $html);
	pg_assert_contains('aria-hidden="true"', $html);
	pg_assert_true(strpos($html, '</script>",') === false && substr_count($html, '</script>') === 1, 'a label cannot end the JSON script');

	preg_match('/<script type="application\/json" id="traffic_chart_data">(.*?)<\/script>/s', $html, $match);
	$data = json_decode($match[1], true);
	pg_assert_same('line', $data['type']);
	pg_assert_same(array(3, null), $data['series'][0]['data']);
	pg_assert_same('right', $data['series'][1]['axis']);
	pg_assert_true($data['series'][1]['dashed']);

	pg_assert_contains('<caption>Visits by day</caption>', $html);
	pg_assert_contains('<td>–</td>', $html, 'a missing point is a dash');
	pg_assert_contains(pg_format_number(2.5, 1), $html);
}

function test_visitors_sections_order_and_files()
{
	pg_assert_same(
		array('alerts', 'traffic', 'conversions', 'sources', 'content', 'site_search', 'google', 'pages', 'speed', 'ai', 'places'),
		array_keys(pg_visitors_sections())
	);

	pg_assert_false(pg_visitors_section_exists('shell'), 'the frame is no section');
	pg_assert_false(pg_visitors_section_exists('../shell'));
}

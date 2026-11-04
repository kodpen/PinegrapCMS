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
	pg_assert_contains(h(pg_visitors_percent(20, 0)), $bad, 'through the language\'s percent form');
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

function test_visitors_change_without_an_earlier_figure_is_a_quiet_dash()
{
	$none = pg_visitors_change(12, 0);
	pg_assert_contains('<span aria-hidden="true">–</span>', $none, 'a dash is what shows');
	pg_assert_contains('<span class="visually-hidden">' . h(lang('No earlier figure')) . '</span>', $none, 'the words are read out');

	$cell = pg_visitors_change_cell(30, 20);
	pg_assert_contains('title="' . h(lang(array('string' => 'Previous period: {var:1}', 'vars' => array(pg_format_number(20, 0))))) . '"', $cell['html']);
	pg_assert_contains('is-good', $cell['html']);
	pg_assert_same(10.0, $cell['sort']);

	$plain = pg_visitors_change_cell(30, null);
	pg_assert_contains('is-none', $plain['html'], 'not compared');
	pg_assert_false(isset($plain['sort']));
}

function test_visitors_table_limit_label_and_hidden_first_heading()
{
	$rows = array();

	for ($i = 1; $i <= 7; $i++) {
		$rows[] = array('row' . $i, array('html' => (string) $i, 'sort' => $i));
	}

	$html = pg_visitors_table(array('Value', array('label' => 'Visits', 'end' => true)), $rows, array('caption' => 'A & B', 'limit' => 5, 'hide_first' => true));

	pg_assert_contains('aria-label="A &amp; B"', $html, 'named on the table');
	pg_assert_false(strpos($html, '<caption') !== false, 'no caption: it kept a pixel and drew a scroll bar');
	pg_assert_contains('is-collapsed', $html);
	pg_assert_same(2, substr_count($html, '<tr class="pg-visitors-extra">'), 'two rows past the limit');
	pg_assert_true((bool) preg_match('/<table [^>]*id="(pg_visitors_table_\d+)"/', $html, $match), 'an id for the button');
	pg_assert_contains('data-pg-more="' . $match[1] . '"', $html);
	pg_assert_contains('aria-controls="' . $match[1] . '"', $html);
	pg_assert_contains(h(lang(array('string' => 'Show all ({var:1})', 'vars' => array(pg_format_number(7, 0))))), $html);
	pg_assert_contains('<th scope="col"><span class="visually-hidden">Value</span></th>', $html);

	$short = pg_visitors_table(array('Value'), array(array('a'), array('b')), array('limit' => 5));
	pg_assert_false(strpos($short, 'data-pg-more') !== false, 'no button when every row shows');
	pg_assert_false(strpos($short, 'is-collapsed') !== false);
}

function test_visitors_chart_reads_out_its_tooltip_notes()
{
	$html = pg_visitors_chart('notes_chart', array(
		'labels' => array('Mon', 'Tue'),
		'series' => array(array('label' => 'Orders', 'data' => array(1, 2))),
		'notes'  => array('Revenue: <b>5</b>', ''),
	));

	pg_assert_contains('<th scope="col">' . h(lang('Notes')) . '</th>', $html);
	pg_assert_contains('<td>Revenue: &lt;b&gt;5&lt;/b&gt;</td>', $html, 'escaped, beside its figures');

	$plain = pg_visitors_chart('plain_chart', array('labels' => array('Mon'), 'series' => array(array('label' => 'x', 'data' => array(1)))));
	pg_assert_false(strpos($plain, h(lang('Notes'))) !== false, 'no column without notes');
}

function test_visitors_chart_writes_units_and_note_lines()
{
	$html = pg_visitors_chart('unit_chart', array(
		'labels' => array('Mon', 'Tue'),
		'series' => array(
			array('label' => 'LCP', 'data' => array(3.4, null), 'unit' => 's'),
			array('label' => 'Views', 'data' => array(52, 7)),
		),
		'notes'  => array("INP: 160 ms\nCLS: 0,07", ''),
	));

	pg_assert_contains('<td>' . h(pg_format_number(3.4, 1)) . ' s</td>', $html, 'the unit follows the figure');
	pg_assert_contains('<td>–</td>', $html, 'no figure, no unit');
	pg_assert_contains('<td>52</td>', $html, 'a series without a unit');
	pg_assert_contains('<td>INP: 160 ms; CLS: 0,07</td>', $html, 'the note lines read out as one cell');
	pg_assert_contains('"unit":"s"', $html, 'the script gets the unit');
	pg_assert_contains('INP: 160 ms\nCLS: 0,07', $html, 'the script gets the lines');
}

function test_visitors_chart_figures_are_hidden_through_a_box()
{
	$html = pg_visitors_chart('box_chart', array('labels' => array('a'), 'series' => array(array('label' => 'x', 'data' => array(1)))));

	pg_assert_contains('<div class="visually-hidden">', $html);
	pg_assert_false(strpos($html, '<table class="visually-hidden"') !== false, 'a hidden table keeps its width and widened the phone page');
}

function test_visitors_anchor_avoids_the_shells_ids()
{
	pg_assert_same('content_section', pg_visitors_anchor('content'), '<main> is #content');
	pg_assert_same('traffic', pg_visitors_anchor('traffic'));
	pg_assert_same('site_search', pg_visitors_anchor('site_search'));
	pg_assert_contains('id="content_section"', pg_visitors_section('content', 'Content', ''));
	pg_assert_contains('pg-visitors-section-icon', pg_visitors_section('traffic', 'Traffic', ''));

	foreach (array_keys(pg_visitors_sections()) as $name) {
		pg_assert_true(isset(pg_visitors_section_icons()[$name]), 'an icon for ' . $name);
	}
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

// A percentage goes through the '{var:1}%' key (pg_visitors_percent()), which
// Turkish words as %12, as everywhere in the panel; the sections had written
// "12 %" in every language.
function test_visitors_percent_follows_the_language()
{
	pg_assert_same(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(12.5, 1)))), pg_visitors_percent(12.5));
	pg_assert_same(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(3, 0)))), pg_visitors_percent(3, 0));

	$files = glob(PG_FUNCTIONS_DIR . '/includes/visitors/*.php');
	$files[] = PG_FUNCTIONS_DIR . '/includes/fn/visitor_insights.php';
	$files[] = PG_FUNCTIONS_DIR . '/includes/fn/site_search_stats.php';

	foreach ($files as $path) {
		$source = (string) file_get_contents($path);

		pg_assert_true(strpos($source, ". ' %'") === false, basename($path) . ': a percent sign glued in code');
		pg_assert_true(strpos($source, ". '%'") === false, basename($path) . ': a percent sign glued in code');
	}
}

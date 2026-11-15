<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the summary figures drawn on the server
 * (pg_report_figures_html() in includes/fn/report_view.php): the same cards
 * and row rule as the browser's report view, a smaller unit beside the
 * number, a card that leads somewhere, and the ERP report screens that
 * print them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_report_figure_value_html_unit_is_smaller()
{
	pg_assert_same('<small>₺</small>12.632,91', pg_report_figure_value_html('₺12.632,91'), 'symbol first');
	pg_assert_same('-<small>₺</small>1.250,00', pg_report_figure_value_html('-₺1.250,00'), 'minus before the symbol');
	pg_assert_same("-1.250,00<small>TL</small>", pg_report_figure_value_html("-1.250,00\u{00A0}TL"), 'symbol last after a no-break space');
	pg_assert_same('45.23<small>%</small>', pg_report_figure_value_html('45.23%'), 'percent');
	pg_assert_same('7', pg_report_figure_value_html('7'), 'a bare count');
	pg_assert_same('&lt;b&gt;', pg_report_figure_value_html('<b>'), 'escaped');
}

function test_report_figures_html_cards_and_rows()
{
	pg_assert_same('', pg_report_figures_html(array()), 'nothing without figures');

	$one = pg_report_view_figure('Net sales', '₺10,00', 'bi-bar-chart', 'teal', 'Gross profit ₺4,00');
	$html = pg_report_figures_html(array($one, $one, $one));

	pg_assert_contains('<dl class="pg-report-figs" style="--pg-report-figs: 3">', $html, 'three share a row');
	pg_assert_same(3, substr_count($html, '<div class="pg-card pg-report-fig">'), 'a card each');
	pg_assert_contains('<dt class="pg-report-fig-name"><span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><span class="bi bi-bar-chart"></span></span>Net sales</dt>', $html, 'the name beside its tile');
	pg_assert_contains('<dd class="pg-report-fig-value"><small>₺</small>10,00</dd>', $html, 'the figure');
	pg_assert_contains('<dd class="pg-report-fig-note">Gross profit ₺4,00</dd>', $html, 'the note');

	pg_assert_contains('--pg-report-figs: 5"', pg_report_figures_html(array_fill(0, 5, $one)), 'five share a row');
	pg_assert_contains('--pg-report-figs: 3"', pg_report_figures_html(array_fill(0, 6, $one)), 'six in two rows of three');
	pg_assert_contains('--pg-report-figs: 4"', pg_report_figures_html(array_fill(0, 8, $one)), 'eight in two rows of four');
	pg_assert_contains('--pg-report-figs: 4"', pg_report_figures_html(array_fill(0, 11, $one)), 'never more than four a row');
}

function test_report_figures_html_link_tone_and_class()
{
	$figure = pg_report_view_figure('Overdue', '₺5,00', 'bi-exclamation-circle', 'nope', '2 past due', 'bad');
	$figure['url'] = 'erp_invoices.php?filter=overdue&direction=sales';
	$figure['class'] = 'fg-danger';
	$html = pg_report_figures_html(array($figure));

	pg_assert_contains('<div class="pg-card pg-report-fig position-relative">', $html, 'a card that leads somewhere holds its link');
	pg_assert_contains('<a class="stretched-link pg-link-body-emphasis text-decoration-none" href="erp_invoices.php?filter=overdue&amp;direction=sales">Overdue</a>', $html, 'the name is the link');
	pg_assert_contains('pg-tone-gray', $html, 'an unknown tone is gray');
	pg_assert_contains('<dd class="pg-report-fig-value fg-danger">', $html, 'the figure keeps its colour');
	pg_assert_contains('<dd class="pg-report-fig-note is-bad">', $html, 'a bad note is marked');
}

function test_report_figures_html_erp_report_screens()
{
	foreach (array('erp_dashboard.php', 'erp_cashflow.php', 'erp_profit.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_report_figures_html(', $source, $file . ': figures as report cards');
		pg_assert_false(strpos($source, 'small text-uppercase fg-secondary') !== false, $file . ': no old figure label');
		pg_assert_false(strpos($source, 'text-uppercase h5 fg-primary fw-bold') !== false, $file . ': no old card title');
	}

	foreach (array('erp_dashboard.php', 'erp_cashflow.php', 'erp_profit.php', 'erp_vat_report.php', 'erp_aging.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($source, 'class="table ') !== false, $file . ': tables are report tables');
		pg_assert_contains('pg-report-frame', $source, $file . ': tables in report frames');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for scroll boxes under an opaque sticky heading row: the box keeps
 * a scroll-padding as large as that row (and, in the spreadsheet grid, as
 * wide as the row-number column), so a link or cell reached with the
 * keyboard stops beside the heading instead of under it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_sticky_heading_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

function test_sticky_heading_erp_boxes_pad_the_heading_row()
{
	$boxes = array(
		'erp_profit.php' => 'erp_profit_accounts',
		'erp_vat_report.php' => 'erp_vat_documents',
	);

	foreach ($boxes as $file => $table) {
		$source = _test_sticky_heading_source($file);
		$found = preg_match('/<div class="[^"]*table-responsive" style="([^"]*)">\s*<table[^>]*id="' . $table . '"/', $source, $match);

		pg_assert_same(1, $found, $file . ': the box around #' . $table);
		pg_assert_contains('scroll-padding-top:2.5rem', $found ? $match[1] : '', $file . ': the box clears its sticky heading row');
	}
}

function test_sticky_heading_office_grid_pads_heading_and_row_numbers()
{
	$source = _test_sticky_heading_source('assets/js/pg_office_view.js');

	pg_assert_contains('pane.style.scrollPaddingTop', $source, 'the grid pane clears the column-letter row');
	pg_assert_contains('pane.style.scrollPaddingLeft', $source, 'the grid pane clears the row-number column');

	$select = strpos($source, 'function select(td)');
	$scroll = strpos($source, 'td.scrollIntoView', (int) $select);
	$pad = strpos($source, 'padHeadings()', (int) $select);

	pg_assert_true($select !== false && $pad !== false && $pad < $scroll, 'select() measures the headings before it scrolls the cell into view');
}

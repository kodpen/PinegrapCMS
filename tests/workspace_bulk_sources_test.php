<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sources of bulk product drafts in the Workspace
 * (includes/workspace/bulk_sources.php): a pasted list and a channel's
 * spreadsheet laid out as the rows of one bulk create card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk_sources.php');

// Lines split on tabs (a range copied from a sheet), semicolons or bars;
// empty lines left out.
function test_ws_bulk_text_table_splits_lines_and_cells()
{
	$table = ws_bulk_text_table("Mavi kupa\t129,90\n\n  Kırmızı kupa ; 99 TL \r\nYeşil kupa | 1.250,00\n");

	pg_assert_same(array(array('Mavi kupa', '129,90'), array('Kırmızı kupa', '99 TL'), array('Yeşil kupa', '1.250,00')), $table, 'three rows');
	pg_assert_same(array(array('Tek ürün')), ws_bulk_text_table('Tek ürün'), 'one name');
	pg_assert_same(array(), ws_bulk_text_table(" \n\t\n"), 'nothing');
}

// Several lines (empty ones not counted) make a list; one line does not.
function test_ws_bulk_text_is_list()
{
	pg_assert_true(ws_bulk_text_is_list("Mavi kupa 129,90\nKırmızı kupa 99"), 'two lines');
	pg_assert_true(ws_bulk_text_is_list("Ürün adı;Fiyat\nMavi kupa;129,90"), 'a header and a row');
	pg_assert_false(ws_bulk_text_is_list('Mavi kupa 129,90'), 'one line');
	pg_assert_false(ws_bulk_text_is_list("\nMavi kupa 129,90\n\n  \n"), 'one line among empty ones');
	pg_assert_false(ws_bulk_text_is_list(''), 'nothing');
}

// With no header, the first cell is the name (written as the short
// description too, as /product does) and an amount after it the price.
function test_ws_bulk_table_rows_without_a_header()
{
	$laid = ws_bulk_table_rows(array(array('Mavi kupa', '129,90'), array('Kırmızı kupa'), array('Yeşil kupa', '1.250,00 TL')));

	pg_assert_true($laid['ok'], 'laid out');
	pg_assert_same(array('name' => 'Mavi kupa', 'short_description' => 'Mavi kupa', 'price' => 12990), $laid['rows'][0], 'name and price');
	pg_assert_same(array('name' => 'Kırmızı kupa', 'short_description' => 'Kırmızı kupa'), $laid['rows'][1], 'a name alone');
	pg_assert_same(125000, $laid['rows'][2]['price'], 'grouped price with a code');

	$bad = ws_bulk_table_rows(array(array('Mavi kupa', '129,90'), array('Kırmızı kupa', 'pahalı', '12')));
	pg_assert_true($bad['ok'], 'words in a later cell are not a price');
	pg_assert_same(1200, $bad['rows'][1]['price'], 'the amount after them is');
}

// A first row that names known columns is a header, read in English or
// Turkish as the import names them; columns it does not know are named back.
function test_ws_bulk_table_rows_with_a_header()
{
	$laid = ws_bulk_table_rows(array(
		array('Stok kodu', 'Ürün adı', 'Fiyat', 'Stok', 'Renk', 'Marka'),
		array('KUPA-1', 'Mavi kupa', '129,90', '12', 'mavi', 'Kodpen'),
		array('KUPA-2', 'Kırmızı kupa', '', '', 'kırmızı', ''),
	));

	pg_assert_true($laid['ok'], 'laid out');
	pg_assert_same(array('name' => 'KUPA-1', 'short_description' => 'Mavi kupa', 'price' => 12990, 'quantity' => '12', 'brand' => 'Kodpen'), $laid['rows'][0], 'mapped by the header');
	pg_assert_same(array('name' => 'KUPA-2', 'short_description' => 'Kırmızı kupa'), $laid['rows'][1], 'empty cells left out');
	pg_assert_same(array('Renk'), $laid['ignored'], 'the unknown column is named');

	$import = ws_bulk_table_rows(array(array('name', 'short_description', 'price'), array('A-1', 'Kupa', '10.5')));
	pg_assert_same(array('name' => 'A-1', 'short_description' => 'Kupa', 'price' => 1050), $import['rows'][0], 'the import file\'s own column names');

	$title_only = ws_bulk_table_rows(array(array('Ürün adı', 'Fiyat'), array('Mavi kupa', '5')));
	pg_assert_same('Mavi kupa', $title_only['rows'][0]['name'], 'no code column: the title is the code');

	$bad = ws_bulk_table_rows(array(array('Ürün adı', 'Fiyat'), array('Mavi kupa', 'yok')));
	pg_assert_false($bad['ok'], 'a price that is not an amount');
	pg_assert_contains('2', $bad['error'], 'the row is named');
}

function test_ws_bulk_table_rows_refuses_more_than_a_card()
{
	$table = array();

	for ($i = 1; $i <= WS_BULK_CREATE_MAX + 1; $i++) {
		$table[] = array('Ürün ' . $i);
	}

	$laid = ws_bulk_table_rows($table);
	pg_assert_false($laid['ok'], '201 rows');
	pg_assert_contains('import_products.php', $laid['error'], 'the import is named');

	pg_assert_false(ws_bulk_table_rows(array())['ok'], 'no rows');
	pg_assert_false(ws_bulk_table_rows(array(array('Ürün adı', 'Fiyat')))['ok'], 'a header alone');
}

// A CSV file is read with its own separator.
function test_ws_bulk_file_table_reads_csv()
{
	$path = tempnam(sys_get_temp_dir(), 'pgcsv');
	file_put_contents($path, "\xEF\xBB\xBFÜrün adı;Fiyat\n\"Mavi; kupa\";129,90\n");

	$read = ws_bulk_file_table($path, 'liste.csv');
	unlink($path);

	pg_assert_true($read['ok'], 'read');
	pg_assert_same(array(array('Ürün adı', 'Fiyat'), array('Mavi; kupa', '129,90')), $read['table'], 'semicolons, quotes and the BOM');

	pg_assert_false(ws_bulk_file_table(__FILE__, 'notlar.txt')['ok'], 'not a table file');
}

// The file button sends through a ws_ action with the token checked; a card
// the person made names them, not an assistant, when it is applied. The
// pasted-list window is gone: several lines go under /create product.
function test_ws_bulk_create_propose_action_and_own_card()
{
	$row = pg_panel_action('ws_bulk_create_propose');

	pg_assert_true(is_array($row), 'ws_bulk_create_propose is in the table');
	pg_assert_same('pg_panel_ws', $row['handler'] ?? '', 'answered by the workspace');
	pg_assert_true(!empty($row['token']), 'with the token checked');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("case 'ws_bulk_create_propose':", $actions, 'the action is answered');

	$bulk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
	pg_assert_contains("\$own = ((int) \$change['request_id'] === 0);", $bulk, 'the step knows a card of the person\'s own');
	pg_assert_contains("ws_change_source(array('kind' => 'person'", $bulk, 'and names the person in the records\' sentences');
	pg_assert_contains("'Bulk change by {var:1}:", $bulk, 'and in the decision');

	$sources = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bulk_sources.php');
	pg_assert_contains('ws_bulk_create_input($viewer, \'product\', $laid[\'rows\'])', $sources, 'the rows are checked as an assistant\'s would be');
	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $sources, 'a file is read only from a channel the person may read');
	pg_assert_false(strpos($sources, "\$source['text']") !== false, 'no pasted text is read by the action');
	pg_assert_false(strpos(substr($actions, strpos($actions, "case 'ws_bulk_create_propose':"), 600), "\$request['text']") !== false, 'nor sent to it');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_false(strpos($script, 'bulk_make_intro') !== false, 'the window is gone from the screen');
	pg_assert_contains('result.command.bulk', $script, 'a bulk card from a command is told');
}

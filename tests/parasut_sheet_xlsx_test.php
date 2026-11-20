<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the Paraşüt import sheets written by pg_xlsx_write_layout() with the
 * heads of includes/fn/parasut_sheet.php: Orders > Export for Paraşüt
 * Selected and the ERP export's Paraşüt profiles. From the same input, the
 * cells - values, types and number formats - equal those the earlier writers
 * produced: PHPExcel filling the template for the orders export, the
 * template's zip filled from row 4 for the ERP export. PHPExcel is loaded
 * here only, in a process of its own; without includes/phpexcel the
 * comparison is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The orders export's input: cells by row and column letter, as
 * edit_orders.php fills them. Two orders; the first has two lines and a
 * shipping fee, the second one line.
 */
function parasut_sheet_test_order_cells()
{
	$invoice = array(
		4 => array('A' => '=HYPERLINK("http://x.test","a")', 'B' => 'Mağaza - 1001', 'C' => array('date' => '2026-11-19 14:30'), 'F' => array('date' => '2026-11-18 09:05'),
			'H' => 'Fatura', 'K' => 'Pinegrap CMS', 'L' => 'Çay bardağı', 'O' => 2, 'P' => 1000 / 1.2 / 100, 'Q' => 120 / 1.2 / 100, 'R' => 20, 'S' => 0),
		5 => array('L' => '#N/A', 'O' => 1, 'P' => 25.5, 'Q' => 0, 'R' => 20, 'S' => 0),
		6 => array('L' => 'Order shipping fee', 'O' => 1, 'P' => 4999 / 1.2 / 100, 'Q' => 0, 'R' => 20, 'S' => 0),
		7 => array('A' => 'Şule Öztürk', 'B' => 'Mağaza - 1002', 'C' => array('date' => '2026-11-19 14:30'), 'F' => array('date' => '2026-01-01 00:00'),
			'H' => 'Fatura', 'K' => 'Pinegrap CMS', 'L' => 'Kupa', 'O' => 3, 'P' => 12, 'Q' => 0, 'R' => 0, 'S' => 0),
	);
	$contacts = array(
		4 => array('A' => '=HYPERLINK("http://x.test","a")', 'C' => 'M', 'D' => 'a@b.test', 'E' => 'Pinegrap CMS', 'F' => 'Atatürk Cd. 5 34000', 'G' => 'Kadıköy',
			'H' => 'İstanbul', 'I' => '0212 000 00 00', 'J' => '', 'M' => '00012345678'),
		5 => array('A' => 'Şule Öztürk', 'C' => 'M', 'D' => 'sule@b.test', 'E' => 'Pinegrap CMS', 'F' => '', 'G' => 'Çankaya', 'H' => 'Ankara', 'I' => '+90 312', 'J' => '', 'M' => ''),
	);

	return array('invoices' => $invoice, 'contacts' => $contacts);
}

/**
 * The ERP export's input: rows as erp_export_rows_parasut_*() return them.
 */
function parasut_sheet_test_erp_rows()
{
	return array(
		'invoices' => array(
			array('Acme A.Ş.', 'SF2026000001 #1001', '19.11.2026', 'USD', 32.5125, '19.12.2026', 1625.63, 'Fatura', 'SF', 1, '', 'Danışmanlık', '', '', 1.5, 1000, 25.5, 20, '', ''),
			array('', '', '', '', '', '', '', '', '', '', '', 'Kurulum', '', '', 1, 250, '', 10, '', ''),
			array('Şule Öztürk', '=1+1', '01.01.2026', 'TRL', '', '', '', 'Taslak', '', '', '', '#N/A', '', '', 2, 0.1, '', 0, '', ''),
		),
		'contacts' => array(
			array('Acme A.Ş.', '', 'M', 'acme@x.test', '', 'Cad. 1 06000', 'Çankaya', 'Ankara', '0312', '', 'Kavaklıdere', '1234567890', '', '', '', '', ''),
			array('Şule Öztürk', '', 'T', '', '', '', '', '', '', '', '', '', '00012345678', '', '', '', ''),
		),
	);
}

/**
 * The formats edit_orders.php gives the invoice sheet's data columns.
 */
function parasut_sheet_test_invoice_layout()
{
	$layout = pg_parasut_sheet_layout('invoices');
	$layout['formats'] = array(2 => 'd.mm.yyyy', 5 => 'd.mm.yyyy', 15 => '#,##0.00', 16 => '#,##0.00', 17 => '#,##', 18 => '#,##0.00');

	return $layout;
}

// Every cell of the sheets, read back by PHPExcel: written the way the
// product wrote them before and the way it writes them now.
function test_parasut_sheets_match_the_cells_written_before()
{
	$library = PG_FUNCTIONS_DIR . '/includes/phpexcel';

	if (!is_file($library . '/PHPExcel.php') || !is_dir($library . '/templates') || !class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_parasut_sheet_' . bin2hex(random_bytes(4));
	mkdir($dir);

	$orders = parasut_sheet_test_order_cells();
	$erp = parasut_sheet_test_erp_rows();

	pg_assert_true(pg_xlsx_write_layout(parasut_sheet_test_invoice_layout(), pg_parasut_sheet_rows($orders['invoices'], 4), $dir . '/new_orders_invoices.xlsx'));
	pg_assert_true(pg_xlsx_write_layout(pg_parasut_sheet_layout('contacts'), pg_parasut_sheet_rows($orders['contacts'], 4), $dir . '/new_orders_contacts.xlsx'));

	foreach (array('invoices' => 'parasut_invoices', 'contacts' => 'parasut_contacts') as $kind => $profile_id) {
		$profile = array('id' => $profile_id, 'sheet' => $kind, 'first_row' => 4, 'label' => $profile_id);
		pg_assert_true(erp_export_write_xlsx($profile, erp_export_columns($profile), $erp[$kind], $dir . '/new_erp_' . $kind . '.xlsx'));

		// The ERP export before: the template's zip with its worksheet's rows
		// from row 4 replaced by pg_xlsx_sheet_rows().
		$template = $library . '/templates/' . (($kind === 'invoices') ? 'parasut_satis_faturalari.xlsx' : 'parasut_musteri_ve_tedarikciler.xlsx');
		$old = $dir . '/old_erp_' . $kind . '.xlsx';
		copy($template, $old);
		$zip = new ZipArchive();
		$zip->open($old);
		$sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
		$kept = '';
		preg_match_all('/<row\b[^>]*?(?:\/>|>.*?<\/row>)/s', $sheet, $matches);
		foreach ($matches[0] as $row_xml) {
			if (preg_match('/\br="(\d+)"/', $row_xml, $r) && ((int) $r[1] < 4)) {
				$kept .= $row_xml;
			}
		}
		$sheet = preg_replace('/<sheetData\b[^>]*?(?:\/>|>.*?<\/sheetData>)/s', '<sheetData>' . $kept . pg_xlsx_sheet_rows($erp[$kind], 4) . '</sheetData>', $sheet, 1);
		$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
		$zip->close();
	}

	// The orders export before: PHPExcel fills the templates as edit_orders.php
	// did, text columns as explicit text cells, the rest through setCellValue().
	$text_columns = array('invoices' => array('A', 'B', 'L'), 'contacts' => array('A', 'D', 'F', 'G', 'H', 'I', 'J', 'M'));
	$script = $dir . '/run.php';
	$code = '<?php
error_reporting(E_ALL);
date_default_timezone_set("UTC");
require ' . var_export($library . '/PHPExcel.php', true) . ';
$library = ' . var_export($library, true) . ';
$dir = ' . var_export($dir, true) . ';
$orders = ' . var_export($orders, true) . ';
$text = ' . var_export($text_columns, true) . ';
$border = array("borders" => array("allborders" => array("style" => PHPExcel_Style_Border::BORDER_THIN, "color" => array("rgb" => "b8b8b8"))));
foreach (array("invoices" => "parasut_satis_faturalari.xlsx", "contacts" => "parasut_musteri_ve_tedarikciler.xlsx") as $kind => $file) {
	$book = PHPExcel_IOFactory::load($library . "/templates/" . $file);
	$sheet = $book->getActiveSheet();
	foreach ($orders[$kind] as $row => $cells) {
		foreach ($cells as $column => $value) {
			if (is_array($value)) {
				$value = PHPExcel_Shared_Date::PHPToExcel(date("j.m.Y H:i", strtotime($value["date"])));
			}
			if (in_array($column, $text[$kind], true)) {
				$sheet->setCellValueExplicit($column . $row, $value, PHPExcel_Cell_DataType::TYPE_STRING);
			} else {
				$sheet->setCellValue($column . $row, $value);
			}
		}
		$last = ($kind === "invoices") ? "T" : "Q";
		$sheet->getStyle("A" . $row . ":" . $last . $row)->applyFromArray($border);
		$sheet->getStyle("A" . $row . ":" . $last . $row)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
		if ($kind === "invoices") {
			foreach (array("C", "F") as $c) {
				$sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_PARASUT);
			}
			foreach (array("P", "Q", "S") as $c) {
				$sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED_PARASUT);
			}
			$sheet->getStyle("R" . $row)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED_PARASUT1);
		}
	}
	PHPExcel_IOFactory::createWriter($book, "Excel2007")->save($dir . "/old_orders_" . $kind . ".xlsx");
	$book->disconnectWorksheets();
}
$out = array();
foreach (glob($dir . "/*.xlsx") as $path) {
	$book = PHPExcel_IOFactory::createReader("Excel2007")->load($path);
	$sheet = $book->getSheet(0);
	$cells = array();
	foreach ($sheet->getCellCollection() as $reference) {
		$cell = $sheet->getCell($reference);
		$value = $cell->getValue();
		if ($value instanceof PHPExcel_RichText) {
			$value = $value->getPlainText();
		}
		if (($value === null) || ($value === "")) {
			continue;
		}
		$type = $cell->getDataType();
		$cells[$reference] = array(
			"v" => $value,
			"t" => ($type === "inlineStr") ? "s" : $type,
			"f" => is_numeric($value) && !is_string($value) ? $sheet->getStyle($reference)->getNumberFormat()->getFormatCode() : "",
		);
	}
	$out[basename($path, ".xlsx")] = array("title" => $sheet->getTitle(), "cells" => $cells);
	$book->disconnectWorksheets();
}
echo json_encode($out);
';
	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
	$read = json_decode($output, true);

	foreach (glob($dir . '/*') as $file) {
		unlink($file);
	}
	rmdir($dir);

	pg_assert_true(is_array($read), 'PHPExcel read the files: ' . substr($output, 0, 400));

	foreach (array('orders_invoices', 'orders_contacts', 'erp_invoices', 'erp_contacts') as $name) {
		$old = $read['old_' . $name];
		$new = $read['new_' . $name];

		pg_assert_same($old['title'], $new['title'], $name . ' sheet name');
		pg_assert_same(array_keys($old['cells']), array_keys($new['cells']), $name . ' the same cells hold a value');

		foreach ($old['cells'] as $reference => $cell) {
			$other = $new['cells'][$reference];
			$label = $name . ' ' . $reference;

			if ($reference === 'A1') {
				// The help note: the template's line breaks are \n or \r\n.
				pg_assert_same(str_replace("\r\n", "\n", trim($cell['v'])), trim($other['v']), $label . ' help note');
				continue;
			}

			pg_assert_same($cell['t'], $other['t'], $label . ' type');

			if ($cell['t'] === 'n') {
				pg_assert_true(abs((float) $cell['v'] - (float) $other['v']) < 1e-9, $label . ' number ' . $cell['v'] . ' / ' . $other['v']);
				pg_assert_same($cell['f'], $other['f'], $label . ' number format');
			} else {
				// The template's heading 'FİRMA ADI * ' keeps its trailing space.
				pg_assert_same((string) $cell['v'], (string) $other['v'], $label . ' text');
			}
		}
	}
}

// Typed text is a text cell however it starts: never a formula.
function test_parasut_sheet_text_is_never_a_formula()
{
	$dir = sys_get_temp_dir() . '/pg_parasut_sheet_' . bin2hex(random_bytes(4));
	mkdir($dir);
	$path = $dir . '/s.xlsx';

	pg_assert_true(pg_xlsx_write_layout(pg_parasut_sheet_layout('contacts'), array(array(0 => '=HYPERLINK("http://x.test","a")', 2 => 'M', 3 => '#N/A', 4 => '')), $path));

	$zip = new ZipArchive();
	$zip->open($path);
	$sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
	$styles = (string) $zip->getFromName('xl/styles.xml');
	$zip->close();
	unlink($path);
	rmdir($dir);

	pg_assert_true(strpos($sheet, '<f>') === false, 'no formula');
	pg_assert_true(strpos($sheet, '<c r="A4" s="') !== false && strpos($sheet, 't="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;http://x.test&quot;,&quot;a&quot;)</t>') !== false, 'the name as text');
	pg_assert_true((bool) preg_match('/<c r="E4" s="\d+"\/>/', $sheet), 'an empty value is an empty bordered cell');
	pg_assert_true(strpos($sheet, '<c r="Q4" s="') !== false, 'every column of a data row is drawn');
	pg_assert_true(strpos($sheet, '<mergeCell ref="A1:E1"/>') !== false, 'the help note spans its columns');
	pg_assert_true(strpos($sheet, '<row r="1" ht="183.75" customHeight="1">') !== false, 'the help note row keeps its height');
	pg_assert_true(strpos($styles, 'FFB8B8B8') !== false, 'the grey border');
}

// The ERP profiles name their sheet; no template file is read.
function test_erp_parasut_profiles_name_their_sheet()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');

	pg_assert_true(strpos($source, "'sheet' => 'invoices'") !== false, 'invoices profile');
	pg_assert_true(strpos($source, "'sheet' => 'contacts'") !== false, 'contacts profile');
	pg_assert_true(strpos($source, 'phpexcel') === false, 'no template from includes/phpexcel');
	pg_assert_same(20, count(erp_export_columns(array('id' => 'parasut_invoices'))));
	pg_assert_same('FİRMA ADI *', erp_export_columns(array('id' => 'parasut_contacts'))[0]);
}

// pg_parasut_sheet_rows(): row numbers to a list from the first data row,
// letters to column indexes.
function test_parasut_sheet_rows_from_cells()
{
	pg_assert_same(array(), pg_parasut_sheet_rows(array(), 4));
	pg_assert_same(
		array(array(0 => 'a', 11 => 'l'), array(), array(19 => 't', 26 => 'aa')),
		pg_parasut_sheet_rows(array(4 => array('L' => 'l', 'A' => 'a'), 6 => array('T' => 't', 'AA' => 'aa')), 4)
	);
}

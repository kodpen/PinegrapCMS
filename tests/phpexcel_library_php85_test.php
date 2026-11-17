<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the bundled PHPExcel (includes/phpexcel) stays quiet on PHP 8.5
 * where the product uses it: the Parasut and ERP exports fill a template and
 * write it as .xlsx, and the product and bank statement importers read .xlsx,
 * .xls and .ods files cell by cell. The library is no longer maintained, so it
 * carries a local patch; a deprecation or warning here is written to the
 * error log on every export or import.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_phpexcel_product_paths_run_without_notices()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$library = PG_FUNCTIONS_DIR . '/includes/phpexcel';
	$script = tempnam(sys_get_temp_dir(), 'pg_phpexcel');
	$code = '<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
$quiet = false;
// A notice the @ operator silences is not logged, so it is not counted.
set_error_handler(function ($severity, $message, $file, $line) use (&$quiet) {
	if (!$quiet && (error_reporting() & $severity)) {
		echo "ERROR " . $message . " (" . basename($file) . ":" . $line . ")\n";
	}
	return true;
});
$library = ' . var_export($library, true) . ';
require $library . "/PHPExcel.php";
$dir = sys_get_temp_dir() . "/pg_phpexcel_test_" . bin2hex(random_bytes(4));
mkdir($dir);
$out = array();
$book = PHPExcel_IOFactory::load($library . "/templates/parasut_satis_faturalari.xlsx");
$book->getProperties()->setCreator("Pinegrap")->setTitle("Test");
$sheet = $book->getActiveSheet();
$sheet->setCellValue("A2", "Fatura ğüş")->setCellValue("B2", 12.5)->setCellValue("D2", "=B2*2");
$sheet->setCellValueExplicit("C2", "00123", PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValue("E2", PHPExcel_Shared_Date::PHPToExcel(gmmktime(0, 0, 0, 11, 17, 2026)));
$sheet->getStyle("E2")->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_PARASUT);
$sheet->getStyle("B2")->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED_PARASUT);
$sheet->getStyle("A2:Q2")->applyFromArray(array("borders" => array("allborders" => array("style" => PHPExcel_Style_Border::BORDER_THIN, "color" => array("rgb" => "b8b8b8")))));
$sheet->getStyle("A2:Q2")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
$book->setActiveSheetIndex(0);
PHPExcel_IOFactory::createWriter($book, "Excel2007")->save($dir . "/out.xlsx");
$book->disconnectWorksheets();
$other = PHPExcel_IOFactory::load($library . "/templates/parasut_musteri_ve_tedarikciler.xlsx");
PHPExcel_IOFactory::createWriter($other, "Excel2007")->save($dir . "/other.xlsx");
$out[] = "other " . $other->getActiveSheet()->getHighestColumn();
// The product writes no .xls: the one read below is made with notices muted.
$quiet = true;
PHPExcel_IOFactory::createWriter(PHPExcel_IOFactory::load($dir . "/out.xlsx"), "Excel5")->save($dir . "/in.xls");
$quiet = false;
$zip = new ZipArchive();
$zip->open($dir . "/in.ods", ZipArchive::CREATE);
$zip->addFromString("mimetype", "application/vnd.oasis.opendocument.spreadsheet");
$zip->addFromString("meta.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><office:document-meta xmlns:office=\"urn:oasis:names:tc:opendocument:xmlns:office:1.0\" office:version=\"1.2\"><office:meta/></office:document-meta>");
$zip->addFromString("content.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?><office:document-content xmlns:office=\"urn:oasis:names:tc:opendocument:xmlns:office:1.0\" xmlns:table=\"urn:oasis:names:tc:opendocument:xmlns:table:1.0\" xmlns:text=\"urn:oasis:names:tc:opendocument:xmlns:text:1.0\" office:version=\"1.2\"><office:body><office:spreadsheet><table:table table:name=\"S\"><table:table-row><table:table-cell office:value-type=\"string\"><text:p>Ad</text:p></table:table-cell><table:table-cell office:value-type=\"float\" office:value=\"3.5\"><text:p>3,5</text:p></table:table-cell><table:table-cell office:value-type=\"date\" office:date-value=\"2026-11-17\"><text:p>17.11.2026</text:p></table:table-cell></table:table-row></table:table></office:spreadsheet></office:body></office:document-content>");
$zip->close();
foreach (array("Excel2007" => "out.xlsx", "Excel5" => "in.xls", "OOCalc" => "in.ods") as $type => $file) {
	foreach (array(true, false) as $data_only) {
		$reader = PHPExcel_IOFactory::createReader($type);
		$reader->setReadDataOnly($data_only);
		$read = $reader->load($dir . "/" . $file);
		$read_sheet = $read->getSheet(0);
		$last = PHPExcel_Cell::columnIndexFromString($read_sheet->getHighestDataColumn()) - 1;
		$row = ($type === "OOCalc") ? 1 : 2;
		$cells = array();
		for ($column = 0; $column <= min($last, 5); $column++) {
			$cell = $read_sheet->getCellByColumnAndRow($column, $row, false);
			$value = $cell ? $cell->getCalculatedValue() : "";
			if ($cell && PHPExcel_Shared_Date::isDateTime($cell) && is_numeric($value)) {
				$value = gmdate("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP((float) $value));
			}
			$cells[] = $value;
		}
		$out[] = $type . ($data_only ? " data " : " full ") . $read_sheet->getHighestDataRow() . ":" . implode("|", $cells);
		$read->disconnectWorksheets();
	}
}
foreach (glob($dir . "/*") as $file) {
	unlink($file);
}
rmdir($dir);
echo "OUT " . implode("\n", $out) . "\n";
';
	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
	unlink($script);

	pg_assert_same(implode("\n", array(
		'OUT other R',
		'Excel2007 data 982:Fatura ğüş|12.5|00123|25|46343|',
		'Excel2007 full 982:Fatura ğüş|12.5|1900-05-02|1900-01-25|2026-11-17|',
		'Excel5 data 3:Fatura ğüş|12.5|00123|25|46343|',
		'Excel5 full 982:Fatura ğüş|12.5|1900-05-02|1900-01-25|2026-11-17|',
		'OOCalc data 1:Ad|3.5|2026-11-17',
		'OOCalc full 1:Ad|3.5|2026-11-17',
	)), trim($output));
}

// PHP 8.0 reports a parameter with a default before a required one when the
// file is compiled; the default was never used, so the patch dropped it.
function test_phpexcel_has_no_default_before_a_required_parameter()
{
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . '/includes/phpexcel', FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		$tokens = array();

		foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				$tokens[] = $token;
			}
		}

		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_FUNCTION)) {
				continue;
			}

			while (($i < $count) && ($tokens[$i] !== '(')) {
				$i++;
			}

			$depth = 0;
			$defaulted = '';
			$has_default = false;
			$variable = '';
			$line = 0;

			for (; $i < $count; $i++) {
				$token = $tokens[$i];

				if (($token === '(') || ($token === '[')) {
					$depth++;
				} elseif (($token === ')') || ($token === ']')) {
					$depth--;
				}

				if (($depth === 1) && is_array($token) && ($token[0] === T_VARIABLE)) {
					$variable = $token[1];
					$line = $token[2];
				} elseif (($depth === 1) && ($token === '=')) {
					$has_default = true;
				}

				// A parameter ends at a comma of the list or at its ")".
				if ((($depth === 1) && ($token === ',')) || ($depth === 0)) {
					if ($variable !== '') {
						if ($has_default) {
							$defaulted = $variable;
						} elseif ($defaulted !== '') {
							$found[] = $file->getFilename() . ':' . $line . ' ' . $defaulted . ' before ' . $variable;
						}
					}

					$has_default = false;
					$variable = '';

					if ($depth === 0) {
						break;
					}
				}
			}
		}
	}

	pg_assert_same(array(), $found, 'defaults before a required parameter');
}

// strftime() is deprecated from PHP 8.1; PHPExcel's DATEVALUE() took the
// current year, month and day from it and now takes them from date().
function test_phpexcel_calls_no_strftime()
{
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . '/includes/phpexcel', FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
			if (is_array($token) && ($token[0] === T_STRING) && in_array(strtolower($token[1]), array('strftime', 'gmstrftime', 'utf8_encode', 'utf8_decode'), true)) {
				$found[] = $file->getFilename() . ':' . $token[2] . ' ' . $token[1];
			}
		}
	}

	pg_assert_same(array(), $found, 'deprecated date and encoding calls in PHPExcel');
}

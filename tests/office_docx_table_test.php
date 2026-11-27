<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Word tables put in after a paragraph and their rows and columns
 * put in or deleted (includes/fn/office_docx_table.php): the entries
 * checked, a new table's XML (the document's table style or thin borders,
 * the columns sharing the text's width), the refusals of merged, uneven
 * and locked tables, saves read back by the product's reader with the
 * rest of the file byte for byte, and each refusal leaving the file as it
 * was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_table_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// A document package written from its body; with a styles part holding
// "Table Grid" when asked.
function office_docx_table_test_file($body, $styles = false)
{
	$w = office_docx_table_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'officeDocument" Target="word/document.xml"/></Relationships>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. ($styles ? '<Relationship Id="rId1" Type="' . $rel . 'styles" Target="styles.xml"/>' : '') . '</Relationships>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:document xmlns:w="' . $w . '"><w:body>'
			. implode('', $body) . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:gutter="0"/></w:sectPr></w:body></w:document>',
	);

	if ($styles) {
		$parts['word/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:styles xmlns:w="' . $w . '">'
			. '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/></w:style>'
			. '<w:style w:type="table" w:styleId="TabloKlavuzu"><w:name w:val="Table Grid"/></w:style></w:styles>';
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_table_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return array($path, $parts);
}

function office_docx_table_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_docx_table_test_done($path)
{
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	@unlink($path);
}

function office_docx_table_test_cell($text)
{
	return '<w:tc><w:tcPr><w:tcW w:w="4000" w:type="dxa"/></w:tcPr><w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p></w:tc>';
}

// Paragraphs 0 to 9: a paragraph, a 2 by 2 table (1 to 4), a paragraph, a
// table whose first row is one merged cell (6 to 8), the last paragraph.
function office_docx_table_test_body()
{
	return array(
		'<w:p><w:r><w:t>Giriş</w:t></w:r></w:p>',
		'<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid><w:gridCol w:w="4000"/><w:gridCol w:w="4000"/></w:tblGrid>'
			. '<w:tr><w:trPr><w:tblHeader/></w:trPr>' . office_docx_table_test_cell('A1') . office_docx_table_test_cell('B1') . '</w:tr>'
			. '<w:tr>' . office_docx_table_test_cell('A2') . office_docx_table_test_cell('B2') . '</w:tr></w:tbl>',
		'<w:p><w:r><w:t>Ara</w:t></w:r></w:p>',
		'<w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="4000"/><w:gridCol w:w="4000"/></w:tblGrid>'
			. '<w:tr><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Birleşik</w:t></w:r></w:p></w:tc></w:tr>'
			. '<w:tr>' . office_docx_table_test_cell('C2') . office_docx_table_test_cell('D2') . '</w:tr></w:tbl>',
		'<w:p><w:r><w:t>Son</w:t></w:r></w:p>',
	);
}

// The texts of a part's paragraphs in reading order, and of each table's
// rows as "a|b".
function office_docx_table_test_rows($xml)
{
	$document = new DOMDocument();
	$document->loadXML($xml);
	$tables = array();

	foreach ($document->getElementsByTagNameNS(office_docx_table_test_w(), 'tbl') as $table) {
		$rows = array();

		foreach (pg_office_docx_table_children($table, 'tr') as $row) {
			$cells = array();

			foreach (pg_office_docx_table_cells($row) as $cell) {
				$cells[] = $cell->textContent;
			}

			$rows[] = implode('|', $cells);
		}

		$tables[] = $rows;
	}

	return $tables;
}

function test_office_docx_table_request()
{
	pg_assert_same('table_size', pg_office_docx_table_request(array('rows' => 0, 'columns' => 2)), 'no rows');
	pg_assert_same('table_size', pg_office_docx_table_request(array('rows' => 21, 'columns' => 2)), 'over 20 rows');
	pg_assert_same('table_size', pg_office_docx_table_request(array('rows' => 2, 'columns' => 11)), 'over 10 columns');
	pg_assert_same('none', pg_office_docx_table_request(array('rows' => 'iki', 'columns' => 2)), 'not a number');
	pg_assert_same('none', pg_office_docx_table_request(array('rows' => 1, 'columns' => 2, 'cells' => array(array(), array()))), 'more rows of cells than rows');
	pg_assert_same('none', pg_office_docx_table_request(array('rows' => 1, 'columns' => 1, 'cells' => array(array('a', 'b')))), 'more cells than columns');
	pg_assert_same('none', pg_office_docx_table_request(array('rows' => 1, 'columns' => 1, 'cells' => array(array(array(array('kind' => 'heading9')))))), 'a kind that is not offered');

	$table = pg_office_docx_table_request(array('rows' => '2', 'columns' => 2, 'cells' => array(array('Ad', array(array('text' => 'Soyad'), array('text' => 'İkinci'))))));

	pg_assert_same(2, $table['rows'], 'rows');
	pg_assert_same(2, $table['columns'], 'columns');
	pg_assert_same('Ad', $table['cells'][0][0][0]['text'], 'a plain text is one paragraph');
	pg_assert_same('İkinci', $table['cells'][0][1][1]['text'], 'a cell of two paragraphs');
	pg_assert_same(array(), $table['cells'][1][1], 'a cell not given stays empty');

	pg_assert_same('none', pg_office_docx_table_change_request(array('cell' => 1, 'table' => 'merge')), 'an action not offered');
	pg_assert_same(array('cell' => 3, 'action' => 'row_after', 'cells' => array(array(array('text' => 'x', 'format' => null, 'kind' => null, 'align' => null)))),
		pg_office_docx_table_change_request(array('cell' => '3', 'table' => 'row_after', 'cells' => array('x'))), 'a new row\'s cells');
	pg_assert_same(null, pg_office_docx_table_change_request(array('cell' => 3, 'table' => 'delete'))['cells'], 'a delete puts nothing in');
}

function test_office_docx_table_new()
{
	$w = office_docx_table_test_w();
	$document = new DOMDocument();
	$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body><w:p/></w:body></w:document>');
	$near = $document->getElementsByTagNameNS($w, 'p')->item(0);

	$plain = str_replace(' xmlns:w="' . $w . '"', '', $document->saveXML(pg_office_docx_table_new($near, 1, 3, '', 9026)));
	pg_assert_same('<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
		. '<w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/><w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
		. '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/><w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
		. '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/></w:tblBorders></w:tblPr>'
		. '<w:tblGrid><w:gridCol w:w="3008"/><w:gridCol w:w="3008"/><w:gridCol w:w="3008"/></w:tblGrid>'
		. '<w:tr><w:tc><w:tcPr><w:tcW w:w="3008" w:type="dxa"/></w:tcPr><w:p/></w:tc><w:tc><w:tcPr><w:tcW w:w="3008" w:type="dxa"/></w:tcPr><w:p/></w:tc>'
		. '<w:tc><w:tcPr><w:tcW w:w="3008" w:type="dxa"/></w:tcPr><w:p/></w:tc></w:tr></w:tbl>', $plain, 'thin borders, the columns sharing the width');

	$styled = $document->saveXML(pg_office_docx_table_new($near, 2, 1, 'TableGrid', 9026));
	pg_assert_contains('<w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/></w:tblPr>',
		$styled, 'the style and its look, no borders of its own');
	pg_assert_same(2, substr_count($styled, '<w:tr>'), 'two rows');

	$strict = new DOMDocument();
	$strict->loadXML('<w:document xmlns:w="http://purl.oclc.org/ooxml/wordprocessingml/main"><w:body><w:p/></w:body></w:document>');
	$xml = $strict->saveXML(pg_office_docx_table_new($strict->documentElement, 1, 1, '', 9026));
	pg_assert_contains('<w:start ', $xml, 'a strict document\'s start border');
	pg_assert_same(false, strpos($xml, '<w:left '), 'no left border in a strict document');
}

function test_office_docx_table_width_and_style()
{
	$w = office_docx_table_test_w();
	$document = new DOMDocument();
	$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body><w:p/>'
		. '<w:p><w:pPr><w:sectPr><w:pgSz w:w="12240"/><w:pgMar w:left="1800" w:right="1800"/></w:sectPr></w:pPr></w:p>'
		. '<w:tbl><w:tr><w:tc><w:tcPr><w:tcW w:w="5000" w:type="dxa"/></w:tcPr><w:p/></w:tc></w:tr></w:tbl>'
		. '<w:p/><w:sectPr><w:pgSz w:w="11906"/><w:pgMar w:left="1440" w:right="1440"/></w:sectPr></w:body></w:document>');
	$paragraphs = pg_office_docx_paragraphs($document);

	pg_assert_same(8640, pg_office_docx_table_text_width($paragraphs[0]), 'the width of the section the paragraph is in');
	pg_assert_same(4784, pg_office_docx_table_text_width($paragraphs[2]), 'a cell\'s width less its margins');
	pg_assert_same(9026, pg_office_docx_table_text_width($paragraphs[3]), 'the body\'s own section');

	if (!pg_office_ready()) {
		return;
	}

	list($path) = office_docx_table_test_file(array('<w:p/>'), true);
	$zip = new ZipArchive();
	$zip->open($path);
	pg_assert_same('TabloKlavuzu', pg_office_docx_table_style($zip, $document), '"Table Grid" by its name');
	$zip->close();
	office_docx_table_test_done($path);

	list($path) = office_docx_table_test_file(array('<w:p/>'));
	$zip = new ZipArchive();
	$zip->open($path);
	pg_assert_same('', pg_office_docx_table_style($zip, $document), 'no styles: borders of its own');
	$zip->close();
	office_docx_table_test_done($path);
}

function test_office_docx_table_refusal()
{
	$w = office_docx_table_test_w();
	$cases = array(
		'' => '<w:tr><w:tc><w:p/></w:tc><w:tc><w:p/></w:tc></w:tr>',
		'merged' => '<w:tr><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p/></w:tc></w:tr>',
		'merged ' => '<w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/></w:tcPr><w:p/></w:tc><w:tc><w:p/></w:tc></w:tr>',
		'merged  ' => '<w:tr><w:trPr><w:gridBefore w:val="1"/></w:trPr><w:tc><w:p/></w:tc></w:tr>',
		'merged   ' => '<w:tr><w:tc><w:p/></w:tc></w:tr>',
		'locked' => '<w:tr><w:trPr><w:ins w:id="1" w:author="a"/></w:trPr><w:tc><w:p/></w:tc><w:tc><w:p/></w:tc></w:tr>',
		'locked ' => '<w:sdt><w:sdtContent><w:tr><w:tc><w:p/></w:tc><w:tc><w:p/></w:tc></w:tr></w:sdtContent></w:sdt>',
		'locked  ' => '<w:tr><w:sdt><w:sdtContent><w:tc><w:p/></w:tc></w:sdtContent></w:sdt><w:tc><w:p/></w:tc></w:tr>',
	);

	foreach ($cases as $code => $rows) {
		$document = new DOMDocument();
		$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="1"/><w:gridCol w:w="1"/></w:tblGrid>'
			. '<w:bookmarkStart w:id="1" w:name="a"/>' . $rows . '</w:tbl><w:p/></w:body></w:document>');
		$table = $document->getElementsByTagNameNS($w, 'tbl')->item(0);

		pg_assert_same(trim($code), pg_office_docx_table_refusal($table), 'refusal: ' . $rows);
	}

	$document = new DOMDocument();
	$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body><w:tbl><w:tblGrid><w:gridCol w:w="1"/><w:gridCol w:w="1"/></w:tblGrid>' . $cases['merged'] . '</w:tbl></w:body></w:document>');
	pg_assert_same('', pg_office_docx_table_refusal($document->getElementsByTagNameNS($w, 'tbl')->item(0), true), 'a merged table may be deleted whole');
}

function test_office_docx_table_save_new()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	list($path, $parts) = office_docx_table_test_file(office_docx_table_test_body(), true);
	$read = pg_office_read($path, 'tablo.docx', true);
	$directory = sys_get_temp_dir();

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);

	$saved = pg_office_docx_save($path, array(
		array('after' => 0, 'table' => array('rows' => 2, 'columns' => 3, 'cells' => array(
			array('Ad', 'Soyad', array(array('text' => 'Yaş', 'format' => array(array('start' => 0, 'end' => 3, 'set' => array('bold' => true)))))),
			array('Ali', '', array(array('text' => 'bir'), array('text' => 'iki', 'align' => 'center'))),
		))),
		array('after' => 0, 'text' => 'Tablodan sonra'),
		array('after' => 9, 'table' => array('rows' => 1, 'columns' => 1)),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$xml = office_docx_table_test_part($path, 'word/document.xml');
	$tables = office_docx_table_test_rows($xml);

	pg_assert_same(array(array('Ad|Soyad|Yaş', 'Ali||biriki'), array('A1|B1', 'A2|B2'), array('Birleşik', 'C2|D2'), array('')), $tables, 'the new tables in their places with their cells');
	pg_assert_contains('<w:tblStyle w:val="TabloKlavuzu"/>', $xml, 'the document\'s table style');
	pg_assert_same(false, strpos($xml, 'tblBorders'), 'no borders of its own with a style');
	pg_assert_contains('<w:gridCol w:w="3008"/>', $xml, 'the columns share the text\'s width (11906 less two 1440 margins)');
	pg_assert_contains('<w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Yaş</w:t></w:r>', $xml, 'a cell\'s format');
	pg_assert_contains('<w:p><w:r><w:t>bir</w:t></w:r></w:p><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>iki</w:t></w:r></w:p>', $xml, 'a cell of two paragraphs, one centred');
	pg_assert_contains('</w:tbl><w:p><w:r><w:t>Tablodan sonra</w:t></w:r></w:p><w:tbl>', $xml, 'a paragraph asked after the same one follows the table and keeps the two tables apart');
	pg_assert_contains('<w:p><w:r><w:t>Son</w:t></w:r></w:p><w:tbl>', $xml, 'a table after the last paragraph');
	pg_assert_contains('</w:tbl><w:p/><w:sectPr>', $xml, 'a paragraph closes a table that would end the body');

	// The rest byte for byte.
	foreach (office_docx_table_test_body() as $index => $block) {
		pg_assert_contains($block, $xml, 'block ' . $index . ' as it was');
	}

	foreach (array('word/styles.xml', 'word/_rels/document.xml.rels', '[Content_Types].xml', '_rels/.rels') as $name) {
		pg_assert_same($parts[$name], office_docx_table_test_part($path, $name), $name . ' untouched');
	}

	// Read back by the product's reader: the new cells are paragraphs to type in.
	$back = pg_office_read($path, 'tablo.docx', true);
	$texts = json_decode(json_encode($back['edit']['paragraphs']), true);

	pg_assert_true($back['ok'], 'read back');
	pg_assert_same(4, substr_count($back['html'], '<table class="office-table">'), 'four tables drawn');
	pg_assert_same(array('Giriş', 'Ad', 'Soyad', 'Yaş', 'Ali', '', 'bir', 'iki', 'Tablodan sonra'), array_slice(array_values($texts), 0, 9), 'the new cells read back in order');

	office_docx_table_test_done($path);
}

function test_office_docx_table_save_rows_columns()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	list($path, $parts) = office_docx_table_test_file(office_docx_table_test_body());
	$read = pg_office_read($path, 'tablo.docx', true);
	$directory = sys_get_temp_dir();

	$saved = pg_office_docx_save($path, array(
		array('cell' => 3, 'table' => 'row_after', 'cells' => array('C1', 'C2')),
		array('cell' => 3, 'table' => 'row_after', 'cells' => array(array(array('text' => 'D1', 'kind' => 'bullet')))),
		array('cell' => 1, 'table' => 'row_before', 'cells' => array('Üst')),
		array('cell' => 1, 'table' => 'column_after', 'cells' => array('y0', 'y1')),
		array('cell' => 2, 'table' => 'column_delete'),
		array('p' => 4, 'text' => 'B2 silinecek'),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$xml = office_docx_table_test_part($path, 'word/document.xml');
	$tables = office_docx_table_test_rows($xml);

	pg_assert_same(array('Üst|y0', 'A1|y1', 'A2|', 'C1|', 'D1|'), $tables[0], 'rows in the order asked, the new column, the deleted column gone');
	pg_assert_contains('<w:tblGrid><w:gridCol w:w="2000"/><w:gridCol w:w="6000"/></w:tblGrid>', $xml, 'the new column halves its source; the deleted one\'s width goes to the column before');
	pg_assert_same(1, substr_count($xml, '<w:tblHeader/>'), 'a row put in beside the header row is no header');
	pg_assert_contains('<w:numPr>', $xml, 'a new cell\'s paragraph takes its kind');
	pg_assert_same($tables[1], array('Birleşik', 'C2|D2'), 'the other table untouched');

	foreach (array(0, 2, 3, 4) as $index) {
		pg_assert_contains(office_docx_table_test_body()[$index], $xml, 'block ' . $index . ' as it was');
	}

	$back = pg_office_read($path, 'tablo.docx', true);
	pg_assert_true($back['ok'], 'read back');
	pg_assert_contains('<td><p data-p="1">Üst</p></td>', $back['html'], 'the new row drawn first');

	office_docx_table_test_done($path);

	// Deleting rows, a table and the paragraphs that went with them.
	list($path) = office_docx_table_test_file(office_docx_table_test_body());
	$read = pg_office_read($path, 'tablo.docx', true);
	$saved = pg_office_docx_save($path, array(
		array('cell' => 1, 'table' => 'row_delete'),
		array('cell' => 3, 'table' => 'row_delete'),
		array('p' => 1, 'delete' => true),
		array('p' => 1, 'kind' => 'heading1'),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$xml = office_docx_table_test_part($path, 'word/document.xml');
	pg_assert_same(array(array('Birleşik', 'C2|D2')), office_docx_table_test_rows($xml), 'a table with every row deleted goes whole');
	pg_assert_contains('<w:p><w:r><w:t>Giriş</w:t></w:r></w:p><w:p><w:r><w:t>Ara</w:t></w:r></w:p>', $xml, 'the paragraphs around it stay');

	office_docx_table_test_done($path);
}

function test_office_docx_table_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$body = office_docx_table_test_body();
	$body[] = '<w:tbl><w:tblGrid><w:gridCol w:w="1"/><w:gridCol w:w="1"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:drawing/></w:r></w:p></w:tc><w:tc><w:p><w:bookmarkStart w:id="5" w:name="b"/></w:p></w:tc></w:tr>'
		. '<w:tr><w:tc><w:p/></w:tc><w:tc><w:p/></w:tc></w:tr></w:tbl>';
	$body[] = '<w:p><w:bookmarkEnd w:id="5"/></w:p>';
	$body[] = '<w:sdt><w:sdtContent><w:tbl><w:tblGrid><w:gridCol w:w="1"/></w:tblGrid><w:tr><w:tc><w:p/></w:tc></w:tr></w:tbl></w:sdtContent></w:sdt><w:p/>';

	list($path) = office_docx_table_test_file($body);
	$read = pg_office_read($path, 'tablo.docx', true);
	$before = file_get_contents($path);
	$directory = sys_get_temp_dir();

	$cases = array(
		array(array(array('cell' => 6, 'table' => 'row_after')), 'table_merged', '7', 'merged'),
		array(array(array('cell' => 7, 'table' => 'column_delete')), 'table_merged', '8', 'merged'),
		array(array(array('cell' => 0, 'table' => 'row_after')), 'table_missing', '1', 'not in a table'),
		array(array(array('cell' => 10, 'table' => 'row_delete')), 'table_delete', '11', 'picture'),
		array(array(array('cell' => 11, 'table' => 'column_delete')), 'table_delete', '12', 'bookmark'),
		array(array(array('cell' => 15, 'table' => 'row_after')), 'table_locked', '16', 'content control'),
		array(array(array('cell' => 40, 'table' => 'row_after')), 'missing', '41', 'not in the file'),
		array(array(array('after' => 0, 'table' => array('rows' => 30, 'columns' => 2))), 'table_size', '', ''),
		array(array(array('cell' => 1, 'table' => 'row_after', 'cells' => array('a', 'b', 'c'))), 'none', '', ''),
		array(array(array('after' => 15, 'table' => array('rows' => 1, 'columns' => 1))), 'add_locked', '16', 'content control'),
		array(array(array('p' => 0, 'text' => 'yeni'), array('cell' => 10, 'table' => 'delete')), 'table_delete', '11', 'picture'),
	);

	foreach ($cases as $case) {
		$saved = pg_office_docx_save($path, $case[0], $read['version'], $directory);

		pg_assert_same(false, $saved['ok'], 'refused: ' . $case[1]);
		pg_assert_same($case[1], $saved['code'], 'the reason: ' . $case[1] . ' ' . json_encode($case[0]));

		if ($case[2] !== '') {
			pg_assert_contains(' ' . $case[2] . ' ', $saved['error'] . ' ', 'the message names the paragraph');
			pg_assert_contains($case[3], $saved['error'], 'the message says why');
		}

		pg_assert_same($before, file_get_contents($path), 'the file as it was: ' . $case[1]);
	}

	// The whole table with its bookmark's end goes; a merged table may go whole.
	$saved = pg_office_docx_save($path, array(array('cell' => 13, 'table' => 'row_delete'), array('cell' => 6, 'table' => 'delete')), $read['version'], $directory);
	pg_assert_true($saved['ok'], 'an empty row and a merged table deleted: ' . $saved['error']);
	pg_assert_same(false, strpos(office_docx_table_test_part($path, 'word/document.xml'), 'Birleşik'), 'the merged table gone');

	office_docx_table_test_done($path);
}

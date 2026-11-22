<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The empty workbook and the empty document that "New spreadsheet" and
 * "New document" make, in the File Manager's New menu and a channel's attach
 * menu, written without a library and then opened for editing.
 *
 * A workbook goes through the shared writer (pg_xlsx_write_book()). The
 * file window changes only cells and rows a sheet already holds, so the
 * sheet is a grid of cells that are there and hold nothing: typing fills
 * them and the head menus add rows and columns. A document is the few parts
 * a word processor needs - the main part with one empty paragraph and an A4
 * page, the styles and the settings - so that its paragraph can be typed
 * into at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The empty grid of a new workbook: rows 1 to 30, columns A to J.
define('PG_OFFICE_NEW_ROWS', 30);
define('PG_OFFICE_NEW_COLUMNS', 10);

/**
 * The name a new file is made under, in the person's language, before the
 * folder or channel makes it unique.
 *
 * @param string $kind xlsx or docx
 * @return string '' for any other kind
 */
function pg_office_new_name($kind)
{
    switch ($kind) {
        case 'xlsx':
            return lang('New spreadsheet') . '.xlsx';

        case 'docx':
            return lang('New document') . '.docx';
    }

    return '';
}

/**
 * Writes a new empty workbook or document.
 *
 * @param string $kind xlsx or docx
 * @param string $path where to write it; anything there is replaced
 * @return bool
 */
function pg_office_new_write($kind, $path)
{
    if ($kind === 'xlsx') {
        $row = array_fill(0, PG_OFFICE_NEW_COLUMNS, array('t' => 'blank'));

        return pg_xlsx_write_book(array(array('name' => lang('Sheet1'))), function () use ($row) {
            return array_fill(1, PG_OFFICE_NEW_ROWS, $row);
        }, $path);
    }

    if ($kind === 'docx') {
        return pg_docx_write_blank($path);
    }

    return false;
}

/**
 * The bytes of a new empty workbook or document, for a door that stores
 * them itself (a channel's message).
 *
 * @param string $kind xlsx or docx
 * @return string '' when the kind is unknown or the file could not be written
 */
function pg_office_new_bytes($kind)
{
    if (pg_office_new_name($kind) === '') {
        return '';
    }

    $path = tempnam(sys_get_temp_dir(), 'pg_office_new_');

    if ($path === false) {
        return '';
    }

    // The writer makes the archive itself: an empty file in its place is no zip.
    @unlink($path);
    $bytes = pg_office_new_write($kind, $path) ? (string) @file_get_contents($path) : '';
    @unlink($path);

    return $bytes;
}

/**
 * An empty Word document: one empty paragraph on an A4 page with 2.5 cm
 * margins, the Normal style in an 11 point Calibri, and settings that ask
 * a word processor for its current behaviour (compatibility mode 15).
 *
 * @param string $path where to write the .docx; anything there is replaced
 * @return bool
 */
function pg_docx_write_blank($path)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $ns = 'http://schemas.openxmlformats.org/';
    $w = 'xmlns:w="' . $ns . 'wordprocessingml/2006/main"';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $relationship = $ns . 'officeDocument/2006/relationships/';
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $zip->addFromString('[Content_Types].xml', $head
        . '<Types xmlns="' . $ns . 'package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', $head
        . '<Relationships xmlns="' . $ns . 'package/2006/relationships">'
        . '<Relationship Id="rId1" Type="' . $relationship . 'officeDocument" Target="word/document.xml"/>'
        . '</Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', $head
        . '<Relationships xmlns="' . $ns . 'package/2006/relationships">'
        . '<Relationship Id="rId1" Type="' . $relationship . 'styles" Target="styles.xml"/>'
        . '<Relationship Id="rId2" Type="' . $relationship . 'settings" Target="settings.xml"/>'
        . '</Relationships>');
    $zip->addFromString('word/document.xml', $head
        . '<w:document ' . $w . '><w:body><w:p/>'
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>');
    $zip->addFromString('word/styles.xml', $head
        . '<w:styles ' . $w . '><w:docDefaults>'
        . '<w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
        . '</w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
        . '</w:styles>');
    $zip->addFromString('word/settings.xml', $head
        . '<w:settings ' . $w . '><w:defaultTabStop w:val="708"/>'
        . '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>'
        . '</w:settings>');

    if (!$zip->close()) {
        @unlink($path);

        return false;
    }

    return true;
}

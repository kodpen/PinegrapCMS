<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Exporting products, in one place.
//
// The CSV this writes is the CSV view_products.php has always written -- same
// columns, same order, same formatting -- because it is now the only thing
// that writes it: that screen calls in here rather than carrying its own copy.
// The spreadsheet writer is the same table handed to a different writer, so a
// workbook and a CSV of the same products can never disagree about what a
// product is.
//
// Two things about the column list are worth knowing before changing it.
//
// The heading is not always the database column. Nine of them differ --
// required_product_id for required_product, recurring_days_before_start for
// start, and so on -- because the heading is the name the importer reads and
// the column is the name the row is stored under. Renaming a heading breaks
// every file anyone has ever exported.
//
// The four custom product fields appear only when the site has given them a
// label, and the heading they get is that label rather than a fixed name.

// One entry per column, in the order they are written.
function pg_products_export_columns()
{
    $columns = array(
        array('heading' => 'name', 'column' => 'name', 'kind' => 'text'),
        array('heading' => 'enabled', 'column' => 'enabled', 'kind' => 'raw'),
        array('heading' => 'short_description', 'column' => 'short_description', 'kind' => 'text'),
        array('heading' => 'full_description', 'column' => 'full_description', 'kind' => 'text'),
        array('heading' => 'details', 'column' => 'details', 'kind' => 'text'),
        array('heading' => 'code', 'column' => 'code', 'kind' => 'text'),
        array('heading' => 'keywords', 'column' => 'keywords', 'kind' => 'text'),
        array('heading' => 'image_name', 'column' => 'image_name', 'kind' => 'text'),
        array('heading' => 'price', 'column' => 'price', 'kind' => 'money'),
        array('heading' => 'taxable', 'column' => 'taxable', 'kind' => 'raw'),
        array('heading' => 'tax_rate', 'column' => 'tax_rate', 'kind' => 'raw'),
        array('heading' => 'selection_type', 'column' => 'selection_type', 'kind' => 'raw'),
        array('heading' => 'default_quantity', 'column' => 'default_quantity', 'kind' => 'raw'),
        array('heading' => 'address_name', 'column' => 'address_name', 'kind' => 'text'),
        array('heading' => 'title', 'column' => 'title', 'kind' => 'text'),
        array('heading' => 'meta_description', 'column' => 'meta_description', 'kind' => 'text'),
        array('heading' => 'meta_keywords', 'column' => 'meta_keywords', 'kind' => 'text'),
        array('heading' => 'inventory', 'column' => 'inventory', 'kind' => 'raw'),
        array('heading' => 'inventory_quantity', 'column' => 'inventory_quantity', 'kind' => 'raw'),
        array('heading' => 'backorder', 'column' => 'backorder', 'kind' => 'raw'),
        array('heading' => 'out_of_stock_message', 'column' => 'out_of_stock_message', 'kind' => 'text'),
        array('heading' => 'required_product_id', 'column' => 'required_product', 'kind' => 'raw'),
        array('heading' => 'form', 'column' => 'form', 'kind' => 'raw'),
        array('heading' => 'form_name', 'column' => 'form_name', 'kind' => 'text'),
        array('heading' => 'form_label_column_width', 'column' => 'form_label_column_width', 'kind' => 'text'),
        array('heading' => 'form_quantity_type', 'column' => 'form_quantity_type', 'kind' => 'raw'),
        array('heading' => 'shippable', 'column' => 'shippable', 'kind' => 'raw'),
        array('heading' => 'weight', 'column' => 'weight', 'kind' => 'raw'),
        array('heading' => 'primary_weight_points', 'column' => 'primary_weight_points', 'kind' => 'raw'),
        array('heading' => 'secondary_weight_points', 'column' => 'secondary_weight_points', 'kind' => 'raw'),
        array('heading' => 'length', 'column' => 'length', 'kind' => 'raw'),
        array('heading' => 'width', 'column' => 'width', 'kind' => 'raw'),
        array('heading' => 'height', 'column' => 'height', 'kind' => 'raw'),
        array('heading' => 'container_required', 'column' => 'container_required', 'kind' => 'raw'),
        array('heading' => 'preparation_time', 'column' => 'preparation_time', 'kind' => 'raw'),
        array('heading' => 'free_shipping', 'column' => 'free_shipping', 'kind' => 'raw'),
        array('heading' => 'extra_shipping_cost', 'column' => 'extra_shipping_cost', 'kind' => 'money'),
        array('heading' => 'commissionable', 'column' => 'commissionable', 'kind' => 'raw'),
        array('heading' => 'commission_rate_limit', 'column' => 'commission_rate_limit', 'kind' => 'raw'),
        array('heading' => 'order_receipt_message', 'column' => 'order_receipt_message', 'kind' => 'text'),
        array('heading' => 'order_receipt_bcc_email_address', 'column' => 'order_receipt_bcc_email_address', 'kind' => 'text'),
        array('heading' => 'email_page_id', 'column' => 'email_page', 'kind' => 'raw'),
        array('heading' => 'email_bcc_email_address', 'column' => 'email_bcc', 'kind' => 'text'),
        array('heading' => 'recurring', 'column' => 'recurring', 'kind' => 'raw'),
        array('heading' => 'recurring_schedule_editable_by_customer', 'column' => 'recurring_schedule_editable_by_customer', 'kind' => 'raw'),
        array('heading' => 'recurring_days_before_start', 'column' => 'start', 'kind' => 'raw'),
        array('heading' => 'recurring_number_of_payments', 'column' => 'number_of_payments', 'kind' => 'raw'),
        array('heading' => 'recurring_payment_period', 'column' => 'payment_period', 'kind' => 'raw'),
        array('heading' => 'recurring_profile_disabled_perform_actions', 'column' => 'recurring_profile_disabled_perform_actions', 'kind' => 'raw'),
        array('heading' => 'recurring_profile_disabled_expire_membership', 'column' => 'recurring_profile_disabled_expire_membership', 'kind' => 'raw'),
        array('heading' => 'recurring_profile_disabled_revoke_private_access', 'column' => 'recurring_profile_disabled_revoke_private_access', 'kind' => 'raw'),
        array('heading' => 'recurring_profile_disabled_email', 'column' => 'recurring_profile_disabled_email', 'kind' => 'raw'),
        array('heading' => 'recurring_profile_disabled_email_subject', 'column' => 'recurring_profile_disabled_email_subject', 'kind' => 'text'),
        array('heading' => 'recurring_profile_disabled_email_page_id', 'column' => 'recurring_profile_disabled_email_page_id', 'kind' => 'raw'),
        array('heading' => 'recurring_sage_group_id', 'column' => 'sage_group_id', 'kind' => 'raw'),
        array('heading' => 'contact_group_id', 'column' => 'contact_group_id', 'kind' => 'raw'),
        array('heading' => 'membership_renewal', 'column' => 'membership_renewal', 'kind' => 'raw'),
        array('heading' => 'grant_private_access', 'column' => 'grant_private_access', 'kind' => 'raw'),
        array('heading' => 'private_folder_id', 'column' => 'private_folder', 'kind' => 'raw'),
        array('heading' => 'private_days', 'column' => 'private_days', 'kind' => 'raw'),
        array('heading' => 'start_page_id', 'column' => 'send_to_page', 'kind' => 'raw'),
        array('heading' => 'reward_points', 'column' => 'reward_points', 'kind' => 'raw'),
        array('heading' => 'gift_card', 'column' => 'gift_card', 'kind' => 'raw'),
        array('heading' => 'gift_card_email_subject', 'column' => 'gift_card_email_subject', 'kind' => 'text'),
        array('heading' => 'gift_card_email_format', 'column' => 'gift_card_email_format', 'kind' => 'raw'),
        array('heading' => 'gift_card_email_body', 'column' => 'gift_card_email_body', 'kind' => 'text'),
        array('heading' => 'gift_card_email_page_id', 'column' => 'gift_card_email_page_id', 'kind' => 'raw'),
        array('heading' => 'submit_form', 'column' => 'submit_form', 'kind' => 'raw'),
        array('heading' => 'submit_form_custom_form_page_id', 'column' => 'submit_form_custom_form_page_id', 'kind' => 'raw'),
        array('heading' => 'submit_form_create', 'column' => 'submit_form_create', 'kind' => 'raw'),
        array('heading' => 'submit_form_update', 'column' => 'submit_form_update', 'kind' => 'raw'),
        array('heading' => 'submit_form_update_where_field', 'column' => 'submit_form_update_where_field', 'kind' => 'raw'),
        array('heading' => 'submit_form_update_where_value', 'column' => 'submit_form_update_where_value', 'kind' => 'raw'),
        array('heading' => 'submit_form_quantity_type', 'column' => 'submit_form_quantity_type', 'kind' => 'raw'),
        array('heading' => 'add_comment', 'column' => 'add_comment', 'kind' => 'raw'),
        array('heading' => 'add_comment_page_id', 'column' => 'add_comment_page_id', 'kind' => 'raw'),
        array('heading' => 'add_comment_message', 'column' => 'add_comment_message', 'kind' => 'text'),
        array('heading' => 'add_comment_name', 'column' => 'add_comment_name', 'kind' => 'text'),
        array('heading' => 'add_comment_only_for_submit_form_update', 'column' => 'add_comment_only_for_submit_form_update', 'kind' => 'raw'),
        array('heading' => '', 'column' => 'custom_field_1', 'kind' => 'custom', 'slot' => 1),
        array('heading' => '', 'column' => 'custom_field_2', 'kind' => 'custom', 'slot' => 2),
        array('heading' => '', 'column' => 'custom_field_3', 'kind' => 'custom', 'slot' => 3),
        array('heading' => '', 'column' => 'custom_field_4', 'kind' => 'custom', 'slot' => 4),
        array('heading' => 'notes', 'column' => 'notes', 'kind' => 'text'),
        array('heading' => 'google_product_category', 'column' => 'google_product_category', 'kind' => 'text'),
        array('heading' => 'gtin', 'column' => 'gtin', 'kind' => 'text'),
        array('heading' => 'brand', 'column' => 'brand', 'kind' => 'text'),
        array('heading' => 'mpn', 'column' => 'mpn', 'kind' => 'text'),
    );

    $labels = array(
        1 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL,
        2 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL,
        3 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL,
        4 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL);

    $out = array();

    foreach ($columns as $column) {

        // A custom field with no label is switched off for this site, and has
        // never been written into the file.
        if ($column['kind'] == 'custom') {

            if ($labels[$column['slot']] == '') {
                continue;
            }

            $column['heading'] = $labels[$column['slot']];
            $column['kind'] = 'text';
        }

        $out[] = $column;
    }

    return $out;
}

// The value of one column for one product, as it goes into the file.
function pg_products_export_value($product, $column)
{
    $value = isset($product[$column['column']]) ? $product[$column['column']] : '';

    // Prices are stored in the smallest unit and written as a decimal.
    if ($column['kind'] == 'money') {
        return sprintf('%01.2lf', $value / 100);
    }

    return (string) $value;
}

// The whole table: one heading row and one row per product.
//
// $where is a WHERE clause including the word WHERE, or an empty string, and
// $order is what follows ORDER BY. Both arrive already built by the caller,
// which is how the products screen keeps exporting exactly what its filters
// are showing.
function pg_products_export_table($where = '', $order = 'name ASC')
{
    $columns = pg_products_export_columns();

    // The submit form fields are columns too, and which ones exist depends on
    // what the site has defined -- so they are discovered rather than listed.
    $submit_form_fields = db_items(
        "SELECT
            product_submit_form_fields.product_id,
            product_submit_form_fields.action,
            product_submit_form_fields.value,
            form_fields.name
        FROM product_submit_form_fields
        LEFT JOIN form_fields ON product_submit_form_fields.form_field_id = form_fields.id
        ORDER BY
            product_submit_form_fields.product_id,
            product_submit_form_fields.action,
            product_submit_form_fields.id");

    $create_fields = array();
    $update_fields = array();
    $by_product = array();

    foreach ($submit_form_fields as $field) {

        switch ($field['action']) {

            case 'create':
                if (in_array($field['name'], $create_fields) == false) {
                    $create_fields[] = $field['name'];
                }
                break;

            case 'update':
                if (in_array($field['name'], $update_fields) == false) {
                    $update_fields[] = $field['name'];
                }
                break;
        }

        $by_product[$field['product_id']][$field['action']][$field['name']] = $field['value'];
    }

    $headings = array();

    foreach ($columns as $column) {
        $headings[] = $column['heading'];
    }

    foreach ($create_fields as $field) {
        $headings[] = 'sfc_' . $field;
    }

    foreach ($update_fields as $field) {
        $headings[] = 'sfu_' . $field;
    }

    $select = array('id');

    foreach ($columns as $column) {
        if (in_array($column['column'], $select) == false) {
            $select[] = $column['column'];
        }
    }

    $products = db_items(
        'SELECT ' . implode(', ', $select) . '
        FROM products
        ' . $where . '
        ORDER BY ' . $order);

    $rows = array();

    foreach ($products as $product) {

        $row = array();

        foreach ($columns as $column) {
            $row[] = pg_products_export_value($product, $column);
        }

        foreach ($create_fields as $field) {
            $row[] = isset($by_product[$product['id']]['create'][$field]) ? $by_product[$product['id']]['create'][$field] : '';
        }

        foreach ($update_fields as $field) {
            $row[] = isset($by_product[$product['id']]['update'][$field]) ? $by_product[$product['id']]['update'][$field] : '';
        }

        $rows[] = $row;
    }

    return array('headings' => $headings, 'rows' => $rows);
}

// The header that names the downloaded file.
//
// Given twice on purpose. A product group called "Ornek Koleksiyon" is fine in
// the plain form; one called "Ornek Koleksiyon" with the Turkish letters in it
// is not -- the plain form is bytes, and a browser reading those bytes as
// Latin-1 saves the file under a name nobody typed. The starred form carries
// the real UTF-8 name and every current browser prefers it; the plain one is
// kept, stripped down to what is safe, for anything that does not.
function pg_products_export_disposition($file_name)
{
    // Turkish letters are the ones that actually turn up here, and each has an
    // unaccented twin that reads correctly. Everything else outside the safe
    // set becomes an underscore, and runs of them collapse: a name of nothing
    // but accents would otherwise come out as a row of underscores.
    $plain = str_replace(
        array('ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'),
        array('c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'),
        (string) $file_name);

    $plain = preg_replace('/[^A-Za-z0-9._-]+/', '_', $plain);
    $plain = trim($plain, '_');

    if ($plain == '') {
        $plain = 'products';
    }

    return 'attachment; filename="' . $plain . '"; filename*=UTF-8\'\'' . rawurlencode((string) $file_name);
}

// Straight to the browser, quoted the way this file has always been quoted.
function pg_products_export_csv($table, $file_name = 'products.csv')
{
    header('Content-type: text/csv; charset=utf-8');
    header('Content-Disposition: ' . pg_products_export_disposition($file_name));

    $out = array();

    foreach ($table['headings'] as $heading) {
        $out[] = '"' . escape_csv($heading) . '"';
    }

    echo implode(',', $out) . "\n";

    foreach ($table['rows'] as $row) {

        $out = array();

        foreach ($row as $value) {
            $out[] = '"' . escape_csv($value) . '"';
        }

        echo implode(',', $out) . "\n";
    }
}

// The same table as a workbook.
//
// Written straight into the file rather than through PHPExcel. PHPExcel builds
// an object per cell, and this catalog is thirty thousand products across
// eighty-seven columns -- two and a half million of them. It ran out of memory
// and returned nothing at all, which is the worst way for an export to fail:
// the browser has already been told a file is coming. A worksheet is XML in a
// zip, and XML written a row at a time costs one row of memory.
//
// The import reads a .xlsx the same way, and keeps PHPExcel for the older
// Excel format and OpenDocument -- see import_products_f.php. Reading a format
// somebody else wrote is what a library is worth carrying for; writing one
// this file controls end to end is not.

// A column number as a spreadsheet names it: 0 is A, 26 is AA.
function pg_products_export_column_letter($index)
{
    $letters = '';

    for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
        $letters = chr(65 + (($number - 1) % 26)) . $letters;
    }

    return $letters;
}

// One value as XML text.
//
// The control characters go first. A product description that has picked up a
// stray 0x0B somewhere in twenty years of edits is legal in a CSV and illegal
// in XML, and a single one of them makes the whole workbook refuse to open.
function pg_products_export_xml($value)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);

    return str_replace(
        array('&', '<', '>'),
        array('&amp;', '&lt;', '&gt;'),
        $value);
}

// Every cell is written as text on purpose: a product code like 001-1002 is a
// date to a spreadsheet, and a price written as a number picks up whatever
// decimal separator the reader's machine uses -- either one comes back through
// the importer as something else.
//
// Text is carried inline rather than through the shared strings table. The
// table is the smaller file when values repeat, but it has to be held whole
// until the last row is known, which is the memory this writer exists to
// avoid; the zip squeezes the repetition back out anyway.
function pg_products_export_sheet($table, $path)
{
    $handle = fopen($path, 'w');

    if ($handle === false) {
        return false;
    }

    $letters = array();
    $width = count($table['headings']);

    for ($index = 0; $index < $width; $index++) {
        $letters[$index] = pg_products_export_column_letter($index);
    }

    // Excel works the used range out for itself, but a reader that trusts the
    // declared one reports an empty sheet without it.
    $last_cell = ($width > 0)
        ? ($letters[$width - 1] . (count($table['rows']) + 1))
        : 'A1';

    fwrite($handle,
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . $last_cell . '"/>' .
        '<sheetViews><sheetView workbookViewId="0">' .
        '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' .
        '</sheetView></sheetViews><sheetData>');

    // The heading row carries style 1, which is the bold font in styles.xml.
    $line = '<row r="1">';

    foreach ($table['headings'] as $index => $heading) {
        $line .= '<c r="' . $letters[$index] . '1" s="1" t="inlineStr"><is><t xml:space="preserve">' .
            pg_products_export_xml($heading) . '</t></is></c>';
    }

    fwrite($handle, $line . '</row>');

    $row_number = 1;

    foreach ($table['rows'] as $row) {

        $row_number++;
        $line = '<row r="' . $row_number . '">';

        foreach ($row as $index => $value) {

            // An empty cell is left out rather than written empty: on a
            // catalog this size the omission is a fifth of the file.
            if (($value === '') || ($value === null)) {
                continue;
            }

            if (!isset($letters[$index])) {
                $letters[$index] = pg_products_export_column_letter($index);
            }

            $line .= '<c r="' . $letters[$index] . $row_number . '" t="inlineStr"><is><t xml:space="preserve">' .
                pg_products_export_xml($value) . '</t></is></c>';
        }

        fwrite($handle, $line . '</row>');
    }

    fwrite($handle, '</sheetData></worksheet>');
    fclose($handle);

    return true;
}

// The five small parts of the package. Only the worksheet is big enough to be
// worth streaming; these are a few hundred bytes each.
function pg_products_export_parts($sheet_title)
{
    $ns = 'http://schemas.openxmlformats.org/';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    return array(

        '[Content_Types].xml' => $head .
            '<Types xmlns="' . $ns . 'package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>',

        '_rels/.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>',

        'xl/workbook.xml' => $head .
            '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . pg_products_export_xml($sheet_title) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>',

        'xl/_rels/workbook.xml.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>',

        // Two cell formats: 0 is the plain one every value uses, 1 is the bold
        // heading row. The fills and borders are not optional decoration --
        // a styleSheet without them is rejected by Excel.
        'xl/styles.xml' => $head .
            '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
            '<fonts count="2">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="11"/><name val="Calibri"/></font>' .
            '</fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
            '</cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>');
}

function pg_products_export_xlsx($table, $file_name = 'products.xlsx')
{
    // Zipping is the one part not written here. ZipArchive is what PHPExcel
    // itself needs to write a workbook, so an installation that has ever
    // exported one has it; an installation that does not is told which file to
    // ask for rather than handed a broken one.
    if (!class_exists('ZipArchive')) {
        output_error(lang('The file could not be read.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $sheet_path = tempnam(sys_get_temp_dir(), 'pg_sheet_');
    $book_path = tempnam(sys_get_temp_dir(), 'pg_book_');

    if (($sheet_path === false) || ($book_path === false)) {
        output_error(lang('The file could not be read.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    if (pg_products_export_sheet($table, $sheet_path) == false) {
        unlink($sheet_path);
        unlink($book_path);
        output_error(lang('The file could not be read.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $zip = new ZipArchive();

    if ($zip->open($book_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        unlink($sheet_path);
        unlink($book_path);
        output_error(lang('The file could not be read.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    foreach (pg_products_export_parts('Products') as $part_name => $part) {
        $zip->addFromString($part_name, $part);
    }

    // From disk rather than from a string: the worksheet is the whole point of
    // writing it to a file first, and addFile() reads it when close() runs.
    $zip->addFile($sheet_path, 'xl/worksheets/sheet1.xml');
    $zip->close();

    unlink($sheet_path);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: ' . pg_products_export_disposition($file_name));
    header('Content-Length: ' . filesize($book_path));
    header('Cache-Control: max-age=0');

    readfile($book_path);
    unlink($book_path);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the sources of bulk product drafts a person makes without an
 * assistant: several lines under /create product (/oluştur ürün), or a CSV
 * or spreadsheet file posted in a channel, answered by /create product or
 * sent with it. Either is laid out as a table,
 * the table as the rows of one bulk create (bulk.php), and the rows wait as
 * a card under a message of the person's own, which they apply like any
 * bulk change: checked again and written a slice at a time.
 *
 * A spreadsheet is read as the product import reads it
 * (pg_products_import_to_csv()), a price as the import reads one
 * (pg_products_import_price_cents()), so a file means the same here as in
 * import_products.php; more than one card's rows go there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The longest list of lines read, in characters.
 */
define('WS_BULK_TEXT_MAX', 200000);

/**
 * Column names a header may use, folded (ws_bulk_header_key()), and the
 * product field each fills: the import file's own names, and the English
 * and Turkish words a sheet is usually headed with.
 *
 * @return array folded name => field
 */
function ws_bulk_header_fields()
{
    return array(
        'name'              => 'name',
        'sku'               => 'name',
        'code'              => 'name',
        'productid'         => 'name',
        'productcode'       => 'name',
        'stokkodu'          => 'name',
        'urunkodu'          => 'name',
        'shortdescription'  => 'short_description',
        'productname'       => 'short_description',
        'urunadi'           => 'short_description',
        'urun'              => 'short_description',
        'ad'                => 'short_description',
        'adi'               => 'short_description',
        'fulldescription'   => 'full_description',
        'description'       => 'full_description',
        'aciklama'          => 'full_description',
        'price'             => 'price',
        'fiyat'             => 'price',
        'satisfiyati'       => 'price',
        'quantity'          => 'quantity',
        'inventoryquantity' => 'quantity',
        'stock'             => 'quantity',
        'stok'              => 'quantity',
        'adet'              => 'quantity',
        'miktar'            => 'quantity',
        'brand'             => 'brand',
        'marka'             => 'brand',
        'gtin'              => 'gtin',
        'barcode'           => 'gtin',
        'barkod'            => 'gtin',
        'mpn'               => 'mpn',
        'keywords'          => 'keywords',
        'weight'            => 'weight',
        'agirlik'           => 'weight',
    );
}

/**
 * A header cell folded for comparing: lower case, Turkish letters as their
 * Latin twins, nothing but letters and digits.
 *
 * @param string $text
 * @return string
 */
function ws_bulk_header_key($text)
{
    $text = strtr((string) $text, array('İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ç' => 'c', 'ç' => 'c', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o'));

    return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($text));
}

/**
 * Is a text a list of several lines (not counting empty ones)? Under
 * /create product it makes the bulk card; one line is a single product.
 *
 * @param string $text
 * @return bool
 */
function ws_bulk_text_is_list($text)
{
    return count(ws_bulk_text_table($text)) > 1;
}

/**
 * A list of lines as a table: a row per line, cells split on tabs (a range
 * copied from a sheet), else semicolons, else bars; empty lines left out.
 *
 * @param string $text
 * @return array[] rows of cells
 */
function ws_bulk_text_table($text)
{
    $table = array();

    foreach (preg_split('/\r\n|\r|\n/', mb_substr((string) $text, 0, WS_BULK_TEXT_MAX)) as $line) {
        if (trim($line) === '') {
            continue;
        }

        $separator = (strpos($line, "\t") !== false) ? "\t" : ((strpos($line, ';') !== false) ? ';' : ((strpos($line, '|') !== false) ? '|' : null));
        $cells = ($separator === null) ? array($line) : explode($separator, $line);

        $table[] = array_map('trim', $cells);
    }

    return $table;
}

/**
 * Is a file, by its name, one products are made from: a CSV or a
 * spreadsheet the product import reads?
 *
 * @param string $file_name
 * @return bool
 */
function ws_bulk_file_is_table($file_name)
{
    return (bool) preg_match('/\.(csv|xlsx|xlsm|xls|ods)$/i', (string) $file_name);
}

/**
 * A CSV or spreadsheet file as a table, through the workflow table import's
 * reader (ws_wtable_import_file_table()): at most one card's rows and the
 * header, and one row more so that a longer file is refused.
 *
 * @param string $path      on disk
 * @param string $file_name the name it was posted under
 * @return array ok, error, table
 */
function ws_bulk_file_table($path, $file_name)
{
    if (!ws_bulk_file_is_table($file_name)) {
        return array('ok' => false, 'error' => lang('Products are made from a CSV, Excel or OpenDocument file.'), 'table' => array());
    }

    $read = ws_wtable_import_file_table($path, $file_name, WS_BULK_CREATE_MAX + 2);

    return array('ok' => $read['ok'], 'error' => $read['error'], 'table' => $read['table']);
}

/**
 * A table laid out as bulk create rows (ws_bulk_create_rows() checks them
 * after). A first row that names a known column is a header and the cells
 * are read by it; columns it does not know are named back. With no header
 * the first cell is the name and the first amount after it the price. A
 * name alone is the short description too, as /product writes it.
 *
 * @param array[] $table
 * @return array ok, error, rows, ignored (header cells not read)
 */
function ws_bulk_table_rows($table)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'rows' => array(), 'ignored' => array());
    };

    $table = array_values(array_filter((array) $table, 'is_array'));
    $known = ws_bulk_header_fields();
    $columns = array();
    $ignored = array();

    foreach ($table[0] ?? array() as $position => $cell) {
        $key = ws_bulk_header_key($cell);

        if (isset($known[$key]) && !in_array($known[$key], $columns, true)) {
            $columns[$position] = $known[$key];
        } elseif (trim((string) $cell) !== '') {
            $ignored[] = trim((string) $cell);
        }
    }

    $header = !empty($columns);
    $data = $header ? array_slice($table, 1) : $table;

    if (empty($data)) {
        return $fail(lang('There is no product row to make.'));
    }

    if (count($data) > WS_BULK_CREATE_MAX) {
        return $fail(lang(array('string' => 'One bulk card makes at most {var:1} records; bring more in with the product import (import_products.php).', 'vars' => WS_BULK_CREATE_MAX)));
    }

    $rows = array();

    foreach ($data as $index => $cells) {
        $number = $index + ($header ? 2 : 1);
        $row = array();

        if ($header) {
            foreach ($columns as $position => $field) {
                $cell = trim((string) ($cells[$position] ?? ''));

                if ($cell === '') {
                    continue;
                }

                if ($field === 'price') {
                    $cents = pg_products_import_price_cents($cell);

                    if ($cents === null) {
                        return $fail(lang(array('string' => 'Row {var:1}: {var:2} is not a price.', 'vars' => array($number, $cell))));
                    }

                    $cell = $cents;
                }

                $row[$field] = $cell;
            }
        } else {
            $first = trim((string) ($cells[0] ?? ''));

            if ($first !== '') {
                $row['name'] = $first;
                $row['short_description'] = $first;
            }

            foreach (array_slice($cells, 1) as $cell) {
                $cents = (trim((string) $cell) === '') ? null : pg_products_import_price_cents($cell);

                if ($cents !== null) {
                    $row['price'] = $cents;
                    break;
                }
            }
        }

        if (empty($row)) {
            continue;
        }

        // No code column: the title is the code, as /product writes it.
        if (!isset($row['name']) && isset($row['short_description'])) {
            $row = array('name' => $row['short_description']) + $row;
        }

        if (!isset($row['name'])) {
            return $fail(lang(array('string' => 'Row {var:1} has no name.', 'vars' => $number)));
        }

        $rows[] = $row;
    }

    if (empty($rows)) {
        return $fail(lang('There is no product row to make.'));
    }

    return array('ok' => true, 'error' => '', 'rows' => $rows, 'ignored' => $ignored);
}

/**
 * The table of a file posted in a channel the person may read.
 *
 * @param array $viewer
 * @param int   $message_id the message the file is on
 * @return array ok, error, table, name
 */
function ws_bulk_message_table($viewer, $message_id)
{
    $message = ws_message((int) $message_id);
    $channel = $message ? ws_channel($message['channel_id']) : null;

    if (!$message || !$channel || !ws_can_read_channel($viewer, $channel) || ((int) $message['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'), 'table' => array(), 'name' => '');
    }

    if (((int) $message['file_id'] <= 0) || !ws_bulk_file_is_table((string) $message['file_name'])) {
        return array('ok' => false, 'error' => lang('Products are made from a CSV, Excel or OpenDocument file.'), 'table' => array(), 'name' => '');
    }

    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'");

    if (($stored === '') || !is_file(FILE_DIRECTORY_PATH . '/' . $stored)) {
        return array('ok' => false, 'error' => lang('The file could not be read.'), 'table' => array(), 'name' => '');
    }

    if (!function_exists('pg_products_import_to_csv')) {
        require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');
    }

    $read = ws_bulk_file_table(FILE_DIRECTORY_PATH . '/' . $stored, (string) $message['file_name']);

    return $read + array('name' => (string) $message['file_name']);
}

/**
 * Makes the bulk create card of a file posted in a channel, for the person
 * (ws_bulk_create_card()), under a message of their own naming the file:
 * /create product answering the file, or sent with it
 * (ws_command_product_file()).
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param array $source message_id (the message the file is on)
 * @return array ok, error, message_id, count, ignored
 */
function ws_bulk_create_propose($viewer, $channel_id, $source)
{
    $read = ws_bulk_message_table($viewer, (int) ($source['message_id'] ?? 0));

    if (!$read['ok']) {
        return array('ok' => false, 'error' => $read['error'], 'message_id' => 0, 'count' => 0, 'ignored' => array());
    }

    $file_name = $read['name'];

    return ws_bulk_create_card($viewer, (int) $channel_id, ws_bulk_table_rows($read['table']), function ($count) use ($file_name) {
        return lang(array('string' => 'Product drafts: {var:1} rows from “{var:2}”.', 'vars' => array($count, $file_name)));
    });
}

/**
 * Makes a bulk create card for the person: the rows checked as an
 * assistant's would be, a message of their own in the channel, and the card
 * waiting under it for them to apply.
 *
 * @param array           $viewer
 * @param int             $channel_id
 * @param array           $laid ws_bulk_table_rows()
 * @param string|Closure  $body the message's text, or a function of the
 *                              number of rows giving it
 * @return array ok, error, message_id, count, ignored
 */
function ws_bulk_create_card($viewer, $channel_id, $laid, $body)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'message_id' => 0, 'count' => 0, 'ignored' => array());
    };

    if (!function_exists('ws_bulk_ready') || !ws_bulk_ready()) {
        return $fail(lang('This site cannot take proposed changes yet: its database has to be upgraded.'));
    }

    $channel = ws_channel((int) $channel_id);

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('That channel could not be found.'));
    }

    if (!ws_change_allowed($viewer, 'product', 0)) {
        return $fail(lang('You may not change a record of this kind.'));
    }

    if (!$laid['ok']) {
        return $fail($laid['error']);
    }

    if (!function_exists('pg_pb_price_figure')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $checked = ws_bulk_create_input($viewer, 'product', $laid['rows']);

    if (!$checked['ok']) {
        return $fail($checked['error']);
    }

    $body = ($body instanceof Closure) ? (string) $body($checked['matched']) : (string) $body;
    $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX));

    if (empty($sent['ok'])) {
        return $fail(!empty($sent['error']) ? (string) $sent['error'] : lang('Invalid request.'));
    }

    $snapshot = array('matched' => $checked['matched'], 'sample' => $checked['sample']);

    // A card with no request behind it: the person proposed it to
    // themselves, so only they apply it.
    db("INSERT INTO ws_ai_changes (request_id, channel_id, message_id, requested_by, record_type, action, record_id, fields, reason, snapshot, created_at)
        VALUES ('0', '" . (int) $channel['id'] . "', '" . (int) $sent['message_id'] . "', '" . (int) $viewer['id'] . "', 'product', 'bulk', '0',
            '" . e(json_encode($checked['spec'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "', '',
            '" . e(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)) . "', '" . time() . "')");

    ws_message_touch((int) $sent['message_id']);

    return array('ok' => true, 'error' => '', 'message_id' => (int) $sent['message_id'], 'count' => (int) $checked['matched'], 'ignored' => $laid['ignored']);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The collection CSV reader of the content import framework (includes/fn/
 * importers.php), shared by the Webflow and Wix readers: Webflow's CMS
 * export and Wix's CMS export both write one row per collection item and
 * one column per field, and neither file says what the fields mean.
 *
 * - The mapping step asks for the form the rows become records of and
 *   pairs each column with one of its fields (row col_<n>, the column's
 *   place in the header); the screen guesses each pair from matching names.
 * - An item is a record whose data carries the cells by column row name
 *   (data.columns); the record writer (write_record.php) writes each paired
 *   cell into its field: rich text as HTML through the media fetch and the
 *   cleaner, a picture as a fetched file, a reference as the text it holds.
 * - Webflow: key Item ID, title Name, Draft or Archived make a draft, the
 *   Slug gives the old address /<collection address>/<slug>, the collection
 *   address being a mapping row since the file does not hold it.
 * - Wix: key ID, title Title; wix:image:// addresses are rewritten to the
 *   public media host. The file holds no address.
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
 * The field types a column row offers: the ones the record writer fills
 * from a cell (pg_import_record_column_rows()).
 *
 * @return string[]
 */
function pg_import_collection_field_types()
{
    return array('text box', 'text area', 'email address', 'date', 'date and time', 'file upload', 'check box', 'pick list', 'radio button');
}

/**
 * A header cell as the reader compares it: lower case, single spaces. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_collection_column($cell)
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $cell)));
}

/**
 * The columns of a service's file that only identify the item in the old
 * service and are never offered to a field (lower case). Pure.
 *
 * @param string $service webflow or wix
 * @return string[]
 */
function pg_import_collection_id_columns($service)
{
    return ($service === 'webflow') ? array('collection id', 'locale id', 'item id') : array('id', 'owner');
}

/**
 * The header of a collection file: the cells as written, '' for none. A
 * missing or unreadable file has none.
 *
 * @param string $file
 * @return array{header:string[],separator:string,offset:int}
 */
function pg_import_collection_header($file)
{
    $answer = array('header' => array(), 'separator' => ',', 'offset' => -1);
    $handle = ((string) $file !== '') ? @fopen($file, 'rb') : false;

    if (!$handle) {
        return $answer;
    }

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $answer['separator'] = pg_csv_read_separator($handle);
    $cells = fgetcsv($handle, 0, $answer['separator'], '"', '\\');

    if (is_array($cells)) {
        $answer['header'] = array_map(function ($cell) {
            return trim(preg_replace('/\s+/u', ' ', (string) $cell));
        }, $cells);
        $answer['offset'] = (int) ftell($handle);
    }

    fclose($handle);

    return $answer;
}

/**
 * The mapping rows of a collection file: the form, the folder for its
 * pictures, for Webflow the collection's old address, then one field row
 * per column (col_<n>) whose guess is the column's heading. Pure but for
 * reading the header.
 *
 * @param string $file
 * @param string $service webflow or wix
 * @return array
 */
function pg_import_collection_mapping($file, $service)
{
    $rows = array(
        'form'        => array('label' => lang('Form the items become records of'), 'type' => 'form', 'required' => true),
        'file_folder' => array('label' => lang('Folder for pictures'), 'type' => 'folder', 'required' => false),
    );

    if ($service === 'webflow') {
        $rows['address_base'] = array(
            'label'       => lang('Address of the collection on the old site'),
            'type'        => 'text',
            'required'    => false,
            'placeholder' => '/blog',
            'help'        => lang('Each item gets a redirect from this address followed by its slug. Left empty, no redirect is made.'),
        );
    }

    return $rows + pg_import_collection_column_rows(pg_import_collection_header($file)['header'], $service);
}

/**
 * One field row per column of $header, but for the columns that only
 * identify the item. Pure.
 *
 * @param string[] $header
 * @param string   $service
 * @return array
 */
function pg_import_collection_column_rows($header, $service)
{
    $rows = array();
    $left_out = pg_import_collection_id_columns($service);

    foreach ((array) $header as $index => $heading) {
        $heading = (string) $heading;

        if (($heading === '') || in_array(pg_import_collection_column($heading), $left_out, true)) {
            continue;
        }

        $rows['col_' . (int) $index] = array(
            'label'       => lang(array('string' => 'Column: {var:1}', 'vars' => array($heading))),
            'type'        => 'form_field',
            'form'        => 'form',
            'required'    => false,
            'guess'       => $heading,
            'field_types' => pg_import_collection_field_types(),
        );
    }

    return $rows;
}

/**
 * @param string $file
 * @param string $service webflow or wix
 * @return array the cursor
 */
function pg_import_collection_open($file, $service)
{
    $head = pg_import_collection_header($file);

    return array(
        'file'      => (string) $file,
        'service'   => (string) $service,
        'offset'    => $head['header'] ? $head['offset'] : -1,
        'separator' => $head['separator'],
        'header'    => $head['header'],
        'line'      => 1,
    );
}

/**
 * The next item, one record per row. Null at the end.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_collection_next(&$cursor)
{
    if ((int) $cursor['offset'] < 0) {
        return null;
    }

    $handle = @fopen($cursor['file'], 'rb');

    if (!$handle) {
        return null;
    }

    fseek($handle, (int) $cursor['offset']);

    while (true) {
        $cells = fgetcsv($handle, 0, $cursor['separator'], '"', '\\');

        if ($cells === false) {
            $cursor['offset'] = -1;
            fclose($handle);

            return null;
        }

        $line = (int) $cursor['line'] + 1;
        $cursor['line'] += pg_csv_row_lines($cells);
        $cursor['offset'] = (int) ftell($handle);

        if (trim(implode('', $cells)) !== '') {
            break;
        }
    }

    fclose($handle);

    return pg_import_collection_item((string) $cursor['service'], (array) $cursor['header'], $cells, $line);
}

/**
 * The dry run's line of an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_collection_plan($item)
{
    $data = (array) ($item['data'] ?? array());
    $detail = (string) ($data['slug'] ?? '');

    if (($data['status'] ?? '') === 'draft') {
        $detail = trim($detail . ' (' . lang('Draft') . ')');
    }

    return array('title' => (string) ($item['title'] ?? ''), 'detail' => $detail);
}

// ---- Rows ------------------------------------------------------------------

/**
 * A yes/true cell. Pure.
 *
 * @param string $cell
 * @return bool
 */
function pg_import_collection_yes($cell)
{
    return in_array(mb_strtolower(trim((string) $cell)), array('true', 'yes', '1'), true);
}

/**
 * The Unix time of a date cell, 0 when it is empty or unreadable. Webflow
 * writes JavaScript's date text ("Fri Mar 15 2024 10:20:30 GMT+0000
 * (Coordinated Universal Time)"), whose zone name in brackets PHP cannot
 * read; Wix writes ISO 8601 in UTC. Pure.
 *
 * @param string $cell
 * @return int
 */
function pg_import_collection_time($cell)
{
    $cell = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $cell));

    if ($cell === '') {
        return 0;
    }

    $time = strtotime($cell);

    return ($time === false) ? 0 : (int) $time;
}

/**
 * The day a date cell names (Y-m-d), '' when it is empty or unreadable. The
 * day is read in the cell's own time zone: Webflow and Wix write a date
 * field as midnight UTC, which in the site's zone west of UTC is the day
 * before. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_collection_date($cell)
{
    $cell = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $cell));

    if (($cell === '') || (strtotime($cell) === false)) {
        return '';
    }

    try {
        return (new DateTime($cell))->format('Y-m-d');
    } catch (Exception $exception) {
        return '';
    }
}

/**
 * A cell with Wix's media addresses (wix:image://v1/<id>/<name>#…) turned
 * into the public addresses the media fetch can read. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_collection_wix_media($cell)
{
    return (string) preg_replace('#wix:image://v1/([A-Za-z0-9_~.\-]+)/[^\s"\'<>,;\]]*#', 'https://static.wixstatic.com/media/$1', (string) $cell);
}

/**
 * The record item of a row. Pure.
 *
 * @param string   $service webflow or wix
 * @param string[] $header  as written
 * @param array    $cells
 * @param int      $line    the row's line in the file
 * @return array
 */
function pg_import_collection_item($service, $header, $cells, $line)
{
    $columns = array();
    $named = array();

    foreach ((array) $header as $index => $heading) {
        $cell = trim((string) ($cells[$index] ?? ''));

        if ($service === 'wix') {
            $cell = pg_import_collection_wix_media($cell);
        }

        $name = pg_import_collection_column((string) $heading);

        if (($name !== '') && !isset($named[$name])) {
            $named[$name] = $cell;
        }

        if ($cell !== '') {
            $columns['col_' . (int) $index] = $cell;
        }
    }

    $data = array('columns' => $columns, 'status' => 'publish', 'slug' => '', 'time' => 0);

    if ($service === 'webflow') {
        $id = (string) ($named['item id'] ?? '');
        $title = (string) ($named['name'] ?? '');
        $data['slug'] = (string) ($named['slug'] ?? '');
        $data['time'] = pg_import_collection_time($named['published on'] ?? '') ?: pg_import_collection_time($named['created on'] ?? '');

        // A draft or archived item was not on the old site.
        if (pg_import_collection_yes($named['draft'] ?? '') || pg_import_collection_yes($named['archived'] ?? '')) {
            $data['status'] = 'draft';
        }
    } else {
        $id = (string) ($named['id'] ?? '');
        $title = (string) ($named['title'] ?? '');
        $data['time'] = pg_import_collection_time($named['created date'] ?? '');
    }

    if ($title === '') {
        $title = ($data['slug'] !== '') ? $data['slug'] : lang(array('string' => 'Line {var:1}', 'vars' => array((int) $line)));
    }

    return array(
        'kind'       => 'record',
        'source_key' => mb_substr($service . ':' . (($id !== '') ? 'item:' . $id : 'line:' . (int) $line), 0, 191),
        'title'      => $title,
        'data'       => $data,
    );
}

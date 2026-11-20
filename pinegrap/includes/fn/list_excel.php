<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Export to Excel for the panel lists that export CSV.
 *
 * A list does not build its workbook itself: it asks for the Excel form of
 * the CSV it already writes. pg_list_excel_capture(), called where the CSV
 * export starts, holds back everything the export prints; when the request
 * ends, the CSV is read back, its cells typed by the list's column map
 * (number, money, date, text) and written as a one-sheet .xlsx with a bold,
 * frozen heading row (includes/fn/xlsx_write.php). The rows, the filters and
 * the permission are therefore those of the CSV, by construction.
 *
 * An export that ends in anything but a CSV (a refusal page, a redirect)
 * is let through unchanged.
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
 * Whether the request asks for the Excel form of a list's export (excel=1,
 * in the query or a posted form).
 *
 * @return bool
 */
function pg_list_excel_asked()
{
    $asked = isset($_GET['excel']) ? $_GET['excel'] : (isset($_POST['excel']) ? $_POST['excel'] : '');

    return $asked === '1';
}

/**
 * The address of a list's Excel export: its CSV export's address with
 * excel=1.
 *
 * @param string $csv_url
 * @return string
 */
function pg_list_excel_url($csv_url)
{
    $csv_url = (string) $csv_url;

    return $csv_url . ((strpos($csv_url, '?') === false) ? '?' : '&') . 'excel=1';
}

/**
 * The toolbar action of a list's Excel export, as a pg_screen_toolbar()
 * button.
 *
 * @param string $csv_url
 * @return array
 */
function pg_list_excel_action($csv_url)
{
    return array('label' => lang('Export to Excel'), 'url' => pg_list_excel_url($csv_url), 'icon' => 'bi-file-earmark-excel');
}

/**
 * The same action as a link, for a toolbar a screen draws itself.
 *
 * @param string $csv_url
 * @param string $class   The link's classes, those of the CSV link beside it
 * @return string
 */
function pg_list_excel_link($csv_url, $class = 'pg-button pg-button-ghost')
{
    return '<a class="' . h($class) . '" href="' . h(pg_list_excel_url($csv_url)) . '">' . pg_list_excel_icon($class) . h(lang('Export to Excel')) . '</a>';
}

/**
 * The same action as a submit button, for a list whose CSV export is a
 * button of its filter form: the form's fields go with it, and excel=1
 * names the Excel form.
 *
 * @param string $class
 * @param string $attributes Markup of further attributes, the CSV button's
 *                           own (an onclick check)
 * @return string
 */
function pg_list_excel_button($class = 'pg-button pg-button-ghost', $attributes = '')
{
    return '<button type="submit" name="excel" value="1" class="' . h($class) . '"' . (($attributes !== '') ? ' ' . $attributes : '') . '>' . pg_list_excel_icon($class) . h(lang('Export to Excel')) . '</button>';
}

/**
 * The action's icon; a menu item spaces it from its label itself.
 *
 * @param string $class
 * @return string
 */
function pg_list_excel_icon($class)
{
    return '<i class="bi bi-file-earmark-excel' . ((strpos(' ' . $class . ' ', ' menu-item ') !== false) ? ' me-3' : '') . '" aria-hidden="true"></i>';
}

/**
 * Starts holding back the CSV export when the request asks for Excel. Call
 * it where the export starts, before anything of the file is printed.
 *
 * @param array  $types       Column type by heading text or zero-based index:
 *                            'number', 'money', 'date'; the rest are text
 * @param string $sheet_title
 * @return bool true when the export will be sent as Excel
 */
function pg_list_excel_capture($types = array(), $sheet_title = '')
{
    if (!pg_list_excel_asked() || !class_exists('ZipArchive') || !empty($GLOBALS['pg_list_excel'])) {
        return false;
    }

    $buffer = fopen('php://temp', 'w+b');

    if ($buffer === false) {
        return false;
    }

    $GLOBALS['pg_list_excel'] = array(
        'types'  => (array) $types,
        'title'  => (string) $sheet_title,
        'buffer' => $buffer,
        'level'  => ob_get_level() + 1,
    );

    ob_start('pg_list_excel_collect');
    register_shutdown_function('pg_list_excel_finish');

    return true;
}

/**
 * Whether an Excel export is holding back the output; a streaming export
 * skips its flush() then, which would send the CSV's headers.
 *
 * @return bool
 */
function pg_list_excel_capturing()
{
    return !empty($GLOBALS['pg_list_excel']);
}

/**
 * The output handler: keeps what the export prints and passes nothing on.
 *
 * @param string $chunk
 * @return string
 */
function pg_list_excel_collect($chunk)
{
    if (!empty($GLOBALS['pg_list_excel']) && ($chunk !== '')) {
        fwrite($GLOBALS['pg_list_excel']['buffer'], $chunk);
    }

    return '';
}

/**
 * At the end of the request: the held-back CSV as a workbook, or what was
 * printed as it was when it is no CSV or cannot be turned into one.
 *
 * @return void
 */
function pg_list_excel_finish()
{
    if (empty($GLOBALS['pg_list_excel'])) {
        return;
    }

    $state = $GLOBALS['pg_list_excel'];

    while (ob_get_level() >= $state['level']) {
        ob_end_flush();
    }

    $GLOBALS['pg_list_excel'] = null;
    $buffer = $state['buffer'];
    rewind($buffer);

    $is_csv = false;
    $disposition = '';

    foreach (headers_list() as $header) {
        if (preg_match('/^content-type:\s*text\/csv/i', $header)) {
            $is_csv = true;
        } elseif (preg_match('/^content-disposition:/i', $header)) {
            $disposition = $header;
        }
    }

    $path = false;

    // A CSV too big for the memory left, or for one sheet, goes out as it
    // was: building the workbook would end the request in a fatal error.
    if ($is_csv && !headers_sent() && !pg_list_excel_fits(pg_list_excel_size($buffer), pg_ini_bytes(@ini_get('memory_limit')), memory_get_usage())) {
        $is_csv = false;
    }

    if ($is_csv && !headers_sent()) {
        $table = pg_list_excel_table($buffer, $state['types'], defined('DATE_FORMAT') ? DATE_FORMAT : 'month_day');
        $directory = PG_FUNCTIONS_DIR . '/data/temp';
        $path = (is_dir($directory) || @mkdir($directory, 0755, true)) ? @tempnam($directory, 'list_') : false;
        $title = ($state['title'] !== '') ? $state['title'] : pg_list_excel_file_name($disposition, 'export.csv');

        if (($path !== false) && !pg_xlsx_write_plain($table['headings'], $table['rows'], $path, $title)) {
            @unlink($path);
            $path = false;
        }
    }

    if ($path === false) {
        rewind($buffer);
        fpassthru($buffer);
        fclose($buffer);

        return;
    }

    fclose($buffer);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header(($disposition !== '') ? pg_list_excel_disposition($disposition) : 'Content-Disposition: attachment; filename="export.xlsx"');
    header('Content-Length: ' . (int) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    @unlink($path);
}

/**
 * The size of a held-back CSV: its bytes, its rows below the heading and
 * its cells, the heading's too. The stream is put back at its start.
 *
 * @param resource $handle
 * @return array array('bytes' => int, 'rows' => int, 'cells' => int)
 */
function pg_list_excel_size($handle)
{
    rewind($handle);
    $stat = fstat($handle);
    $bytes = isset($stat['size']) ? (int) $stat['size'] : 0;

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $start = ftell($handle);
    $delimiter = pg_list_excel_delimiter((string) fgets($handle));
    fseek($handle, $start);

    $escape = (PHP_VERSION_ID >= 70400) ? '' : '\\';
    $lines = 0;
    $cells = 0;

    // Counted as pg_list_excel_table() reads them: an empty line is no row.
    while (($line = fgetcsv($handle, 0, $delimiter, '"', $escape)) !== false) {
        if (($line === array(null)) || ($line === array(''))) {
            continue;
        }

        $lines++;
        $cells += count($line);
    }

    rewind($handle);

    return array('bytes' => $bytes, 'rows' => max(0, $lines - 1), 'cells' => $cells);
}

/**
 * The memory, in bytes, that pg_list_excel_table() and
 * pg_xlsx_write_plain() take at most for a CSV of this size: each cell
 * held as a value and again as XML (measured at up to 140 bytes a cell and 12 a
 * CSV byte on PHP 8.3), with a margin.
 *
 * @param array $size pg_list_excel_size()
 * @return int
 */
function pg_list_excel_need($size)
{
    return ((int) $size['cells'] * 200) + ((int) $size['bytes'] * 16);
}

/**
 * Whether a CSV can be sent as a workbook: one sheet holds the heading and
 * 1,048,575 rows, and the build must fit in the memory left.
 *
 * @param array $size         pg_list_excel_size()
 * @param int   $memory_limit bytes, 0 for none (pg_ini_bytes())
 * @param int   $in_use       bytes already taken
 * @return bool
 */
function pg_list_excel_fits($size, $memory_limit, $in_use)
{
    if ((int) $size['rows'] > 1048575) {
        return false;
    }

    return ($memory_limit <= 0) || (pg_list_excel_need($size) < ($memory_limit - $in_use));
}

/**
 * The CSV's Content-Disposition header with the .csv names as .xlsx (the
 * plain name and a filename* UTF-8 name alike).
 *
 * @param string $header
 * @return string
 */
function pg_list_excel_disposition($header)
{
    return preg_replace('/\.csv(?=["\';\s]|$)/i', '.xlsx', (string) $header);
}

/**
 * The file name of a Content-Disposition header without its extension, for
 * the sheet's title.
 *
 * @param string $header
 * @param string $fallback
 * @return string
 */
function pg_list_excel_file_name($header, $fallback)
{
    $name = preg_match('/filename="?([^";]+)/i', (string) $header, $match) ? $match[1] : $fallback;

    return preg_replace('/\.[a-z]+$/i', '', $name);
}

/**
 * The separator of a CSV's heading line: the one of comma, semicolon and tab
 * it holds most often outside quotes.
 *
 * @param string $line
 * @return string
 */
function pg_list_excel_delimiter($line)
{
    $bare = preg_replace('/"(?:[^"]|"")*"/', '', (string) $line);
    $best = ',';
    $most = 0;

    foreach (array(',', ';', "\t") as $delimiter) {
        $count = substr_count($bare, $delimiter);

        if ($count > $most) {
            $best = $delimiter;
            $most = $count;
        }
    }

    return $best;
}

/**
 * A CSV read back into a heading and typed rows for pg_xlsx_write_plain().
 *
 * @param resource $handle      At the CSV's start (a byte order mark is skipped)
 * @param array    $types       Column type by heading text or zero-based index
 * @param string   $date_format DATE_FORMAT, for the day order of the panel's dates
 * @return array array('headings' => list, 'rows' => list of lists)
 */
function pg_list_excel_table($handle, $types, $date_format)
{
    $start = ftell($handle);

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        fseek($handle, $start);
    }

    $start = ftell($handle);
    $delimiter = pg_list_excel_delimiter((string) fgets($handle));
    fseek($handle, $start);

    $headings = array();
    $column_types = array();
    $rows = array();
    // The exports double a quote and use no escape character (RFC 4180);
    // an empty escape reads them exactly, from PHP 7.4 on.
    $escape = (PHP_VERSION_ID >= 70400) ? '' : '\\';

    while (($cells = fgetcsv($handle, 0, $delimiter, '"', $escape)) !== false) {
        if (($cells === array(null)) || ($cells === array(''))) {
            continue;
        }

        if (empty($headings)) {
            foreach ($cells as $index => $heading) {
                $heading = (string) $heading;
                $headings[] = $heading;
                $column_types[] = isset($types[$index]) ? $types[$index] : (isset($types[$heading]) ? $types[$heading] : 'text');
            }

            continue;
        }

        $row = array();

        foreach ($cells as $index => $cell) {
            $row[] = pg_list_excel_cell((string) $cell, isset($column_types[$index]) ? $column_types[$index] : 'text', $delimiter, $date_format);
        }

        $rows[] = $row;
    }

    return array('headings' => $headings, 'rows' => $rows);
}

/**
 * One CSV cell as a workbook cell. A number, money or date column whose cell
 * is not one stays text. Text loses the CSV's formula guard: a workbook's
 * text cell is never a formula.
 *
 * @param string $value
 * @param string $type      'number', 'money', 'date' or 'text'
 * @param string $delimiter The CSV's separator: a semicolon file writes a decimal comma
 * @param string $date_format
 * @return mixed
 */
function pg_list_excel_cell($value, $type, $delimiter, $date_format)
{
    if ($value === '') {
        return '';
    }

    if (($type === 'number') || ($type === 'money')) {
        $number = trim($value);

        if (($delimiter === ';') && preg_match('/^-?\d+,\d+$/', $number)) {
            $number = str_replace(',', '.', $number);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $number)) {
            if ($type === 'money') {
                return array('money' => (float) $number);
            }

            return (strpos($number, '.') === false) && (strlen(ltrim($number, '-')) < 16) ? (int) $number : (float) $number;
        }
    } elseif ($type === 'date') {
        $date = pg_list_excel_date($value, $date_format);

        if ($date !== null) {
            return array('date' => $date);
        }
    }

    return pg_csv_formula_unguard($value);
}

/**
 * A date as a panel export writes it, as Y-m-d or Y-m-d H:i:s: an ISO date
 * (2026-11-19, 2026-11-19 17:05[:09]) or the panel's own form
 * (pg_csv_export_time(): 11/19/2026 5:05:09 PM +03, 19/11/2026 17:05:09 +03).
 * The zone is dropped: the cell shows the site's wall clock, as the CSV does.
 *
 * @param string $value
 * @param string $date_format 'month_day' or 'day_month'
 * @return string|null null when it is no date
 */
function pg_list_excel_date($value, $date_format)
{
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $value, $parts)) {
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        $hour = isset($parts[4]) ? (int) $parts[4] : null;
        $minute = isset($parts[5]) ? (int) $parts[5] : 0;
        $second = isset($parts[6]) ? (int) $parts[6] : 0;
    } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?: (\d{1,2}):(\d{2})(?::(\d{2}))?(?: ?([AaPp][Mm]))?(?: \S+)?)?$/', $value, $parts)) {
        $first = (int) $parts[1];
        $second_part = (int) $parts[2];
        $month = ($date_format === 'month_day') ? $first : $second_part;
        $day = ($date_format === 'month_day') ? $second_part : $first;
        $year = (int) $parts[3];
        $hour = isset($parts[4]) && ($parts[4] !== '') ? (int) $parts[4] : null;
        $minute = isset($parts[5]) ? (int) $parts[5] : 0;
        $second = isset($parts[6]) ? (int) $parts[6] : 0;

        if (($hour !== null) && !empty($parts[7])) {
            if (($hour < 1) || ($hour > 12)) {
                return null;
            }

            $hour = ($hour % 12) + ((strtoupper($parts[7]) === 'PM') ? 12 : 0);
        }
    } else {
        return null;
    }

    if (($year < 1900) || !checkdate($month, $day, $year) || (($hour !== null) && (($hour > 23) || ($minute > 59) || ($second > 59)))) {
        return null;
    }

    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

    return ($hour === null) ? $date : $date . sprintf(' %02d:%02d:%02d', $hour, $minute, $second);
}

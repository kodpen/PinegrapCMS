<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Spreadsheets for the CSV importers: an uploaded .xlsx, .ods or .xls is
 * read through the sheet reader (includes/fn/sheet_read.php) and handed to
 * the importer as the CSV file it already reads.
 *
 * pg_sheet_import_upload() is called by an importer after its token check
 * and before it looks at the upload. A CSV upload passes untouched. A
 * workbook with one sheet is turned into a CSV file in place: the upload's
 * tmp_name names that file from then on, so the importer's own checks,
 * separator guess and row loop run as for a CSV. A workbook with more than
 * one sheet is kept aside and a page asks which sheet to import; that page
 * posts the importer's own fields back with the choice, and the chosen
 * sheet then comes in as the upload.
 *
 * The importers that read a workbook themselves (Products > Import, the
 * bank statement import) ask the same question through
 * pg_sheet_import_workbook_answer() and pg_sheet_import_workbook_ask(), and
 * read the chosen sheet with their own reader.
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
 * How long a workbook waits for its sheet to be chosen, in seconds.
 */
define('PG_SHEET_IMPORT_MAX_AGE', 3600);

/**
 * The extensions the importers' file fields accept beside their CSV ones.
 */
define('PG_SHEET_IMPORT_ACCEPT', '.xlsx,.ods,.xls');

/**
 * The names of a workbook's sheets, in the order pg_sheet_rows() counts
 * them: worksheets only, a chart sheet left out.
 *
 * @param string $path  the file on disk
 * @param string $kind  pg_sheet_kind(): xlsx, ods or xls
 * @param string $error '' or read, large (pg_sheet_rows())
 * @return array of string
 */
function pg_sheet_names($path, $kind, &$error)
{
    $error = '';
    $path = (string) $path;
    $names = array();

    if ((($kind === 'xlsx') || ($kind === 'xlsm')) && class_exists('ZipArchive') && class_exists('DOMDocument')) {
        $zip = new ZipArchive();

        if (is_file($path) && ($zip->open($path) === true)) {
            foreach (pg_office_xlsx_sheets($zip, $refused) as $entry) {
                if ($entry['type'] === 'worksheet') {
                    $names[] = $entry['name'];
                }
            }

            $zip->close();

            if ($refused !== '') {
                $error = ($refused === 'large') ? 'large' : 'read';
                return array();
            }
        }
    } elseif ($kind === 'ods') {
        $names = pg_sheet_ods_names($path, $error);
    } elseif ($kind === 'xls') {
        $names = pg_sheet_xls_names($path);
    }

    if (($error === '') && empty($names)) {
        $error = 'read';
    }

    return ($error === '') ? $names : array();
}

/**
 * The table names of an .ods file's content.xml, each table passed over
 * without reading its cells.
 *
 * @param string $path
 * @param string $error '' or read, large
 * @return array of string
 */
function pg_sheet_ods_names($path, &$error)
{
    $error = '';

    if (!class_exists('ZipArchive') || !class_exists('XMLReader') || !is_file($path)) {
        $error = 'read';
        return array();
    }

    $size = pg_zip_entry_size($path, 'content.xml');

    if ($size <= 0) {
        $error = 'read';
        return array();
    }

    if ($size > PG_SHEET_ODS_MAX_PART) {
        $error = 'large';
        return array();
    }

    $reader = new XMLReader();

    if (!$reader->open(pg_zip_entry_uri($path, 'content.xml', $size), null, LIBXML_NONET)) {
        $error = 'read';
        return array();
    }

    $table_ns = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    $previous = libxml_use_internal_errors(true);
    $names = array();
    $skip = false;

    while ($skip ? $reader->next() : $reader->read()) {
        $skip = false;

        if (($reader->nodeType === XMLReader::ELEMENT) && ($reader->localName === 'table') && ($reader->namespaceURI === $table_ns)) {
            $names[] = (string) $reader->getAttributeNs('name', $table_ns);
            $skip = true;
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if (pg_zip_entry_overran()) {
        $error = 'read';
        return array();
    }

    return $names;
}

/**
 * The worksheet names of an .xls workbook, from its BOUNDSHEET records; a
 * chart, macro or dialog sheet is left out, as the reader does not count
 * it. Empty when the file cannot be read.
 *
 * @param string $path
 * @return array of string
 */
function pg_sheet_xls_names($path)
{
    if (!is_file($path)) {
        return array();
    }

    // The same load as pg_sheet_xls_load(), asking for a sheet no workbook
    // has: the workbook globals are read and checked, no sheet's cells.
    @require_once(PG_FUNCTIONS_DIR . '/excel_reader2.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/sheet_read_xls.php');

    $reader = new Pg_Sheet_Xls_Reader(PHP_INT_MAX);

    try {
        @$reader->read($path);
    } catch (Throwable $exception) {
        return array();
    }

    if ($reader->pg_broken || ($reader->_ole->error !== 0)) {
        return array();
    }

    $data = (string) $reader->data;
    $length = strlen($data);
    $version = ($length >= 6) ? (ord($data[4]) | (ord($data[5]) << 8)) : 0;
    $names = array();
    $pos = 0;

    // The globals end at their EOF; _parse() has checked that one is there.
    while ($pos + 4 <= $length) {
        $code = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
        $size = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);

        if (($code === SPREADSHEET_EXCEL_READER_TYPE_EOF) || ($pos + 4 + $size > $length)) {
            break;
        }

        $record = substr($data, $pos + 4, $size);
        $pos += 4 + $size;

        if (($code !== SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET) || ($size < 8)) {
            continue;
        }

        // The sheet's own BOF says what kind of sheet it is.
        $offset = ord($record[0]) | (ord($record[1]) << 8) | (ord($record[2]) << 16) | (ord($record[3]) << 24);

        if (($offset < 0) || ($offset + 8 > $length) || ((ord($data[$offset + 6]) | (ord($data[$offset + 7]) << 8)) !== SPREADSHEET_EXCEL_READER_WORKSHEET)) {
            continue;
        }

        $chars = ord($record[6]);

        if ($version === SPREADSHEET_EXCEL_READER_BIFF8) {
            $wide = (ord($record[7]) & 0x01) === 0x01;
            $names[] = $reader->pg_convert(substr($record, 8, $wide ? $chars * 2 : $chars), $wide ? 'UTF-16LE' : 'ISO-8859-1');
        } else {
            $names[] = $reader->pg_bytes(substr($record, 7, $chars));
        }
    }

    return $names;
}

/**
 * One sheet of a workbook written out as a CSV file the importers read:
 * UTF-8 without a byte order mark, every cell quoted, a cell as
 * pg_sheet_cell_text() gives it. The first row with text is the header;
 * a row is as wide as the header at least; a later row the sheet leaves out, or one without text, is a blank line,
 * so a file line is the sheet's row number less the rows above the header.
 *
 * The separator is the one given, or else the comma, unless the headings
 * hold commas: then the semicolon or the tab, whichever none of them holds,
 * so an importer that counts separators on the header line finds it.
 *
 * @param string $path   the workbook
 * @param string $kind   pg_sheet_kind()
 * @param int    $sheet  which sheet, 0 for the first
 * @param string $target the CSV file to write
 * @param string $error  '' or read, large, sheet (pg_sheet_rows()), empty
 * @param string $separator the importer's own separator, '' to choose one
 * @return bool
 */
function pg_sheet_import_csv($path, $kind, $sheet, $target, &$error, $separator = '')
{
    $error = '';
    $header = null;
    $width = 0;
    $last = 0;
    $handle = @fopen($target, 'wb');

    if (!$handle) {
        $error = 'read';
        return false;
    }

    // Each row goes to the file as it is read, so a big sheet is never held
    // whole; a late error empties the file and refuses it.
    foreach (pg_sheet_rows($path, $kind, $read_error, $sheet) as $number => $cells) {
        $texts = array();

        foreach ($cells as $cell) {
            $texts[] = pg_sheet_cell_text($cell);
        }

        // Empty cells at the end of a row are not written (a row shorter
        // than the header is filled up to it below).
        while ($texts && (end($texts) === '')) {
            array_pop($texts);
        }

        if ($header === null) {
            if (!$texts) {
                continue;
            }

            $header = $texts;
            $width = count($header);
            $last = $number;

            if ($separator === '') {
                $joined = implode('', $header);
                $separator = ',';

                if (strpos($joined, ',') !== false) {
                    $separator = (strpos($joined, ';') === false) ? ';' : "\t";
                }
            }

            fwrite($handle, pg_sheet_import_csv_line($header, $separator));
            continue;
        }

        for ($gap = $last + 1; $gap < $number; $gap++) {
            fwrite($handle, pg_sheet_import_csv_line(array(), $separator));
        }

        // A row as wide as the header at least, as a CSV file writes it.
        while (count($texts) < $width) {
            $texts[] = '';
        }

        fwrite($handle, pg_sheet_import_csv_line($texts, $separator));
        $last = $number;
    }

    if (($read_error === '') && ($header === null)) {
        $read_error = 'empty';
    }

    if ($read_error !== '') {
        ftruncate($handle, 0);
        fclose($handle);
        $error = $read_error;
        return false;
    }

    fclose($handle);

    return true;
}

/**
 * One CSV line with every cell quoted and a quote inside a cell doubled.
 *
 * @param array  $texts
 * @param string $separator
 * @return string
 */
function pg_sheet_import_csv_line($texts, $separator)
{
    $cells = array();

    foreach ($texts as $text) {
        $cells[] = '"' . str_replace('"', '""', (string) $text) . '"';
    }

    return implode($separator, $cells) . "\n";
}

/**
 * A reader error as the importers' existing refusal text.
 *
 * @param string $error read, large, sheet, empty, expired
 * @return string
 */
function pg_sheet_import_error_text($error)
{
    switch ((string) $error) {
        case 'large':
            return lang('The file is too large.');

        case 'sheet':
        case 'empty':
            return lang('The file was empty.');

        case 'expired':
            return lang('The upload has expired. Start again.');
    }

    return lang('The file could not be read.');
}

/**
 * The CSV files this request made from workbooks, or one added to them.
 * Each is removed when the request ends.
 *
 * @param string|null $add
 * @return array of string
 */
function pg_sheet_import_made($add = null)
{
    static $made = array();

    if ($add !== null) {
        if (!$made) {
            register_shutdown_function(function () {
                foreach (pg_sheet_import_made() as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            });
        }

        $made[] = (string) $add;
    }

    return $made;
}

/**
 * Whether an upload's file may be read: a file PHP received, or the CSV
 * file pg_sheet_import_upload() made in its place. For the importers that
 * check is_uploaded_file() themselves.
 *
 * @param array|null $upload a $_FILES entry
 * @return bool
 */
function pg_sheet_import_file($upload)
{
    $file = (is_array($upload) && isset($upload['tmp_name']) && is_string($upload['tmp_name'])) ? $upload['tmp_name'] : '';

    if ($file === '') {
        return false;
    }

    return is_uploaded_file($file) || in_array($file, pg_sheet_import_made(), true);
}

/**
 * Where a workbook waits for its sheet to be chosen, '' when it cannot.
 * The token is checked against its shape before it goes near a path.
 *
 * @param string $token 32 hex characters
 * @return string
 */
function pg_sheet_import_staged_path($token)
{
    if (preg_match('/^[a-f0-9]{32}$/', (string) $token) !== 1) {
        return '';
    }

    $directory = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    return pg_dir_writable($directory) ? ($directory . '/sheet_import_' . $token . '.bin') : '';
}

/**
 * The fields of a posted form as hidden inputs, arrays at any depth, the
 * form token and the sheet fields left out: the sheet question posts them
 * back to the importer unchanged.
 *
 * @param array  $fields
 * @param string $prefix the name the fields sit under, '' at the top
 * @return string HTML
 */
function pg_sheet_import_hidden_fields($fields, $prefix = '')
{
    $html = '';

    foreach ((array) $fields as $key => $value) {
        $name = ($prefix === '') ? (string) $key : ($prefix . '[' . $key . ']');

        if (($prefix === '') && in_array((string) $key, array('token', 'pg_sheet', 'pg_sheet_token'), true)) {
            continue;
        }

        if (is_array($value)) {
            $html .= pg_sheet_import_hidden_fields($value, $name);
        } else {
            $html .= '<input type="hidden" name="' . h($name) . '" value="' . h((string) $value) . '">';
        }
    }

    return $html;
}

/**
 * The page that asks which sheet of a workbook to import.
 *
 * @param string $title     the importer's screen title
 * @param string $file_name the uploaded file's name
 * @param array  $names     the sheet names
 * @param string $token     the staged workbook's token
 * @param string $back      where Cancel goes, '' for the importer's own address
 * @return string HTML
 */
function pg_sheet_import_question($title, $file_name, $names, $token, $back = '')
{
    $options = '';

    foreach (array_values($names) as $index => $name) {
        $options .= '<option value="' . $index . '">' . h(($name !== '') ? $name : lang(array('string' => 'Sheet {var:1}', 'vars' => $index + 1))) . '</option>';
    }

    $self = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return pg_page_shell(array(
        'title'               => $title,
        'extra classes'       => 'pg-measure-edit',
        'icon'                => 'upload_file',
        'heading'             => $title,
        'heading_description' => lang('This workbook has more than one sheet. Choose the one to import.'),
    )) . '
<main id="content" class="container-fluid">
    <form action="' . h($self) . '" method="post">
        ' . get_token_field() . '
        ' . pg_sheet_import_hidden_fields($_POST) . '
        <input type="hidden" name="pg_sheet_token" value="' . h($token) . '">
        <section class="pg-card p-5 my-7" aria-labelledby="pg_sheet_file">
            <h2 class="pg-title-section mb-4" id="pg_sheet_file">' . h($file_name) . '</h2>
            <label class="pg-field-label" for="pg_sheet">' . h(lang('Sheet to import')) . '</label>
            <select class="pg-input" id="pg_sheet" name="pg_sheet">' . $options . '</select>
            <div class="d-flex flex-wrap gap-3 mt-5">
                <button type="submit" class="pg-button pg-button-fill">' . h(lang('Continue')) . '</button>
                <a class="pg-button pg-button-quiet" href="' . h(($back !== '') ? $back : $self) . '">' . h(lang('Cancel')) . '</a>
            </div>
        </section>
    </form>
</main>' . output_footer();
}

/**
 * Forgets the workbooks nobody chose a sheet for within the hour, and
 * gives the ones still waiting.
 *
 * @return array token => array(name, kind, screen, time)
 */
function pg_sheet_import_staged()
{
    $staged = isset($_SESSION['software']['sheet_import']) && is_array($_SESSION['software']['sheet_import']) ? $_SESSION['software']['sheet_import'] : array();

    foreach ($staged as $key => $entry) {
        if ((time() - (int) ($entry['time'] ?? 0)) > PG_SHEET_IMPORT_MAX_AGE) {
            $old = pg_sheet_import_staged_path($key);

            if (($old !== '') && is_file($old)) {
                @unlink($old);
            }

            unset($staged[$key]);
        }
    }

    $_SESSION['software']['sheet_import'] = $staged;

    // A question left unanswered in a session that ended is in no session
    // any more: its workbook goes by the file's age.
    pg_sheet_import_sweep(PG_FUNCTIONS_DIR . '/data/temp', time());

    return $staged;
}

/**
 * Removes the waiting workbooks (sheet_import_<token>.bin) older than
 * PG_SHEET_IMPORT_MAX_AGE from a folder, whichever session kept them.
 *
 * @param string $directory
 * @param int    $now
 * @return int how many were removed
 */
function pg_sheet_import_sweep($directory, $now)
{
    $removed = 0;
    $files = is_dir($directory) ? glob(rtrim($directory, '/') . '/sheet_import_*.bin') : array();

    foreach ((array) $files as $file) {
        if (preg_match('/\/sheet_import_[a-f0-9]{32}\.bin$/', $file) !== 1) {
            continue;
        }

        $time = @filemtime($file);

        if (($time !== false) && (($now - $time) > PG_SHEET_IMPORT_MAX_AGE) && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * The workbook a sheet question posted back, when the post carries
 * pg_sheet_token: its file, name, kind and the chosen sheet. The token is
 * spent and the file removed when the request ends.
 *
 * @param string $error '' or expired: the token is unknown, old or from
 *                      another screen
 * @return array|null ['path', 'name', 'kind', 'sheet'], null when the post
 *                    is not an answer to the sheet question
 */
function pg_sheet_import_answer(&$error)
{
    $error = '';
    $token = isset($_POST['pg_sheet_token']) ? (string) $_POST['pg_sheet_token'] : '';
    $staged = pg_sheet_import_staged();

    if ($token === '') {
        return null;
    }

    $entry = isset($staged[$token]) ? $staged[$token] : null;
    $path = pg_sheet_import_staged_path($token);
    $screen = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (!is_array($entry) || ($entry['screen'] !== $screen) || ($path === '') || !is_file($path)) {
        $error = 'expired';
        return null;
    }

    unset($_SESSION['software']['sheet_import'][$token]);
    // The file goes when the request ends, whatever the import makes of it.
    pg_sheet_import_made($path);

    return array(
        'path'  => $path,
        'name'  => (string) $entry['name'],
        'kind'  => (string) $entry['kind'],
        'sheet' => isset($_POST['pg_sheet']) ? max(0, (int) $_POST['pg_sheet']) : 0,
    );
}

/**
 * Keeps an uploaded workbook aside and prints the sheet question, then ends
 * the request. Returns the refusal text only when the file cannot be kept.
 *
 * @param string $path    the uploaded file
 * @param string $name    its name
 * @param string $kind    pg_sheet_kind()
 * @param array  $names   its sheet names, more than one
 * @param string $title   the importer's screen title
 * @param array  $options 'back': see pg_sheet_import_upload()
 * @return string
 */
function pg_sheet_import_ask($path, $name, $kind, $names, $title, $options = array())
{
    $token = bin2hex(random_bytes(16));
    $target = pg_sheet_import_staged_path($token);

    if (($target === '') || !move_uploaded_file($path, $target)) {
        return pg_sheet_import_error_text('read');
    }

    $_SESSION['software']['sheet_import'][$token] = array(
        'name'   => mb_substr($name, 0, 200),
        'kind'   => $kind,
        'screen' => basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')),
        'time'   => time(),
    );

    print pg_sheet_import_question($title, $name, $names, $token, (string) ($options['back'] ?? ''));
    exit();
}

/**
 * The upload in a file field when it is a workbook PHP received: its path,
 * name and kind; null for a CSV, no file or a failed upload.
 *
 * @param string $field
 * @return array|null ['path', 'name', 'kind']
 */
function pg_sheet_import_uploaded_workbook($field)
{
    $upload = isset($_FILES[$field]) && is_array($_FILES[$field]) ? $_FILES[$field] : null;

    if (($upload === null) || !is_string($upload['name'] ?? null) || !is_string($upload['tmp_name'] ?? null) || ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file($upload['tmp_name'])) {
        return null;
    }

    $kind = pg_sheet_kind($upload['name']);

    return ($kind === '') ? null : array('path' => $upload['tmp_name'], 'name' => $upload['name'], 'kind' => $kind);
}

/**
 * Whether a workbook asks which sheet to import: its sheet names when it
 * has more than one, else an empty list (one sheet, or a file the names
 * could not be read from, which the importer's own reader then refuses).
 *
 * @param string $path
 * @param string $kind pg_sheet_kind()
 * @return array of string
 */
function pg_sheet_import_question_sheets($path, $kind)
{
    $names = pg_sheet_names($path, $kind, $error);

    return (($error === '') && (count($names) > 1)) ? $names : array();
}

/**
 * Turns a workbook upload into the CSV file the importer reads; see the
 * file's head. Returns '' when the importer goes on (a CSV, no file or a
 * failed upload untouched, for its own checks), or the refusal text to show.
 * A workbook with more than one sheet and no sheet chosen yet prints the
 * sheet question and ends the request.
 *
 * @param string $field   the file field's name
 * @param string $title   the importer's screen title, for the sheet question
 * @param array  $options 'separator': the CSV separator the importer reads
 *                        (pg_sheet_import_csv()); 'back': where the
 *                        question's Cancel goes when the form posts to an
 *                        address that is not the screen
 * @return string
 */
function pg_sheet_import_upload($field, $title, $options = array())
{
    $answer = pg_sheet_import_answer($error);

    if ($error !== '') {
        return pg_sheet_import_error_text($error);
    }

    if ($answer !== null) {
        $path = $answer['path'];
        $name = $answer['name'];
        $kind = $answer['kind'];
        $sheet = $answer['sheet'];
    } else {
        $workbook = pg_sheet_import_uploaded_workbook($field);

        if ($workbook === null) {
            return '';
        }

        $path = $workbook['path'];
        $name = $workbook['name'];
        $kind = $workbook['kind'];
        $names = pg_sheet_names($path, $kind, $error);

        if ($error !== '') {
            return pg_sheet_import_error_text($error);
        }

        if (count($names) > 1) {
            return pg_sheet_import_ask($path, $name, $kind, $names, $title, $options);
        }

        $sheet = 0;
    }

    $csv = @tempnam(sys_get_temp_dir(), 'pgsheet');

    if (!is_string($csv) || ($csv === '')) {
        return pg_sheet_import_error_text('read');
    }

    pg_sheet_import_made($csv);

    if (!pg_sheet_import_csv($path, $kind, $sheet, $csv, $error, (string) ($options['separator'] ?? ''))) {
        return pg_sheet_import_error_text($error);
    }

    $_FILES[$field] = array(
        'name'     => $name,
        'type'     => 'text/csv',
        'tmp_name' => $csv,
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($csv),
    );

    return '';
}

/**
 * The sheet question for the importers that read a workbook themselves
 * (Products > Import, the bank statement import), in two calls around the
 * importer's own upload checks.
 *
 * pg_sheet_import_workbook_answer() comes first: when the post answers the
 * sheet question, the kept workbook is put back in $_FILES[$field] (its
 * name, tmp_name the kept file, its size) so the importer's checks see the
 * file as on the first post, and the chosen sheet is returned. Else 0.
 *
 * @param string $field
 * @param string $error '' or the refusal text (the upload has expired)
 * @return int the sheet to read, 0 for the first
 */
function pg_sheet_import_workbook_answer($field, &$error)
{
    $answer = pg_sheet_import_answer($code);
    $error = ($code !== '') ? pg_sheet_import_error_text($code) : '';

    if ($answer === null) {
        return 0;
    }

    $_FILES[$field] = array(
        'name'     => $answer['name'],
        'type'     => 'application/octet-stream',
        'tmp_name' => $answer['path'],
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($answer['path']),
    );

    return $answer['sheet'];
}

/**
 * The second call, after the importer's own upload checks: an uploaded
 * workbook with more than one sheet is kept aside and the sheet question
 * printed, ending the request. A CSV, a one-sheet workbook and the file an
 * answer put back go on. Returns the refusal text only when the file cannot
 * be kept.
 *
 * @param string $field
 * @param string $title   the importer's screen title
 * @param array  $options 'back': see pg_sheet_import_upload()
 * @return string
 */
function pg_sheet_import_workbook_ask($field, $title, $options = array())
{
    $workbook = pg_sheet_import_uploaded_workbook($field);

    if ($workbook === null) {
        return '';
    }

    $names = pg_sheet_import_question_sheets($workbook['path'], $workbook['kind']);

    return $names ? pg_sheet_import_ask($workbook['path'], $workbook['name'], $workbook['kind'], $names, $title, $options) : '';
}

/**
 * The line under an importer's file field that names the formats it takes.
 *
 * @return string HTML
 */
function pg_sheet_import_hint()
{
    return '<div class="form-text">' . h(lang('A CSV file, or an .xlsx, .ods or .xls workbook. A workbook with more than one sheet asks which sheet to import.')) . '</div>';
}

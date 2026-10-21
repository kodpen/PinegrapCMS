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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {
    $output =
    pg_page_shell([
        'title'=> lang('Import Key Codes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Import Key Codes'),
        'heading_description' => lang('Import new or overwrite existing key codes.'),
        'cancel'=>array('enable'=>'true','url'=>'view_key_codes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Key Codes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php'), array('label' => lang('Import Key Codes'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form action="import_key_codes.php" method="post" enctype="multipart/form-data">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Import CSV') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12  my-2">
                                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                            <input type="file" id="file" name="file" class="form-control w-auto"/>
                                        </div>
                                    </div>
                                </div>       
                            </div> 
                        </div> 
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_import" name="submit_import" value="Import" class="btn my-1  btn-success " ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import Key Codes') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

    print $output;
    
} else {
    validate_token_field();
    
    // if no file was uploaded
    if (empty($_FILES['file']['name'])) {
        output_error(lang('Please select a file') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Fix classic Mac (CR-only) line endings: fgetcsv() splits only on LF unless this
    // setting is on. It is deprecated since PHP 8.1 but still functional through 8.5,
    // so the E_DEPRECATED notice is suppressed instead of dropping CR support.
    @ini_set('auto_detect_line_endings', true);

    // get file handle for uploaded CSV file
    $handle = fopen($_FILES['file']['tmp_name'], "r");

    // The header line says which separator the file uses. A byte order mark
    // (Excel's "CSV UTF-8") is stepped over before the cells are read: left
    // in front of a quoted heading it keeps the quotes in the name.
    $header_line = (string) fgets($handle);
    $separator = pg_key_code_import_separator($header_line);
    fseek($handle, (substr($header_line, 0, 3) === "\xEF\xBB\xBF") ? 3 : 0);

    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, $separator);

    // if file is empty
    if (!$columns) {
        output_error(lang('The file was empty') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // cell index => key_codes column, for the columns the import reads
    $column_names = array();

    foreach ($columns as $key => $value) {
        $column = pg_key_code_import_column($value);

        if (($column !== '') && !in_array($column, $column_names, true)) {
            $column_names[$key] = $column;
        }
    }

    // if key code column could not be found, output error
    if (!in_array('code', $column_names, true)) {
        output_error(lang('A key code column could not be found') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $summary = array('added' => 0, 'changed' => 0, 'unchanged' => 0);
    $problems = array();
    $offer_found = array();
    $next_line = 2;

    // loops through all rows of data in CSV file
    while (($row = fgetcsv($handle, 100000, $separator)) !== false) {
        // The file line the row starts on: a quoted cell can hold line
        // breaks, so a row can take more than one line.
        $line = $next_line;
        $next_line += 1 + preg_match_all('/\r\n?|\n/', implode('', (array) $row));

        $read = pg_key_code_import_row($row, $column_names, DATE_FORMAT);
        $values = $read['values'];

        // A row without a key code (a blank line) is passed over.
        if ($values['code'] === '') {
            continue;
        }

        $problem = $read['error'];

        if (($problem === '') && isset($values['offer_code'])) {
            if (!isset($offer_found[$values['offer_code']])) {
                $offer_found[$values['offer_code']] = (db_value("SELECT COUNT(*) FROM offers WHERE code = '" . e($values['offer_code']) . "'") > 0);
            }

            if (!$offer_found[$values['offer_code']]) {
                $problem = lang('Sorry, we cannot find an offer for the offer code that you entered.') . ' (' . $values['offer_code'] . ')';
            }
        }

        // The rows already there with this code (the column is not unique).
        $existing = ($problem === '')
            ? db_items("SELECT offer_code, enabled, expiration_date, notes, single_use, report FROM key_codes WHERE code = '" . e($values['code']) . "'")
            : array();

        if (($problem === '') && !$existing && !isset($values['offer_code'])) {
            $problem = lang('Offer Code is required.');
        }

        if ($problem !== '') {
            $problems[] = lang(array('string' => 'Line {var:1}: {var:2}', 'vars' => array($line, $values['code'] . ' — ' . $problem)));
            continue;
        }

        // A code can have one row per offer (the affiliate flows add them so).
        // When the file's offer code matches one of those rows, only that row
        // is compared and updated, so each line of an export changes only its
        // own row instead of stamping its offer code on the code's other rows.
        $where_offer = '';

        if ($existing && isset($values['offer_code'])) {
            $same_offer = array();

            foreach ($existing as $current) {
                if ((string) $current['offer_code'] === (string) $values['offer_code']) {
                    $same_offer[] = $current;
                }
            }

            if ($same_offer) {
                $existing = $same_offer;
                $where_offer = " AND offer_code = '" . e($values['offer_code']) . "'";
            }
        }

        // A new code: what the file does not give takes the default, and a
        // code is enabled unless the file says otherwise.
        if (!$existing) {
            $values += array('enabled' => 1, 'expiration_date' => '0000-00-00', 'single_use' => 0, 'report' => 'key_code', 'notes' => '');

            db("INSERT INTO key_codes (code, offer_code, enabled, expiration_date, single_use, report, notes, user, timestamp)
                VALUES (
                    '" . e($values['code']) . "',
                    '" . e($values['offer_code']) . "',
                    '" . (int) $values['enabled'] . "',
                    '" . e($values['expiration_date']) . "',
                    '" . (int) $values['single_use'] . "',
                    '" . e($values['report']) . "',
                    '" . e($values['notes']) . "',
                    '" . (int) $user['id'] . "',
                    UNIX_TIMESTAMP())");

            $summary['added']++;
            continue;
        }

        // A code already there changes only in the columns the file gives,
        // and only when one of them differs: a used single-use code (enabled
        // 0) stays used unless the file's enabled column opens it again.
        $sets = array();

        foreach ($values as $column => $value) {
            if ($column === 'code') {
                continue;
            }

            foreach ($existing as $current) {
                if ((string) $current[$column] !== (string) $value) {
                    $sets[$column] = $column . " = '" . e((string) $value) . "'";
                    break;
                }
            }
        }

        if (!$sets) {
            $summary['unchanged']++;
            continue;
        }

        db("UPDATE key_codes
            SET " . implode(', ', $sets) . ",
                user = '" . (int) $user['id'] . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE code = '" . e($values['code']) . "'" . $where_offer);

        $summary['changed']++;
    }
    
    fclose($handle);

    $message = lang(array(
        'string' => 'Key codes imported: {var:1} added, {var:2} changed, {var:3} unchanged, {var:4} line(s) skipped.',
        'vars'   => array($summary['added'], $summary['changed'], $summary['unchanged'], count($problems)),
    ));

    log_activity($message, $_SESSION['sessionusername']);

    $liveform_view_key_codes = new liveform('view_key_codes');
    $liveform_view_key_codes->add_notice(h($message));

    // The first lines that could not be used, each with its reason.
    if ($problems) {
        $shown = array_map('h', array_slice($problems, 0, 20));

        if (count($problems) > 20) {
            $shown[] = h(lang(array('string' => 'and {var:1} more line(s) that could not be used.', 'vars' => array(count($problems) - 20))));
        }

        $liveform_view_key_codes->add_warning(implode('<br>', $shown));
    }

    // forward user to view key codes screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_key_codes.php');
}

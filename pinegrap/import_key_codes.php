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
        'cancel'=>array('enable'=>'true','url'=>'view_key_codes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Key Codes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php'), array('label' => lang('Import Key Codes'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Import new or overwrite existing key codes.') . '" title="' . lang('Import Key Codes') . '">[' . lang('Key Code') . ']</h2>
                    </div>
                </div>
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
                                <button type="submit" id="submit_import" name="submit_import" value="Import" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Importing') ) . '" ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import Key Codes') ) . '</span></button>
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
    if (!$_FILES['file']['name']) {
        output_error(lang('Please select a file') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Fix Mac line-ending issue.
    ini_set('auto_detect_line_endings', true);

    // get file handle for uploaded CSV file
    $handle = fopen($_FILES['file']['tmp_name'], "r");
    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, ",");

    // if file is empty
    if (!$columns) {
        output_error(lang('The file was empty') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    
    // create array with column field names
    foreach ($columns as $key => $value) {
        $column_names[] = convert_column_name($value);
    }

    // Assume that enabled column does not exist, until we find out otherwise.
    // We use this later to determine whether we need to enable by default,
    // in case enabled column was not included.
    $enabled_column_exists = false;

    // foreach column field name
    foreach ($column_names as $key => $value) {
        // if the column is invalid, remove from column list
        if ($value === false) {
            unset($column_names[$key]);
        }

        // if column is key code, then store key location for key code
        if ($value == 'code') {
            $key_code_key = $key;

        } else if ($value == 'enabled') {
            $enabled_column_exists = true;
        }
    }
    
    // if key code column could not be found, output error
    if (isset($key_code_key) == false) {
        output_error(lang('A key code column could not be found') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // build list of column names for database query
    foreach ($column_names as $key => $value) {
        $column_list .= "$value, ";
    }
    
    $column_list .= 'user, timestamp';

    // If an enabled column does not exist in the CSV file,
    // then add column so key codes are enabled by default.
    if (!$enabled_column_exists) {
        $column_list .= ', enabled';
    }

    $imported_key_codes = 0;

    // loops through all rows of data in CSV file
    while ($row = fgetcsv($handle, 100000, ",")) {
        $key_code = $row[$key_code_key];
        
        // if there is a key code
        if ($key_code) {
            // delete key code if key code already exists
            $query = "DELETE FROM key_codes WHERE code = '" . escape($key_code) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $data_list = '';            
            
            // foreach field
            foreach ($column_names as $key => $value) {
                // create value list
                $value = escape($row[$key]);
                $data_list .= "'$value', ";
            }

            $data_list .= "'$user[id]', UNIX_TIMESTAMP()";

            // If an enabled column does not exist in the CSV file,
            // then add column so key codes are enabled by default.
            if (!$enabled_column_exists) {
                $data_list .= ", '1'";
            }
            
            // insert row of data into database
            $query = "INSERT INTO key_codes ($column_list)
                     VALUES ($data_list)";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $imported_key_codes++;
        }
    }
    
    fclose($handle);

    log_activity(lang(array('string'=>'{var:1} key codes were imported','vars'=>$imported_key_codes)), $_SESSION['sessionusername']);

    // forward user to view key codes screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_key_codes.php');
}

function convert_column_name($column_name)
{
    // convert column name to lowercase
    $column_name = mb_strtolower($column_name);
    // remove spaces from column name
    $column_name = str_replace(' ', '', $column_name);
    // remove underscores from column name
    $column_name = str_replace('_', '', $column_name);
    // remove dashes from column name
    $column_name = str_replace('-', '', $column_name);

    switch ($column_name) {
        case 'keycode':
            return('code');
            break;

        case 'offercode':
            return('offer_code');
            break;

        case 'enabled':
            return('enabled');
            break;

        case 'expirationdate':
            return('expiration_date');
            break;

        case 'notes':
            return('notes');
            break;

        case 'singleuse':
            return('single_use');
            break;

        case 'report':
            return('report');
            break;
    }

    return false;
}
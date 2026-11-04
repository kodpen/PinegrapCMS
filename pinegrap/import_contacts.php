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

// A file over post_max_size empties the whole request; say so instead of
// drawing the form again as if nothing had been sent.
pg_upload_refuse_discarded_body();
$user = validate_user();
validate_contacts_access($user);

include_once('liveform.class.php');

if (!$_POST) {
    // get all contact groups
    $query =
        "SELECT
           id,
           name
        FROM contact_groups
        WHERE " . pg_audience_hand_sql() . "
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contact_groups = array();
    
    // loop through all contact groups
    while ($row = mysqli_fetch_assoc($result)) {
        // if user has access to contact group, then include this contact group
        if (validate_contact_group_access($user, $row['id']) == true) {
            $contact_groups[] = $row;
        }
    }
    
    $output_contact_groups = '';
    $contact_group_counter = 1;
    $number_of_contact_groups_per_cell = ceil(count($contact_groups)/3);
    
    // loop through and output the contact groups
    foreach ($contact_groups as $key => $contact_group) {
        
        $output_contact_groups .= '
        <div class="col-12 md:col-4 my-3">
            <div class="form-field">
                <div class="switch"><input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class=""/></div>
                <label class="" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
            </div>
        </div>';
       
    }
    
    // One Summary row of the status column: a label (plain text), its value
    // (HTML) and, unless it is the last row, the line under it.
    $status_row = function ($label, $value, $last) {
        return '
                                        <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                            <dt class="fw-normal">' . h($label) . '</dt>
                                            <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                        </div>';
    };

    print
    pg_page_shell(
        array(
            'title'=> lang('Import Contacts'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Import Contacts'),
            'heading_description' => lang('Import contacts into any of my contact groups.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php')),
        
            'breadcrumb' => array(array('label' => lang('Contacts'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php')), array('label' => lang('Import Contacts'))),
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <form name="form" action="import_contacts.php" method="post" class="product_form" enctype="multipart/form-data">
                ' . get_token_field() . '
                <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                <!-- Edit screen layout of product_builder.php: the form column, the sticky status column (Summary), the sticky save bar. -->
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Import CSV') . '
                            </h2>
                            <div class="card-body pt-0">
                                <div class="my-3">
                                    <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                    <input name="file" id="file" type="file" class="form-control" accept=".csv,.txt,text/csv" aria-describedby="file_help" required />
                                    <div id="file_help" class="form-text">' . h(lang('The first row of the file names the columns. A file exported from the contacts list can be imported as it is.')) . ' ' . h(lang('Recognised headings include First Name, Last Name, Email, Company, Job Title, Mobile Phone, Business Phone, Address, City, Zip Code, Country, Description and the column names of the export.')) . '</div>
                                    ' . pg_upload_limit_hint() . '
                                </div>
                                <div class="my-5" role="radiogroup" aria-labelledby="import_mode_label">
                                    <div class="form-label" id="import_mode_label">' . lang('Import Mode') . '</div>
                                    <div class="form-field">
                                        <input value="import_all_contacts" class="radio" type="radio" id="import_all_contacts" name="import_mode" checked>
                                        <label for="import_all_contacts">' . lang('Import all contacts') . '</label>
                                    </div>
                                    <div class="form-field">
                                        <input value="only_import_unique_contacts" class="radio" type="radio" id="only_import_unique_contacts" name="import_mode">
                                        <label for="only_import_unique_contacts">' . lang('Only import contacts with unique email addresses') . '</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Select Contact Groups to Import into') . '
                            </h2>
                            <div class="card-body pt-0">
                                <div class="row">
                                    ' . $output_contact_groups . '
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Summary') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <dl class="mb-0">'
        . $status_row(lang('Contacts'), h(number_format((int) db_value("SELECT COUNT(*) FROM contacts"))), false)
        . $status_row(lang('Contact Groups'), '<span id="contact_import_summary_groups">0</span> / ' . h(number_format(count($contact_groups))), false)
        . $status_row(lang('File'), '<span id="contact_import_summary_file">' . h(lang('None')) . '</span>', true) . '
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_import" value="Import" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">file_upload</span><span class="pg-btn-label">' . lang('Import Contacts') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
            <script>
                // The Summary names the chosen file and counts the ticked groups.
                (function () {
                    var box = document.getElementById("file");
                    var file = document.getElementById("contact_import_summary_file");
                    var groups = document.getElementById("contact_import_summary_groups");
                    var none = file.textContent;
                    box.addEventListener("change", function () {
                        file.textContent = (box.files && box.files.length) ? box.files[0].name : none;
                    });
                    var ticks = document.querySelectorAll("input[name^=\\"contact_group_\\"]");
                    var count = function () {
                        var n = 0;
                        for (var i = 0; i < ticks.length; i++) {
                            if (ticks[i].checked) {
                                n++;
                            }
                        }
                        groups.textContent = n;
                    };
                    for (var i = 0; i < ticks.length; i++) {
                        ticks[i].addEventListener("change", count);
                    }
                    count();
                })();
            </script>
        </div>
    </div>
</main>' .
    output_footer();

} else {
    validate_token_field();
    
    // if no file was uploaded
    if (!$_FILES['file']['name']) {
        output_error(lang('Please select a file.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $upload_limits = pg_upload_limits();
    $upload_refusal = pg_upload_file_refusal($_FILES['file'], $upload_limits['file_max']);

    if ($upload_refusal !== '') {
        output_error(h($upload_refusal) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // get file handle for uploaded CSV file
    $handle = pg_csv_open($_FILES['file']['tmp_name']);
    // The header line says which separator the file uses (Excel in Turkish
    // writes ';').
    $separator = pg_csv_read_separator($handle);
    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, $separator, '"', '\\');

    // if file is empty
    if (!$columns) {
        output_error(lang('The file was empty.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // create array with column field names
    foreach ($columns as $key => $value) {
        $column_names[] = convert_column_name($value);
    }

    // Headings the import does not know, named in the result.
    $unknown_headings = array();

    foreach ($column_names as $key => $value) {
        if ($value === FALSE) {
            $unknown_headings[] = $columns[$key];
        }
    }

    // One file column per contacts column: a later heading for the same
    // field is left out and named in the result.
    $unique_columns = pg_import_unique_columns($column_names);
    $column_names = $unique_columns['columns'];
    $repeated_headings = array();

    foreach ($unique_columns['repeated'] as $key) {
        $repeated_headings[] = $columns[$key];
    }

    foreach ($column_names as $key => $value) {
        // if column is email_address, then store key location for email_address
        if ($value == 'email_address') {
            $email_address_key = $key;
        }
    }

    // The text columns' lengths, so a longer value is cut with a word in the
    // result instead of silently by the non-strict session.
    $column_lengths = pg_import_column_lengths(db_items("SHOW COLUMNS FROM contacts"));

    // Rows changed or skipped, as array(line, reason).
    $problems = array();

    // The insert's values for one row, its problems added to $problems.
    $contact_values = function ($row, $line) use ($column_names, $columns, $column_lengths, &$problems) {
        $data_list = '';

        foreach ($column_names as $key => $column) {
            $read = pg_import_contact_value($column, $row[$key] ?? '', $column_lengths[$column] ?? 0, trim((string) $columns[$key]));

            if ($read['problem'] !== '') {
                $problems[] = array($line, $read['problem']);
            }

            $data_list .= "'" . escape($read['value']) . "', ";
        }

        return $data_list;
    };

    // Without one known column every row would become an empty contact.
    if (!$column_names) {
        output_error(h(lang(array('string' => 'None of the columns in the file could be used, so nothing was imported. Headings found: {var:1}', 'vars' => array(pg_csv_headings_text($columns))))) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // build list of column names for database query
    $column_list = '';
    foreach ($column_names as $key => $value) {
        $column_list .= "$value, ";
    }
    $column_list .= 'user, timestamp';
    
    // get all contact groups
    // a group filled by rules is not offered and never imported into
    $query = "SELECT id FROM contact_groups WHERE " . pg_audience_hand_sql();
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contact_groups = array();
    
    // loop through all contact groups
    while ($row = mysqli_fetch_assoc($result)) {
        // if contact group was checked and user has access to contact group, add contact group to array
        if ((($_POST['contact_group_' . $row['id']] ?? '') == 1) && (validate_contact_group_access($user, $row['id']) == true)) {
            $contact_groups[] = $row;
        }
    }
    
    // if user has a user role and there are no contact groups to import contact into, then output error
    if (($user['role'] == 3) && (!$contact_groups)) {
        output_error(lang('Please select at least one contact group for the contacts to be imported into') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $imported_contacts = 0;

    // Contacts created and not yet in the audit trail: written in batches, so
    // a large file neither reads every new row at once nor one at a time.
    $audit_created = array();

    $next_line = 2;

    // loops through all rows of data in CSV file
    while ($row = fgetcsv($handle, 100000, $separator, '"', '\\')) {
        // The file line the row starts on.
        $line = $next_line;
        $next_line += pg_csv_row_lines($row);

        // Assume that this line does not have a value,
        // until we find out otherwise.
        $line_has_value = false;

        // Loop through the columns for this line,
        // in order to determine if at least one has a value.
        foreach ($row as $value) {
            // If the value is not blank, then this line has at least one value,
            // so remember that and break out of loop.
            if (trim($value) != '') {
                $line_has_value = true;
                break;
            }
        }

        // If this line has at least one value, then continue to process it.
        if ($line_has_value == true) {
            $contact_id = 0;
            
            switch ($_POST['import_mode']) {
                case 'import_all_contacts':
                    // if e-mail address data was supplied in CSV file and e-mail address is invalid, clear e-mail address value
                    if ((isset($email_address_key) == true) && (validate_email_address(trim($row[$email_address_key] ?? '')) == false)) {
                        if (trim($row[$email_address_key] ?? '') != '') {
                            $problems[] = array($line, lang(array('string' => 'The e-mail address "{var:1}" is not valid, so the contact was imported without it.', 'vars' => array(trim($row[$email_address_key])))));
                        }

                        $row[$email_address_key] = '';
                    }
                    
                    $data_list = $contact_values($row, $line) . "'$user[id]', UNIX_TIMESTAMP()";
                    
                    // insert row of data into database
                    $query = "INSERT INTO contacts ($column_list) VALUES ($data_list)";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    $contact_id = mysqli_insert_id(db::$con);
                    
                    // if e-mail address exists
                    if (isset($email_address_key) && (trim($row[$email_address_key] ?? '') != '')) {
                        // check to see if contact should be opted out
                        $query = "SELECT id FROM contacts WHERE (opt_in = 0) AND (email_address = ?) AND (id != ?)";
                        $result = db_q($query, array((string) trim($row[$email_address_key]), (string) $contact_id)) or output_error('Query failed.');
                        
                        // if a contact was found
                        if (mysqli_num_rows($result) > 0) {
                            $query = "UPDATE contacts SET opt_in = 0 WHERE id = ?";
                            $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');
                        }
                    }

                    $audit_created[] = $contact_id;
                    
                    $imported_contacts++;
                    
                    break;
                    
                case 'only_import_unique_contacts':
                    // Without an e-mail address column there is nothing to compare, so every contact counts as unique.
                    $unique = true;
                    
                    // if e-mail address data was supplied in CSV file
                    if (isset($email_address_key)) {
                        // if e-mail address is not valid, set e-mail address to empty
                        if (validate_email_address(trim($row[$email_address_key] ?? '')) == false) {
                            if (trim($row[$email_address_key] ?? '') != '') {
                                $problems[] = array($line, lang(array('string' => 'The e-mail address "{var:1}" is not valid, so the contact was imported without it.', 'vars' => array(trim($row[$email_address_key])))));
                            }

                            $row[$email_address_key] = '';
                        }

                        // if e-mail address is not empty
                        if (trim($row[$email_address_key]) != '') {
                            // query database to determine if e-mail address is already in use
                            $query = "SELECT id FROM contacts WHERE email_address = ?";
                            $result = db_q($query, array((string) trim($row[$email_address_key]))) or output_error('Query failed.');
                            
                            // if a contact was not found
                            if (mysqli_num_rows($result) == 0) {
                                $unique = true;
                            } else {
                                $unique = false;
                                $problems[] = array($line, lang(array('string' => 'A contact with the e-mail address "{var:1}" already exists, so the row was not imported.', 'vars' => array(trim($row[$email_address_key])))));
                            }
                            
                        // else e-mail address is empty
                        } else {
                            $unique = true;
                        }
                    }

                    // if contact is unique
                    if ($unique == true) {
                        $data_list = $contact_values($row, $line) . "'$user[id]', UNIX_TIMESTAMP()";
                        
                        // insert row of data into database
                        $query = "INSERT INTO contacts ($column_list) VALUES ($data_list)";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
                        $contact_id = mysqli_insert_id(db::$con);

                        $audit_created[] = $contact_id;

                        $imported_contacts++;
                    }
                    
                    break;
            }
            
            // if contact was created, assign contact to contact groups
            if ($contact_id) {
                // loop through all checked contact groups
                foreach ($contact_groups as $contact_group) {
                    // assign contact to this contact group
                    $query =
                        "INSERT INTO contacts_contact_groups_xref (
                            contact_id,
                            contact_group_id)
                        VALUES (
                            ?,
                            ?)";
                    $result = db_q($query, array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
                }
            }

            if (count($audit_created) >= 200) {
                pg_audit_record_many('create', 'contacts', pg_audit_created_rows($audit_created));
                $audit_created = array();
            }
        }
    }
    fclose($handle);

    pg_audit_record_many('create', 'contacts', pg_audit_created_rows($audit_created));
    
    log_activity(lang(array('string'=>'{var:1} contacts were imported','vars'=>pg_format_number($imported_contacts, 0))), $_SESSION['sessionusername']);
    
    $liveform_view_contacts = new liveform('view_contacts');
    $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contacts were imported','vars'=>pg_format_number($imported_contacts, 0))) );

    if (pg_csv_headings_text($unknown_headings) !== '') {
        $liveform_view_contacts->add_warning(h(lang(array('string' => 'These columns were not recognised and were left out: {var:1}', 'vars' => array(pg_csv_headings_text($unknown_headings))))));
    }

    if (pg_csv_headings_text($repeated_headings) !== '') {
        $liveform_view_contacts->add_warning(h(lang(array('string' => 'These columns fill the same field as an earlier column and were left out: {var:1}', 'vars' => array(pg_csv_headings_text($repeated_headings))))));
    }

    // The first rows changed or skipped, each with its line and reason.
    if ($problems) {
        $liveform_view_contacts->add_warning(implode('<br>', array_map('h', pg_csv_problem_list($problems))));
    }
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
    }
}

function convert_column_name($column_name)
{
    // lowercase, with the Turkish I letters as one (İl, IL and ıl are "il"),
    // so a heading typed in Turkish is matched as written below
    $column_name = pg_list_search_fold(trim((string) $column_name));
    // remove spaces from column name
    $column_name = str_replace(' ', '', $column_name);
    // remove underscores from column name
    $column_name = str_replace('_', '', $column_name);
    // remove dashes from column name
    $column_name = str_replace('-', '', $column_name);

    // Turkish headings (folded as above: ı is i, spaces, dashes and
    // underscores gone), the panel's own labels among them.
    $turkish = array(
        'hitap' => 'salutation', 'selamlama' => 'salutation',
        'ad' => 'first_name', 'adi' => 'first_name', 'isim' => 'first_name',
        'soyad' => 'last_name', 'soyadi' => 'last_name', 'soyisim' => 'last_name',
        'sonek' => 'suffix',
        'takmaad' => 'nickname', 'lakap' => 'nickname',
        'şirket' => 'company', 'şirketadi' => 'company', 'firma' => 'company', 'firmaadi' => 'company', 'kurum' => 'company',
        'unvan' => 'title', 'ünvan' => 'title', 'görev' => 'title', 'pozisyon' => 'title',
        'departman' => 'department', 'bölüm' => 'department',
        'ofis' => 'office_location', 'ofiskonumu' => 'office_location',
        'adres' => 'business_address_1', 'adres1' => 'business_address_1', 'işadresi' => 'business_address_1', 'işletmeadresi1' => 'business_address_1',
        'adres2' => 'business_address_2', 'işadresi2' => 'business_address_2', 'işletmeadresi2' => 'business_address_2',
        'şehir' => 'business_city', 'ilçe' => 'business_city', 'işşehir' => 'business_city',
        'il' => 'business_state', 'eyalet/il' => 'business_state',
        'ülke' => 'business_country',
        'postakodu' => 'business_zip_code', 'pk' => 'business_zip_code',
        'telefon' => 'business_phone', 'tel' => 'business_phone', 'iştelefonu' => 'business_phone', 'işletmetelefonu' => 'business_phone',
        'faks' => 'business_fax', 'fax' => 'business_fax', 'işfaksi' => 'business_fax', 'işletmefaksi' => 'business_fax',
        'evadresi' => 'home_address_1', 'evadresi1' => 'home_address_1', 'evadresi2' => 'home_address_2',
        'evşehir' => 'home_city', 'evşehri' => 'home_city', 'evil' => 'home_state', 'evülke' => 'home_country', 'evpostakodu' => 'home_zip_code',
        'evtelefonu' => 'home_phone', 'evfaksi' => 'home_fax',
        'cep' => 'mobile_phone', 'ceptelefonu' => 'mobile_phone', 'gsm' => 'mobile_phone', 'mobiltelefon' => 'mobile_phone',
        'eposta' => 'email_address', 'epostaadresi' => 'email_address', 'mail' => 'email_address', 'mailadresi' => 'email_address',
        'internetsitesi' => 'website', 'websitesi' => 'website', 'site' => 'website',
        'kaynak' => 'lead_source', 'potansiyelmüşterikaynaği' => 'lead_source',
        'iletiizni' => 'opt_in', 'izin' => 'opt_in',
        'açiklama' => 'description', 'not' => 'description', 'notlar' => 'description',
        'üyeno' => 'member_id', 'üyenumarasi' => 'member_id', 'üyekimliği' => 'member_id',
        'bitiştarihi' => 'expiration_date', 'sonkullanmatarihi' => 'expiration_date', 'üyelikbitiştarihi' => 'expiration_date',
    );

    if (isset($turkish[$column_name])) {
        return $turkish[$column_name];
    }

    switch ($column_name) {
        case 'salutation':
            return('salutation');
            break;

        case 'firstname':
        case 'first':
        case 'name':
            return('first_name');
            break;

        case 'lastname':
        case 'last':
            return('last_name');
            break;

        case 'suffix':
            return('suffix');
            break;

        case 'nickname':
        case 'alias':
            return('nickname');
            break;

        case 'company':
        case 'organization':
            return('company');
            break;

        case 'title':
        case 'jobtitle':
        case 'position':
            return('title');
            break;

        case 'department':
            return('department');
            break;

        case 'officelocation':
        case 'office':
        case 'location':
            return('office_location');
            break;

        case 'businessaddress1':
        case 'businessaddress':
        case 'businessstreet1':
        case 'businessstreet':
        case 'address1':
        case 'address':
        case 'street':
            return('business_address_1');
            break;

        case 'businessaddress2':
        case 'businessstreet2':
        case 'address2':
        case 'street2':
            return('business_address_2');
            break;

        case 'businesscity':
        case 'city':
            return('business_city');
            break;

        case 'businessstate':
        case 'state':
            return('business_state');
            break;

        case 'businesscountry':
        case 'businesscountry/region':
        case 'country':
            return('business_country');
            break;

        case 'businesszipcode':
        case 'businesspostalcode':
        case 'zipcode':
        case 'zip':
        case 'postalcode':
        case 'postal':
            return('business_zip_code');
            break;

        case 'businessphone':
        case 'businessphonenumber':
        case 'phone':
        case 'phonenumber':
            return('business_phone');
            break;

        case 'businessfax':
        case 'businessfaxnumber':
        case 'fax':
        case 'faxnumber':
            return('business_fax');
            break;

        case 'homeaddress1':
        case 'homeaddress':
        case 'homestreet1':
        case 'homestreet':
            return('home_address_1');
            break;

        case 'homeaddress2':
        case 'homestreet2':
            return('home_address_2');
            break;

        case 'homecity':
            return('home_city');
            break;

        case 'homestate':
            return('home_state');
            break;

        case 'homecountry':
        case 'homecountry/region':
            return('home_country');
            break;

        case 'homezipcode':
        case 'homezip':
        case 'homepostalcode':
        case 'homepostal':
            return('home_zip_code');
            break;

        case 'homephone':
        case 'homephonenumber':
            return('home_phone');
            break;

        case 'homefax':
        case 'homefaxnumber':
            return('home_fax');
            break;

        case 'mobilephone':
        case 'mobilephonenumber':
        case 'mobile':
        case 'cellphone':
        case 'cellphonenumber':
        case 'cell':
            return('mobile_phone');
            break;

        case 'emailaddress':
        case 'email':
            return('email_address');
            break;

        case 'website':
        case 'web':
        case 'site':
        case 'webpage':
        case 'url':
        case 'businesswebpage':
            return('website');
            break;

        case 'leadsource':
        case 'source':
            return('lead_source');
            break;

        case 'optin':
            return('opt_in');
            break;

        case 'description':
        case 'notes':
        case 'note':
        case 'comments':
        case 'comment':
            return('description');
            break;

        case 'memberid':
            return('member_id');
            break;

        case 'expirationdate':
            return('expiration_date');
            break;
            
        case 'affiliateapproved':
            return('affiliate_approved');
            break;
            
        case 'affiliatename':
            return('affiliate_name');
            break;

        case 'affiliatecode':
            return('affiliate_code');
            break;
            
        case 'affiliatecommissionrate':
            return('affiliate_commission_rate');
            break;
    }
    return FALSE;
}
?>
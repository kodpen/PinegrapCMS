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
validate_contacts_access($user);

include_once('liveform.class.php');

if (!$_POST) {
    // get all contact groups
    $query =
        "SELECT
           id,
           name
        FROM contact_groups
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
        <div class="col-12 col-md-4 my-2">
            <div class="form-check form-switch">
                <input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="form-check-input"/>
                <label class="form-check-label" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
            </div>
        </div>';
       
    }
    
    print
    pg_page_shell(
        array(
            'title'=> lang('Import Contacts'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Import Contacts'),
            'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
        
            'breadcrumb' => array(array('label' => lang('Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'), array('label' => lang('Import Contacts'))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Import contacts into any of my contact groups.') . '" title="' . lang('Import Contacts') . '">[' . lang('New Contacts') . ']</h2>
                    </div>
                </div>
                <form name="form" action="import_contacts.php" method="post" class="product_form" enctype="multipart/form-data">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Import CSV') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                            <input name="file" id="file" type="file" size="60" class="form-control w-auto" />
                                        </div>
                                        <div class="col-12 my-3">
                                            <label class="form-label" for="">'. lang('Import Mode') . '</label>
                                            <div class="form-check">
                                                <input value="import_all_contacts" class="form-check-input" type="radio" id="import_all_contacts" name="import_mode" checked>
                                                <label class="form-check-label" for="import_all_contacts">'. lang('Import all contacts') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="only_import_unique_contacts" class="form-check-input" type="radio" id="only_import_unique_contacts" name="import_mode">
                                                <label class="form-check-label" for="only_import_unique_contacts">'. lang('Only import contacts with unique email addresses') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>       
                            </div> 
                        </div> 
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Select Contact Groups to Import into') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_contact_groups . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_import" value="Import" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Importing') ) . '" ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import Contacts') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
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

    // Fix Mac line-ending issue.
    ini_set('auto_detect_line_endings', true);

    // get file handle for uploaded CSV file
    $handle = fopen($_FILES['file']['tmp_name'], "r");
    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, ",");

    // if file is empty
    if (!$columns) {
        output_error(lang('The file was empty.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // create array with column field names
    foreach ($columns as $key => $value) {
        $column_names[] = convert_column_name($value);
    }

    // foreach column field name
    foreach ($column_names as $key => $value) {
        // if the column is invalid, remove from column list
        if ($value === FALSE) {
            unset($column_names[$key]);
        }

        // if column is email_address, then store key location for email_address
        if ($value == 'email_address') {
            $email_address_key = $key;
        }
    }

    // build list of column names for database query
    foreach ($column_names as $key => $value) {
        $column_list .= "$value, ";
    }
    $column_list .= 'user, timestamp';
    
    // get all contact groups
    $query = "SELECT id FROM contact_groups";
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

    // loops through all rows of data in CSV file
    while ($row = fgetcsv($handle, 100000, ",")) {
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
                    if ((isset($email_address_key) == true) && (validate_email_address(trim($row[$email_address_key])) == false)) {
                        $row[$email_address_key] = '';
                    }
                    
                    $data_list = '';
                    
                    // foreach field
                    foreach ($column_names as $key => $value) {
                        // create value list
                        $value = escape(trim($row[$key]));
                        $data_list .= "'$value', ";
                    }

                    $data_list .= "'$user[id]', UNIX_TIMESTAMP()";
                    
                    // insert row of data into database
                    $query = "INSERT INTO contacts ($column_list) VALUES ($data_list)";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    $contact_id = mysqli_insert_id(db::$con);
                    
                    // if e-mail address exists
                    if (trim($row[$email_address_key]) != '') {
                        // check to see if contact should be opted out
                        $query = "SELECT id FROM contacts WHERE (opt_in = 0) AND (email_address = '" . escape(trim($row[$email_address_key])) . "') AND (id != '$contact_id')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
                        // if a contact was found
                        if (mysqli_num_rows($result) > 0) {
                            $query = "UPDATE contacts SET opt_in = 0 WHERE id = '$contact_id'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        }
                    }
                    
                    $imported_contacts++;
                    
                    break;
                    
                case 'only_import_unique_contacts':
                    // if e-mail address data was supplied in CSV file
                    if (isset($email_address_key)) {
                        // if e-mail address is not valid, set e-mail address to empty
                        if (validate_email_address(trim($row[$email_address_key])) == false) {
                            $row[$email_address_key] = '';
                        }

                        // if e-mail address is not empty
                        if (trim($row[$email_address_key]) != '') {
                            // query database to determine if e-mail address is already in use
                            $query = "SELECT id FROM contacts WHERE email_address = '" . escape(trim($row[$email_address_key])) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            
                            // if a contact was not found
                            if (mysqli_num_rows($result) == 0) {
                                $unique = true;
                            } else {
                                $unique = false;
                            }
                            
                        // else e-mail address is empty
                        } else {
                            $unique = true;
                        }
                    }

                    // if contact is unique
                    if ($unique == true) {
                        $data_list = '';
                        
                        // foreach field
                        foreach ($column_names as $key => $value) {
                            // create value list
                            $value = escape(trim($row[$key]));
                            $data_list .= "'$value', ";
                        }

                        $data_list .= "'$user[id]', UNIX_TIMESTAMP()";
                        
                        // insert row of data into database
                        $query = "INSERT INTO contacts ($column_list) VALUES ($data_list)";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
                        $contact_id = mysqli_insert_id(db::$con);

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
                            '" . $contact_id . "',
                            '" . $contact_group['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
        }
    }
    fclose($handle);
    
    log_activity(lang(array('string'=>'{var:1} contacts were imported','vars'=>number_format($imported_contacts))), $_SESSION['sessionusername']);
    
    $liveform_view_contacts = new liveform('view_contacts');
    $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contacts were imported','vars'=>number_format($imported_contacts))) );
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
    }
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
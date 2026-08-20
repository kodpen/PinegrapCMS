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

// if user does not have access to contact, then output error
if (validate_contact_access($user, $_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit contact because user does not have access to a contact group that the contact is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

if (!$_POST) {
    // if ecommerce is on, then output orders button to show orders for contact
    if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $output_orders_button = '
            <nav id="button_bar" class="navigation " aria-label="Button Bar">
                <div class=" btn-group btn-group-sm flex-wrap">
                    <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Loading') . '" href="view_orders_for_contact.php?id=' . h(urlencode($_REQUEST['id'])) . '"><span class="material-icons me-1">storefront</span>' . lang('View Orders') . '</a>
                </div>
            </nav>';
    }

    $query =
        "SELECT
            contacts.image,
            contacts.file_id,
            contacts.salutation,
            contacts.first_name,
            contacts.last_name,
            contacts.suffix,
            contacts.nickname,
            contacts.company,
            contacts.title,
            contacts.department,
            contacts.office_location,
            contacts.business_address_1,
            contacts.business_address_2,
            contacts.business_city,
            contacts.business_state,
            contacts.business_country,
            contacts.business_zip_code,
            contacts.business_phone,
            contacts.business_fax,
            contacts.home_address_1,
            contacts.home_address_2,
            contacts.home_city,
            contacts.home_state,
            contacts.home_country,
            contacts.home_zip_code,
            contacts.home_phone,
            contacts.home_fax,
            contacts.mobile_phone,
            contacts.email_address,
            contacts.website,
            contacts.lead_source,
            contacts.opt_in,
            contacts.description,
            contacts.member_id,
            contacts.expiration_date,
            contacts.affiliate_approved,
            contacts.affiliate_name,
            contacts.affiliate_code,
            contacts.affiliate_commission_rate,
            contacts.tax_number,
            contacts.tax_office,
            contacts.parasut_contact_id,
            user.user_id,
            user.user_username AS username,
            user.user_role
        FROM contacts
        LEFT JOIN user ON contacts.id = user.user_contact
        WHERE contacts.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $image = '';
    if($row['file_id'] == 0){
        if($row['image']){
            $image = '
            <div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link link-danger bi bi-x-lg p-0" title="remove" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                    <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' . h($row['image']) . '">
                        <input type="hidden" name="image_url" id="image_url" value="' . e($row['image']) . '">
                        <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($row['image']) . '" />
                    </div>
                </div>
            </div>';
        }else{
            $image = '
            <div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link link-danger bi bi-x-lg p-0 opacity-0"></button></div>
                    <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' . h($row['image']) . '">
                        <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="assets/images/person1.png" />
                    </div>
                </div>
            </div>';
        }
       
    }else{
        $query = 
        "SELECT 
            files.name
        FROM files 
        WHERE files.id = '" . escape($row['file_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $file = mysqli_fetch_array($result);
        $file_name = $file['name'];

        $image = '
        <div class="item col">
            <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link link-danger bi bi-x-lg p-0" title="remove" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' . h($file_name) . '">
                    <input type="hidden" name="file_id" id="file_id" value="' . $row['file_id'] . '"/>
                    <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($file_name) . '" />
                </div>
            </div>
        </div>';


    }
    $salutation =           h($row['salutation']);
    $first_name =           h($row['first_name']);
    $last_name =            h($row['last_name']);
    $suffix =               h($row['suffix']);
    $nickname =             h($row['nickname']);
    $company =              h($row['company']);
    $title =                h($row['title']);
    $department =           h($row['department']);
    $office_location =      h($row['office_location']);
    $business_address_1 =   h($row['business_address_1']);
    $business_address_2 =   h($row['business_address_2']);
    $business_city =        h($row['business_city']);
    $business_state =       h($row['business_state']);
    $business_country =     h($row['business_country']);
    $business_zip_code =    h($row['business_zip_code']);
    $business_phone =       h($row['business_phone']);
    $business_fax =         h($row['business_fax']);
    $home_address_1 =       h($row['home_address_1']);
    $home_address_2 =       h($row['home_address_2']);
    $home_city =            h($row['home_city']);
    $home_state =           h($row['home_state']);
    $home_country =         h($row['home_country']);
    $home_zip_code =        h($row['home_zip_code']);
    $home_phone =           h($row['home_phone']);
    $home_fax =             h($row['home_fax']);
    $mobile_phone =         h($row['mobile_phone']);
    $email_address =        h($row['email_address']);
    $website =              h($row['website']);
    $lead_source =          h($row['lead_source']);
    $opt_in =              $row['opt_in'];
    $description =          h($row['description']);
    $member_id =            h($row['member_id']);
    $expiration_date =      $row['expiration_date'];
    $affiliate_approved =   $row['affiliate_approved'];
    $affiliate_name =       h($row['affiliate_name']);
    $affiliate_code =       h($row['affiliate_code']);
    $affiliate_commission_rate = $row['affiliate_commission_rate'];
    $tax_number          = h($row['tax_number']          ?? '');
    $tax_office          = h($row['tax_office']          ?? '');
    $parasut_contact_id  = h($row['parasut_contact_id']  ?? '');
    $user_id = $row['user_id'];
    $username = $row['username'];
    $user_role = $row['user_role'];

    // If this contact has a user connected to it, then output username.
    if ($username != '') {
        // If the editor user is an administrator or the editor user has access to edit this user,
        // then prepare username with link.
        if ((USER_ROLE == 0) || (USER_ROLE < $user_role)) {
            $output_user_info = '
            <div class="col-12 my-2">
                <a class="btn btn-link link-secondary py-0 mb-2  users-color" href="edit_user.php?id=' . $user_id . '"><span class="material-icons me-2">account_circle</span>' . h($username) . '</a>
            </div>';

        // Otherwise the editor user does not have access to edit this user,
        // so prepare username without link.
        } else {
            $output_user_info ='
            <div class="col-12 my-2">
                <span class="py-2 mb-2 users-color" href="edit_user.php?id=' . $user_id . '"><span class="material-icons me-2">account_circle</span>' . h($username) . '</span>
            </div>';
        }

    // Otherwise this contact does not have a user connected to it, so output different content.
    } else {
        // If the user editing this contact has a manager role or above,
        // then add button to allow user to create a user for this contact.
        $output_create_user_button = '';
        if (USER_ROLE < 3) {
            $output_create_user_button = lang(array('string'=>' {var:1} connected to this contact.','vars'=>array('<a class="alert-link" href="#!" onclick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/add_user.php?contact_id=' . $_GET['id'])) . '\'">' . lang('Create an User') . '</a>') ));
        }

        $output_user_info ='
        <div class="col-12 my-2">
            <div class="alert alert-primary">
                <p class="mb-0">' . lang('This Contact does not currently have a User connected to it.') . $output_create_user_button . '</p>
            </div>
        </div>';

        
    }

    if ($expiration_date == '0000-00-00') {
        $expiration_date = '';
    }

    $expiration_date = prepare_form_data_for_output($expiration_date, 'date');
    
    // if contact is opt-in
    if ($opt_in) {
        $opt_in_checked = ' checked="checked"';
    // else contact is not opt-in
    } else {
        $opt_in_checked = '';
    }
    
    // get all contact groups
    $query =
        "SELECT
           id,
           name,
           email_subscription
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

    // determine how many contact groups need to be in each table cell
    $number_of_contact_groups_per_cell = ceil(count($contact_groups)/3);
    
    foreach ($contact_groups as $key => $contact_group) {
        // check if contact is in this contact group
        $query =
            "SELECT contact_id
            FROM contacts_contact_groups_xref
            WHERE
                (contact_id = '" . escape($_GET['id']) . "')
                AND (contact_group_id = '" . $contact_group['id'] . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if contact is in this contact group, then prepare checkbox to be checked
        if (mysqli_num_rows($result) > 0) {
            $contact_group_checked = ' checked="checked"';
            $contact_group_opt_in_cell_style = '';
        } else {
            $contact_group_checked = '';
            $contact_group_opt_in_cell_style = '; display: none';
        }
        
        // if contact group has email subscription turned on, prepare onclick to show opt-in field and prepare opt-in field
        if ($contact_group['email_subscription'] == 1) {
            // check if contact is opted-in to this contact group
            $query =
                "SELECT opt_in
                FROM opt_in
                WHERE
                    (contact_id = '" . escape($_GET['id']) . "')
                    AND (contact_group_id = '" . $contact_group['id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            // if an opt-in record was not found or opt-in is 1, then contact is opted-in
            if ((mysqli_num_rows($result) == 0) || ($row['opt_in'] == 1)) {
                $contact_group_opt_in_checked = ' checked="checked"';
                
            // else contact is opted-out
            } else {
                $contact_group_opt_in_checked = '';
            }

            $contact_group_target = ' data-bs-target="#contact_group_opt_in_cell_' . $contact_group['id'] . '"';
            $output_opt_in = '
            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="contact_group_opt_in_cell_' . $contact_group['id'] . '">
                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(45px, 0px);"></div>
                <div class="popover-body py-0">
                    <div class="row">
                        <div class="col-12 my-2">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="contact_group_opt_in_' . $contact_group['id'] . '" id="contact_group_opt_in_' . $contact_group['id'] . '" value="1"' . $contact_group_opt_in_checked . ' class="form-check-input" />
                                <label class="form-check-label" for="contact_group_opt_in_' . $contact_group['id'] . '"> ' . lang('Opt-In Status') . '</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        } else {
            $contact_group_target = '';
            $output_opt_in = '';
        }
        
        $output_contact_groups .=
        '<div class="col-12 col-md-4 my-2">
            <div class="form-check form-switch">
                <input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="collapse-switcher form-check-input"' . $contact_group_checked . $contact_group_target . '/>
                <label class="form-check-label" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
            </div>
            ' . $output_opt_in . '
        </div>';

    }
    
    // if the affiliate program is enabled, prepare affiliate program output
    if (AFFILIATE_PROGRAM == true) {
        if ($affiliate_approved) {
            $affiliate_approved_checked = ' checked';
        } else {
            $affiliate_approved_checked = '';
        }
        
        // clear affiliate commission rate if it is 0
        if ($affiliate_commission_rate == 0) {
            $affiliate_commission_rate = '';
        }
        
        $output_affiliate =
        '<div class="col-12">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Affiliate Information') . '
                </div>
                <div class="card-body">
                    <div class="row">
                    
                        <div class="col-12 my-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="affiliate_approved" name="affiliate_approved" value="1"' . $affiliate_approved_checked . '>
                                <label class="form-check-label" for="affiliate_approved">' . lang('Approved') . '</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-3 my-2">
                            <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                            <input value="' . $affiliate_name . '" type="text" name="affiliate_name" id="affiliate_name" class="form-control" maxlength="100" />
                        </div>
                        <div class="col-12 col-md-6 my-2">
                            <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                            <input value="' . $affiliate_code . '" type="text" name="affiliate_code" id="affiliate_code" class="form-control" maxlength="100" />
                            <div class="form-text text-end">' . lang('leave blank to automatically generate code') . '</div>
                        </div>
                        <div class="col-12 col-md-3 my-2">
                            <label for="affiliate_commission_rate" class="form-label">' . lang('Commission Rate') . '</label>
                            <div class="input-group">
                                <input value="' . $affiliate_commission_rate . '" type="text" name="affiliate_commission_rate" id="affiliate_commission_rate" class="form-control" size="3" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;">
                                <label for="affiliate_commission_rate" class="input-group-text">%</label>
                            </div>
                            <div class="form-text text-end">' . lang(array('string'=>'leave blank for default: {var:1}%','vars'=>AFFILIATE_DEFAULT_COMMISSION_RATE)) . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Contact') . ' : ' . $first_name  . ' ' . $last_name,
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Edit Contact'),
            'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
        
            'breadcrumb' => array(array('label' => lang('All My Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'), array('label' => lang('Edit Contact'))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('View or update this contact\'s information, subscriber status, member status, affiliate status, or contact groups.') . '" title="' . lang('Edit Contact') . '">[' . $first_name  . ' ' . $last_name  . ']</h2>
                        ' . $output_orders_button . '
                    </div>
                </div>
                <form name="form" action="edit_contact.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row justify-content-center">
                        <div class="col-12 col-sm order-sm-2 align-self-end">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Contact\'s Name') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="salutation" class="form-label">' . lang('Salutation') . '</label>
                                            <input value="' . $salutation . '" type="text" name="salutation" id="salutation" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="first_name" class="form-label">' . lang('First Name') . '</label>
                                            <input value="' . $first_name . '" type="text" name="first_name" id="first_name" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="last_name" class="form-label">' . lang('Last Name') . '</label>
                                            <input value="' . $last_name . '" type="text" name="last_name" id="last_name" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="suffix" class="form-label">' . lang('Suffix') . '</label>
                                            <input value="' . $suffix . '" type="text" name="suffix" id="suffix" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="nickname" class="form-label">' . lang('Nickname') . '</label>
                                            <input value="' . $nickname . '" type="text" name="nickname" id="nickname" class="form-control" size="30" maxlength="50" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto order-sm-1 ">
                            <div class="card my-4 display-on-hover overflow-hidden position-relative" style="width:200px;height:auto;">
                                <div id="software_image_picker_container" ondblclick="software_image_picker({initialize:true,SingleImage:true,file_input_name:\'file_id\'});" class="user-select-none sortable-list img-list bg-body-tertiary rounded p-2 row g-4">' . $image . '</div>
                                <div class="card-footer border-0 bg-transparent">
                                    <button type="button" class="btn"  onclick="software_image_picker({initialize:true,SingleImage:true,file_input_name:\'file_id\'});"><span class="material-icons me-2">image_search</span>' . lang('Change Image') . '</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Contact\'s Subscriber Information') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                                            <div class="input-group">
                                                <input value="' . $email_address . '" type="text" name="email_address" id="email_address" class="form-control text-end" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                                                <a class="btn btn-primary" href="mailto:' . $email_address . '" ><span class="material-icons">email</span></a>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_in" name="opt_in" value="1"' . $opt_in_checked . '>
                                                <label class="form-check-label" for="opt_in">' . lang('Opt-In Status') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Connected Account of this Contact') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        ' . $output_user_info . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 ">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Contact\'s Work Information') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="company" class="form-label">' . lang('Company') . '</label>
                                            <input value="' . $company . '" type="text" name="company" id="company" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="title" class="form-label">' . lang('Title') . '</label>
                                            <input value="' . $title . '" type="text" name="title" id="title" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="department" class="form-label">' . lang('Department') . '</label>
                                            <input value="' . $department . '" type="text" name="department" id="department" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="office_location" class="form-label">' . lang('Office Location') . '</label>
                                            <input value="' . $office_location . '" type="text" name="office_location" id="office_location" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="website" class="form-label">' . lang('Website') . '</label>
                                            <input value="' . $website . '" type="text" name="website" id="website" class="form-control" maxlength="255" />
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_address_1" class="form-label">' . lang('Business Address') . ' 1</label>
                                            <input value="' . $business_address_1 . '" type="text" name="business_address_1" id="business_address_1" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_address_2" class="form-label">' . lang('Business Address') . ' 2</label>
                                            <input value="' . $business_address_2 . '" type="text" name="business_address_2" id="business_address_2" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_city" class="form-label">' . lang('Business City') . '</label>
                                            <input value="' . $business_city . '" type="text" name="business_city" id="business_city" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_state" class="form-label">' . lang('Business State') . '</label>
                                            <input value="' . $business_state . '" type="text" name="business_state" id="business_state" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_country" class="form-label">' . lang('Business Country') . '</label>
                                            <input value="' . $business_country . '" type="text" name="business_country" id="business_country" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_zip_code" class="form-label">' . lang('Business Zip Code') . '</label>
                                            <input value="' . $business_zip_code . '" type="text" name="business_zip_code" id="business_zip_code" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_phone" class="form-label">' . lang('Business Phone') . '</label>
                                            <input value="' . $business_phone . '" type="text" name="business_phone" id="business_phone" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_fax" class="form-label">' . lang('Business Fax') . '</label>
                                            <input value="' . $business_fax . '" type="text" name="business_fax" id="business_fax" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="tax_number" class="form-label">VKN / TCKN</label>
                                            <input value="' . $tax_number . '" type="text" name="tax_number" id="tax_number" class="form-control" maxlength="11" inputmode="numeric" />
                                            <div class="form-text">' . lang('11-digit TCKN for individuals, 10-digit VKN for companies.') . '</div>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                                            <input value="' . $tax_office . '" type="text" name="tax_office" id="tax_office" class="form-control" maxlength="100" />
                                        </div>
                                        ' . (ENABLE_PARASUT ? ($parasut_contact_id ? '
                                        <div class="col-12 col-md-4 my-2">
                                            <label class="form-label text-muted small">' . lang('Parasut Contact ID') . '</label>
                                            <div class="input-group">
                                                <input value="' . $parasut_contact_id . '" type="text" name="parasut_contact_id" class="form-control form-control-sm font-monospace text-muted" />
                                                <span class="input-group-text small text-muted" title="' . lang('Set automatically when the first invoice is created.') . '"><i class="bi bi-link-45deg"></i></span>
                                            </div>
                                        </div>' : '
                                        <div class="col-12 col-md-4 my-2">
                                            <input type="hidden" name="parasut_contact_id" value="" />
                                            <div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>' . lang('Parasut Contact ID will be set automatically when the first invoice is created.') . '</div>
                                        </div>') : '<input type="hidden" name="parasut_contact_id" value="' . $parasut_contact_id . '" />') . '
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Contact\'s Home Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_address_1" class="form-label">' . lang('Home Address') . ' 1</label>
                                            <input value="' . $home_address_1 . '" type="text" name="home_address_1" id="home_address_1" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_address_2" class="form-label">' . lang('Home Address') . ' 2</label>
                                            <input value="' . $home_address_2 . '" type="text" name="home_address_2" id="home_address_2" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_city" class="form-label">' . lang('Home City') . '</label>
                                            <input value="' . $home_city . '" type="text" name="home_city" id="home_city" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_state" class="form-label">' . lang('Home State') . '</label>
                                            <input value="' . $home_state . '" type="text" name="home_state" id="home_state" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_country" class="form-label">' . lang('Home Country') . '</label>
                                            <input value="' . $home_country . '" type="text" name="home_country" id="home_country" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_zip_code" class="form-label">' . lang('Home Zip Code') . '</label>
                                            <input value="' . $home_zip_code . '" type="text" name="home_zip_code" id="home_zip_code" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_phone" class="form-label">' . lang('Home Phone') . '</label>
                                            <input value="' . $home_phone . '" type="text" name="home_phone" id="home_phone" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_fax" class="form-label">' . lang('Home Fax') . '</label>
                                            <input value="' . $home_fax . '" type="text" name="home_fax" id="home_fax" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="mobile_phone" class="form-label">' . lang('Mobile Phone') . '</label>
                                            <input value="' . $mobile_phone . '" type="text" name="mobile_phone" id="mobile_phone" class="form-control" maxlength="255" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Membership Access') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                                            <input value="' . $member_id . '" type="text" name="member_id" id="member_id" class="form-control" />
                                            <div class="form-text text-end">' . lang('leave blank for no membership') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="expiration_date" class="form-label">' . lang('Expiration Date') . '</label>
                                            <input value="' . $expiration_date . '" type="text" name="expiration_date" id="expiration_date" class="form-control" />
                                            <div class="form-text text-end">' . lang('leave blank for lifetime membership') . '</div>
                                            ' . get_date_picker_format() . '
                                            <script>
                                                $("#expiration_date").datepicker(datetimepicker_options);
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Additional Notes') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="lead_source" class="form-label">' . lang('Lead Source') . '</label>
                                            <input value="' . $lead_source . '" type="text" name="lead_source" id="lead_source" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="description" class="form-label">' . lang('Description') . '</label>
                                            <textarea type="text" name="description" id="description" class="form-control" maxlength="50" >' . $description . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ' . $output_affiliate . '

                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Contact Groups & Subscriptions') . '
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
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('contact')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                </form>
            </div>
        </div>
    </main>' .
        output_footer();

} else {
    validate_token_field();
    
    // if contact was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete contact
        $query =
            "DELETE FROM contacts
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete contact references in contacts_contact_groups_xref
        $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete contact references in opt_in
        $query = "DELETE FROM opt_in WHERE contact_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity("contact ($_POST[first_name] $_POST[last_name]) was deleted", $_SESSION['sessionusername']);
        
        $liveform_view_contacts = new liveform('view_contacts');
        $liveform_view_contacts->add_notice(lang('The contact has been deleted.'));
        
    // else contact was not selected for delete
    } else {

        $_POST['email_address'] = trim($_POST['email_address']);
        
        // if an e-mail address was entered, validate e-mail address
        if ($_POST['email_address']) {
            if (validate_email_address($_POST['email_address']) == FALSE) {
                output_error(lang('The email address is invalid') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
            }
        }
        
        if (AFFILIATE_PROGRAM == true) {
            // determine if affiliate was approved already (we will use this later)
            $query = "SELECT affiliate_approved FROM contacts WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            $row = mysqli_fetch_assoc($result);
            $original_affiliate_approved = $row['affiliate_approved'];
            
            $affiliate_code = $_POST['affiliate_code'];
            
            // if an affiliate code was entered check to see if affiliate code is already in use
            if ($affiliate_code) {
                $query = "SELECT id FROM contacts WHERE affiliate_code = '" . escape($affiliate_code) . "' AND id != '" . escape($_POST['id'] ?? '') . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                if (mysqli_num_rows($result) > 0) {
                    output_error(lang('That affiliate code is already in use. Please use a different code') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                }
            }
            
            // if affiliate is approved and affiliate code is blank, generate affiliate code
            if ($_POST['affiliate_approved'] && (!$affiliate_code)) {
                $affiliate_code = generate_affiliate_code();
            }
            
            $sql_affiliate =
                "affiliate_approved = '" . escape($_POST['affiliate_approved'] ?? '') . "',
                affiliate_name = '" . escape($_POST['affiliate_name'] ?? '') . "',
                affiliate_code = '" . escape($affiliate_code) . "',
                affiliate_commission_rate = '" . escape($_POST['affiliate_commission_rate'] ?? '') . "',";
        }
      

        if(isset($_POST['file_id']) && $_POST['file_id'] != ''){
            $sql_file_id = "file_id = '" . escape($_POST['file_id'] ?? '') . "',";
        }else{
            $sql_file_id = "file_id = '',";
        }
    
        if(isset($_POST['image_url']) && $_POST['image_url'] != ''){
            $sql_image = "image = '" . escape($_POST['image_url'] ?? '') . "',";
        }else{
            $sql_image = "image = '',";
        }
        $query =
            "UPDATE contacts
            SET
                salutation = '" . escape($_POST['salutation'] ?? '') . "',
                first_name = '" . escape($_POST['first_name'] ?? '') . "',
                last_name = '" . escape($_POST['last_name'] ?? '') . "',
                suffix = '" . escape($_POST['suffix'] ?? '') . "',
                nickname = '" . escape($_POST['nickname'] ?? '') . "',
                company = '" . escape($_POST['company'] ?? '') . "',
                title = '" . escape($_POST['title'] ?? '') . "',
                department = '" . escape($_POST['department'] ?? '') . "',
                office_location = '" . escape($_POST['office_location'] ?? '') . "',
                business_address_1 = '" . escape($_POST['business_address_1'] ?? '') . "',
                business_address_2 = '" . escape($_POST['business_address_2'] ?? '') . "',
                business_city = '" . escape($_POST['business_city'] ?? '') . "',
                business_state = '" . escape($_POST['business_state'] ?? '') . "',
                business_country = '" . escape($_POST['business_country'] ?? '') . "',
                business_zip_code = '" . escape($_POST['business_zip_code'] ?? '') . "',
                business_phone = '" . escape($_POST['business_phone'] ?? '') . "',
                business_fax = '" . escape($_POST['business_fax'] ?? '') . "',
                home_address_1 = '" . escape($_POST['home_address_1'] ?? '') . "',
                home_address_2 = '" . escape($_POST['home_address_2'] ?? '') . "',
                home_city = '" . escape($_POST['home_city'] ?? '') . "',
                home_state = '" . escape($_POST['home_state'] ?? '') . "',
                home_country = '" . escape($_POST['home_country'] ?? '') . "',
                home_zip_code = '" . escape($_POST['home_zip_code'] ?? '') . "',
                home_phone = '" . escape($_POST['home_phone'] ?? '') . "',
                home_fax = '" . escape($_POST['home_fax'] ?? '') . "',
                mobile_phone = '" . escape($_POST['mobile_phone'] ?? '') . "',
                email_address = '" . escape($_POST['email_address'] ?? '') . "',
                website = '" . escape($_POST['website'] ?? '') . "',
                lead_source = '" . escape($_POST['lead_source'] ?? '') . "',
                opt_in = '" . escape($_POST['opt_in'] ?? '') . "',
                description = '" . escape($_POST['description'] ?? '') . "',
                member_id = '" . escape($_POST['member_id'] ?? '') . "',
                expiration_date = '" . escape(prepare_form_data_for_input($_POST['expiration_date'], 'date')) . "',
                tax_number = '" . escape(trim($_POST['tax_number'] ?? '')) . "',
                tax_office = '" . escape(trim($_POST['tax_office'] ?? '')) . "',
                parasut_contact_id = '" . escape(trim($_POST['parasut_contact_id'] ?? '')) . "',
                $sql_affiliate
                user = '" . $user['id'] . "',
                $sql_file_id
                $sql_image
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');

        // If this contact has an email address, then update opt-in status for other contacts
        // with this same email address, so the opt-in status is the same for all.
        if ($_POST['email_address']) {
            db(
                "UPDATE contacts SET opt_in = '" . e($_POST['opt_in'] ?? '') . "'
                WHERE email_address = '" . e($_POST['email_address'] ?? '') . "'");
        }
        
        // get all contact groups, so that contact can be added to selected contact groups
        $query =
            "SELECT
               id,
               email_subscription
            FROM contact_groups";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $contact_groups = array();
        
        while ($row = mysqli_fetch_assoc($result)) {
            $contact_groups[] = $row;
        }
        
        // loop through all contact groups
        foreach ($contact_groups as $contact_group) {
            // if user has access to contact group, then continue
            if (validate_contact_group_access($user, $contact_group['id']) == true) {
                // if contact group was selected for contact, add contact to contact group
                if (($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) {
                    // check to see if contact is already in contact group
                    $query =
                        "SELECT contact_id
                        FROM contacts_contact_groups_xref
                        WHERE
                            (contact_id = '" . escape($_POST['id'] ?? '') . "')
                            AND (contact_group_id = '" . $contact_group['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    // if contact is not already in contact group, then add contact to contact group
                    if (mysqli_num_rows($result) == 0) {
                        $query =
                            "INSERT INTO contacts_contact_groups_xref (
                                contact_id,
                                contact_group_id)
                            VALUES (
                                '" . escape($_POST['id'] ?? '') . "',
                                '" . $contact_group['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                    
                    // if contact group has email subscription turned on, add opt-in selection
                    if ($contact_group['email_subscription'] == 1) {
                        // check to see if there is already an opt-in record
                        $query =
                            "SELECT contact_id
                            FROM opt_in
                            WHERE
                                (contact_id = '" . escape($_POST['id'] ?? '') . "')
                                AND (contact_group_id = '" . $contact_group['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
                        // if there is not already an opt-in record, then create record
                        if (mysqli_num_rows($result) == 0) {
                            $query =
                                "INSERT INTO opt_in (
                                    contact_id,
                                    contact_group_id,
                                    opt_in)
                                VALUES (
                                    '" . escape($_POST['id'] ?? '') . "',
                                    '" . $contact_group['id'] . "',
                                    '" . escape($_POST['contact_group_opt_in_' . $contact_group['id']]) . "')";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            
                        // else an opt-in record already exists, so update record
                        } else {
                            $query =
                                "UPDATE opt_in
                                SET opt_in = '" . escape($_POST['contact_group_opt_in_' . $contact_group['id']]) . "'
                                WHERE
                                    (contact_id = '" . escape($_POST['id'] ?? '') . "')
                                    AND (contact_group_id = '" . $contact_group['id'] . "')";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        }
                    }
                    
                // else contact group was not selected for contact, so remove contact from contact group
                } else {
                    $query =
                        "DELETE FROM contacts_contact_groups_xref
                        WHERE
                            (contact_id = '" . escape($_POST['id'] ?? '') . "')
                            AND (contact_group_id = '" . $contact_group['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
        }
        
        // if affiliate program is enabled and there is a group offer and the affiliate has been approved and it was not approved before, then determine if we need to add a key code for group offer for this affiliate
        if ((AFFILIATE_PROGRAM == TRUE) && (AFFILIATE_GROUP_OFFER_ID != 0) && ($_POST['affiliate_approved'] == 1) && ($original_affiliate_approved == 0)) {
            // check if offer exists and get offer code
            $query = "SELECT code FROM offers WHERE id = '" . AFFILIATE_GROUP_OFFER_ID . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if an offer was found, then continue to check if a key code should be added for group offer
            if (mysqli_num_rows($result) > 0) {
                $offer = mysqli_fetch_assoc($result);
                
                // check if a key code already exists for this group offer and affiliate
                $query =
                    "SELECT id
                    FROM key_codes
                    WHERE
                        (code = '" . escape($affiliate_code) . "')
                        AND (offer_code = '" . escape($offer['code']) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // if a key code does not already exist for this group offer and affiliate, then create key code
                if (mysqli_num_rows($result) == 0) {
                    $query =
                        "INSERT INTO key_codes (
                            code,
                            offer_code,
                            enabled,
                            user,
                            timestamp)
                        VALUES (
                            '" . escape($affiliate_code) . "',
                            '" . escape($offer['code']) . "',
                            '1',
                            '" . $user['id'] . "',
                            UNIX_TIMESTAMP())";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
        }

        log_activity( lang(array('string'=>'contact ({var:1} {var:2}) was modified','vars'=>array($_POST['first_name'],$_POST['last_name']) )), $_SESSION['sessionusername']);
          
        // if there is not a send to or the send to is the view contacts screen, then add notice
        if ((isset($_REQUEST['send_to']) == FALSE) || (mb_strpos($_REQUEST['send_to'], 'view_contacts.php') !== FALSE)) {
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice(lang('The contact has been saved.'));
        }
    }
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
    }
}
?>
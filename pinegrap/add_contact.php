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
           name,
           email_subscription
        FROM contact_groups
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contact_groups = array();
    
    // loop through all contact groups and put in array if the user has access to it
    while ($row = mysqli_fetch_assoc($result)) {
        // if user has access to contact group, then include this contact group
        if (validate_contact_group_access($user, $row['id']) == true) {
            $contact_groups[] = $row;
        }
    }
    
    $output_contact_groups = '';
    
    // determine how many contact groups need to be in each table cell
    $number_of_contact_groups_per_cell = ceil(count($contact_groups)/3);
    
    // loop through and output the contact groups
    foreach ($contact_groups as $key => $contact_group) {
        // if contact group has email subscription turned on, prepare opt-in field
        if ($contact_group['email_subscription'] == 1) {
            $contact_group_target = ' data-bs-target="#contact_group_opt_in_cell_' . $contact_group['id'] . '"';
            $output_opt_in = '
            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="contact_group_opt_in_cell_' . $contact_group['id'] . '">
                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(45px, 0px);"></div>
                <div class="popover-body py-0">
                    <div class="row">
                        <div class="col-12 my-2">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="contact_group_opt_in_' . $contact_group['id'] . '" id="contact_group_opt_in_' . $contact_group['id'] . '" value="1" checked="checked" class="form-check-input" />
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
                    <input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="collapse-switcher form-check-input"' . $contact_group_target . '/>
                    <label class="form-check-label" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
                </div>
                ' . $output_opt_in . '
            </div>';
    
    }

    // if the affiliate program is enabled, prepare affiliate program output
    if (AFFILIATE_PROGRAM == true) {
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
                                    <input class="form-check-input" type="checkbox" id="affiliate_approved" name="affiliate_approved" value="1">
                                    <label class="form-check-label" for="affiliate_approved">' . lang('Approved') . '</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-3 my-2">
                                <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                                <input type="text" name="affiliate_name" id="affiliate_name" class="form-control" maxlength="100" />
                            </div>
                            <div class="col-12 col-md-6 my-2">
                                <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                                <input type="text" name="affiliate_code" id="affiliate_code" class="form-control" maxlength="100" />
                                <div class="form-text text-end">' . lang('leave blank to automatically generate code') . '</div>
                            </div>
                            <div class="col-12 col-md-3 my-2">
                                <label for="affiliate_commission_rate" class="form-label">' . lang('Commission Rate') . '</label>
                                <div class="input-group">
                                    <input type="text" name="affiliate_commission_rate" id="affiliate_commission_rate" class="form-control" size="3" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;">
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
            'title'=> lang('Create Contact'),
            'extra classes'=>'contact',
            'icon'=>'contact',
            'heading'=>lang('Create Contact'),
            'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'),
                array('label' => lang('Create Contact')),
            ),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break" data-bs-content="' . lang('Create a new contact, subscriber, unregistered member, or unapproved affiliate, and add them to any of my contact groups.') . '" title="' . lang('Create Contact') . '">[' . lang('First Name') . ' ' . lang('Last Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_contact.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row justify-content-center">
                        <div class="col-12 col-sm order-sm-2 align-self-end">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Contact\'s Name') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="salutation" class="form-label">' . lang('Salutation') . '</label>
                                            <input type="text" name="salutation" id="salutation" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="first_name" class="form-label">' . lang('First Name') . '</label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="last_name" class="form-label">' . lang('Last Name') . '</label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="suffix" class="form-label">' . lang('Suffix') . '</label>
                                            <input type="text" name="suffix" id="suffix" class="form-control" size="30" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="nickname" class="form-label">' . lang('Nickname') . '</label>
                                            <input type="text" name="nickname" id="nickname" class="form-control" size="30" maxlength="50" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto order-sm-1 ">
                            <div class="card my-4 display-on-hover overflow-hidden position-relative" style="width:200px;height:auto;">
                                <div id="software_image_picker_container" ondblclick="software_image_picker({initialize:true,SingleImage:true});" class="user-select-none sortable-list img-list bg-body-tertiary rounded p-2 row g-4"></div>
                                <div class="card-footer border-0 bg-transparent">
                                    <button type="button" class="btn"  onclick="software_image_picker({initialize:true,SingleImage:true});"><span class="material-icons me-2">image_search</span>' . lang('Change Image') . '</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Contact\'s Subscriber Information') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                                            <input type="text" name="email_address" id="email_address" class="form-control text-end" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_in" name="opt_in" value="1" checked="checked">
                                                <label class="form-check-label" for="opt_in">' . lang('Opt-In Status') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 ">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Contact\'s Work Information') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="company" class="form-label">' . lang('Company') . '</label>
                                            <input type="text" name="company" id="company" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="title" class="form-label">' . lang('Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="department" class="form-label">' . lang('Department') . '</label>
                                            <input type="text" name="department" id="department" class="form-control" maxlength="50"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="office_location" class="form-label">' . lang('Office Location') . '</label>
                                            <input type="text" name="office_location" id="office_location" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="website" class="form-label">' . lang('Website') . '</label>
                                            <input type="text" name="website" id="website" class="form-control" maxlength="255" />
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_address_1" class="form-label">' . lang('Business Address') . ' 1</label>
                                            <input type="text" name="business_address_1" id="business_address_1" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_address_2" class="form-label">' . lang('Business Address') . ' 2</label>
                                            <input type="text" name="business_address_2" id="business_address_2" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_city" class="form-label">' . lang('Business City') . '</label>
                                            <input type="text" name="business_city" id="business_city" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_state" class="form-label">' . lang('Business State') . '</label>
                                            <input type="text" name="business_state" id="business_state" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_country" class="form-label">' . lang('Business Country') . '</label>
                                            <input type="text" name="business_country" id="business_country" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_zip_code" class="form-label">' . lang('Business Zip Code') . '</label>
                                            <input type="text" name="business_zip_code" id="business_zip_code" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_phone" class="form-label">' . lang('Business Phone') . '</label>
                                            <input type="text" name="business_phone" id="business_phone" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="business_fax" class="form-label">' . lang('Business Fax') . '</label>
                                            <input type="text" name="business_fax" id="business_fax" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="tax_number" class="form-label">VKN / TCKN</label>
                                            <input type="text" name="tax_number" id="tax_number" class="form-control" maxlength="11" inputmode="numeric" />
                                            <div class="form-text">' . lang('11-digit TCKN for individuals, 10-digit VKN for companies.') . '</div>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                                            <input type="text" name="tax_office" id="tax_office" class="form-control" maxlength="100" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Contact\'s Home Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_address_1" class="form-label">' . lang('Home Address') . ' 1</label>
                                            <input type="text" name="home_address_1" id="home_address_1" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_address_2" class="form-label">' . lang('Home Address') . ' 2</label>
                                            <input type="text" name="home_address_2" id="home_address_2" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_city" class="form-label">' . lang('Home City') . '</label>
                                            <input type="text" name="home_city" id="home_city" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_state" class="form-label">' . lang('Home State') . '</label>
                                            <input type="text" name="home_state" id="home_state" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_country" class="form-label">' . lang('Home Country') . '</label>
                                            <input type="text" name="home_country" id="home_country" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_zip_code" class="form-label">' . lang('Home Zip Code') . '</label>
                                            <input type="text" name="home_zip_code" id="home_zip_code" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_phone" class="form-label">' . lang('Home Phone') . '</label>
                                            <input type="text" name="home_phone" id="home_phone" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="home_fax" class="form-label">' . lang('Home Fax') . '</label>
                                            <input type="text" name="home_fax" id="home_fax" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="mobile_phone" class="form-label">' . lang('Mobile Phone') . '</label>
                                            <input type="text" name="mobile_phone" id="mobile_phone" class="form-control" maxlength="255" />
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
                                            <input type="text" name="member_id" id="member_id" class="form-control" />
                                            <div class="form-text text-end">' . lang('leave blank for no membership') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="expiration_date" class="form-label">' . lang('Expiration Date') . '</label>
                                            <input type="text" name="expiration_date" id="expiration_date" class="form-control" />
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
                                            <input type="text" name="lead_source" id="lead_source" class="form-control" maxlength="50" />
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="description" class="form-label">' . lang('Description') . '</label>
                                            <textarea type="text" name="description" id="description" class="form-control" maxlength="50" ></textarea>
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
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    $_POST['email_address'] = trim($_POST['email_address']);
    
    // if an e-mail address was entered
    if ($_POST['email_address']) {
        // validate e-mail address
        if (validate_email_address($_POST['email_address']) == FALSE) {
            output_error(lang('The email address is invalid') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }
    }
    
    if (AFFILIATE_PROGRAM == true) {
        $affiliate_code = $_POST['affiliate_code'];
        
        // if an affiliate code was entered check to see if affiliate code is already in use
        if ($affiliate_code) {
            $query = "SELECT id FROM contacts WHERE affiliate_code = '" . escape($affiliate_code) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            if (mysqli_num_rows($result) > 0) {
                output_error(lang('That affiliate code is already in use. Please use a different code') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
            }
        }
        
        // if affiliate is approved and affiliate code is blank, generate affiliate code
        if ($_POST['affiliate_approved'] && (!$affiliate_code)) {
            $affiliate_code = generate_affiliate_code();
        }
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
    
    // if user has user role, then check to make sure that at least one group was selected for contact
    if ($user['role'] == 3) {
        // assume no contact groups were selected, until we find out otherwise
        $contact_group_selected = false;
        
        // loop through all contact groups
        foreach ($contact_groups as $contact_group) {
            // if contact group was selected for contact to be added to and user has access to contact group, take note
            if ((($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) && (validate_contact_group_access($user, $contact_group['id']) == true)) {
                $contact_group_selected = true;
                break;
            }
        }
        
        // if no contact groups were selected, then output error
        if ($contact_group_selected == false) {
            output_error(lang('Please select at least one contact group for the contact') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }
    }

    $sql_file_id ='';

    if(isset($_POST['file_id']) && $_POST['file_id'] != ''){
        $sql_file_id = "'" . escape($_POST['file_id'] ?? '') . "',";
    }else{
        $sql_file_id = "'',";
    }

    if(isset($_POST['image_url']) && $_POST['image_url'] != ''){
        $sql_image = "'" . escape($_POST['image_url'] ?? '') . "',";
    }else{
        $sql_image = "'',";
    }


    $query =
        "INSERT INTO contacts (
            salutation,
            first_name,
            last_name,
            suffix,
            nickname,
            company,
            title,
            department,
            office_location,
            business_address_1,
            business_address_2,
            business_city,
            business_state,
            business_country,
            business_zip_code,
            business_phone,
            business_fax,
            home_address_1,
            home_address_2,
            home_city,
            home_state,
            home_country,
            home_zip_code,
            home_phone,
            home_fax,
            mobile_phone,
            email_address,
            website,
            lead_source,
            opt_in,
            description,
            user,
            timestamp,
            image,
            file_id,
            member_id,
            expiration_date,
            affiliate_approved,
            affiliate_name,
            affiliate_code,
            affiliate_commission_rate,
            tax_number,
            tax_office)
        VALUES (
            '" . escape($_POST['salutation'] ?? '') . "',
            '" . escape($_POST['first_name'] ?? '') . "',
            '" . escape($_POST['last_name'] ?? '') . "',
            '" . escape($_POST['suffix'] ?? '') . "',
            '" . escape($_POST['nickname'] ?? '') . "',
            '" . escape($_POST['company'] ?? '') . "',
            '" . escape($_POST['title'] ?? '') . "',
            '" . escape($_POST['department'] ?? '') . "',
            '" . escape($_POST['office_location'] ?? '') . "',
            '" . escape($_POST['business_address_1'] ?? '') . "',
            '" . escape($_POST['business_address_2'] ?? '') . "',
            '" . escape($_POST['business_city'] ?? '') . "',
            '" . escape($_POST['business_state'] ?? '') . "',
            '" . escape($_POST['business_country'] ?? '') . "',
            '" . escape($_POST['business_zip_code'] ?? '') . "',
            '" . escape($_POST['business_phone'] ?? '') . "',
            '" . escape($_POST['business_fax'] ?? '') . "',
            '" . escape($_POST['home_address_1'] ?? '') . "',
            '" . escape($_POST['home_address_2'] ?? '') . "',
            '" . escape($_POST['home_city'] ?? '') . "',
            '" . escape($_POST['home_state'] ?? '') . "',
            '" . escape($_POST['home_country'] ?? '') . "',
            '" . escape($_POST['home_zip_code'] ?? '') . "',
            '" . escape($_POST['home_phone'] ?? '') . "',
            '" . escape($_POST['home_fax'] ?? '') . "',
            '" . escape($_POST['mobile_phone'] ?? '') . "',
            '" . escape($_POST['email_address'] ?? '') . "',
            '" . escape($_POST['website'] ?? '') . "',
            '" . escape($_POST['lead_source'] ?? '') . "',
            '" . escape($_POST['opt_in'] ?? '') . "',
            '" . escape($_POST['description'] ?? '') . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            $sql_image
            $sql_file_id
            '" . escape($_POST['member_id'] ?? '') . "',
            '" . escape(prepare_form_data_for_input($_POST['expiration_date'], 'date')) . "',
            '" . escape($_POST['affiliate_approved'] ?? '') . "',
            '" . escape($_POST['affiliate_name'] ?? '') . "',
            '" . escape($affiliate_code) . "',
            '" . escape($_POST['affiliate_commission_rate'] ?? '') . "',
            '" . escape(trim($_POST['tax_number'] ?? '')) . "',
            '" . escape(trim($_POST['tax_office'] ?? '')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    
    $contact_id = mysqli_insert_id(db::$con);

    // If this contact has an email address, then update opt-in status for other contacts
    // with this same email address, so the opt-in status is the same for all.
    if ($_POST['email_address']) {
        db(
            "UPDATE contacts SET opt_in = '" . e($_POST['opt_in'] ?? '') . "'
            WHERE email_address = '" . e($_POST['email_address'] ?? '') . "'");
    }
    
    // loop through all contact groups
    foreach ($contact_groups as $contact_group) {
        // if contact group was selected for contact to be added to and user has access to contact group, add contact to contact group
        if ((($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) && (validate_contact_group_access($user, $contact_group['id']) == true)) {
            $query =
                "INSERT INTO contacts_contact_groups_xref (
                    contact_id,
                    contact_group_id)
                VALUES (
                    '" . $contact_id . "',
                    '" . $contact_group['id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if contact group has email subscription turned on, add opt-in selection
            if ($contact_group['email_subscription'] == 1) {
                $query =
                    "INSERT INTO opt_in (
                        contact_id,
                        contact_group_id,
                        opt_in)
                    VALUES (
                        '" . $contact_id . "',
                        '" . $contact_group['id'] . "',
                        '" . escape($_POST['contact_group_opt_in_' . $contact_group['id']]) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
    }
    
    // if affiliate program is enabled and there is a group offer and the affiliate has been approved, then determine if we need to add a key code for group offer for this affiliate
    if ((AFFILIATE_PROGRAM == TRUE) && (AFFILIATE_GROUP_OFFER_ID != 0) && ($_POST['affiliate_approved'] == 1)) {
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

    log_activity("contact ($_POST[first_name] $_POST[last_name]) was created", $_SESSION['sessionusername']);

    $liveform_view_contacts = new liveform('view_contacts');
    $liveform_view_contacts->add_notice(lang('The contact has been created.'));

    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
    }
}
?>
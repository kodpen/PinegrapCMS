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


ini_set('max_execution_time', '9999');
include('init.php');
include_once('liveform.class.php');
$liveform = new liveform('import_users');
$liveform_view_users = new liveform('view_users');
$user = validate_user();
validate_area_access($user, 'manager');

if (!$_POST) {
    // if user is not an administrator or designer, then prepare to disable role picklist
    if ($user['role'] <= 1) {    
        $output_role_disabled_class = '';
        $output_role_disabled_attribute = '';
    }else{
        $output_role_disabled_class =' disabled';
        $output_role_disabled_attribute = 'disabled="disabled"';
    }
    
    $output_manage_forms = '';
    
    // if forms module is on, then output manage forms checkbox
    if (FORMS === true) {
        $output_manage_forms = '<div class="form-check my-2 form-switch"><input type="checkbox" name="manage_forms" id="manage_forms" value="yes" class="form-check-input" /><label class="form-check-label" for="manage_forms">' . lang('Also allow User to access submitted form data for selected folders') . '</label></div>';
    }
    
    // if calendars module is on, then output manage calendars checkbox
    if (CALENDARS === true) {
        // get all calendars
        $query =
            "SELECT
               id,
               name
            FROM calendars
            ORDER BY name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        $output_calendars = '';

        // loop through all calendars
        while ($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $name = $row['name'];
            
            $output_calendars .= '<div class="form-check"><input type="checkbox" name="calendar_' . $id . '" id="calendar_' . $id . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_' . $id . '"> ' . h($name) . '</label></div>';
        }
        
        $output_manage_calendars =
            '<div class="col-12 mt-5 mb-1 collapse show" id="manage_calendars_heading_row">
                <h4 class="fw-bold text-muted">' . lang('Calendar Management Rights') . '</h4>
            </div>
            <div class="col-12 my-2 collapse show" id="manage_calendars_row">
                <div class="form-check form-switch">
                    <input value="yes" id="manage_calendars" name="manage_calendars" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#calendar_access" />
                    <label class="form-check-label" for="manage_calendars">' . lang('Allow User to add events to one or more calendars') . '</label>
                </div>
                <div class="collapse popover w-100 fade bs-popover-bottom p-0 mb-2" id="calendar_access">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                    <div class="card-header border-0 bg-reset">
                                        <div class="form-check form-switch">
                                            <input id="multiselect-checkbox-checker-4" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                            <label for="multiselect-checkbox-checker-4" class="form-check-label">' . lang('Select All') . '</label>
                                        </div>
                                    </div>
                                    <div class="card-body overflow-auto" style="max-height:300px">
                                        ' . $output_calendars . '
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" id="publish_calendar_events" name="publish_calendar_events" value="yes" checked="checked" class="form-check-input" />
                                    <label class="form-check-label" for="publish_calendar_events">' . lang('Also allow User to publish calendar events for selected calendars') . '</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>';
    }
    
    // if e-commerce module is on, then output manage e-commerce checkbox
    if (ECOMMERCE === true) {
        $output_set_offline_payment = '';
        
        // if offline payment is enabled, then prepare to output set offline payment
        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $output_set_offline_payment = '
            <div class="form-check my-2 form-switch">
                <input type="checkbox" id="set_offline_payment" name="set_offline_payment" value="1" class="form-check-input" />
                <label class="form-check-label" for="set_offline_payment">' . lang('Allow User to set offline payment option for orders') . '</label>
            </div>';
        }
        
        $output_manage_ecommerce =
            '<div class="col-12 mt-5 mb-1 collapse show" id="manage_ecommerce_heading_row">
                <h4 class="fw-bold text-muted">' . lang('Commerce Management Rights') . '</h4>
            </div>
            <div class="col-12 my-2 collapse show" id="manage_ecommerce_row">
                <div class="form-check mb-2 form-switch">
                    <input type="checkbox" id="manage_ecommerce" name="manage_ecommerce" value="yes" class="form-check-input collapse-switcher" role="switch" data-bs-target="#view_card_data_container"/>
                    <label class="form-check-label" for="manage_ecommerce">' . lang('Allow User to manage all commerce (i.e. products, shipping, tax, and orders)') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="view_card_data_container">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input type="checkbox" id="view_card_data" name="view_card_data" value="1" class="form-check-input" />
                                    <label class="form-check-label" for="view_card_data">' . lang('Also allow User to view card data') . '</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
                <div class="form-check my-2 form-switch">
                    <input type="checkbox" id="manage_ecommerce_reports" name="manage_ecommerce_reports" value="1" class="form-check-input" />
                    <label class="form-check-label" for="manage_ecommerce_reports">' . lang('Allow User to manage all commerce reports (i.e. order reports & shipping report)') . '</label>
                </div>
                ' . $output_set_offline_payment . '
            </div>';
    }

    $output_manage_ads = '';

    // If ads is enabled, then output area for it.
    if (ADS === true) {
        $output_manage_ads =
            '<div class="col-12 mt-5 mb-1 collapse show" id="manage_ad_regions_heading_row">
                <h4 class="fw-bold text-muted">' . lang('Ads Management Rights') . '</h4>
            </div>
            <div class="col-12 my-2 collapse show" id="manage_ad_regions_row">
                <h5>' . lang('Allow User to edit Ads within the selected Ad Regions') . '</h5>
                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                    <div class="card-header border-0 bg-reset">
                        <div class="form-check form-switch">
                            <input id="multiselect-checkbox-checker-6" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                            <label for="multiselect-checkbox-checker-6" class="form-check-label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                        ' . get_checkboxes_for_items_user_can_edit('ad_regions') . '
                    </div>
                </div>
            </div>';
    }
    
    // get all contact groups
    $contact_groups = db_items(
        "SELECT
           id,
           name
        FROM contact_groups
        ORDER BY name");

    $output_contact_groups = '';
    $output_contact_groups_for_contact = '';
    $contact_group_counter = 1;
    $number_of_contact_groups_per_cell = ceil(count($contact_groups)/3);

    // loop through all contact groups
    foreach ($contact_groups as $key => $contact_group) {
        $output_contact_groups .= '<div class="form-check"><input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="contact_group_' . $contact_group['id'] . '">' . h($contact_group['name']) . '</label></div>';
        
        $output_contact_groups_for_contact .= '
        <div class="col-12 col-md-4 my-2">
            <div class="form-check form-switch">
                <input type="checkbox" name="contact_group_for_contact_' . $contact_group['id'] . '" id="contact_group_for_contact_' . $contact_group['id'] . '" value="1" class="form-check-input"/>
                <label class="form-check-label" for="contact_group_for_contact_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
            </div>
        </div>';
    }
    
    $output_hidden_role = '';
    
    // if user that is logged in is a manager then output hidden field for role data
    if ($user['role'] == 2) {
        $output_hidden_role = '<input type="hidden" name="role" value="3" />';
    }

    $output_badge_label_info = '';
    $output_badge_label_placeholder = lang('Badge Label');

    // If there is a default badge label in the site settings,
    // then output info about how field can be left blank for default.
    if (BADGE_LABEL != '') {
        $output_badge_label_info = '<div class="form-text text-end">(' . lang('leave blank for default') . ': "' . h(BADGE_LABEL) . '")</div>';
        $output_badge_label_placeholder =h(BADGE_LABEL);
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> lang('Import Users'),
            'extra classes'=>'users',
            'icon'=>'account', 
            'heading'=>lang('Import Users'),
            'cancel'=>array('enable'=>'true','url'=>'view_users.php'),
        
            'breadcrumb' => array(array('label' => lang('All My Users'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php'), array('label' => lang('Import Users'))),
        )
    ) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Import new Users and Contacts, assign privileges, and choose to email login info to Users.') . '" title="' . lang('Import Users') . '">[' . lang('New Users') . ']</h2>
                    </div>
                </div>
                <form name="form" enctype="multipart/form-data" action="import_users.php" method="post">
                    ' . get_token_field() . '
                    ' . $output_hidden_role . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                            <div class="card my-4 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('User Role') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <select name="role" id="role" class="form-select collapse-if-selected' . $output_role_disabled_class . '" ' . $output_role_disabled_attribute . ' data-bs-target="#user_options_row" onchange="change_user_role(this.options[this.selectedIndex].value)">' . select_user_role(3, $user['role']) . '</select>
                                            <script>
                                                $(document).ready(function() {
                                                    change_user_role($("select#role option:selected").val());
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-lg-9 col-xl-10">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Import CSV') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                            ' . $liveform->output_field(array('type'=>'file', 'name'=>'file', 'size'=>'60', 'id'=>'file', 'class'=>'form-control w-auto')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-12 col-lg-6 col-xl-5 my-2">
                                            <label for="username" class="form-label">' . lang('User Start Page') . '</label>
                                            <select class="form-select" name="home_page" id="home_page"><option value="0">[' . lang('None') . ']</option>' . select_page() . '</select>
                                            <div class="form-text text-end">' . lang('Send User to a Specific Page on Login') . '</div>
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-6 col-xl-3 my-2">
                                            <label class="form-label" for="reward_points">' . lang('Reward Program, Reward Points') . '</label>
                                            <input type="number" class="form-control text-end" id="reward_points" name="reward_points" maxlength="9"/>
                                        </div>
                                        <div class="col-12 my-2 mt-3">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" id="badge" name="badge" value="1" class="form-check-input collapse-switcher" data-bs-target="#badge_row"/>
                                                <label class="form-check-label" for="badge">' . lang('Show badge next to username') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="badge_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="badge_label">' . lang('Badge Label') . '</label>
                                                            <input type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" class="form-control" value="" size="20" maxlength="100" />
                                                            ' . $output_badge_label_info . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> 
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" id="notify_user" name="notify_user" value="1" class="form-check-input"/>
                                                <label class="form-check-label" for="notify_user">' . lang('Notify User: Send email with login info to User') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse" id="user_options_row">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('User Access Privileges') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 my-2 collapse" id="user_has_all_permissions">
                                                <div class="alert alert-warning">' . lang('This users would be have all permissions') . '</div>
                                            </div>
                                            <div class="col-12 mt-2 mb-1 collapse show" id="edit_access_heading_row">
                                                <h4 class="fw-bold text-muted">' . lang('Content Management & Forms Management Rights') . '</h4>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="edit_access_row">
                                                <h5>' . lang('Allow User to view and edit pages, files, and custom forms within selected folders') . '</h5>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-check form-switch">
                                                            <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                            <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . get_acl_folder_tree('edit') . '
                                                    </div>
                                                </div>
                                                <div class="form-check my-2 form-switch"><input type="checkbox" name="create_pages" id="create_pages" value="1" class="form-check-input" checked="checked" /><label class="form-check-label" for="create_pages">' . lang('Also allow User to create/duplicate pages in selected folders') . '</label></div>
                                                <div class="form-check my-2 form-switch"><input type="checkbox" name="delete_pages" id="delete_pages" value="1" class="form-check-input" checked="checked" /><label class="form-check-label" for="delete_pages">' . lang('Also allow User to delete pages in selected folders') . '</label></div>
                                                ' . $output_manage_forms . '
                                                <h5>' . lang('Allow User to set the following page types for pages') . '</h5>
                                                <div class="card multiselect-checkbox-container rounded-0">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-check form-switch">
                                                            <input id="multiselect-checkbox-checker-1" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                            <label for="multiselect-checkbox-checker-1" class="form-check-label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . get_page_type_checkboxes_and_labels() . '
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 mt-5 mb-1 collapse show" id="shared_content_access_rights_heading_row">
                                                <h4 class="fw-bold text-muted">' . lang('Shared Content Management Rights') . '</h4>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="common_regions_access_row">
                                                <h5>' . lang('Allow User to edit the content within the selected Common Regions') . '</h5>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-check form-switch">
                                                            <input id="multiselect-checkbox-checker-2" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                            <label for="multiselect-checkbox-checker-2" class="form-check-label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . get_checkboxes_for_items_user_can_edit('common_regions') . '
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="menus_access_row">
                                                <h5>' . lang('Allow User to edit Menu Items within the selected Menus') . '</h5>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-check form-switch">
                                                            <input id="multiselect-checkbox-checker-3" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                            <label for="multiselect-checkbox-checker-3" class="form-check-label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . get_checkboxes_for_items_user_can_edit('menus') . '
                                                    </div>
                                                </div>
                                            </div>
                                            ' . $output_manage_calendars . '
                                            <div class="col-12 mt-5 mb-1 collapse show" id="manage_visitors_heading_row">
                                                <h4 class="fw-bold text-muted">' . lang('Visitor Report Management Rights') . '</h4>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="manage_visitors_row">
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" id="manage_visitors" name="manage_visitors" value="yes" class="form-check-input" />
                                                    <label class="form-check-label" for="manage_visitors">' . lang('Allow User to manage all visitor reports') . '</label>
                                                </div>
                                            </div>
                                            <div class="col-12 mt-5 mb-1 collapse show" id="manage_contacts_and_manage_emails_heading_row">
                                                <h4 class="fw-bold text-muted">' . lang('Contact Management & Campaign Management Rights') . '</h4>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="manage_contacts_and_manage_emails_row">
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" id="manage_contacts" name="manage_contacts" value="yes" onclick="show_or_hide_contact_group_access()" class="form-check-input" />
                                                    <label class="form-check-label" for="manage_contacts">' . lang('Allow User to view, edit, import, and export all contacts within any selected contact groups') . '</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" id="manage_emails" name="manage_emails" value="yes" onclick="show_or_hide_contact_group_access()" class="form-check-input" />
                                                    <label class="form-check-label" for="manage_emails">' . lang('Allow User to send e-mail campaigns to any selected contact groups') . '</label>
                                                </div>
                                                <div class="collapse popover w-100 fade bs-popover-bottom p-0 mb-2" id="contact_group_access">
                                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                    <div class="popover-body">
                                                        <div class="row">
                                                            <div class="col-12 my-1">
                                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                                    <div class="card-header border-0 bg-reset">
                                                                        <div class="form-check form-switch">
                                                                            <input id="multiselect-checkbox-checker-5" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                                            <label for="multiselect-checkbox-checker-5" class="form-check-label">' . lang('Select All') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                                        ' . $output_contact_groups . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> 
                                            </div>
                                            ' . $output_manage_ecommerce . '
                                            ' . $output_manage_ads . '
                                            <div class="col-12 mt-5 mb-1 collapse show" id="view_access_heading_row">
                                                <h4 class="fw-bold text-muted">' . lang('Private Content Access Rights') . '</h4>
                                            </div>
                                            <div class="col-12 my-2 collapse show" id="view_access_row">
                                                <h5>' . lang('Allow User to view pages, files, and submit custom forms within selected private folders.') . '</h5>
                                                <div class="alert alert-secondary">' . lang('For selected folders, you can enter an optional expiration date.') . ' (' . lang('leave blank for no expiration') . ').</div>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . get_date_picker_format() . '
                                                        ' . get_acl_folder_tree('view') . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Select Contact Groups to Import into') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_contact_groups_for_contact . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_import" value="Import" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Importing') ) . '" ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import Users') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form('import_users');

} else {
    validate_token_field();
    
    // if editor is not an administrator and the editor's role is less than or equal to the role that the editor is trying to set, then output error
    if (($user['role'] != 0) && ($user['role'] >= $_POST['role'])) {
        log_activity(lang('access denied because user does not have access to create a user with the requested role'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    
    // if no file was uploaded
    if (!$_FILES['file']['name']) {
        $liveform->mark_error('file', lang('Please select a file.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . h($_REQUEST['send_to']));
        exit();
    }

    // Fix Mac line-ending issue.
    ini_set('auto_detect_line_endings', true);
    
    // get file handle for uploaded CSV file
    $handle = fopen($_FILES['file']['tmp_name'], "r");
    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, ",");
    
    // if file is empty
    if (!$columns) {
        $liveform->mark_error('file', lang('The file was empty.'));
        fclose($handle);
        
        // Redirect user back to the import_users page
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . h($_REQUEST['send_to']));
        exit();
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
        
        // if column is username, then store key location for username
        if ($value == 'username') {
            $username_key = $key;
        }
    }
    
    // Setup variables
    $users_to_be_imported = array();
    $invalid_emails_count = 0;
    $invalid_email_list = '';
    $pre_existing_emails_count = 0;
    $pre_existing_email_list = '';
    $pre_existing_users_count = 0;
    $pre_existing_user_list = '';
    $import_user_error = false;

    // loops through all rows of data in CSV file
    while ($row = fgetcsv($handle, 100000, ",")) {
        $current_import_user_error = false;

        // If there is an email address in this row, then continue.
        if (trim($row[$email_address_key]) != '') {
            // Validate email
            if (validate_email_address($row[$email_address_key]) == FALSE) {
                $invalid_emails_count ++;
                if ($invalid_email_list) {
                    $invalid_email_list .= ', ';
                }
                $invalid_email_list .= $row[$email_address_key];
                $current_import_user_error = true;
                $import_user_error = true;
            }

            // If there is a username in this row, then check if the username
            // is already in use.
            if (trim($row[$username_key]) != '') {
                // Check if the username is already in use
                $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE (user_username = '" . escape($row[$username_key]) . "') OR (user_email = '" . escape($row[$username_key]) . "')") or output_error('Query failed');
                if (mysqli_num_rows($result) > 0)
                {
                    $pre_existing_users_count ++;
                    if ($pre_existing_user_list) {
                        $pre_existing_user_list .= ', ';
                    }
                    $pre_existing_user_list .= $row[$username_key];
                    $current_import_user_error = true;
                    $import_user_error = true;
                }
            }

            // Check if the email_address is already in use
            $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE (user_email = '" . escape($row[$email_address_key]) . "') OR (user_username = '" . escape($row[$email_address_key]) . "')") or output_error('Query failed');
            if (mysqli_num_rows($result) > 0)
            {
                $pre_existing_emails_count ++;
                if ($pre_existing_email_list) {
                    $pre_existing_email_list .= ', ';
                }
                $pre_existing_email_list .= $row[$email_address_key];
                $current_import_user_error = true;
                $import_user_error = true;
            }

            if ($current_import_user_error === false) {
                // If there are no errors, then prepare to add user.
                $users_to_be_imported[] = $row;
            }

        // Otherwise there is not an email address in this row, so output error.
        } else {
            // There was an improperly formatted row in the csv file so tell the user to fix it.
            $liveform->mark_error('file', lang('There are errors in your .csv file. Please check each users format and then try again.'));
            
            // Forward them back to the import users screen.
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . h($_REQUEST['send_to']));
            exit();
        }
        
    }

    fclose($handle);
 
    // If there is an error, mark it and redirect the user
    if ($import_user_error) {
        $combined_errors = '';
        
        // If there were emails that had an invalid format
        if ($invalid_emails_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} email address(es) were invalid. ({var:2})','vars'=>array($invalid_emails_count,$invalid_email_list))) . '</p>';
        }
        
        // If there were users that already exist
        if ($pre_existing_users_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} user name(s) already exist. ({var:2})','vars'=>array($pre_existing_users_count,$pre_existing_user_list))) . '</p>';
        }
        
        // If there were email addresses that already exist.
        if ($pre_existing_emails_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} email address(es) already exist. ({var:2})','vars'=>array($pre_existing_emails_count,$pre_existing_email_list))) . '</p>';
        }
        
      
        
        // Mark the errors.
        $liveform->mark_error('general_error', '<h4 class="alert-heading">' . lang('The file you selected could not be imported because of the following error(s)') . ':</h4>' . $combined_errors);
        
        // Forward them back to the import users screen.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . h($_REQUEST['send_to']));
        exit();
    }
    
    $contact_column_list = '';

    // Build list of contact column names for database query.
    foreach ($column_names as $key => $value) {
        // If this is not the username column, then add it.
        if ($value != 'username') {
            $contact_column_list .= "$value, ";
        }
    }

    $contact_column_list .= 'user, timestamp';

    $contact_groups = db_items("SELECT id FROM contact_groups");

    $contact_groups_for_contact = array();

    foreach ($contact_groups as $contact_group) {
        // If contact group was checked, add contact group to array.
        if (($_POST['contact_group_for_contact_' . $contact_group['id']] ?? '') == 1) {
            $contact_groups_for_contact[] = $contact_group;
        }
    }

    $imported_users = 0;
    
    // Loop through the valid users and import them
    foreach ($users_to_be_imported as $user_to_be_imported) {

        $username = trim($user_to_be_imported[$username_key]);
        $email_address = trim($user_to_be_imported[$email_address_key]);

        // If the username in the CSV file was blank,
        // then create a username by using everything before "@" in the email address,
        // and, if necessary, add numbers to the end to make it unique.
        if ($username == '') {
            $username = strtok($email_address, '@');
            $username = get_unique_username($username);
        }

        // If a user in the db already has the same username or email,
        // then don't add this user, and skip to the next one.
        if (
            db_value(
                "SELECT user_id
                FROM user
                WHERE
                    (user_username = '" . e($username) . "')
                    OR (user_username = '" . e($email_address) . "')
                    OR (user_email = '" . e($username) . "')
                    OR (user_email = '" . e($email_address) . "')")
        ) {
            continue;
        }

        $random_password = get_random_string(array(
            'type' => 'lowercase_letters',
            'length' => 10));

        $sql_set_page_type_columns = '';
        $sql_set_page_type_values = '';
            
        // if forms are enabled, then set the sql to update the set page type columns that are associated with this feature
        if (FORMS == true) {
            $sql_set_page_type_columns .= 
                " user_set_page_type_custom_form,
                user_set_page_type_custom_form_confirmation,
                user_set_page_type_form_list_view,
                user_set_page_type_form_item_view,
                user_set_page_type_form_view_directory,";
            
            $sql_set_page_type_values .= 
                " '" . escape($_POST['set_page_type_custom_form'] ?? '') . "',
                '" . escape($_POST['set_page_type_custom_form_confirmation'] ?? '') . "',
                '" . escape($_POST['set_page_type_form_list_view'] ?? '') . "',
                '" . escape($_POST['set_page_type_form_item_view'] ?? '') . "',
                '" . escape($_POST['set_page_type_form_view_directory'] ?? '') . "',";
        }
        
        // if calendars are enabled, then set the sql to update the set page type columns that are associated with this feature
        if (CALENDARS == true) {
            $sql_set_page_type_columns .= 
                " user_set_page_type_calendar_view,
                user_set_page_type_calendar_event_view,";
            
            $sql_set_page_type_values .= 
                " '" . escape($_POST['set_page_type_calendar_view'] ?? '') . "',
                '" . escape($_POST['set_page_type_calendar_event_view'] ?? '') . "',";
        }
        
        // if ecommerce is enabled, then set the sql to update the set page type columns that are associated with this feature
        if (ECOMMERCE == true) {
            $sql_set_page_type_columns .= 
                " user_set_page_type_catalog,
                user_set_page_type_catalog_detail,
                user_set_page_type_express_order,
                user_set_page_type_order_form,
                user_set_page_type_shopping_cart,
                user_set_page_type_shipping_address_and_arrival,
                user_set_page_type_shipping_method,
                user_set_page_type_billing_information,
                user_set_page_type_order_preview,
                user_set_page_type_order_receipt,";
            
            $sql_set_page_type_values .= 
                " '" . escape($_POST['set_page_type_catalog'] ?? '') . "',
                '" . escape($_POST['set_page_type_catalog_detail'] ?? '') . "',
                '" . escape($_POST['set_page_type_express_order'] ?? '') . "',
                '" . escape($_POST['set_page_type_order_form'] ?? '') . "',
                '" . escape($_POST['set_page_type_shopping_cart'] ?? '') . "',
                '" . escape($_POST['set_page_type_shipping_address_and_arrival'] ?? '') . "',
                '" . escape($_POST['set_page_type_shipping_method'] ?? '') . "',
                '" . escape($_POST['set_page_type_billing_information'] ?? '') . "',
                '" . escape($_POST['set_page_type_order_preview'] ?? '') . "',
                '" . escape($_POST['set_page_type_order_receipt'] ?? '') . "',";
        }
        
        // insert row into user table
        $query =
            "INSERT INTO user (
                user_username,
                user_email,
                user_password,
                user_role,
                user_home,
                user_badge,
                user_badge_label,
                user_reward_points,
                user_manage_contacts,
                user_manage_visitors,
                user_create_pages,
                user_delete_pages,
                user_manage_forms,
                user_manage_calendars,
                user_manage_emails,
                user_manage_ecommerce,
                user_view_card_data,
                manage_ecommerce_reports,
                user_set_offline_payment,
                user_publish_calendar_events,
                user_set_page_type_email_a_friend,
                user_set_page_type_folder_view,
                user_set_page_type_photo_gallery,
                $sql_set_page_type_columns
                user_timestamp,
                user_user)
            VALUES (
                '" . escape($username) . "',
                '" . escape($email_address) . "',
                '" . md5($random_password) . "',
                '" . escape($_POST['role'] ?? '') . "',
                '" . escape($_POST['home_page'] ?? '') . "',
                '" . escape($_POST['badge'] ?? '') . "',
                '" . escape($_POST['badge_label'] ?? '') . "',
                '" . escape($_POST['reward_points'] ?? '') . "',
                '" . escape($_POST['manage_contacts'] ?? '') . "',
                '" . escape($_POST['manage_visitors'] ?? '') . "',
                '" . escape($_POST['create_pages'] ?? '') . "',
                '" . escape($_POST['delete_pages'] ?? '') . "',
                '" . escape($_POST['manage_forms'] ?? '') . "',
                '" . escape($_POST['manage_calendars'] ?? '') . "',
                '" . escape($_POST['manage_emails'] ?? '') . "',
                '" . escape($_POST['manage_ecommerce'] ?? '') . "',
                '" . escape($_POST['view_card_data'] ?? '') . "',
                '" . e($_POST['manage_ecommerce_reports'] ?? '') . "',
                '" . escape($_POST['set_offline_payment'] ?? '') . "',
                '" . escape($_POST['publish_calendar_events'] ?? '') . "',
                '" . escape($_POST['set_page_type_email_a_friend'] ?? '') . "',
                '" . escape($_POST['set_page_type_folder_view'] ?? '') . "',
                '" . escape($_POST['set_page_type_photo_gallery'] ?? '') . "',
                $sql_set_page_type_values
                UNIX_TIMESTAMP(),
                '$user[id]')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $user_id = mysqli_insert_id(db::$con);
        
        // insert data into aclfolder table
        $result = mysqli_query(db::$con, "SELECT folder_id FROM folder") or output_error('Query failed');
        while($row = mysqli_fetch_array($result))
        {
            $folder_id = $row['folder_id'];
            $sql_expiration_date = "";
            
            // if the user was given edit rights to this folder, then set rights value to 2
            if (($_POST['edit_' . $folder_id] ?? '') == 1) {
                $rights = 2;
                
            // else if the user was given view rights to this folder, then deal with that.
            } elseif (($_POST['view_' . $folder_id] ?? '') == 1) {
                // Remove spaces from beginning and end of date.
                $expiration_date = trim($_POST['view_' . $folder_id . '_expiration_date']);

                // If an expiration date was entered, then validate it.
                if ($expiration_date != '') {
                    // Convert date to storage format.
                    $expiration_date = prepare_form_data_for_input($expiration_date, 'date');
                    
                    // Split date into parts.
                    $expiration_date_parts = explode('-', $expiration_date);
                    $year = $expiration_date_parts[0];
                    $month = $expiration_date_parts[1];
                    $day = $expiration_date_parts[2];
                    
                    // If the expiration date is valid, then give user access and set expiration date in SQL.
                    if ((is_numeric($month) == true) && (is_numeric($day) == true) && (is_numeric($year) == true) && (checkdate($month, $day, $year) == true)) {
                        $rights = 1;
                        $sql_expiration_date = $expiration_date;
                        
                    // Otherwise the expiration date is not valid, so do not give user access and do not set expiration date in SQL.
                    } else {
                        $rights = 0;
                    }

                // Otherwise an expiration date was not entered, so just give user access.
                } else {
                    $rights = 1;
                }
                
            // else the user was was given no rights to this folder, so set rights value to 0
            } else {
                $rights = 0;
            }
            
            $result2 = mysqli_query(db::$con, "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES ('$user_id', '$folder_id', '$rights', '" . escape($sql_expiration_date) . "')") or output_error('Query failed');
        }

        // if user that was created has a user role, then prepare to assign access to various items for user
        if ($_POST['role'] == 3) {
            // get all common regions
            $query = "SELECT cregion_id FROM cregion";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $common_regions = array();
            
            while ($row = mysqli_fetch_assoc($result)) {
                $common_regions[] = $row;
            }
            
            // loop through all common regions and input them if they were selected
            foreach ($common_regions as $common_region) {
                // if common region was selected for user to be given access to, give access to common region
                if (($_POST['common_region_' . $common_region['cregion_id']] ?? '') == 1) {
                    $query =
                        "INSERT INTO users_common_regions_xref (
                            user_id,
                            common_region_id)
                        VALUES (
                            '" . escape($user_id) . "',
                            '" . escape($common_region['cregion_id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // get all menus
            $query = "SELECT id FROM menus";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $menus = array();
            
            while ($row = mysqli_fetch_assoc($result)) {
                $menus[] = $row;
            }
            
            // loop through all menus and input them if they were selected
            foreach ($menus as $menu) {
                // if menu was selected for user to be given access to, give access to menu
                if (($_POST['menu_' . $menu['id']] ?? '') == 1) {
                    $query =
                        "INSERT INTO users_menus_xref (
                            user_id,
                            menu_id)
                        VALUES (
                            '" . escape($user_id) . "',
                            '" . escape($menu['id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // if manage contacts or manage e-mails was checked, check to see which contact groups the user needs to be given access to
            if (($_POST['manage_contacts'] == 'yes') || ($_POST['manage_emails'] == 'yes')) {
                // loop through all contact groups
                foreach ($contact_groups as $contact_group) {
                    // if contact group was selected for user to be given access to, give access to user to contact group
                    if (($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_contact_groups_xref (
                                user_id,
                                contact_group_id)
                            VALUES (
                                '" . $user_id . "',
                                '" . $contact_group['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
            
            // if manage calendars was checked, check to see which calendars the user needs to be given access to
            if ($_POST['manage_calendars'] == 'yes') {
                // get all calendars
                $query = "SELECT id FROM calendars";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $calendars = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $calendars[] = $row;
                }
                
                // loop through all calendars
                foreach ($calendars as $calendar) {
                    // if calendar was selected for user to be given access to, give access to user to calendar
                    if (($_POST['calendar_' . $calendar['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_calendars_xref (
                                user_id,
                                calendar_id)
                            VALUES (
                                '" . $user_id . "',
                                '" . $calendar['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }

            // If ads are enabled, then store access.
            if (ADS === true) {
                // get all ad regions
                $query = "SELECT id FROM ad_regions";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $ad_regions = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $ad_regions[] = $row;
                }
                
                // loop through all ad regions and input them if they were selected
                foreach ($ad_regions as $ad_region) {
                    // if ad region was selected for user to be given access to, give access to ad region
                    if (($_POST['ad_region_' . $ad_region['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_ad_regions_xref (
                                user_id,
                                ad_region_id)
                            VALUES (
                                '" . escape($user_id) . "',
                                '" . escape($ad_region['id']) . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
        }

        $contact_data_list = '';
        
        // Loop through columns in order to prepare contact data list.
        foreach ($column_names as $key => $value) {
            // If this is not the username column, then add data for it.
            if ($value != 'username') {
                $value = escape(trim($user_to_be_imported[$key]));
                $contact_data_list .= "'$value', ";
            }
        }

        $contact_data_list .= "'$user[id]', UNIX_TIMESTAMP()";

        // Create contact.
        db("INSERT INTO contacts ($contact_column_list) VALUES ($contact_data_list)");

        $contact_id = mysqli_insert_id(db::$con);
        
        // If there is an existing contact with this email address
        // that is currently opted-out, then opt-out the new contact also.
        if (db_value("SELECT id FROM contacts WHERE (opt_in = 0) AND (email_address = '" . escape($email_address) . "') AND (id != '$contact_id')")) {
            db("UPDATE contacts SET opt_in = 0 WHERE id = '$contact_id'");
        }

        // Connect user to contact.
        db(
            "UPDATE user
            SET user_contact = '" . $contact_id . "'
            WHERE user_id = '" . $user_id . "'");

        // Loop through all checked contact groups for contact
        // in order to add this contact to those groups.
        foreach ($contact_groups_for_contact as $contact_group) {
            db(
                "INSERT INTO contacts_contact_groups_xref (
                    contact_id,
                    contact_group_id)
                VALUES (
                    '" . $contact_id . "',
                    '" . $contact_group['id'] . "')");
        }

        // If the user checked to notify the user, then send email to user.
        if ($_POST['notify_user'] == 1) {
            $login = '';
            
            // if user's role is administrator, designer, or manager
            // or user has edit rights
            // or user has access to control panel
            // then set link to PATH/SOFTWARE_DIRECTORY/
            if (
                ($_POST['role'] < 3)
                || (no_acl_check($user_id) == true)
                || ($_POST['manage_calendars'] == 'yes')
                || ($_POST['manage_forms'] == 'yes')
                || ($_POST['manage_visitors'] == 'yes')
                || ($_POST['manage_contacts'] == 'yes')
                || ($_POST['manage_emails'] == 'yes')
                || ($_POST['manage_ecommerce'] == 'yes')
                || $_POST['manage_ecommerce_reports']
                || (count(get_items_user_can_edit('ad_regions', $user_id)) > 0)
            ) {
                $login = 
                    lang('Login') . ':' . "\n" .
                    URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . "\n";

            // else if there was a send to page selected for this user        
            } elseif ($_POST['home_page']) {
                $login =
                    lang('Login') . ':' . "\n" .
                    URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . encode_url_path(get_page_name($_POST['home_page'])) . "\n";
            }
            
            // e-mail user random password
            email(array(
                'to' => $email_address,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject' => lang('New Account'),
                'body' =>
lang('An administrator has created a new account for you.  You can find your login info below.') . "

" . lang('Email') . ": $email_address
" . lang('Password') . ": $random_password
                
$login"));

        }
        
        $imported_users ++;
    }
    
    log_activity(lang(array('string'=>'{var:1} users were imported','vars'=>$imported_users)), $_SESSION['sessionusername']);
    
    // Output Confirmation
    $liveform_view_users->add_notice(lang(array('string'=>'{var:1} users were imported','vars'=>$imported_users)));
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_REQUEST['send_to']);
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
    }
    exit();
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
        case 'emailaddress':
        case 'email':
            return('email_address');
            break;

        case 'username':
        case 'user':
            return('username');
            break;

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
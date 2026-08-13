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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// set memory limit to unlimited
ini_set('memory_limit', '-1');

include('init.php');

$user = validate_user();
validate_visitors_access($user);

$liveform = new liveform('view_visitor_report');

// If the base currency symbol is not defined, then that is because commerce
// is disabled, so we need to set a base currency.  This is the only area
// that shows currency when commerce is disabled.  In the future,
// we should probably consider not showing commerce data on this screen
// if commerce is disabled, however we are not going to spend time on that for now.
if (defined('BASE_CURRENCY_SYMBOL') == false) {
    define('BASE_CURRENCY_SYMBOL', '$');
}

// if an id was passed in the query string, then set id
if (isset($_GET['id']) == true) {
    $id = $_GET['id'];
    
// else if an id was passed in post, then set id
} elseif (isset($_POST['id']) == true) {
    $id = $_POST['id'];
}

// prepare query string with id if necessary
$query_string_id = '';

// if an id is set, then prepare query string with id
if (isset($id) == true) {
    $query_string_id = '?id=' . $id;
}

// prepare query string with id if necessary
$query_string_visitor_report_id = '';

// if an id is set, then prepare query string with id
if (isset($id) == true) {
    $query_string_visitor_report_id = '&visitor_report_id=' . $id;
}

// get session index in order to store different session values for this screen depending on the report that is being edited
$session_index = 0;

// if there is an id set, then set session index to id
if (isset($id) == true) {
    $session_index = $id;
}

// if the form has not been submitted, then display form and report
if (!$_POST) {
    $output_edit_button = '';
    $output_edit_form_style = '';
    
    // if the visitor report is being viewed
    if (isset($_GET['id']) == true) {
        $output_edit_button = '
        <nav id="button_bar" class="navigation " aria-label="Button Bar">
            <div class=" btn-group flex-wrap">
                <a class="btn btn-primary"  href="#" id="edit_button" onclick="document.getElementById(\'button_bar\').style.display = \'none\'; document.getElementById(\'edit_form\').style.display = \'block\'; return false;" ><span class="material-icons me-1">edit</span>' . lang('Edit') . '</a>
            </div>
        </nav>';
        $output_edit_form_style = '; display: none';
    }
    
    // if a visitor report is being created and this screen has not already been submitted, then set default values for form fields
    if ((isset($_GET['id']) == false) && ($liveform->field_in_session('submit_button') == false)) {
        // set default values for summarize by fields
        $liveform->assign_field_value('summarize_by_1', 'year');
        $liveform->assign_field_value('summarize_by_2', 'month');
        $liveform->assign_field_value('summarize_by_3', 'day');
    
    // else if a visitor report is being edited and this screen has not been submitted already, pre-populate fields with data
    } elseif ((isset($_GET['id']) == true) && ($liveform->field_in_session('id') == false)) {
        // get visitor report data
        $query =
            "SELECT
                name,
                detail,
                summarize_by_1,
                order_by_1,
                summarize_by_2,
                order_by_2,
                summarize_by_3,
                order_by_3
            FROM visitor_reports
            WHERE id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $output_visitor_report_name = $row['name'];
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('detail', $row['detail']);
        $liveform->assign_field_value('summarize_by_1', $row['summarize_by_1']);
        $liveform->assign_field_value('order_by_1', $row['order_by_1']);
        $liveform->assign_field_value('summarize_by_2', $row['summarize_by_2']);
        $liveform->assign_field_value('order_by_2', $row['order_by_2']);
        $liveform->assign_field_value('summarize_by_3', $row['summarize_by_3']);
        $liveform->assign_field_value('order_by_3', $row['order_by_3']);
    }
    
    // if a visitor report is being created, then prepare screen name
    if (isset($_GET['id']) == false) {
        $output_screen_name = 'Create Visitor Report';
        
    // else a visitor report is being viewed, so prepare screen name
    } else {
        $output_screen_name = 'View Visitor Report: <strong>' . $liveform->get_field_value('name') . '</strong>';
    }
    
    $output_hidden_id_field = '';
    
    // if a visitor report is being edited, then prepare to display hidden id field
    if (isset($_GET['id']) == true) {
        $output_hidden_id_field = $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$_GET['id']));
    }
    
    $summarize_by_options = array();

    $summarize_by_options[lang('-None-')] = '';
    $summarize_by_options[lang('General')] = '<optgroup>';
    $summarize_by_options[lang('Year')] = 'year';
    $summarize_by_options[lang('Month')] = 'month';
    $summarize_by_options[lang('Day')] = 'day';
    $summarize_by_options[lang('Site Search Terms')] = 'site_search_terms';
    
    // if multi currency is enabled, prepare currency option
    if (ECOMMERCE_MULTICURRENCY == true) {
        $summarize_by_options[lang('Currency')] = 'currency_code';
    }
    
    $summarize_by_options[''] = '</optgroup>';
    $summarize_by_options[lang('Referral')] = '<optgroup>';
    $summarize_by_options[lang('URL')] = 'http_referer';
    $summarize_by_options[lang('Host Name')] = 'referring_host_name';
    $summarize_by_options[lang('Search Engine')] = 'referring_search_engine';
    $summarize_by_options[lang('Search Terms')] = 'referring_search_terms';
    $summarize_by_options[lang('Pay Per Click / Organic')] = 'pay_per_click_organic';
    $summarize_by_options[lang('First Visit')] = 'first_visit';
    $summarize_by_options[lang('Landing Page')] = 'landing_page_name';
    $summarize_by_options[lang('Tracking Code')] = 'tracking_code';
    
    // if affiliate program is enabled, prepare affiliate code option
    if (AFFILIATE_PROGRAM == true) {
        $summarize_by_options[lang('Affiliate Code')] = 'affiliate_code';
    }
    
    $summarize_by_options[''] = '</optgroup>';

    $summarize_by_options[lang('UTM')] = '<optgroup>';
    $summarize_by_options[lang('Source')] = 'utm_source';
    $summarize_by_options[lang('Medium')] = 'utm_medium';
    $summarize_by_options[lang('Campaign')] = 'utm_campaign';
    $summarize_by_options[lang('Term')] = 'utm_term';
    $summarize_by_options[lang('Content')] = 'utm_content';
    $summarize_by_options[''] = '</optgroup>';

    $summarize_by_options[lang('Action')] = '<optgroup>';
    $summarize_by_options[lang('Page Views')] = 'page_views';
    $summarize_by_options[lang('Custom Form Submitted')] = 'custom_form_submitted';
    $summarize_by_options[lang('Custom Form Name')] = 'custom_form_name';
    $summarize_by_options[lang('Order Created')] = 'order_created';
    $summarize_by_options[lang('Order Retrieved')] = 'order_retrieved';
    $summarize_by_options[lang('Order Checked Out')] = 'order_checked_out';
    $summarize_by_options[lang('Order Completed')] = 'order_completed';
    $summarize_by_options[''] = '</optgroup>';
    $summarize_by_options[lang('Location')] = '<optgroup>';
    $summarize_by_options[lang('City')] = 'city';
    $summarize_by_options[lang('State')] = 'state';
    $summarize_by_options[lang('Zip Code')] = 'zip_code';
    $summarize_by_options[lang('Country')] = 'country';
    $summarize_by_options[''] = '</optgroup>';
    
    $order_by_options = array(
        lang('alphabet') => 'alphabet',
        lang('number of visitors') => 'number of visitors',
        lang('number of page views') => 'number of page views',
        lang('order total') => 'order total'
    );
    
    // get filters
    $filters = array();
    
    // if a visitor report is being created or a form has been submitted, then get filters from liveform
    if ((isset($_GET['id']) == false) || ($liveform->field_in_session('submit_button') == true)) {
        // loop through all filters in order to add them to array
        for ($i = 1; $i <= $liveform->get_field_value('last_filter_number'); $i++) {
            // if filter exists and an operator was selected for this filter, then add filter to array
            if ($liveform->get_field_value('filter_' . $i . '_operator') != '') {
                // if user entered a value, clear dynamic value, in order to prevent user from using two values
                if ($liveform->get_field_value('filter_' . $i . '_value') != '') {
                    $dynamic_value = '';
                    $dynamic_value_attribute = '';
                } else {
                    $dynamic_value = $liveform->get_field_value('filter_' . $i . '_dynamic_value');
                    
                    // if days ago was selected for dynamic value, then set dynamic value attribute
                    if ($dynamic_value == 'days ago') {
                        $dynamic_value_attribute = $liveform->get_field_value('filter_' . $i . '_dynamic_value_attribute');
                    } else {
                        $dynamic_value_attribute = '';
                    }
                }
                
                $filters[] = array(
                    'field' => $liveform->get_field_value('filter_' . $i . '_field'),
                    'operator' => $liveform->get_field_value('filter_' . $i . '_operator'),
                    'value' => $liveform->get_field_value('filter_' . $i . '_value'),
                    'dynamic_value' => $dynamic_value,
                    'dynamic_value_attribute' => $dynamic_value_attribute
                );
            }
        }
        
    // else a visitor report is being edited and a form has not been submitted, so get filters from database
    } else {
        $query =
            "SELECT
                field,
                operator,
                value,
                dynamic_value,
                dynamic_value_attribute
            FROM visitor_report_filters
            WHERE visitor_report_id = '" . escape($_GET['id']) . "'
            ORDER BY id";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        while ($row = mysqli_fetch_assoc($result)) {
            // get field type
            $field_type = '';
            
            // if the field for this filter is date, then set type to date
            if ($row['field'] == 'date') {
                $field_type = 'date';
            }
            
            $filters[] = array(
                'field' => $row['field'],
                'operator' => $row['operator'],
                'value' => prepare_form_data_for_output($row['value'], $field_type),
                'dynamic_value' => $row['dynamic_value'],
                'dynamic_value_attribute' => $row['dynamic_value_attribute']
            );
        }
    }
    
    // initialize variables
    $date_filter_exists = false;
    $output_filters_for_javascript = '';
    $count = 0;
    
    // loop through filters in order to prepare output for javascript
    foreach ($filters as $filter) {
        // if the field for this filter is date, then remember that a date filter exists
        if ($filter['field'] == 'date') {
            $date_filter_exists = true;
        }
        
        // if dynamic value attribute is equal to 0, then set to empty string
        if ($filter['dynamic_value_attribute'] == 0) {
            $filter['dynamic_value_attribute'] = '';
        }
        
        $output_filters_for_javascript .=
            'filters[' . $count . '] = new Array();
            filters[' . $count . ']["field"] = "' . $filter['field'] . '";
            filters[' . $count . ']["operator"] = "' . $filter['operator'] . '";
            filters[' . $count . ']["value"] = "' . escape_javascript($filter['value']) . '";
            filters[' . $count . ']["dynamic_value"] = "' . $filter['dynamic_value'] . '";
            filters[' . $count . ']["dynamic_value_attribute"] = "' . $filter['dynamic_value_attribute'] . '";' . "\n";
        
        $count++;
    }
    
    // set field options
    $field_options = array();
    $field_options[] = array('name' => lang('General'), 'value' => '<optgroup>');
    $field_options[] = array('name' => lang('Date'), 'value' => 'date', 'type' => 'date');
    $field_options[] = array('name' => lang('Site Search Terms'), 'value' => 'site_search_terms');
    
    // if multi currency is enabled, prepare currency option
    if (ECOMMERCE_MULTICURRENCY == true) {
        $currency_options = array();
        
        // get currencies in order to build currency options
        $query =
            "SELECT
                name,
                code
            FROM currencies
            ORDER BY
                base DESC,
                name ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // loop through each currency in order to add it to array
        while ($row = mysqli_fetch_assoc($result)) {
            $currency_option = array(
                'name' => $row['name'] . ' (' . $row['code'] . ')',
                'value' => $row['code']
            );

            $currency_options[] = $currency_option;
        }
        
        $field_options[] = array('name' => lang('Currency'), 'value' => 'currency_code', 'value_options' => $currency_options);
    }
    
    $field_options[] = array('name' => '', 'value' => '</optgroup>');
    $field_options[] = array('name' => lang('Referral'), 'value' => '<optgroup>');
    $field_options[] = array('name' => lang('URL'), 'value' => 'http_referer');
    $field_options[] = array('name' => lang('Host Name'), 'value' => 'referring_host_name');
    
    // create array for referring search engines
    $referring_search_engines = array(
        'AlltheWeb',
        'AltaVista',
        'AOL',
        'Ask.com',
        'Bing',
        'Comet Web Search',
        'EarthLink',
        'Excite',
        'Google',
        'HotBot',
        'LookSmart',
        'Lycos',
        'Mamma.com',
        'MetaCrawler',
        'MSN',
        'Netscape',
        'Open Directory Project',
        'Overture',
        'Viewpoint',
        'WebCrawler',
        'Yahoo!'
    );
    
    $referring_search_engine_options = array();
    
    // loop through each referring search engine in order to add it to array
    foreach ($referring_search_engines as $referring_search_engine) {
        $referring_search_engine_options[] = array(
            'name' => $referring_search_engine,
            'value' => $referring_search_engine
        );
    }
    
    $field_options[] = array('name' => lang('Search Engine'), 'value' => 'referring_search_engine', 'value_options' => $referring_search_engine_options);
    $field_options[] = array('name' => lang('Search Terms'), 'value' => 'referring_search_terms');
    
    $pay_per_click_organic_options = array();
    $pay_per_click_organic_options[] = array('name' => lang('Pay Per Click'), 'value' => 'pay_per_click');
    $pay_per_click_organic_options[] = array('name' => lang('Organic'), 'value' => 'organic');
    $pay_per_click_organic_options[] = array('name' => lang('Neither'), 'value' => 'neither');
    
    $field_options[] = array('name' => lang('Pay Per Click / Organic'), 'value' => 'pay_per_click_organic', 'value_options' => $pay_per_click_organic_options);
    
    $first_visit_options = array();
    $first_visit_options[] = array('name' => lang('First Visit'), 'value' => '1');
    $first_visit_options[] = array('name' => lang('Return Visit'), 'value' => '0');
    
    $field_options[] = array('name' => lang('First Visit'), 'value' => 'first_visit', 'value_options' => $pay_per_click_organic_options);
    $field_options[] = array('name' => lang('Landing Page'), 'value' => 'landing_page_name');
    $field_options[] = array('name' => lang('Tracking Code'), 'value' => 'tracking_code');
    
    // if the affiliate program is enabled, then prepare affiliate code field
    if (AFFILIATE_PROGRAM == true) {
        $field_options[] = array('name' => lang('Affiliate Code'), 'value' => 'affiliate_code');
    }
    
    $field_options[] = array('name' => '', 'value' => '</optgroup>');

    $field_options[] = array('name' => lang('UTM'), 'value' => '<optgroup>');
    $field_options[] = array('name' => lang('Source'), 'value' => 'utm_source');
    $field_options[] = array('name' => lang('Medium'), 'value' => 'utm_medium');
    $field_options[] = array('name' => lang('Campaign'), 'value' => 'utm_campaign');
    $field_options[] = array('name' => lang('Term'), 'value' => 'utm_term');
    $field_options[] = array('name' => lang('Content'), 'value' => 'utm_content');
    $field_options[] = array('name' => '', 'value' => '</optgroup>');

    $field_options[] = array('name' => lang('Action'), 'value' => '<optgroup>');
    $field_options[] = array('name' => lang('Page Views'), 'value' => 'page_views');
    
    $custom_form_submitted_options = array();
    $custom_form_submitted_options[] = array('name' => lang('Submitted'), 'value' => '1');
    $custom_form_submitted_options[] = array('name' => lang('Not Submitted'), 'value' => '0');
    
    $field_options[] = array('name' => lang('Custom Form Submitted'), 'value' => 'custom_form_submitted', 'value_options' => $custom_form_submitted_options);
    $field_options[] = array('name' => lang('Custom Form'), 'value' => 'custom_form_name');
    
    $order_created_options = array();
    $order_created_options[] = array('name' => lang('Created'), 'value' => '1');
    $order_created_options[] = array('name' => lang('Not Created'), 'value' => '0');
    
    $field_options[] = array('name' => lang('Order Created'), 'value' => 'order_created', 'value_options' => $order_created_options);
    
    $order_retrieved_options = array();
    $order_retrieved_options[] = array('name' => lang('Retrieved'), 'value' => '1');
    $order_retrieved_options[] = array('name' => lang('Not Retrieved'), 'value' => '0');
    
    $field_options[] = array('name' => lang('Order Retrieved'), 'value' => 'order_retrieved', 'value_options' => $order_retrieved_options);
    
    $order_checked_out_options = array();
    $order_checked_out_options[] = array('name' => lang('Checked Out'), 'value' => '1');
    $order_checked_out_options[] = array('name' => lang('Not Checked Out'), 'value' => '0');
    
    $field_options[] = array('name' => lang('Order Checked Out'), 'value' => 'order_checked_out', 'value_options' => $order_checked_out_options);
    
    $order_completed_options = array();
    $order_completed_options[] = array('name' => lang('Completed'), 'value' => '1');
    $order_completed_options[] = array('name' => lang('Not Completed'), 'value' => '0');
    
    $field_options[] = array('name' => lang('Order Completed'), 'value' => 'order_completed', 'value_options' => $order_completed_options);
    $field_options[] = array('name' => '', 'value' => '</optgroup>');
    $field_options[] = array('name' => lang('Location'), 'value' => '<optgroup>');
    $field_options[] = array('name' => lang('City'), 'value' => 'city');
    $field_options[] = array('name' => lang('State'), 'value' => 'state');
    $field_options[] = array('name' => lang('Zip Code'), 'value' => 'zip_code');
    $field_options[] = array('name' => lang('Country'), 'value' => 'country');
    $field_options[] = array('name' => lang('IP Address'), 'value' => 'ip_address');
    $field_options[] = array('name' => '', 'value' => '</optgroup>');
    
    $output_field_options_for_javascript = '';
    $count = 0;

    // loop through all field options in order to prepare javascript array
    foreach ($field_options as $field_option) {
        $output_field_options_for_javascript .=
            'field_options[' . $count . '] = new Array();
            field_options[' . $count . ']["name"] = "' . escape_javascript($field_option['name']) . '";
            field_options[' . $count . ']["value"] = "' . escape_javascript($field_option['value']) . '";
            field_options[' . $count . ']["type"] = "' . escape_javascript($field_option['type']) . '";' . "\n";
        
        // if there are value options, then add value options to javascript array
        if (isset($field_option['value_options']) == true) {
            $output_field_options_for_javascript .=
                'field_options[' . $count . ']["value_options"] = new Array();' . "\n";
            
            $count_2 = 0;
            
            // loop through value options in order to add options to javascript array
            foreach ($field_option['value_options'] as $value_option) {
                $output_field_options_for_javascript .=
                    'field_options[' . $count . ']["value_options"][' . $count_2 . '] = new Array();
                    field_options[' . $count . ']["value_options"][' . $count_2 . ']["name"] = "' . escape_javascript($value_option['name']) . '";
                    field_options[' . $count . ']["value_options"][' . $count_2 . ']["value"] = "' . escape_javascript($value_option['value']) . '";' . "\n";
                    
                $count_2++;
            }
        }
        
        $count++;
    }
    
    $output_cancel_button = '';
    
    // if the user is creating a visitor report, then prepare cancel button to send user back a page
    if (isset($_GET['id']) == false) {
        $output_cancel_button = '';
    
    // else the user is editing a visitor report, so prepare cancel button to hide edit form and show edit button
    } else {
        $output_cancel_button = '<button type="button" name="cancel" value="Cancel" onclick="document.getElementById(\'button_bar\').style.display = \'block\'; document.getElementById(\'edit_form\').style.display = \'none\';" class="btn my-1 btn-secondary"><span class="material-icons me-2">close</span><span class="btn-text" >' . lang(array('string'=>'Cancel Edit') ) . '</span></button>';
    }
    
    $output_delete_button = '';
    
    // if user is editing an existing visitor report, then prepare to output delete button
    if (isset($_GET['id']) == true) {
        $output_delete_button = '<button type="submit" name="submit_button" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('visitor report')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }
    
    // if there are no date filters, then prepare to output date changer
    if ($date_filter_exists == false) {
        // if the date has not been set in the session yet, populate start and stop days with default, which is the past week
        if (isset($_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month']) == false) {
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'] = date('m', time() - 2678400);
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'] = date('d', time() - 2678400);
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] = date('Y', time() - 2678400);
            
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_month'] = date('m');
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_day'] = date('d');
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_year'] = date('Y');
            
        // else if the date has been passed in the query string, then set date in session
        } elseif (isset($_GET['start_month']) == true) {
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'] = $_GET['start_month'];
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'] = $_GET['start_day'];
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] = $_GET['start_year'];
            
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_month'] = $_GET['stop_month'];
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_day'] = $_GET['stop_day'];
            $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_year'] = $_GET['stop_year'];
        }
        
        $decrease_year['start_month'] = '01';
        $decrease_year['start_day'] = '01';
        $decrease_year['start_year'] = $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] - 1;
        $decrease_year['stop_month'] = '12';
        $decrease_year['stop_day'] = '31';
        $decrease_year['stop_year'] = $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] - 1;

        $current_year['start_month'] = '01';
        $current_year['start_day'] = '01';
        $current_year['start_year'] = date('Y');
        $current_year['stop_month'] = '12';
        $current_year['stop_day'] = '31';
        $current_year['stop_year'] = date('Y');

        $increase_year['start_month'] = '01';
        $increase_year['start_day'] = '01';
        $increase_year['start_year'] = $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] + 1;
        $increase_year['stop_month'] = '12';
        $increase_year['stop_day'] = '31';
        $increase_year['stop_year'] = $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year'] + 1;

        $decrease_month['new_time'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'] - 1, 1, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $decrease_month['new_month'] = date('m', $decrease_month['new_time']);
        $decrease_month['new_year'] = date('Y', $decrease_month['new_time']);
        $decrease_month['start_month'] = $decrease_month['new_month'];
        $decrease_month['start_day'] = '01';
        $decrease_month['start_year'] = $decrease_month['new_year'];
        $decrease_month['stop_month'] = $decrease_month['new_month'];
        $decrease_month['stop_day'] = date('t', $decrease_month['new_time']);
        $decrease_month['stop_year'] = $decrease_month['new_year'];

        $current_month['new_month'] = date('m');
        $current_month['new_year'] = date('Y');
        $current_month['start_month'] = $current_month['new_month'];
        $current_month['start_day'] = '01';
        $current_month['start_year'] = $current_month['new_year'];
        $current_month['stop_month'] = $current_month['new_month'];
        $current_month['stop_day'] = date('t');
        $current_month['stop_year'] = $current_month['new_year'];

        $increase_month['new_time'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'] + 1, 1, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $increase_month['new_month'] = date('m', $increase_month['new_time']);
        $increase_month['new_year'] = date('Y', $increase_month['new_time']);
        $increase_month['start_month'] = $increase_month['new_month'];
        $increase_month['start_day'] = '01';
        $increase_month['start_year'] = $increase_month['new_year'];
        $increase_month['stop_month'] = $increase_month['new_month'];
        $increase_month['stop_day'] = date('t', $increase_month['new_time']);
        $increase_month['stop_year'] = $increase_month['new_year'];

        $decrease_week['start_date_timestamp'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $decrease_week['new_time_start'] = strtotime('last Sunday', $decrease_week['start_date_timestamp']);
        $decrease_week['new_time_stop'] = strtotime('Saturday', $decrease_week['new_time_start']);
        $decrease_week['start_month'] = date('m', $decrease_week['new_time_start']);
        $decrease_week['start_day'] = date('d', $decrease_week['new_time_start']);
        $decrease_week['start_year'] = date('Y', $decrease_week['new_time_start']);
        $decrease_week['stop_month'] = date('m', $decrease_week['new_time_stop']);
        $decrease_week['stop_day'] = date('d', $decrease_week['new_time_stop']);
        $decrease_week['stop_year'] = date('Y', $decrease_week['new_time_stop']);

        // if today is Sunday
        if (date('l') == 'Sunday') {
            $current_week['new_time_start'] = strtotime('Sunday');
        } else {
            $current_week['new_time_start'] = strtotime('last Sunday');
        }
        $current_week['new_time_stop'] = strtotime('Saturday', $current_week['new_time_start']);
        $current_week['start_month'] = date('m', $current_week['new_time_start']);
        $current_week['start_day'] = date('d', $current_week['new_time_start']);
        $current_week['start_year'] = date('Y', $current_week['new_time_start']);
        $current_week['stop_month'] = date('m', $current_week['new_time_stop']);
        $current_week['stop_day'] = date('d', $current_week['new_time_stop']);
        $current_week['stop_year'] = date('Y', $current_week['new_time_stop']);

        $increase_week['start_date_timestamp'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $increase_week['new_time_start'] = strtotime('next Sunday', $increase_week['start_date_timestamp']);
        $increase_week['new_time_stop'] = strtotime('Saturday', $increase_week['new_time_start']);
        $increase_week['start_month'] = date('m', $increase_week['new_time_start']);
        $increase_week['start_day'] = date('d', $increase_week['new_time_start']);
        $increase_week['start_year'] = date('Y', $increase_week['new_time_start']);
        $increase_week['stop_month'] = date('m', $increase_week['new_time_stop']);
        $increase_week['stop_day'] = date('d', $increase_week['new_time_stop']);
        $increase_week['stop_year'] = date('Y', $increase_week['new_time_stop']);

        $decrease_day['new_time'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'] - 1, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $decrease_day['new_month'] = date('m', $decrease_day['new_time']);
        $decrease_day['new_day'] = date('d', $decrease_day['new_time']);
        $decrease_day['new_year'] = date('Y', $decrease_day['new_time']);
        $decrease_day['start_month'] = $decrease_day['new_month'];
        $decrease_day['start_day'] = $decrease_day['new_day'];
        $decrease_day['start_year'] = $decrease_day['new_year'];
        $decrease_day['stop_month'] = $decrease_day['new_month'];
        $decrease_day['stop_day'] = $decrease_day['new_day'];
        $decrease_day['stop_year'] = $decrease_day['new_year'];
        
        $current_day['new_month'] = date('m');
        $current_day['new_day'] = date('d');
        $current_day['new_year'] = date('Y');
        $current_day['start_month'] = $current_day['new_month'];
        $current_day['start_day'] = $current_day['new_day'];
        $current_day['start_year'] = $current_day['new_year'];
        $current_day['stop_month'] = $current_day['new_month'];
        $current_day['stop_day'] = $current_day['new_day'];
        $current_day['stop_year'] = $current_day['new_year'];
        
        $increase_day['new_time'] = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'] + 1, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $increase_day['new_month'] = date('m', $increase_day['new_time']);
        $increase_day['new_day'] = date('d', $increase_day['new_time']);
        $increase_day['new_year'] = date('Y', $increase_day['new_time']);
        $increase_day['start_month'] = $increase_day['new_month'];
        $increase_day['start_day'] = $increase_day['new_day'];
        $increase_day['start_year'] = $increase_day['new_year'];
        $increase_day['stop_month'] = $increase_day['new_month'];
        $increase_day['stop_day'] = $increase_day['new_day'];
        $increase_day['stop_year'] = $increase_day['new_year'];
        
        // get timestamps for start and stop dates
        $start_timestamp = mktime(0, 0, 0, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_day'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['start_year']);
        $stop_timestamp = mktime(23, 59, 59, $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_month'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_day'], $_SESSION['software']['statistics']['view_visitor_report'][$session_index]['stop_year']);
        
        // prepare query string with id if necessary
        $output_date_changer_query_string_id = '';

        // if an id is set, then prepare query string with id
        if (isset($id) == true) {
            $output_date_changer_query_string_id = '&id=' . $id;
        }
        
        $start_date = get_absolute_time(array('timestamp' => $start_timestamp, 'type' => 'date'));
        $stop_date = get_absolute_time(array('timestamp' => $stop_timestamp, 'type' => 'date'));
        
        $output_date_range = $start_date;
        
        // if the stop date is different from the start date, then add stop date to date range
        if ($stop_date != $start_date) {
            $output_date_range .= ' - ' . $stop_date;
        }
        
        $output_date_changer = '
        <div class="row justify-content-center justify-content-md-end">
            <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_visitor_report.php?start_month=' . $decrease_year['start_month'] . '&start_day=' . $decrease_year['start_day'] . '&start_year=' . $decrease_year['start_year'] . '&stop_month=' . $decrease_year['stop_month'] . '&stop_day=' . $decrease_year['stop_day'] . '&stop_year=' . $decrease_year['stop_year'] . $output_date_changer_query_string_id . '"><</a>
                <a class="btn py-0 px-1 border-bottom border-top" href="view_visitor_report.php?start_month=' . $current_year['start_month'] . '&start_day=' . $current_year['start_day'] . '&start_year=' . $current_year['start_year'] . '&stop_month=' . $current_year['stop_month'] . '&stop_day=' . $current_year['stop_day'] . '&stop_year=' . $current_year['stop_year'] . $output_date_changer_query_string_id . '">' . lang('Year') . '</a>
                <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_visitor_report.php?start_month=' . $increase_year['start_month'] . '&start_day=' . $increase_year['start_day'] . '&start_year=' . $increase_year['start_year'] . '&stop_month=' . $increase_year['stop_month'] . '&stop_day=' . $increase_year['stop_day'] . '&stop_year=' . $increase_year['stop_year'] . $output_date_changer_query_string_id . '">></a>
            </div>
            <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_visitor_report.php?start_month=' . $decrease_month['start_month'] . '&start_day=' . $decrease_month['start_day'] . '&start_year=' . $decrease_month['start_year'] . '&stop_month=' . $decrease_month['stop_month'] . '&stop_day=' . $decrease_month['stop_day'] . '&stop_year=' . $decrease_month['stop_year'] . $output_date_changer_query_string_id . '"><</a>
                <a class="btn py-0 px-1 border-bottom border-top" href="view_visitor_report.php?start_month=' . $current_month['start_month'] . '&start_day=' . $current_month['start_day'] . '&start_year=' . $current_month['start_year'] . '&stop_month=' . $current_month['stop_month'] . '&stop_day=' . $current_month['stop_day'] . '&stop_year=' . $current_month['stop_year'] . $output_date_changer_query_string_id . '">' . lang('Month') . '</a>
                <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_visitor_report.php?start_month=' . $increase_month['start_month'] . '&start_day=' . $increase_month['start_day'] . '&start_year=' . $increase_month['start_year'] . '&stop_month=' . $increase_month['stop_month'] . '&stop_day=' . $increase_month['stop_day'] . '&stop_year=' . $increase_month['stop_year'] . '">></a>
            </div>
            <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_visitor_report.php?start_month=' . $decrease_week['start_month'] . '&start_day=' . $decrease_week['start_day'] . '&start_year=' . $decrease_week['start_year'] . '&stop_month=' . $decrease_week['stop_month'] . '&stop_day=' . $decrease_week['stop_day'] . '&stop_year=' . $decrease_week['stop_year'] . $output_date_changer_query_string_id . '"><</a>
                <a class="btn py-0 px-1 border-bottom border-top" href="view_visitor_report.php?start_month=' . $current_week['start_month'] . '&start_day=' . $current_week['start_day'] . '&start_year=' . $current_week['start_year'] . '&stop_month=' . $current_week['stop_month'] . '&stop_day=' . $current_week['stop_day'] . '&stop_year=' . $current_week['stop_year'] . $output_date_changer_query_string_id . '">' . lang('Week') . '</a>
                <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_visitor_report.php?start_month=' . $increase_week['start_month'] . '&start_day=' . $increase_week['start_day'] . '&start_year=' . $increase_week['start_year'] . '&stop_month=' . $increase_week['stop_month'] . '&stop_day=' . $increase_week['stop_day'] . '&stop_year=' . $increase_week['stop_year'] . $output_date_changer_query_string_id . '">></a>
            </div>
            <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_visitor_report.php?start_month=' . $decrease_day['start_month'] . '&start_day=' . $decrease_day['start_day'] . '&start_year=' . $decrease_day['start_year'] . '&stop_month=' . $decrease_day['stop_month'] . '&stop_day=' . $decrease_day['stop_day'] . '&stop_year=' . $decrease_day['stop_year'] . $output_date_changer_query_string_id . '"><</a>
                <a class="btn py-0 px-1 border-bottom border-top" href="view_visitor_report.php?start_month=' . $current_day['start_month'] . '&start_day=' . $current_day['start_day'] . '&start_year=' . $current_day['start_year'] . '&stop_month=' . $current_day['stop_month'] . '&stop_day=' . $current_day['stop_day'] . '&stop_year=' . $current_day['stop_year'] . $output_date_changer_query_string_id . '">' . lang('Day') . '</a>
                <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_visitor_report.php?start_month=' . $increase_day['start_month'] . '&start_day=' . $increase_day['start_day'] . '&start_year=' . $increase_day['start_year'] . '&stop_month=' . $increase_day['stop_month'] . '&stop_day=' . $increase_day['stop_day'] . '&stop_year=' . $increase_day['stop_year'] . $output_date_changer_query_string_id . '">></a>
            </div>    
        </div>
        <p class="text-center text-md-end p-0 m-0">
            <span class="badge text-dark fw-light border-2">    ' . $output_date_range . '</span>
        </p>';

    }
    
    // start where
    $where = '';
    
    // loop through all filters in order to prepare SQL
    foreach ($filters as $filter) {
        // get operand 1 and field type when necessary
        $operand_1 = '';
        $field_type = '';
        
        switch ($filter['field']) {
            case 'date': $operand_1 = 'FROM_UNIXTIME(visitors.start_timestamp, \'%Y-%m-%d\')'; $field_type = 'date'; break;
            case 'site_search_terms': $operand_1 = 'visitors.site_search_terms'; break;
            case 'currency_code': $operand_1 = 'visitors.currency_code'; break;
            case 'http_referer': $operand_1 = 'visitors.http_referer'; break;
            case 'referring_host_name': $operand_1 = 'visitors.referring_host_name'; break;
            case 'referring_search_engine': $operand_1 = 'visitors.referring_search_engine'; break;
            case 'referring_search_terms': $operand_1 = 'visitors.referring_search_terms'; break;
            case 'pay_per_click_organic': $operand_1 = 'CASE WHEN (visitors.tracking_code LIKE "%' . escape(escape_like(PAY_PER_CLICK_FLAG)) . '%") THEN "pay_per_click" WHEN (visitors.referring_search_engine != "") THEN "organic" ELSE "neither" END'; break;
            case 'first_visit': $operand_1 = 'visitors.first_visit'; break;
            case 'landing_page_name': $operand_1 = 'visitors.landing_page_name'; break;
            case 'tracking_code': $operand_1 = 'visitors.tracking_code'; break;
            case 'affiliate_code': $operand_1 = 'visitors.affiliate_code'; break;

            case 'utm_source': $operand_1 = 'visitors.utm_source'; break;
            case 'utm_medium': $operand_1 = 'visitors.utm_medium'; break;
            case 'utm_campaign': $operand_1 = 'visitors.utm_campaign'; break;
            case 'utm_term': $operand_1 = 'visitors.utm_term'; break;
            case 'utm_content': $operand_1 = 'visitors.utm_content'; break;
            
            case 'page_views': $operand_1 = 'visitors.page_views'; break;
            case 'custom_form_submitted': $operand_1 = 'visitors.custom_form_submitted'; break;
            case 'custom_form_name': $operand_1 = 'visitors.custom_form_name'; break;
            case 'order_created': $operand_1 = 'visitors.order_created'; break;
            case 'order_retrieved': $operand_1 = 'visitors.order_retrieved'; break;
            case 'order_checked_out': $operand_1 = 'visitors.order_checked_out'; break;
            case 'order_completed': $operand_1 = 'visitors.order_completed'; break;
            case 'city': $operand_1 = 'visitors.city'; break;
            case 'state': $operand_1 = 'visitors.state'; break;
            case 'zip_code': $operand_1 = 'visitors.zip_code'; break;
            case 'country': $operand_1 = 'visitors.country'; break;
            case 'ip_address': $operand_1 = 'INET_NTOA(visitors.ip_address)'; break;
        }
        
        // if a basic value was entered, use that value
        if ($filter['value'] != '') {
            $operand_2 = prepare_form_data_for_input($filter['value'], $field_type);
            
        // else a dynamic value was entered, so use dynamic value
        } else {
            $operand_2 = get_dynamic_value($filter['dynamic_value'], $filter['dynamic_value_attribute']);
        }
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";
            
        // else where is not blank, so add and
        } else {
            $where .= "AND ";
        }
        
        $where .= "(" . prepare_sql_operation($filter['operator'], $operand_1, $operand_2) . ") ";
    }
    
    // if there are no date filters, then add filter for date changer
    if ($date_filter_exists == false) {
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";
            
        // else where is not blank, so add and
        } else {
            $where .= "AND ";
        }
        
        // add start and stop timestamp to where clause
        $where .= "(visitors.start_timestamp >= $start_timestamp) AND (visitors.start_timestamp <= $stop_timestamp)";
    }
    
    if ($liveform->get_field_value('summarize_by_1') != '') {
        $sql_summarize_by_1 = get_summarize_by_column($liveform->get_field_value('summarize_by_1')) . ',';
        $number_of_summarize_bys = 1;
        
        if ($liveform->get_field_value('summarize_by_2') != '') {
            $sql_summarize_by_2 = get_summarize_by_column($liveform->get_field_value('summarize_by_2')) . ',';
            $number_of_summarize_bys = 2;
            
            if ($liveform->get_field_value('summarize_by_3') != '') {
                $sql_summarize_by_3 = get_summarize_by_column($liveform->get_field_value('summarize_by_3')) . ',';
                $number_of_summarize_bys = 3;
            }
        }
        
    } else {
        $number_of_summarize_bys = 0;
    }
    
    // ── Aggregate in the database, not in PHP ────────────────────────────
    //
    // This block used to select every matching visitor row with no limit,
    // walk the result set in PHP and add the totals up by hand. On a site
    // taking 100,000-200,000 visits a day a few weeks of that is millions of
    // rows, and the page stopped opening at all: the result set was pulled
    // into memory row by row, and each row was also appended to a per-group
    // 'visitors' array — which was built even when the detail checkbox was
    // off and then never read. Add an ORDER BY over FROM_UNIXTIME()
    // expressions, which no index can serve, and MySQL was sorting the whole
    // history on disk before PHP saw the first row.
    //
    // MySQL does the counting now. What comes back is one row per group, so
    // a report over ten million visits transfers a few dozen rows.
    //
    // Nothing about filtering changes. $where, prepare_sql_operation() and
    // every summarize-by option are untouched, and the totals are still
    // computed across every matching row rather than a sample.
    $detail_on = ($liveform->get_field_value('detail') == 1);

    // Detail rows are read separately and a page at a time. The totals above
    // them always describe the whole result set; this only limits how many
    // individual rows are listed, because a browser cannot usefully render
    // millions of table rows anyway. The full set is available through the
    // CSV export, which streams instead of buffering.
    $detail_page_size = 500;
    $detail_page      = isset($_GET['detail_page']) ? max(1, (int) $_GET['detail_page']) : 1;
    $detail_offset    = ($detail_page - 1) * $detail_page_size;
    $detail_total     = 0;

    // Build the grouping list once; the same expressions are used for the
    // SELECT, the GROUP BY and the ORDER BY.
    $group_expressions = array();
    if ($number_of_summarize_bys >= 1) $group_expressions[] = rtrim($sql_summarize_by_1, ',');
    if ($number_of_summarize_bys >= 2) $group_expressions[] = rtrim($sql_summarize_by_2, ',');
    if ($number_of_summarize_bys >= 3) $group_expressions[] = rtrim($sql_summarize_by_3, ',');

    $sql_group_select = '';
    foreach ($group_expressions as $i => $expression) {
        $sql_group_select .= $expression . ' AS sb' . ($i + 1) . ', ';
    }

    // Totals are summed, not counted row by row. custom_form_submitted and
    // the order_* columns are 0/1 flags, so SUM() over them reproduces
    // exactly what the old ++ accumulation produced.
    $sql_totals =
        "COUNT(*) AS row_count,
         COALESCE(SUM(visitors.page_views), 0) AS page_views,
         COALESCE(SUM(visitors.custom_form_submitted), 0) AS custom_form_submitted,
         COALESCE(SUM(visitors.order_created), 0) AS order_created,
         COALESCE(SUM(visitors.order_retrieved), 0) AS order_retrieved,
         COALESCE(SUM(visitors.order_checked_out), 0) AS order_checked_out,
         COALESCE(SUM(visitors.order_completed), 0) AS order_completed,
         COALESCE(SUM(visitors.order_total), 0) AS order_total";

    $sql_group_by = '';
    $sql_order_by = '';
    if (!empty($group_expressions)) {
        $aliases      = array();
        foreach ($group_expressions as $i => $expression) $aliases[] = 'sb' . ($i + 1);
        $sql_group_by = 'GROUP BY ' . implode(', ', $aliases);
        $sql_order_by = 'ORDER BY ' . implode(', ', $aliases);
    }

    $query =
        "SELECT
            $sql_group_select
            $sql_totals
        FROM visitors
        $where
        $sql_group_by
        $sql_order_by";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $results = array();

    // Grand totals accumulate over the group rows, so they stay correct
    // however many groups there are.
    $grand_count = 0;
    $grand_page_views = 0;
    $grand_custom_form_submitted = 0;
    $grand_order_created = 0;
    $grand_order_retrieved = 0;
    $grand_order_checked_out = 0;
    $grand_order_completed = 0;
    $grand_order_total = 0;

    // Maps a group's name path to its position in $results, so the detail
    // rows fetched below can be filed under the right heading.
    $group_index = array();

    while ($row = mysqli_fetch_assoc($result)) {

        $totals = array(
            'count'                 => (int) $row['row_count'],
            'page_views'            => (int) $row['page_views'],
            'custom_form_submitted' => (int) $row['custom_form_submitted'],
            'order_created'         => (int) $row['order_created'],
            'order_retrieved'       => (int) $row['order_retrieved'],
            'order_checked_out'     => (int) $row['order_checked_out'],
            'order_completed'       => (int) $row['order_completed'],
            'order_total'           => (int) $row['order_total'],
        );

        $grand_count                 += $totals['count'];
        $grand_page_views            += $totals['page_views'];
        $grand_custom_form_submitted += $totals['custom_form_submitted'];
        $grand_order_created         += $totals['order_created'];
        $grand_order_retrieved       += $totals['order_retrieved'];
        $grand_order_checked_out     += $totals['order_checked_out'];
        $grand_order_completed       += $totals['order_completed'];
        $grand_order_total           += $totals['order_total'];

        if ($number_of_summarize_bys == 0) {
            continue;
        }

        $name_1 = trim((string) $row['sb1']);
        $key_1  = mb_strtolower($name_1);

        if (!isset($group_index[$key_1])) {
            $group_index[$key_1] = count($results);
            $results[$group_index[$key_1]] = array('name' => $name_1) + array_fill_keys(array_keys($totals), 0);
        }
        $k1 = $group_index[$key_1];

        // Level 1 totals are the sum of the levels beneath it.
        foreach ($totals as $field => $value) $results[$k1][$field] += $value;

        if ($number_of_summarize_bys == 1) {
            continue;
        }

        $name_2 = trim((string) $row['sb2']);
        $key_2  = $key_1 . "\x00" . mb_strtolower($name_2);

        if (!isset($group_index[$key_2])) {
            $group_index[$key_2] = isset($results[$k1]['results']) ? count($results[$k1]['results']) : 0;
            $results[$k1]['results'][$group_index[$key_2]] = array('name' => $name_2) + array_fill_keys(array_keys($totals), 0);
        }
        $k2 = $group_index[$key_2];

        foreach ($totals as $field => $value) $results[$k1]['results'][$k2][$field] += $value;

        if ($number_of_summarize_bys == 2) {
            continue;
        }

        $name_3 = trim((string) $row['sb3']);
        $key_3  = $key_2 . "\x00" . mb_strtolower($name_3);

        if (!isset($group_index[$key_3])) {
            $group_index[$key_3] = isset($results[$k1]['results'][$k2]['results']) ? count($results[$k1]['results'][$k2]['results']) : 0;
            $results[$k1]['results'][$k2]['results'][$group_index[$key_3]] = array('name' => $name_3) + array_fill_keys(array_keys($totals), 0);
        }
        $k3 = $group_index[$key_3];

        foreach ($totals as $field => $value) $results[$k1]['results'][$k2]['results'][$k3][$field] += $value;
    }

    // ── CSV export ───────────────────────────────────────────────────────
    //
    // The screen shows a page of detail rows at a time; this is how the whole
    // set is obtained. Rows are written to the output stream as they arrive
    // from MySQL and the buffer is flushed each time, so peak memory is one
    // row regardless of whether the report covers a thousand visitors or ten
    // million. Nothing is accumulated in an array — which is precisely the
    // mistake that stopped this page loading.
    //
    // Uses the same $where the report does, so the export matches the report
    // filter for filter.
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {

        $export_name = trim((string) $liveform->get_field_value('name'));
        if ($export_name == '') {
            $export_name = 'visitor-report';
        }
        $export_name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $export_name);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $export_name . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');

        // Byte order mark, so Excel opens the file as UTF-8 rather than
        // guessing an ANSI code page and mangling Turkish characters.
        fwrite($out, "\xEF\xBB\xBF");

        $export_headers = array();
        for ($i = 1; $i <= $number_of_summarize_bys; $i++) {
            $export_headers[] = $liveform->get_field_value('summarize_by_' . $i);
        }
        fputcsv($out, array_merge($export_headers, array(
            lang('Visitor Number'),
            lang('Date'),
            lang('Page Views'),
            lang('Custom Form Submitted'),
            lang('Order Created'),
            lang('Order Retrieved'),
            lang('Order Checked Out'),
            lang('Order Completed'),
            lang('Order Total'),
        )));

        $query =
            "SELECT
                $sql_group_select
                visitors.id,
                visitors.start_timestamp,
                visitors.page_views,
                visitors.custom_form_submitted,
                visitors.order_created,
                visitors.order_retrieved,
                visitors.order_checked_out,
                visitors.order_completed,
                visitors.order_total
            FROM visitors
            $where
            ORDER BY " . (!empty($group_expressions) ? implode(', ', $group_expressions) . ', ' : '') . "visitors.id";

        // Unbuffered: mysqli_query() would pull the entire result into PHP
        // before the first row could be written, defeating the whole point.
        $result = mysqli_query(db::$con, $query, MYSQLI_USE_RESULT) or output_error('Query failed.');

        $written = 0;

        while ($row = mysqli_fetch_assoc($result)) {

            $line = array();
            for ($i = 1; $i <= $number_of_summarize_bys; $i++) {
                $line[] = isset($row['sb' . $i]) ? $row['sb' . $i] : '';
            }

            fputcsv($out, array_merge($line, array(
                $row['id'],
                get_absolute_time(array('timestamp' => $row['start_timestamp'], 'type' => 'date and time', 'timezone_type' => 'site')),
                $row['page_views'],
                $row['custom_form_submitted'],
                $row['order_created'],
                $row['order_retrieved'],
                $row['order_checked_out'],
                $row['order_completed'],
                number_format($row['order_total'] / 100, 2, '.', ''),
            )));

            // Push each batch down the wire rather than letting it pile up in
            // PHP's output buffer, which would recreate the memory problem in
            // a different place.
            if ((++$written % 1000) === 0) {
                if (ob_get_level() > 0) @ob_flush();
                @flush();
            }
        }

        mysqli_free_result($result);
        fclose($out);
        exit();
    }

    // ── Detail rows ──────────────────────────────────────────────────────
    //
    // Only read when the detail checkbox is on. Previously these were built
    // unconditionally and discarded, which is what exhausted memory on a
    // large report even for someone who only wanted the summary.
    $visitors = array();

    if ($detail_on) {

        $detail_total = (int) db_value("SELECT COUNT(*) FROM visitors $where");

        $query =
            "SELECT
                $sql_group_select
                visitors.id,
                visitors.start_timestamp,
                visitors.page_views,
                visitors.custom_form_submitted,
                visitors.order_created,
                visitors.order_retrieved,
                visitors.order_checked_out,
                visitors.order_completed,
                visitors.order_total
            FROM visitors
            $where
            ORDER BY " . (!empty($group_expressions) ? implode(', ', $group_expressions) . ', ' : '') . "visitors.id
            LIMIT $detail_page_size OFFSET $detail_offset";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {

            $visitor = array(
                'id'                    => $row['id'],
                'timestamp'             => $row['start_timestamp'],
                'page_views'            => $row['page_views'],
                'custom_form_submitted' => $row['custom_form_submitted'],
                'order_created'         => $row['order_created'],
                'order_retrieved'       => $row['order_retrieved'],
                'order_checked_out'     => $row['order_checked_out'],
                'order_completed'       => $row['order_completed'],
                'order_total'           => $row['order_total'],
            );

            if ($number_of_summarize_bys == 0) {
                $visitors[] = $visitor;
                continue;
            }

            $key_1 = mb_strtolower(trim((string) $row['sb1']));
            if (!isset($group_index[$key_1])) continue;
            $k1 = $group_index[$key_1];

            if ($number_of_summarize_bys == 1) {
                $results[$k1]['visitors'][] = $visitor;
                continue;
            }

            $key_2 = $key_1 . "\x00" . mb_strtolower(trim((string) $row['sb2']));
            if (!isset($group_index[$key_2])) continue;
            $k2 = $group_index[$key_2];

            if ($number_of_summarize_bys == 2) {
                $results[$k1]['results'][$k2]['visitors'][] = $visitor;
                continue;
            }

            $key_3 = $key_2 . "\x00" . mb_strtolower(trim((string) $row['sb3']));
            if (!isset($group_index[$key_3])) continue;
            $k3 = $group_index[$key_3];

            $results[$k1]['results'][$k2]['results'][$k3]['visitors'][] = $visitor;
        }
    }
    
    $output_report_detail_headers = '';
    $output_report_detail_cells = '';
    
    // if detail is on, then prepare to output detail
    if ($liveform->get_field_value('detail') == 1) {
        $output_report_detail_headers =
            '<th class="align-middle text-nowrap">' . lang('Visitor Number') . '</th>
            <th class="align-middle text-nowrap">' . lang('Date') . '</th>';
        
        $output_report_detail_cells =
            '<td>&nbsp;</td>
            <td>&nbsp;</td>';
    }
    
    // Check to see if the order report name is empty, and add new order report header if it is.
    if ($output_visitor_report_name == '') {
        $output_visitor_report_name = '[' . lang('new visitor report') . ']';
    }
    
    print
    pg_page_shell(
        array(
            'title'=> lang('Visitor Report'),
            'extra classes'=>'visitor',
            'icon'=>'visitor', 
            'heading'=> lang('Visitor Report'),
            'cancel'=>array('enable'=>'true','url'=>'view_visitor_reports.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Visitor Reports'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'), array('label' => lang('Visitor Report'))),
        )
    ) . '




            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('View or update this real-time visitor report.') . '" title="' . lang('Visitor Report') . '">' . h($output_visitor_report_name) . '</h2>
                        ' . $output_edit_button . '
                    </div>
                </div>
                <form name="form" id="edit_form" action="view_visitor_report.php" method="post" style="' . $output_edit_form_style . '">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'id'=>'last_filter_number', 'name'=>'last_filter_number', 'value'=>'0')) . '
                    ' . $output_hidden_id_field . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="name" class="form-label">' . lang('Visitor Report Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100')) . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <h4 class="fw-bold text-muted">' . lang('Visitor Report Layout') . '</h4>
                                        </div>
                                        <div class="col-12 col-lg-8 my-2">
                                            <div class="input-group-text row my-2">
                                                <div class="col-12 col-md">' . lang('Summarize by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'summarize_by_1', 'class'=>'form-select', 'options'=>$summarize_by_options)) . '</div>
                                                <div class="col-12 col-md">' . lang('order by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'order_by_1', 'class'=>'form-select', 'options'=>$order_by_options)) . '</div>
                                            </div>
                                            <div class="input-group-text row my-2">
                                                <div class="col-12 col-md">' . lang('and then by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'summarize_by_2', 'class'=>'form-select', 'options'=>$summarize_by_options)) . '</div>
                                                <div class="col-12 col-md">' . lang('order by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'order_by_2', 'class'=>'form-select', 'options'=>$order_by_options)) . '</div>
                                            </div>
                                            <div class="input-group-text row my-2">
                                                <div class="col-12 col-md">' . lang('and finally by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'summarize_by_3', 'class'=>'form-select', 'options'=>$summarize_by_options)) . '</div>
                                                <div class="col-12 col-md">' . lang('order by') . '</div>
                                                <div class="col-12 col-md">' . $liveform->output_field(array('type'=>'select', 'name'=>'order_by_3', 'class'=>'form-select', 'options'=>$order_by_options)) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h4 class="fw-bold text-muted">' . lang('Order Report Filters') . '</h4>
                                        </div>
                                        <script type="text/javascript" language="JavaScript 1.2">
                                            //We add new language data to translate object
                                            translate["contains"] = "' . lang('contains') . '";
                                            translate["does not contain"] = "' . lang('does not contain') . '";
                                            translate["is equal to"] = "' . lang('is equal to') . '";
                                            translate["is not equal to"] = "' . lang('is not equal to') . '";
                                            translate["is less than"] = "' . lang('is less than') . '";
                                            translate["is less than or equal to"] = "' . lang('is less than or equal to') . '";
                                            translate["is greater than"] = "' . lang('is greater than') . '";
                                            translate["is greater than or equal to"] = "' . lang('is greater than or equal to') . '";
                                            translate["Current Date"] = "' . lang('Current Date') . '";
                                            translate["Current Date & Time"] = "' . lang('Current Date & Time') . '";
                                            translate["Day(s) Ago"] = "' . lang('Day(s) Ago') . '";
                                            translate["Current Time"] = "' . lang('Current Time') . '";
                                            translate["Viewer"] = "' . lang('Viewer') . '";
                                            translate["Viewer\'s E-mail Address"] = "' . lang('Viewer\'s E-mail Address') . '";

                                            var last_filter_number = 0;
                                            var filters = new Array();
                                            ' . $output_filters_for_javascript . '
                                            var field_options = new Array();
                                            ' . $output_field_options_for_javascript . '
                                            window.onload = initialize_filters;
                                        </script>
                                        <div>
                                            <a href="javascript:void(0)" onclick="create_filter()" class="button btn btn-primary">' . lang('Add Filter') . '</a>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="filter_table" class="chart_no_hover table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-nowrap" style="min-width:200px">' . lang('Field') . '</th>
                                                        <th class="text-nowrap" style="min-width:200px">' . lang('Operator') . '</th>
                                                        <th class="text-nowrap" style="min-width:200px">' . lang('Value') . '</th>
                                                        <th class="text-nowrap" style="min-width:200px">' . lang('Dynamic Value') . '</th>
                                                        <th class="text-nowrap">&nbsp;</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'detail', 'id'=>'detail', 'value'=>'1', 'class'=>'form-check-input')) . '
                                                <label class="form-check-label" for="detail">' . lang('Show Detail (Include Visitor Session Information)') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save &amp; Run" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save & Run') ) . '</span></button>
                                ' . $output_cancel_button . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-8 text-center text-md-start"></div> 
                    <div class="col-12 col-sm-12 col-md-6 col-xl-4 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <div class="col-auto">
                                ' . $output_date_changer . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th colspan="' . $number_of_summarize_bys . '">&nbsp;</th>
                                    ' . $output_report_detail_headers . '
                                    <th class="text-end text-nowrap">' . lang('Visitors') . '</th>
                                    <th class="text-end text-nowrap">' . lang('Visitors') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Page View') . '</th>
                                    <th class="text-end text-nowrap">' . lang('Page Views') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Custom Form Submitted') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Order Created') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Order Retrieved') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Order Checked Out') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Order Completed') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Order Total') . '</th>
                                    <th class="text-end text-nowrap">' . lang('Order Total') . ' %</th>
                                    <th class="text-end text-nowrap">' . lang('Average Order Total') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    // if there is at least one summarize by
    if ($number_of_summarize_bys >= 1) {
        // if an order by has been selected for summarize by 1
        if (get_order_by_sort_value($liveform->get_field_value('order_by_1'))) {
            // prepare temp array in order to sort array
            $temp = array();    
            
            foreach ($results as $result) {
                 $temp[] = $result[get_order_by_sort_value($liveform->get_field_value('order_by_1'))];
            }

            array_multisort($temp, SORT_DESC, $results);
        }
        
        foreach ($results as $summarize_by_1_result) {
            $summarize_by_1_name = update_summarize_by_name($summarize_by_1_result['name'], $liveform->get_field_value('summarize_by_1'));
            
            // get percentages
            $count_percentage = ($grand_count > 0) ? $summarize_by_1_result['count'] / $grand_count * 100 : 0;
            $page_views_percentage = ($grand_page_views > 0) ? $summarize_by_1_result['page_views'] / $grand_page_views * 100 : 0;
            $custom_form_submitted_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['custom_form_submitted'] / $summarize_by_1_result['count'] * 100 : 0;
            $order_created_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['order_created'] / $summarize_by_1_result['count'] * 100 : 0;
            $order_retrieved_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['order_retrieved'] / $summarize_by_1_result['count'] * 100 : 0;
            $order_checked_out_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['order_checked_out'] / $summarize_by_1_result['count'] * 100 : 0;
            $order_completed_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['order_completed'] / $summarize_by_1_result['count'] * 100 : 0;
            $order_total_percentage = ($grand_order_total > 0) ? $summarize_by_1_result['order_total'] / $grand_order_total * 100 : 0;
            $average_order_total = ($summarize_by_1_result['count'] > 0) ? $summarize_by_1_result['order_total'] / $summarize_by_1_result['count'] : 0;
            
            print
                '<tr style="font-weight: bold; color: #008000; cursor: default">
                    <td class="align-middle text-nowrap" colspan="' . $number_of_summarize_bys . '">' . nl2br(h($summarize_by_1_name)) . '</td>
                    ' . $output_report_detail_cells . '
                    <td class="align-middle text-end">' . number_format($summarize_by_1_result['count']) . '</td>
                    <td class="align-middle text-end">' . number_format($count_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($summarize_by_1_result['page_views']) . '</td>
                    <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($custom_form_submitted_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($order_created_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($order_retrieved_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($order_checked_out_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . number_format($order_completed_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . prepare_amount($summarize_by_1_result['order_total'] / 100) . '</td>
                    <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                    <td class="align-middle text-end">' . prepare_amount($average_order_total / 100) . '</td>
                </tr>';
            
            // if there is at least 2 summarize bys
            if ($number_of_summarize_bys >= 2) {
                // if an order by has been selected for summarize by 2
                if (get_order_by_sort_value($liveform->get_field_value('order_by_2'))) {
                    // prepare temp array in order to sort array
                    $temp = array();    
                    
                    foreach ($summarize_by_1_result['results'] as $result) {
                         $temp[] = $result[get_order_by_sort_value($liveform->get_field_value('order_by_2'))];
                    }

                    array_multisort($temp, SORT_DESC, $summarize_by_1_result['results']);
                }
                
                foreach ($summarize_by_1_result['results'] as $summarize_by_2_result) {
                    $summarize_by_2_name = update_summarize_by_name($summarize_by_2_result['name'], $liveform->get_field_value('summarize_by_2'));
                    
                    $colspan = $number_of_summarize_bys - 1;
                    
                    // get percentages
                    $count_percentage = ($summarize_by_1_result['count'] > 0) ? $summarize_by_2_result['count'] / $summarize_by_1_result['count'] * 100 : 0;
                    $page_views_percentage = ($summarize_by_1_result['page_views'] > 0) ? $summarize_by_2_result['page_views'] / $summarize_by_1_result['page_views'] * 100 : 0;
                    $custom_form_submitted_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['custom_form_submitted'] / $summarize_by_2_result['count'] * 100 : 0;
                    $order_created_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['order_created'] / $summarize_by_2_result['count'] * 100 : 0;
                    $order_retrieved_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['order_retrieved'] / $summarize_by_2_result['count'] * 100 : 0;
                    $order_checked_out_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['order_checked_out'] / $summarize_by_2_result['count'] * 100 : 0;
                    $order_completed_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['order_completed'] / $summarize_by_2_result['count'] * 100 : 0;
                    $order_total_percentage = ($summarize_by_1_result['order_total'] > 0) ? $summarize_by_2_result['order_total'] / $summarize_by_1_result['order_total'] * 100 : 0;
                    $average_order_total = ($summarize_by_2_result['count'] > 0) ? $summarize_by_2_result['order_total'] / $summarize_by_2_result['count'] : 0;
                    
                    print
                        '<tr style="font-weight: bold; color: #719700; cursor: default">
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td class="align-middle text-nowrap" colspan="' . $colspan . '">' . nl2br(h($summarize_by_2_name)) . '</td>
                            ' . $output_report_detail_cells . '
                            <td class="align-middle text-end">' . number_format($summarize_by_2_result['count']) . '</td>
                            <td class="align-middle text-end">' . number_format($count_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($summarize_by_2_result['page_views']) . '</td>
                            <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($custom_form_submitted_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($order_created_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($order_retrieved_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($order_checked_out_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($order_completed_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . prepare_amount($summarize_by_2_result['order_total'] / 100) . '</td>
                            <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . prepare_amount($average_order_total / 100) . '</td>
                        </tr>';
                    
                    // if there are 3 summarize bys
                    if ($number_of_summarize_bys == 3) {
                        // if an order by has been selected for summarize by 3
                        if (get_order_by_sort_value($liveform->get_field_value('order_by_3'))) {
                            // prepare temp array in order to sort array
                            $temp = array();    
                            
                            foreach ($summarize_by_2_result['results'] as $result) {
                                 $temp[] = $result[get_order_by_sort_value($liveform->get_field_value('order_by_3'))];
                            }

                            array_multisort($temp, SORT_DESC, $summarize_by_2_result['results']);
                        }
                        
                        foreach ($summarize_by_2_result['results'] as $summarize_by_3_result) {
                            $summarize_by_3_name = update_summarize_by_name($summarize_by_3_result['name'], $liveform->get_field_value('summarize_by_3'));

                            // get percentages
                            $count_percentage = ($summarize_by_2_result['count'] > 0) ? $summarize_by_3_result['count'] / $summarize_by_2_result['count'] * 100 : 0;
                            $page_views_percentage = ($summarize_by_2_result['page_views'] > 0) ? $summarize_by_3_result['page_views'] / $summarize_by_2_result['page_views'] * 100 : 0;
                            $custom_form_submitted_percentage = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['custom_form_submitted'] / $summarize_by_3_result['count'] * 100 : 0;
                            $order_created_percentage = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['order_created'] / $summarize_by_3_result['count'] * 100 : 0;
                            $order_retrieved_percentage = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['order_retrieved'] / $summarize_by_3_result['count'] * 100 : 0;
                            $order_checked_out_percentage = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['order_checked_out'] / $summarize_by_3_result['count'] * 100 : 0;
                            $order_completed_percentage = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['order_completed'] / $summarize_by_3_result['count'] * 100 : 0;
                            $order_total_percentage = ($summarize_by_2_result['order_total'] > 0) ? $summarize_by_3_result['order_total'] / $summarize_by_2_result['order_total'] * 100 : 0;
                            $average_order_total = ($summarize_by_3_result['count'] > 0) ? $summarize_by_3_result['order_total'] / $summarize_by_3_result['count'] : 0;
                            
                            print
                                '<tr style="font-weight: bold; color: #808080; cursor: default">
                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                    <td class="align-middle text-nowrap">' . nl2br(h($summarize_by_3_name)) . '</td>
                                    ' . $output_report_detail_cells . '
                                    <td class="align-middle text-end">' . number_format($summarize_by_3_result['count']) . '</td>
                                    <td class="align-middle text-end">' . number_format($count_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($summarize_by_3_result['page_views']) . '</td>
                                    <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($custom_form_submitted_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($order_created_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($order_retrieved_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($order_checked_out_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . number_format($order_completed_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . prepare_amount($summarize_by_3_result['order_total'] / 100) . '</td>
                                    <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                                    <td class="align-middle text-end">' . prepare_amount($average_order_total / 100) . '</td>
                                </tr>';
                            
                            if ($liveform->get_field_value('detail') == 1) {
                                foreach ($summarize_by_3_result['visitors'] as $visitor) {
                                    // get percentages
                                    $count_percentage = ($summarize_by_3_result['count'] > 0) ? $visitor['count'] / $summarize_by_3_result['count'] * 100 : 0;
                                    $page_views_percentage = ($summarize_by_3_result['page_views'] > 0) ? $visitor['page_views'] / $summarize_by_3_result['page_views'] * 100 : 0;
                                    $order_total_percentage = ($summarize_by_3_result['order_total'] > 0) ? $visitor['order_total'] / $summarize_by_3_result['order_total'] * 100 : 0;
                                    
                                    $output_custom_form_submitted = ($visitor['custom_form_submitted']) ? '*' : '';
                                    $output_order_created = ($visitor['order_created']) ? '*' : '';
                                    $output_order_retrieved = ($visitor['order_retrieved']) ? '*' : '';
                                    $output_order_checked_out = ($visitor['order_checked_out']) ? '*' : '';
                                    $output_order_completed = ($visitor['order_completed']) ? '*' : '';
                                    
                                    $output_link_url = 'view_visitor.php?id=' . $visitor['id'] . h(escape_javascript($query_string_visitor_report_id));
                                    
                                    print
                                        '<tr style="color: #969696" class="pointer" onclick="window.location.href=\'' . $output_link_url . '\'">
                                            <td colspan="3">&nbsp;</td>
                                            <td class="align-middle">' . $visitor['id'] . '</td>
                                            <td class="align-middle text-nowrap">' . get_relative_time(array('timestamp' => $visitor['timestamp'])) . '</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td class="align-middle text-end">' . number_format($visitor['page_views']) . '</td>
                                            <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                                            <td class="align-middle text-end">' . $output_custom_form_submitted . '</td>
                                            <td class="align-middle text-end">' . $output_order_created . '</td>
                                            <td class="align-middle text-end">' . $output_order_retrieved . '</td>
                                            <td class="align-middle text-end">' . $output_order_checked_out . '</td>
                                            <td class="align-middle text-end">' . $output_order_completed . '</td>
                                            <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                                            <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                                            <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                                        </tr>';
                                }
                            }
                        }
                        
                    // else there are not 3 summarize bys
                    } else {
                        if ($liveform->get_field_value('detail') == 1) {
                            foreach ($summarize_by_2_result['visitors'] as $visitor) {
                                // get percentages
                                $count_percentage = ($summarize_by_2_result['count'] > 0) ? $visitor['count'] / $summarize_by_2_result['count'] * 100 : 0;
                                $page_views_percentage = ($summarize_by_2_result['page_views'] > 0) ? $visitor['page_views'] / $summarize_by_2_result['page_views'] * 100 : 0;
                                $order_total_percentage = ($summarize_by_2_result['order_total'] > 0) ? $visitor['order_total'] / $summarize_by_2_result['order_total'] * 100 : 0;
                                
                                $output_custom_form_submitted = ($visitor['custom_form_submitted']) ? '*' : '';
                                $output_order_created = ($visitor['order_created']) ? '*' : '';
                                $output_order_retrieved = ($visitor['order_retrieved']) ? '*' : '';
                                $output_order_checked_out = ($visitor['order_checked_out']) ? '*' : '';
                                $output_order_completed = ($visitor['order_completed']) ? '*' : '';
                                
                                $output_link_url = 'view_visitor.php?id=' . $visitor['id'] . h(escape_javascript($query_string_visitor_report_id));
                                
                                print
                                    '<tr style="color: #969696;" class="pointer" onclick="window.location.href=\'' . $output_link_url . '\'">
                                        <td colspan="2">&nbsp;</td>
                                        <td class="align-middle">' . $visitor['id'] . '</td>
                                        <td class="align-middle text-nowrap">' . get_relative_time(array('timestamp' => $visitor['timestamp'])) . '</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td class="align-middle text-end">' . number_format($visitor['page_views']) . '</td>
                                        <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                                        <td class="align-middle text-end">' . $output_custom_form_submitted . '</td>
                                        <td class="align-middle text-end">' . $output_order_created . '</td>
                                        <td class="align-middle text-end">' . $output_order_retrieved . '</td>
                                        <td class="align-middle text-end">' . $output_order_checked_out . '</td>
                                        <td class="align-middle text-end">' . $output_order_completed . '</td>
                                        <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                                        <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                                        <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                                    </tr>';
                            }
                        }
                    }
                }
                
            // else there is not at least 2 summarize bys
            } else {
                if ($liveform->get_field_value('detail') == 1) {
                    foreach ($summarize_by_1_result['visitors'] as $visitor) {
                        // get percentages
                        $count_percentage = ($summarize_by_1_result['count'] > 0) ? $visitor['count'] / $summarize_by_1_result['count'] * 100 : 0;
                        $page_views_percentage = ($summarize_by_1_result['page_views'] > 0) ? $visitor['page_views'] / $summarize_by_1_result['page_views'] * 100 : 0;
                        $order_total_percentage = ($summarize_by_1_result['order_total'] > 0) ? $visitor['order_total'] / $summarize_by_1_result['order_total'] * 100 : 0;
                        
                        $output_custom_form_submitted = ($visitor['custom_form_submitted']) ? '*' : '';
                        $output_order_created = ($visitor['order_created']) ? '*' : '';
                        $output_order_retrieved = ($visitor['order_retrieved']) ? '*' : '';
                        $output_order_checked_out = ($visitor['order_checked_out']) ? '*' : '';
                        $output_order_completed = ($visitor['order_completed']) ? '*' : '';
                        
                        $output_link_url = 'view_visitor.php?id=' . $visitor['id'] . h(escape_javascript($query_string_visitor_report_id));
                        
                        print
                            '<tr style="color: #969696" class="pointer" onclick="window.location.href=\'' . $output_link_url . '\'">
                                <td>&nbsp;</td>
                                <td class="align-middle">' . $visitor['id'] . '</td>
                                <td class="align-middle text-nowrap">' . get_relative_time(array('timestamp' => $visitor['timestamp'])) . '</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td class="align-middle text-end">' . number_format($visitor['page_views']) . '</td>
                                <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                                <td class="align-middle text-end">' . $output_custom_form_submitted . '</td>
                                <td class="align-middle text-end">' . $output_order_created . '</td>
                                <td class="align-middle text-end">' . $output_order_retrieved . '</td>
                                <td class="align-middle text-end">' . $output_order_checked_out . '</td>
                                <td class="align-middle text-end">' . $output_order_completed . '</td>
                                <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                                <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                                <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                            </tr>';
                    }
                }
            }
        }
        
    // else there is not at least 1 summarize by
    } else {
        if ($liveform->get_field_value('detail') == 1) {
            foreach ($visitors as $visitor) {
                // get percentages
                $count_percentage = ($grand_count > 0) ? $visitor['count'] / $grand_count * 100 : 0;
                $page_views_percentage = ($grand_page_views > 0) ? $visitor['page_views'] / $grand_page_views * 100 : 0;
                $order_total_percentage = ($grand_order_total > 0) ? $visitor['order_total'] / $grand_order_total * 100 : 0;
                
                $output_custom_form_submitted = ($visitor['custom_form_submitted']) ? '*' : '';
                $output_order_created = ($visitor['order_created']) ? '*' : '';
                $output_order_retrieved = ($visitor['order_retrieved']) ? '*' : '';
                $output_order_checked_out = ($visitor['order_checked_out']) ? '*' : '';
                $output_order_completed = ($visitor['order_completed']) ? '*' : '';
                
                $output_link_url = 'view_visitor.php?id=' . $visitor['id'] . h(escape_javascript($query_string_visitor_report_id));
                
                print
                    '<tr style="color: #969696" class="pointer" onclick="window.location.href=\'' . $output_link_url . '\'">
                        <td>&nbsp;</td>
                        <td>' . $visitor['id'] . '</td>
                        <td class="align-middle text-nowrap">' . get_relative_time(array('timestamp' => $visitor['timestamp'])) . '</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="align-middle text-end">' . number_format($visitor['page_views']) . '</td>
                        <td class="align-middle text-end">' . number_format($page_views_percentage, 2) . '%</td>
                        <td class="align-middle text-end">' . $output_custom_form_submitted . '</td>
                        <td class="align-middle text-end">' . $output_order_created . '</td>
                        <td class="align-middle text-end">' . $output_order_retrieved . '</td>
                        <td class="align-middle text-end">' . $output_order_checked_out . '</td>
                        <td class="align-middle text-end">' . $output_order_completed . '</td>
                        <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                        <td class="align-middle text-end">' . number_format($order_total_percentage, 2) . '%</td>
                        <td class="align-middle text-end">' . prepare_amount($visitor['order_total'] / 100) . '</td>
                    </tr>';
            }
        }
    }
    
    // ── Detail pager ─────────────────────────────────────────────────────
    //
    // Shown only when the detail listing is on and there is more than one
    // page of it. The totals above are unaffected by paging — they are
    // computed over every matching row — so this navigates the listing
    // without changing any figure on the screen.
    $output_detail_pager = '';

    if ($detail_on && $detail_total > $detail_page_size) {

        $detail_pages = (int) ceil($detail_total / $detail_page_size);
        $detail_from  = $detail_offset + 1;
        $detail_to    = min($detail_offset + $detail_page_size, $detail_total);

        // Preserve everything already in the query string except the page
        // number, so date-range and report id survive paging.
        $pager_params = $_GET;
        unset($pager_params['detail_page']);
        $pager_base = 'view_visitor_report.php?' . (empty($pager_params) ? '' : http_build_query($pager_params) . '&') . 'detail_page=';

        $pager_link = function ($page, $label, $disabled) use ($pager_base) {
            if ($disabled) {
                return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
            }
            return '<li class="page-item"><a class="page-link" href="' . h($pager_base . (int) $page) . '">' . $label . '</a></li>';
        };

        $csv_params = $_GET;
        unset($csv_params['detail_page']);
        $csv_params['export'] = 'csv';
        $csv_url = 'view_visitor_report.php?' . http_build_query($csv_params);

        $output_detail_pager =
            '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-top">
                <span class="text-muted small">'
                . h(lang(array(
                    'string' => 'Showing {var:1} to {var:2} of {var:3} visitor{suffix:3}',
                    'vars'   => array(number_format($detail_from), number_format($detail_to), number_format($detail_total)),
                    'suffix' => array('', '', ($detail_total == 1 ? '' : 's')),
                )))
                . '</span>
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="' . h($csv_url) . '">
                        <i class="bi bi-filetype-csv me-1"></i>' . lang('Export All to CSV') . '
                    </a>
                    <nav><ul class="pagination pagination-sm mb-0">'
                        . $pager_link(1, '&laquo;', $detail_page <= 1)
                        . $pager_link($detail_page - 1, '&lsaquo;', $detail_page <= 1)
                        . '<li class="page-item disabled"><span class="page-link">'
                        . h(lang(array('string' => 'Page {var:1} of {var:2}', 'vars' => array(number_format($detail_page), number_format($detail_pages)))))
                        . '</span></li>'
                        . $pager_link($detail_page + 1, '&rsaquo;', $detail_page >= $detail_pages)
                        . $pager_link($detail_pages, '&raquo;', $detail_page >= $detail_pages)
                    . '</ul></nav>
                </div>
            </div>';
    }

    $grand_custom_form_submitted_percentage = ($grand_count > 0) ? $grand_custom_form_submitted / $grand_count * 100 : 0;
    $grand_order_created_percentage = ($grand_count > 0) ? $grand_order_created / $grand_count * 100 : 0;
    $grand_order_retrieved_percentage = ($grand_count > 0) ? $grand_order_retrieved / $grand_count * 100 : 0;
    $grand_order_checked_out_percentage = ($grand_count > 0) ? $grand_order_checked_out / $grand_count * 100 : 0;
    $grand_order_completed_percentage = ($grand_count > 0) ? $grand_order_completed / $grand_count * 100 : 0;
    $grand_average_order_total = ($grand_count > 0) ? $grand_order_total / $grand_count : 0;
    
                    print
                        '<tr style="font-weight: bold; color: #c49700;  cursor: default">
                            <td class="align-middle text-nowrap" colspan="' . $number_of_summarize_bys . '">' . lang('Grand Total') . '</td>
                            ' . $output_report_detail_cells . '
                            <td class="align-middle text-end">' . number_format($grand_count) . '</td>
                            <td class="align-middle text-end">100.00%</td>
                            <td class="align-middle text-end">' . number_format($grand_page_views) . '</td>
                            <td class="align-middle text-end">100.00%</td>
                            <td class="align-middle text-end">' . number_format($grand_custom_form_submitted_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($grand_order_created_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($grand_order_retrieved_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($grand_order_checked_out_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . number_format($grand_order_completed_percentage, 2) . '%</td>
                            <td class="align-middle text-end">' . prepare_amount($grand_order_total / 100) . '</td>
                            <td class="align-middle text-end">100.00%</td>
                            <td class="align-middle text-end">' . prepare_amount($grand_average_order_total / 100) . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ' . $output_detail_pager . '
        </div>
    </div>
</div>
</main>' .
        output_footer();
    
    $liveform->remove_form();
    
// else the form has been submitted, so process form
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // if the user selected to delete this visitor report, then delete it
    if ($liveform->get_field_value('submit_button') == 'Delete') {
        // get visitor report name for log
        $query = "SELECT name FROM visitor_reports WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $visitor_report_name = $row['name'];
        
        // delete visitor report
        $query = "DELETE FROM visitor_reports WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete visitor report filters
        $query = "DELETE FROM visitor_report_filters WHERE visitor_report_id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        log_activity(lang(array('string'=>'visitor report ({var:1}) was deleted','vars'=>$visitor_report_name)), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_view_visitor_reports = new liveform('view_visitor_reports');
        $liveform_view_visitor_reports->add_notice(lang('The visitor report has been deleted.'));
        // send user to view visitor reports screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_visitor_reports.php');
        exit();
        
    // else the user did not choose to delete the visitor report
    } else {
        $liveform->validate_required_field('name', lang('Name is required.'));
        
        // if there is an error, forward user back to previous screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_visitor_report.php' . $query_string_id);
            exit();
        }
        
        // check to see if name is already in use
        $query =
            "SELECT id
            FROM visitor_reports
            WHERE
                (name = '" . escape($liveform->get_field_value('name')) . "')
                AND (id != '" . escape($liveform->get_field_value('id')) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if name is already in use, prepare error and forward user back to previous screen
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            
            // forward user to previous screen
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_visitor_report.php' . $query_string_id);
            exit();
        }
        
        // if the user is creating a new visitor report, then create visitor report record
        if ($liveform->field_in_session('id') == false) {
            $query =
                "INSERT INTO visitor_reports (
                    name,
                    detail,
                    summarize_by_1,
                    order_by_1,
                    summarize_by_2,
                    order_by_2,
                    summarize_by_3,
                    order_by_3,
                    created_user_id,
                    created_timestamp,
                    last_modified_user_id,
                    last_modified_timestamp)
                VALUES (
                    '" . escape($liveform->get_field_value('name')) . "',
                    '" . escape($liveform->get_field_value('detail')) . "',
                    '" . escape($liveform->get_field_value('summarize_by_1')) . "',
                    '" . escape($liveform->get_field_value('order_by_1')) . "',
                    '" . escape($liveform->get_field_value('summarize_by_2')) . "',
                    '" . escape($liveform->get_field_value('order_by_2')) . "',
                    '" . escape($liveform->get_field_value('summarize_by_3')) . "',
                    '" . escape($liveform->get_field_value('order_by_3')) . "',
                    '" . $user['id'] . "',
                    UNIX_TIMESTAMP(),
                    '" . $user['id'] . "',
                    UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $id = mysqli_insert_id(db::$con);
            $query_string_id = '?id=' . $id;
            
        // else the user is updating an existing visitor report, so update visitor report record
        } else {
            $query =
                "UPDATE visitor_reports
                SET
                    name = '" . escape($liveform->get_field_value('name')) . "',
                    detail = '" . escape($liveform->get_field_value('detail')) . "',
                    summarize_by_1 = '" . escape($liveform->get_field_value('summarize_by_1')) . "',
                    order_by_1 = '" . escape($liveform->get_field_value('order_by_1')) . "',
                    summarize_by_2 = '" . escape($liveform->get_field_value('summarize_by_2')) . "',
                    order_by_2 = '" . escape($liveform->get_field_value('order_by_2')) . "',
                    summarize_by_3 = '" . escape($liveform->get_field_value('summarize_by_3')) . "',
                    order_by_3 = '" . escape($liveform->get_field_value('order_by_3')) . "',
                    last_modified_user_id = '" . $user['id'] . "',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete visitor report filters
            $query = "DELETE FROM visitor_report_filters WHERE visitor_report_id = '" . escape($liveform->get_field_value('id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
        
        $date_filter_exists = false;
        
        // loop through all filters in order to insert filters into database
        for ($i = 1; $i <= $liveform->get_field_value('last_filter_number'); $i++) {
            // if filter exists and an operator was selected for this filter, then insert filter
            if ($liveform->get_field_value('filter_' . $i . '_operator') != '') {
                // get field type
                $field_type = '';
                
                // if the field for this filter is date, then set type to date
                if ($liveform->get_field_value('filter_' . $i . '_field') == 'date') {
                    $field_type = 'date';
                    $date_filter_exists = true;
                }
                
                // if user entered a value, clear dynamic value, in order to prevent user from using two values
                if ($liveform->get_field_value('filter_' . $i . '_value') != '') {
                    $dynamic_value = '';
                    $dynamic_value_attribute = '';
                } else {
                    $dynamic_value = $liveform->get_field_value('filter_' . $i . '_dynamic_value');
                    
                    // if days ago was selected for dynamic value, then set dynamic value attribute
                    if ($dynamic_value == 'days ago') {
                        $dynamic_value_attribute = $liveform->get_field_value('filter_' . $i . '_dynamic_value_attribute');
                    } else {
                        $dynamic_value_attribute = '';
                    }
                }
                
                // insert filter
                $query =
                    "INSERT INTO visitor_report_filters (
                        visitor_report_id,
                        field,
                        operator,
                        value,
                        dynamic_value,
                        dynamic_value_attribute)
                    VALUES (
                        '" . escape($id) . "',
                        '" . escape($liveform->get_field_value('filter_' . $i . '_field')) . "',
                        '" . escape($liveform->get_field_value('filter_' . $i . '_operator')) . "',
                        '" . escape(prepare_form_data_for_input($liveform->get_field_value('filter_' . $i . '_value'), $field_type)) . "',
                        '" . escape($dynamic_value) . "',
                        '" . escape($dynamic_value_attribute) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
        
        $date_filter_message = '';
        
        // if there is a date filter, then prepare message
        if ($date_filter_exists == true) {
            $order_date_filter_message = lang(' Date browsing has been disabled because there is a date filter in this report.');
        }
        
        // if the user is creating a new visitor report, then log activity, remove form, and add notice in a certain way
        if ($liveform->field_in_session('id') == false) {
            log_activity('visitor report (' . $liveform->get_field_value('name') . ') was created', $_SESSION['sessionusername']);
            $liveform->remove_form();
            $liveform->add_notice(lang('The visitor report has been created, and the results appear below.') . $date_filter_message);
            
        // else the user is updating an existing visitor report, so log activity, remove form, and add notice in a certain way
        } else {
            log_activity(lang(array('string'=>'visitor report ({var:1}) was modified','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
            $liveform->remove_form();
            $liveform->add_notice(lang('The visitor report has been saved, and the results appear below.') . $date_filter_message);
        }
        
        // send user back to visitor report
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_visitor_report.php' . $query_string_id);
        exit();
    }
}

function get_summarize_by_column($summarize_by)
{
    switch ($summarize_by) {
        case 'year': return 'FROM_UNIXTIME(visitors.start_timestamp, \'%Y\')';
        case 'month': return 'FROM_UNIXTIME(visitors.start_timestamp, \'%m\')';
        case 'day': return 'FROM_UNIXTIME(visitors.start_timestamp, \'%d\')';
        case 'site_search_terms': return 'visitors.site_search_terms';
        case 'currency_code': return 'visitors.currency_code';
        case 'http_referer': return 'visitors.http_referer';
        case 'referring_host_name': return 'visitors.referring_host_name';
        case 'referring_search_engine': return 'visitors.referring_search_engine';
        case 'referring_search_terms': return 'visitors.referring_search_terms';
        case 'pay_per_click_organic': return 'CASE WHEN (visitors.tracking_code LIKE "%' . escape(escape_like(PAY_PER_CLICK_FLAG)) . '%") THEN "Pay Per Click" WHEN (visitors.referring_search_engine != "") THEN "Organic" ELSE "" END';
        case 'first_visit': return 'visitors.first_visit';
        case 'landing_page_name': return 'visitors.landing_page_name';
        case 'tracking_code': return 'visitors.tracking_code';
        case 'affiliate_code': return 'visitors.affiliate_code';

        case 'utm_source': return 'visitors.utm_source';
        case 'utm_medium': return 'visitors.utm_medium';
        case 'utm_campaign': return 'visitors.utm_campaign';
        case 'utm_term': return 'visitors.utm_term';
        case 'utm_content': return 'visitors.utm_content';

        case 'page_views': return 'visitors.page_views';
        case 'custom_form_submitted': return 'visitors.custom_form_submitted';
        case 'custom_form_name': return 'visitors.custom_form_name';
        case 'order_created': return 'visitors.order_created';
        case 'order_retrieved': return 'visitors.order_retrieved';
        case 'order_checked_out': return 'visitors.order_checked_out';
        case 'order_completed': return 'visitors.order_completed';
        case 'city': return 'visitors.city';
        case 'state': return 'visitors.state';
        case 'zip_code': return 'visitors.zip_code';
        case 'country': return 'visitors.country';
    }
}

function update_summarize_by_name($summarize_by_name, $summarize_by) {
    switch ($summarize_by) {
        case 'month':
            $summarize_by_name = get_month_name_from_number($summarize_by_name);
            break;
            
        case 'site_search_terms':
            $summarize_by_name = str_replace('|', ",\n", $summarize_by_name);
            break;
            
        case 'first_visit':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('First Visit');
            } else {
                $summarize_by_name = lang('Return Visit');
            }
            break;
            
        case 'custom_form_submitted':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('Custom Form Submitted');
            } else {
                $summarize_by_name = lang('Custom Form Not Submitted');
            }
            break;
            
        case 'order_created':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('Order Created');
            } else {
                $summarize_by_name = lang('Order Not Created');
            }
            break;
        
        case 'order_retrieved':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('Order Retrieved');
            } else {
                $summarize_by_name = lang('Order Not Retrieved');
            }
            break;
        
        case 'order_checked_out':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('Order Checked Out');
            } else {
                $summarize_by_name = lang('Order Not Checked Out');
            }
            break;
        
        case 'order_completed':
            if ($summarize_by_name == 1) {
                $summarize_by_name = lang('Order Completed');
            } else {
                $summarize_by_name = lang('Order Not Completed');
            }
            break;
            
        case 'currency_code':
            // if the currency is known then output currency
            if ($summarize_by_name != '') {
                $summarize_by_name = get_currency_name_from_code($summarize_by_name) . ' (' . $summarize_by_name . ')';
                
            // else the currency is not known so output placeholder
            } else {
                $summarize_by_name = '[' . lang('Not Specified') . ']';
            }
            break;
    }
    
    if ($summarize_by_name == '') {
        $summarize_by_name = '[' . lang('Not Specified') . ']';
    }
    
    return $summarize_by_name;
}

function get_order_by_sort_value($order_by)
{
    switch ($order_by) {
        case 'alphabet': return '';
        case 'number of visitors': return 'count';
        case 'number of page views': return 'page_views';
        case 'order total': return 'order_total';
    }
}
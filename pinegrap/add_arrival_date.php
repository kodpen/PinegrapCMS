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
    // get all shipping methods, in order to prepare shipping method id options for shipping cut-offs
    $query =
        "SELECT
            id,
            name,
            code
        FROM shipping_methods
        ORDER BY name ASC, code ASC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $shipping_methods = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $shipping_methods[] = $row;
    }
    
    $output_shipping_method_id_options_for_javascript = '';
    $count = 0;

    // loop through all shipping methods in order to prepare javascript array
    foreach ($shipping_methods as $shipping_method) {
        // set the name to the shipping method name
        $shipping_method_name = $shipping_method['name'];
        
        // if there is a code, then add the code to the name
        if ($shipping_method['code'] != '') {
            $shipping_method_name .= ' (' . $shipping_method['code'] . ')';
        }
        
        // set the value to the shipping method id
        $shipping_method_value = $shipping_method['id'];
        
        $output_shipping_method_id_options_for_javascript .=
            'shipping_method_id_options[' . $count . '] = new Array();
            shipping_method_id_options[' . $count . ']["name"] = "' . escape_javascript($shipping_method_name) . '";
            shipping_method_id_options[' . $count . ']["value"] = "' . $shipping_method_value . '";' . "\n";
        
        $count++;
    }
    
    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date'))),
        'cancel'=>array('enable'=>'true','url'=>'view_arrival_dates.php'),
        'breadcrumb' => array(
            array('label' => lang('All Shipping Arrival Dates'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_arrival_dates.php'),
            array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date')))),
        ),
    ]) . '
            ' . get_date_picker_format() . '
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
        <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new arrival date for any special occasion, or holiday that will be presented during checkout. (Delete all will hide feature.)') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date'))) . '">[' . lang(array('string'=>'new {var:1}','vars'=>lang('arrival date'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_arrival_date.php" method="post" >
                    ' . get_token_field() . '
                    <input type="hidden" id="last_shipping_cutoff_number" name="last_shipping_cutoff_number" value="0" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Preview Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Arrival Date'))) . '</label>
                                            <input type="text" name="name"  id="name" maxlength="50" class="form-control add-header-content-updater" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Arrival Date Code') . '</label>
                                            <input type="text" name="code"  id="code" maxlength="50" class="form-control" />
                                            <div class="form-text text-end">' . lang('Arrival Date Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-4  my-2">
                                            <label for="sort_order" class="form-label">' . lang('Sorting') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="sort_order" id="sort_order" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                            </div>
                                        </div>
                                        <div class="col-12  col-sm-8 col-lg-12 my-2">
                                            <label for="description" class="form-label">' . lang(array('string'=>'{var:1} Description','vars'=>lang('Arrival Date'))) . '</label>
                                            <input type="text" name="description" id="description" maxlength="255" class="form-control add-header-content-updater" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Arrival Date Availability') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" checked="checked" />
                                                <label class="form-check-label" for="status">' . lang('Enable this Arrival Date') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="default_selected" name="default_selected" value="1" />
                                                <label class="form-check-label" for="default_selected">' . lang('Selected by Default') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-8 my-3">
                                            <div class="row">
                                                <div class="col-12 col-md">
                                                    <label for="start_date" class="form-label">' . lang('Start Date') . '</label>
                                                    <input placeholder="'. date('n/j/Y') . '" type="text" name="start_date" id="start_date" class="form-control" maxlength="10" autocomplete="off"/>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label for="end_date" class="form-label">' . lang('End Date') . '</label>
                                                    <input type="text" name="end_date" id="end_date" class="form-control " maxlength="10" autocomplete="off"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Arrival Date Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4">
                                            <label for="arrival_date" class="form-label">' . lang('Arrival Date') . '</label>
                                            <input placeholder="'. date('n/j/Y') . '" type="text" name="arrival_date" id="arrival_date" class="form-control" maxlength="10" autocomplete="off"/>
                                        </div>
                                        <div class="col-12 mt-4">
                                            <h5>' . lang('Or allow Customer to enter this Arrival Date during checkout') . '</h5>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input value="1" id="custom" name="custom" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#custom_row" onclick="show_or_hide_custom()"/>
                                                <label class="form-check-label" for="custom">' . lang('Display Custom Field') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="custom_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1" id="custom_maximum_arrival_date_row" style="display: none">
                                                            <label for="custom_maximum_arrival_date" class="form-label">' . lang('Latest Allowed Arrival Date') . '</label>
                                                            <input placeholder="'. date('n/j/Y') . '" type="text" name="custom_maximum_arrival_date" id="custom_maximum_arrival_date" class="form-control" maxlength="10" autocomplete="off"/>
                                                            <div class="form-text text-end">' . lang('leave blank for no restriction') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2" id="shipping_cutoff_heading_row">
                                            <h4 class="fw-bold text-muted">' . lang('Shipping Cut-offs') . '</h4>
                                        </div>
                                        <div id="shipping_cutoff_row">
                                            <div>
                                                <a href="javascript:void(0)" onclick="create_shipping_cutoff()" class="button btn btn-primary">' . lang('Add Shipping Cut-off') . '</a>
                                            </div>
                                            <div class="table-responsive" >
                                                <table id="shipping_cutoff_table" style="display: none;" class="chart_no_hover table">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-nowrap" style="min-width:200px">' . lang('Shipping Method') . '</th>
                                                            <th class="text-nowrap" style="min-width:200px">' . lang('Cut-off Date & Time') . '</th>
                                                            <th class="text-nowrap">&nbsp;</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                                <script type="text/javascript">
                                                    var last_shipping_cutoff_number = 0;
                                                    var shipping_cutoffs = new Array();
                                                    var shipping_method_id_options = new Array();
                                                    ' . $output_shipping_method_id_options_for_javascript . '
                                                </script>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        $("#end_date,#start_date,#arrival_date,#custom_maximum_arrival_date").datepicker(datetimepicker_options);
                    </script>
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

    print $output;

} else {
    validate_token_field();
    

    if($_POST['status'] == 1){
        $status = 'enabled';
    }else{
        $status = 'disabled';
    }


    // create arrival date
    $query =
        "INSERT INTO arrival_dates (
            name,
            description,
            code,
            arrival_date,
            status,
            start_date,
            end_date,
            default_selected,
            sort_order,
            custom,
            custom_maximum_arrival_date,
            user,
            timestamp)
        VALUES (
            '" . escape($_POST['name'] ?? '') . "',
            '" . escape($_POST['description'] ?? '') . "',
            '" . escape($_POST['code'] ?? '') . "',
            '" . escape(prepare_form_data_for_input($_POST['arrival_date'], 'date')) . "',
            '" . escape($status) . "',
            '" . escape(prepare_form_data_for_input($_POST['start_date'], 'date')) . "',
            '" . escape(prepare_form_data_for_input($_POST['end_date'], 'date')) . "',
            '" . escape($_POST['default_selected'] ?? '') . "',
            '" . escape($_POST['sort_order'] ?? '') . "',
            '" . escape($_POST['custom'] ?? '') . "',
            '" . escape(prepare_form_data_for_input($_POST['custom_maximum_arrival_date'], 'date')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $arrival_date_id = mysqli_insert_id(db::$con);

    // if default selected was checked, then turn value off for all other arrival dates
    if (($_POST['default_selected'] ?? '') == 1) {
        // update arrival date
        $query = "UPDATE arrival_dates SET default_selected = 0 WHERE id != $arrival_date_id";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
    
    // assume that there is not an error for a shipping cut-off until we find out otherwise
    $shipping_cutoff_error = FALSE;
    
    // create array for storing shipping methods that have a shipping cut-off, so that we do not create multiple cut-offs for the same shipping method
    $shipping_cutoff_shipping_methods = array();
    
    // loop through all shipping cut-offs in order to insert them into database
    for ($i = 1; $i <= $_POST['last_shipping_cutoff_number']; $i++) {
        // if a shipping method was selected
        // and a shipping cut-off has not already been added for the selected shipping method
        // and a cut-off date and time was entered
        // and the cut-off date and time is a valid date
        // and the cut-off date and time is between the start date and end date for the arrival date,
        // then insert shipping cut-off into database
        if (
            (($_POST['shipping_cutoff_' . $i . '_shipping_method_id'] ?? '') != '')
            && (in_array($_POST['shipping_cutoff_' . $i . '_shipping_method_id'], $shipping_cutoff_shipping_methods) == FALSE)
            && (($_POST['shipping_cutoff_' . $i . '_date_and_time'] ?? '') != '')
            && (validate_date_and_time($_POST['shipping_cutoff_' . $i . '_date_and_time']) == TRUE)
            && (prepare_form_data_for_input($_POST['shipping_cutoff_' . $i . '_date_and_time'], 'date and time') >= prepare_form_data_for_input($_POST['start_date'], 'date') . ' 00:00:00')
            && (prepare_form_data_for_input($_POST['shipping_cutoff_' . $i . '_date_and_time'], 'date and time') <= prepare_form_data_for_input($_POST['end_date'], 'date') . ' 23:59:59')
        ) {
            $query =
                "INSERT INTO shipping_cutoffs (
                    arrival_date_id,
                    shipping_method_id,
                    date_and_time)
                VALUES (
                    '$arrival_date_id',
                    '" . escape($_POST['shipping_cutoff_' . $i . '_shipping_method_id']) . "',
                    '" . escape(prepare_form_data_for_input($_POST['shipping_cutoff_' . $i . '_date_and_time'], 'date and time')) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // remember that the shipping method has been used in a shipping cut-off
            $shipping_cutoff_shipping_methods[] = $_POST['shipping_cutoff_' . $i . '_shipping_method_id'];
            
        // else there was an error, so remember that
        } else {
            $shipping_cutoff_error = TRUE;
        }
    }
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('arrival date'),$_POST['name']))), $_SESSION['sessionusername']);
   
    // if there was a shipping cut-off error, then add notice and forward user to edit arrival date screen
    if ($shipping_cutoff_error == TRUE) {
        include_once('liveform.class.php');
        $liveform = new liveform('edit_arrival_date');
        
        $liveform->add_notice(lang('The arrival date has been created, however one or more shipping cut-offs were removed because of an error. It is recommended that you review the shipping cut-offs. Please make sure you select a shipping method, enter a valid cut-off date &amp; time that falls between the start date and end date, and make sure you do not enter multiple shipping cut-offs for the same shipping method.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_arrival_date.php?id=' . $arrival_date_id);
        
    // else there was not a shipping cut-off error, so forward user to view arrival dates screen
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_arrival_dates.php');
    }
}
?>
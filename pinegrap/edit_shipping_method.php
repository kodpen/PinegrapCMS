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
    // get shipping method data
    $query = "SELECT * FROM shipping_methods WHERE id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $shipping_method = $row;

    $name = h($row['name']);
    $description = h($row['description']);
    $code = h($row['code']);
    $status = $row['status'];
    $start_time = $row['start_time'];
    $end_time = $row['end_time'];

    $service = $row['service'];

    $realtime_rate = $row['realtime_rate'];

    $realtime_rate_checked = '';

    if ($realtime_rate) {
        $realtime_rate_checked = ' checked="checked"';
    }

    $base_rate = sprintf("%01.2lf", $row['base_rate'] / 100);
    $variable_base_rate = $row['variable_base_rate'];

    $base_rate_2 = '';
    $base_rate_2_subtotal = '';

    if ($row['base_rate_2_subtotal'] > 0) {
        $base_rate_2 = sprintf("%01.2lf", $row['base_rate_2'] / 100);
        $base_rate_2_subtotal = sprintf("%01.2lf", $row['base_rate_2_subtotal'] / 100);
    }

    $base_rate_3 = '';
    $base_rate_3_subtotal = '';

    if ($row['base_rate_3_subtotal'] > 0) {
        $base_rate_3 = sprintf("%01.2lf", $row['base_rate_3'] / 100);
        $base_rate_3_subtotal = sprintf("%01.2lf", $row['base_rate_3_subtotal'] / 100);
    }

    $base_rate_4 = '';
    $base_rate_4_subtotal = '';

    if ($row['base_rate_4_subtotal'] > 0) {
        $base_rate_4 = sprintf("%01.2lf", $row['base_rate_4'] / 100);
        $base_rate_4_subtotal = sprintf("%01.2lf", $row['base_rate_4_subtotal'] / 100);
    }

    $primary_weight_rate = sprintf("%01.2lf", $row['primary_weight_rate'] / 100);
    $primary_weight_rate_first_item_excluded = $row['primary_weight_rate_first_item_excluded'];
    $secondary_weight_rate = sprintf("%01.2lf", $row['secondary_weight_rate'] / 100);
    $secondary_weight_rate_first_item_excluded = $row['secondary_weight_rate_first_item_excluded'];
    $item_rate = sprintf("%01.2lf", $row['item_rate'] / 100);
    $item_rate_first_item_excluded = $row['item_rate_first_item_excluded'];
    $base_transit_days = $row['base_transit_days'];
    $adjust_transit = $row['adjust_transit'];
    $street_address = $row['street_address'];
    $po_box = $row['po_box'];
    $transit_on_sunday = $row['transit_on_sunday'];
    $transit_on_saturday = $row['transit_on_saturday'];
    $available_on_sunday = $row['available_on_sunday'];
    $available_on_sunday_cutoff_time = $row['available_on_sunday_cutoff_time'];
    $available_on_monday = $row['available_on_monday'];
    $available_on_monday_cutoff_time = $row['available_on_monday_cutoff_time'];
    $available_on_tuesday = $row['available_on_tuesday'];
    $available_on_tuesday_cutoff_time = $row['available_on_tuesday_cutoff_time'];
    $available_on_wednesday = $row['available_on_wednesday'];
    $available_on_wednesday_cutoff_time = $row['available_on_wednesday_cutoff_time'];
    $available_on_thursday = $row['available_on_thursday'];
    $available_on_thursday_cutoff_time = $row['available_on_thursday_cutoff_time'];
    $available_on_friday = $row['available_on_friday'];
    $available_on_friday_cutoff_time = $row['available_on_friday_cutoff_time'];
    $available_on_saturday = $row['available_on_saturday'];
    $available_on_saturday_cutoff_time = $row['available_on_saturday_cutoff_time'];
    $protected = $row['protected'];

    $output_variable_base_rate_checked = '';
    $output_base_rate_2_row_style = ' style="display: none"';
    $output_base_rate_3_row_style = ' style="display: none"';
    $output_base_rate_4_row_style = ' style="display: none"';

    if ($variable_base_rate == 1) {
        $output_variable_base_rate_checked = ' checked="checked"';
        $output_base_rate_2_row_style = '';
        $output_base_rate_3_row_style = '';
        $output_base_rate_4_row_style = '';
    }

    // prepare checked status for status radio buttons
    if ($status == 'enabled') {
        $status_enabled_checked = ' checked="checked"';
    } else {
        $status_enabled_checked = '';
    }

    // prepare checked status for adjust transit checkbox
    if ($adjust_transit == 1) {
        $adjust_transit_checked = ' checked="checked"';
    } else {
        $adjust_transit_checked = '';
    }

    $primary_weight_rate_first_item_excluded_checked = '';

    if ($primary_weight_rate_first_item_excluded == 1) {
        $primary_weight_rate_first_item_excluded_checked = ' checked="checked"';
    }

    $secondary_weight_rate_first_item_excluded_checked = '';

    if ($secondary_weight_rate_first_item_excluded == 1) {
        $secondary_weight_rate_first_item_excluded_checked = ' checked="checked"';
    }

    $item_rate_first_item_excluded_checked = '';

    if ($item_rate_first_item_excluded == 1) {
        $item_rate_first_item_excluded_checked = ' checked="checked"';
    }

    // prepare checked status for street address checkbox
    if ($street_address == 1) {
        $street_address_checked = ' checked="checked"';
    } else {
        $street_address_checked = '';
    }

    // prepare checked status for po box checkbox
    if ($po_box == 1) {
        $po_box_checked = ' checked="checked"';
    } else {
        $po_box_checked = '';
    }

    // get all zones for zones selection
    $query = "SELECT id, name FROM zones ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        $zones[] = array('id'=>$row['id'], 'name'=>$row['name']);
    }

    // if there is at least one zone
    if ($zones) {
        // foreach zone, check if zone is allowed or disallowed for this zone
        foreach ($zones as $key => $value) {
            $query = "SELECT zone_id FROM shipping_methods_zones_xref WHERE shipping_method_id = '" . escape($_GET['id']) . "' AND zone_id = '" . $zones[$key]['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // if shipping method and zone were found
            if (mysqli_num_rows($result)) {
                $allowed_zones[] = $zones[$key];
            } else {
                $disallowed_zones[] = $zones[$key];
            }
        }

        // if there is at least one allowed zone
        if ($allowed_zones) {
            // foreach allowed zone prepare option
            foreach ($allowed_zones as $key => $value) {
                $output_allowed_zones .= '<option selected="selected" value="' . $allowed_zones[$key]['id'] . '">' . h($allowed_zones[$key]['name']) . '</option>';
            }
        }

        // if there is at least one disallowed zone
        if ($disallowed_zones) {
            // foreach disallowed zone prepare option
            foreach ($disallowed_zones as $key => $value) {
                $output_allowed_zones .= '<option value="' . $disallowed_zones[$key]['id'] . '">' . h($disallowed_zones[$key]['name']) . '</option>';
            }
        }
    }

    // Leave handle days blank if zero.

    $handle_days = '';

    if ($shipping_method['handle_days']) {
        $handle_days = $shipping_method['handle_days'];
    }

    $handle_mon_checked = '';

    if ($shipping_method['handle_mon']) {
        $handle_mon_checked = ' checked="checked"';
    }

    $handle_tue_checked = '';

    if ($shipping_method['handle_tue']) {
        $handle_tue_checked = ' checked="checked"';
    }

    $handle_wed_checked = '';

    if ($shipping_method['handle_wed']) {
        $handle_wed_checked = ' checked="checked"';
    }

    $handle_thu_checked = '';

    if ($shipping_method['handle_thu']) {
        $handle_thu_checked = ' checked="checked"';
    }

    $handle_fri_checked = '';

    if ($shipping_method['handle_fri']) {
        $handle_fri_checked = ' checked="checked"';
    }

    $handle_sat_checked = '';

    if ($shipping_method['handle_sat']) {
        $handle_sat_checked = ' checked="checked"';
    }

    $handle_sun_checked = '';

    if ($shipping_method['handle_sun']) {
        $handle_sun_checked = ' checked="checked"';
    }

    $ship_mon_checked = '';

    if ($shipping_method['ship_mon']) {
        $ship_mon_checked = ' checked="checked"';
    }

    $ship_tue_checked = '';

    if ($shipping_method['ship_tue']) {
        $ship_tue_checked = ' checked="checked"';
    }

    $ship_wed_checked = '';

    if ($shipping_method['ship_wed']) {
        $ship_wed_checked = ' checked="checked"';
    }

    $ship_thu_checked = '';

    if ($shipping_method['ship_thu']) {
        $ship_thu_checked = ' checked="checked"';
    }

    $ship_fri_checked = '';

    if ($shipping_method['ship_fri']) {
        $ship_fri_checked = ' checked="checked"';
    }

    $ship_sat_checked = '';

    if ($shipping_method['ship_sat']) {
        $ship_sat_checked = ' checked="checked"';
    }

    $ship_sun_checked = '';

    if ($shipping_method['ship_sun']) {
        $ship_sun_checked = ' checked="checked"';
    }

    // Leave end of day blank if zero.

    $end_of_day = '';

    if ($shipping_method['end_of_day'] != '00:00:00') {
        $end_of_day = prepare_form_data_for_output($shipping_method['end_of_day'], 'time');
    }
    
    // prepare checked status for transit on sunday checkbox
    if ($transit_on_sunday == 1) {
        $transit_on_sunday_checked = ' checked="checked"';
    } else {
        $transit_on_sunday_checked = '';
    }
    
    // prepare checked status for transit on saturday checkbox
    if ($transit_on_saturday == 1) {
        $transit_on_saturday_checked = ' checked="checked"';
    } else {
        $transit_on_saturday_checked = '';
    }
    
    // get excluded transit dates
    $query = "SELECT date FROM excluded_transit_dates WHERE shipping_method_id = '" . escape($_GET['id']) . "' ORDER BY date";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $output_excluded_transit_dates = '';
    
    // loop through all excluded transit dates in order to prepare data for textarea
    while ($row = mysqli_fetch_assoc($result)) {
        // if this is not the first excluded transit date, then add a newline character
        if ($output_excluded_transit_dates != '') {
            $output_excluded_transit_dates .= ",";
        }
        
        $output_excluded_transit_dates .= prepare_form_data_for_output($row['date'], 'date');
    }
    
    $available_on_sunday_checked = '';
    $available_on_monday_checked = '';
    $available_on_tuesday_checked = '';
    $available_on_wednesday_checked = '';
    $available_on_thursday_checked = '';
    $available_on_friday_checked = '';
    $available_on_saturday_checked = '';
    
    // if available on sunday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_sunday == '1') {
        $available_on_sunday_checked = ' checked="checked"';
    }
    
    // if available on sunday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_sunday_cutoff_time == '00:00:00') {
        $available_on_sunday_cutoff_time = '';
    }
    
    // if available on monday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_monday == '1') {
        $available_on_monday_checked = ' checked="checked"';
    }
    
    // if available on monday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_monday_cutoff_time == '00:00:00') {
        $available_on_monday_cutoff_time = '';
    }
    
    // if available on tuesday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_tuesday == '1') {
        $available_on_tuesday_checked = ' checked="checked"';
    }
    
    // if available on tuesday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_tuesday_cutoff_time == '00:00:00') {
        $available_on_tuesday_cutoff_time = '';
    }
    
    // if available on wednesday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_wednesday == '1') {
        $available_on_wednesday_checked = ' checked="checked"';
    }
    
    // if available on wednesday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_wednesday_cutoff_time == '00:00:00') {
        $available_on_wednesday_cutoff_time = '';
    }
    
    // if available on thursday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_thursday == '1') {
        $available_on_thursday_checked = ' checked="checked"';
    }
    
    // if available on thursday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_thursday_cutoff_time == '00:00:00') {
        $available_on_thursday_cutoff_time = '';
    }
    
    // if available on friday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_friday == '1') {
        $available_on_friday_checked = ' checked="checked"';
    }
    
    // if available on friday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_friday_cutoff_time == '00:00:00') {
        $available_on_friday_cutoff_time = '';
    }
    
    // if available on saturday is one, then set it to checked and show it's cut-off time cell
    if ($available_on_saturday == '1') {
        $available_on_saturday_checked = ' checked="checked"';
    }
    
    // if available on saturday cutoff time is set to 00:00:00, then set it to blank
    if ($available_on_saturday_cutoff_time == '00:00:00') {
        $available_on_saturday_cutoff_time = '';
    }

    $protected_checked = '';

    if ($protected) {
        $protected_checked = ' checked="checked"';
    }
    
    $output =
    pg_page_shell([
        'title'=> lang('Edit Shipping Method'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Shipping Method'),
        'cancel'=>array('enable'=>'true','url'=>'view_shipping_methods.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Methods'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_methods.php'), array('label' => lang('Edit Shipping Method'))),
        ]) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
    ' . get_date_picker_format() . get_date_time_picker_format() . get_time_picker_format() . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit a shipping method that will be made available during checkout based on the products and destination address.') . '" title="' . lang('Edit Shipping Method') . '">[' . $name . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_shipping_method.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Display Name') . '</label>
                                            <input  value="' . $name . '" type="text" name="name" placeholder="' . lang('Shipping Method Name') . '" id="name" maxlength="50" class="form-control add-header-content-updater" />
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-8 my-2">
                                            <label for="description" class="form-label">' . lang('Display Message') . '</label>
                                            <input value="' . $description . '" type="text" name="description" id="description" class="form-control" maxlength="255" />
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            <input value="' . $code . '" type="text" name="code" id="code" class="form-control" maxlength="50" />
                                            <div class="form-text text-end">' . lang('Shipping Method Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="service" class="form-label">' . lang('Service') . '</label>
                                            ' . render(array('template' => 'shipping_method_service.php')) . '
                                            <script>$("#service").val("' . escape_javascript($service) . '")</script>
                                            <div class="form-text text-end">' . lang('Service for Real-Time Rate & Delivery') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Shipping Method Charges') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2 collapse" id="realtime_rate_row">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="realtime_rate" name="realtime_rate" value="1"' . $realtime_rate_checked . '/>
                                                <label class="form-check-label" for="realtime_rate">' . lang('Real-Time Rate') . '</label>
                                            </div>
                                            <script>init_shipping_method_service()</script>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="col-12 col-lg-4">
                                                <div class=" border-1 border rounded p-2 m-1">
                                                    <label class="form-label" for="base_rate">' . lang('Base Rate') . '</label>
                                                    <div class="input-group">
                                                        <input value="' . $base_rate . '" type="text" name="base_rate" id="base_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                        <label class="input-group-text" for="base_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                    </div>
                                                    <div class="form-check form-switch  my-2 ms-3">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="variable_base_rate" name="variable_base_rate" value="1"' . $output_variable_base_rate_checked . ' data-bs-target="#base_rate_rows"/>
                                                        <label class="form-check-label text-wrap" for="variable_base_rate">' . lang('Enable variable base rate') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 mt-1" id="base_rate_rows">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">' . lang('The base rate above') . '</div>
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_2">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $base_rate_2 . '" style="min-width: 90px;" type="text" name="base_rate_2" id="base_rate_2" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_2">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_2_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input value="' . $base_rate_2_subtotal . '" style="min-width: 90px;" type="text" name="base_rate_2_subtotal" id="base_rate_2_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_2_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_3">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $base_rate_3 . '" style="min-width: 90px;" type="text" name="base_rate_3" id="base_rate_3" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text " for="base_rate_3">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_3_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input value="' . $base_rate_3_subtotal . '" style="min-width: 90px;" type="text" name="base_rate_3_subtotal" id="base_rate_3_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_3_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_4">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $base_rate_4 . '" style="min-width: 90px;" type="text" name="base_rate_4" id="base_rate_4" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_4">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_4_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input value="' . $base_rate_4_subtotal . '" style="min-width: 90px;" type="text" name="base_rate_4_subtotal" id="base_rate_4_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_4_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4 my-2">
                                            <div class=" border-1 border rounded p-2 m-1">
                                                <label class="form-label" for="primary_weight_rate">' . lang('Primary Weight Rate') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $primary_weight_rate . '" type="text" name="primary_weight_rate" id="primary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="primary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="primary_weight_rate_first_item_excluded" name="primary_weight_rate_first_item_excluded" value="1"' . $primary_weight_rate_first_item_excluded_checked . ' />
                                                    <label class="form-check-label text-wrap" for="primary_weight_rate_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4 my-2">
                                            <div class=" border-1 border rounded p-2 m-1">
                                                <label class="form-label" for="secondary_weight_rate">' . lang('Secondary Weight Rate') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $secondary_weight_rate . '" type="text" name="secondary_weight_rate" id="secondary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="primary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="secondary_weight_rate_first_item_excluded" name="secondary_weight_rate_first_item_excluded" value="1"' . $secondary_weight_rate_first_item_excluded_checked . ' />
                                                    <label class="form-check-label text-wrap" for="secondary_weight_rate_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4 my-2">
                                            <div class=" border-1 border rounded p-2 m-1">
                                                <label class="form-label" for="item_rate">' . lang('Item Rate') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $item_rate . '" type="text" name="item_rate" id="item_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="item_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="item_rate_first_item_excluded" name="item_rate_first_item_excluded" value="1"' . $item_rate_first_item_excluded_checked . ' />
                                                    <label class="form-check-label text-wrap" for="item_rate_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
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
                                    ' . lang('Destination Delivery Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="street_address" name="street_address" value="1"' . $street_address_checked . '/>
                                                <label class="form-check-label" for="street_address">' . lang('Allow Street Address') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="po_box" name="po_box" value="1"' . $po_box_checked . '/>
                                                <label class="form-check-label" for="po_box">' . lang('Allow PO Box') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
                                            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $output_allowed_zones . '</select>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="handle_days" class="form-label">' . lang('Handling Time') . '</label>
                                            <div class="input-group">
                                                <input value="' . h($handle_days) . '" type="text" name="handle_days" id="handle_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="end_of_day" class="form-label">' . lang('End of Day Time') . '</label>
                                            <input value="' . $end_of_day . '" type="text" name="end_of_day" id="end_of_day" class="form-control" maxlength="8" />
                                            <div class="form-text text-end" >' . lang('h:mm AM/PM') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h5 class="text-muted">' . lang('Handling on') . '</h5>
                                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-auto">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_mon" name="handle_mon" value="1"' . $handle_mon_checked . '>
                                                        <label class="form-check-label" for="handle_mon">' . lang('Monday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_tue" name="handle_tue" value="1"' . $handle_tue_checked . '>
                                                        <label class="form-check-label" for="handle_tue">' . lang('Tuesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_wed" name="handle_wed" value="1"' . $handle_wed_checked . '>
                                                        <label class="form-check-label" for="handle_wed">' . lang('Wednesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_thu" name="handle_thu" value="1"' . $handle_thu_checked . '>
                                                        <label class="form-check-label" for="handle_thu">' . lang('Thursday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_fri" name="handle_fri" value="1"' . $handle_fri_checked . '>
                                                        <label class="form-check-label" for="handle_fri">' . lang('Friday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_sat" name="handle_sat" value="1"' . $handle_sat_checked . '>
                                                        <label class="form-check-label" for="handle_sat">' . lang('Saturday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_sun" name="handle_sun" value="1"' . $handle_sun_checked . '>
                                                        <label class="form-check-label" for="handle_sun">' . lang('Sunday') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h5 class="text-muted mt-3">' . lang('Ships on') . '</h5>
                                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-auto">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_mon" name="ship_mon" value="1"' . $ship_mon_checked . '>
                                                        <label class="form-check-label" for="ship_mon">' . lang('Monday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_tue" name="ship_tue" value="1"' . $ship_tue_checked . '>
                                                        <label class="form-check-label" for="ship_tue">' . lang('Tuesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_wed" name="ship_wed" value="1"' . $ship_wed_checked . '>
                                                        <label class="form-check-label" for="ship_wed">' . lang('Wednesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_thu" name="ship_thu" value="1"' . $ship_thu_checked . '>
                                                        <label class="form-check-label" for="ship_thu">' . lang('Thursday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_fri" name="ship_fri" value="1"' . $ship_fri_checked . '>
                                                        <label class="form-check-label" for="ship_fri">' . lang('Friday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_sat" name="ship_sat" value="1"' . $ship_sat_checked . '>
                                                        <label class="form-check-label" for="ship_sat">' . lang('Saturday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_sun" name="ship_sun" value="1"' . $ship_sun_checked . '>
                                                        <label class="form-check-label" for="ship_sun">' . lang('Sunday') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="base_transit_days" class="form-label">' . lang('Base Transit Time') . '</label>
                                            <div class="input-group">
                                                <input value="' . $base_transit_days . '" type="text" name="base_transit_days" id="base_transit_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="adjust_transit" name="adjust_transit" value="1"' . $adjust_transit_checked . '/>
                                                <label class="form-check-label" for="adjust_transit">' . lang('Adjust Transit for Country') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="transit_on_saturday" name="transit_on_saturday" value="1"' . $transit_on_saturday_checked . '/>
                                                <label class="form-check-label" for="transit_on_saturday">' . lang('Transit on Saturday') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="transit_on_sunday" name="transit_on_sunday" value="1"' . $transit_on_sunday_checked . '/>
                                                <label class="form-check-label" for="transit_on_sunday">' . lang('Transit on Sunday') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Shipping Method Availability') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="status" name="status" value="1"' . $status_enabled_checked . ' />
                                                <label class="form-check-label" for="status">' . lang('Enabled this shipping method') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="protected" name="protected" value="1"' . $protected_checked . ' />
                                                <label class="form-check-label" for="protected">' . lang('Protected') . '</label>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3 mb-2">
                                            <label for="excluded_transit_dates" class="form-label">' . lang('Excluded Dates') . '</label>
                                            <input value="' . $output_excluded_transit_dates . '" type="text" name="excluded_transit_dates" id="excluded_transit_dates" class="form-control tagin min-height-tagin"  maxlength="255"/>
                                            <div class="form-text text-end">' . lang('Excluded Dates for Shipping & Handling (e.g. Holidays). Split dates with enter key or \',\' key') . ' (' . get_date_format_help() . ')</div>
                                            <script>
                                                if(document.body.contains(document.querySelector("input#excluded_transit_dates"))){
                                                    tagin( document.querySelector("#excluded_transit_dates") );
                                                }
                                            </script>
                                        </div>
                                        <div class="col-12 col-lg-8 my-3">
                                            <div class="row">
                                                <div class="col-12 col-md">
                                                    <label for="start_time" class="form-label">' . lang('Start Time') . '</label>
                                                    <input value="' . prepare_form_data_for_output($start_time, 'date and time') . '" 12:00 AM" type="text" name="start_time" id="start_time" class="form-control" maxlength="10" autocomplete="off"/>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label for="end_time" class="form-label">' . lang('End Time') . '</label>
                                                    <input value="' . prepare_form_data_for_output($end_time, 'date and time') . '" type="text" name="end_time" id="end_time" class="form-control " maxlength="10" autocomplete="off"/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h5 class="text-muted mt-3">' . lang('Available on') . '</h5>
                                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_monday" name="available_on_monday" value="1"' . $available_on_monday_checked . ' data-bs-target="#available_on_monday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_monday">' . lang('Monday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_monday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_monday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_monday_cutoff_time" id="available_on_monday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_monday_cutoff_time, 'time', true) . '"  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_tuesday" name="available_on_tuesday" value="1"' . $available_on_tuesday_checked . ' data-bs-target="#available_on_tuesday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_tuesday">' . lang('Tuesday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_tuesday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_tuesday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_tuesday_cutoff_time" id="available_on_tuesday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_tuesday_cutoff_time, 'time', true) . '"  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_wednesday" name="available_on_wednesday" value="1"' . $available_on_wednesday_checked . ' data-bs-target="#available_on_wednesday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_wednesday">' . lang('Wednesday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_wednesday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_wednesday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_wednesday_cutoff_time" id="available_on_wednesday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_wednesday_cutoff_time, 'time', true) . '" class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_thursday" name="available_on_thursday" value="1"' . $available_on_thursday_checked . ' data-bs-target="#available_on_thursday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_thursday">' . lang('Thursday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_thursday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_thursday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_thursday_cutoff_time" id="available_on_thursday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_thursday_cutoff_time, 'time', true) . '"  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_friday" name="available_on_friday" value="1"' . $available_on_friday_checked . ' data-bs-target="#available_on_friday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_friday">' . lang('Friday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_friday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_friday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_friday_cutoff_time" id="available_on_friday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_friday_cutoff_time, 'time', true) . '" class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_saturday" name="available_on_saturday" value="1"' . $available_on_saturday_checked . ' data-bs-target="#available_on_saturday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_saturday">' . lang('Saturday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_saturday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_saturday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_saturday_cutoff_time" id="available_on_saturday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_saturday_cutoff_time, 'time', true) . '"  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_sunday" name="available_on_sunday" value="1"' . $available_on_sunday_checked . ' data-bs-target="#available_on_sunday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_sunday">' . lang('Sunday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_sunday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_sunday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_sunday_cutoff_time" id="available_on_sunday_cutoff_time" size="8" maxlength="8" value="' . prepare_form_data_for_output($available_on_sunday_cutoff_time, 'time', true) . '"  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <h6><em>' . lang(array('string'=>'For reference, the current site time is {var:1}.','vars'=>date("g:i A"))) . '</em></h6>
                                        </div>

                                        <script>
                                        $("#end_time,#start_time").datetimepicker(datetimepicker_options);
                                            $("#end_of_day,#available_on_monday_cutoff_time,#available_on_tuesday_cutoff_time,#available_on_wednesday_cutoff_time,#available_on_thursday_cutoff_time,#available_on_friday_cutoff_time,#available_on_saturday_cutoff_time,#available_on_sunday_cutoff_time").timepicker(datetimepicker_options);
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('shipping method')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

    print $output;

} else {

    validate_token_field();
    
    // delete shipping method references in shipping_methods_zones_xref (we do this reguardless of whether we are deleting the shipping method or updating the shipping method)
    $query = "DELETE FROM shipping_methods_zones_xref ".
             "WHERE shipping_method_id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // delete excluded transit dates for this shipping method (we do this reguardless of whether we are deleting the shipping method or updating the shipping method)
    $query = "DELETE FROM excluded_transit_dates WHERE shipping_method_id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // if shipping method was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete shipping_method
        $query = "DELETE FROM shipping_methods WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete records in offer_actions_shipping_methods_xref for this shipping method
        $query = "DELETE FROM offer_actions_shipping_methods_xref WHERE shipping_method_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete shipping cut-offs for this shipping method
        $query = "DELETE FROM shipping_cutoffs WHERE shipping_method_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        db("DELETE FROM ship_date_adjustments WHERE shipping_method_id = '" . escape($_POST['id'] ?? '') . "'");

        log_activity(lang(array('string'=>'shipping method ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
        
    // else shipping method was not selected for delete
    } else {

        $service = $_POST['service'];

        $realtime_rate = $_POST['realtime_rate'];

        // If the service does not support real-time rates then clear real-time rate value, so it
        // won't be enabled in db and show check mark on All Shipping Methods screen.
        if (!$service or substr($service, 0, 5) == 'fedex') {
            $realtime_rate = 0;
        }

        $base_rate = 0;
        if($_POST['base_rate']){
            // remove commas and spaces from price
            $base_rate = str_replace(',', '', $_POST['base_rate']);
            $base_rate = str_replace(' ', '',$base_rate); 
            // convert price from dollars to cents
            $base_rate = $base_rate * 100;
        }
        $primary_weight_rate = 0;
        if($_POST['primary_weight_rate']){
            // remove commas and spaces from price
            $primary_weight_rate = str_replace(',', '', $_POST['primary_weight_rate']);
            $primary_weight_rate = str_replace(' ', '',$primary_weight_rate); 
            // convert price from dollars to cents
            $primary_weight_rate = $primary_weight_rate * 100;
        }
        $secondary_weight_rate = 0;
        if($_POST['secondary_weight_rate']){
            // remove commas and spaces from price
            $secondary_weight_rate = str_replace(',', '', $_POST['secondary_weight_rate']);
            $secondary_weight_rate = str_replace(' ', '',$secondary_weight_rate); 
            // convert price from dollars to cents
            $secondary_weight_rate = $secondary_weight_rate * 100;
        }
        $item_rate = 0;
        if($_POST['item_rate']){
            // remove commas and spaces from price
            $item_rate = str_replace(',', '', $_POST['item_rate']);
            $item_rate = str_replace(' ', '',$item_rate); 
            // convert price from dollars to cents
            $item_rate = $item_rate * 100;
        }
        $variable_base_rate = 0;
        $base_rate_2 = 0;
        $base_rate_2_subtotal = 0;
        $base_rate_3 = 0;
        $base_rate_3_subtotal = 0;
        $base_rate_4 = 0;
        $base_rate_4_subtotal = 0;

        // If variable base rate is enabled, then prepare that data.
        if ($_POST['variable_base_rate'] == 1) {
            $variable_base_rates = array();

            // Loop through the different variable base rate fields
            // in order to add them to array if data was entered for them.
            for ($number = 2; $number <= 4; $number++) { 
                // If a valid base rate and subtotal was entered, then add this variable base rate.
                if (
                    (is_numeric($_POST['base_rate_' . $number]))
                    && ($_POST['base_rate_' . $number] >= 0)
                    && (is_numeric($_POST['base_rate_' . $number . '_subtotal']))
                    && ($_POST['base_rate_' . $number . '_subtotal'] > 0)
                ) {
                    // remove commas and spaces from price
                    $variable_base_rate = str_replace(',', '', $_POST['base_rate_' . $number . '_subtotal']);
                    $variable_base_rate = str_replace(' ', '',$variable_base_rate); 
                    // convert price from dollars to cents
                    $variable_base_rate = $variable_base_rate * 100;

                    $variable_base_rates[] = array(
                        'subtotal' =>$variable_base_rate,
                        'rate' => $_POST['base_rate_' . $number] * 100);
                }
            }

            // If there is at least one valid variable base rate, then deal with them.
            if ($variable_base_rates) {
                // Prepare to enable variable base rates in the db.
                $variable_base_rate = 1;

                // Order the variable base rates by the subtotal (subtotal is the first value in the array).
                sort($variable_base_rates);

                // Loop through the variable base rates in order to prepare db values.
                foreach ($variable_base_rates as $key => $rate) {
                    $number = $key + 2;

                    ${'base_rate_' . $number} = $rate['rate'];
                    ${'base_rate_' . $number . '_subtotal'} = $rate['subtotal'];
                }
            }
        }

        if($_POST['status'] == 1){
            $status = 'enabled';
        }else{
            $status = 'disabled';
        }

        // update shipping method
        $query = "UPDATE shipping_methods SET
                    name = '" . escape($_POST['name'] ?? '') . "',
                    description = '" . escape($_POST['description'] ?? '') . "',
                    code = '" . escape($_POST['code'] ?? '') . "',
                    status = '" . escape($status) . "',
                    start_time = '" . escape(prepare_form_data_for_input($_POST['start_time'], 'date and time')) . "',
                    end_time = '" . escape(prepare_form_data_for_input($_POST['end_time'], 'date and time')) . "',
                    service = '" . e($_POST['service'] ?? '') . "',
                    realtime_rate = '" . e($realtime_rate) . "',
                    base_rate = '" . escape($base_rate) . "',
                    variable_base_rate = '" . escape($variable_base_rate) . "',
                    base_rate_2 = '" . escape($base_rate_2) . "',
                    base_rate_2_subtotal = '" . escape($base_rate_2_subtotal) . "',
                    base_rate_3 = '" . escape($base_rate_3) . "',
                    base_rate_3_subtotal = '" . escape($base_rate_3_subtotal) . "',
                    base_rate_4 = '" . escape($base_rate_4) . "',
                    base_rate_4_subtotal = '" . escape($base_rate_4_subtotal) . "',
                    primary_weight_rate = '" . escape($primary_weight_rate) . "',
                    primary_weight_rate_first_item_excluded = '" . escape($_POST['primary_weight_rate_first_item_excluded'] ?? '') . "',
                    secondary_weight_rate = '" . escape($secondary_weight_rate) . "',
                    secondary_weight_rate_first_item_excluded = '" . escape($_POST['secondary_weight_rate_first_item_excluded'] ?? '') . "',
                    item_rate = '" . escape($item_rate) . "',
                    item_rate_first_item_excluded = '" . escape($_POST['item_rate_first_item_excluded'] ?? '') . "',
                    base_transit_days = '" . escape($_POST['base_transit_days'] ?? '') . "',
                    adjust_transit = '" . escape($_POST['adjust_transit'] ?? '') . "',
                    street_address = '" . escape($_POST['street_address'] ?? '') . "',
                    po_box = '" . escape($_POST['po_box'] ?? '') . "',
                    handle_days = '" . e($_POST['handle_days'] ?? '') . "',
                    handle_mon = '" . e($_POST['handle_mon'] ?? '') . "',
                    handle_tue = '" . e($_POST['handle_tue'] ?? '') . "',
                    handle_wed = '" . e($_POST['handle_wed'] ?? '') . "',
                    handle_thu = '" . e($_POST['handle_thu'] ?? '') . "',
                    handle_fri = '" . e($_POST['handle_fri'] ?? '') . "',
                    handle_sat = '" . e($_POST['handle_sat'] ?? '') . "',
                    handle_sun = '" . e($_POST['handle_sun'] ?? '') . "',
                    ship_mon = '" . e($_POST['ship_mon'] ?? '') . "',
                    ship_tue = '" . e($_POST['ship_tue'] ?? '') . "',
                    ship_wed = '" . e($_POST['ship_wed'] ?? '') . "',
                    ship_thu = '" . e($_POST['ship_thu'] ?? '') . "',
                    ship_fri = '" . e($_POST['ship_fri'] ?? '') . "',
                    ship_sat = '" . e($_POST['ship_sat'] ?? '') . "',
                    ship_sun = '" . e($_POST['ship_sun'] ?? '') . "',
                    end_of_day = '" . e(prepare_form_data_for_input($_POST['end_of_day'], 'time')) . "',
                    transit_on_sunday = '" . escape($_POST['transit_on_sunday'] ?? '') . "',
                    transit_on_saturday = '" . escape($_POST['transit_on_saturday'] ?? '') . "',
                    available_on_sunday = '" . escape($_POST['available_on_sunday'] ?? '') . "',
                    available_on_sunday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_sunday_cutoff_time'], 'time')) . "',
                    available_on_monday = '" . escape($_POST['available_on_monday'] ?? '') . "',
                    available_on_monday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_monday_cutoff_time'], 'time')) . "',
                    available_on_tuesday = '" . escape($_POST['available_on_tuesday'] ?? '') . "',
                    available_on_tuesday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_tuesday_cutoff_time'], 'time')) . "',
                    available_on_wednesday = '" . escape($_POST['available_on_wednesday'] ?? '') . "',
                    available_on_wednesday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_wednesday_cutoff_time'], 'time')) . "',
                    available_on_thursday = '" . escape($_POST['available_on_thursday'] ?? '') . "',
                    available_on_thursday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_thursday_cutoff_time'], 'time')) . "',
                    available_on_friday = '" . escape($_POST['available_on_friday'] ?? '') . "',
                    available_on_friday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_friday_cutoff_time'], 'time')) . "',
                    available_on_saturday = '" . escape($_POST['available_on_saturday'] ?? '') . "',
                    available_on_saturday_cutoff_time = '" . escape(prepare_form_data_for_input($_POST['available_on_saturday_cutoff_time'], 'time')) . "',
                    protected = '" . e($_POST['protected'] ?? '') . "',
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // load all zones in array by exploding string that has allowed zone ids separated by commas
        $allowed_zones = $_POST['allowed_zones'];

        // foreach allowed zone insert row in shipping_methods_zones_xref table
        foreach ($allowed_zones as $zone_id) {
            // if zone id is not blank, insert row
            if ($zone_id) {
                $query = "INSERT INTO shipping_methods_zones_xref (shipping_method_id, zone_id) VALUES ('" . escape($_POST['id'] ?? '') . "', '" . escape($zone_id) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
        
        // load all excluded transit dates into an array
        $excluded_transit_dates =  explode(',', $_POST['excluded_transit_dates']);
        
        // loop through all excluded transit dates in order to validate
        foreach ($excluded_transit_dates as $key => $excluded_transit_date) {
            // remove spaces from beginning and end of date
            $excluded_transit_date = trim($excluded_transit_date);
            
            // convert date to storage format
            $excluded_transit_date = prepare_form_data_for_input($excluded_transit_date, 'date');
            
            // split date into parts
            $excluded_transit_date_parts = explode('-', $excluded_transit_date);
            $year = $excluded_transit_date_parts[0];
            $month = $excluded_transit_date_parts[1];
            $day = $excluded_transit_date_parts[2];
            
            // if date is valid then update date in array
            if ((is_numeric($month) == true) && (is_numeric($day) == true) && (is_numeric($year) == true) && (checkdate($month, $day, $year) == true)) {
                $excluded_transit_dates[$key] = $excluded_transit_date;
                
            // else date is not valid, so remove date from array
            } else {
                unset($excluded_transit_dates[$key]);
            }
        }
        
        // remove duplicate dates from array
        $excluded_transit_dates = array_unique($excluded_transit_dates);
        
        // sort array
        sort($excluded_transit_dates);
        
        // loop through all excluded transit dates in order to add dates to database
        foreach ($excluded_transit_dates as $excluded_transit_date) {
            $query =
                "INSERT INTO excluded_transit_dates (
                    shipping_method_id,
                    date)
                VALUES (
                    '" . escape($_POST['id'] ?? '') . "',
                    '" . escape($excluded_transit_date) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
        
        log_activity(lang(array('string'=>'shipping method ({var:1}) was modified','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    }

    // forward user to view shipping methods screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_shipping_methods.php');
}
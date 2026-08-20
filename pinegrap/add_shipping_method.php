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

    // only ever appended to below, so it has to start out empty
    $output_disallowed_zones = '';

    // get all zones for zones selection
    $query = "SELECT id, name FROM zones ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        $output_disallowed_zones .= '<option value="' . $row['id'] . '">' . h($row['name']) . '</option>';
    }

    if (DATE_FORMAT == 'month_day') {
        $output_end_time = '12/31/2099 11:59 PM';
    } else {
        $output_end_time = '31/12/2099 11:59 PM';
    }

    $output =
    pg_page_shell([
        'title'=> lang('Create Shipping Method'),
        'extra_classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Shipping Method'),
        'cancel'=>array('enable'=>'true','url'=>'view_shipping_methods.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Methods'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_methods.php'), array('label' => lang('Create Shipping Method'))),
        ]) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
    ' . get_date_picker_format() . get_date_time_picker_format() . get_time_picker_format() . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new shipping method that will be made available during checkout based on the products and destination address.') . '" title="' . lang('Create Shipping Method') . '">[' . lang('Shipping Method Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_shipping_method.php" method="post">
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
                                            <input type="text" name="name" placeholder="' . lang('Shipping Method Name') . '" id="name" maxlength="50" class="form-control add-header-content-updater" />
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-8 my-2">
                                            <label for="description" class="form-label">' . lang('Display Message') . '</label>
                                            <input type="text" name="description" id="description" class="form-control" maxlength="255" />
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            <input type="text" name="code" id="code" class="form-control" maxlength="50" />
                                            <div class="form-text text-end">' . lang('Shipping Method Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="service" class="form-label">' . lang('Service') . '</label>
                                            ' . render(array('template' => 'shipping_method_service.php')) . '
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
                                                <input class="form-check-input" type="checkbox" id="realtime_rate" name="realtime_rate" value="1"/>
                                                <label class="form-check-label" for="realtime_rate">' . lang('Real-Time Rate') . '</label>
                                            </div>
                                            <script>init_shipping_method_service()</script>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="col-12 col-lg-4">
                                                <div class=" border-1 border rounded p-2 m-1">
                                                    <label class="form-label" for="base_rate">' . lang('Base Rate') . '</label>
                                                    <div class="input-group">
                                                        <input type="text" name="base_rate" id="base_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                        <label class="input-group-text" for="base_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                    </div>
                                                    <div class="form-check form-switch  my-2 ms-3">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="variable_base_rate" name="variable_base_rate" value="1"  data-bs-target="#base_rate_rows"/>
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
                                                                <input style="min-width: 90px;" type="text" name="base_rate_2" id="base_rate_2" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_2">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_2_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input style="min-width: 90px;" type="text" name="base_rate_2_subtotal" id="base_rate_2_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_2_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_3">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input style="min-width: 90px;" type="text" name="base_rate_3" id="base_rate_3" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text " for="base_rate_3">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_3_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input style="min-width: 90px;" type="text" name="base_rate_3_subtotal" id="base_rate_3_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_3_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_4">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input style="min-width: 90px;" type="text" name="base_rate_4" id="base_rate_4" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_4">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_4_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input style="min-width: 90px;" type="text" name="base_rate_4_subtotal" id="base_rate_4_subtotal" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
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
                                                    <input type="text" name="primary_weight_rate" id="primary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="primary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="primary_weight_rate_first_item_excluded" name="primary_weight_rate_first_item_excluded" value="1" />
                                                    <label class="form-check-label text-wrap" for="primary_weight_rate_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4 my-2">
                                            <div class=" border-1 border rounded p-2 m-1">
                                                <label class="form-label" for="secondary_weight_rate">' . lang('Secondary Weight Rate') . '</label>
                                                <div class="input-group">
                                                    <input type="text" name="secondary_weight_rate" id="secondary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="primary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="secondary_weight_rate_first_item_excluded" name="secondary_weight_rate_first_item_excluded" value="1" />
                                                    <label class="form-check-label text-wrap" for="secondary_weight_rate_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4 my-2">
                                            <div class=" border-1 border rounded p-2 m-1">
                                                <label class="form-label" for="item_rate">' . lang('Item Rate') . '</label>
                                                <div class="input-group">
                                                    <input type="text" name="item_rate" id="item_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <label class="input-group-text" for="item_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                                </div>
                                                <div class="form-check form-switch my-2 ms-3">
                                                    <input class="form-check-input" type="checkbox" id="item_rate_first_item_excluded" name="item_rate_first_item_excluded" value="1" />
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
                                                <input class="form-check-input" type="checkbox" id="street_address" name="street_address" value="1" checked="checked"/>
                                                <label class="form-check-label" for="street_address">' . lang('Allow Street Address') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="po_box" name="po_box" value="1" checked="checked"/>
                                                <label class="form-check-label" for="po_box">' . lang('Allow PO Box') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
                                            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $output_disallowed_zones . '</select>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="handle_days" class="form-label">' . lang('Handling Time') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="handle_days" id="handle_days" class="form-control" value="" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="end_of_day" class="form-label">' . lang('End of Day Time') . '</label>
                                            <input type="text" name="end_of_day" id="end_of_day" class="form-control" value="" maxlength="8" />
                                            <div class="form-text text-end" >' . lang('h:mm AM/PM') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h5 class="text-muted">' . lang('Handling on') . '</h5>
                                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-auto">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_mon" name="handle_mon" value="1" checked="checked">
                                                        <label class="form-check-label" for="handle_mon">' . lang('Monday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_tue" name="handle_tue" value="1" checked="checked">
                                                        <label class="form-check-label" for="handle_tue">' . lang('Tuesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_wed" name="handle_wed" value="1" checked="checked">
                                                        <label class="form-check-label" for="handle_wed">' . lang('Wednesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_thu" name="handle_thu" value="1" checked="checked">
                                                        <label class="form-check-label" for="handle_thu">' . lang('Thursday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_fri" name="handle_fri" value="1" checked="checked">
                                                        <label class="form-check-label" for="handle_fri">' . lang('Friday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_sat" name="handle_sat" value="1">
                                                        <label class="form-check-label" for="handle_sat">' . lang('Saturday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="handle_sun" name="handle_sun" value="1">
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
                                                        <input class="form-check-input" type="checkbox" id="ship_mon" name="ship_mon" value="1" checked="checked">
                                                        <label class="form-check-label" for="ship_mon">' . lang('Monday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_tue" name="ship_tue" value="1" checked="checked">
                                                        <label class="form-check-label" for="ship_tue">' . lang('Tuesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_wed" name="ship_wed" value="1" checked="checked">
                                                        <label class="form-check-label" for="ship_wed">' . lang('Wednesday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_thu" name="ship_thu" value="1" checked="checked">
                                                        <label class="form-check-label" for="ship_thu">' . lang('Thursday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_fri" name="ship_fri" value="1" checked="checked">
                                                        <label class="form-check-label" for="ship_fri">' . lang('Friday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_sat" name="ship_sat" value="1">
                                                        <label class="form-check-label" for="ship_sat">' . lang('Saturday') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="ship_sun" name="ship_sun" value="1">
                                                        <label class="form-check-label" for="ship_sun">' . lang('Sunday') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                            <label for="base_transit_days" class="form-label">' . lang('Base Transit Time') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="base_transit_days" id="base_transit_days" class="form-control" value="" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="adjust_transit" name="adjust_transit" value="1"/>
                                                <label class="form-check-label" for="adjust_transit">' . lang('Adjust Transit for Country') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="transit_on_saturday" name="transit_on_saturday" value="1"/>
                                                <label class="form-check-label" for="transit_on_saturday">' . lang('Transit on Saturday') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="transit_on_sunday" name="transit_on_sunday" value="1"/>
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
                                                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" checked="checked" />
                                                <label class="form-check-label" for="status">' . lang('Enabled this shipping method') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="protected" name="protected" value="1" />
                                                <label class="form-check-label" for="protected">' . lang('Protected') . '</label>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3 mb-2">
                                            <label for="excluded_transit_dates" class="form-label">' . lang('Excluded Dates') . '</label>
                                            <input type="text" name="excluded_transit_dates" id="excluded_transit_dates" class="form-control tagin min-height-tagin"  maxlength="255"/>
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
                                                    <input value="'. date('n/j/Y') . ' 12:00 AM" type="text" name="start_time" id="start_time" class="form-control" maxlength="10" autocomplete="off"/>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label for="end_time" class="form-label">' . lang('End Time') . '</label>
                                                    <input value="' . $output_end_time . '" type="text" name="end_time" id="end_time" class="form-control " maxlength="10" autocomplete="off"/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <h5 class="text-muted mt-3">' . lang('Available on') . '</h5>
                                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_monday" name="available_on_monday" value="1" checked="checked" data-bs-target="#available_on_monday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_monday">' . lang('Monday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_monday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_monday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_monday_cutoff_time" id="available_on_monday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_tuesday" name="available_on_tuesday" value="1" checked="checked" data-bs-target="#available_on_tuesday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_tuesday">' . lang('Tuesday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_tuesday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_tuesday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_tuesday_cutoff_time" id="available_on_tuesday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_wednesday" name="available_on_wednesday" value="1" checked="checked" data-bs-target="#available_on_wednesday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_wednesday">' . lang('Wednesday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_wednesday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_wednesday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_wednesday_cutoff_time" id="available_on_wednesday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_thursday" name="available_on_thursday" value="1" checked="checked" data-bs-target="#available_on_thursday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_thursday">' . lang('Thursday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_thursday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_thursday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_thursday_cutoff_time" id="available_on_thursday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_friday" name="available_on_friday" value="1" checked="checked" data-bs-target="#available_on_friday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_friday">' . lang('Friday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_friday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_friday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_friday_cutoff_time" id="available_on_friday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_saturday" name="available_on_saturday" value="1" checked="checked" data-bs-target="#available_on_saturday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_saturday">' . lang('Saturday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_saturday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_saturday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_saturday_cutoff_time" id="available_on_saturday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input collapse-switcher" type="checkbox" id="available_on_sunday" name="available_on_sunday" value="1" checked="checked" data-bs-target="#available_on_sunday_cutoff_time_cell">
                                                        <label class="form-check-label" for="available_on_sunday">' . lang('Sunday') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-auto" id="available_on_sunday_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="available_on_sunday_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label> 
                                                                    <input type="text" name="available_on_sunday_cutoff_time" id="available_on_sunday_cutoff_time" size="8" maxlength="8" value=""  class="form-control" /> 
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

    $service = $_POST['service'];

    $realtime_rate = $_POST['realtime_rate'];

    // If the service does not support real-time rates then clear real-time rate value, so it
    // won't be enabled in db and show check mark on All Shipping Methods screen.
    if (!$service or substr($service, 0, 5) == 'fedex') {
        $realtime_rate = 0;
    }
    
    if($_POST['status'] == 1){
        $status = 'enabled';
    }else{
        $status = 'disabled';
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
        $variable_base_rate = 0;
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
                    'subtotal' =>  $variable_base_rate,
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

    // create shipping method
    $query = "INSERT INTO shipping_methods (
                name,
                description,
                code,
                status,
                start_time,
                end_time,
                service,
                realtime_rate,
                base_rate,
                variable_base_rate,
                base_rate_2,
                base_rate_2_subtotal,
                base_rate_3,
                base_rate_3_subtotal,
                base_rate_4,
                base_rate_4_subtotal,
                primary_weight_rate,
                primary_weight_rate_first_item_excluded,
                secondary_weight_rate,
                secondary_weight_rate_first_item_excluded,
                item_rate,
                item_rate_first_item_excluded,
                base_transit_days,
                adjust_transit,
                street_address,
                po_box,
                handle_days,
                handle_mon,
                handle_tue,
                handle_wed,
                handle_thu,
                handle_fri,
                handle_sat,
                handle_sun,
                ship_mon,
                ship_tue,
                ship_wed,
                ship_thu,
                ship_fri,
                ship_sat,
                ship_sun,
                end_of_day,
                transit_on_sunday,
                transit_on_saturday,
                available_on_sunday,
                available_on_sunday_cutoff_time,
                available_on_monday,
                available_on_monday_cutoff_time,
                available_on_tuesday,
                available_on_tuesday_cutoff_time,
                available_on_wednesday,
                available_on_wednesday_cutoff_time,
                available_on_thursday,
                available_on_thursday_cutoff_time,
                available_on_friday,
                available_on_friday_cutoff_time,
                available_on_saturday,
                available_on_saturday_cutoff_time,
                protected,
                user,
                timestamp)
            VALUES (
                '" . escape($_POST['name'] ?? '') . "',
                '" . escape($_POST['description'] ?? '') . "',
                '" . escape($_POST['code'] ?? '') . "',
                '" . escape($status) . "',
                '" . escape(prepare_form_data_for_input($_POST['start_time'], 'date and time')) . "',
                '" . escape(prepare_form_data_for_input($_POST['end_time'], 'date and time')) . "',
                '" . e($_POST['service'] ?? '') . "',
                '" . e($realtime_rate) . "',
                '" . escape($base_rate) . "',
                '" . escape($variable_base_rate) . "',
                '" . escape($base_rate_2) . "',
                '" . escape($base_rate_2_subtotal) . "',
                '" . escape($base_rate_3) . "',
                '" . escape($base_rate_3_subtotal) . "',
                '" . escape($base_rate_4) . "',
                '" . escape($base_rate_4_subtotal) . "',
                '" . escape($primary_weight_rate) . "',
                '" . escape($_POST['primary_weight_rate_first_item_excluded'] ?? '') . "',
                '" . escape($secondary_weight_rate) . "',
                '" . escape($_POST['secondary_weight_rate_first_item_excluded'] ?? '') . "',
                '" . escape($item_rate) . "',
                '" . escape($_POST['item_rate_first_item_excluded'] ?? '') . "',
                '" . escape($_POST['base_transit_days'] ?? '') . "',
                '" . escape($_POST['adjust_transit'] ?? '') . "',
                '" . escape($_POST['street_address'] ?? '') . "',
                '" . escape($_POST['po_box'] ?? '') . "',
                '" . e($_POST['handle_days'] ?? '') . "',
                '" . e($_POST['handle_mon'] ?? '') . "',
                '" . e($_POST['handle_tue'] ?? '') . "',
                '" . e($_POST['handle_wed'] ?? '') . "',
                '" . e($_POST['handle_thu'] ?? '') . "',
                '" . e($_POST['handle_fri'] ?? '') . "',
                '" . e($_POST['handle_sat'] ?? '') . "',
                '" . e($_POST['handle_sun'] ?? '') . "',
                '" . e($_POST['ship_mon'] ?? '') . "',
                '" . e($_POST['ship_tue'] ?? '') . "',
                '" . e($_POST['ship_wed'] ?? '') . "',
                '" . e($_POST['ship_thu'] ?? '') . "',
                '" . e($_POST['ship_fri'] ?? '') . "',
                '" . e($_POST['ship_sat'] ?? '') . "',
                '" . e($_POST['ship_sun'] ?? '') . "',
                '" . e(prepare_form_data_for_input($_POST['end_of_day'], 'time')) . "',
                '" . escape($_POST['transit_on_sunday'] ?? '') . "',
                '" . escape($_POST['transit_on_saturday'] ?? '') . "',
                '" . escape($_POST['available_on_sunday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_sunday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_monday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_monday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_tuesday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_tuesday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_wednesday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_wednesday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_thursday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_thursday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_friday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_friday_cutoff_time'], 'time')) . "',
                '" . escape($_POST['available_on_saturday'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['available_on_saturday_cutoff_time'], 'time')) . "',
                '" . e($_POST['protected'] ?? '') . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $shipping_method_id = mysqli_insert_id(db::$con);

    // load all allowed zones in array by exploding string that has allowed zone ids separated by commas
    $allowed_zones = $_POST['allowed_zones'];

    // foreach allowed zone insert row in shipping_methods_zones_xref table
    foreach ($allowed_zones as $zone_id) {
        // if zone id is not blank, insert row
        if ($zone_id) {
            $query = "INSERT INTO shipping_methods_zones_xref (shipping_method_id, zone_id) VALUES ($shipping_method_id, '" . escape($zone_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }
    
    // load all excluded transit dates into an array
    $excluded_transit_dates = explode(',', $_POST['excluded_transit_dates']);
    
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
                '$shipping_method_id',
                '" . escape($excluded_transit_date) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
    
    log_activity(lang(array('string'=>'shipping method ({var:1}) was created','vars'=>$_POST['name'])), $_SESSION['sessionusername']);

    // forward user to view shipping methods screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_shipping_methods.php');
}
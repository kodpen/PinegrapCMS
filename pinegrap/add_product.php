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
 
include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {
    if(db('SELECT product_image_code_template FROM config') != ''){
        $code = db('SELECT product_image_code_template FROM config');
    }
    // if tax is on, check tax checkbox
    if (ECOMMERCE_TAX == true) {
        $tax_checked = 'checked="checked"';
    }
    
    // if shipping is on, check shippable checkbox
    if (ECOMMERCE_SHIPPING == true) {
        $shippable_checked = 'checked="checked"';
    }else{
        $shippable_row_class = 'd-none';
    }
    // get all zones for zones selection
    $query = "SELECT id, name FROM zones ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        $output_allowed_zones .= '<option value="' . $row['id'] . '">' . h($row['name']) . '</option>';
    }
    
    // if the affiliate program is enabled, prepare affiliate program output
    if (AFFILIATE_PROGRAM == true) {
        $output_commissionable =
            '<div class="col-12 my-1">
                <div class="form-check form-switch">
                    <input value="1" id="commissionable" name="commissionable" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#commission_rate_limit_row" />
                    <label class="form-check-label" for="commissionable">' . lang('Commissionable') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="commission_rate_limit_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-2">
                                <label for="commission_rate_limit" class="form-label">' . lang('Commission Rate Limit') . '</label>
                                <div class="input-group">
                                    <input type="text" name="commission_rate_limit" id="commission_rate_limit" class="form-control" size="3" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                    <label for="commission_rate_limit"  class="input-group-text">%</label>
                                </div>
                                <div class="form-text text-end">(' . lang('leave blank for no limit') . ')</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    // determine if start row should be outputted
    $output_start_row = '';
    // if payment gateway is not ClearCommerce, then prepare to output start row
    if (ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce') {
        $output_start_row =
        '<div class="col-12 col-sm-6 col-lg-4 my-1">
            <label for="start" class="form-label">' . lang('Start (days)') . '</label>
            <div class="input-group">
                <input type="text" name="start" id="start" class="form-control" value="0" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
            </div>
            <div class="form-text text-end">' . lang('0 to start immediately') . '</div>
        </div>';
    }
    
    // determine if recurring profile disabled rows should be outputted
    $output_recurring_profile_disabled_rows = '';
    
    // if credit/debit card payment method is enabled and payment gateway is PayPal Payments Pro, then prepare to output recurring profile disabled rows
    if ((ECOMMERCE_CREDIT_DEBIT_CARD == true) && (ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro')) {
        $output_recurring_profile_disabled_rows =
            '<div class="col-12 my-3">
                <div class="form-check form-switch">
                    <input value="1" id="recurring_profile_disabled_perform_actions" name="recurring_profile_disabled_perform_actions" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#recurring_profile_disabled_perform_actions_row"/>
                    <label class="form-check-label" for="recurring_profile_disabled_perform_actions">' . lang('Perform action(s) if profile is disabled') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 w-100" id="recurring_profile_disabled_perform_actions_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body row">
                        <div class="alert alert-warning">' . lang('requires recurring payment job') . '</div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input value="1" id="recurring_profile_disabled_expire_membership" name="recurring_profile_disabled_expire_membership" class="form-check-input" type="checkbox" role="switch"/>
                                <label class="form-check-label" for="recurring_profile_disabled_expire_membership">' . lang('Expire Membership') . '</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input value="1" id="recurring_profile_disabled_revoke_private_access" name="recurring_profile_disabled_revoke_private_access" class="form-check-input" type="checkbox" role="switch"/>
                                <label class="form-check-label" for="recurring_profile_disabled_revoke_private_access">' . lang('Revoke Private Access') . '</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input value="1" id="recurring_profile_disabled_email" name="recurring_profile_disabled_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#recurring_profile_disabled_email_row"/>
                                <label class="form-check-label" for="recurring_profile_disabled_email">' . lang('Send E-mail to Customer') . '</label>
                            </div>
                        </div>
                        <div class="collapse popover  fade bs-popover-bottom p-0 w-100 border-start-0 border-end-0 border-bottom-0" id="recurring_profile_disabled_email_row">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row">
                                    <div class="col-12 col-sm-6 my-1">
                                        <label for="recurring_profile_disabled_email_subject" class="form-label">' . lang('Subject') . '</label>
                                        <input type="text" name="recurring_profile_disabled_email_subject" id="recurring_profile_disabled_email_subject" class="form-control mb-2" maxlength="255"/>
                                    </div>
                                    <div class="col-12 col-sm-6 my-1">
                                        <label for="recurring_profile_disabled_email_page_id" class="form-label">' . lang('Page') . '</label>
                                        <select name="recurring_profile_disabled_email_page_id" id="recurring_profile_disabled_email_page_id" class="form-select"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    // determine if Sage group ID row should be shown
    $output_sage_group_id_row = '';
    
    // if credit/debit card payment method is enabled and payment gateway is Sage, then output Sage group ID row
    if ((ECOMMERCE_CREDIT_DEBIT_CARD == TRUE) && (ECOMMERCE_PAYMENT_GATEWAY == 'Sage')) {
    
        $output_sage_group_id_row =
            '<div class="col-12 col-sm-6 col-md-4 my-2">
                <label for="sage_group_id" class="form-label">Sage Group ID</label>
                    <input type="text" name="sage_group_id" id="sage_group_id" class="form-control" size="7" maxlength="9"/>
            </div>';
    }
    $output_attributes = '';
	
    // Get product attributes.
    $attributes = db_items(
        "SELECT
            id,
            name
        FROM product_attributes
        ORDER BY name", 'id');

    // If there are attributes, then get options and output attribute area.
    if ($attributes) {
        $attribute_options = db_items(
            "SELECT
                id,
                product_attribute_id,
                label
            FROM product_attribute_options
            ORDER BY
                product_attribute_id,
                sort_order");

        // Loop through the options in order to add them to the attributes array.
        foreach ($attribute_options as $attribute_option) {
            $attributes[$attribute_option['product_attribute_id']]['options'][] = $attribute_option;
        }
        
        // We use array_values() below so that the array is treated as an array
        // and not an object in js, in order to maintain order of the attributes.
        $output_attributes =
            '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Attribute Options') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mt-1 mb-2">
                            <label class="form-label" >' . lang('Product Attributes') . '</label>
                            <div class="attributes">
                                <div class="attribute_list row" ></div>
                                <button type="button" class="add_attribute btn btn-primary mt-2"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Attribute') . '</button>
                            </div>
                            <script>
                                init_product_attributes({
                                    attributes: ' . encode_json(array_values($attributes)) . ',
                                    labels:{
                                        "Select Attribute":"' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Attribute')) )) . '",
                                        "Select Option":"' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Option')) )) . '",
                                        "Move Up":"' . lang('Move Up') . '",
                                        "Move Down":"' . lang('Move Down') . '",
                                        "Remove":"' . lang('Remove') . '"
                                    }
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>';
    }

    $output_groups = '';
	
    // Get product groups.
    $groups = db_items(
        "SELECT
            id,
            name
        FROM product_groups
        ORDER BY name", 'id');

    // If there are groups, then get options and output group area.
    if ($groups) {
        $group_options_html = get_product_group_options(0, 0, 0, 0, array(), TRUE);
        $output_groups =
            '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Catalog Options') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mt-1 mb-2">
                            <label class="form-label" >' . lang('Include product in to selected groups') . '</label>
                            <div class="groups">
                                <div class="group_list row"></div>
                                <button type="button" class="add_group btn btn-primary mt-2"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Group') . '</button>
                            </div>
                            <script>
                                init_product_groups({
                                    group_options: ' . json_encode($group_options_html) . ',
                                    labels:{
                                        "Select Group":"' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Group')) )) . '",
                                        "Remove":"' . lang('Remove') . '"
                                    }
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>';
    }


    $output_custom_product_field_rows = '';

    // If there is at least one active custom product field, then output area for that.
    if (
        (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '')
        || (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '')
        || (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '')
        || (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '')
    ) {  
        $output_custom_product_field_rows .=
            '<div class="col-12">
                <h6 class="text-muted">' . lang('Custom Product Fields') . '</h6>
            </div>
            <div class="col-12 col-sm-6 col-md-4 my-2">';

        // If the first custom product field is active, then output row for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
            $output_custom_product_field_rows .=
                '<label for="custom_field_1" class="form-label">' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL) . '</label>
                <input class="form-control" type="text" id="custom_field_1" name="custom_field_1" maxlength="255" />';
        }

        // If the second custom product field is active, then output row for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_product_field_rows .=
            '<label for="custom_field_2" class="form-label">' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL) . '</label>
            <input class="form-control" type="text" id="custom_field_2" name="custom_field_2" maxlength="255" />';
        }

        // If the third custom product field is active, then output row for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_product_field_rows .=
            '<label for="custom_field_3" class="form-label">' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL) . '</label>
            <input class="form-control" type="text" id="custom_field_3" name="custom_field_3" maxlength="255" />';
        }

        // If the fourth custom product field is active, then output row for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_product_field_rows .=
            '<label for="custom_field_4" class="form-label">' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL) . '</label>
            <input class="form-control" type="text" id="custom_field_4" name="custom_field_4" maxlength="255" />';
        }
        $output_custom_product_field_rows .='</div>';
    }

    print
    pg_page_shell([
        'title'=> lang('Create Product'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Product'),
        'cancel'=>array('enable'=>'true','url'=>'view_products.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'), array('label' => lang('Create Product'))),
        ]) . '
            ' . get_wysiwyg_editor_code(array('order_receipt_message', 'full_description', 'details', 'out_of_stock_message')) . '
        <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create products to offer merchandise, memberships, downloads, recurring services, donations, or account payments.') . '" title="' . lang('Create Product') . '">[' . lang('Product Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_product.php" method="post" class="product_form">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="name" class="form-label">' . lang('Product ID / SKU') . '</label>
                                            <input type="text" name="name" id="name" class="form-control"  />
                                        </div>
                                        <div class="col-12 col-sm-8 my-2">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            <input type="text" name="short_description" placeholder="' . lang('Product Name') . '" id="short_description" class="form-control add-header-content-updater" />
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-4 my-2">
                                            <label for="price" class="form-label">' . lang('Unit Price') . '</label>
                                            <div class="input-group">
                                                <input value="0" type="text" name="price" id="price" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                <label class="input-group-text" for="price">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-lg-6 my-2">
                                            <div class="row">
                                                <div class="col-12 col-sm-12 col-lg-8 offset-lg-8">
                                                    <label for="selection_type" class="form-label">' . lang('Selection Type') . '</label>
                                                    <select name="selection_type" id="selection_type" class="form-select"  >' .  select_selection_type() . '</select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="default_quantity" class="form-label">' . lang('Default Quantity') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="1" type="text" name="default_quantity" id="default_quantity" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="minimum_quantity" class="form-label">' . lang('Min. Quantity') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="minimum_quantity" id="minimum_quantity" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="maximum_quantity" class="form-label">' . lang('Max. Quantity') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="maximum_quantity" id="maximum_quantity" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>       
                            </div> 
                        </div>
                        <div class="col-12">          
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Product Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" checked="checked" />
                                                <label class="form-check-label" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="taxable" name="taxable" value="1" ' . $tax_checked . ' />
                                                <label class="form-check-label" for="taxable">' . lang('Taxable') . '</label>
                                            </div>
                                        </div>
                                        ' . $output_commissionable . '
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1" id="recurring" name="recurring" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#recurring_schedule_editable_by_customer_row" />
                                                <label class="form-check-label" for="recurring">' . lang('Recurring Payment') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="recurring_schedule_editable_by_customer_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1" id="recurring_schedule_editable_by_customer" name="recurring_schedule_editable_by_customer" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#recurring_schedule_editable_by_customer_message"/>
                                                                <label class="form-check-label" for="recurring_schedule_editable_by_customer">' . lang('Allow customer to set schedule') . '</label>
                                                            </div>
                                                            <div class="collapse alert alert-primary" id="recurring_schedule_editable_by_customer_message">' . lang('You may select default values for the schedule below') . '</div>
                                                        </div>
                                                        ' . $output_start_row . '
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="number_of_payments" class="form-label">' . lang('Number of Payments') . '</label>
                                                                <input type="text" name="number_of_payments" id="number_of_payments" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: left;" />
                                                            <div class="form-text text-end">' . get_number_of_payments_message() . '</div>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="payment_period" class="form-label">' . lang('Payment Period') . '</label>
                                                            <select name="payment_period" id="payment_period" class="form-select">' .  select_payment_period('Monthly') . '</select>
                                                        </div>
                                                        ' . $output_recurring_profile_disabled_rows . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"  name="inventory" id="inventory" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#collapse_inventory">
                                                <label class="form-check-label" for="inventory">' . lang('Track Inventory') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="collapse_inventory">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label for="inventory_quantity" class="form-label ">' . lang('Inventory Quantity') . '</label>
                                                            <div class="input-group number-controls">
                                                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                                <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="inventory_quantity" id="inventory_quantity" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-3">
                                                            <div class="form-check form-switch">
                                                              <input value="1" name="backorder" id="backorder" class="form-check-input" type="checkbox" role="switch" />
                                                              <label class="form-check-label" for="backorder">' . lang('Accept Backorders') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label for="out_of_stock_message" class="form-label">' . lang('Out of Stock Message') . '</label>
                                                            <textarea id="out_of_stock_message" name="out_of_stock_message">' . h('<p>' . lang('Sorry, this item is not currently available.') . '</p>') . '</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch ' . $shippable_row_class . '">
                                                <input value="1"' . $shippable_checked . ' id="shippable" name="shippable" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#shippable_row" />
                                                <label class="form-check-label" for="shippable">' . lang('Shippable') . '</label>
                                            </div>
                                            <div class="collapse popover   fade bs-popover-bottom p-0 mb-2" id="shippable_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 justify-content-center d-flex flex-wrap my-1">
                                                            
                                                            <div class="form-check form-switch">
                                                              <input value="1" name="convert_to_metric_system" id="convert_to_metric_system" class="form-check-input" type="checkbox" role="switch" data-bs-target="#weight,#length,#width,#height" />
                                                              <label class="form-label" for="convert_to_metric_system">' . lang('Convert to metric system') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12  col-lg-4 my-1">
                                                            <label for="weight" class="form-label">' . lang('Weight') . '</label>
                                                            <div class="input-group">
                                                                <input value="0" type="text" name="weight" id="weight" class="form-control" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsOptional="true" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label class="input-group-text unit" for="weight">lbs</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-sm-6  col-lg-4 my-1">
                                                            <label for="primary_weight_points" class="form-label">' . lang('Primary Weight Points') . '</label>
                                                            <input value="0" type="text" name="primary_weight_points" id="primary_weight_points" class="form-control" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="1" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                        </div>
                                                        <div class="col-12 col-sm-6  col-lg-4 my-1">
                                                            <label for="secondary_weight_points" class="form-label">' . lang('Secondary Weight Points') . '</label>
                                                            <input value="0" type="text" name="secondary_weight_points" id="secondary_weight_points" class="form-control" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="1" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                        </div>
                                                        <div class="col-12 my-3">
                                                            
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <h6 class="text-muted">' . lang('Dimentions') . '</h6>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                    <label class="form-label" for="length">' . lang('Length') . '</label>
                                                                    <div class="input-group my-1">
                                                                        <input value="0" type="text" name="length" id="length" class="form-control"  inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsOptional="true"  data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                        <label class="input-group-text unit" for="length">inc</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                    <label class="form-label" for="width">' . lang('Width') . '</label>
                                                                    <div class="input-group my-1">
                                                                        <input value="0" type="text" name="width" id="width" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsOptional="true"  data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                        <label class="input-group-text unit" for="width">inc</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                    <label class="form-label" for="height">' . lang('Height') . '</label>
                                                                    <div class="input-group my-1">
                                                                        <input value="0" type="text" name="height" id="height" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsOptional="true"  data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                        <label class="input-group-text unit" for="height">inc</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label for="preparation_time" class="form-label">' . lang('Preparation Time') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="preparation_time" id="preparation_time" class="form-control" value="" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-2">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="container_required" name="container_required" value="1"/>
                                                                <label class="form-check-label" for="container_required">' . lang('Container Required') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-2">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input collapse-switcher" type="checkbox" id="free_shipping" name="free_shipping" value="1" data-bs-target="#free_shipping_row" />
                                                                <label class="form-check-label" for="free_shipping">' . lang('Free Shipping') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 show-reverse" id="free_shipping_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-2">
                                                                        <label for="extra_shipping_cost" class="form-label">' . lang('Extra Shipping Cost') . '</label>
                                                                        <div class="input-group">
                                                                            <input value="0" type="text" name="extra_shipping_cost" id="extra_shipping_cost" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                            <label class="input-group-text" for="extra_shipping_cost">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
                                                            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $output_allowed_zones . '</select>
                                                        </div>
                                                    </div>
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
                                    ' . lang('Image Options') . ' 
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-3">
                                            <div id="software_image_picker_container" ondblclick="software_image_picker({initialize:true});" class="user-select-none sortable-list img-list bg-body-tertiary rounded p-2 row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4"></div>
                                            <button type="button" class="btn btn-primary my-3 me-2" onclick="software_image_picker({initialize:true});" ><span class="bi bi-plus-circle me-2"></span>' . lang('Add Image') . '</button>
                                            <button type="button" class="btn " data-bs-toggle="modal" data-bs-target="#image_code"><span class="material-icons me-2">code</span>' . lang('Code') . '</button>

                                            <div class="modal fade" id="image_code" tabindex="-1" aria-labelledby="image_code" aria-hidden="true">
                                                <div class="modal-dialog modal-lg ">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">' . lang('Code') . '</h5>
                                                            <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body "> 
                                                            <div class="row">
                                                                <div class="col-12 my-2">
                                                                <div class="alert alert-primary">' . lang('Tags') . ':<span title="' . lang('Loop start') . '">^^image_loop_start^^</span>, <span title="' . lang('Short Description') . '">^^image_alt^^</span>, <span title="' . lang('Image Url') . '">^^image_url^^</span>, <span title="' . lang('Loop End') . '">^^image_loop_end^^</span></div>
                                                                </div>
                                                                <div class="col-12 my-2">
                                                                    <textarea id="code" name="code">' . $code . '</textarea>
                                                                    ' . get_codemirror_includes() . '
                                                                    ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <script>
                                                $(document).ready(function() {
                                                    $(".sortable-list").sortable({
                                                        items: "> div:not(.add_new_item)",
                                                        placeholder: "col",
                                                        handle: ".card .card-body",
                                                        revert: "100",
                                                        cursorAt: { left: 1 },
                                                        animation: 150,
                                                        forcePlaceholderSize: false,
                                                        forceHelperSize: true,
                                                        swapThreshold: 1,
                                                        tolerance: "pointer",
                                                        zIndex: 9999,
                                                        cursor: "move",
                                                        cancel: ".no-drag"
                                                    });
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 ">
                        ' . $output_attributes . '
                        </div>
                        <div class="col-12 col-md-6 ">
                        ' . $output_groups . '
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Detail Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            <textarea id="full_description" name="full_description" ></textarea>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="details" class="form-label">' . lang('Details') . '</label>
                                            <textarea id="details" name="details" ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    '. lang('Checkout Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_sage_group_id_row . '
                                        <div class="col-12 col-sm-6 col-md-8 my-2">
                                            <label class="form-label" for="required_product">'. lang('Requires Product') . '</label>
                                            <select class="form-select" id="required_product" name="required_product"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Product')) )) . '-</option>' .  select_product() . '</select>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input value="1" id="product_form" name="product_form" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#product_form_row" />
                                                <input type="hidden" id="current_form_state" name="current_form_state" value="" >
                                                <script>
                                                    $("input.collapse-switcher#product_form").on("click", function() {
                                                        if(this.checked == true){
                                                            if($("#original_form_state").val() == 0 || $("#original_form_state").length < 1){
                                                                $("#create_button").value= "Create & Continue";
                                                                $("#create_button .btn-text").text("' . lang('Create & Continue') . '");
                                                                $("#current_form_state").val(1);
                                                            }
                                                        }else{
                                                            $("#create_button").val("Create");
                                                            $("#create_button .btn-text").text("' . lang('Create') . '");
                                                            $("#current_form_state").val(0);
                                                        } 
                                                    });
                                                </script>
                                                <label class="form-check-label" for="product_form">'. lang('Enable Product Form') . '</label>
                                            </div>
                                            <div class="collapse popover   fade bs-popover-bottom p-0 mb-2" id="product_form_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="alert alert-primary">' . lang(array('string'=>'when ready, click "{var:1}" at the bottom of this screen to create the Product Form.','vars'=>array(lang('Create & Continue')) )) . '</div>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-md-8 my-1">
                                                            <label class="form-label" for="form_name">'. lang('Form Title for Display') . '</label>
                                                            <input type="text" id="form_name" name="form_name" class="form-control" maxlength="100" value="" >
                                                        </div>

                                                        <div class="col-12 col-sm-6 col-md-4 my-1">
                                                            <label class="form-label" for="form_label_column_width">'. lang('Label Column Width') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" id="form_label_column_width" name="form_label_column_width" class="form-control" value="" size="3" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;">
                                                                <label class="input-group-text" for="form_label_column_width">%</label>
                                                            </div>
                                                            <div class="form-text text-end">'. lang('leave blank for auto') . '</div>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-md-4 my-1">
                                                            <label class="form-label" for="">'. lang('Quantity Type') . '</label>
                                                            <div class="form-check">
                                                                <input value="One Form per Quantity" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_quantity" name="form_quantity_type" checked>
                                                                <label class="form-check-label" for="form_quantity_type_one_form_per_quantity">'. lang('One form per quantity') . '</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input value="One Form per Product" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_product" name="form_quantity_type">
                                                                <label class="form-check-label" for="form_quantity_type_one_form_per_product">'. lang('One form per product') . '</label>
                                                            </div>
                                                        </div>
                                                    </div>
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
                                    ' . lang('Order Complete Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label class="form-label" for="order_receipt_message">' . lang('Order Receipt Page Message') . '</label>
                                            <textarea id="order_receipt_message" name="order_receipt_message"></textarea>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4 my-1">
                                            <label class="form-label" for="order_receipt_bcc_email_address">' . lang('Order Receipt BCC E-mail Address') . '</label>
                                            <input type="text" class="form-control text-end" id="order_receipt_bcc_email_address" name="order_receipt_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4 my-1">
                                            <label class="form-label" for="email_page">' . lang('E-mail Additional Page to Customer') . '</label>
                                            <select class="form-select" id="email_page" name="email_page"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4 my-1">
                                            <label class="form-label" for="email_bcc">' . lang('BCC E-mail Address') . '</label>
                                            <input type="text" class="form-control text-end" id="email_bcc" name="email_bcc" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4 my-1">
                                            <label class="form-label" for="contact_group_id">' . lang('Add to Contact Group') . '</label>
                                            <select class="form-select" id="contact_group_id" name="contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group(0, $user) . '</select>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-4 my-1">
                                            <label for="membership_renewal" class="form-label">' . lang('Add Days to Customer\'s Membership') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="membership_renewal" id="membership_renewal" class="form-control" value="" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                            </div>
                                            <div class="text-end form-text">' . lang('0 for none') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input value="1" id="grant_private_access" name="grant_private_access" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#grant_private_access_row" />
                                                <label class="form-check-label" for="grant_private_access">' . lang('Grant Private Access to Customer') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="grant_private_access_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label class="form-label" for="private_folder">' . lang('Set "View" Access to Folder') . '</label>
                                                            <select class="form-select" id="private_folder" name="private_folder"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Folder')) )) . '-</option>' .  select_folder(0, 0, 0, 0, array(), array(), 'private') . '</select>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label for="private_days" class="form-label">' . lang('Length') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="private_days" id="private_days" class="form-control" value="" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                            </div>
                                                            <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label class="form-label" for="send_to_page">' . lang('Set Customer\'s Start Page to') . '</label>
                                                            <select class="form-select" id="send_to_page" name="send_to_page"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 my-1">
                                            <label for="reward_points" class="form-label">' . lang('Reward Points') . '</label>
                                            <input type="text" name="reward_points" id="reward_points" class="form-control" value="" size="5" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                        </div>
                                        <div class="col-12 mt-3 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1" id="gift_card" name="gift_card" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#gift_card_row" />
                                                <label class="form-check-label" for="gift_card">' . lang('Email Gift Card') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="gift_card_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-sm-6 col-md-8 my-1">
                                                            <label class="form-label" for="gift_card_email_subject">' . lang('Subject') . '</label>
                                                            <input type="text" id="gift_card_email_subject" name="gift_card_email_subject" class="form-control" maxlength="100" value="" >
                                                        </div> 
                                                        <div class="col-12 my-1">
                                                            <div class="col-12">
                                                                <label class="form-label">' . lang('Format') . '</label>
                                                            </div>
                                                            <div class="form-check  form-check-inline">
                                                                <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_plain_text" name="gift_card_email_format"  checked="checked" value="plain_text" data-bs-target="#gift_card_email_format_plain_text_row" />
                                                                <label for="gift_card_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                                            </div>
                                                            <div class="form-check  form-check-inline">
                                                                <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_html" name="gift_card_email_format" value="html"  data-bs-target="#gift_card_email_format_html_row"/>
                                                                <label for="gift_card_email_format_html">' . lang('HTML') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="gift_card_email_format_plain_text_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                          <label for="gift_card_email_body" class="form-label">' . lang('Body') . '</label>
                                                                          <textarea class="form-control" id="gift_card_email_body" name="gift_card_email_body" rows="3"></textarea>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="gift_card_email_format_html_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-sm-8 my-1">
                                                                            <label class="form-label" for="gift_card_email_page_id">' . lang('Page') . '</label>
                                                                            <select class="form-select" id="gift_card_email_page_id" name="gift_card_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1" id="submit_form" name="submit_form" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#submit_form_row" />
                                                <label class="form-check-label" for="submit_form">' . lang('Create/Update Submitted Form') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="submit_form_custom_form_page_id">' . lang('Custom Form') . '</label>
                                                            <select class="form-select collapse-if-selected" id="submit_form_custom_form_page_id" name="submit_form_custom_form_page_id" onchange="product_submit_form_update_custom_form_fields()" data-bs-target="#submit_form_custom_form_page_row"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Form')) )) . '-</option>' .  select_page('', 'custom form') . '</select>
                                                            <script>product_submit_form_update_custom_form_fields();</script>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="submit_form_custom_form_page_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1" id="submit_form_create" name="submit_form_create" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#submit_form_create_row" />
                                                                                <label class="form-check-label" for="submit_form_create">' . lang('Create Submitted Form') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_create_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1">
                                                                                            <div class="alert alert-primary">' . lang(array('string'=>'Please configure the fields below that should be set when a Submitted Form is {var:1}.','vars'=>array( lang('created') ) )) . '</div>
                                                                                            <div id="submit_form_create_field"></div>
                                                                                            <button type="button" onclick="product_submit_form_add_field({action: \'create\'})" class="btn btn-sm btn-primary my-3"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Field') . '</button>
                                                                                            <input type="hidden" id="last_submit_form_create_field_number" name="last_submit_form_create_field_number" value="0" />
                                                                                            <script>
                                                                                                var last_submit_form_field_number = [];                            
                                                                                                last_submit_form_field_number["create"] = 0;
                                                                                            </script>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1" id="submit_form_update" name="submit_form_update" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#submit_form_update_row" />
                                                                                <label class="form-check-label" for="submit_form_update">' . lang('Update Submitted Form') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_update_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1">
                                                                                            <div class="alert alert-primary">' . lang(array('string'=>'Please configure the fields below that should be set when a Submitted Form is {var:1}.','vars'=>array( lang('updated') ) )) . '</div>
                                                                                            <div id="submit_form_update_field"></div>
                                                                                            <button type="button" onclick="product_submit_form_add_field({action: \'update\'})" class="btn btn-sm btn-primary my-3"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Field') . '</button>
                                                                                            <input type="hidden" id="last_submit_form_update_field_number" name="last_submit_form_update_field_number" value="0" />
                                                                                            <script>
                                                                                                last_submit_form_field_number["update"] = 0;
                                                                                            </script>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="alert alert-primary">' . lang('Please specify which Submitted Form should be updated.') . '</div>
                                                                            <div class="input-group w-100">
                                                                                <span class="input-group-text">
                                                                                    <label class="form-label" for="submit_form_update_where_field">' . lang('Where:') . '</label>
                                                                                    <select class="form-select" id="submit_form_update_where_field" name="submit_form_update_where_field"></select>
                                                                                </span>
                                                                                <span class="input-group-text">
                                                                                    <label class="form-label" for="submit_form_update_where_value">' . lang('is equal to:') . '</label>
                                                                                    <input class="form-control" type="text" id="submit_form_update_where_value" name="submit_form_update_where_value" value="' . h($submit_form_update_where_value) . '" maxlength="255">
                                                                                </span>
                                                                                <script>init_product_submit_form_update_where("' . escape_javascript($submit_form_update_where_field) . '")</script>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1" id="add_comment" name="add_comment" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#add_comment_row" />
                                                <label class="form-check-label" for="add_comment">' . lang('Add Comment') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="add_comment_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label class="form-label" for="add_comment_page_id">' . lang('Page') . '</label>
                                                            <select class="form-select" id="add_comment_page_id" name="add_comment_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label class="form-label" for="add_comment_name">' . lang('Added by') . '</label>
                                                            <input value="" class="form-control"  id="add_comment_name" name="add_comment_name"/>
                                                        </div>
                                                        <div class="col-12  my-1">
                                                            <label class="form-label" for="add_comment_message">' . lang('Comment') . '</label>
                                                            <textarea class="form-control"  id="add_comment_message" name="add_comment_message"></textarea>
                                                        </div>
                                                        <div class="col-12  my-3">
                                                            <div class="form-check form-switch">
                                                                <input value="1" id="add_comment_only_for_submit_form_update" name="add_comment_only_for_submit_form_update" class="form-check-input" type="checkbox" role="switch" />
                                                                <label class="form-check-label" for="add_comment_only_for_submit_form_update">' . lang('Only add Comment if Submitted Form was updated') . '</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label class="form-label">' . lang('Form/Comment Quantity Type') . '</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="submit_form_quantity_type" id="submit_form_quantity_type_quantity" value="One Form per Quantity" checked>
                                                <label class="form-check-label" for="submit_form_quantity_type_quantity">' . lang('One form/comment per quantity') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="submit_form_quantity_type" id="submit_form_quantity_type_product" value="One Form per Product">
                                                <label class="form-check-label" for="submit_form_quantity_type_product">' . lang('One form/comment per product') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Site Search & Tag Cloud') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-3 mb-2">
                                            <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                            <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255"/>
                                            <script>
                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                    tagin( document.querySelector("#keywords") );
                                                }
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Helpful Contents') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_custom_product_field_rows . '
                                        <div class="col-12 my-3">
                                            <label for="notes" class="form-label">' . lang('Notes') . '</label>
                                            <textarea id="notes" name="notes" class="form-control"></textarea>
                                            <div class="form-text text-end">' . lang('Product Notes for Order Exporting') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 ">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('SEO') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                            <div class="input-group ">
                                                <label for="address_name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . 'example-catalog/{' . lang('Catalog Name') . '}">public</label>
                                                <input type="text" name="address_name" id="address_name" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" ></textarea>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_keywords" class="form-label">' . lang('Web Browser Keywords') . '</label>
                                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255"/>
                                            <script>
                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                    tagin(document.querySelector("#meta_keywords"));
                                                }
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 ">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('RSS Feed') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="google_product_category" class="form-label popover-click" title="' . lang('Google Product Category') . '" data-bs-content="' . lang('Google product category <a href=\'https://kodpen.com/blog/evrimi-i-google-r-n-kategori-tablosu\' target=\'_blank\'>[google_product_category]</a> All products are automatically assigned a product category from Google’s continuously evolving product taxonomy. Providing high-quality, on-topic titles and descriptions, as well as accurate pricing, brand, and GTIN information will help ensure your products are correctly categorized.The Google product category [google_product_category] attribute is optional, and can be used to override Google’s automatic categorization in specific cases.') . '">
                                                ' . lang('GPC') . ' (' . lang('what is this?') . ')
                                            </label>
                                            <input type="text" name="google_product_category" id="google_product_category" size="100" class="form-control" maxlength="255" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="gtin" class="form-label">' . lang('GTIN') . '</label>
                                            <input type="text" name="gtin" id="gtin" size="30" class="form-control" maxlength="50" placeholder="' . lang('e.g. UPC') . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="brand" class="form-label">' . lang('Brand') . '</label>
                                            <input type="text" name="brand" id="brand" size="30" class="form-control" maxlength="100" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="mpn" class="form-label">' . lang('MPN') . '</label>
                                            <input type="text" name="mpn" id="mpn" size="30" class="form-control" maxlength="50" placeholder="' . lang('i.e. manufacturer product number') . '" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
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

    //we convert metric system to inch and pound. we store like this.
    $weight = 0;
    $length = 0;
    $width = 0;
    $height = 0;
    if ($_POST['convert_to_metric_system'] == 1){
        $weight = round($_POST['weight'] * 2.20462262185,2);
        $length = round($_POST['length'] * 0.39370078740158,2);
        $width = round($_POST['width'] * 0.39370078740158,2);
        $height = round($_POST['height'] * 0.39370078740158,2);
    }else{
        $weight = $_POST['weight'];
        $length = $_POST['length'];
        $width = $_POST['width'];
        $height = $_POST['height'];
    }
    
    // if user selected a contact group and user does not have access to contact group
    if ($_POST['contact_group_id'] && (validate_contact_group_access($user, $_POST['contact_group_id']) == false)) { 
        log_activity( lang('access denied because user does not have access to contact group that user selected for product') , $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
   
    }
    // remove commas and spaces from price
    $price = str_replace(',', '', $_POST['price']);
    $price = str_replace(' ', '',$price); 
    // convert price from dollars to cents
    $price = $price * 100;
    
    // remove commas from extra shipping cost
    $extra_shipping_cost = str_replace(',', '', $_POST['extra_shipping_cost']);
    
    // convert extra shipping cost from dollars to cents
    $extra_shipping_cost = $extra_shipping_cost * 100;

    $_POST['order_receipt_bcc_email_address'] = trim($_POST['order_receipt_bcc_email_address']);
    
    // if a order receipt bcc email address was supplied, validate the e-mail address
    if ($_POST['order_receipt_bcc_email_address']) {
        if (validate_email_address($_POST['order_receipt_bcc_email_address']) == FALSE) {
            output_error(lang('The order receipt bcc e-mail address is invalid. <a href="javascript:history.go(-1);">Go back</a>.'));
        }
    }

    $_POST['email_bcc'] = trim($_POST['email_bcc']);
    
    // if a bcc e-mail address was supplied, validate the e-mail address
    if ($_POST['email_bcc']) {
        if (validate_email_address($_POST['email_bcc']) == FALSE) {
            output_error(lang('The additional page bcc e-mail address is invalid. <a href="javascript:history.go(-1);">Go back</a>.'));
        }
    }
    
    // if the affiliate program is enabled, prepare affiliate program SQL
    if (AFFILIATE_PROGRAM == true) {
        $sql_commissionable_1 =
            "commissionable,
            commission_rate_limit,";
        
        $sql_commissionable_2 =
            "'" . escape($_POST['commissionable']) . "',
            '" . escape($_POST['commission_rate_limit']) . "',";
    }

    // determine if recurring profile disabled fields should be updated
    $sql_recurring_profile_disabled_1 = '';
    $sql_recurring_profile_disabled_2 = '';
    
    // if credit/debit card payment method is enabled and payment gateway is PayPal Payments Pro, then prepare to update recurring profile disabled fields
    if ((ECOMMERCE_CREDIT_DEBIT_CARD == true) && (ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro')) {
        $sql_recurring_profile_disabled_1 =
            "recurring_profile_disabled_perform_actions,
            recurring_profile_disabled_expire_membership,
            recurring_profile_disabled_revoke_private_access,
            recurring_profile_disabled_email,
            recurring_profile_disabled_email_subject,
            recurring_profile_disabled_email_page_id,";
        
        $sql_recurring_profile_disabled_2 =
            "'" . escape($_POST['recurring_profile_disabled_perform_actions']) . "',
            '" . escape($_POST['recurring_profile_disabled_expire_membership']) . "',
            '" . escape($_POST['recurring_profile_disabled_revoke_private_access']) . "',
            '" . escape($_POST['recurring_profile_disabled_email']) . "',
            '" . escape($_POST['recurring_profile_disabled_email_subject']) . "',
            '" . escape($_POST['recurring_profile_disabled_email_page_id']) . "',";
    }
    
    // determine if Sage group ID field should be updated
    $sql_sage_group_id_1 = '';
    $sql_sage_group_id_2 = '';
    
    // if credit/debit card payment method is enabled and payment gateway is Sage, then update Sage group ID field
    if ((ECOMMERCE_CREDIT_DEBIT_CARD == TRUE) && (ECOMMERCE_PAYMENT_GATEWAY == 'Sage')) {
        $sql_sage_group_id_1 = "sage_group_id,";
        $sql_sage_group_id_2 = "'" . escape($_POST['sage_group_id']) . "',";
    }


    $selected_images = array();
    foreach ($_POST['selected_images'] as $selected_image ) {
        $selected_images[] = $selected_image ;
    }
    
    $selected_count = 0;
    foreach ($selected_images as $value) {
        $selected_count++;
    }
    if($selected_count >= 1){
        $selected_cover_image = reset($selected_images);
        array_shift($selected_images);
        if($selected_cover_image){
            $sql_imagename = 
            "'" . escape($selected_cover_image) . "',";
        }
    }else{
        $sql_imagename = 
        "'',";
    }
	// Insert db
    $query = "INSERT INTO products (
                name,
                enabled,
                short_description,
                full_description,
                details,
                code,
                keywords,
                image_name,
                price,
                taxable,
                contact_group_id,
                order_receipt_bcc_email_address,
                email_page,
                email_bcc,
                order_receipt_message,
                required_product,
                shippable,
                weight,
                primary_weight_points,
                secondary_weight_points,
                length,
                width,
                height,
                container_required,
                preparation_time,
                free_shipping,
                extra_shipping_cost,
                $sql_commissionable_1
                selection_type,
                default_quantity,
                minimum_quantity,
                maximum_quantity,
                title,
                meta_description,
                meta_keywords,
                inventory,
                inventory_quantity,
                backorder,
                out_of_stock_message,
                recurring,
                recurring_schedule_editable_by_customer,
                start,
                number_of_payments,
                payment_period,
                $sql_recurring_profile_disabled_1
                $sql_sage_group_id_1
                membership_renewal,
                grant_private_access,
                private_folder,
                private_days,
                send_to_page,
                reward_points,
                gift_card,
                gift_card_email_subject,
                gift_card_email_format,
                gift_card_email_body,
                gift_card_email_page_id,
                submit_form,
                submit_form_custom_form_page_id,
                submit_form_quantity_type,
                submit_form_create,
                submit_form_update,
                submit_form_update_where_field,
                submit_form_update_where_value,
                add_comment,
                add_comment_page_id,
                add_comment_message,
                add_comment_name,
                add_comment_only_for_submit_form_update,
                form,
                form_name,
                form_label_column_width,
                form_quantity_type,
                custom_field_1,
                custom_field_2,
                custom_field_3,
                custom_field_4,
                notes,
                google_product_category,
                gtin,
                brand,
                mpn,
                user,
                timestamp)
             VALUES (
                '" . escape($_POST['name']) . "',
                '" . escape($_POST['enabled']) . "',
                '" . escape($_POST['short_description']) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['full_description'])) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['details'])) . "',
                '" . escape($_POST['code']) . "',
                '" . escape($_POST['keywords']) . "',
               	$sql_imagename
                '" . escape($price) . "',
                '" . escape($_POST['taxable']) . "',
                '" . escape($_POST['contact_group_id']) . "',
                '" . escape($_POST['order_receipt_bcc_email_address']) . "',
                '" . escape($_POST['email_page']) . "',
                '" . escape($_POST['email_bcc']) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['order_receipt_message'])) . "',
                '" . escape($_POST['required_product']) . "',
                '" . escape($_POST['shippable']) . "',
                '" . escape($weight) . "',
                '" . escape($_POST['primary_weight_points']) . "',
                '" . escape($_POST['secondary_weight_points']) . "',
                '" . e($length) . "',
                '" . e($width) . "',
                '" . e($height) . "',
                '" . e($_POST['container_required']) . "',
                '" . escape($_POST['preparation_time']) . "',
                '" . escape($_POST['free_shipping']) . "',
                '" . escape($extra_shipping_cost) . "',
                $sql_commissionable_2
                '" . escape($_POST['selection_type']) . "',
                '" . escape($_POST['default_quantity']) . "',
                '" . escape($_POST['minimum_quantity']) . "',
                '" . escape($_POST['maximum_quantity']) . "',
                '" . escape($_POST['title']) . "',
                '" . escape($_POST['meta_description']) . "',
                '" . escape($_POST['meta_keywords']) . "',
                '" . escape($_POST['inventory']) . "',
                '" . escape($_POST['inventory_quantity']) . "',
                '" . escape($_POST['backorder']) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['out_of_stock_message'])) . "',
                '" . escape($_POST['recurring']) . "',
                '" . escape($_POST['recurring_schedule_editable_by_customer']) . "',
                '" . escape($_POST['start']) . "',
                '" . escape($_POST['number_of_payments']) . "',
                '" . escape($_POST['payment_period']) . "',
                $sql_recurring_profile_disabled_2
                $sql_sage_group_id_2
                '" . escape($_POST['membership_renewal']) . "',
                '" . escape($_POST['grant_private_access']) . "',
                '" . escape($_POST['private_folder']) . "',
                '" . escape($_POST['private_days']) . "',
                '" . escape($_POST['send_to_page']) . "',
                '" . escape($_POST['reward_points']) . "',
                '" . escape($_POST['gift_card']) . "',
                '" . escape($_POST['gift_card_email_subject']) . "',
                '" . escape($_POST['gift_card_email_format']) . "',
                '" . escape($_POST['gift_card_email_body']) . "',
                '" . escape($_POST['gift_card_email_page_id']) . "',
                '" . escape($_POST['submit_form']) . "',
                '" . escape($_POST['submit_form_custom_form_page_id']) . "',
                '" . e($_POST['submit_form_quantity_type']) . "',
                '" . escape($_POST['submit_form_create']) . "',
                '" . escape($_POST['submit_form_update']) . "',
                '" . e($_POST['submit_form_update_where_field']) . "',
                '" . e($_POST['submit_form_update_where_value']) . "',
                '" . escape($_POST['add_comment']) . "',
                '" . escape($_POST['add_comment_page_id']) . "',
                '" . escape($_POST['add_comment_message']) . "',
                '" . escape($_POST['add_comment_name']) . "',
                '" . escape($_POST['add_comment_only_for_submit_form_update']) . "',
                '" . escape($_POST['product_form']) . "',
                '" . escape($_POST['form_name']) . "',
                '" . escape($_POST['form_label_column_width']) . "',
                '" . escape($_POST['form_quantity_type']) . "',
                '" . escape($_POST['custom_field_1']) . "',
                '" . escape($_POST['custom_field_2']) . "',
                '" . escape($_POST['custom_field_3']) . "',
                '" . escape($_POST['custom_field_4']) . "',
                '" . escape($_POST['notes']) . "',
                '" . escape($_POST['google_product_category']) . "',
                '" . escape($_POST['gtin']) . "',
                '" . escape($_POST['brand']) . "',
                '" . escape($_POST['mpn']) . "',
                '" . $user['id'] . "',
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $product_id = mysqli_insert_id(db::$con);

    if($selected_count > 1){
        foreach ($selected_images as $value) {db("INSERT INTO products_images_xref (product,file_name)VALUES ('$product_id','" . escape($value) . "')");}
    }
  
    // If the user added attributes, then save them.
    if ($_POST['attributes']) {
        $attributes = decode_json($_POST['attributes']);

        $sort_order = 0;

        foreach ($attributes as $attribute) {
            $sort_order++;

            db(
                "INSERT INTO products_attributes_xref (
                    product_id,
                    attribute_id,
                    option_id,
                    sort_order)
                VALUES (
                    '$product_id',
                    '" . e($attribute['attribute_id']) . "',
                    '" . e($attribute['option_id']) . "',
                    '$sort_order')");
        }
    }
    

	// If the user added groups, then save them.
    if ($_POST['groups']) {
        $groups = decode_json($_POST['groups']);

        foreach ($groups as $group) {
            db(
                "INSERT INTO products_groups_xref (
                    product,
                    product_group)
                VALUES (
                    '$product_id',
                    '" . e($group['product_group']) . "'
                   )");
        }
    }
    


    // if the address name is NOT blank then use that value for the address name
    if ($_POST['address_name'] != '') {
        $address_name = $_POST['address_name'];
        
    // else if the short description is NOT blank then use that value
    } elseif ($_POST['short_description'] != '') {
        $address_name = $_POST['short_description'];
        
    // else if the name is NOT blank then use that value
    } elseif ($_POST['name'] != '') {
        $address_name = $_POST['name'];
        
    // else use the product id
    } else {
        $address_name = $product_id;
    }
    
    // prepare the address name for the database
    $address_name = prepare_catalog_item_address_name($address_name);
    
    // update the product's address name
    $query = "UPDATE products SET address_name = '" . escape($address_name) . "' WHERE id = '$product_id'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // load all allowed zones in array by exploding string that has allowed zone ids separated by commas
    $allowed_zones = $_POST['allowed_zones'];

    // foreach allowed zone insert row in products_zones_xref table
    foreach ($allowed_zones as $zone_id) {
        // if zone id is not blank, insert row
        if ($zone_id) {
            $query = "INSERT INTO products_zones_xref (product_id, zone_id) VALUES ($product_id, '" . escape($zone_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    // If a custom form was selected for submit form feature, then check if we need to add fields to database.
    if ($_POST['submit_form_custom_form_page_id']) {
        // Create array for storing submit form fields that have a value set, so if a user tried
        // to set multiple values for the same field, we don't add the extras.
        $added_submit_form_fields = array();
        
        // Loop through all submit form create fields in order to insert them into database.
        for ($field_number = 1; $field_number <= $_POST['last_submit_form_create_field_number']; $field_number++) {
            // If a field was selected, and the field has not already been added,
            // then continue to check if field should be added to database.
            if (
                ($_POST['submit_form_create_field_' . $field_number . '_form_field_id'])
                && (in_array($_POST['submit_form_create_field_' . $field_number . '_form_field_id'], $added_submit_form_fields) == false)
            ) {
                // Check to make sure that selected field actually exists on the custom form
                // in order to make sure that user is not trying to do something funny like trying to
                // set a field on a different form from the one they selected.
                $field_id = db_value(
                    "SELECT id
                    FROM form_fields
                    WHERE
                        (id = '" . escape($_POST['submit_form_create_field_' . $field_number . '_form_field_id']) . "')
                        AND (page_id = '" . escape($_POST['submit_form_custom_form_page_id']) . "')");

                // If a field was found for the selected field and selected custom form,
                // then continue to add field to database.
                if ($field_id) {
                    db(
                        "INSERT INTO product_submit_form_fields (
                            product_id,
                            action,
                            form_field_id,
                            value)
                        VALUES (
                            '" . escape($product_id) . "',
                            'create',
                            '" . escape($_POST['submit_form_create_field_' . $field_number . '_form_field_id']) . "',
                            '" . escape(trim($_POST['submit_form_create_field_' . $field_number . '_value'])) . "')");

                    // Remember that the field has been added so we don't add multiple records for the same field.
                    $added_submit_form_fields[] = $_POST['submit_form_create_field_' . $field_number . '_form_field_id'];
                }
            }
        }

        $added_submit_form_fields = array();
        
        // Loop through all submit form update fields in order to insert them into database.
        for ($field_number = 1; $field_number <= $_POST['last_submit_form_update_field_number']; $field_number++) {
            // If a field was selected, and the field has not already been added,
            // then continue to check if field should be added to database.
            if (
                ($_POST['submit_form_update_field_' . $field_number . '_form_field_id'])
                && (in_array($_POST['submit_form_update_field_' . $field_number . '_form_field_id'], $added_submit_form_fields) == false)
            ) {
                // Check to make sure that selected field actually exists on the custom form
                // in order to make sure that user is not trying to do something funny like trying to
                // set a field on a different form from the one they selected.
                $field_id = db_value(
                    "SELECT id
                    FROM form_fields
                    WHERE
                        (id = '" . escape($_POST['submit_form_update_field_' . $field_number . '_form_field_id']) . "')
                        AND (page_id = '" . escape($_POST['submit_form_custom_form_page_id']) . "')");

                // If a field was found for the selected field and selected custom form,
                // then continue to add field to database.
                if ($field_id) {
                    db(
                        "INSERT INTO product_submit_form_fields (
                            product_id,
                            action,
                            form_field_id,
                            value)
                        VALUES (
                            '" . escape($product_id) . "',
                            'update',
                            '" . escape($_POST['submit_form_update_field_' . $field_number . '_form_field_id']) . "',
                            '" . escape(trim($_POST['submit_form_update_field_' . $field_number . '_value'])) . "')");

                    // Remember that the field has been added so we don't add multiple records for the same field.
                    $added_submit_form_fields[] = $_POST['submit_form_update_field_' . $field_number . '_form_field_id'];
                }
            }
        }
    }

    // If submit form, and submit form update are enabled for this product,
    // then add product form field for the reference code and set that in the product property.
    if (
        $_POST['submit_form']
        && $_POST['submit_form_update']
        && ($_POST['submit_form_update_where_field'] == 'reference_code')
        &&
        (
            ($_POST['submit_form_update_where_value'] == '')
            || (mb_strpos($_POST['submit_form_update_where_value'], '^^') !== false)
        )
    ) {
        // Remove carets from where value, in order to get field name.
        $field_name = str_replace('^^', '', $_POST['submit_form_update_where_value']);

        if ($field_name == '') {
            $field_name = 'reference_code';
        }
        
        db(
            "INSERT INTO form_fields (
                form_type,
                product_id,
                name,
                label,
                type,
                required,
                user,
                timestamp)
            VALUES (
                'product',
                '$product_id',
                '" . e($field_name) . "',
                'Conversation Number:',
                'text box',
                '0',
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");

        $field_id = mysqli_insert_id(db::$con);

        // Enable product form and set reference code field in product.
        db(
            "UPDATE products
            SET
                form = '1',
                submit_form_update_where_value = '^^" . e($field_name) . "^^'
            WHERE id = '$product_id'");
    }

    //if code has ^^image_loop_start^^ and ^^image_url^^ and ^^image_loop_end^^. so we prepare to code to insert in config
    if( 
        (strpos($_POST['code'], '^^image_url^^') !== false)&&
        (strpos($_POST['code'], '^^image_loop_start^^') !== false)&&
        (strpos($_POST['code'], '^^image_loop_end^^') !== false)
    ){ 
        //get product_image_code_template from config
        $query = "SELECT product_image_code_template FROM config";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $config_code = $row['product_image_code_template'];
        //check if config_code is not equal to POSTED code
        if($config_code != $_POST['code']){
            //update config_code with new code
            $query = "UPDATE config SET product_image_code_template = '" . escape($_POST['code']) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }       
 
    log_activity( lang(array('string'=>'product ({var:1}) was created','vars'=>array($_POST['name']) )) , $_SESSION['sessionusername']);

    if ($_POST['current_form_state'] == 1) {
        // Use the insert id captured right after the INSERT. The previous code
        // re-queried by unescaped product name: an injection vector, and wrong
        // whenever two products share a name (it returned the older row).
        // forward user to the product form fields page
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?product_id=' . (int) $product_id);
    } else {
        // forward user to view products page
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_products.php');
    }


}
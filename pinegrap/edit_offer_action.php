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

// get all shipping methods for shipping discount feature
$query =
    "SELECT
        id,
        name,
        code
    FROM shipping_methods
    ORDER BY name ASC";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$shipping_methods = array();

// loop through all shipping methods in order to add them to array
while ($row = mysqli_fetch_assoc($result)) {
    $shipping_methods[] = $row;
}

if (!$_POST) {
    // get offer action data
    $query = "SELECT * FROM offer_actions WHERE id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $name = $row['name'];
    $type = $row['type'];
    $discount_order_amount = sprintf("%01.2lf", $row['discount_order_amount'] / 100);
    $discount_order_percentage = $row['discount_order_percentage'];
    $discount_product_product_id = $row['discount_product_product_id'];
    $discount_product_amount = sprintf("%01.2lf", $row['discount_product_amount'] / 100);
    $discount_product_percentage = $row['discount_product_percentage'];
    $add_product_product_id = $row['add_product_product_id'];
    $add_product_quantity = $row['add_product_quantity'];
    $add_product_discount_amount = sprintf("%01.2lf", $row['add_product_discount_amount'] / 100);
    $add_product_discount_percentage = $row['add_product_discount_percentage'];
    $discount_shipping_percentage = $row['discount_shipping_percentage'];

    
    $output_shipping_methods = '';
    
    // loop through all shipping methods in order to prepare output
    foreach ($shipping_methods as $shipping_method) {
        // check if shipping method is included in this offer action
        $query =
            "SELECT COUNT(*)
            FROM offer_actions_shipping_methods_xref
            WHERE
                (offer_action_id = '" . escape($_GET['id']) . "')
                AND (shipping_method_id = '" . $shipping_method['id'] . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // assume that this shipping method should not be checked until we find out otherwise
        $checked = '';
        
        // if this shipping method is included in this offer action,
        // then prepare to check shipping method check box
        if ($row[0] > 0) {
            $checked = ' checked="checked"';
        }
        
        $output_shipping_methods .= '<div class="form-check"><input type="checkbox" name="shipping_method_' . $shipping_method['id'] . '" id="shipping_method_' . $shipping_method['id'] . '" value="1" class="form-check-input multiselect-checkbox"' . $checked . '/><label class="form-check-label" for="shipping_method_' . $shipping_method['id'] . '">' . h($shipping_method['name']) . ' (' . h($shipping_method['code']) . ')</label></div>';
    }

    print
    pg_page_shell([
        'title'=> lang('Edit Offer Action'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Offer Action'),
        'cancel'=>array('enable'=>'true','url'=>'view_offer_actions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Offer Actions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_offer_actions.php'), array('label' => lang('Edit Offer Action'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit an offer action that can be assigned to any offer.') . '" title="' . lang('Edit Offer Action') . '">[' . h($name) . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_offer_action.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                            <div class="card my-4 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Offer Action Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <select name="type" id="type" class="form-select collapse-if-selected" data-bs-target="#offer_action_options_row" onchange="change_offer_action_type(this.options[this.selectedIndex].value)"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('type')) )) . '-</option>' . select_offer_action_type($type) . '</select></select>
                                            <script>
                                                $(document).ready(function() {
                                                    change_offer_action_type($("select#type option:selected").val());
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
                                ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-12 col-lg-6 col-xl-3 my-2">
                                            <label for="name" class="form-label">' . lang('Offer Action Name') . '</label>
                                            <input value="' . h($name) . '" type="text" name="name" id="name" class="form-control add-header-content-updater"/>
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse" id="offer_action_options_row">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Offer Action Options') . '
                                    </div>
                                    <div class="card-body">

                                        <div class="row collapse" id="discount_order">
                                            <div class="col-12 my-2">
                                                <h4 class="fw-bold text-muted">' . lang('Discount Order') . '</h4>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="discount_order_amount" class="form-label">' . lang('Amount') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $discount_order_amount . '" type="text" name="discount_order_amount" id="discount_order_amount" class="form-control" style="min-width:100px" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                </div>
                                            </div>
                                            <div class="col-12 my-2">
                                                <h4 class="text-muted">' . lang('or') . '</h4>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="discount_order_percentage" class="form-label">' . lang('Percentage') . '</label>
                                                <input value="' . $discount_order_percentage . '" type="text" name="discount_order_percentage" id="discount_order_percentage" class="form-control" style="min-width:100px" maxlength="8" inputmode="numeric" data-inputmask-alias="percentage"  data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                            </div>
                                        </div>

                                        <div class="row collapse" id="discount_product">
                                            <div class="col-12 my-2">
                                                <h4 class="fw-bold text-muted">' . lang('Discount Product') . '</h4>
                                            </div>
                                            <div class="col-12 my-2">
                                                <label for="discount_product_product_id" class="form-label">' . lang('Product') . '</label>
                                                <select name="discount_product_product_id" id="discount_product_product_id" class="select2 form-select w-auto" data-placeholder="' . lang('Click to select product(s)') . '"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product')) )) . '-</option>' . select_product($discount_product_product_id) . '</select>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="discount_product_amount" class="form-label">' . lang('Amount') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $discount_product_amount . '" type="text" name="discount_product_amount" id="discount_product_amount" class="form-control" style="min-width:100px" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                </div>
                                            </div>
                                            <div class="col-12 my-2">
                                                <h4 class="text-muted">' . lang('or') . '</h4>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="discount_product_percentage" class="form-label">' . lang('Percentage') . '</label>
                                                <input value="' . $discount_product_percentage . '" type="text" name="discount_product_percentage" id="discount_product_percentage" class="form-control" style="min-width:100px" maxlength="8" inputmode="numeric" data-inputmask-alias="percentage"  data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                            </div>
                                        </div>

                                        <div class="row collapse" id="add_product">
                                            <div class="col-12 my-2">
                                                <h4 class="fw-bold text-muted">' . lang('Add Product') . '</h4>
                                            </div>
                                            <div class="col-12 col-lg-8 my-2">
                                                <label for="add_product_product_id" class="form-label">' . lang('Product') . '</label>
                                                <select name="add_product_product_id" id="add_product_product_id" class="select2 form-select w-auto" data-placeholder="' . lang('Click to select product(s)') . '"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product')) )) . '-</option>' . select_product($add_product_product_id) . '</select>
                                            </div>
                                            <div class="col-12 col-lg-4 my-2">
                                                <label for="add_product_quantity" class="form-label ">' . lang('Quantity') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                    <input value="' . $add_product_quantity . '" class="form-control text-center border-start-0 border-end-0" type="text" name="add_product_quantity" id="add_product_quantity" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="add_product_discount_amount" class="form-label">' . lang('Discount Amount') . '</label>
                                                <div class="input-group">
                                                    <input value="' . $add_product_discount_amount . '" type="text" name="add_product_discount_amount" id="add_product_discount_amount" class="form-control" style="min-width:100px" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                    <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                </div>
                                            </div>
                                            <div class="col-12 my-2">
                                                <h4 class="text-muted">' . lang('or') . '</h4>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="add_product_discount_percentage" class="form-label">' . lang('Discount Percentage') . '</label>
                                                <input value="' . $add_product_discount_percentage . '" type="text" name="add_product_discount_percentage" id="add_product_discount_percentage" class="form-control" style="min-width:100px" maxlength="8" inputmode="numeric" data-inputmask-alias="percentage"  data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                            </div>
                                        </div>

                                        <div class="row collapse" id="discount_shipping">
                                            <div class="col-12 my-2">
                                                <h4 class="fw-bold text-muted">' . lang('Discount Shipping') . '</h4>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label for="discount_shipping_percentage" class="form-label">' . lang('Percentage') . '</label>
                                                <input value="' . $discount_shipping_percentage . '" type="text" name="discount_shipping_percentage" id="discount_shipping_percentage" class="form-control" style="min-width:100px" maxlength="8" inputmode="numeric" data-inputmask-alias="percentage"  data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                            </div>
                                            
                                            <div class="col-12 my-2">
                                                <h5>' . lang('Allowed Shipping Methods') . '</h5>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-check form-switch">
                                                            <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                                            <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                    ' . $output_shipping_methods . '
                                                    </div>
                                                </div>
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
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('offer action')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // delete related records in offer_actions_shipping_methods_xref
    // we do this regardless of whether we are deleting or updating this offer action
    $query = "DELETE FROM offer_actions_shipping_methods_xref WHERE offer_action_id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if offer action was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete offer action
        $query = "DELETE FROM offer_actions WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete records where this action was a selected action for offers
        $query = "DELETE FROM offers_offer_actions_xref WHERE offer_action_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'offer action ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    // else offer action was not selected for delete
    } else {

        // if the name is blank, then mark error and forward user back to previous screen
        if ($_POST['name'] == '') {
            output_error(lang(array('string'=>'{var:1} is required','vars'=>array(lang('offer action name')))) . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        $discount_order_amount = 0;
        if($_POST['discount_order_amount']){
            // remove commas and spaces from price
            $discount_order_amount = str_replace(',', '', $_POST['discount_order_amount']);
            $discount_order_amount = str_replace(' ', '',$discount_order_amount); 
            // convert price from dollars to cents
            $discount_order_amount = $discount_order_amount * 100;
        }

        $discount_product_amount = 0;
        if($_POST['discount_product_amount']){
            // remove commas and spaces from price
            $discount_product_amount = str_replace(',', '', $_POST['discount_product_amount']);
            $discount_product_amount = str_replace(' ', '',$discount_product_amount); 
            // convert price from dollars to cents
            $discount_product_amount = $discount_product_amount * 100;
        }

        $discount_order_amount = 0;
        if($_POST['discount_order_amount']){
            // remove commas and spaces from price
            $discount_order_amount = str_replace(',', '', $_POST['add_product_discount_amount']);
            $discount_order_amount = str_replace(' ', '',$discount_order_amount); 
            // convert price from dollars to cents
            $discount_order_amount = $discount_order_amount * 100;
        }

        /* begin: set limit for percentages at 100 */

        // remove commas, spaces and percentage from percentage
        $discount_order_percentage = str_replace(',', '', $_POST['discount_order_percentage']);
        $discount_order_percentage = str_replace(' ', '',$discount_order_percentage); 
        $discount_order_percentage = str_replace('%', '',$discount_order_percentage); 
        if ($discount_order_percentage > 100) {
            $discount_order_percentage = 100;
        } 

        // remove commas, spaces and percentage from percentage
        $discount_product_percentage = str_replace(',', '', $_POST['discount_product_percentage']);
        $discount_product_percentage = str_replace(' ', '',$discount_product_percentage); 
        $discount_product_percentage = str_replace('%', '',$discount_product_percentage); 
        if ($discount_product_percentage > 100) {
            $discount_product_percentage = 100;
        }

        // remove commas, spaces and percentage from percentage
        $add_product_discount_percentage = str_replace(',', '', $_POST['add_product_discount_percentage']);
        $add_product_discount_percentage = str_replace(' ', '',$add_product_discount_percentage); 
        $add_product_discount_percentage = str_replace('%', '',$add_product_discount_percentage); 
        if ($add_product_discount_percentage > 100) {
            $add_product_discount_percentage = 100;
        }

        // remove commas, spaces and percentage from percentage
        $discount_shipping_percentage = str_replace(',', '', $_POST['discount_shipping_percentage']);
        $discount_shipping_percentage = str_replace(' ', '',$discount_shipping_percentage); 
        $discount_shipping_percentage = str_replace('%', '',$discount_shipping_percentage); 
        if ($discount_shipping_percentage > 100) {
            $discount_shipping_percentage = 100;
        }

        /* end: set limit for percentages at 100 */

        // update offer action
        $query = "UPDATE offer_actions SET
                    name = '" . escape($_POST['name'] ?? '') . "',
                    type = '" . escape($_POST['type'] ?? '') . "',
                    discount_order_amount = '" . escape($discount_order_amount) . "',
                    discount_order_percentage = '" . escape($discount_order_percentage) . "',
                    discount_product_product_id = '" . escape($_POST['discount_product_product_id'] ?? '') . "',
                    discount_product_amount = '" . escape($discount_product_amount) . "',
                    discount_product_percentage = '" . escape($discount_product_percentage) . "',
                    add_product_product_id = '" . escape($_POST['add_product_product_id'] ?? '') . "',
                    add_product_quantity = '" . escape($_POST['add_product_quantity'] ?? '') . "',
                    add_product_discount_amount = '" . escape($add_product_discount_amount) . "',
                    add_product_discount_percentage = '" . escape($add_product_discount_percentage) . "',
                    discount_shipping_percentage = '" . escape($discount_shipping_percentage) . "',
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // loop through all shipping methods in order to add database records
        foreach ($shipping_methods as $shipping_method) {
            // if shipping method was selected, then add database record for shipping method for this offer action
            if (($_POST['shipping_method_' . $shipping_method['id']] ?? '') == 1) {
                $query =
                    "INSERT INTO offer_actions_shipping_methods_xref (
                        offer_action_id,
                        shipping_method_id)
                    VALUES (
                        '" . escape($_POST['id'] ?? '') . "',
                        '" . $shipping_method['id'] . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }

        log_activity(lang(array('string'=>'offer action ({var:1}) was modified','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    }

    // forward user to view offer actions screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_offer_actions.php');
}
?>
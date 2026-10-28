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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM shipping_methods WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

if (!$_POST) {
    $result = db_q("SELECT * FROM shipping_methods WHERE id = ?", array($id)) or output_error('Query failed.');
    $shipping_method = mysqli_fetch_assoc($result);

    // The excluded dates as the form shows them, comma separated.
    $result = db_q("SELECT date FROM excluded_transit_dates WHERE shipping_method_id = ? ORDER BY date", array($id)) or output_error('Query failed.');

    $excluded_transit_dates = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $excluded_transit_dates[] = prepare_form_data_for_output($row['date'], 'date');
    }

    $output =
    pg_page_shell([
        'title'=> lang('Edit Shipping Method'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Shipping Method'),
        'heading_description' => lang('Edit a shipping method that will be made available during checkout based on the products and destination address.'),
        'cancel'=>array('enable'=>'true','url'=>'view_shipping_methods.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Methods'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_methods.php'), array('label' => lang('Edit Shipping Method'))),
        ]) . '
<main id="content" class="container-fluid">
    ' . get_date_picker_format() . get_date_time_picker_format() . get_time_picker_format() . pg_fields_script() . '
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="edit_shipping_method.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_shipping_method_form_cards(pg_shipping_method_form_row_values($shipping_method, implode(',', $excluded_transit_dates)), pg_shipping_method_zone_options_for($id)) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('shipping method')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . $id . '">
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

    print $output;

} else {

    validate_token_field();

    // The zones and excluded dates go in both cases: a delete drops them, a
    // save writes them again.
    db_q("DELETE FROM shipping_methods_zones_xref WHERE shipping_method_id = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM excluded_transit_dates WHERE shipping_method_id = ?", array($id)) or output_error('Query failed.');

    // if shipping method was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        db_q("DELETE FROM shipping_methods WHERE id = ?", array($id)) or output_error('Query failed.');
        db_q("DELETE FROM offer_actions_shipping_methods_xref WHERE shipping_method_id = ?", array($id)) or output_error('Query failed.');
        db_q("DELETE FROM shipping_cutoffs WHERE shipping_method_id = ?", array($id)) or output_error('Query failed.');
        db_q("DELETE FROM ship_date_adjustments WHERE shipping_method_id = ?", array($id));

        log_activity(lang(array('string'=>'shipping method ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
        
    // else shipping method was not selected for delete
    } else {
        $columns = pg_shipping_method_form_columns($_POST);
        $columns['user'] = (string) $user['id'];

        $assignments = array();

        foreach (array_keys($columns) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $parameters = array_values($columns);
        $parameters[] = $id;

        // update shipping method
        db_q(
            "UPDATE shipping_methods SET " . implode(', ', $assignments) . ", timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            $parameters) or output_error('Query failed.');

        pg_shipping_method_save_links($id, $_POST);

        log_activity(lang(array('string'=>'shipping method ({var:1}) was modified','vars'=>$columns['name'])), $_SESSION['sessionusername']);
    }

    // forward user to view shipping methods screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_shipping_methods.php');
}

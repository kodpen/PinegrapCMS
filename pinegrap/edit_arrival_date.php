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
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM arrival_dates WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');
$liveform = new liveform('edit_arrival_date');

if (!$_POST) {
    // get arrival date data
    $query = "SELECT * FROM arrival_dates WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $arrival_date_row = $row;

    // get shipping cut-offs
    $query =
        "SELECT
            shipping_method_id,
            date_and_time
        FROM shipping_cutoffs
        WHERE arrival_date_id = ?
        ORDER BY id ASC";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

    $shipping_cutoffs = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $shipping_cutoffs[] = $row;
    }

    $output_shipping_cutoffs_for_javascript = '';
    $count = 0;

    // loop through shipping cut-offs in order to prepare output for javascript
    foreach ($shipping_cutoffs as $shipping_cutoff) {
        $output_shipping_cutoffs_for_javascript .=
            'shipping_cutoffs[' . $count . '] = new Array();
            shipping_cutoffs[' . $count . ']["shipping_method_id"] = "' . $shipping_cutoff['shipping_method_id'] . '";
            shipping_cutoffs[' . $count . ']["date_and_time"] = "' . prepare_form_data_for_output($shipping_cutoff['date_and_time'], 'date and time') . '";' . "\n";

        $count++;
    }
    
    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Arrival Date'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Arrival Date'))),
        'heading_description' => lang('Edit a arrival date for any special occasion, or holiday that will be presented during checkout. (Delete all will hide feature.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_arrival_dates.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Arrival Dates'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_arrival_dates.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Arrival Date'))))),
        ]) . '
<main id="content" class="container-fluid">
            ' . get_date_picker_format() . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="edit_arrival_date.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="last_shipping_cutoff_number" name="last_shipping_cutoff_number" value="0" />
                    ' . pg_arrival_date_form_cards($arrival_date_row, $output_shipping_cutoffs_for_javascript) . '
                    ' . pg_fields_script() . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('arrival date')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // delete shipping cut-offs (we do this regardless of whether the arrival date is being deleted or edited)
    $query = "DELETE FROM shipping_cutoffs WHERE arrival_date_id = ?";
    $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
    
    // assume that there is not an error for a shipping cut-off until we find out otherwise
    $shipping_cutoff_error = FALSE;
    
    // if arrival date was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete arrival date
        $query = "DELETE FROM arrival_dates WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('arrival date'), $_POST['name']) )), $_SESSION['sessionusername']);
    // else arrival date was not selected for delete
    } else {
        // update arrival date
        $query =
            "UPDATE arrival_dates SET
                name = ?,
                description = ?,
                code = ?,
                arrival_date = ?,
                status = ?,
                start_date = ?,
                end_date = ?,
                default_selected = ?,
                sort_order = ?,
                custom = ?,
                custom_maximum_arrival_date = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(pg_arrival_date_form_values($_POST, $user['id']), array((string) ($_POST['id'] ?? '')))) or output_error('Query failed.');

        // if default selected was checked, then turn value off for all other arrival dates
        if (($_POST['default_selected'] ?? '') == 1) {
            // update arrival date
            $query = "UPDATE arrival_dates SET default_selected = 0 WHERE id != ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        }
        
        // Cut-offs without a shipping method, with a method used twice or with
        // a time outside the dates are left out.
        $shipping_cutoff_error = pg_arrival_date_form_save_cutoffs($_POST['id'] ?? '', $_POST);

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('arrival date'), $_POST['name']) )), $_SESSION['sessionusername']);
    }
    
    // if there was a shipping cut-off error, then add notice and forward user to edit arrival date screen
    if ($shipping_cutoff_error == TRUE) {
        $liveform->add_notice(lang('The arrival date has been saved, however one or more shipping cut-offs were removed because of an error. It is recommended that you review the shipping cut-offs. Please make sure you select a shipping method, enter a valid cut-off date &amp; time that falls between the start date and end date, and make sure you do not enter multiple shipping cut-offs for the same shipping method.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_arrival_date.php?id=' . urlencode($_POST['id']));
        
    // else there was not a shipping cut-off error, so forward user to view arrival dates screen
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_arrival_dates.php');
    }
}
?>
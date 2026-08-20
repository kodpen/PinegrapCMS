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
    // get state data
    $query = "SELECT * FROM states WHERE id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $name = h($row['name']);
    $code = h($row['code']);
    $country_id = $row['country_id'];

    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))),
        'cancel'=>array('enable'=>'true','url'=>'view_states.php')
    ,
            'breadcrumb' => array(array('label' => lang('All States'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_states.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit a state/province that can be included in any shipping zone or tax zone.') . '" title="' . lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))) . '">[' . $name . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_state.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('State'))) . '</label>
                                            <input value="' . $name . '" name="name" id="name" type="text" class="form-control add-header-content-updater" maxlength="50" />
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="code" class="form-label">' . lang('State Code') . '</label>
                                            <input value="' . $code . '" name="code" id="code" type="text" class="form-control" maxlength="50" />
                                            <div class="text-end form-text">' . lang('New State/Province Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Country') . '</label>
                                            <select name="country_id" id="country_id"  class="form-select">' .  select_country($country_id) . '</select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('state')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // if state was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete state
        $query = "DELETE FROM states WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete state references in zones_states_xref
        $query = "DELETE FROM zones_states_xref WHERE state_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete state references in tax_zones_states_xref
        $query = "DELETE FROM tax_zones_states_xref WHERE state_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete verified shipping addresses for this state
        $query = "DELETE FROM verified_shipping_addresses WHERE state_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('state'), $_POST['name']) )), $_SESSION['sessionusername']);

    // else state was not selected for delete
    } else {
        // update state
        $query = "UPDATE states SET
                    name = '" . escape($_POST['name'] ?? '') . "',
                    code = '" . escape($_POST['code'] ?? '') . "',
                    country_id = '" . escape($_POST['country_id'] ?? '') . "',
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('state'), $_POST['name']) )), $_SESSION['sessionusername']);

    }

    // forward user to view states screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_states.php');
}
?>
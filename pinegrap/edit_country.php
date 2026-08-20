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
    $row = db_item("SELECT * FROM countries WHERE id = '" . escape($_GET['id']) . "'");

    $name = $row['name'];
    $code = $row['code'];
    $zip_code_required = $row['zip_code_required'];
    $transit_adjustment_days = $row['transit_adjustment_days'];
    $default_selected = $row['default_selected'];

    $output =
        pg_page_shell([
            'title'=> lang('Edit Country'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Country'),
            'cancel'=>array('enable'=>'true','url'=>'view_countries.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Countries'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_countries.php'), array('label' => lang('Edit Country'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('Edit a country that can be included in any shipping zone or tax zone.') . '" title="' . lang('Edit Country') . '">' . h($name) . '</h2>
                        </div>
                    </div>
                    <form name="form" action="edit_country.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Country'))) . '</label>
                                                <input type="text" name="name" id="name" class="form-control add-header-content-updater" maxlength="50" value="' . h($name) . '" />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                <div class="form-text">' . lang('Display on Commerce Pages') . '</div>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="code" class="form-label">' . lang('Code') . '</label>
                                                <input type="text" name="code" id="code" class="form-control" maxlength="50" value="' . h($code) . '"/>
                                                <div class="form-text">' . lang('New Country Code for Order Reporting') . '</div>
                                            </div>

                                            <div class="col-12 my-1 mt-3">
                                                <div class="form-check form-switch">
                                                    <input value="1"' . ($zip_code_required ? ' checked' : '') . ' name="zip_code_required" id="zip_code_required" class="form-check-input" type="checkbox" role="switch">
                                                    <label class="form-check-label" for="zip_code_required">' . lang('Zip Code Required') . '</label>
                                                </div>
                                            </div>

                                            <div class="col-12 my-1 mb-3">
                                                <div class="form-check form-switch">
                                                    <input value="1"' . ($default_selected == 1 ? ' checked' : '') . '  name="default_selected" id="default_selected" class="form-check-input" type="checkbox" role="switch">
                                                    <label class="form-check-label" for="default_selected">' . lang('Selected by Default') . '</label>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                                <label for="transit_adjustment_days" class="form-label ">' . lang('Transit Adjustment Days') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0" value="' . h($transit_adjustment_days) . '" type="text" name="inventory_quantity" id="transit_adjustment_days" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                </div>
                                                <div class="form-text">' . lang('Shipping Delays Specific to this Country') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang('Saving') . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-danger" data-loading-content="' . lang('Deleting') . '" data-confirm-content="' . lang('WARNING: This country and all states and verified shipping addresses in this country will be permanently deleted.') . '"><span class="material-icons me-2">delete</span><span class="btn-text">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </main>' .
        output_footer();

    echo $output;

} else {
    validate_token_field();

    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // get all states so that we can delete all items connected to each state
        $states = db_items("SELECT id FROM states WHERE country_id = '" . escape($_POST['id'] ?? '') . "'");
        foreach ($states as $state) {
            db("DELETE FROM zones_states_xref WHERE state_id = '" . escape($state['id']) . "'");
            db("DELETE FROM tax_zones_states_xref WHERE state_id = '" . escape($state['id']) . "'");
            db("DELETE FROM verified_shipping_addresses WHERE state_id = '" . escape($state['id']) . "'");
        }

        db("DELETE FROM states WHERE country_id = '" . escape($_POST['id'] ?? '') . "'");
        db("DELETE FROM zones_countries_xref WHERE country_id = '" . escape($_POST['id'] ?? '') . "'");
        db("DELETE FROM countries WHERE id = '" . escape($_POST['id'] ?? '') . "'");

        log_activity(lang(array('string' => 'country ({var:1}) was deleted', 'vars' => array($_POST['name']))), $_SESSION['sessionusername']);

    } else {
        db("UPDATE countries SET
                name = '" . escape($_POST['name'] ?? '') . "',
                code = '" . escape($_POST['code'] ?? '') . "',
                zip_code_required = '" . e($_POST['zip_code_required'] ?? '') . "',
                transit_adjustment_days = '" . escape($_POST['transit_adjustment_days'] ?? '') . "',
                default_selected = '" . escape($_POST['default_selected'] ?? '') . "',
                user = '" . $user['id'] . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'");

        // if default selected was checked, then turn value off for all other countries
        if ($_POST['default_selected'] == 1) {
            db("UPDATE countries SET default_selected = 0 WHERE id != '" . escape($_POST['id'] ?? '') . "'");
        }

        log_activity(lang(array('string' => 'country ({var:1}) was modified', 'vars' => array($_POST['name']))), $_SESSION['sessionusername']);
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_countries.php');
}

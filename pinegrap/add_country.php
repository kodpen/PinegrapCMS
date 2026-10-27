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

if (!$_POST) {
    $output =
        pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))),
        'heading_description' => lang('Add a new country that can be included in any shipping zone or tax zone.'),
            'breadcrumb' => array(array('label' => lang('All Countries'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_countries.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="add_country.php" method="post">
                        ' . get_token_field() . '
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-7">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Country'))) . '</label>
                                                <input type="text" name="name" id="name" class="form-control" maxlength="50" />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                <div class="form-text">' . lang('Display on Commerce Pages') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="code" class="form-label">' . lang('Code') . '</label>
                                                <input type="text" name="code" id="code" class="form-control" maxlength="50" />
                                                <div class="form-text">' . lang('New Country Code for Order Reporting') . '</div>
                                            </div>

                                            <div class="col-12 my-1 mt-5">
                                                <div class="form-field">
                                                    <div class="switch"><input value="1"  name="zip_code_required" id="zip_code_required" class="" type="checkbox" role="switch"></div>
                                                    <label class="" for="zip_code_required">' . lang('Zip Code Required') . '</label>
                                                </div>
                                            </div>

                                            <div class="col-12 my-1 mb-5">
                                                <div class="form-field">
                                                    <div class="switch"><input value="1"  name="default_selected" id="default_selected" class="" type="checkbox" role="switch"></div>
                                                    <label class="" for="default_selected">' . lang('Selected by Default') . '</label>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12 md:col-6 lg:col-4 my-1">
                                                <label for="transit_adjustment_days" class="form-label ">' . lang('Transit Adjustment Days') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="transit_adjustment_days" id="transit_adjustment_days" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                </div>
                                                <div class="form-text">' . lang('Shipping Delays Specific to this Country') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    // create country
    $query = "INSERT INTO countries (
                name,
                code,
                zip_code_required,
                transit_adjustment_days,
                default_selected,
                user,
                timestamp)
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP())";
    $result = db_q($query, array(
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['code'] ?? ''),
        (string) ($_POST['zip_code_required'] ?? ''),
        (string) ($_POST['transit_adjustment_days'] ?? ''),
        (string) ($_POST['default_selected'] ?? ''),
        (string) $user['id'])) or output_error('Query failed.');

    $country_id = mysqli_insert_id(db::$con);

    // if default selected was checked, then turn value off for all other countries
    if ($_POST['default_selected'] == 1) {
        // update country
        $query = "UPDATE countries SET default_selected = 0 WHERE id != ?";
        $result = db_q($query, array((int) $country_id)) or output_error('Query failed.');
    }

    log_activity( lang(array('string'=>'country ({var:1}) was created','vars'=>array($_POST['name']) )) , $_SESSION['sessionusername']);


    // forward user to view countries page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_countries.php');
}
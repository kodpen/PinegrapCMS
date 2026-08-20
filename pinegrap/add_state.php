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
    $output =
        pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('State'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('State'))),
        'cancel'=>array('enable'=>'true','url'=>'view_states.php')
    ,
            'breadcrumb' => array(array('label' => lang('All States'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_states.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('State'))))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Add a new state/province that can be included in any shipping zone or tax zone.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('State'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('state'))) . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="add_state.php" method="post">
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
                                                <input name="name" id="name" type="text" class="form-control add-header-content-updater" maxlength="50" />
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="code" class="form-label">' . lang('State Code') . '</label>
                                                <input name="code" id="code" type="text" class="form-control" maxlength="50" />
                                                <div class="text-end form-text">' . lang('New State/Province Code for Order Reporting') . '</div>
                                            </div>
                                            <div class="col-12 col-md-12 col-lg-4 my-2">
                                                <label for="name" class="form-label">' . lang('Country') . '</label>
                                                <select name="country_id" id="country_id"  class="form-select">' .  select_country() . '</select>
                                            </div>
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
    
    // create state
    $query = "INSERT INTO states (
                name,
                code,
                country_id,
                user,
                timestamp)
            VALUES (
                '" . escape($_POST['name'] ?? '') . "',
                '" . escape($_POST['code'] ?? '') . "',
                '" . escape($_POST['country_id'] ?? '') . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('state'),$_POST['name']))), $_SESSION['sessionusername']);

    // forward user to view states page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_states.php');
}
?>
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

$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php">' . lang('All Referral Sources') . '</a></li>';

if (!$_POST) {
    $output =
        pg_page_shell(
            array(
                'title'=> lang('Create Refferal Source'),
                'extra classes'=>'setting',
                'icon'=>'setting',
                'heading'=>lang('Create Refferal Source'),
                'cancel'=>array('enable'=>'true','url'=>'view_referral_sources.php'),
                'breadcrumb' => array(
                    array('label' => lang('All Referral Sources'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php'),
                    array('label' => lang('Create Refferal Source')),
                ),
            )
        ) . '
                    <div class="row">
                <div class="col-12">
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new referral source to gather marketing information during checkout. (Delete all will hide feature.)') . '" title="' . lang('Create Refferal Source') . '">[' . lang('new referral source') . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="add_referral_source.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('New Referral Source Information') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <h5>' . lang('Order Preview Page Display Options') . '</h5>
                                            
                                            <div class="col-12 col-md-6 my-2">
                                                <label for="name" class="form-label">*' . lang('Referral Source Name') . '</label>
                                                <input type="text" name="name" class="form-control add-header-content-updater" maxlength="50" />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                            <div class="col-12 col-sm-2 my-2">
                                                <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0" value="" type="text" name="sort_order" id="sort_order" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button">add</button>
                                                </div>
                                            </div>


                                        </div>
                                        <div class="row mt-4">
                                            <h5>' . lang('New Referral Source Code for Order Reporting') . '</h5>
                                            <div class="col-12 col-md-4 my-2">
                                                <label for="code" class="form-label">*' . lang('Referral Source Code') . '</label>
                                                <input type="text" name="code" class="form-control" maxlength="50" />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
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

    print $output;

} else {
    validate_token_field();
    
    // create referral source
    $query = "INSERT INTO referral_sources (
                name,
                code,
                sort_order,
                user,
                timestamp)
            VALUES (
                '" . escape($_POST['name'] ?? '') . "',
                '" . escape($_POST['code'] ?? '') . "',
                '" . escape($_POST['sort_order'] ?? '') . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $referral_source_id = mysqli_insert_id(db::$con);

    log_activity(lang(array('string'=>'referral source ({var:1}) was created','vars'=>array($_POST['name']) )) , $_SESSION['sessionusername']);


    // forward user to view referral sources page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_referral_sources.php');
}
?>
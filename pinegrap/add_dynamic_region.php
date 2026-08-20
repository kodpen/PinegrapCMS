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
validate_area_access($user, 'administrator');

if (!isset($_POST['name'])) {
    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Dynamic Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Dynamic Region'))),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Dynamic Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Dynamic Region'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new dynamic region that contains PHP code and can be added to any page style.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Dynamic Region'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('dynamic region'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_dynamic_region.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Dynamic Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<dregion>') . '</div>
                                                <input name="name" id="name" type="text" class="form-control add-header-content-updater" maxlength="100" />
                                                <div class="input-group-text">' . h('</dregion>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-5">' . lang('PHP Code to appear on associated Pages') . '</h5>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('PHP Code Snippet') . '</label>
                                            <textarea name="code" id="code" rows="30" cols="60" wrap="off"></textarea>
                                            ' . get_codemirror_includes() . '
                                            ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'php')) . '
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
    
    include_once('liveform.class.php');
    
    $_POST['name'] = trim($_POST['name']);
    // insert row into region table
    $query = "INSERT INTO dregion (dregion_name, dregion_code, dregion_user, dregion_timestamp) "
            ."VALUES ('" . escape($_POST['name'] ?? '') . "', '" . escape($_POST['code'] ?? '') . "', {$user['id']}, UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('dynamic region'),$_POST['name']))), $_SESSION['sessionusername']);
    $notice = lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Dynamic Region') ));


    $liveform_view_styles = new liveform('view_regions');
    $liveform_view_styles->add_notice($notice);
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions');
}
?>
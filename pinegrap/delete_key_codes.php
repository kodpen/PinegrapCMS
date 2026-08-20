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

if (!$_POST) {
    $output =
    pg_page_shell([
        'title'=> lang('Delete Key Codes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Delete Key Codes'),
        'cancel'=>array('enable'=>'true','url'=>'view_key_codes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Key Codes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php'), array('label' => lang('Delete Key Codes'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Click \'Delete\' to delete all key codes.') . '" title="' . lang('Delete Key Codes') . '">' . lang('Delete Key Codes') . '</h2>
                    </div>
                </div>
                <form action="delete_key_codes.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_delete" name="submit_delete" value="Delete" class="btn my-1  btn-danger" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: All {var:1} will be permanently deleted.','vars'=>array(lang('key codes')))) . '" ><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // delete all key codes
    $query = "TRUNCATE key_codes";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang('all key codes were deleted'), $_SESSION['sessionusername']);
    
    // forward user to view key codes screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_key_codes.php');
}
?>
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
    // What the button removes, for the Summary. expiration_date
    // '0000-00-00' is a code that never expires.
    $key_code_counts = db_item(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(enabled = 1), 0) AS enabled,
            COALESCE(SUM(expiration_date != '0000-00-00' AND expiration_date < CURRENT_DATE()), 0) AS expired
        FROM key_codes");
    $key_code_total = is_array($key_code_counts) ? (int) $key_code_counts['total'] : 0;

    $status_row = function ($label, $value, $last) {
        return '
                                        <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                            <dt class="fw-normal">' . h($label) . '</dt>
                                            <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                        </div>';
    };

    $export_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php?submit_data=' . urlencode('Export Key Codes');

    $output =
    pg_page_shell([
        'title'=> lang('Delete Key Codes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Delete Key Codes'),
        'heading_description' => lang('Click \'Delete\' to delete all key codes.'),
        'cancel'=>array('enable'=>'true','url'=>'view_key_codes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Key Codes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php'), array('label' => lang('Delete Key Codes'))),
        ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <form action="delete_key_codes.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <!-- Edit screen layout of product_builder.php: the form column, the sticky status column (Summary), the sticky save bar. -->
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Delete Key Codes') . '
                            </h2>
                            <div class="card-body pt-0">'
        . ($key_code_total > 0
            ? '
                                <div class="alert theme-warning" role="alert"><i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . lang('Every key code is deleted, whichever offer it belongs to. This cannot be undone: customers can no longer use these codes at checkout.') . '</div>
                                <p class="mb-0">' . lang('To keep a copy, export the key codes first; the file can be imported again.') . ' <a href="' . h($export_url) . '"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>' . lang('Export Key Codes') . '</a></p>'
            : '
                                <p class="mb-0">' . lang('There are no key codes to delete.') . '</p>') . '
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Summary') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <dl class="mb-0">'
        . $status_row(lang('Key Codes'), h(pg_format_number($key_code_total, 0)), false)
        . $status_row(lang('Enabled'), h(pg_format_number(is_array($key_code_counts) ? (int) $key_code_counts['enabled'] : 0, 0)), false)
        . $status_row(lang('Expired'), h(pg_format_number(is_array($key_code_counts) ? (int) $key_code_counts['expired'] : 0, 0)), true) . '
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_delete" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: All {var:1} will be permanently deleted.','vars'=>array(lang('key codes')))) . '"' . ($key_code_total > 0 ? '' : ' disabled') . '><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
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
    
    log_activity(lang('All key codes were deleted.'), $_SESSION['sessionusername']);
    
    // forward user to view key codes screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_key_codes.php');
}
?>
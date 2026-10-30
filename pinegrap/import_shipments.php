<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tracking numbers from a CSV file: one row per parcel, order_number, number
 * and an optional carrier. Each order's new numbers are added to the ones its
 * address already has through update_order(), the order screen's own save,
 * so the parcel row, order.shipped, the shipping e-mail and the invoice on
 * shipment follow as they do for a number typed in by hand. An order that
 * ships to several addresses is refused: the file cannot say which parcel
 * goes where. The page answers with a result for every row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
// A file over post_max_size empties the whole request; say so instead of
// answering with the token page.
pg_upload_refuse_discarded_body();

$user = validate_user();
validate_ecommerce_access($user);

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_import.php');

// Kept small enough that one request writes the whole file well inside the
// time limit; a larger shipment list is uploaded in parts.
$shipping_import_max_rows = 2000;
$shipping_import_max_bytes = 1048576;

$output_result = '';
$output_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $upload = isset($_FILES['file']) ? $_FILES['file'] : null;

    $upload_limits = pg_upload_limits();
    $upload_refusal = pg_upload_file_refusal($upload, $upload_limits['file_max']);

    if ($upload_refusal !== '') {
        $output_error = $upload_refusal;
    } elseif (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $output_error = lang('Please select a CSV file to upload.');
    } elseif ($upload['size'] > $shipping_import_max_bytes) {
        $output_error = lang('The file is larger than 1 MB. Please upload it in parts.');
    } else {
        $output_result = pg_shipping_import_run($upload['tmp_name'], !empty($_POST['notify']), $shipping_import_max_rows);
    }
}

print
    pg_page_shell(
        array(
            'title'               => lang('Import Tracking Numbers'),
            'extra classes'       => 'store',
            'icon'                => 'store',
            'heading'             => lang('Import Tracking Numbers'),
            'heading_description' => lang('Add tracking numbers to orders from a CSV file.'),
            'cancel'              => array('enable' => 'true', 'url' => 'view_orders.php'),
            'breadcrumb'          => array(
                array('label' => lang('View Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang('Import Tracking Numbers')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . (($output_error !== '') ? '<div class="alert theme-danger my-5">' . h($output_error) . '</div>' : '') . '
            ' . (($output_result !== '') ? '<div class="card my-7"><div class="card-body">' . $output_result . '</div></div>' : '') . '
            <form name="form" action="import_shipments.php" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . h(lang('Import CSV')) . '
                    </div>
                    <div class="card-body">
                        <p class="fg-secondary">' . h(lang('One row per parcel with the columns order_number, number and carrier. The carrier may be left empty; the header row is optional. Numbers are added to the ones the order already has. Orders that ship to more than one address are skipped.')) . '</p>
                        <pre class="bg-1 p-3 rounded-4 small">order_number,number,carrier
10045,1234567890,Yurtiçi Kargo
10046,1Z999AA10123456784,</pre>
                        <div class="my-5">
                            <label for="file" class="form-label">' . h(lang('CSV File')) . '</label>
                            <input name="file" id="file" type="file" accept=".csv,text/csv,text/plain" class="form-control w-auto" required>
                            ' . pg_upload_limit_hint($shipping_import_max_bytes) . '
                        </div>
                        <div class="form-field my-5">
                            <div class="switch"><input type="checkbox" name="notify" id="notify" value="1" class="" checked></div>
                            <label class="" for="notify">' . h(lang('Send customers the shipping e-mail')) . '</label>
                        </div>
                    </div>
                </div>
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_import" value="Import" class="btn my-1 btn-solid theme-success"><i class="bi bi-upload me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Import Tracking Numbers')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>' .
    output_footer();

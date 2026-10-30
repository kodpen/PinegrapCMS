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

// The product is one value; one sent as an array (?product_id[]=x) counts as
// not given: it ended in an error page.
pg_request_single_values(array('product_id'));

$liveform = new liveform('preview_form');

// get product info
$query =
    "SELECT 
        name,
        short_description,
        form_name,
        form_label_column_width
    FROM products
    WHERE id = ?";
$result = db_q($query, array((string) ($_GET['product_id'] ?? ''))) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// Opened without a product, or for one that no longer exists (stale link,
// hand-typed URL): a "not found" screen instead of PHP notices and an empty
// form.
if (!$row) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$product_name = $row['name'];
$short_description = $row['short_description'];
$form_name = $row['form_name'];
$form_label_column_width = $row['form_label_column_width'];

$form_description = $form_name;

// if form name is blank and short description is not, use short description for form name
if (($form_name == '') && ($short_description != '')) {
    $form_description = $short_description;
    
// else, if form name is blank and product name is not, use product name for form name
} else if (($form_name == '') && ($product_name != '')) {
    $form_description = $product_name;
}

$output_legend = '';

// if there is a form name, then output a legend
if ($form_name != '') {
    $output_legend = '<legend class="software_legend">' . h($form_name) . '</legend>';
}

$form_info = get_form_info(0, $_GET['product_id'], 0, 0, $form_label_column_width, $office_use_only = false, $liveform, 'backend');

// assume that we don't need to output wywiwyg javascript until we find out otherwise
$output_wysiwyg_javascript = '';

// if there is at least one wysiwyg field, prepare wysiwyg fields
if ($form_info['wysiwyg_fields']) {
    $output_wysiwyg_javascript = get_wysiwyg_editor_code($form_info['wysiwyg_fields']);
}

$output = pg_page_shell( array(
    'title' => h($form_description) . ' | ' . lang('Preview Form'),
    'icon' => 'store',
    'heading' => lang('Preview Form'),
    'cancel'=>array('enable'=>'true', 'title'=>lang('Back to Product Form'), 'url'=>'view_fields.php?product_id=' . (int)$_GET['product_id']),
    'breadcrumb' => array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Edit Product'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id']))),
        array('label' => lang('Edit Product Form'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?product_id=' . h(escape_javascript($_GET['product_id']))),
        array('label' => lang('Preview Form')),
    ),
) ) . '
<main id="content" class="container-fluid">
    ' . $output_wysiwyg_javascript . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-3  flex-wrap">
                <div class="col-12 sm:col-12 text-center md:text-start">
<div class="row mb-3">
                        <div class="col-12 md:col">
                            <h2 class="d-inline-block text-break">' . h($form_description) . '</h2>
                            <p class="form-text mt-0">' . lang('The form layout will look like this when displayed within a page.') . '</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card my-7 ">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('Form Properties') . '
                        </div>
                        <div class="card-body text-center md:text-start">
                            <div class="row">
                                <div class="col-12 my-3">
                                    <fieldset style="margin-bottom: 1em">
                                        ' . $output_legend . '
                                        <div style="padding: 0.7em">
                                            <table>
                                                ' . $form_info['content'] . '
                                            </table>
                                            ' . $form_info['picker_scripts'] . '
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

print $output;
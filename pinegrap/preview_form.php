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

$liveform = new liveform('preview_form');

// get product info
$query =
    "SELECT 
        name,
        short_description,
        form_name,
        form_label_column_width
    FROM products
    WHERE id = '" . escape($_GET['product_id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

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
    'cancel'=>array('enable'=>'true', 'title'=>lang('Back to Product Form'), 'url'=>'view_fields.php?product_id=' . (int)$_GET['product_id']),
    'breadcrumb' => array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Edit Product'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id']))),
        array('label' => lang('Edit Product Form'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?product_id=' . h(escape_javascript($_GET['product_id']))),
        array('label' => lang('Preview Form')),
    ),
) ) . '
    ' . $output_wysiwyg_javascript . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 text-center text-md-start">
<div class="row mb-2">
                        <div class="col-12 col-md">
                            <h2 class="d-inline-block text-break" data-bs-content="' . lang('The form layout will look like this when displayed within a page.') . '" title="' . lang('Preview Form') . '">' . h($form_description) . '</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card my-4 ">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Form Properties') . '
                        </div>
                        <div class="card-body text-center text-md-start">
                            <div class="row">
                                <div class="col-12 my-2">
                                    <fieldset style="margin-bottom: 1em">
                                        ' . $output_legend . '
                                        <div style="padding: 0.7em">
                                            <table>
                                                ' . $form_info['content'] . '
                                            </table>
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
</main>' .
output_footer();

print $output;
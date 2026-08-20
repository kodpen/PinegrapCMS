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

include_once('liveform.class.php');
$liveform = new liveform('view_product_attributes');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_product_attributes'][$key] = trim($value);
    }
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_product_attributes']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_product_attributes']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_product_attributes']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'product_attributes.name';
        break;

    case lang('Label & Options'):
        $sort_column = 'product_attributes.label';
        break;

    case lang('Created'):
        $sort_column = 'product_attributes.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'product_attributes.last_modified_timestamp';
        break;

    default:
        $sort_column = 'product_attributes.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_product_attributes']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_product_attributes']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_product_attributes']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_product_attributes']['order'] = 'asc';
}

$all_product_attributes = 0;

// get the total number of product attributes
$query = "SELECT COUNT(*) FROM product_attributes";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_product_attributes = $row[0];



// Get all product attributes.
$query =
    "SELECT
        product_attributes.id,
        product_attributes.name,
        product_attributes.label,
        created_user.user_username AS created_username,
        product_attributes.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        product_attributes.last_modified_timestamp
    FROM product_attributes
    LEFT JOIN user AS created_user ON product_attributes.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON product_attributes.last_modified_user_id = last_modified_user.user_id
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_product_attributes']['order'] ?? ''));
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$product_attributes = mysqli_fetch_items($result);

$output_rows = '';

// if there is at least one result to display
if ($product_attributes) {
    foreach ($product_attributes as $product_attribute) {
        $options = db_items(
            "SELECT
                id,
                label,
                no_value
            FROM product_attribute_options
            WHERE product_attribute_id = '" . $product_attribute['id'] . "'
            ORDER BY sort_order");

       
        $output_options = '';
        foreach ($options as $option) {
            $output_no_value = '';

            if ($option['no_value']) {
                $output_no_value = lang(' [\'No Thanks\' Option]');
            }

            $output_options .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($option['label']) . $output_no_value . '</span></li>';
        }

       

        $created_username = '';
        
        if ($product_attribute['created_username'] != '') {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($product_attribute['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($product_attribute['last_modified_username'] != '') {
            $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($product_attribute['last_modified_username']) ) ) );
        }
        if($product_attribute['label'] != ''){
            $output_label = h($product_attribute['label']);
        }else{
            $output_label = '<div class="text-danger">[' . lang('No Label') . ']</div>';
        }



        $output_rows .=
            '<tr>
                <td></td>
                <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product_attribute.php?id=' . $product_attribute['id'] . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="chart_label align-middle">' . h($product_attribute['name']) . '</td>
                <td class="align-middle ">
                    <div class="row row-cols-2 align-middle">
                        <div class="col align-self-center">' . $output_label . '</div>
                        <div class="dropdown col">
                            <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false">' . lang('Options') . '</button>
                            <ul class="dropdown-menu">
                                ' . $output_options . '
                            </ul>
                        </div>
                    </div>
                </td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $product_attribute['created_timestamp'])) . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $product_attribute['last_modified_timestamp'])) . $last_modified_username . '</td>
            </tr>';
    }
}

echo
    pg_page_shell([
        'title'=> lang('All Product Attributes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Product Attributes')
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All attributes that can be assigned to products.') . '" title="' . lang('All Product Attributes') . '">' . lang('All Product Attributes') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_product_attribute.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['ecommerce']['view_product_attributes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_product_attributes']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Label & Options'), ($_SESSION['software']['ecommerce']['view_product_attributes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_product_attributes']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_product_attributes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_product_attributes']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_product_attributes']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_product_attributes']['order'] ?? '')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();

$liveform->remove_form();
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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

$output_layout_buttons = '';
$output_rss_table_heading = '';
$output_office_use_only_table_heading = '';
$output_preview_button  = '';
// if there is a page_id supplied in the query string, then this is a page form
if ((isset($_GET['page_id'])) && ($_GET['page_id'] != '')) {
    validate_area_access($user, 'user');
    
    // get page info
    $query =
        "SELECT
            page_type,
            page_folder,
            page_name
        FROM page
        WHERE page_id = '" . escape($_GET['page_id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $page_type = $row['page_type'];
    $folder_id = $row['page_folder'];
    $page_name = $row['page_name'];
    
    $form_type = '';
    
    // get the form type by looking at the page type
    switch ($page_type) {
        case 'custom form':
            $form_type = 'custom';
            break;

        // Express order can have a shipping and/or billing form, so check query string for the type
        // of form that we are dealing with
        case 'express order':

            if ($_REQUEST['form_type'] == 'shipping') {
                $form_type = 'shipping';
            } else {
                $form_type = 'billing';
            }

            break;

        case 'shipping address and arrival':
            $form_type = 'shipping';
            break;

        case 'billing information':
            $form_type = 'billing';
            break;
    }

    // Get the form type name that we will output to user

    $form_type_name = '';

    switch ($form_type) {
        case 'custom':
            $form_type_name = lang('custom form');
            break;

        case 'shipping':
            $form_type_name = lang('custom shipping form');
            break;

        case 'billing':
            $form_type_name = lang('custom billing form');
            break;
    }
    
    $form_type_identifier_id = 'page_id';

    // Prepare sql filter in order to get correct fields

    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_REQUEST[$form_type_identifier_id]) . "'";

    // If the page type is express order then we need to add an extra filter for the form type
    if ($page_type == 'express order') {
        $form_type_filter .=
            " AND form_fields.form_type = '" . e($form_type) . "'";
    }
    
    // validate user's access
    if (check_edit_access($folder_id) == false) {
        log_activity(lang( array('string'=>'access denied to view fields for {var:1} because user does not have access to modify folder that {var:1} is in','vars'=>array($form_type_name) ) ), $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }

    $form_name = '';

    // If this is a page and form type that supports a form name, then get it
    if ($page_type != 'express order' or $form_type != 'shipping') {

        // get form name for page
        $query = "SELECT form_name FROM " . str_replace(' ', '_', $page_type) . "_pages WHERE page_id = '" . escape($_GET['page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $form_name = $row['form_name'];
    }
    
    // if form name is blank, use page name for form name
    if (!$form_name) {
        $form_name = $page_name;
    }
    
    // setup form designer heading, content heading and subheading.
    $output_form_designer_subnav_heading = h($form_name);
    
    $output_form_designer_subnav_subheading = '';
    
    // if the page name is different from the form name, then output page name
    if ($form_name != $page_name) {
        $output_form_designer_subnav_subheading = '<p class="p-0 m-0">' . lang('Displayed in Page') . ': ' . h($page_name) . '</p>';
    }

    // If this page supports a custom layout and the user is an admin or designer,
    // then determine if we need to output generate layout button.
    if (
        check_if_page_type_supports_layout($page_type)
        && (USER_ROLE < 2)
    ) {
        $layout_type = db_value(
            "SELECT layout_type
            FROM page
            WHERE page_id = '" . e($_GET['page_id']) . "'");

        // If this page has a custom layout type then output generate layout button.
        if ($layout_type == 'custom') {
            $output_layout_buttons =
            '<div class=" btn-group btn-group-sm flex-wrap ">
                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Loading') . '" href="page_designer.php?url=' . h(urlencode(PATH . encode_url_path($page_name))) . '&amp;type=layout&amp;id=' . h($_GET['page_id']) . '"><span class="material-icons me-1">code</span>' . lang('Edit Layout') . '</a>
                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Loading') . '" href="generate_layout.php?page_id=' . h($_GET['page_id']) . '"><span class="material-icons me-1">plagiarism</span>' . lang('Generate Layout') . '</a>
            </div>';
        }
    }
    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php">' . lang('All My Pages') . '</a></li>';
    $output_breadcrumb_second_level_item ='';
    $pg_breadcrumb_parent_items = array(
        array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
    );
    $output_form_designer_content_heading = ucwords($form_type_name);
    $output_form_designer_content_subheading = lang(array('string'=>'Add fields to this {var:1}','vars'=>$form_type_name));
    $delete_data_warning = '';
    
    // If this is a custom form, then output certain content.
    if ($form_type == 'custom') {
        $output_rss_table_heading = '<th>' . lang('RSS Element') . '</th>';
        $output_office_use_only_table_heading = '<th class="text-center">' . lang('Office Use Only') . '</th>';
        $delete_data_warning = lang(' and ALL SUBMITTED FORM DATA for the selected field(s)');
    }
    $output_cancel_onclick = 'window.location.href=\'' . h(escape_javascript($_GET['send_to'])) . '\'';
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1}{var:2} will be permanently deleted.','vars'=>array(lang('field(s)'),$delete_data_warning))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>';
    
// else if there is a product_id supplied in the query string, this is a product form
} elseif ((isset($_GET['product_id'])) && ($_GET['product_id'] != '')) {
    validate_ecommerce_access($user);
    
    $form_type = 'product';
    $form_type_name = 'product form';
    $form_type_identifier_id = 'product_id';
    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_GET[$form_type_identifier_id]) . "'";
    
    // get product name, short description and form name to determine what we will use for the form name
    $query = "SELECT 
                 name,
                 short_description,
                 form_name
             FROM products
             WHERE id = '" . escape($_GET['product_id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $product_name = $row['name'];
    $short_description = $row['short_description'];
    $form_name = $row['form_name'];
    
    // if form name is blank and short description is not, use short description for form name
    if (($form_name == '') && ($short_description != '')) {
        $form_name = $short_description;
        
    // else, if form name is blank and product name is not, use product name for form name
    } else if (($form_name == '') && ($product_name != '')) {
        $form_name = $product_name;
    }
    
    // setup form designer heading, content heading and subheading
    $output_form_designer_subnav_heading = h($short_description);
    $output_form_designer_subnav_subheading = '
    <p class="p-0 m-0">' . lang('Product ID / SKU') . ': ' . h($product_name) . '</p>
    <p class="p-0 m-0">' . lang('Form Name') . ': ' . h($form_name) . '</p>';
    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php">' . lang('All Products') . '</a></li>';
    $output_breadcrumb_second_level_item = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id'])) . '">' . lang('Edit Product') . '</a></li>';
    $pg_breadcrumb_parent_items = array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Edit Product'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id']))),
    );
    $output_form_designer_content_heading = lang('Edit Product Form');
    $output_form_designer_content_subheading = lang('Add fields to this product form.');
    $output_cancel_onclick = 'window.location.href=\'edit_product.php?id=' . h(escape_javascript($_GET['product_id'])) . '\'';
    $output_preview_button = '
    <div class=" btn-group btn-group-sm flex-wrap ">
        <button type="button" class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Loading') . '" onclick="document.location.href = \'preview_form.php?product_id=\' + document.getElementById(\'product_id\').value"><span class="material-icons me-1">preview</span>' . lang('Preview') . '</button>
    </div>';
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('field(s)'),$delete_data_warning))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>';
    
    // Send the product id through the browser url.
    $output_product_id_to_url = '&product_id=' . h(escape_javascript(urlencode($_GET['product_id'])));

// Else if there is a product_group_id, this is a variant set's form template
// (2026.4). One form drawn here, written to every product in the set.
} elseif ((isset($_GET['product_group_id'])) && ($_GET['product_group_id'] != '')) {
    validate_ecommerce_access($user);

    $form_type = 'product_group';
    $form_type_name = 'product form';
    $form_type_identifier_id = 'product_group_id';

    // form_type is pinned as well as the id. The copies generated for each
    // product carry the same product_group_id, so filtering on that column
    // alone would list the template plus one copy per variant.
    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_GET[$form_type_identifier_id]) . "'"
        . " AND form_fields.form_type = 'product_group'";

    $product_group = db_items(
        "SELECT name, short_description, form_name
        FROM product_groups
        WHERE id = '" . e($_GET['product_group_id']) . "'
        LIMIT 1");

    $product_group = $product_group ? $product_group[0] : array('name' => '', 'short_description' => '', 'form_name' => '');

    $form_name = $product_group['form_name'];

    if (($form_name == '') && ($product_group['short_description'] != '')) {
        $form_name = $product_group['short_description'];
    } elseif ($form_name == '') {
        $form_name = $product_group['name'];
    }

    $variant_count = (int) db_value(
        "SELECT COUNT(*)
        FROM products_groups_xref
        WHERE product_group = '" . e($_GET['product_group_id']) . "'");

    $output_form_designer_subnav_heading = h($product_group['name']);

    // The count is the point of this screen: what you draw here lands on every
    // one of those products.
    $output_form_designer_subnav_subheading = '
    <p class="p-0 m-0">' . lang('Form Name') . ': ' . h($form_name) . '</p>
    <p class="p-0 m-0">' . lang(array(
        'string' => 'Applied to {var:1} variant{suffix:1}',
        'vars'   => array($variant_count),
        'suffix' => ($variant_count === 1) ? '' : 's')) . '</p>';

    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products2.php">' . lang('Variant Sets') . '</a></li>';
    $output_breadcrumb_second_level_item = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id'])) . '">' . h($product_group['name']) . '</a></li>';

    $pg_breadcrumb_parent_items = array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets'),
        array('label' => $product_group['name'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id']))),
    );

    $output_form_designer_content_heading = lang('Edit Product Form');
    $output_form_designer_content_subheading = lang('Add fields to this product form.');
    $output_cancel_onclick = 'window.location.href=\'edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id'])) . '\'';

    // No preview button: a template belongs to no single product, and
    // preview_form.php renders one product's form.
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang('WARNING: The selected field(s) will be permanently deleted.') . '"><span class="bi bi-trash me-2"></span>' . lang('Delete Selected') . '</button>';

    $output_product_id_to_url = '&product_group_id=' . h(escape_javascript(urlencode($_GET['product_group_id'])));
}

// get fields
$query = "SELECT
            form_fields.id,
            form_fields.name,
            form_fields.rss_field,
            form_fields.label,
            form_fields.information,
            form_fields.type,
            form_fields.required,
            form_fields.office_use_only,
            user.user_username as username,
            form_fields.timestamp
         FROM form_fields
         LEFT JOIN user ON form_fields.user = user.user_id
         WHERE $form_type_filter
         ORDER BY form_fields.sort_order";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$number_of_results = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $name = $row['name'];
    $rss_field = $row['rss_field'];
    $type = get_field_type_name($row['type']);
    $office_use_only = '';
    
    if ($row['type'] == 'information') {
        $label_or_information = $row['information'];
    } else {
        $label_or_information = $row['label'];
    }
    
    if ($row['required'] == 1) {
        $required = '<span class="material-icons">task_alt</span>';
    } else {
        $required = '';
    }
    
    $office_use_only = '';
    $output_rss_field_cell = '';
    
    $username = $row['username'];
    $timestamp = $row['timestamp'];
    
    $output_link_url = 'edit_field.php?id=' . $id . $output_product_id_to_url . '&send_to=' . h(escape_javascript(urlencode($_GET['send_to'])));
    
    // if this is a custom form
    if ($form_type == 'custom') {
        $output_rss_field_cell = '<td>' . h($rss_field) . '</td>';
        
        // if office_use_only is set to 1, output * in the cell
        if ($row['office_use_only'] == 1) {
            $office_use_only = '<td class="align-middle text-center"><span class="material-icons">task_alt</span></td>';
        } else {
            $office_use_only = '<td class="align-middle text-center"></td>';
        }
    }
    
    $number_of_results++;
    
    $output_rows .=
        '<tr> 
            <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="fields[]" value="' . $id . '" class="checkbox" /></td>
			<td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="chart_label">' . h($name) . '</td>
            <td title="' . h($label_or_information) . '"><span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;">' . $label_or_information . '</span></td>
            ' . $output_rss_field_cell . '
            <td>' . h($type) . '</td>
            <td class="align-middle text-center">' . $required . '</td>
            ' . $office_use_only . '
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($username) ) ) ) . '</td>
        </tr>';
}

$liveform = new liveform('view_fields');

echo
pg_page_shell(array(
    'cancel'=>array('enable'=>true,'onclick'=>$output_cancel_onclick),
    'breadcrumb' => array_merge(
        $pg_breadcrumb_parent_items,
        array(array('label' => $output_form_designer_content_heading))
    ),
)) . '
    <div class="row">
        ' . $liveform->get_messages() . '
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . $output_form_designer_content_subheading . '" title="' . $output_form_designer_content_heading . '">[' . $output_form_designer_subnav_heading . ']</h2>
                    ' . $output_form_designer_subnav_subheading . '
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_field.php?' . $form_type_identifier_id . '=' . h(urlencode($_GET[$form_type_identifier_id])) . '&amp;form_type=' . $form_type . '&amp;send_to=' . h(urlencode($_GET['send_to'])) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        ' . $output_layout_buttons . '
                        ' . $output_preview_button . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <form name="form"  action="delete_fields.php" method="post"  class="disable_shortcut"> 
                        ' . get_token_field() . '
                        <input type="hidden" name="send_to" value="' . h($_GET['send_to']) . '">
                        <input type="hidden" name="' . h($form_type_identifier_id) . '" id="' . h($form_type_identifier_id) . '" value="' . h($_GET[$form_type_identifier_id]) . '">
                        <input type="hidden" name="form_type" value="' . $form_type . '">
                        <table class="chart table-hover table " style="width:100%;display:none">
                           <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . lang('Name') . '</th>
                                    <th>' . lang('Label / Information') . '</th>
                                    ' . $output_rss_table_heading . '
                                    <th>' . lang('Field Type') . '</th>
                                    <th class="text-center">' . lang('Required') . '</th>
                                    ' . $output_office_use_only_table_heading . '
                                    <th>' . lang('Last Modified') . '</th>
                                </tr>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    ' . $output_form_designer_footer . '
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
' . output_footer();

$liveform->remove_form();

function get_field_type_name($field_type)
{
    switch ($field_type) {
        case 'text box':
            return lang('Text Box');
            break;
            
        case 'text area':
            return lang('Text Area');
            break;
        
        case 'pick list':
            return lang('Pick List');
            break;
        
        case 'radio button':
            return lang('Radio Button');
            break;
        
        case 'check box':
            return lang('Check Box');
            break;
            
        case 'file upload':
            return lang('File Upload');
            break;
            
        case 'date':
            return lang('Date');
            break;
            
        case 'date and time':
            return lang('Date & Time');
            break;
            
        case 'email address':
            return lang('E-mail Address');
            break;
        
        case 'information':
            return lang('Information');
            break;
            
        case 'time':
            return lang('Time');
            break;
    }
}
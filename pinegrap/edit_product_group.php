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
require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('edit_product_group');

// if the form has not been submitted
if (!$_POST) {
    // get product group information from database
    $query = 
        "SELECT 
            name,
            enabled,
            parent_id,
            short_description,
            full_description,
            details,
            code,
            keywords,
            image_name,
            display_type,
            address_name,
            title,
            meta_description,
            meta_keywords,
            seo_score,
            " . (pg_seo_schema_ready() ? "seo_flags, seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
            seo_analysis,
            seo_analysis_current,
            attributes
        FROM product_groups
        WHERE id = '" . e($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    
    $name = h($row['name']);
    $enabled = $row['enabled'];
    $parent_id = $row['parent_id'];
    $short_description = h($row['short_description']);
    $full_description = h(prepare_rich_text_editor_content_for_output($row['full_description']));
    $details = h(prepare_rich_text_editor_content_for_output($row['details']));
    $code = $row['code'];
    $keywords = h($row['keywords']);
    $image_name = h($row['image_name']);
    $display_type = $row['display_type'];
    $address_name = $row['address_name'];
    $title = $row['title'];
    $meta_description = $row['meta_description'];
    $meta_keywords = $row['meta_keywords'];
    $seo_score = $row['seo_score'];
    // seo_checked_at as well: pg_seo_row_scored() asks it first, and without
    // it the panel reads "not calculated yet" after every save.
    $seo_row = array(
        'seo_score' => $row['seo_score'],
        'seo_flags' => $row['seo_flags'],
        'seo_checked_at' => $row['seo_checked_at'],
        'seo_analysis' => $row['seo_analysis'],
        'seo_analysis_current' => $row['seo_analysis_current'],
    );
    $enable_attributes = $row['attributes'];

    $enabled_checked = '';

    if ($enabled) {
        $enabled_checked = ' checked="checked"';
    }
    
    // if display type is set to browse, then select that radio button and prepare to hide rows that only apply to select product groups
    if ($display_type == 'browse') {
        $display_type_browse_checked = ' checked="checked"';
        $display_type_select_checked = '';
        
    // else, display type is select, so select that radio button and prepare to show rows that only apply to select product groups
    } else {
        $display_type_select_checked = ' checked="checked"';
        $display_type_browse_checked = '';
    }
    
    $output_display_type_options =
        '<div class="form-check  form-check-inline" title="' . lang('Display contents for browsing on catalog page') . '">
            <input class="form-check-input collapse-switcher"  type="radio" id="browse" name="display_type" value="browse" ' . $display_type_browse_checked . '/>
            <label for="browse">' . lang('Browse') . '</label>
        </div>
        <div class="form-check  form-check-inline" title="' . lang('Display contents for selection on catalog detail page') . '">
            <input class="form-check-input collapse-switcher"  type="radio" id="select" name="display_type" value="select" ' . $display_type_select_checked . ' data-bs-target="#display_type_select_row"/>
            <label for="select">' . lang('Select') . '</label>
        </div>';
    
    $items = array();
    $selected_products = array();

    // Determine whether to show product images based on config setting (default: show).
    $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;
    $output_image_header = $show_image ? '<th>' . lang('Image') . '</th>' : '';

    // initialize arrays that will store data for sorting
    $item_sort_orders = array();
    $item_names = array();
    
    // get all product groups currently in this product group
    $query = 
        "SELECT
            id,
            name,
            enabled,
            short_description,
            image_name,
            sort_order
        FROM product_groups
        WHERE
            parent_id = '" . e($_GET['id']) . "'";
    
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    while ($row = mysqli_fetch_assoc($result)) {
        $row['type'] = 'product_group';
        $items[] = $row;
        
        $item_sort_orders[] = $row['sort_order'];
        $item_names[] = mb_strtolower($row['name']);
    }
    
    // if there are product groups inside of this product group
    if (count($items) > 0) {
        $child_product_group_exists = true;
    } else {
        $child_product_group_exists = false;
    }
    
    // get all products currently in this product group
    $query = 
        "SELECT
            product as id,
            sort_order,
            products.name,
            products.enabled,
            products.short_description,
            products.image_name as image_name,
            products.price
        FROM products_groups_xref
        LEFT JOIN products on products.id = product
        WHERE
            product_group = '" . e($_GET['id']) . "'";

    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    while ($row = mysqli_fetch_assoc($result)) {
        $row['price'] = $row['price'] / 100;
        $row['type'] = 'product';
        
        $selected_products[] = $row['id'];
        $items[] = $row;
        
        $item_sort_orders[] = $row['sort_order'];
        $item_names[] = mb_strtolower($row['name']);
    }

    // sort the items by sort order and then by name
    array_multisort($item_sort_orders, $item_names, $items);

    foreach ($items as $item) {
        // if this is a product
        if ($item['type'] == "product") {
            $output_checkbox = '<input class="form-check-input " type="checkbox" name="products[]" checked="checked" value="' . $item['id'] . '"  />';
            $output_product_group_class = '';
            $price = prepare_amount($item['price']);
            $id_type = 'product_id';
            $output_url = 'edit_product.php?id=' . $item['id'];
            
        // else, this is a product_group
        } else {
            $output_checkbox = '<span class="material-icons disabled-checkbox">check_box</span>';
            $output_product_group_class = 'unselectable selected';
            $price = '';
            $id_type = 'product_group_id';
            $output_url = 'edit_product_group.php?id=' . $item['id'];
        }

        // If this item is enabled, then use green color for name and short description.
        if ($item['enabled'] == 1) {
            $status_class = 'text-success';
        
        // Otherwise this item is disabled, so use red color for name and short description.
        } else {
            $status_class = 'text-danger';
        }
        $output_image_column = '';
        if ($show_image) {
            if (!$item['image_name']) {
                $output_image_column = '<td class="align-middle text-start"><svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string'=>'No Image') ) . '</text></svg></td>';
            } else {
                $output_image_column = '<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH . $item['image_name'] . '" /></td>';
            }
        }
       
        $output_rows .=
            '<tr id="' . $item['id'] . '" class="' . $output_product_group_class . '">
                <td class="select-all align-middle text-start">' . $output_checkbox . '</td>
			    <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                ' . $output_image_column . '
                <td class="chart_label  align-middle ' . $status_class . '">' . h($item['name']) . '</td>
                <td class="align-middle ' . $status_class . '">' . h($item['short_description']) . '</td>
                <td class="align-middle text-end">' . $price . '</td>
                <td class="align-middle "><input class="form-control text-end" type="text" name="sort_order_' . $item['type'] . '_' . $item['id'] . '" size="5" value="' . $item['sort_order'] . '" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;width:60px;" /></td>
                <td></td>
            </tr>';
    }
    
    // Build the exclusion clause once (reused for count and initial batch).
    $unselected_exclude_sql = $selected_products
        ? " WHERE id NOT IN (" . implode(',', array_map('intval', $selected_products)) . ")"
        : "";

    $total_unselected = (int) db_value("SELECT COUNT(*) FROM products" . $unselected_exclude_sql);

    // Preload first 20 unselected products so the table isn't sparse on load.
    $initial_unselected = db_items(
        "SELECT id, name, enabled, short_description, image_name, price
         FROM products $unselected_exclude_sql
         ORDER BY timestamp DESC
         LIMIT 20"
    );

    foreach ($initial_unselected as $urow) {
        $urow['price'] = $urow['price'] / 100;
        $ustatus_class = $urow['enabled'] == 1 ? 'text-success' : 'text-danger';
        $uoutput_image_column = '';
        if ($show_image) {
            if (!$urow['image_name']) {
                $uoutput_image_column = '<td class="align-middle text-start"><svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string'=>'No Image')) . '</text></svg></td>';
            } else {
                $uoutput_image_column = '<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . $urow['image_name'] . '" /></td>';
            }
        }
        $output_rows .=
            '<tr id="' . $urow['id'] . '">' .
            '<td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="products[]" value="' . $urow['id'] . '"/></td>' .
            '<td class="align-middle text-start"><button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product.php?id=' . $urow['id'] . '\'"><i class="bi bi-pencil"></i></button></td>' .
            $uoutput_image_column .
            '<td class="chart_label align-middle ' . $ustatus_class . '">' . h($urow['name']) . '</td>' .
            '<td class="align-middle ' . $ustatus_class . '">' . h($urow['short_description']) . '</td>' .
            '<td class="align-middle text-end">' . prepare_amount($urow['price']) . '</td>' .
            '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $urow['id'] . '" size="5" value="" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;width:60px;" /></td>' .
            '<td></td>' .
            '</tr>';
    }

    $initial_unselected_count = count($initial_unselected);

    // Get product images from xref.
    $query = "SELECT product_group,file_name FROM product_groups_images_xref WHERE product_group = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $xref_image_names = '';
    $output_xref_image_names ='';
    if (mysqli_num_rows($result) != 0){
        $xref_image_names = array();

        while ($row = mysqli_fetch_assoc($result)){
            $xref_image_names[]= $row['file_name'];
        }
        foreach($xref_image_names as $xref_image_name) {
            $output_xref_images .= '
            <div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button"  class="btn btn-link link-danger bi bi-x-lg p-0" title="remove" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                    <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' . $xref_image_name . '">
                        <input type="hidden" name="selected_images[]" value="' . $xref_image_name . '"/>
                        <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . $xref_image_name . '" />
                    </div>
                </div>
            </div>';
        }
    }

    $output_thumbnail ='';
    if($image_name == true){
        $output_thumbnail ='<div class="col-12 col-md-auto"><a href="' . OUTPUT_PATH . $image_name . '" target="_blank"><img style="width: 100px;height:100px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . OUTPUT_PATH . $image_name . '" /></a></div>';
        $output_selected_image = '
        <div class="item col">
            <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button"  class="btn btn-link link-danger bi bi-x-lg p-0" title="remove" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' . $image_name . '">
                    <input type="hidden" name="selected_images[]" value="' . $image_name . '"/>
                    <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . $image_name . '" />
                </div>
            </div>
        </div>';

    }

    $output_selected_images =  $output_selected_image . $output_xref_images;

    $output_attributes = '';

    // If there is at least one attribute in the system, then output area for them.
    if (db_value("SELECT COUNT(*) FROM product_attributes")) {
       
        if ($enable_attributes == 1) {
            $output_enable_attributes_checked = ' checked="checked"';
        }

        $attributes = array();

        // If this product group contains at least one product, then continue
        // to get attributes for those products.
        if ($selected_products) {
            $sql_products = "";

            // Loop through the products in this product group in order to prepare
            // SQL for getting attributes for those products.
            foreach ($selected_products as $product_id) {
                if ($sql_products != '') {
                    $sql_products .= " OR ";
                }

                $sql_products .= "(product_id = '$product_id')";
            }

            // Get all attributes for products in this product group.
            $attributes_without_sort_order = db_items(
                "SELECT
                    DISTINCT(attribute_id) AS id,
                    product_attributes.name
                FROM products_attributes_xref
                LEFT JOIN product_attributes ON products_attributes_xref.attribute_id = product_attributes.id
                WHERE $sql_products");

            // If at least one attribute was found, then continue to prepare
            // attributes and order them.
            if ($attributes_without_sort_order) {
                // Get sort orders for attributes for this product group.
                $sort_orders = db_items(
                    "SELECT
                        attribute_id AS id,
                        default_option_id,
                        sort_order
                    FROM product_groups_attributes_xref
                    WHERE product_group_id = '" . e($_GET['id']) . "'", 'id');

                // Get all options for products in this product group,
                // so that we know whether we should include an option
                // for an attribute or not.
                $product_options = db_values(
                    "SELECT DISTINCT(option_id)
                    FROM products_attributes_xref
                    WHERE $sql_products");

                // Loop through the raw attributes array in order to prepare
                // new attributes array that we can use to sort the attributes.
                foreach ($attributes_without_sort_order as $attribute) {
                    // If there is a sort order for this attribute, then set it.
                    if ($sort_orders[$attribute['id']]) {
                        $sort_order = $sort_orders[$attribute['id']]['sort_order'];

                    } else {
                        $sort_order = 9999999;
                    }

                    // Get all the options for this attribute, so we can include them in array.
                    $options = db_items(
                        "SELECT
                            id,
                            label,
                            no_value
                        FROM product_attribute_options
                        WHERE product_attribute_id = '" . $attribute['id'] . "'
                        ORDER BY sort_order");

                    // Loop through the options in order to remove ones that are
                    // not in use by a product in this product group.
                    foreach ($options as $key => $option) {
                        // Assume that the option is not valid, until we find out otherwise.
                        // An option is valid if it matches at least one product.
                        $option_valid = false;

                        // If there is product that has this option,
                        // or if this is a "no thanks" option,
                        // and there is at least one product that would match
                        // this "no thanks" option, then the option is valid.
                        if (
                            (in_array($option['id'], $product_options) == true)
                            ||
                            (
                                ($option['no_value'] == 1)
                                &&
                                (
                                    db_value(
                                        "SELECT products.id
                                        FROM products_groups_xref
                                        LEFT JOIN products ON products_groups_xref.product = products.id
                                        LEFT JOIN products_attributes_xref
                                            ON
                                                (products_groups_xref.product = products_attributes_xref.product_id)
                                                AND (products_attributes_xref.attribute_id = '" . e($attribute['id']) . "')
                                        WHERE
                                            (products_groups_xref.product_group = '" . e($_GET['id']) . "')
                                            AND (products.enabled = '1')
                                            AND (products_attributes_xref.product_id IS NULL)
                                        LIMIT 1")
                                )
                            )
                        ) {
                            $option_valid = true;
                        }

                        // If the option is not valid, then remove it.
                        if ($option_valid == false) {
                            unset($options[$key]);
                        }
                    }

                    $attribute = array('sort_order' => $sort_order) + $attribute;

                    $attribute['options'] = array_values($options);
                    $attribute['default_option_id'] = $sort_orders[$attribute['id']]['default_option_id'];

                    $attributes[] = $attribute;
                }

                sort($attributes);
            }
        }

        // If there was at least one attribute found for a product,
        // then init js to allow attributes to be ordered.
        if ($attributes) {
            $output_attribute_management =
                '<label class="w-100 text-end">' . lang('Sorting') . '</label>
                <div class="attributes"></div>

                <script>
                    init_product_group_attributes({
                        attributes: ' . encode_json($attributes) . ',
                        labels:{
                            "Default Option":"' . lang('Default Option') . '",
                            "None":"' . lang('None') . '",
                            "Move Up":"' . lang('Move Up') . '",
                            "Move Down":"' . lang('Move Down') . '",
                            "Remove":"' . lang('Remove') . '"
                        }
                    });
                </script>';

        // Otherwise no attributes were found for products in this product group,
        // so output message to user that explains that.
        } else {
            $output_attribute_management =
                '<div class="attributes alert alert-warning">
                    ' . lang('No Attributes were found for Products in this Product Group.  Once you assign Attributes to Products and include those Products in this Product Group, then you may manage those Attributes here.') . '
                </div>';
        }

        $output_attributes =
            '<div class="col-12 mt-3 mb-2">
                <h6 class="text-muted">' . lang('Attributes') . '</h6>
                <input type="hidden" name="enable_attributes_exists" value="true">
                <div class="form-check form-switch">
                    <input value="1" ' . $output_enable_attributes_checked . ' id="enable_attributes" name="enable_attributes" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#enable_attributes_row" />
                    <label class="form-check-label" for="enable_attributes">' . lang('Enable Attributes') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="enable_attributes_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(51px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            ' . $output_attribute_management . '
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    // Show parent selector only when there are selectable groups (excluding self and descendants).
    $_parent_options_html = get_product_group_options($parent_id, 0, $_GET['id'], 0, array(), FALSE);
    if ($_parent_options_html !== '') {
        $output_parent_product_group_row =
            '<div class="col-12 col-md-4 my-2">
                <label for="parent_id" class="form-label">' . lang('Parent Product Group') . '</label>
                <select style="width:100%" class="select2 form-select" id="parent_id" name="parent_id" data-placeholder="' . lang('Select Parent Group') . '" data-allow-clear="true"><option value=""></option>' . $_parent_options_html . '</select>
            </div>';
    } else {
        $output_parent_product_group_row = '';
    }

    // Delete button: only allowed for non-root product groups.
    if ($parent_id != 0) {
        // if there is a child product group in this product group, then disable delete button
        if ($child_product_group_exists == true) {
            $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " onclick="alert(\'' . lang('Please delete all product groups in this product group before deleting this product group.') . '\')" ><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
        // else there is not a child object in this product group, so allow delete
        } else {
            $output_delete_button = '
            <button type="submit" name="submit_button" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('product group')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
        }
    } else {
        $output_delete_button = '';
    }
    
    echo
    pg_page_shell([
        'title'=> lang('Edit Product Group'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Product Group'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_groups.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php'), array('label' => lang('Edit Product Group'))),
        ]) . '
            ' . get_wysiwyg_editor_code(array('full_description', 'details')) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<div class="row mb-2">
                            ' . $output_thumbnail . '
                            <div class="col-12 col-md">
                                <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit a product group and assign products to them.') . '" title="' . lang('Edit Product Group') . '">[' . $name . ']</h2>
                            </div>
                        </div>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Duplicating') . '" href="duplicate_product_group.php?id=' . h($_GET['id']) . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_product_group.php" method="post" class="product_group_form">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />

                    <div class="row edit_product_group_container">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Product Group Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="name" class="form-label">' . lang('Product Group Name') . '</label>
                                            <input type="text" name="name" id="name" class="form-control add-header-content-updater" value="' . $name . '"/>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"' . $enabled_checked . ' />
                                                <label class="form-check-label" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Catalog Page Display Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_parent_product_group_row . '
                                        <div class="col-12 col-md-8 my-2">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            <input type="text" name="short_description" placeholder="' . lang('Short Description') . '" id="short_description" maxlength="100" class="form-control" value="' . $short_description . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            <textarea id="full_description" name="full_description">' . $full_description . '</textarea>
                                        </div>

                                        <div class="col-12 my-3">
                                            <div class="col-12">
                                                <label class="form-label">' . lang('Display Type') . '</label>
                                            </div>
                                            ' . $output_display_type_options . '
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="display_type_select_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-2">
                                                            <div class="alert alert-primary">' . lang('Product group behaves like a product and provides the ability to select options in product detail page.') . '</div>
                                                        </div>
                                                        <div class="col-12 mt-1 mb-2">
                                                            <label for="details" class="form-label">' . lang('Details') . '</label>
                                                            <textarea id="details" name="details">' . $details . '</textarea>
                                                        </div>
                                                        <div class="col-12 mt-3 mb-2">
                                                            <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                                            <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255" value="' . $keywords . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                                    tagin( document.querySelector("#keywords") );
                                                                }
                                                            </script>
                                                        </div>
                                                        ' . $output_attributes . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Image Options') . ' 
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-3">
                                            <div id="software_image_picker_container" ondblclick="software_image_picker({initialize:true});" class="user-select-none sortable-list img-list bg-body-tertiary rounded p-2 row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4">' . $output_selected_images . '</div>
                                            <button type="button" class="btn btn-primary my-3 me-2" onclick="software_image_picker({initialize:true});" ><span class="bi bi-plus-circle me-2"></span>' . lang('Add Image') . '</button>
                                            <button type="button" class="btn " data-bs-toggle="modal" data-bs-target="#image_code"><span class="material-icons me-2">code</span>' . lang('Code') . '</button>

                                            <div class="modal fade" id="image_code" tabindex="-1" aria-labelledby="image_code" aria-hidden="true">
                                                <div class="modal-dialog modal-lg ">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">' . lang('Code') . '</h5>
                                                            <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body "> 
                                                            <div class="row">
                                                                <div class="col-12 my-2">
                                                                <div class="alert alert-primary">' . lang('Tags') . ':<span title="' . lang('Loop start') . '">^^image_loop_start^^</span>, <span title="' . lang('Short Description') . '">^^image_alt^^</span>, <span title="' . lang('Image Url') . '">^^image_url^^</span>, <span title="' . lang('Loop End') . '">^^image_loop_end^^</span></div>
                                                                </div>
                                                                <div class="col-12 my-2">
                                                                    <textarea id="code" name="code">' . h($code) . '</textarea>
                                                                    ' . get_codemirror_includes() . '
                                                                    ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <script>
                                                $(document).ready(function() {
                                                    $(".sortable-list").sortable({
                                                        items: "> div:not(.add_new_item)",
                                                        placeholder: "col",
                                                        handle: ".card .card-body",
                                                        revert: "100",
                                                        cursorAt: { left: 1 },
                                                        animation: 150,
                                                        forcePlaceholderSize: false,
                                                        forceHelperSize: true,
                                                        swapThreshold: 1,
                                                        tolerance: "pointer",
                                                        zIndex: 9999,
                                                        cursor: "move",
                                                        cancel: ".no-drag"
                                                    });
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('SEO') . '<span class="float-end">' . pg_seo_render_badge($seo_row) . '</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                            <div class="input-group ">
                                                <label for="address_name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Catalog Name') . '}">public</label>
                                                <input type="text" name="address_name" id="address_name" class="form-control" value="' . h($address_name) . '" />
                                            </div>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" value="' . h($title) . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" >' . h($meta_description) . '</textarea>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_keywords" class="form-label">' . lang('Web Browser Keywords') . '</label>
                                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255" value="' . $meta_keywords . '"/>
                                            <script>
                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                    tagin(document.querySelector("#meta_keywords"));
                                                }
                                            </script>
                                        </div>
                                        <div class="col-12">
                                            ' . pg_seo_render_checklist($seo_row, 'product_group', (int) $_GET['id']) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4 edit_products">
                                <div class="card-header bg-reset text-uppercase text-primary h5 fw-bold">' . lang('Products and Child Product Groups to Include') . '</div>                                <div class="card-body p-0 position-relative">
                                    <table id="product_group_products_table" class="chart table-hover table datatable-restricted-mode" style="width:100%;display:none">
                                        <thead>
                                            <tr>
                                                <th class="noVis">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                                    </div>
                                                </th>
                                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                                ' . $output_image_header . '
                                                <th>' . lang('Name') . '</th>
                                                <th>' . lang('Short Description') . '</th>
                                                <th class="text-end">' . lang('Price') . '</th>
                                                <th>' . lang('Sorting') . '</th>
                                                <th class="noVis"></th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $output_rows . '</tbody>
                                    </table>
                                </div>
                                ' . ($total_unselected > $initial_unselected_count ? '
                                <div class="p-3 border-top">
                                    <button id="btn_load_more_products" type="button" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-arrow-down-circle me-2"></i>' . lang('Load More Products') . '
                                    </button>
                                </div>' : '') . '
                            </div>
                        </div>
                        <script>
                        (function() {
                            var loadedOffset = ' . $initial_unselected_count . ';
                            var loadMoreBusy = false;
                            var productGroupId = ' . (int)$_GET['id'] . ';
                            var showImage = ' . ($show_image ? 'true' : 'false') . ';
                            var totalUnselected = ' . $total_unselected . ';

                            $("#btn_load_more_products").on("click", function() {
                                if (loadMoreBusy) return;
                                loadMoreBusy = true;
                                var $btn = $(this);
                                $btn.prop("disabled", true).find("i").removeClass("bi-arrow-down-circle").addClass("bi-hourglass-split");

                                $.ajax({
                                    url: "api.php",
                                    type: "POST",
                                    contentType: "application/json",
                                    dataType: "json",
                                    data: JSON.stringify({
                                        action: "get_unselected_products",
                                        product_group_id: productGroupId,
                                        offset: loadedOffset
                                    }),
                                    success: function(response) {
                                        if (response.status === "success" && response.rows) {
                                            var $rows = $(response.rows);
                                            var dt = $("#product_group_products_table").DataTable();
                                            $rows.each(function() {
                                                dt.row.add(this);
                                            });
                                            dt.draw(false);

                                            // Immediately resolve lazy images in loaded rows (they are on-demand anyway).
                                            $rows.find("img.lazy").each(function() {
                                                var src = $(this).data("src");
                                                if (src) {
                                                    $(this).attr("src", src).removeClass("lazy");
                                                }
                                            });

                                            loadedOffset += response.loaded;
                                            var remaining = totalUnselected - loadedOffset;
                                            if (!response.has_more) {
                                                $btn.closest(".border-top").hide();
                                            } else {
                                                $btn.prop("disabled", false).find("i").removeClass("bi-hourglass-split").addClass("bi-arrow-down-circle");
                                            }
                                        }
                                        loadMoreBusy = false;
                                    },
                                    error: function() {
                                        loadMoreBusy = false;
                                        $btn.prop("disabled", false).find("i").removeClass("bi-hourglass-split").addClass("bi-arrow-down-circle");
                                    }
                                });
                            });
                        })();
                        </script>
                    </div>
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_button" name="submit_button" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                            <span class="enable-on-selected"> 
                                <button type="button" value="Modify Selected" title="' . lang('Modify Selected Products') . '" data-bs-content="' . lang('All selected products will be modified. However, the changes you made on this page will be removed. If you have made any changes, save first.') . '" class="btn btn-sm my-1 btn-primary p-2 disabled material-icons" onclick="convert_product_group_to_product_form(\'edit_products.php\')">living</button>
                            </span>
                        </div>
                    </nav>
                    <input type="hidden" name="max_input_vars_test" value="true">
                    
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_enabled">
                    <input type="hidden" name="edit_allowed_zones">
                    <input type="hidden" name="edit_disallowed_zones">
                    <input type="hidden" name="edit_change_price_method">
                    <input type="hidden" name="edit_price_value">
                    <input type="hidden" name="edit_inventory">
                    <input type="hidden" name="edit_inventory_quantity_process">
                    <input type="hidden" name="edit_inventory_quantity">
                </form>
                <script>
                    function convert_product_group_to_product_form(action){
                        var form = $("form.product_group_form");
                        var default_action = form.attr("action");
                        pgConfirm({
                            title: "' . lang('Warning') . '",
                            message: "' . lang('Warning! If you have made changes in this product group, these changes will disappear and you will be directed to the View Products page when you edit the products. Are you sure about this action?') . '",
                            confirmText: "' . lang('Continue') . '",
                            cancelText: "' . lang('Cancel') . '",
                            variant: "warning"
                        }).then(function(ok){
                            if (ok) {
                                form.attr("action", action);
                                window.open("edit_products.php", "popup", "toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500");
                                form.find(".edit_product_group_container .card:not(.edit_products)").remove();
                                $("html").animate({ scrollTop: 0 }, "slow");
                            } else {
                                form.attr("action", default_action);
                            }
                        });
                    }
                </script>
            </div>
        </div>
    </main>' .
        output_footer();
        
        $liveform->remove_form();

} else {
    validate_token_field();
    

    // If the max_input_vars_test hidden field is not in the post data then that means the post data was truncated,
    // so output error. This can happen because of max_input_vars (i.e. php.ini
    // setting added in PHP v5.3.9 and often backported to earlier versions).
    // This can happen when there is a large number of products (e.g. 1,000+).
    // The default value for max_input_vars is 1,000.
    if (isset($_POST['max_input_vars_test']) == FALSE) {
        output_error('Sorry, the server did not accept the form that you submitted. We recommend that you ask the server administrator to check the max_input_vars PHP setting in the php.ini file.  We recommend that it be set to a number that is at least double the number of Products that the site will contain. <a href="javascript:history.go(-1)">Go back</a>.');
    }
    
    // get parent_id for product group
    $query = 
        "SELECT 
            parent_id
        FROM product_groups
        WHERE id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $parent_id = $row['parent_id'];

    // Delete product group images references (we do this for both delete and update).
    db("DELETE FROM product_groups_images_xref WHERE product_group = '" . escape($_POST['id'] ?? '') . "'");

    // if product group was selected for delete
    if (($_POST['submit_button'] ?? '') == 'Delete') {
        // if the parent_id is not set to 0, allow user to delete the product group because it is not the root product group
        if ($parent_id != '0') {
            $new_product_ids = array();
            
            $search_results_pages = array();
            
            // get data from all search result pages that have "search products" enabled
            $query = "SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            while($row = mysqli_fetch_assoc($result)) {
                $search_results_pages[] = $row;
            }
            
            $search_results_pages_using_current_product_group = array();
            
            // loop through each search result page to get the search results page ids that uses this product group
            foreach ($search_results_pages as $search_results_page) {
                $search_results_page_product_groups = array();
                
                // get the product groups inside of this product group
                $search_results_page_product_groups = get_product_groups_in_product_group_tree($search_results_page['product_group_id']);
                
                // loop through the product groups to see if any of them match this one, and if there is a match then add it to the array
                foreach ($search_results_page_product_groups as $product_group) {
                    if ($product_group['id'] == $_POST['id']) {
                        $search_results_pages_using_current_product_group[] = $search_results_page;
                    }
                }
            }
            
            // loop through the search result pages for this product group and delete it's tag cloud
            foreach ($search_results_pages_using_current_product_group as $search_results_page) {
                delete_tag_cloud_keywords_for_search_results_page($search_results_page['page_id']);
            }
            
            // delete product group
            $query = "DELETE FROM product_groups ".
                     "WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            
            // delete all entries in products_groups_xref table
            $query = "DELETE FROM products_groups_xref ".
                     "WHERE product_group = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            
            // loop through the search result pages for this product group and re-build it's tag cloud
            foreach ($search_results_pages_using_current_product_group as $search_results_page) {
                update_tag_cloud_keywords_for_search_results_page_product_group($search_results_page['page_id'], $search_results_page['product_group_id']);
            }

            // Check if this product group has short links, in order to determine if we need to delete them and update rewrite file.
            $query =
                "SELECT COUNT(*)
                FROM short_links
                WHERE
                    (destination_type = 'product_group')
                    AND (product_group_id = '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);

            // If a short link exists, then delete them and update short links in rewrite file.
            if ($row[0] != 0) {
                $query =
                    "DELETE FROM short_links
                    WHERE
                        (destination_type = 'product_group')
                        AND (product_group_id = '" . escape($_POST['id'] ?? '') . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }

            db("DELETE FROM product_groups_attributes_xref WHERE product_group_id = '" . e($_POST['id'] ?? '') . "'");
            
            log_activity("product group ($_POST[name]) was deleted", $_SESSION['sessionusername']);

        // else, output an error stating that the product group cannot be deleted because it is the root product group
        } else {
            $liveform->mark_error('', lang('This product group could not be deleted because it is the root product group.'));
            
            // forward user to view product groups page
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . $_POST['id']);
            exit();
        }
    // else product group was not selected for delete
    } else {
        // if the name is blank, then mark error and forward user back to previous screen
        if ($_POST['name'] == '') {
            $liveform->mark_error('name', lang('Name is required.'));
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . $_POST['id']);
            exit();
        }
        
        // If parent_id is blank, require it when selectable groups exist
        if (!$_POST['parent_id']) {
            $available_parent_options = get_product_group_options(0, 0, $_POST['id'], 0, array(), FALSE);
            if ($available_parent_options !== '') {
                $liveform->mark_error('parent_id', lang(array('string' => '{var:1} is required', 'vars' => array(lang('Parent Product Group')))) . '.');
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . $_POST['id']);
                exit();
            }
            $_POST['parent_id'] = 0;
        }
        
        // assume that we will not update the display type until we find out otherwise
        $sql_display_type = "";
        
        // Update display type if the group is not (or will not be) root-level
        if ($_POST['parent_id'] != 0) {
            $sql_display_type = "display_type = '" . escape($_POST['display_type'] ?? '') . "',";
        }
        
        // if the address name is NOT blank then use that value for the address name
        if ($_POST['address_name'] != '') {
            $address_name = $_POST['address_name'];
            
        // else if the short description is NOT blank then use that value
        } elseif ($_POST['short_description'] != '') {
            $address_name = $_POST['short_description'];
            
        // else use the name as the value
        } else {
            $address_name = $_POST['name'];
        }





        
        // prepare the address name for the database
        $address_name = prepare_catalog_item_address_name($address_name, $_POST['id']);
        
        // get current product group information
        $query =
            "SELECT
                title,
                meta_description,
                full_description,
                details,
                seo_analysis_current,
                address_name,
                keywords,
                short_description
            FROM product_groups
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $title = $row['title'];
        $meta_description = $row['meta_description'];
        $full_description = $row['full_description'];
        $details = $row['details'];
        $seo_analysis_current = $row['seo_analysis_current'];
        $current_address_name = $row['address_name'];
        $current_keywords = $row['keywords'];
        $current_short_description = $row['short_description'];
        
        $search_results_pages = array();
        
        // get data from all search result pages that have "search products" enabled
        $query = "SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        while($row = mysqli_fetch_assoc($result)) {
            $search_results_pages[] = $row;
        }
        
        $search_results_pages_using_current_product_group = array();
        $search_results_pages_using_parent_product_group = array();
        
        // loop through each search result page to get the search results page ids that uses this product group
        foreach ($search_results_pages as $search_results_page) {
            $search_results_page_product_groups = array();
            
            // get the product groups inside of this product group
            $search_results_page_product_groups = get_product_groups_in_product_group_tree($search_results_page['product_group_id']);
            
            // loop through the product groups to see if any of them match this one, and if there is a match then add it to the arrays
            foreach ($search_results_page_product_groups as $product_group) {
                if ($product_group['id'] == $_POST['id']) {
                    $search_results_pages_using_current_product_group[] = $search_results_page;
                
                } elseif ($product_group['id'] == $_POST['parent_id']) {
                    $search_results_pages_using_parent_product_group[] = $search_results_page;
                }
            }
        }
        
        // loop through the search result pages for this product group and delete it's tag cloud
        foreach ($search_results_pages_using_current_product_group as $search_results_page) {
            delete_tag_cloud_keywords_for_search_results_page($search_results_page['page_id']);
        }
        
        // loop through the search result pages for the parent product group and delete it's tag cloud
        foreach ($search_results_pages_using_parent_product_group as $search_results_page) {
            delete_tag_cloud_keywords_for_search_results_page($search_results_page['page_id']);
        }
        
        $sql_seo_analysis_current = "";
        
        // If the seo analysis is current and any field the SEO score reads
        // has changed, then prepare to clear the current status. The promoted
        // keywords feed the site search check and the short description has
        // its own check.
        if (
            ($seo_analysis_current == 1)
            &&
            (
                (trim($title) != trim($_POST['title']))
                || (trim($meta_description) != trim($_POST['meta_description']))
                || (trim($full_description) != trim(prepare_rich_text_editor_content_for_input($_POST['full_description'])))
                || (trim($details) != trim(prepare_rich_text_editor_content_for_input($_POST['details'])))
                || (trim($current_keywords) != trim($_POST['keywords'] ?? ''))
                || (trim($current_short_description) != trim($_POST['short_description'] ?? ''))
            )
        ) {
            $sql_seo_analysis_current = "seo_analysis_current = '0',";
        }

        $sql_enable_attributes = "";

        // If the enable attributes check box appeared on the form, then update value.
        if ($_POST['enable_attributes_exists'] == 'true') {
            $sql_enable_attributes = "attributes = '" . escape($_POST['enable_attributes'] ?? '') . "',";
        }

        // Before we update the product group, get the old status, so later
        // we know if the status changed and need to update status for children.
        $old_enabled = db_value(
            "SELECT enabled
            FROM product_groups
            WHERE id = '" . e($_POST['id'] ?? '') . "'");

        $selected_images = array();
        foreach ($_POST['selected_images'] as $selected_image ) {
            $selected_images[] = $selected_image ;
        }

        $selected_count = 0;
        foreach ($selected_images as $value) {
            $selected_count++;
        }
        if($selected_count >= 1){
            $selected_cover_image = reset($selected_images);
            array_shift($selected_images);
            if($selected_cover_image){
                $sql_imagename = 
                "image_name = '" . escape($selected_cover_image) . "',";
            }
        }else{
            $sql_imagename = 
            "image_name = '',";
        }

        // update product group
        db(
            "UPDATE product_groups SET
                name = '" . e($_POST['name'] ?? '') . "',
                enabled = '" . e($_POST['enabled'] ?? '') . "',
                parent_id = '" . e($_POST['parent_id'] ?? '') . "',
                short_description = '" . e($_POST['short_description'] ?? '') . "',
                full_description = '" . e(prepare_rich_text_editor_content_for_input($_POST['full_description'])) . "',
                details = '" . e(prepare_rich_text_editor_content_for_input($_POST['details'])) . "',
                code = '" . e($_POST['code'] ?? '') . "',
                keywords = '" . e($_POST['keywords'] ?? '') . "',
                $sql_imagename
                $sql_display_type
                address_name = '" . e($address_name) . "',
                title = '" . e($_POST['title'] ?? '') . "',
                meta_description = '" . e($_POST['meta_description'] ?? '') . "',
                meta_keywords = '" . e($_POST['meta_keywords'] ?? '') . "',
                $sql_seo_analysis_current
                $sql_enable_attributes
                user = '" . $user['id'] . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($_POST['id'] ?? '') . "'");
        
        // flush appropriate entries from products_groups_xref table
        $query = "DELETE FROM products_groups_xref WHERE product_group = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');

        if($selected_count > 1){
            foreach ($selected_images as $value) {db("INSERT INTO product_groups_images_xref (product_group,file_name)VALUES ('" . escape($_POST['id'] ?? '') . "','" . escape($value) . "')");}
        }


       
        // if at least one product was selected then proceed with update to products_groups_xref table
        if ($_POST['products']) {
            // foreach product that was selected
            foreach ($_POST['products'] as $product_id) {
                $query = "INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES ('" . escape($product_id) . "', '" . escape($_POST['id'] ?? '') . "', '" . escape($_POST['sort_order_product_' . $product_id]) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            }
        }
        
        // get all product groups currently in this product group
        $query = 
            "SELECT
                id
            FROM product_groups
            WHERE
                parent_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($_POST['sort_order_product_group_' . $row['id']])) {
                $query = 
                    "UPDATE product_groups SET 
                        sort_order = '" . escape($_POST['sort_order_product_group_' . $row['id']]) . "' 
                    WHERE id = '" . $row['id'] . "'";
                $result2 = mysqli_query(db::$con, $query) or output_error('Query failed');
            }
        }
        
        // loop through the search result pages for this product group and re-build it's tag cloud
        foreach ($search_results_pages_using_current_product_group as $search_results_page) {
            update_tag_cloud_keywords_for_search_results_page_product_group($search_results_page['page_id'], $search_results_page['product_group_id']);
        }
        
        // loop through the search result pages for the parent product group and re-build it's tag cloud
        foreach ($search_results_pages_using_parent_product_group as $search_results_page) {
            update_tag_cloud_keywords_for_search_results_page_product_group($search_results_page['page_id'], $search_results_page['product_group_id']);
        }

        db("DELETE FROM product_groups_attributes_xref WHERE product_group_id = '" . e($_POST['id'] ?? '') . "'");

        // If there are attributes to save, then save them.
        if ($_POST['attributes']) {
            $attributes = decode_json($_POST['attributes']);

            $sort_order = 0;

            foreach ($attributes as $attribute) {
                $sort_order++;
                
                db(
                    "INSERT INTO product_groups_attributes_xref (
                        product_group_id,
                        attribute_id,
                        default_option_id,
                        sort_order)
                    VALUES (
                        '" . e($_POST['id'] ?? '') . "',
                        '" . e($attribute['id']) . "',
                        '" . e($attribute['default_option_id']) . "',
                        '$sort_order')");
            }
        }

        // If the status of the product group has changed, then update status
        // for child items.
        if ($_POST['enabled'] != $old_enabled) {

            require_once(dirname(__FILE__) . '/update_product_group_status.php');

            if ($_POST['enabled']) {
                $status = 'enabled';
            } else {
                $status = 'disabled';
            }

            update_product_group_status(array(
                'id' => $_POST['id'],
                'status' => $status));

        }
        
        log_activity("product group ($_POST[name]) was modified", $_SESSION['sessionusername']);
    }
    
    // if there is a send to set, then forward user to send to
    if ($_POST['send_to'] != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
        
    // else there is not a send to set, so forward user to view product groups screen.
    } else {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_product_groups.php');
    }
}
?>
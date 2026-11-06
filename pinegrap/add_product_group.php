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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');

// The image picker on this screen is the one the product screens draw, so the
// markup, the drop area and the upload rules stay in one place — the copy that
// used to live here was a second implementation that quietly fell behind.
include_once('product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');
$liveform = new liveform('add_product_group');

if (!$_POST) {

    // The image code template new groups start from.
    $code = (string) db('SELECT product_image_code_template FROM config');

    // Determine whether to show product images based on config setting (default: show).
    $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;
    $output_image_header = $show_image ? '<th data-pg-sort="none">' . lang('Image') . '</th>' : '';

    // Products in the Recycle Bin are not offered for the new group.
    $live_products_sql = pg_catalog_bin_live_sql('products');

    $total_unselected = (int) db_value("SELECT COUNT(*) FROM products WHERE 1 = 1" . $live_products_sql);

    // Preload first 50 products so the page isn't empty on load.
    $initial_products = db_items(
        "SELECT id, name, enabled, short_description, image_name, price
         FROM products
         WHERE 1 = 1" . $live_products_sql . "
         " . pg_product_group_form_unselected_order_sql() . "
         LIMIT 50"
    );

    $output_rows .= pg_product_group_form_unselected_rows($initial_products, $show_image);

    $initial_count = count($initial_products);

    // Determine if any product groups exist to control the parent selector behavior.
    $any_product_groups = (bool)db_value("SELECT COUNT(*) FROM product_groups");
    $default_parent_id = $any_product_groups ? (int)db_value("SELECT id FROM product_groups WHERE parent_id = 0 ORDER BY sort_order, name LIMIT 1") : 0;
    if ($any_product_groups) {
        $output_parent_group_select_block =
            '<div class="col-12 my-3">' .
            '<label for="parent_id" class="form-label">' . lang('Parent Product Group') . '</label>' .
            '<select required style="width:100%" class="form-control" data-pg-combobox id="parent_id" name="parent_id" data-placeholder="' . lang('Select Parent Group') . '">' .
            get_product_group_options($default_parent_id, 0, 0, 0, array(), FALSE, 'text', false, true, false) .
            '</select></div>';
    } else {
        $output_parent_group_select_block = '<input type="hidden" name="parent_id" value="0" />';
    }

    echo
    pg_page_shell([
        'title'=> lang('Create Product Group'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Product Group'),
        'heading_description' => lang('Create a new product group and include products and other product groups.'),
        'cancel'=>array('enable'=>'true','url'=>'view_product_groups.php')
    ,
            'breadcrumb' => array(array('label' => lang('Product Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php'), array('label' => lang('Create Product Group'))),
        ]) . '
<main id="content" class="container-fluid">
            ' . get_wysiwyg_editor_code(array('full_description', 'details')) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_product_group.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />
                    ' . pg_product_group_form_layout(array(
                        'form' => pg_product_group_form_cards($liveform, array('code' => $code), array('is_new' => true)),
                        'status' => pg_product_group_form_status_card(array(), $output_parent_group_select_block),
                        'wide' => pg_product_group_form_products_card(array(
                            'table_id' => 'add_product_group_products_table',
                            'title' => lang('Products to Include'),
                            'card_class' => '',
                            'image_header' => $output_image_header,
                            'rows' => $output_rows,
                            'total_unselected' => $total_unselected,
                            'loaded' => $initial_count,
                            'product_group_id' => 0)),
                        'buttons' => '
                                        <button type="submit" id="create_button" name="submit_button" value="Create" data-pg-save-shortcut class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>')) . '
                    <input type="hidden" name="max_input_vars_test" value="true">
                </form>
            </div>
        </div>
    ' .
        pg_pb_render_image_picker_assets() .
        ($show_image ? _pg_lightbox_assets_once() : '') .
        '</main>' . output_footer();

$liveform->remove_form();

} else {
    validate_token_field();
    
    // If the max_input_vars_test hidden field is not in the post data then that means the post data was truncated,
    // so output error. This can happen because of max_input_vars (i.e. php.ini
    // setting added in PHP v5.3.9 and often backported to earlier versions).
    // This can happen when there is a large number of products (e.g. 1,000+).
    // The default value for max_input_vars is 1,000.
    if (isset($_POST['max_input_vars_test']) == FALSE) {
        output_error(lang('Sorry, the server did not accept the form that you submitted. We recommend that you ask the server administrator to check the max_input_vars PHP setting in the php.ini file.  We recommend that it be set to a number that is at least double the number of Products that the site will contain.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // A blank name, an over-long name or short description or a sort order
    // that is no whole number sends the user back to the form.
    if (pg_product_group_form_mark_problems($liveform, pg_product_group_form_post_problems($_POST), lang(array('string'=>'{var:1} is required','vars'=>array(lang('Product Group Name')))) . '.')) {
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_product_group.php');
        exit();
    }
    
    $_POST['name'] = trim((string) $_POST['name']);

    // If product groups exist, parent_id is required
    if (db_value("SELECT COUNT(*) FROM product_groups") && !$_POST['parent_id']) {
        $liveform->mark_error('parent_id', lang(array('string' => '{var:1} is required', 'vars' => array(lang('Parent Product Group')))) . '.');
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_product_group.php');
        exit();
    }
    if (!$_POST['parent_id']) {
        $_POST['parent_id'] = 0;
    }

    // The parent list was drawn when the screen opened: a parent deleted or
    // moved to the recycle bin since then, or an id the list never offered,
    // would store the new group outside the live tree, where no screen or
    // catalog page reaches it.
    if (!function_exists('pg_catalog_group_map')) {
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    }

    if (!pg_product_group_form_parent_ok(pg_catalog_group_map(), 0, $_POST['parent_id'], 0)) {
        $liveform->mark_error('parent_id', lang('The selected parent product group no longer exists.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_product_group.php');
        exit();
    }

    $_POST['parent_id'] = (int) $_POST['parent_id'];

    // The address is made from the address name, the short description or the name.
    $address_name = pg_product_group_form_address_source($_POST);
    
    // prepare the address name for the database
    $address_name = prepare_catalog_item_address_name($address_name);

    // The first picked image is the cover; the rest are stored apart.
    list($cover_image, $more_group_images) = pg_product_group_form_images(pg_pb_selected_images());
    $sql_imagename = "'" . escape($cover_image) . "',";

    // create product group
    db(
        "INSERT INTO product_groups (
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
            attributes,
            user, 
            timestamp)
        VALUES (
            '" . e($_POST['name'] ?? '') . "',
            '" . e($_POST['enabled'] ?? '') . "',
            '" . e($_POST['parent_id'] ?? '') . "', 
            '" . e($_POST['short_description'] ?? '') . "', 
            '" . e(prepare_rich_text_editor_content_for_input($_POST['full_description'])) . "', 
            '" . e(prepare_rich_text_editor_content_for_input($_POST['details'])) . "', 
            '" . e($_POST['code'] ?? '') . "', 
            '" . e($_POST['keywords'] ?? '') . "', 
           	$sql_imagename
            '" . e($_POST['display_type'] ?? '') . "', 
            '" . e($address_name) . "',
            '" . e($_POST['title'] ?? '') . "',
            '" . e($_POST['meta_description'] ?? '') . "',
            '" . e($_POST['meta_keywords'] ?? '') . "',
            '1',
            '$user[id]', 
            UNIX_TIMESTAMP())");
    
    $product_group_id = mysqli_insert_id(db::$con);

    foreach ($more_group_images as $value) {
        db_q("INSERT INTO product_groups_images_xref (product_group, file_name) VALUES (?, ?)", array((string) $product_group_id, (string) $value)) or output_error('Query failed');
    }

    pg_product_group_form_link_products($product_group_id, $_POST);

    // The search results pages whose product group tree now holds the new
    // group rebuild their tag cloud.
    foreach (pg_product_group_form_search_results_pages($product_group_id)['current'] as $search_results_page) {
        delete_tag_cloud_keywords_for_search_results_page($search_results_page['page_id']);
        update_tag_cloud_keywords_for_search_results_page_product_group($search_results_page['page_id'], $search_results_page['product_group_id']);
    }

    //if code has ^^image_loop_start^^ and ^^image_url^^ and ^^image_loop_end^^. so we prepare to code to insert in config
    if( 
        (strpos($_POST['code'], '^^image_url^^') !== false)&&
        (strpos($_POST['code'], '^^image_loop_start^^') !== false)&&
        (strpos($_POST['code'], '^^image_loop_end^^') !== false)
    ){ 
        //get product_image_code_template from config
        $query = "SELECT product_image_code_template FROM config";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $config_code = $row['product_image_code_template'];
        //check if config_code is not equal to POSTED code
        if($config_code != $_POST['code']){
            //update config_code with new code
            $query = "UPDATE config SET product_image_code_template = ?";
            $result = db_q($query, array((string) ($_POST['code'] ?? ''))) or output_error('Query failed.');
        }
    }       
    
    log_activity( lang(array('string'=>'product group ({var:1}) was created','vars'=>array($_POST['name']) )) , $_SESSION['sessionusername']);

    // Back to the Catalog Manager, which shows the notice as a toast.
    $notice_liveform = new liveform('view_product_groups');
    $notice_liveform->add_notice(lang(array('string' => 'The product group, {var:1}, was added.', 'vars' => $_POST['name'])));

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_product_groups.php');
}
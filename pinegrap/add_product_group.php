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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('add_product_group');

if (!$_POST) {

    if(db('SELECT product_image_code_template FROM config') != ''){
        $code = db('SELECT product_image_code_template FROM config');
    }

    // Determine whether to show product images based on config setting (default: show).
    $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;
    $output_image_header = $show_image ? '<th>' . lang('Image') . '</th>' : '';

    $total_unselected = (int) db_value("SELECT COUNT(*) FROM products");

    // Preload first 50 products so the page isn't empty on load.
    $initial_products = db_items(
        "SELECT id, name, enabled, short_description, image_name, price
         FROM products
         ORDER BY timestamp DESC
         LIMIT 50"
    );

    foreach ($initial_products as $urow) {
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
            '<td class="align-middle chart_label ' . $ustatus_class . '">' . h($urow['name']) . '</td>' .
            '<td class="align-middle ' . $ustatus_class . '">' . h($urow['short_description']) . '</td>' .
            '<td class="align-middle text-end">' . prepare_amount($urow['price']) . '</td>' .
            '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $urow['id'] . '" size="5" value="" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;width:60px;" /></td>' .
            '<td></td>' .
            '</tr>';
    }

    $initial_count = count($initial_products);

    // Determine if any product groups exist to control the parent selector behavior.
    $any_product_groups = (bool)db_value("SELECT COUNT(*) FROM product_groups");
    $default_parent_id = $any_product_groups ? (int)db_value("SELECT id FROM product_groups WHERE parent_id = 0 ORDER BY sort_order, name LIMIT 1") : 0;
    if ($any_product_groups) {
        $output_parent_group_select_block =
            '<div class="col-12 col-md-4 my-2">' .
            '<label for="parent_id" class="form-label">*' . lang('Parent Product Group') . '</label>' .
            '<select required style="width:100%" class="select2 form-select" id="parent_id" name="parent_id" data-placeholder="' . lang('Select Parent Group') . '">' .
            get_product_group_options($default_parent_id, 0, 0, 0, array(), FALSE) .
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
        'cancel'=>array('enable'=>'true','url'=>'view_product_groups.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Product Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php'), array('label' => lang('Create Product Group'))),
        ]) . '
            ' . get_wysiwyg_editor_code(array('full_description', 'details')) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new product group and include products and other product groups.') . '" title="' . lang('Create Product Group') . '">[' . lang('new product group') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_product_group.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('New Product Group Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="name" class="form-label">*' . lang('Product Group Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text','id'=>'name','name'=>'name', 'class'=>'form-control add-header-content-updater ')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" checked="checked" />
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
                                        ' . $output_parent_group_select_block . '
                                        <div class="col-12 col-md-8 my-2">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            <input type="text" name="short_description" placeholder="' . lang('Short Description') . '" id="short_description" maxlength="100" class="form-control" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            <textarea id="full_description" name="full_description"></textarea>
                                        </div>

                                        <div class="col-12 my-3">
                                            <div class="col-12">
                                                <label class="form-label">' . lang('Display Type') . '</label>
                                            </div>
                                            <div class="form-check  form-check-inline" title="' . lang('Display contents for browsing on catalog page') . '">
                                                <input class="form-check-input collapse-switcher"  type="radio" id="browse" name="display_type" checked="checked" value="browse" />
                                                <label for="browse">' . lang('Browse') . '</label> 
                                            </div>
                                            <div class="form-check  form-check-inline" title="' . lang('Display contents for selection on catalog detail page') . '">
                                                <input class="form-check-input collapse-switcher"  type="radio" id="select" name="display_type" value="select" data-bs-target="#display_type_select_row"/>
                                                <label for="select">' . lang('Select') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="display_type_select_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-2">
                                                            <div class="alert alert-primary">' . lang('Product group behaves like a product and provides the ability to select options in product detail page.') . '</div>
                                                        </div>
                                                        <div class="col-12 mt-1 mb-2">
                                                            <label for="details" class="form-label">' . lang('Details') . '</label>
                                                            <textarea id="details" name="details"></textarea>
                                                        </div>
                                                        <div class="col-12 mt-3 mb-2">
                                                            <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                                            <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                                    tagin( document.querySelector("#keywords") );
                                                                }
                                                            </script>
                                                        </div>
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
                                            <div id="software_image_picker_container" ondblclick="software_image_picker({initialize:true});" class="user-select-none sortable-list img-list bg-body-tertiary rounded p-2 row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4"></div>
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
                                                                    <textarea id="code" name="code">' . $code . '</textarea>
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
                                    ' . lang('SEO') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                            <div class="input-group ">
                                                <label for="address_name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Catalog Name') . '}">public</label>
                                                <input type="text" name="address_name" id="address_name" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" />
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" ></textarea>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_keywords" class="form-label">' . lang('Web Browser Keywords') . '</label>
                                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"  maxlength="255"/>
                                            <script>
                                                if(document.body.contains(document.querySelector("input#keywords"))){
                                                    tagin(document.querySelector("#meta_keywords"));
                                                }
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset text-uppercase text-primary h5 fw-bold">' . lang('Products to Include') . '</div>
                                <div class="card-body p-0 position-relative">
                                    <table id="add_product_group_products_table" class="chart table-hover table datatable-restricted-mode" style="width:100%;display:none">
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
                                ' . ($total_unselected > $initial_count ? '
                                <div class="p-3 border-top">
                                    <button id="btn_load_more_products" type="button" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-arrow-down-circle me-2"></i>' . lang('Load More Products') . '
                                    </button>
                                </div>' : '') . '
                            </div>
                        <script>
                        (function() {
                            var loadedOffset = ' . $initial_count . ';
                            var loadMoreBusy = false;
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
                                        product_group_id: 0,
                                        offset: loadedOffset
                                    }),
                                    success: function(response) {
                                        if (response.status === "success" && response.rows) {
                                            var $rows = $(response.rows);
                                            var dt = $("#add_product_group_products_table").DataTable();
                                            $rows.each(function() {
                                                dt.row.add(this);
                                            });
                                            dt.draw(false);

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
                    </div>
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_button" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="max_input_vars_test" value="true">
                </form>
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

    // if the name is blank, then mark error and forward user back to previous screen
    if ($_POST['name'] == '') {
        $liveform->mark_error('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Product Group Name')))) . '.');
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_product_group.php');
        exit();
    }
    
    // If product groups exist, parent_id is required
    if (db_value("SELECT COUNT(*) FROM product_groups") && !$_POST['parent_id']) {
        $liveform->mark_error('parent_id', lang(array('string' => '{var:1} is required', 'vars' => array(lang('Parent Product Group')))) . '.');
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_product_group.php');
        exit();
    }
    if (!$_POST['parent_id']) {
        $_POST['parent_id'] = 0;
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
    $address_name = prepare_catalog_item_address_name($address_name);

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
            "'" . escape($selected_cover_image) . "',";
        }
    }else{
        $sql_imagename = 
        "'',";
    }

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

    if($selected_count > 1){
        foreach ($selected_images as $value) {db("INSERT INTO product_groups_images_xref (product_group,file_name)VALUES ('$product_group_id','" . escape($value) . "')");}
    }

    // if at least one product was selected then proceed with update to products_groups_xref table
    if ($_POST['products']) {
        // foreach product that was selected
        foreach($_POST['products'] as $product_id) {
            $query = "INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES ('" . escape($product_id) . "', '" . $product_group_id . "', '" . escape($_POST['sort_order_product_' . $product_id]) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        }
    }
    
    $search_results_pages = array();
        
    // get data from all search result pages that have "search products" enabled
    $query = "SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while($row = mysqli_fetch_assoc($result)) {
        $search_results_pages[] = $row;
    }
    
    $search_results_pages_using_parent_product_group = array();
    
    // loop through each search result page to get the search results page ids that uses this product group
    foreach ($search_results_pages as $search_results_page) {
        $search_results_page_product_groups = array();
        
        // get the product groups inside of this product group
        $search_results_page_product_groups = get_product_groups_in_product_group_tree($search_results_page['product_group_id']);
        
        // loop through the product groups to see if any of them match this one, and if there is a match then add it to the array
        foreach ($search_results_page_product_groups as $product_group) {
            if ($product_group['id'] == $product_group_id) {
                $search_results_pages_using_parent_product_group[] = $search_results_page;
            }
        }
    }
    
    // loop through the search result pages for the parent product group and delete then re-build it's tag cloud
    foreach($search_results_pages_using_parent_product_group as $search_results_page) {
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
            $query = "UPDATE config SET product_image_code_template = '" . escape($_POST['code'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }       
    
    log_activity( lang(array('string'=>'product group ({var:1}) was created','vars'=>array($_POST['name']) )) , $_SESSION['sessionusername']);

    // forward user to view product groups page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_product_groups.php');
}
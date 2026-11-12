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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/field_list.php');

$user = validate_user();

// The form's page, product and product group are single values; one sent as an
// array (?page_id[]=x) counts as not given: it ended in an error page.
pg_request_single_values(array('page_id', 'product_id', 'product_group_id'));

$output_layout_buttons = '';
$output_product_id_to_url = '';
$output_preview_button  = '';
$output_designer_form_notice = '';
// A form drawn in the visual designer: its fields are listed, never changed here.
$designer_form = false;
// if there is a page_id supplied in the query string, then this is a page form
if ((isset($_GET['page_id'])) && ($_GET['page_id'] != '')) {
    validate_area_access($user, 'user');
    
    // get page info
    $query =
        "SELECT
            page_type,
            page_folder,
            page_name,
            page_style
        FROM page
        WHERE page_id = ?";
    $result = db_q($query, array((string) $_GET['page_id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // A deleted or mistyped page id must stop here: the page type below is
    // used to build a table name, and a missing row would turn that into a
    // query against a table that does not exist.
    if (!$row) {
        output_error(lang('Sorry, the page could not be found.'), 404);
    }
    
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

            if (($_REQUEST['form_type'] ?? '') == 'shipping') {
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

    // A form drawn in the visual designer keeps its page's type ('standard').
    // Its fields are custom form fields, derived from the canvas on every
    // design save.
    if (($form_type === '') && pg_page_has_designer_form($_GET['page_id'])) {
        $designer_form = true;
        $form_type = 'custom';
    }

    // A page with no form has no fields to list, and its Create button would
    // lead to add_field.php's refusal.
    if ($form_type === '') {
        pg_form_fields_refuse_page($_GET['page_id']);
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
    $form_settings_table = pg_form_settings_table($page_type, $form_type);

    if (($form_settings_table !== '') && ($page_type != 'express order' or $form_type != 'shipping')) {

        // get form name for page
        $query = "SELECT form_name FROM " . $form_settings_table . " WHERE page_id = '" . escape($_GET['page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $form_name = $row['form_name'] ?? '';
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
        $output_form_designer_subnav_subheading = '<span class="small fg-secondary me-3">' . lang('Displayed in Page') . ': ' . h($page_name) . '</span>';
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
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

            // A page of a visual design opens the designer's layout tab.
            $edit_layout_url = pg_layout_edit_url(
                $_GET['page_id'],
                $row['page_style'],
                pg_page_is_visual_design(array('page_style' => $row['page_style'])),
                USER_ROLE,
                pg_hosted());

            $output_layout_buttons =
                '<a class="btn btn-sm btn-text theme-secondary" href="' . h($edit_layout_url . ((strpos($edit_layout_url, 'edit_page_layout.php') === 0) ? '&send_to=' . urlencode(REQUEST_URL) : '')) . '"><i class="bi bi-code-slash me-1" aria-hidden="true"></i>' . lang('Edit Layout') . '</a>'
                . '<a class="btn btn-sm btn-text theme-secondary" href="generate_layout.php?page_id=' . h($_GET['page_id']) . '"><i class="bi bi-file-earmark-code me-1" aria-hidden="true"></i>' . lang('Generate Layout') . '</a>';
        }
    }
    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php">' . lang('All My Pages') . '</a></li>';
    $output_breadcrumb_second_level_item ='';
    $pg_breadcrumb_parent_items = array(
        array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
    );
    $output_form_designer_content_heading = ucwords($form_type_name);
    if ($form_type == 'shipping') {
        $output_form_designer_content_subheading = lang('Add fields to this custom shipping form.');
    } elseif ($form_type == 'billing') {
        $output_form_designer_content_subheading = lang('Add fields to this custom billing form.');
    } else {
        $output_form_designer_content_subheading = lang('Add fields to this custom form.');
    }
    $delete_data_warning = '';
    
    // If this is a custom form, then output certain content.
    if ($form_type == 'custom') {
        $delete_data_warning = lang(' and ALL SUBMITTED FORM DATA for the selected field(s)');
    }
    $output_cancel_onclick = 'window.location.href=\'' . h(escape_javascript(pg_safe_back_url(($_GET['send_to'] ?? ''), 'view_pages.php'))) . '\'';
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1}{var:2} will be permanently deleted.','vars'=>array(lang('field(s)'),$delete_data_warning))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang(array('string'=>'Delete Selected') ) . '</button>';

    // The canvas is the record of a designer form: a field added, changed or
    // deleted here would be undone by the next design save. The list stays
    // (it is where the ^^name^^ variables are read), the screen points to the
    // editor, and Create / Delete Selected are not offered.
    if ($designer_form) {
        $output_form_designer_content_subheading = lang('The fields of this form are edited in the Page Design Studio.');
        $output_form_designer_footer = '';
        $output_designer_form_notice =
            '<div class="alert theme-info d-flex flex-wrap align-items-center gap-3 mt-5 mb-0" role="note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span class="flex-grow-1">' . lang('This form was drawn in the Page Design Studio. Its fields are added, changed and removed there; a change made on this screen would be overwritten by the next design save. A field\'s variable (^^name^^) puts its value into the form\'s e-mails.') . '</span>
                <a class="btn btn-sm btn-outline theme-secondary" href="' . h(pg_page_edit_url_by_id($_GET['page_id'])) . '"><i class="bi bi-brush me-1" aria-hidden="true"></i>' . lang('Open in the Page Design Studio') . '</a>
            </div>';
    }

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
             WHERE id = ?";
    $result = db_q($query, array((string) $_GET['product_id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // The product was deleted after this screen was opened, or the link is stale.
    if (!$row) {
        output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
    }
    
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
    <span class="small fg-secondary me-3">' . lang('Product ID / SKU') . ': ' . h($product_name) . '</span>
    <span class="small fg-secondary me-3">' . lang('Form Name') . ': ' . h($form_name) . '</span>';
    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php">' . lang('All Products') . '</a></li>';
    $output_breadcrumb_second_level_item = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id'])) . '">' . lang('Edit Product') . '</a></li>';
    $pg_breadcrumb_parent_items = array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Edit Product'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product.php?id=' . h(escape_javascript($_GET['product_id']))),
    );
    $output_form_designer_content_heading = lang('Edit Product Form');
    $output_form_designer_content_subheading = lang('Add fields to this product form.');
    $output_cancel_onclick = 'window.location.href=\'edit_product.php?id=' . h(escape_javascript($_GET['product_id'])) . '\'';
    $output_preview_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" onclick="document.location.href = \'preview_form.php?product_id=\' + document.getElementById(\'product_id\').value"><i class="bi bi-eye me-1" aria-hidden="true"></i>' . lang('Preview') . '</button>';
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('field(s)')))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang(array('string'=>'Delete Selected') ) . '</button>';
    
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

    // The variant set was deleted after this screen was opened, or the link is stale.
    if (!$product_group) {
        output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
    }
    $product_group = $product_group[0];

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
    <span class="small fg-secondary me-3">' . lang('Form Name') . ': ' . h($form_name) . '</span>
    <span class="small fg-secondary me-3">' . lang(array(
        'string' => 'Applied to {var:1} variant{suffix:1}',
        'vars'   => array($variant_count),
        'suffix' => ($variant_count === 1) ? '' : 's')) . '</span>';

    $output_breadcrumb_first_level_item = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets">' . lang('Variant Sets') . '</a></li>';
    $output_breadcrumb_second_level_item = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id'])) . '">' . h($product_group['name']) . '</a></li>';

    $pg_breadcrumb_parent_items = array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets'),
        array('label' => $product_group['name'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id']))),
    );

    $output_form_designer_content_heading = lang('Edit Product Form');
    $output_form_designer_content_subheading = lang('Add fields to this product form.');
    $output_cancel_onclick = 'window.location.href=\'edit_product_group.php?id=' . h(escape_javascript($_GET['product_group_id'])) . '\'';

    // No preview button: a template belongs to no single product, and
    // preview_form.php renders one product's form.
    $output_form_designer_footer = '<button type="submit" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . lang('WARNING: The selected field(s) will be permanently deleted.') . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';

    $output_product_id_to_url = '&product_group_id=' . h(escape_javascript(urlencode($_GET['product_group_id'])));

// else no form context (page_id, form_id, product_id or product_group_id) was supplied in the
// query string, so there is nothing to list.  Without this branch $form_type_filter stayed
// undefined and was interpolated into the WHERE clause, which produced a fatal SQL error.
} else {
    output_error(lang('Sorry, the page could not be found.'), 404);
}

// The field screen's address; the field's id goes at the end.
$field_url = 'edit_field.php?send_to=' . urlencode((string) ($_GET['send_to'] ?? ''))
    . (($form_type == 'product') ? '&product_id=' . urlencode((string) $_GET['product_id']) : '')
    . (($form_type == 'product_group') ? '&product_group_id=' . urlencode((string) $_GET['product_group_id']) : '')
    . '&id=';

// A designer form's fields change in the visual designer only.
$quick_edit = $designer_form ? array() : pg_field_edit_columns($form_type);

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON once the form and the user's
// access to it were checked above, with the checks of the field screen.
if (pg_list_edit_requested() && $quick_edit) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return true;
    });

    $saved = pg_field_quick_update($edit['id'], $edit['field'], $edit['value'], array(
        'form_type' => $form_type,
        'filter' => $form_type_filter,
        'page_id' => $_GET['page_id'] ?? 0,
        'product_id' => $_GET['product_id'] ?? 0,
        'product_group_id' => $_GET['product_group_id'] ?? 0,
        'user_id' => (int) $edit['user']['id'],
        'form_name' => $form_name,
        'url' => $field_url,
    ));

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

// Every field is printed in the form's order: the list table driver sorts
// and searches them in the browser (browser mode); Position brings the
// form's order back.
$list_rows = array();
$position = 0;

foreach (pg_field_list_rows($form_type_filter) as $row) {
    $position++;
    $list_rows[] = array('id' => $row['id'], 'cells' => pg_field_list_cells($row, $position, $field_url . (int) $row['id'], $designer_form, $quick_edit));
}

$columns = pg_field_list_columns($form_type, $quick_edit);
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$list_state = pg_list_state('view_fields', $sort_columns, array('sort' => 'position', 'order' => 'asc'));

// The page's own address, which answers the quick edits of this form.
$list_params = array('page_id', 'form_type', 'product_id', 'product_group_id', 'send_to');
$edit_query = array();

foreach ($list_params as $param) {
    if (isset($_GET[$param]) && is_string($_GET[$param]) && ($_GET[$param] !== '')) {
        $edit_query[$param] = $_GET[$param];
    }
}

$list = pg_list_table_printed(array(
    'list' => 'view_fields',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
    'edit' => $quick_edit ? array('url' => 'view_fields.php?' . http_build_query($edit_query), 'columns' => $quick_edit) : null,
    'ids_field' => $designer_form ? '' : 'fields[]',
));

// A designer form gets no Create button.
$output_create_button = $designer_form ? '' : '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_field.php?' . $form_type_identifier_id . '=' . h(urlencode($_GET[$form_type_identifier_id])) . '&amp;form_type=' . $form_type . '&amp;send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . '"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>';

$liveform = new liveform('view_fields');

echo
pg_page_shell(array(
    'title' => $output_form_designer_subnav_heading . ' | ' . $output_form_designer_content_heading,
    'icon' => (($form_type == 'product') || ($form_type == 'product_group')) ? 'store' : 'design',
    'heading' => $output_form_designer_content_heading,
    'heading_description' => $output_form_designer_content_subheading,
    'cancel'=>array('enable'=>true,'onclick'=>$output_cancel_onclick),
    'breadcrumb' => array_merge(
        $pg_breadcrumb_parent_items,
        array(array('label' => $output_form_designer_content_heading))
    ),
)) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'primary'   => $output_create_button,
        'secondary' => array($output_layout_buttons, $output_preview_button),
        'grow'      => '<span class="fw-semibold text-break me-3">' . $output_form_designer_subnav_heading . '</span>' . $output_form_designer_subnav_subheading,
    )) . '
    <div class="row">
        ' . $liveform->get_messages() . '
        <div class="col-12">
            ' . $output_designer_form_notice . '
            <form name="form" action="delete_fields.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '">
                <input type="hidden" name="' . h($form_type_identifier_id) . '" id="' . h($form_type_identifier_id) . '" value="' . h($_GET[$form_type_identifier_id]) . '">
                <input type="hidden" name="form_type" value="' . $form_type . '">
                ' . pg_list_table_html($list['source'], $list['page'], $output_form_designer_content_heading, $output_form_designer_footer) . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . output_footer();
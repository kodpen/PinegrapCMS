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
validate_area_access($user, 'designer');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM menus WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
$liveform = new liveform('edit_menu', $_REQUEST['id']);

// if the form has not been submitted
if (!$_POST) {
    // get menu information
    $query =
        "SELECT
            name,
            effect,
            first_level_popup_position,
            second_level_popup_position,
            class,
            active_item_class
        FROM menus
        WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // If the menu does not exist, then output error.
    if ($row === null) {
        output_error(lang('Invalid request.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $menu_name = $row['name'];

    // if edit menu screen has not been submitted already, pre-populate fields with data
    if (!$liveform->field_in_session('name')) {
        // Assign the values to the fields.
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('effect', $row['effect']);
        $liveform->assign_field_value('first_level_popup_position', $row['first_level_popup_position']);
        $liveform->assign_field_value('second_level_popup_position', $row['second_level_popup_position']);
        $liveform->set('class', $row['class']);
        $liveform->set('active_item_class', $row['active_item_class']);
    }
    
    echo
    pg_page_shell([
        'title'=> lang('Edit Menu Properties'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Menu Properties'),
        'heading_description' => lang('Edit shared menu that can be added to any page style and managed by any site manager.'),
        'cancel'=>array('enable'=>'true','url'=>'view_menus.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'), array('label' => lang('Edit Menu Properties'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<menu>' . $menu_name . '</menu>') . '</strong></p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="duplicate_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_menu.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="from" value="' . h(($_GET['from'] ?? '')) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . pg_menu_form_card($liveform) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('menu')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
        output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // if menu was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // get menu name for the log
        $query = "SELECT name FROM menus WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $menu_name = $row['name'];
        
        // delete menu
        $query = "DELETE FROM menus WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        // delete menu items
        $query = "DELETE FROM menu_items WHERE menu_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        // delete users_menus_xref records
        $query = "DELETE FROM users_menus_xref WHERE menu_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'menu ({var:1}) was deleted','vars'=>$menu_name)), $_SESSION['sessionusername']);
        
        // if the user came from the pages tab, then forward user back to page
        if ($_POST['from'] == 'pages') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
            $liveform->remove_form();
        // else the user came from the design tab, so prepare notice and forward user to view menus screen
        } else {
            $liveform->remove_form();
            $liveform_view_menus = new liveform('view_menus');
            $liveform_view_menus->add_notice(lang('The menu has been deleted.'));
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menus.php');
        }
        
        
        
    // else the menu was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        
        pg_menu_form_validate_name($liveform, (string) ($_POST['id'] ?? ''));
        
        // if there is an error, forward user back to edit menu screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_menu.php?id=' . $_POST['id'] . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
        
        // update menu
        $query =
            "UPDATE menus
            SET
                name = ?,
                effect = ?,
                first_level_popup_position = ?,
                second_level_popup_position = ?,
                class = ?,
                active_item_class = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(pg_menu_form_values($liveform), array((string) $user['id'], (string) ($_POST['id'] ?? '')))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'menu ({var:1}) was modified','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_menu_items = new liveform('view_menu_items');
        $liveform_view_menu_items->add_notice(lang('The menu has been saved.'));
        
        // forward user to view menu items screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $_POST['id'] . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
        
        
    }
}
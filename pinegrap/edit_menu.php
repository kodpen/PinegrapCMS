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
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('edit_menu', $_REQUEST['id']);

// if the form has not been submitted
if (!$_POST) {
    // if edit menu screen has not been submitted already, pre-populate fields with data
    if (!$liveform->field_in_session('name')) {
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
            WHERE id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $menu_name = $row['name'];
        
        // Assign the values to the fields.
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('effect', $row['effect']);
        $liveform->assign_field_value('first_level_popup_position', $row['first_level_popup_position']);
        $liveform->assign_field_value('second_level_popup_position', $row['second_level_popup_position']);
        $liveform->set('class', $row['class']);
        $liveform->set('active_item_class', $row['active_item_class']);
    }
    
    // Set options for First and Second Level Pop-up Positions
    $popup_position_options = 
        array(
            lang('Top') => 'Top',
            lang('Bottom') => 'Bottom',
            lang('Left') => 'Left',
            lang('Right') => 'Right');
    
    echo
    pg_page_shell([
        'title'=> lang('Edit Menu Properties'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Menu Properties'),
        'cancel'=>array('enable'=>'true','url'=>'view_menus.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'), array('label' => lang('Edit Menu Properties'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit shared menu that can be added to any page style and managed by any site manager.') . '" title="' . lang('Edit Menu Properties') . '">[' . h($row['name']) . ']</h2>
                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<menu>' . $menu_name . '</menu>') . '</strong></p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Duplicating') . '" href="duplicate_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_menu.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="from" value="' . h(($_GET['from'] ?? '')) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Menu Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="name" class="form-label">' . lang('Menu Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<menu>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100', 'required'=>'required')) . '
                                                <div class="input-group-text">' . h('</menu>') . '</div>
                                            </div>
                                           
                                        </div>
                                        <div class="col-12 mt-1">
                                            <label class="form-label" for="">'. lang('Submenu Effect') . '</label>
                                        </div>
                                        <div class="col-12 mb-1">
                                            <div class="form-check form-check-inline" title="">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_popup',  'name'=>'effect', 'value'=>'Pop-up', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#pop-up_row')) . '
                                                <label class="form-check-label" for="effect_popup">'. lang('Pop-up') . '<br><img src="assets/images/menu_effect_popup.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_accordion', 'name'=>'effect', 'value'=>'Accordion', 'class'=>'form-check-input collapse-switcher')) . '
                                                <label class="form-check-label" for="effect_accordion">'. lang('Accordion') . '<br><img src="assets/images/menu_effect_accordion.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_none', 'name'=>'effect', 'value'=>'', 'class'=>'form-check-input collapse-switcher')) . '
                                                <label class="form-check-label" for="effect_none">'. lang('None') . '<br><img src="assets/images/menu_effect_none.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="pop-up_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-md-6 my-1">
                                                            <label for="first_level_popup_position" class="form-label">' . lang('First Expand Menu') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-select', 'name'=>'first_level_popup_position', 'id'=>'first_level_popup_position', 'options'=>$popup_position_options)) . '
                                                        </div>
                                                        <div class="col-12 col-md-6 my-1">
                                                            <label for="first_level_popup_position" class="form-label">' . lang('Then Expand Menu') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-select', 'name'=>'second_level_popup_position', 'id'=>'second_level_popup_position', 'options'=>$popup_position_options)) . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-3 mb-2">
                                            <label for="class" class="form-label">' . lang('Classes') . '</label>
                                            <div class="input-group input-group-sm d-flex flex-wrap border rounded border-2">
                                                <div class="input-group-text bg-reset border-0">&lt;ul class="<span class="tagin-tag" style="padding-right: 1rem;"><span class="tagin-tag_text" style="max-width:unset;">software_menu</span></span></div>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'class',
                                                    'id' => 'class',
                                                    'class' => 'form-control tagin border-0 min-width-tagin',
                                                    'data-placeholder' => lang('Add classes'),
                                                    'maxlength' => '255')) . '
                                                <script>
                                                    if(document.body.contains(document.querySelector("input#class"))){
                                                        tagin( document.querySelector("#class"),{
                                                            separator : " "
                                                        });
                                                    }
                                                </script>
                                                <div class="input-group-text bg-reset border-0">"&gt;</div>
                                            </div>
                                            <div class="form-text text-end">' . lang('One or More Custom Classes for the Menu. Separate classes with a space') . '</div>
                                        </div>
                                        <div class="col-12 mt-3 mb-2">
                                            <label for="active_item_class" class="form-label">' . lang('Active Item Classes') . '</label>
                                            <div class="input-group input-group-sm d-flex flex-wrap border rounded border-2">
                                                <div class="input-group-text bg-reset border-0">&lt;a class="current</div>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'active_item_class',
                                                    'id' => 'active_item_class',
                                                    'class' => 'form-control tagin border-0 min-width-tagin',
                                                    'data-placeholder' => lang('Add classes'),
                                                    'maxlength' => '255')) . '
                                                <script>
                                                    if(document.body.contains(document.querySelector("input#active_item_class"))){
                                                        tagin( document.querySelector("#active_item_class"),{
                                                            separator : " "
                                                        });
                                                    }
                                                </script>
                                                <div class="input-group-text bg-reset border-0">"&gt;</div>
                                            </div>
                                            <div class="form-text text-end">' . lang('One or More Classes for the Active Menu Item. Separate classes with a space') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('menu')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
        $query = "SELECT name FROM menus WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $menu_name = $row['name'];
        
        // delete menu
        $query = "DELETE FROM menus WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete menu items
        $query = "DELETE FROM menu_items WHERE menu_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete users_menus_xref records
        $query = "DELETE FROM users_menus_xref WHERE menu_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'menu ({var:1}) was deleted','vars'=>$menu_name)), $_SESSION['sessionusername']);
        
        // if the user came from the pages tab, then forward user back to page
        if ($_POST['from'] == 'pages') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
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
        
        $liveform->validate_required_field('name', lang('Name is required.'));
        
        // if there is not already an error for the name field, then check that valid characters were entered for name field
        if ($liveform->check_field_error('name') == false) {
            // Get previous name in order to determine if we should allow underscores or not.
            // We don't want to allow menus to be created or new menu names to be set with underscores,
            // because the theme designer does not support underscores in names.  We are going to allow
            // underscores as long as it was set in the past in order to prevent their styles or themes
            // from being messed up.
            $query = "SELECT name FROM menus WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $previous_name = $row['name'];

            // If the previous name contains underscores, then set regex code and message to allow underscores.
            if (mb_strpos($previous_name, '_') !== FALSE) {
                $regex = '/[^A-Za-z0-9_-]/';
                $message = lang('Please only enter letters, numbers, dashes, and underscores for the name.');

            // Otherwise the previous name does not contain underscores, so set regex code and message so they do not allow underscores.
            } else {
                $regex = '/[^A-Za-z0-9-]/';
                $message = lang('Please only enter letters, numbers, or dashes for the name.');
            }

            // If the name is not valid, then add error.
            if (preg_match($regex, $liveform->get_field_value('name')) == 1) {
                $liveform->mark_error('name', $message);
            }
        }
        
        // if there is not already an error for the name field, then check to see if name is already in use
        if ($liveform->check_field_error('name') == false) {
            // check to see if name is already in use by a different menu
            $query =
                "SELECT id
                FROM menus
                WHERE
                    (name = '" . escape($liveform->get_field_value('name')) . "')
                    AND (id != '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if name is already in use, prepare error
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            }
        }
        
        // if there is an error, forward user back to edit menu screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_menu.php?id=' . $_POST['id'] . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
        
        // update menu
        $query =
            "UPDATE menus
            SET
                name = '" . e($liveform->get_field_value('name')) . "',
                effect = '" . e($liveform->get_field_value('effect')) . "',
                first_level_popup_position = '" . e($liveform->get_field_value('first_level_popup_position')) . "',
                second_level_popup_position = '" . e($liveform->get_field_value('second_level_popup_position')) . "',
                class = '" . e($liveform->get('class')) . "',
                active_item_class = '" . e($liveform->get('active_item_class')) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'menu ({var:1}) was modified','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_menu_items = new liveform('view_menu_items');
        $liveform_view_menu_items->add_notice(lang('The menu has been saved.'));
        
        // forward user to view menu items screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $_POST['id'] . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
        
        
    }
}
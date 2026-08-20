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
validate_area_access($user, 'user');

// get menu id and name that menu item is in, to use for later
$query =
    "SELECT
        menus.id,
        menus.name,
        menu_items.name as menu_item_name,
        menu_items.parent_id
    FROM menus
    LEFT JOIN menu_items ON menus.id = menu_items.menu_id
    WHERE menu_items.id = '" . escape($_REQUEST['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$menu_id = $row['id'];
$menu_name = $row['name'];
$menu_item_name = $row['menu_item_name'];
$parent_id = $row['parent_id'];

// if user has a user role and if they do not have access to this menu, output error
if (($user['role'] == 3) && (in_array($menu_id, get_items_user_can_edit('menus', $user['id'])) == FALSE)) {
    log_activity(lang(array('string'=>'access denied because user does not have access to edit menu item for menu ({var:1})','vars'=>$menu_name)), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('edit_menu_item', $_REQUEST['id']);

if (!$_POST) {
    // if edit menu item screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_menu_item'][$_GET['id']]) == false) {
        // get menu item information
        $query =
            "SELECT
                name,
                sort_order,
                link_page_id,
                link_url,
                link_target,
                security,
                class
            FROM menu_items
            WHERE id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        // Assign the values to the fields.
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('link_page_id', $row['link_page_id']);
        $liveform->assign_field_value('link_url', $row['link_url']);
        $liveform->assign_field_value('link_target', $row['link_target']);
        $liveform->assign_field_value('security', $row['security']);
        $liveform->set('class', $row['class']);
        
        $sort_order = $row['sort_order'];
        
        // get menu item id for menu item directly above this menu item, because this will be the position value
        $query =
            "SELECT id
            FROM menu_items
            WHERE 
                (menu_id = '" . escape($menu_id) . "')
                AND (sort_order < " . $sort_order . ")
                AND (parent_id = '" . escape($parent_id) . "')
            ORDER BY sort_order DESC
            LIMIT 1";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed. ' . $query);
        $row = mysqli_fetch_assoc($result);
        
        $liveform->assign_field_value('sort_order', $row['id']);
    }
    
    $parent_id_options = get_menu_item_options($menu_id, $_GET['id'], 0, 1, $parent_id);
    
    // get sort order options
    $sort_order_options = array();
    $sort_order_options[lang('Top')] = 'top';
    
    // get all menu items that are in this level
    $query =
        "SELECT
            id,
            name
        FROM menu_items
        WHERE
            (id != '" . escape($_GET['id']) . "')
            AND (menu_id = '" . escape($menu_id) . "')
            AND (parent_id = '" . escape($parent_id) . "')
        ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // loop through all menu items in order to add them to sort order options
    while ($row = mysqli_fetch_assoc($result)) {
        $sort_order_options[lang(array('string'=>'Below {var:1}','vars'=>h($row['name'])))] = $row['id'];
    }
    
    $page_options = get_page_options();
    
    $link_target_options = 
        array(
            lang('Same Window')=>'Same Window',
            lang('New Window')=>'New Window');
    
    print pg_page_shell([
        'title'=> lang('Edit Menu Item'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Menu Item'),
        'cancel'=>array('enable'=>'true','url'=>'view_menu_items.php'),
        'breadcrumb' => array(
            array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'),
            array('label' => lang('Edit Menu'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id),
            array('label' => lang('Edit Menu Item')),
        ),
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Define the label to display for this menu item and where it links too.') . '" title="' . lang('Edit Menu Item') . '">[' . h($menu_item_name) . ']</h2>
                        <p>' . lang('Menu') . ': ' . h($menu_name) . '</p>
                    </div>
                </div>
                <form name="form" action="edit_menu_item.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="from" value="' . h(($_GET['from'] ?? '')) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <input type="hidden" id="menu_id" name="menu_id" value="' . h($menu_id) . '" />
                    <input type="hidden" id="parent_id_status" name="parent_id_status" value="" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Menu Item Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="link_target" class="form-label">' . lang('Link Target') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_target', 'id'=>'link_target', 'class'=>'form-select', 'options'=>$link_target_options)) . '
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="parent_id" class="form-label">' . lang('Parent Menu Item') . '</label>
                                            <select name="parent_id" id="parent_id" class="form-select">' . $parent_id_options . '</select>
                                        </div>
                                        <div class="col-12 mt-4"><h5>' . lang('Link Menu Item To') . '</h5></div>
                                        <div class="col-12 col-md-12 col-lg-5 my-2">
                                            <label for="link_page_id" class="form-label">' . lang('Page') . '</label>
                                            <div class="input-group flex-wrap">
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_page_id', 'id'=>'link_page_id', 'class'=>'form-select', 'style'=>'min-width:100px', 'options'=>$page_options, 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'link_url\').value = \'\'}')) . '
                                                <div class="input-group-text">
                                                    <div class="form-check form-switch">
                                                        ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'security', 'id'=>'security', 'value'=>'1', 'class'=>'form-check-input')) . '
                                                        <label class="form-check-label" for="security" title="' . lang('Enable Security') . '" data-bs-content="' . lang('only show Menu Item if Visitor has access to view Page') . '">' . lang('Enable Security') . ' (?)</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-2  my-2 text-lg-center">
                                            <label class="form-label">-' . lang('or') . '-</label>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-5 my-2">
                                            <label for="link_url" class="form-label">' . lang('URL') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'link_url', 'id'=>'link_url', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                        </div>
                                        <div class="col-12 mt-3 mb-2">
                                            <label for="item_class" class="form-label">' . lang('Classes') . '</label>
                                            <div class="input-group input-group-sm d-flex flex-wrap border rounded border-2">
                                                <div class="input-group-text bg-reset border-0">&lt;li class="</div>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'class',
                                                    'id' => 'item_class',
                                                    'class' => 'form-control tagin border-0 min-width-tagin',
                                                    'data-placeholder' => lang('Add classes'),
                                                    'maxlength' => '255')) . '
                                                <script>
                                                    if(document.body.contains(document.querySelector("input#item_class"))){
                                                        tagin( document.querySelector("#item_class"),{
                                                            separator : " "
                                                        });
                                                    }
                                                </script>
                                                <div class="input-group-text bg-reset border-0">"&gt;</div>
                                            </div>
                                            <div class="form-text text-end">' . lang('One or More Custom Classes for the Menu Item. Separate classes with a space') . '</div>
                                        </div>
                                        <div class="col-12"></div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="parent_id" class="form-label">' . lang('Position') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'sort_order', 'name'=>'sort_order', 'class'=>'form-select', 'options'=>$sort_order_options)) . '
                                        </div>
                               
                                        </tr>
                                        <script>
                                            $("#link_url").bind("input", function(){
                                                if ($("#link_url").val() != "") {
                                                    document.getElementById(\'link_page_id\').selectedIndex = 0
                                                }
                                            });
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('menu item and all menu items underneath it')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // if menu_item was selected for deletion
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // function for deleting menu items recursively
        function delete_menu_items($menu_item_id)
        {
            // delete menu_item
            $query = "DELETE FROM menu_items WHERE id = '" . escape($menu_item_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $menu_items = array();
            
            // get sub menu items
            $query = "SELECT id FROM menu_items WHERE parent_id = '" . escape($menu_item_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $menu_items = array();
            
            // loop through sub menu items in order to build array
            while ($row = mysqli_fetch_assoc($result)) {
                $menu_items[] = $row;
            }
            
            // loop through sub menu items in order to delete sub menu items
            foreach ($menu_items as $menu_item) {
                delete_menu_items($menu_item['id']);
            }
        }
        
        // delete menu item and all sub menu items
        delete_menu_items($_POST['id']);

        // update last modified for menu
        $query =
            "UPDATE menus
            SET
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($menu_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'menu item ({var:1}) on menu ({var:2}) was deleted','vars'=>array($_POST['name'],$menu_name))), $_SESSION['sessionusername']);
        
        include_once('liveform.class.php');
        $liveform->remove_form();
        $liveform_view_menu_items = new liveform('view_menu_items');
        $liveform_view_menu_items->add_notice(lang('The menu item has been deleted.'));

        // forward user to view menu items screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
        
        
        
    // else menu item was not selected for deletion
    } else {
        $liveform->add_fields_to_session();
        
        $liveform->validate_required_field('name', lang('Name is required.'));
        
        // if there is an error, forward user back to edit menu screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_menu_item.php?id=' . $_POST['id'] . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
        
        // If there is a link page id, blank out the link_url field!
        if ($_POST['link_page_id']) {
            $link_page_id = $_POST['link_page_id'];
            $link_url = '';
        // Else, there was not a page id, so save the URL fields value instead
        } else {
            $link_url = $_POST['link_url'];
            $link_page_id = '';
        }
        
        // If the parent ID was changed, add this menu items sort order to the end of the line
        if ($_POST['parent_id_status'] == 'changed') {
            // Get the sort order for the next menu item
            $query =
                "SELECT sort_order
                FROM menu_items
                WHERE 
                    (menu_id = '" . escape($menu_id) . "')
                    AND (parent_id = '" . escape($_POST['parent_id'] ?? '')  . "')
                ORDER BY sort_order DESC
                LIMIT 1";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $row = mysqli_fetch_assoc($result);
            $sort_order = $row['sort_order'] + 1;
        } else {
            $sort_order = $_POST['sort_order'];
        }
        
        // update menu item
        $query =
            "UPDATE menu_items
            SET
                name = '" . escape($_POST['name'] ?? '') . "',
                parent_id = '" . escape($_POST['parent_id'] ?? '') . "',
                sort_order = '" . escape($sort_order) . "',
                link_page_id = '" . escape($link_page_id) . "',
                link_url = '" . escape($link_url) . "',
                link_target = '" . escape($_POST['link_target'] ?? '') . "',
                security = '" . escape($_POST['security'] ?? '') . "',
                class = '" . escape($_POST['class'] ?? '') . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // update last modified for menu
        $query =
            "UPDATE menus
            SET
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($menu_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        /* begin: update sort orders for menu items */
        $menu_items = array();
        
        // If sort_order is set to top, add it to the array first thing.
        if ($_POST['sort_order'] == 'top') {
            $menu_items[] = $_POST['id'];
        }
        
        // get all menu items other than the menu item that is currently being edited
        $query =
            "SELECT id
            FROM menu_items
            WHERE 
                (menu_id = '" . escape($menu_id) . "')
                AND (parent_id = '" . escape($_POST['parent_id'] ?? '')  . "')
                AND (id != '" . escape($_POST['id'] ?? '')  . "')
            ORDER BY sort_order";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        while ($row = mysqli_fetch_assoc($result)) {
            // add this menu item to array
            $menu_items[] = $row['id'];
            
            // if this menu item is the position value, then we need to add the menu item that is being edited to the array
            if ($row['id'] == $_POST['sort_order']) {
                $menu_items[] = $_POST['id'];
            }
        }
        
        $count = 1;
        
        // loop through all menu items in order to update sort order
        foreach ($menu_items as $key => $current_menu_id) {
            // update sort order for menu item
            $query =
                "UPDATE menu_items
                SET sort_order = '$count'
                WHERE id = '" . escape($current_menu_id)  . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $count++;
        }
        
        /* end: update sort orders for menu items */
        log_activity(lang(array('string'=>'menu item ({var:1}) on menu ({var:2}) was modified','vars'=>array($_POST['name'],$menu_name) )), $_SESSION['sessionusername']);

        include_once('liveform.class.php');
        $liveform_view_menu_items = new liveform('view_menu_items');
        $liveform_view_menu_items->add_notice(lang('The menu item has been saved.'));

        // forward user to view menu items screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
        
        $liveform->remove_form();
    }
}
?>
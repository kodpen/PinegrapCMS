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

// get menu id and name that we will add the menu item to, to use for later
$query =
    "SELECT
        name
    FROM menus
    WHERE id = '" . escape($_REQUEST['menu_id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$menu_id = $_REQUEST['menu_id'];
$menu_name = $row['name'];

// if user has a user role and if they do not have access to this menu, then user does not have access to edit region, so output error
if (($user['role'] == 3) && (in_array($_REQUEST['menu_id'], get_items_user_can_edit('menus', $user['id'])) == FALSE)) {
    log_activity(lang(array('string'=>'access denied because user does not have access to create a menu item for menu ({var:1})','vars'=>array($menu_name))), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('add_menu_item');

if (!$_POST) {
    // Setup picklist option variables
    $parent_id_options = get_menu_item_options($_REQUEST['menu_id']);
    $page_options = get_page_options();
    $link_target_options = 
        array(
            lang('Same Window')=>'Same Window',
            lang('New Window')=>'New Window');

    if (!$liveform->output_errors()) {
        $liveform->remove_form();
    }
    
    $output = pg_page_shell([
        'title'=> lang('Create Menu Item'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Menu Item'),
        'cancel'=>array('enable'=>'true','url'=>'view_menu_items.php'),
        'breadcrumb' => array(
            array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'),
            array('label' => lang('Edit Menu'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id),
            array('label' => lang('Create Menu Item')),
        ),
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new menu item within this shared menu.') . '" title="' . lang('Create Menu Item') . '">[' . lang('New Menu Item') . ']</h2>
                        <p>' . lang('Menu') . ': ' . h($menu_name) . '</p>
                    </div>
                </div>
                <form name="form" action="add_menu_item.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="from" value="' . h(($_GET['from'] ?? '')) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" id="menu_id" name="menu_id" value="' . $menu_id . '" />
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
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

    print $output;
    
    $liveform->unmark_errors();
    $liveform->clear_notices();

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang('Name is required.'));
    
    // if there is an error, forward user back to edit menu screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_menu_item.php?menu_id=' . $menu_id . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
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
    
    // Get the sort order for the next menu item
    $query = "SELECT sort_order
             FROM menu_items
             WHERE 
                 (menu_id = '" . escape($menu_id) . "')
                 AND (parent_id = '" . escape($_POST['parent_id'] ?? '')  . "')
             ORDER BY sort_order DESC
             LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $row = mysqli_fetch_assoc($result);
    $new_sort_order = $row['sort_order'] + 1;
    
    // create menu item
    $query =
        "INSERT into menu_items
            (name,
            menu_id,
            parent_id,
            sort_order,
            link_page_id,
            link_url,
            link_target,
            security,
            class,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES
            ('" . escape($_POST['name'] ?? '') . "',
            '" . escape($menu_id) . "',
            '" . escape($_POST['parent_id'] ?? '') . "',
            '" . $new_sort_order . "',
            '" . escape($link_page_id) . "',
            '" . escape($link_url) . "',
            '" . escape($_POST['link_target'] ?? '') . "',
            '" . escape($_POST['security'] ?? '') . "',
            '" . escape($_POST['class'] ?? '') . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'menu item ({var:1}) on menu ({var:2}) was created','vars'=>array($_POST['name'],$menu_name) )), $_SESSION['sessionusername']);
    
    // update last modified for menu
    $query =
        "UPDATE menus
        SET
            last_modified_user_id = '" . $user['id'] . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . escape($menu_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    include_once('liveform.class.php');
    $liveform_view_menu_items = new liveform('view_menu_items');
    $liveform_view_menu_items->add_notice(lang('The menu item has been created.'));

    // forward user to view menu items screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
}
?>
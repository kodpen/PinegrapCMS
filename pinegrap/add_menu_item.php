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
validate_area_access($user, 'user');

// The menu is one value; one sent as an array (?menu_id[]=x) counts as not
// given: it ended in an error page.
pg_request_single_values(array('menu_id'));

// get menu id and name that we will add the menu item to, to use for later
$query =
    "SELECT
        name
    FROM menus
    WHERE id = ?";
$result = db_q($query, array((string) ($_REQUEST['menu_id'] ?? ''))) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// Opened without a menu, or for one that no longer exists (stale link,
// hand-typed URL): a "not found" screen instead of PHP notices from the
// missing menu below.
if (!$row) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$menu_id = (int)($_REQUEST['menu_id'] ?? 0);
$menu_name = $row['name'];

// if user has a user role and if they do not have access to this menu, then user does not have access to edit region, so output error
if (($user['role'] == 3) && (in_array($_REQUEST['menu_id'], get_items_user_can_edit('menus', $user['id'])) == FALSE)) {
    log_activity(lang(array('string'=>'access denied because user does not have access to create a menu item for menu ({var:1})','vars'=>array($menu_name))), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_item_form.php');
$liveform = new liveform('add_menu_item');

if (!$_POST) {
    // Setup picklist option variables
    $parent_id_options = get_menu_item_options($_REQUEST['menu_id']);

    if (!$liveform->output_errors()) {
        $liveform->remove_form();
    }
    
    $output = pg_page_shell([
        'title'=> lang('Create Menu Item'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Menu Item'),
        'heading_description' => lang('Create a new menu item within this shared menu.'),
        'cancel'=>array('enable'=>'true','url'=>'view_menu_items.php'),
        'breadcrumb' => array(
            array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'),
            array('label' => lang('Edit Menu'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id),
            array('label' => lang('Create Menu Item')),
        ),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <p>' . lang('Menu') . ': ' . h($menu_name) . '</p>
                    </div>
                </div>
                <form name="form" action="add_menu_item.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="from" value="' . h(($_GET['from'] ?? '')) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" id="menu_id" name="menu_id" value="' . $menu_id . '" />
                    ' . pg_menu_item_form_card($liveform, $parent_id_options) . '
                    
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
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
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_menu_item.php?menu_id=' . $menu_id . '&from=' . urlencode($_POST['from'] ?? '') . '&send_to=' . urlencode($_POST['send_to'] ?? ''));
        exit();
    }
    
    // A chosen page wins over the URL.
    $link = pg_menu_item_form_link($_POST);
    $link_page_id = $link['link_page_id'];
    $link_url = $link['link_url'];

    // The new item goes last on its level.
    $new_sort_order = pg_menu_item_form_last_sort_order($menu_id, $_POST['parent_id'] ?? '');
    
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
            (?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array((string) ($_POST['name'] ?? ''), (string) $menu_id, (string) ($_POST['parent_id'] ?? ''), (string) $new_sort_order, (string) $link_page_id, (string) $link_url, (string) ($_POST['link_target'] ?? ''), (string) ($_POST['security'] ?? ''), (string) ($_POST['class'] ?? ''), (string) $user['id'], (string) $user['id'])) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'menu item ({var:1}) on menu ({var:2}) was created','vars'=>array($_POST['name'],$menu_name) )), $_SESSION['sessionusername']);
    
    pg_menu_item_form_touch_menu($menu_id, $user['id']);

    include_once('liveform.class.php');
    $liveform_view_menu_items = new liveform('view_menu_items');
    $liveform_view_menu_items->add_notice(lang('The menu item has been created.'));

    // forward user to view menu items screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_menu_items.php?id=' . $menu_id . '&from=' . urlencode($_POST['from']) . '&send_to=' . urlencode($_POST['send_to']));
}
?>
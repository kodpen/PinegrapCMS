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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM containers WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_container');

$container = db_item(
    "SELECT
        id,
        name,
        enabled,
        length,
        width,
        height,
        weight,
        cost
    FROM containers
    WHERE id = '" . escape($_REQUEST['id']) . "'");

// If the form has not been submitted, then output it.
if (!$_POST) {

    // Pre-populate fields with existing data on first load.
    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('name', $container['name']);
        $liveform->assign_field_value('enabled', $container['enabled']);

        // Remove extra zeros in decimal.
        $liveform->assign_field_value('length', $container['length'] + 0);
        $liveform->assign_field_value('width', $container['width'] + 0);
        $liveform->assign_field_value('height', $container['height'] + 0);

        // If there is a weight, set it; otherwise leave blank.
        if ($container['weight'] > 0) {
            $liveform->assign_field_value('weight', $container['weight'] + 0);
        }

        // If there is a cost, convert from cents to dollars; otherwise leave blank.
        if ($container['cost']) {
            $liveform->assign_field_value('cost', $container['cost'] / 100);
        }
    }

    echo
        pg_page_shell([
            'title'=> lang('Edit Container'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Container'),
            'heading_description' => lang('Edit a shipping container (e.g. box) that products are packaged in.'),
            'cancel'=>array('enable'=>'true','url'=>'view_containers.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Containers'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_containers.php'), array('label' => lang('Edit Container'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form action="edit_container.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                        ' . pg_container_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('container')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                    ' . pg_container_form_script() . '
                </div>
            </div>
        
</main>' .
        output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    // If the user selected to delete this container, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {

        db("DELETE FROM containers WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        log_activity(lang(array('string' => 'container ({var:1}) was deleted', 'vars' => array($container['name']))), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_view = new liveform('view_containers');
        $liveform_view->add_notice(lang('The container has been deleted.'));

    // Otherwise the user selected to save the container, so save it.
    } else {

        pg_container_form_validate($liveform, $liveform->get_field_value('id'));

        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        // Convert cost to cents.
        $cost = (int) round(pg_parse_amount($liveform->get_field_value('cost')) * 100);

        db(
            "UPDATE containers
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                enabled = '" . escape($liveform->get_field_value('enabled')) . "',
                length = '" . escape($liveform->get_field_value('length')) . "',
                width = '" . escape($liveform->get_field_value('width')) . "',
                height = '" . escape($liveform->get_field_value('height')) . "',
                weight = '" . escape($liveform->get_field_value('weight')) . "',
                cost = '" . escape($cost) . "',
                last_modified_user_id = '" . USER_ID . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        log_activity(lang(array('string' => 'container ({var:1}) was modified', 'vars' => array($liveform->get_field_value('name')))), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_view = new liveform('view_containers');
        $liveform_view->add_notice(lang('The container has been saved.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_containers.php');
}

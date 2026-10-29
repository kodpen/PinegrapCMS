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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
$liveform = new liveform('add_menu');

// if the form has not been submitted
if (!$_POST) {
    // if the form has not been submitted yet, then set default values
    if ($liveform->field_in_session('name') == false) {
        $liveform->assign_field_value('effect', 'Pop-up');
        $liveform->assign_field_value('first_level_popup_position', 'Bottom');
        $liveform->assign_field_value('second_level_popup_position', 'Right');
    }

    
    print
    pg_page_shell([
        'title'=> lang('Create Menu'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Menu'),
        'heading_description' => lang('Create a shared menu that can be added to any page style and managed by any site manager.'),
        'cancel'=>array('enable'=>'true','url'=>'view_menus.php'),
        'breadcrumb' => array(
            array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'),
            array('label' => lang('Create Menu')),
        ),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_menu.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_menu_form_card($liveform) . '
                    
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
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
    
    $liveform->remove_form();

// else the form has been submitted
} else {
  
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    pg_menu_form_validate_name($liveform, null);

    // if there is an error, forward user back to add menu screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_menu.php');
        exit();
    }
    
    // create menu
    $query =
        "INSERT INTO menus (
            name,
            effect,
            first_level_popup_position,
            second_level_popup_position,
            class,
            active_item_class,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
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
    $result = db_q($query, array_merge(pg_menu_form_values($liveform), array(
        (string) $user['id'],
        (string) $user['id'],
    ))) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('menu'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_view_menus = new liveform('view_menus');
    $liveform_view_menus->add_notice(lang('The menu has been created.'));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_menus.php');
    
    
}
?>
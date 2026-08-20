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
$liveform = new liveform('add_menu');

// if the form has not been submitted
if (!$_POST) {
    // Set options for First and Second Level Pop-up Positions
    $popup_position_options = 
        array(
            lang('Top') => 'Top',
            lang('Bottom') => 'Bottom',
            lang('Left') => 'Left',
            lang('Right') => 'Right');
    
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
        'cancel'=>array('enable'=>'true','url'=>'view_menus.php'),
        'breadcrumb' => array(
            array('label' => lang('All Menus'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php'),
            array('label' => lang('Create Menu')),
        ),
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a shared menu that can be added to any page style and managed by any site manager.') . '" title="' . lang('Create Menu') . '">[' . lang('New Menu') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_menu.php" method="post">
                    ' . get_token_field() . '
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
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    $liveform->validate_required_field('name', lang('Name is required.'));

    // if there is not already an error for the name field, then check that valid characters were entered for name field
    if ($liveform->check_field_error('name') == false) {
        if (preg_match('/[^A-Za-z0-9-]/', $liveform->get_field_value('name')) == 1) {
            $liveform->mark_error('name', lang('Please only enter letters, numbers, and dashes for the name.'));
        }
    }
    
    // if there is not already an error for the name field, then check to see if name is already in use
    if ($liveform->check_field_error('name') == false) {
        $query =
            "SELECT id
            FROM menus
            WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if name is already in use by a different menu, prepare error
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }
    
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
            '" . e($liveform->get_field_value('name')) . "',
            '" . e($liveform->get_field_value('effect')) . "',
            '" . e($liveform->get_field_value('first_level_popup_position')) . "',
            '" . e($liveform->get_field_value('second_level_popup_position')) . "',
            '" . e($liveform->get('class')) . "',
            '" . e($liveform->get('active_item_class')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('menu'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_view_menus = new liveform('view_menus');
    $liveform_view_menus->add_notice(lang('The menu has been created.'));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_menus.php');
    
    
}
?>
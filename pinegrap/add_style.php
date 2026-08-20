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
$liveform = new liveform('add_style');

// if the form has not been submitted, then prepare to output form
if (!$_POST) {
    print
    pg_page_shell([
        'title'=> lang('Create Page Style'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Page Style'),
        'cancel'=>array('enable'=>'true','url'=>'view_styles.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Create Page Style'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new HTML template that can be associated with one or many Pages.') . '" title="' . lang('Create Page Style') . '">[' . lang('new page style') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_style.php" method="post" id="question_text">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Page Style Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <div class="alert alert-primary">
                                                <p class="mb-0">' . lang('What type of Page Style do you want to create?') . '</p>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label class="form-label" for="">'. lang('Page Style Type') . '</label>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'type', 'id'=>'custom', 'value'=>'custom', 'class'=>'form-check-input collapse-switcher', 'checked' => 'checked')) . '
                                                <label class="form-check-label" for="custom">'. lang('Custom') . ' (' . lang('enter your own HTML; good for responsive page design') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'type', 'id'=>'system', 'value'=>'system', 'class'=>'form-check-input')) . '
                                                <label class="form-check-label" for="system">'. lang('System') . ' (' . lang('use Visual Pinegrap Editor; drag &amp; drop Bootstrap components') . ')</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_continue" value="Continue" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">done</span><span class="btn-text">' . lang(array('string'=>'Continue') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted, so process the form
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('type', lang('Please select a type.'));
    

    
    // if there is an error, forward user back to the previous screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_style.php');
        exit();
    }
    
    // if the user selected a system type, then forward user to create system style
    if ($liveform->get_field_value('type') == 'system') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_system_style.php');
        
    // else the user select a custom type, so forward user to create custom style
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_custom_style.php');
    }
    
    $liveform->remove_form();
}
?>
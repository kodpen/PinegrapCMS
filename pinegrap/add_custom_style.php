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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/custom_style_form.php');

// If the form was not just submitted, then continue to output the page.
if (!$_POST) {
    
    $output_header = pg_page_shell([
        'title'=> lang('Create Custom Page Style'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Custom Page Style'),
        'heading_description' => lang('Create a new HTML template that can be associated with one or many Pages.'),
        'cancel'=>array('enable'=>'true','url'=>'view_styles.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Create Custom Page Style'))),
        ]) . '<main id="content" class="container-fluid">';
    
    print
    $output_header . '
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="add_custom_style.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_custom_style_form_cards(pg_custom_style_form_defaults(), SOCIAL_NETWORKING == TRUE) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success " ><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

// else save the page style
} else {
    validate_token_field();
    
    $values = pg_custom_style_form_values($_POST, SOCIAL_NETWORKING == TRUE);
    $name = $values['style_name'];

    // insert row into style table
    $result=db_q(
        "INSERT INTO style (" . implode(', ', array_keys($values)) . ", style_timestamp, style_user) VALUES (" . str_repeat('?, ', count($values)) . "UNIX_TIMESTAMP(), ?)",
        array_merge(array_values($values), array((string) $user['id']))
    ) or output_error('Query failed');

    log_activity(lang(array('string'=>'style ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);
    include_once('liveform.class.php');
    $notice = lang('The style was created successfully.');
    $liveform_view_styles = new liveform('view_styles');
    $liveform_view_styles->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
}
?>
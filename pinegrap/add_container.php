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

include_once('liveform.class.php');
$liveform = new liveform('add_container');

// If the form has not been submitted, then output it.
if (!$_POST) {

    // Pre-populate enabled as checked by default on first load.
    if ($liveform->field_in_session('name') == false) {
        $liveform->assign_field_value('enabled', 1);
    }

    echo
        pg_page_shell([
            'title'=> lang('Create Container'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Container'),
            'heading_description' => lang('Create a new shipping container (e.g. box) that products are packaged in.'),
            'cancel'=>array('enable'=>'true','url'=>'view_containers.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Containers'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_containers.php'), array('label' => lang('Create Container'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form method="post">
                        ' . get_token_field() . '
                        ' . pg_container_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang('Create') . '</span></button>
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

    pg_container_form_validate($liveform);

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_container.php');
    }

    // Convert cost to cents.
    $cost = (int) round(pg_parse_amount($liveform->get_field_value('cost')) * 100);

    db(
        "INSERT INTO containers (
            name,
            enabled,
            length,
            width,
            height,
            weight,
            cost,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('enabled')) . "',
            '" . escape($liveform->get_field_value('length')) . "',
            '" . escape($liveform->get_field_value('width')) . "',
            '" . escape($liveform->get_field_value('height')) . "',
            '" . escape($liveform->get_field_value('weight')) . "',
            '" . escape($cost) . "',
            '" . USER_ID . "',
            UNIX_TIMESTAMP(),
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    log_activity(lang(array('string' => 'container ({var:1}) was created', 'vars' => array($liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_view = new liveform('view_containers');
    $liveform_view->add_notice(lang('The container has been created.'));

    go(PATH . SOFTWARE_DIRECTORY . '/view_containers.php');
}

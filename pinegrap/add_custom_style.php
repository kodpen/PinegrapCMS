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

include_once('liveform.class.php');
$liveform = new liveform('add_custom_style');

// If the form was not just submitted, then continue to output the page.
if (!$_POST) {
    // A refused name comes back with everything that was typed.
    $style = pg_custom_style_form_defaults();
    $name_error = pg_region_form_name_error_text($liveform);

    if ($name_error !== '') {
        $style = array(
            'style_name' => (string) $liveform->get_field_value('name'),
            'style_code' => (string) $liveform->get_field_value('code'),
            'collection' => pg_custom_style_form_choice('collection', $liveform->get_field_value('collection')),
            'layout_type' => pg_custom_style_form_choice('layout_type', $liveform->get_field_value('layout_type')),
            'social_networking_position' => pg_custom_style_form_choice('social_networking_position', $liveform->get_field_value('social_networking_position')),
        );
    }

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
            <form name="form" action="add_custom_style.php" method="post">
                ' . get_token_field() . '
                ' . pg_custom_style_form_layout(
                    pg_custom_style_form_cards($style, SOCIAL_NETWORKING == TRUE, $name_error),
                    '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                ) . '
            </form>
</main>' .
    output_footer();

    $liveform->remove_form();

// else save the page style
} else {
    validate_token_field();
    
    $values = pg_custom_style_form_values($_POST, SOCIAL_NETWORKING == TRUE);
    $name = $values['style_name'];

    // A name of only spaces passes the browser's required check and is trimmed to ''.
    if ($name === '') {
        output_error(lang('Name is required.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // A longer name would be cut by the column; a taken one keeps the visual
    // designer from saving either style.
    $name_error = pg_region_form_name_refusal($name, pg_style_name_taken($name));

    if ($name_error !== '') {
        $liveform->add_fields_to_session(array('trim' => false));
        $liveform->mark_error('name', $name_error);
        go(PATH . SOFTWARE_DIRECTORY . '/add_custom_style.php');
    }

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
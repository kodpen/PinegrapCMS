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
validate_email_access($user);

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
$liveform = new liveform('add_email_campaign_profile');

// If the form was not just submitted, then show form.
if (!$_POST) {
    $plain_text_email_campaign_footer = db_value("SELECT plain_text_email_campaign_footer FROM config");

    // If there is a plain text footer, then deal with it.
    if ($plain_text_email_campaign_footer != '') {
        // If the form has not been submitted yet, or the body is equal to the 
        // plain text footer without any of the new lines at the beginnning,
        // then prefill body with plain text footer.
        // If there was an error the liveform class will remove the extra lines
        // at the beginning of the plain text footer, so that is why we have to add the
        // footer again in that case.
        if (
            ($liveform->field_in_session('name') == false)
            || ($liveform->get_field_value('body') == trim($plain_text_email_campaign_footer))
        ) {
            $liveform->assign_field_value('body',
                "\n" .
                "\n" .
                "\n" .
                $plain_text_email_campaign_footer);
        }
    }

    echo
    pg_page_shell(
        array(
            'title'=> lang('Create Campaign Profile'),
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> lang('Create Campaign Profile'),
            'heading_description' => lang('Setup a Campaign to be created automatically when a certain action is completed (e.g. Visitor reserves Calendar Event).'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaign_profiles.php')
        ,
            'breadcrumb' => array(array('label' => lang('My Campaign Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'), array('label' => lang('Create Campaign Profile'))),
        )
    )    . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form action="add_email_campaign_profile.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_email_campaign_profile_form_cards($liveform, $user) . '
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

// Otherwise the form was just submitted, so process form.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    pg_email_campaign_profile_form_validate($liveform);

    // If there is an error, forward user back to previous screen.
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_email_campaign_profile.php');
        exit();
    }

    $columns = pg_email_campaign_profile_form_columns($liveform);

    $columns['created_user_id'] = (int) $user['id'];
    $columns['last_modified_user_id'] = (int) $user['id'];

    // create email campaign profile
    db_q(
        "INSERT INTO email_campaign_profiles (" . implode(', ', array_keys($columns)) . ", created_timestamp, last_modified_timestamp)
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
        array_values($columns));
    
    log_activity(lang(array('string'=>'campaign profile ({var:1}) was created','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
    $liveform_view_email_campaign_profiles->add_notice(lang('The campaign profile has been created.'));

    // Forward user to view email campaign profiles screen.
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
    exit();
}

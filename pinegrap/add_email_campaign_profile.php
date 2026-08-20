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
validate_email_access($user);

include_once('liveform.class.php');
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

    // Prepare display property for various items.
    $output_calendar_event_id_row_class = '';
    $output_custom_form_page_id_row_class = '';
    $output_email_campaign_profile_id_row_class = '';
    $output_product_id_row_class = '';
    $output_body_preview_iframe_source = '';
    $calendar_event_reserved_schedule_period_and_base_class = '';
    $standard_schedule_period_and_base_class = '';

    switch ($liveform->get_field_value('action')) {
        case 'calendar_event_reserved':
            $output_calendar_event_id_row_class = 'show';
            $calendar_event_reserved_schedule_period_and_base_class = 'show';
            break;

        case 'custom_form_submitted':
            $output_custom_form_page_id_row_class = 'show';
            $standard_schedule_period_and_base_class = 'show';
            break;

        case 'email_campaign_sent':
            $output_email_campaign_profile_id_row_class = 'show';
            $standard_schedule_period_and_base_class = 'show';
            break;

        case 'order_abandoned':
        case 'order_completed':
        case 'order_shipped':
            $standard_schedule_period_and_base_class = 'show';
            break;

        case 'product_ordered':
            $output_product_id_row_class = 'show';
            $standard_schedule_period_and_base_class = 'show';
            break;

        default:
            $standard_schedule_period_and_base_class = 'show';
            break;
    }

    switch ($liveform->get_field_value('format')) {
       
        case 'html':
           
            // If a body page is selected, then get page name.
            if ($liveform->get_field_value('page_id') != '') {
                $page_name = get_page_name($liveform->get_field_value('page_id'));

                // If a page name was found, then update body preview iframe with page.
                if ($page_name != '') {
                    $output_body_preview_iframe_source = OUTPUT_PATH . h(encode_url_path($page_name)) . '?edit=no&amp;email=true';
                }
            }

            break;
    }

    $action_options = array(
        '-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Action')) )) . '-' => '',
        lang('Auto Campaign Sent') => 'email_campaign_sent',
        lang('Calendar Event Reserved') => 'calendar_event_reserved',
        lang('Custom Form Submitted') => 'custom_form_submitted');

    $output_product_id_row = '';

    // If commerce is enabled and the user has access to commerce,
    // then add commerce actions and product id row.
    if ((ECOMMERCE == true) && (USER_MANAGE_ECOMMERCE == true)) {

        $action_options[lang('Order Abandoned')] = 'order_abandoned';
        $action_options[lang('Order Completed')] = 'order_completed';
        $action_options[lang('Order Shipped')] = 'order_shipped';
        $action_options[lang('Product Ordered')] = 'product_ordered';

        $output_product_id_row =
            '<div class="col-12 col-md-6 col-lg-4 my-2 collapse ' . $output_product_id_row_class . '" id="product_id_row">
                <label for="product_id" class="form-label">' . lang('Product') . '</label>
                ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-select', 'options'=>get_product_options() )) . '
                <div class="invalid-feedback">' . lang('Required Area') . '</div>
            </div>';
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> lang('Create Campaign Profile'),
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> lang('Create Campaign Profile'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaign_profiles.php')
        ,
            'breadcrumb' => array(array('label' => lang('My Campaign Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'), array('label' => lang('Create Campaign Profile'))),
        )
    )    . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Setup a Campaign to be created automatically when a certain action is completed (e.g. Visitor reserves Calendar Event).') . '" title="' . lang('Create Campaign Profile') . '">[' . lang('Campaign Profile Name') . ']</h2>
                    </div>
                </div>
                <form action="add_email_campaign_profile.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                    
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'enabled', 'name'=>'enabled', 'value'=>'1', 'checked'=>'checked', 'class'=>'form-check-input')) . '
                                                <label class="form-check-label" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'size'=>'60', 'maxlength'=>'100', 'placeholder'=>lang('Campaign Profile Name'), 'class'=>'form-control add-header-content-updater')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div> 
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="action" class="form-label">' . lang('Action') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'action', 'id'=>'action', 'class'=>'form-select', 'options'=>$action_options, 'onchange'=>'change_email_campaign_profile_action()')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div> 
                                        <div class="col-12 col-md-6 col-lg-4 my-2 collapse ' . $output_calendar_event_id_row_class . '" id="calendar_event_id_row">
                                            <label for="calendar_event_id" class="form-label">' . lang('Calendar Event') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'calendar_event_id', 'id'=>'calendar_event_id', 'class'=>'form-select', 'options'=>get_calendar_event_options(array('reservations' => true)) )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div> 
                                        <div class="col-12 col-md-6 col-lg-4 my-2 collapse ' . $output_custom_form_page_id_row_class . '" id="custom_form_page_id_row">
                                            <label for="custom_form_page_id" class="form-label">' . lang('Custom Form') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'custom_form_page_id', 'id'=>'custom_form_page_id', 'class'=>'form-select', 'options'=>get_page_options('', 'custom form') )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div> 
                                        <div class="col-12 col-md-6 col-lg-4 my-2 collapse ' . $output_email_campaign_profile_id_row_class . '" id="email_campaign_profile_id_row">
                                            <label for="email_campaign_profile_id" class="form-label">' . lang('Campaign Profile for Campaign that was sent') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'email_campaign_profile_id', 'id'=>'email_campaign_profile_id', 'class'=>'form-select', 'options'=>get_email_campaign_profile_options() )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        ' . $output_product_id_row . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="subject" class="form-label">' . lang('Subject') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'subject', 'id'=>'subject', 'placeholder'=>lang('Subject'), 'maxlength'=>'255', 'class'=>'form-control')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="col-12">
                                                <label class="form-label">' . lang('Format') . '</label>
                                            </div>
                                            <div class="form-check  form-check-inline">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'format_plain_text', 'name'=>'format', 'value'=>'plain_text', 'checked'=>'checked', 'class'=>'form-check-input collapse-switcher', 'data-bs-target' => '#email_format_plain_text_row')) . '
                                                <label for="format_plain_text">' . lang('Plain Text') . '</label> 
                                            </div>
                                            <div class="form-check  form-check-inline">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'format_html', 'name'=>'format', 'value'=>'html', 'class'=>'form-check-input collapse-switcher', 'data-bs-target' => '#email_format_html_row')) . '
                                                <label for="format_html">' . lang('HTML') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="email_format_plain_text_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                          <label for="email_body" class="form-label">' . lang('Body') . '</label>
                                                          ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'body', 'id'=>'email_body', 'class'=>'form-control', 'placeholder'=>lang('Type E-mail body here') )) . '
                                                          <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100"  id="email_format_html_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-sm-8 my-1">
                                                            <label class="form-label" for="page_id">' . lang('Page') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'page_id', 'name'=>'page_id', 'class'=>'form-select collapse-if-selected', 'options'=>get_page_options('', '', 'view'), 'data-bs-target'=>'#body_preview_row', 'onchange'=>'document.getElementById(\'body_preview_iframe\').src = \'' . OUTPUT_PATH . '\' + this.options[this.selectedIndex].firstChild.nodeValue + \'?edit=no&email=true\';')) . '
                                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="body_preview_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-1">
                                                                        <div style="margin-bottom: 1em">' . lang('Body Preview') . '</div>
                                                                        <iframe id="body_preview_iframe" src="' . $output_body_preview_iframe_source . '" style="min-width:250px;width: 100%;max-width:100%;min-height:300px; height: 500px;resize: both;overflow: auto;"></iframe>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message From') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-6 col-xl-12 my-2">
                                            <label class="form-label" for="from_name">' . lang('From Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text','value'=>h(ORGANIZATION_NAME), 'name'=>'from_name', 'id'=>'from_name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row">
                                                <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                    <label class="form-label" for="bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text','value'=>'', 'name'=>'bcc_email_address', 'id'=>'bcc_email_address', 'class'=>'form-control text-end', 'maxlength'=>'100', 'inputmode'=>'email', 'data-inputmask-alias'=>'email')) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                    <label class="form-label" for="from_email_address">' . lang('From E-mail Address') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text','value'=>h($user['email_address']), 'name'=>'from_email_address', 'id'=>'from_email_address', 'class'=>'form-control text-end', 'maxlength'=>'100', 'inputmode'=>'email', 'data-inputmask-alias'=>'email')) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                    <label class="form-label" for="reply_email_address">' . lang('Reply to E-mail Address') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text','value'=>h($user['email_address']), 'name'=>'reply_email_address', 'id'=>'reply_email_address', 'class'=>'form-control text-end', 'maxlength'=>'100', 'inputmode'=>'email', 'data-inputmask-alias'=>'email')) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message Delivery Schedule') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang('Send e-mail') . '</label>
                                            <div class="input-group mb-3 ">
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'schedule_length', 'class'=>'form-control text-end', 'value'=>'1', 'size'=>'3', 'maxlength'=>'9', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal', 'data-inputmask-digits'=>'9', 'data-inputmask-placeholder'=>'1' )) . ' 
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'schedule_unit', 'class'=>'form-select', 'options'=>array(lang('day(s)') => 'days', lang('hour(s)') => 'hours'))) . ' 
                                                ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-select collapse ' . $calendar_event_reserved_schedule_period_and_base_class, 'name'=>'schedule_period', 'id'=>'schedule_period', 'options'=>array('' => '', lang('before') => 'before', lang('after') => 'after'))) . ' 
                                                ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-select collapse ' . $calendar_event_reserved_schedule_period_and_base_class, 'name'=>'schedule_base', 'id'=>'schedule_base', 'options'=>array('' => '', lang('from action') => 'action', lang('from calendar event start time') => 'calendar_event_start_time'))) . '
                                                <span class="collapse input-group-text ' . $standard_schedule_period_and_base_class . '" id="standard_schedule_period_and_base">&nbsp;' . lang('after action') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label class="form-label" for="schedule_time">' . lang('Send at a specific time') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'schedule_time', 'id'=>'schedule_time', 'class'=>'form-control', 'maxlength'=>'8')) . '
                                            
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            <div class="text-end form-text">' . lang('h:mm AM/PM, leave blank to send at any time.') . '</div>
                                            ' . get_time_picker_format() . '
                                            <script>
                                                $("#schedule_time").timepicker(timepicker_options);
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label class="form-label">' . lang('Purpose') . '</label>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array(
                                                    'type' => 'radio',
                                                    'id' => 'purpose_commercial',
                                                    'name' => 'purpose',
                                                    'value' => 'commercial',
                                                    'checked' => 'checked',
                                                    'class' => 'form-check-input')) . '
                                                <label class="form-check-label" for="purpose_commercial">' . lang('Commercial') . ' (' . lang('send email to opted-in contacts only. Example: \'We have an offer for you\'') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array(
                                                    'type' => 'radio',
                                                    'id' => 'purpose_transactional',
                                                    'name' => 'purpose',
                                                    'value' => 'transactional',
                                                    'class' => 'form-check-input')) . '
                                                <label class="form-check-label" for="purpose_transactional">' . lang('Transactional') . ' (' . lang('send email, regardless of opt-in. Example: \'Your order has been shipped\'') . ')</label>
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

// Otherwise the form was just submitted, so process form.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))));

    // If there is not already an error for the name field, and that name is already in use, then output error.
    if (
        ($liveform->check_field_error('name') == false)
        && (db_value("SELECT COUNT(*) FROM email_campaign_profiles WHERE name = '" . escape($liveform->get_field_value('name')) . "'") != 0)
    ) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
    }

    // If a commerce action was selected, and the user is not allowed to select a commerce action,
    // then clear value in order to generate an error.
    if (
        (
            $liveform->get('action') == 'order_abandoned'
            or $liveform->get('action') == 'order_completed'
            or $liveform->get('action') == 'order_shipped'
            or $liveform->get('action') == 'product_ordered'
        )
        and (!ECOMMERCE or !USER_MANAGE_ECOMMERCE)
    ) {
        $liveform->set('action', '');
    }

    $liveform->validate_required_field('action', lang(array('string'=>'{var:1} is required','vars'=>lang('Action'))));

    // If there is not already an error for the action field, then validate sub-fields for it.
    if ($liveform->check_field_error('action') == false) {
        switch ($liveform->get_field_value('action')) {
            case 'calendar_event_reserved':
                $liveform->validate_required_field('calendar_event_id', lang(array('string'=>'{var:1} is required','vars'=>lang('Calendar Event'))));

                // If there is not already an error for the calendar event field and the user does not have access to selected calendar event, then add error.
                if (
                    ($liveform->check_field_error('calendar_event_id') == false)
                    && (validate_calendar_event_access($liveform->get_field_value('calendar_event_id')) == false)
                ) {
                    log_activity(lang('access denied for user to set calendar event for a campaign profile, because user does not have access to calendar event'), $_SESSION['sessionusername']);
                    $liveform->mark_error('calendar_event_id', lang('Sorry, you do not have access to that calendar event.'));
                }

                break;

            case 'custom_form_submitted':
                $liveform->validate_required_field('custom_form_page_id', lang(array('string'=>'{var:1} is required','vars'=>lang('Custom Form'))));

                // If there is not already an error for the custom form field and the user does not have edit access to the custom form, then add error.
                if (
                    ($liveform->check_field_error('custom_form_page_id') == false)
                    && (check_edit_access(db_value("SELECT page_folder FROM page WHERE page_id = '" . escape($liveform->get_field_value('custom_form_page_id')) . "'")) == false)
                ) {
                    log_activity(lang('access denied for user to set custom form for a campaign profile, because user does not have edit access to custom form page'), $_SESSION['sessionusername']);
                    $liveform->mark_error('custom_form_page_id', lang('Sorry, you do not have access to that custom form.'));
                }

                break;

            case 'email_campaign_sent':
                $liveform->validate_required_field('email_campaign_profile_id', lang(array('string'=>'{var:1} is required','vars'=>lang('Campaign Profile'))));

                // If there is not already an error for the campaign profile field and the user does not have edit access to the campaign profile, then add error.
                if (
                    ($liveform->check_field_error('email_campaign_profile_id') == false)
                    && (USER_ROLE == 3)
                    && (USER_ID != db_value("SELECT created_user_id FROM email_campaign_profiles WHERE id = '" . escape($liveform->get_field_value('email_campaign_profile_id')) . "'"))
                ) {
                    log_activity(lang('access denied for user to set campaign profile action for a campaign profile, because user does not have access to campaign profile'), $_SESSION['sessionusername']);
                    $liveform->mark_error('email_campaign_profile_id', lang('Sorry, you do not have access to that campaign profile.'));
                }

                break;

            case 'product_ordered':
                $liveform->validate_required_field('product_id', lang(array('string'=>'{var:1} is required','vars'=>lang('Product'))));

                // We don't have to check commerce access to product
                // because we already did that above for the action.

                break;
        }
    }

    $liveform->validate_required_field('subject', lang(array('string'=>'{var:1} is required','vars'=>lang('Subject'))));
    $liveform->validate_required_field('format', lang(array('string'=>'{var:1} is required','vars'=>lang('Format'))));

    // If there is not already an error for the format field and HTML format was selected, then validate page id field.
    if (
        ($liveform->check_field_error('format') == false)
        && ($liveform->get_field_value('format') == 'html')
    ) {
        $liveform->validate_required_field('page_id', lang(array('string'=>'{var:1} is required','vars'=>lang('Page'))));

        // If there is not already an error for the page id field
        // and the user does not have view access to the selected page,
        // then log activity and add error.
        if (
            ($liveform->check_field_error('page_id') == false)
            && (check_view_access(db_value("SELECT page_folder FROM page WHERE page_id = '" . escape($liveform->get_field_value('page_id')) . "'")) == false)
        ) {
            log_activity(lang('access denied for user to set page for a campaign profile, because user does not have view access to the page'), $_SESSION['sessionusername']);
            $liveform->mark_error('page_id', lang('You are not authorized to select that page. Please select a different page.'));
        }
    }

    // If a bcc e-mail address was entered and it is not valid, then add error.
    if (
        ($liveform->get_field_value('bcc_email_address') != '')
        && (validate_email_address($liveform->get_field_value('bcc_email_address')) == false)
    ) {
        $liveform->mark_error('bcc_email_address', lang(array('string'=>'Please enter a valid {var:1}.','vars'=>lang('bcc e-mail address'))) );
    }

    $liveform->validate_required_field('from_name', lang(array('string'=>'{var:1} is required','vars'=>lang('From Name'))));
    $liveform->validate_required_field('from_email_address', lang(array('string'=>'Please enter a valid {var:1}.','vars'=>lang('e-mail address'))));

    // If there is not already an error for the from email address field and the from e-mail address is not valid, then add error.
    if (
        ($liveform->check_field_error('from_email_address') == false)
        && (validate_email_address($liveform->get_field_value('from_email_address')) == false)
    ) {
        $liveform->mark_error('from_email_address', lang(array('string'=>'Please enter a valid {var:1}.','vars'=>lang('e-mail address'))));
    }

    // If a reply e-mail address was entered and it is not valid, then add error.
    if (
        ($liveform->get_field_value('reply_email_address') != '')
        && (validate_email_address($liveform->get_field_value('reply_email_address')) == false)
    ) {
        $liveform->mark_error('reply_email_address', lang(array('string'=>'Please enter a valid {var:1}.','vars'=>lang('reply to e-mail address'))));
    }

    // If a schedule time was entered and it is not valid, then add error.
    if (
        ($liveform->get_field_value('schedule_time') != '')
        && (validate_time($liveform->get_field_value('schedule_time')) != true)
    ) {
        $liveform->mark_error('schedule_time', lang(array('string'=>'Please enter a valid {var:1}.','vars'=>lang('time'))));
    }

    $liveform->validate_required_field('schedule_unit', lang(array('string'=>'{var:1} is required','vars'=>lang('Day(s)/Hour(s)'))));

    // If calendar event reserved action was selected, then validate fields for that.
    if ($liveform->get_field_value('action') == 'calendar_event_reserved') {
        $liveform->validate_required_field('schedule_period', lang(array('string'=>'{var:1} is required','vars'=>lang('Before/After'))));
        $liveform->validate_required_field('schedule_base', lang(array('string'=>'{var:1} is required','vars'=>lang('From Action/From Calendar event start time'))));

        // If there is not already an error for the period and base fields, and the user selected "before" and "action",
        // then add error because that is not valid.  Eventually we should spend the time to solve this on the front-end
        // instead of letting this error occur.
        if (
            ($liveform->check_field_error('schedule_period') == false)
            && ($liveform->check_field_error('schedule_base') == false)
            && ($liveform->get_field_value('schedule_period') == 'before')
            && ($liveform->get_field_value('schedule_base') == 'action')
        ) {
            $liveform->mark_error('schedule_period', lang('Sorry, you may not select \'before action\'.  Try setting \'after action\'.'));
            $liveform->mark_error('schedule_base', '');
        }
    }

    $liveform->validate_required_field('purpose', lang(array('string'=>'{var:1} is required','vars'=>lang('Purpose'))));
    
    // If there is an error, forward user back to previous screen.
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_email_campaign_profile.php');
        exit();
    }

    $action_item_id = '';
    $schedule_period = '';
    $schedule_base = '';

    // Set properties differently based on the selected action.
    switch ($liveform->get_field_value('action')) {
        case 'calendar_event_reserved':
            $action_item_id = $liveform->get_field_value('calendar_event_id');
            $schedule_period = $liveform->get_field_value('schedule_period');
            $schedule_base = $liveform->get_field_value('schedule_base');
            break;

        case 'custom_form_submitted':
            $action_item_id = $liveform->get_field_value('custom_form_page_id');
            $schedule_period = 'after';
            $schedule_base = 'action';
            break;

        case 'email_campaign_sent':
            $action_item_id = $liveform->get_field_value('email_campaign_profile_id');
            $schedule_period = 'after';
            $schedule_base = 'action';
            break;

        case 'order_abandoned':
        case 'order_completed':
        case 'order_shipped':
            $action_item_id = '0';
            $schedule_period = 'after';
            $schedule_base = 'action';
            break;

        case 'product_ordered':
            $action_item_id = $liveform->get_field_value('product_id');
            $schedule_period = 'after';
            $schedule_base = 'action';
            break;
    }

    // If the user set the schedule time to "12:00 AM", then force the time to 12:01 AM,
    // because when 12:00 AM is stored in the database, then we assume that means they don't want a schedule time.
    if (mb_strtolower($liveform->get_field_value('schedule_time')) == '12:00 am') {
        $schedule_time = '00:01:00';

    // Otherwise the user did not set the schedule to "12:00 AM", so prepare value like normal.
    } else {
        $schedule_time = prepare_form_data_for_input($liveform->get_field_value('schedule_time'), 'time');
    }
    
    // create email campaign profile
    db(
        "INSERT INTO email_campaign_profiles (
            name,
            enabled,
            action,
            action_item_id,
            subject,
            format,
            body,
            page_id,
            from_name,
            from_email_address,
            reply_email_address,
            bcc_email_address,
            schedule_time,
            schedule_length,
            schedule_unit,
            schedule_period,
            schedule_base,
            purpose,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('enabled')) . "',
            '" . escape($liveform->get_field_value('action')) . "',
            '" . escape($action_item_id) . "',
            '" . escape($liveform->get_field_value('subject')) . "',
            '" . escape($liveform->get_field_value('format')) . "',
            '" . escape($liveform->get_field_value('body')) . "',
            '" . escape($liveform->get_field_value('page_id')) . "',
            '" . escape($liveform->get_field_value('from_name')) . "',
            '" . escape($liveform->get_field_value('from_email_address')) . "',
            '" . escape($liveform->get_field_value('reply_email_address')) . "',
            '" . escape($liveform->get_field_value('bcc_email_address')) . "',
            '" . escape($schedule_time) . "',
            '" . escape($liveform->get_field_value('schedule_length')) . "',
            '" . escape($liveform->get_field_value('schedule_unit')) . "',
            '" . escape($schedule_period) . "',
            '" . escape($schedule_base) . "',
            '" . e($liveform->get('purpose')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())");
    
    log_activity(lang(array('string'=>'campaign profile ({var:1}) was created','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
    $liveform_view_email_campaign_profiles->add_notice(lang('The campaign profile has been created.'));

    // Forward user to view email campaign profiles screen.
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
    exit();
    
   
}
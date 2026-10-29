<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The campaign profile form, shared by the create and the edit screens
 * (add_email_campaign_profile.php, edit_email_campaign_profile.php): the
 * five field cards, the validation of a posted profile and the column
 * values it is saved with. The screens keep what differs: the edit screen
 * loads the record, checks the owner, fills the fields and offers delete;
 * the create screen prefills the plain text footer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The actions that need commerce access (ECOMMERCE and USER_MANAGE_ECOMMERCE).
 *
 * @return array
 */
function pg_email_campaign_profile_commerce_actions()
{
    return array(
        'order_abandoned',
        'order_completed',
        'order_review_request',
        'order_shipped',
        'order_delivered',
        'product_ordered',
    );
}

/**
 * The form field that holds the record an action is tied to; the profile
 * stores it in action_item_id.
 *
 * @param string $action
 * @return string  The field name, '' when the action is tied to no record
 */
function pg_email_campaign_profile_action_item_field($action)
{
    switch ($action) {
        case 'calendar_event_reserved':
            return 'calendar_event_id';

        case 'custom_form_submitted':
            return 'custom_form_page_id';

        case 'email_campaign_sent':
            return 'email_campaign_profile_id';

        case 'product_ordered':
            return 'product_id';
    }

    return '';
}

/**
 * Which of the action-dependent rows the form opens with: the item picker
 * of the action, and either the before/after + base pickers (calendar event
 * reserved) or the fixed "after action" label (every other action, and no
 * action yet).
 *
 * @param string $action
 * @return array  'calendar_event_id', 'custom_form_page_id',
 *                'email_campaign_profile_id', 'product_id',
 *                'calendar_schedule', 'standard_schedule' => 'show' or ''
 */
function pg_email_campaign_profile_action_rows($action)
{
    $rows = array(
        'calendar_event_id' => '',
        'custom_form_page_id' => '',
        'email_campaign_profile_id' => '',
        'product_id' => '',
        'calendar_schedule' => '',
        'standard_schedule' => 'show',
    );

    $item_field = pg_email_campaign_profile_action_item_field($action);

    if ($item_field != '') {
        $rows[$item_field] = 'show';
    }

    if ($action == 'calendar_event_reserved') {
        $rows['calendar_schedule'] = 'show';
        $rows['standard_schedule'] = '';
    }

    return $rows;
}

/**
 * The action_item_id, schedule_period and schedule_base a profile is saved
 * with. Only a calendar event reservation takes its period and base from
 * the form; every other action is sent after the action.
 *
 * @param string $action
 * @param array  $fields  Form field name => posted value (the item fields
 *                        of the actions, schedule_period, schedule_base)
 * @return array  'action_item_id', 'schedule_period', 'schedule_base'
 */
function pg_email_campaign_profile_action_values($action, $fields)
{
    $values = array(
        'action_item_id' => '',
        'schedule_period' => '',
        'schedule_base' => '',
    );

    $item_field = pg_email_campaign_profile_action_item_field($action);

    if ($item_field != '') {
        $values['action_item_id'] = isset($fields[$item_field]) ? (string) $fields[$item_field] : '';
    } elseif (in_array($action, pg_email_campaign_profile_commerce_actions(), true)) {
        $values['action_item_id'] = '0';
    } else {
        return $values;
    }

    if ($action == 'calendar_event_reserved') {
        $values['schedule_period'] = isset($fields['schedule_period']) ? (string) $fields['schedule_period'] : '';
        $values['schedule_base'] = isset($fields['schedule_base']) ? (string) $fields['schedule_base'] : '';
    } else {
        $values['schedule_period'] = 'after';
        $values['schedule_base'] = 'action';
    }

    return $values;
}

/**
 * The schedule time as stored. 00:00:00 means "send at any time", so a
 * chosen 12:00 AM is stored as 00:01:00.
 *
 * @param string $time  As typed, e.g. "9:30 AM", or ''
 * @return string
 */
function pg_email_campaign_profile_schedule_time($time)
{
    if (mb_strtolower((string) $time) == '12:00 am') {
        return '00:01:00';
    }

    return prepare_form_data_for_input($time, 'time');
}

/**
 * The five field cards of the form, wrapped in their row.
 *
 * @param liveform $liveform
 * @param array    $user        The signed-in user (validate_user())
 * @param int      $product_id  The stored product, kept in the product
 *                              picker even when it is no longer offered
 *                              (0 on the create screen)
 * @return string  HTML
 */
function pg_email_campaign_profile_form_cards($liveform, $user, $product_id = 0)
{
    $rows = pg_email_campaign_profile_action_rows($liveform->get_field_value('action'));

    $output_body_preview_iframe_source = '';

    // With the HTML format and a body page chosen, the preview shows that page.
    if (
        ($liveform->get_field_value('format') == 'html')
        && ($liveform->get_field_value('page_id') != '')
    ) {
        $page_name = get_page_name($liveform->get_field_value('page_id'));

        if ($page_name != '') {
            $output_body_preview_iframe_source = OUTPUT_PATH . h(encode_url_path($page_name)) . '?edit=no&amp;email=true';
        }
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
        $action_options[lang('Order Review Request')] = 'order_review_request';
        $action_options[lang('Order Delivered')] = 'order_delivered';
        $action_options[lang('Order Shipped')] = 'order_shipped';
        $action_options[lang('Product Ordered')] = 'product_ordered';

        $output_product_id_row =
            '<div class="col-12 md:col-6 lg:col-4 my-3 collapse ' . $rows['product_id'] . '" id="product_id_row">
                <label for="product_id" class="form-label">' . lang('Product') . '</label>
                ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options(true, $product_id) )) . '
                <div class="invalid-feedback">' . lang('Required Area') . '</div>
            </div>';
    }

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'enabled', 'name'=>'enabled', 'value'=>'1', 'checked'=>'checked', 'class'=>'no-bs')) . '</div>
                                                <label class="" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'size'=>'60', 'maxlength'=>'100', 'placeholder'=>lang('Campaign Profile Name'), 'class'=>'form-control')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="action" class="form-label">' . lang('Action') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'action', 'id'=>'action', 'class'=>'form-control', 'options'=>$action_options, 'onchange'=>'change_email_campaign_profile_action()')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3 collapse ' . $rows['calendar_event_id'] . '" id="calendar_event_id_row">
                                            <label for="calendar_event_id" class="form-label">' . lang('Calendar Event') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'calendar_event_id', 'id'=>'calendar_event_id', 'class'=>'form-control', 'options'=>get_calendar_event_options(array('reservations' => true)) )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3 collapse ' . $rows['custom_form_page_id'] . '" id="custom_form_page_id_row">
                                            <label for="custom_form_page_id" class="form-label">' . lang('Custom Form') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'custom_form_page_id', 'id'=>'custom_form_page_id', 'class'=>'form-control', 'options'=>get_page_options('', 'custom form') )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3 collapse ' . $rows['email_campaign_profile_id'] . '" id="email_campaign_profile_id_row">
                                            <label for="email_campaign_profile_id" class="form-label">' . lang('Campaign Profile for Campaign that was sent') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'email_campaign_profile_id', 'id'=>'email_campaign_profile_id', 'class'=>'form-control', 'options'=>get_email_campaign_profile_options() )) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        ' . $output_product_id_row . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('E-Mail Message') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label for="subject" class="form-label">' . lang('Subject') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'subject', 'id'=>'subject', 'placeholder'=>lang('Subject'), 'maxlength'=>'255', 'class'=>'form-control')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <fieldset>
                                            <legend class="form-label fs-md">' . lang('Format') . '</legend>
                                            <div class="form-field  d-inline-grid me-5">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'format_plain_text', 'name'=>'format', 'value'=>'plain_text', 'checked'=>'checked', 'class'=>'radio collapse-switcher', 'data-bs-target' => '#email_format_plain_text_row')) . '
                                                <label for="format_plain_text">' . lang('Plain Text') . '</label>
                                            </div>
                                            <div class="form-field  d-inline-grid me-5">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'format_html', 'name'=>'format', 'value'=>'html', 'class'=>'radio collapse-switcher', 'data-bs-target' => '#email_format_html_row')) . '
                                                <label for="format_html">' . lang('HTML') . '</label>
                                            </div>
                                            </fieldset>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="email_format_plain_text_row">
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
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100"  id="email_format_html_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 sm:col-8 my-1">
                                                            <label class="form-label" for="page_id">' . lang('Page') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'page_id', 'name'=>'page_id', 'class'=>'form-control collapse-if-selected', 'options'=>get_page_options('', '', 'view'), 'data-bs-target'=>'#body_preview_row', 'onchange'=>'document.getElementById(\'body_preview_iframe\').src = \'' . OUTPUT_PATH . '\' + this.options[this.selectedIndex].firstChild.nodeValue + \'?edit=no&email=true\';')) . '
                                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="body_preview_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-1">
                                                                        <div style="margin-bottom: 1em">' . lang('Body Preview') . '</div>
                                                                        <iframe id="body_preview_iframe" title="' . h(lang('Body Preview')) . '" src="' . $output_body_preview_iframe_source . '" style="min-width:250px;width: 100%;max-width:100%;min-height:300px; height: 500px;resize: both;overflow: auto;"></iframe>
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
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('E-Mail Message From') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-6 xl:col-12 my-3">
                                            <label class="form-label" for="from_name">' . lang('From Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text','value'=>h(ORGANIZATION_NAME), 'name'=>'from_name', 'id'=>'from_name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row">
                                                <div class="col-12 sm:col-6 xl:col-4 my-3">
                                                    <label class="form-label" for="bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text','value'=>'', 'name'=>'bcc_email_address', 'id'=>'bcc_email_address', 'class'=>'form-control text-end', 'maxlength'=>'100', 'inputmode'=>'email', 'data-inputmask-alias'=>'email')) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 sm:col-6 xl:col-4 my-3">
                                                    <label class="form-label" for="from_email_address">' . lang('From E-mail Address') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text','value'=>h($user['email_address']), 'name'=>'from_email_address', 'id'=>'from_email_address', 'class'=>'form-control text-end', 'maxlength'=>'100', 'inputmode'=>'email', 'data-inputmask-alias'=>'email')) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 sm:col-6 xl:col-4 my-3">
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
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('E-Mail Message Delivery Schedule') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-label">' . lang('Send e-mail') . '</div>
                                            <div class="input-group mb-5 ">
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'schedule_length', 'aria-label'=>lang('Send e-mail'), 'class'=>'form-control text-end', 'value'=>'1', 'size'=>'3', 'maxlength'=>'9', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal', 'data-inputmask-digits'=>'9', 'data-inputmask-placeholder'=>'1' )) . '
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'schedule_unit', 'aria-label'=>lang('Day(s)/Hour(s)'), 'class'=>'form-control', 'options'=>array(lang('day(s)') => 'days', lang('hour(s)') => 'hours'))) . '
                                                ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control collapse ' . $rows['calendar_schedule'], 'name'=>'schedule_period', 'aria-label'=>lang('Before/After'), 'id'=>'schedule_period', 'options'=>array('' => '', lang('before') => 'before', lang('after') => 'after'))) . '
                                                ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control collapse ' . $rows['calendar_schedule'], 'name'=>'schedule_base', 'aria-label'=>lang('From Action/From Calendar event start time'), 'id'=>'schedule_base', 'options'=>array('' => '', lang('from action') => 'action', lang('from calendar event start time') => 'calendar_event_start_time'))) . '
                                                <span class="collapse input-group-text ' . $rows['standard_schedule'] . '" id="standard_schedule_period_and_base">&nbsp;' . lang('after action') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label class="form-label" for="schedule_time">' . lang('Send at a specific time') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'schedule_time', 'id'=>'schedule_time', 'class'=>'form-control', 'maxlength'=>'8', 'data-pg-time' => '')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            <div class="text-end form-text">' . ((pg_time_format_code() === 'H:i') ? lang('HH:mm, leave blank to send at any time.') : lang('h:mm AM/PM, leave blank to send at any time.')) . '</div>
                                            ' . get_time_picker_format() . pg_fields_script() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-5">
                                            <fieldset>
                                            <legend class="form-label fs-md">' . lang('Purpose') . '</legend>
                                            <div class="form-field">
                                                ' . $liveform->output_field(array(
                                                    'type' => 'radio',
                                                    'id' => 'purpose_commercial',
                                                    'name' => 'purpose',
                                                    'value' => 'commercial',
                                                    'checked' => 'checked',
                                                    'class' => 'radio')) . '
                                                <label class="" for="purpose_commercial">' . lang('Commercial') . ' (' . lang('send email to opted-in contacts only. Example: \'We have an offer for you\'') . ')</label>
                                            </div>
                                            <div class="form-field">
                                                ' . $liveform->output_field(array(
                                                    'type' => 'radio',
                                                    'id' => 'purpose_transactional',
                                                    'name' => 'purpose',
                                                    'value' => 'transactional',
                                                    'class' => 'radio')) . '
                                                <label class="" for="purpose_transactional">' . lang('Transactional') . ' (' . lang('send email, regardless of opt-in. Example: \'Your order has been shipped\'') . ')</label>
                                            </div>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Validates the posted profile and marks every error on the liveform.
 *
 * @param liveform $liveform
 * @param int      $profile_id  The profile being edited, 0 for a new one
 *                              (its own name is no duplicate)
 * @return void
 */
function pg_email_campaign_profile_form_validate($liveform, $profile_id = 0)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))));

    // If there is not already an error for the name field, and that name is already in use, then output error.
    if ($liveform->check_field_error('name') == false) {
        $name_query = "SELECT COUNT(*) FROM email_campaign_profiles WHERE name = '" . escape($liveform->get_field_value('name')) . "'";

        if ($profile_id) {
            $name_query .= " AND id != '" . (int) $profile_id . "'";
        }

        if (db_value($name_query) != 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }

    // If a commerce action was selected, and the user is not allowed to select a commerce action,
    // then clear value in order to generate an error.
    if (
        in_array($liveform->get('action'), pg_email_campaign_profile_commerce_actions(), true)
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
        && (validate_time($liveform->get_field_value('schedule_time')) == false)
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
}

/**
 * The column values a validated form is saved with, in table order.
 *
 * @param liveform $liveform
 * @return array  Column name => string value
 */
function pg_email_campaign_profile_form_columns($liveform)
{
    // A value posted as an array is saved as '' rather than "Array".
    $field = function ($name) use ($liveform) {
        $value = $liveform->get_field_value($name);
        return is_scalar($value) ? (string) $value : '';
    };

    $action = $field('action');

    $action_values = pg_email_campaign_profile_action_values($action, array(
        'calendar_event_id' => $field('calendar_event_id'),
        'custom_form_page_id' => $field('custom_form_page_id'),
        'email_campaign_profile_id' => $field('email_campaign_profile_id'),
        'product_id' => $field('product_id'),
        'schedule_period' => $field('schedule_period'),
        'schedule_base' => $field('schedule_base'),
    ));

    return array(
        'name' => $field('name'),
        'enabled' => $field('enabled'),
        'action' => $action,
        'action_item_id' => $action_values['action_item_id'],
        'subject' => $field('subject'),
        'format' => $field('format'),
        'body' => $field('body'),
        'page_id' => $field('page_id'),
        'from_name' => $field('from_name'),
        'from_email_address' => $field('from_email_address'),
        'reply_email_address' => $field('reply_email_address'),
        'bcc_email_address' => $field('bcc_email_address'),
        'schedule_time' => (string) pg_email_campaign_profile_schedule_time($field('schedule_time')),
        'schedule_length' => $field('schedule_length'),
        'schedule_unit' => $field('schedule_unit'),
        'schedule_period' => $action_values['schedule_period'],
        'schedule_base' => $action_values['schedule_base'],
        'purpose' => $field('purpose'),
    );
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The e-mail campaign form parts shared by the create and the edit screens
 * (add_email_campaign.php, edit_email_campaign.php): the screen layout (form
 * column, sticky status column, sticky save bar), the message card with its
 * Subject and Format rows, the sender card, and the delivery schedule and
 * CAN-SPAM purpose cards of the status column. A read-only flag prints the
 * stored values as text, for a completed campaign. The screens keep what differs: the
 * format choice and body, the recipients, the status and progress boxes,
 * every query, the INSERT/UPDATE and the redirect.
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
 * One card of the campaign form, in a full-width column of the row it sits
 * in (the form column's or the status column's).
 *
 * @param string $title  Translated, escaped card title
 * @param string $body   Markup inside the card body's row
 * @return string
 */
function pg_email_campaign_card($title, $body)
{
    return
        '<div class="col-12">
            <div class="card mb-7">
                <h2 class="card-header bg-reset border-0 pg-title-section">
                    ' . $title . '
                </h2>
                <div class="card-body pt-0">
                    <div class="row">
                        ' . $body . '
                    </div>
                </div>
            </div>
        </div>';
}

/**
 * The screen row: the form column with the cards the operator fills, the
 * status column (written after it, so on a narrow screen the form comes
 * first) with a sticky box of what is read, and the sticky save bar.
 *
 * @param string $form     Cards of the form column
 * @param string $side     Cards of the status column; '' leaves it out
 * @param string $buttons  Buttons of the save bar; '' leaves it out
 *                         (a completed campaign is only read)
 * @return string
 */
function pg_email_campaign_layout($form, $side, $buttons)
{
    $html =
        '<div class="row">
            <div class="col-12 ' . (($side !== '') ? 'lg:col-8 2xl:col-9 ' : '') . 'order-1">
                <div class="row">
                    ' . $form . '
                </div>
            </div>';

    if ($side !== '') {
        $html .= '
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="row">
                        ' . $side . '
                    </div>
                </div>
            </div>';
    }

    if ($buttons !== '') {
        $html .= '
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            ' . $buttons . '
                        </div>
                    </div>
                </nav>
            </div>';
    }

    return $html . '
        </div>';
}

/**
 * The Subject row: an input, or the stored subject as text.
 *
 * @param string $subject
 * @param bool   $readonly
 * @return string
 */
function pg_email_campaign_subject_row($subject, $readonly = false)
{
    if ($readonly) {
        $field = '<p>' . h($subject) . '</p>';
    } else {
        $field = '<input value="' . h($subject) . '" type="text" name="subject" placeholder="' . lang('Subject') . '" maxlength="255" id="subject" class="form-control" required />';
    }

    // A read-only value has no field, so its heading is no label.
    $heading = $readonly
        ? '<div class="form-label">' . lang('Subject') . '</div>'
        : '<label for="subject" class="form-label">' . lang('Subject') . '</label>';

    return
        '<div class="col-12 my-3">
            ' . $heading . '
            ' . $field . '
        </div>';
}

/**
 * The Format row. Create passes its format radios with the body rows they
 * open; edit passes the stored format's name with $fixed, as a format
 * cannot change after creation.
 *
 * @param string $content  Choice markup, or the translated format name
 * @param bool   $fixed
 * @return string
 */
function pg_email_campaign_format_row($content, $fixed = false)
{
    if ($fixed) {
        return
            '<div class="col-12 my-3">
                <div class="form-label">' . lang('Format') . '</div>
                <p>' . $content . '</p>
            </div>';
    }

    return
        '<div class="col-12 my-1">
            <fieldset>
                <legend class="form-label fs-md">' . lang('Format') . '</legend>
                ' . $content . '
            </fieldset>
        </div>';
}

/**
 * The 'E-Mail Message' card: Subject, Format and what the screen adds
 * after them (edit's body).
 *
 * @param string $subject_row  pg_email_campaign_subject_row()
 * @param string $format_row   pg_email_campaign_format_row()
 * @param string $extra        Rows printed after Format
 * @return string
 */
function pg_email_campaign_message_card($subject_row, $format_row, $extra = '')
{
    return pg_email_campaign_card(lang('E-Mail Message'), $subject_row . $format_row . $extra);
}

/**
 * The 'E-Mail Message From' card: from name, from address, reply-to address.
 *
 * @param array $values    from_name, from_email_address, reply_email_address
 * @param bool  $readonly
 * @return string
 */
function pg_email_campaign_from_card($values, $readonly = false)
{
    $values += array(
        'from_name' => '',
        'from_email_address' => '',
        'reply_email_address' => '',
    );

    $fields = array();

    foreach (array('from_name', 'from_email_address', 'reply_email_address') as $name) {
        if ($readonly) {
            $fields[$name] = '<p>' . h($values[$name]) . '</p>';
        } elseif ($name == 'from_name') {
            $fields[$name] = '<input value="' . h($values[$name]) . '" type="text" class="form-control" id="from_name" name="from_name" maxlength="100" />';
        } else {
            $fields[$name] = '<input value="' . h($values[$name]) . '" type="text" class="form-control text-end" id="' . $name . '" name="' . $name . '" maxlength="100"' . (($name == 'from_email_address') ? ' required' : '') . ' inputmode="email" data-inputmask-alias="email"/>';
        }
    }

    // A read-only value has no field, so its heading is no label.
    $label = function ($for, $text) use ($readonly) {
        return $readonly
            ? '<div class="form-label">' . $text . '</div>'
            : '<label class="form-label" for="' . $for . '">' . $text . '</label>';
    };

    return pg_email_campaign_card(
        lang('E-Mail Message From'),
        '<div class="col-12 md:col-4 my-3">
            ' . $label('from_name', lang('From Name')) . '
            ' . $fields['from_name'] . '
        </div>
        <div class="col-12 md:col-4 my-3">
            ' . $label('from_email_address', lang('From E-mail Address')) . '
            ' . $fields['from_email_address'] . '
        </div>
        <div class="col-12 md:col-4 my-3">
            ' . $label('reply_email_address', lang('Reply to E-mail Address')) . '
            ' . $fields['reply_email_address'] . '
        </div>');
}

/**
 * The 'Delivery Schedule' card with the start time, printed only when the
 * e-mail campaign job runs on the server (else a campaign is sent by hand).
 *
 * @param string    $start_time  Stored 'Y-m-d H:i:s' value, '' for none
 * @param bool      $readonly
 * @param bool|null $enabled     null asks email_campaign_job_enabled()
 * @return string
 */
function pg_email_campaign_schedule_card($start_time, $readonly = false, $enabled = null)
{
    if ($enabled === null) {
        $enabled = email_campaign_job_enabled();
    }

    if (!$enabled) {
        return '';
    }

    if ($start_time == '0000-00-00 00:00:00') {
        $start_time = '';
    }

    if ($readonly) {
        if ($start_time == '') {
            $field = '<p>' . lang('Immediately') . '</p>';
        } else {
            $field = '<p>' . prepare_form_data_for_output($start_time, 'date and time') . '</p>';
        }
    } else {
        $field =
            '<input value="' . prepare_form_data_for_output($start_time, 'date and time') . '" type="text" maxlength="19" class="form-control" id="start_time" name="start_time" data-pg-datetime />
            <div class="text-end form-text">' . lang('Leave blank to send as soon as possible.') . '</div>
            ' . get_date_time_picker_format() . pg_fields_script();
    }

    return pg_email_campaign_card(
        lang('E-Mail Message Delivery Schedule'),
        '<div class="col-12 my-3">
            ' . ($readonly ? '<div class="form-label">' . lang('Send at this Date & Time') . '</div>' : '<label class="form-label" for="start_time">' . lang('Send at this Date & Time') . '</label>') . '
            ' . $field . '
        </div>');
}

/**
 * The Purpose card (as defined by the CAN-SPAM Act): commercial or
 * transactional radios, or the stored purpose as text.
 *
 * @param string $purpose   'commercial'; anything else checks transactional
 * @param bool   $readonly
 * @return string
 */
function pg_email_campaign_purpose_card($purpose, $readonly = false)
{
    if ($readonly) {
        $field = '<div class="form-label">' . lang('Purpose') . '</div>
            <p>' . lang(h(ucwords($purpose))) . '</p>';
    } else {
        $commercial = ($purpose == 'commercial') ? ' checked="checked"' : '';
        $transactional = ($purpose == 'commercial') ? '' : ' checked="checked"';

        $field =
            '<fieldset>
            <legend class="visually-hidden">' . lang('Purpose') . '</legend>
            <div class="form-field mb-3">
                <input class="radio" type="radio" name="purpose" id="purpose_commercial" value="commercial"' . $commercial . ' aria-describedby="purpose_commercial_help">
                <label class="" for="purpose_commercial">' . lang('Commercial') . '</label>
                <div class="form-text" id="purpose_commercial_help">' . lang('Send e-mail to opted-in contacts only. Example: \'We have an offer for you\'') . '</div>
            </div>
            <div class="form-field">
                <input class="radio" type="radio" name="purpose" id="purpose_transactional" value="transactional"' . $transactional . ' aria-describedby="purpose_transactional_help">
                <label class="" for="purpose_transactional">' . lang('Transactional') . '</label>
                <div class="form-text" id="purpose_transactional_help">' . lang('Send e-mail regardless of opt-in. Example: \'Your order has been shipped\'') . ' ' . lang('Use transactional only for real transaction and relationship notices (service outage, terms change, delivery schedule); promotional text is sent as commercial.') . '</div>
            </div>
            </fieldset>';
    }

    return pg_email_campaign_card(
        lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')',
        '<div class="col-12 my-3">
            ' . $field . '
        </div>');
}

/**
 * The field problems of a posted campaign, keyed by field name: Subject and
 * From E-mail Address are required, the sender, reply-to and BCC addresses
 * must be addresses. Reply-to and BCC may be blank; BCC is checked only when
 * it is posted (automatic campaigns). A value sent as an array counts as not
 * given.
 *
 * @param array $values  subject, from_email_address, reply_email_address,
 *                       bcc_email_address (optional)
 * @return array  field name => translated message; empty when all pass
 */
function pg_email_campaign_field_errors($values)
{
    $text = function ($name) use ($values) {
        return (isset($values[$name]) && is_scalar($values[$name])) ? trim((string) $values[$name]) : '';
    };

    $errors = array();

    if ($text('subject') === '') {
        $errors['subject'] = lang('Enter a subject.');
    }

    if ($text('from_email_address') === '') {
        $errors['from_email_address'] = lang('Enter the sender e-mail address.');
    } elseif (!validate_email_address($text('from_email_address'))) {
        $errors['from_email_address'] = lang('The sender e-mail address is not valid.');
    }

    if (($text('reply_email_address') !== '') && !validate_email_address($text('reply_email_address'))) {
        $errors['reply_email_address'] = lang('The reply-to e-mail address is not valid.');
    }

    if (array_key_exists('bcc_email_address', $values) && ($text('bcc_email_address') !== '') && !validate_email_address($text('bcc_email_address'))) {
        $errors['bcc_email_address'] = lang('The BCC e-mail address is not valid.');
    }

    return $errors;
}

/**
 * A posted text field (from name, body, send_to) as a string: a missing field
 * or one posted as an array (from_name[]=x) is ''.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_email_campaign_posted_text($post, $name)
{
    return (isset($post[$name]) && is_string($post[$name])) ? $post[$name] : '';
}

/**
 * A posted status, format or purpose when the form offers it, else null.
 * The status column also holds 'complete', which only sending sets.
 *
 * @param string $kind   status, format or purpose
 * @param mixed  $value
 * @return string|null
 */
function pg_email_campaign_allowed_value($kind, $value)
{
    $allowed = array(
        'status' => array('ready', 'paused', 'cancelled'),
        'format' => array('plain_text', 'html'),
        'purpose' => array('commercial', 'transactional'),
    );

    if (!is_string($value) || !isset($allowed[$kind]) || !in_array($value, $allowed[$kind], true)) {
        return null;
    }

    return $value;
}

/**
 * The field problems as one error text for the screen, each problem on its
 * own line, with a way back to the form.
 *
 * @param array $errors  pg_email_campaign_field_errors()
 * @return string HTML
 */
function pg_email_campaign_field_errors_html($errors)
{
    $items = '';

    foreach ($errors as $message) {
        $items .= '<li>' . h($message) . '</li>';
    }

    return lang('Correct these fields and save again:') . '<ul class="mb-0">' . $items . '</ul> <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.';
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The e-mail campaign form parts shared by the create and the edit screens
 * (add_email_campaign.php, edit_email_campaign.php): the message card with
 * its Subject and Format rows, the sender card, the delivery schedule card
 * and the CAN-SPAM purpose card. A read-only flag prints the stored values
 * as text, for a completed campaign. The screens keep what differs: the
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
 * One full-width card of the campaign form.
 *
 * @param string $title  Translated, escaped card title
 * @param string $body   Markup inside the card body's row
 * @return string
 */
function pg_email_campaign_card($title, $body)
{
    return
        '<div class="col-12">
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    ' . $title . '
                </div>
                <div class="card-body">
                    <div class="row">
                        ' . $body . '
                    </div>
                </div>
            </div>
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
        $field = '<input value="' . h($subject) . '" type="text" name="subject" placeholder="' . lang('Subject') . '" maxlength="255" id="subject" class="form-control" />';
    }

    return
        '<div class="col-12 my-3">
            <label for="subject" class="form-label">' . lang('Subject') . '</label>
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
                <label class="form-label">' . lang('Format') . '</label>
                <p>' . $content . '</p>
            </div>';
    }

    return
        '<div class="col-12 my-1">
            <div class="col-12">
                <label class="form-label">' . lang('Format') . '</label>
            </div>
            ' . $content . '
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
            $fields[$name] = '<input value="' . h($values[$name]) . '" type="text" class="form-control" id="from_name" name="from_name" />';
        } else {
            $fields[$name] = '<input value="' . h($values[$name]) . '" type="text" class="form-control text-end" id="' . $name . '" name="' . $name . '" maxlength="100" inputmode="email" data-inputmask-alias="email"/>';
        }
    }

    return pg_email_campaign_card(
        lang('E-Mail Message From'),
        '<div class="col-12 sm:col-6 xl:col-12 my-3">
            <label class="form-label" for="from_name">' . lang('From Name') . '</label>
            ' . $fields['from_name'] . '
        </div>
        <div class="col-12">
            <div class="row">
                <div class="col-12 sm:col-6 xl:col-4 my-3">
                    <label class="form-label" for="from_email_address">' . lang('From E-mail Address') . '</label>
                    ' . $fields['from_email_address'] . '
                </div>
                <div class="col-12 sm:col-6 xl:col-4 my-3">
                    <label class="form-label" for="reply_email_address">' . lang('Reply to E-mail Address') . '</label>
                    ' . $fields['reply_email_address'] . '
                </div>
            </div>
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
        '<div class="col-12 md:col-6 lg:col-4 my-3">
            <label class="form-label" for="start_time">' . lang('Send at this Date & Time') . '</label>
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
        $field = '<p>' . lang(h(ucwords($purpose))) . '</p>';
    } else {
        $commercial = ($purpose == 'commercial') ? ' checked="checked"' : '';
        $transactional = ($purpose == 'commercial') ? '' : ' checked="checked"';

        $field =
            '<div class="form-field">
                <input class="radio" type="radio" name="purpose" id="purpose_commercial" value="commercial"' . $commercial . '>
                <label class="" for="purpose_commercial">' . lang('Commercial') . ' (' . lang('send email to opted-in contacts only. Example: \'We have an offer for you\'') . ')</label>
            </div>
            <div class="form-field">
                <input class="radio" type="radio" name="purpose" id="purpose_transactional" value="transactional"' . $transactional . '>
                <label class="" for="purpose_transactional">' . lang('Transactional') . ' (' . lang('send email, regardless of opt-in. Example: \'Your order has been shipped\'') . ')</label>
            </div>';
    }

    return pg_email_campaign_card(
        lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')',
        '<div class="col-12 my-5">
            <label class="form-label">' . lang('Purpose') . '</label>
            ' . $field . '
        </div>');
}

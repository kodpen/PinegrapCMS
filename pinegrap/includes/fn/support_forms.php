<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A custom form that opens a support request: once submitted, the record
 * becomes a conversation of a chosen Workspace support box, made through
 * the box's own intake (ws_mail_ingest()) from a mail built out of the
 * record, so its routes, rules, notices and automatic answer follow as for
 * a mail. The automatic answer's subject carries [#T<id>], so the customer's
 * reply threads back into the same conversation.
 *
 * Kept in connect_apps under 'support_forms' (not secret, no schema), one
 * entry per form page: the switch, the box, the field holding the
 * customer's address and an optional subject field. Opened when the form's
 * e-mails go (pg_cf_form_mails_send()), so a form that owes a fee opens it
 * once the record is paid.
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
 * The connect_apps key the forms' choices are kept under.
 *
 * @return string
 */
function pg_support_form_key()
{
    return 'support_forms';
}

/**
 * One form's stored choice, every key present (pure).
 *
 * @param array|null $options connect_apps options of 'support_forms'
 * @param int        $page_id
 * @return array on, box_id, email_field, subject_field (form field ids, 0 for none)
 */
function pg_support_form_normalize($options, $page_id)
{
    $forms = (is_array($options) && isset($options['forms']) && is_array($options['forms'])) ? $options['forms'] : array();
    $row = (isset($forms[(string) (int) $page_id]) && is_array($forms[(string) (int) $page_id])) ? $forms[(string) (int) $page_id] : array();
    $box_id = isset($row['box_id']) ? max(0, (int) $row['box_id']) : 0;
    $email_field = isset($row['email_field']) ? max(0, (int) $row['email_field']) : 0;

    return array(
        'on'            => !empty($row['on']) && ($box_id > 0) && ($email_field > 0),
        'box_id'        => $box_id,
        'email_field'   => $email_field,
        'subject_field' => isset($row['subject_field']) ? max(0, (int) $row['subject_field']) : 0,
    );
}

/**
 * The stored choices of every form.
 *
 * @return array|null connect_apps options of 'support_forms'
 */
function pg_support_form_options()
{
    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app(pg_support_form_key()) : null;

    return (is_array($app) && is_array($app['options'])) ? $app['options'] : null;
}

/**
 * The form's fields: neither an information block nor a file upload.
 *
 * @param int $page_id
 * @return array rows: id, label, name, type, contact_field
 */
function pg_support_form_fields($page_id)
{
    return (array) db_items(
        "SELECT id, label, name, type, contact_field
        FROM form_fields
        WHERE (page_id = '" . (int) $page_id . "') AND (form_type = 'custom') AND (type NOT IN ('information', 'file upload'))
        ORDER BY sort_order, id");
}

/**
 * A field as the lists name it: its label without markup or its colon, else
 * its name (pure).
 *
 * @param array $field
 * @return string
 */
function pg_support_form_field_label($field)
{
    $label = rtrim(trim(strip_tags((string) ($field['label'] ?? ''))), " :\t");

    return ($label !== '') ? $label : (string) ($field['name'] ?? '');
}

/**
 * The fields that may hold the customer's address (pure): e-mail address
 * fields and the field tied to the contact's address, that one first.
 *
 * @param array $fields pg_support_form_fields()
 * @return array field id => label
 */
function pg_support_form_email_choices($fields)
{
    $first = array();
    $rest = array();

    foreach ((array) $fields as $field) {
        if ((string) $field['contact_field'] === 'email_address') {
            $first[(int) $field['id']] = pg_support_form_field_label($field);
        } elseif ((string) $field['type'] === 'email address') {
            $rest[(int) $field['id']] = pg_support_form_field_label($field);
        }
    }

    return $first + $rest;
}

/**
 * The fields that may give the request its subject (pure): a short answer,
 * a text box or a single choice.
 *
 * @param array $fields pg_support_form_fields()
 * @return array field id => label
 */
function pg_support_form_subject_choices($fields)
{
    $choices = array();

    foreach ((array) $fields as $field) {
        if (in_array((string) $field['type'], array('text box', 'pick list', 'radio button'), true)) {
            $choices[(int) $field['id']] = pg_support_form_field_label($field);
        }
    }

    return $choices;
}

/**
 * Whether a mailbox can take a form's requests (pure): switched on, answered
 * by e-mail and a support box, which keeps state, holder and the automatic
 * answer (ws_mail_box_is_support(): a box saved before the switch is one).
 *
 * @param array $box ws_mail_boxes row
 * @return bool
 */
function pg_support_form_box_offered($box)
{
    if (empty($box['enabled']) || in_array((string) ($box['transport'] ?? ''), array('whatsapp', 'sms'), true)) {
        return false;
    }

    $settings = json_decode((string) ($box['settings'] ?? ''), true);

    return !is_array($settings) || !array_key_exists('support', $settings) || !empty($settings['support']);
}

/**
 * A box as the list names it (pure).
 *
 * @param array $box ws_mail_boxes row
 * @return string
 */
function pg_support_form_box_label($box)
{
    $name = trim((string) ($box['name'] ?? ''));
    $address = trim((string) ($box['address'] ?? ''));

    return ($name !== '') ? $name . ' (' . $address . ')' : $address;
}

/**
 * Whether the Workspace's mail tables are there to take a request.
 *
 * @return bool
 */
function pg_support_form_ready()
{
    if (!is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return ws_ready() && function_exists('ws_mail_ingest') && pg_schema_has('ws_mail_boxes', 'id');
}

/**
 * The boxes a form may open requests in.
 *
 * @return array box id => label
 */
function pg_support_form_boxes()
{
    if (!pg_support_form_ready()) {
        return array();
    }

    $boxes = array();

    foreach ((array) db_items("SELECT id, name, address, transport, settings, enabled FROM ws_mail_boxes ORDER BY name, id") as $box) {
        if (pg_support_form_box_offered($box)) {
            $boxes[(int) $box['id']] = pg_support_form_box_label($box);
        }
    }

    return $boxes;
}

/**
 * What a post of a form's support section asks for (pure).
 *
 * @param array $post
 * @param array $before pg_support_form_normalize()
 * @param array $boxes  pg_support_form_boxes()
 * @param array $fields pg_support_form_fields()
 * @return array|null row, changed, marks (field => sentence); null when the
 *                    post has no such section
 */
function pg_support_form_settings_decide($post, $before, $boxes, $fields)
{
    if (empty($post['support_form_card'])) {
        return null;
    }

    $number = function ($name) use ($post) {
        return (isset($post[$name]) && is_scalar($post[$name])) ? max(0, (int) $post[$name]) : 0;
    };
    $on = !empty($post['support_form_on']);
    $box_id = $number('support_form_box');
    $email_field = $number('support_form_email_field');
    $subject_field = $number('support_form_subject_field');
    $marks = array();

    if (($box_id > 0) && !isset($boxes[$box_id])) {
        $marks['support_form_box'] = lang('This mailbox is not a support box that is switched on.');
        $box_id = 0;
    } elseif ($on && ($box_id === 0)) {
        $marks['support_form_box'] = lang('Choose the support box the requests go to.');
    }

    if (($email_field > 0) && !isset(pg_support_form_email_choices($fields)[$email_field])) {
        $marks['support_form_email_field'] = lang('This field is not an e-mail address field of this form.');
        $email_field = 0;
    } elseif ($on && ($email_field === 0)) {
        $marks['support_form_email_field'] = lang('Choose the field that holds the customer\'s e-mail address.');
    }

    if (($subject_field > 0) && !isset(pg_support_form_subject_choices($fields)[$subject_field])) {
        $marks['support_form_subject_field'] = lang('This field cannot give the request its subject.');
        $subject_field = 0;
    }

    $row = array(
        'on'            => $on && ($box_id > 0) && ($email_field > 0),
        'box_id'        => $box_id,
        'email_field'   => $email_field,
        'subject_field' => $subject_field,
    );

    return array(
        'row'     => $row,
        'changed' => ($row !== $before),
        'marks'   => $marks,
    );
}

/**
 * Checks a form's support section before the page is saved: called beside
 * pg_page_form_number_errors() where a custom form page is saved.
 *
 * @param string $page_type
 * @param int    $page_id
 * @param array  $post
 * @return array field => sentence
 */
function pg_support_form_errors($page_type, $page_id, $post)
{
    if (($page_type !== 'custom form') || ((int) $page_id <= 0) || empty($post['support_form_card'])) {
        return array();
    }

    $decision = pg_support_form_settings_decide($post, pg_support_form_normalize(pg_support_form_options(), $page_id), pg_support_form_boxes(), pg_support_form_fields($page_id));

    return $decision['marks'];
}

/**
 * Writes a form's choice, only when it changed and was not refused.
 *
 * @param int   $page_id
 * @param array $post
 * @param int   $user_id
 */
function pg_support_form_save($page_id, $post, $user_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || empty($post['support_form_card']) || !function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return;
    }

    $options = pg_support_form_options();
    $decision = pg_support_form_settings_decide($post, pg_support_form_normalize($options, $page_id), pg_support_form_boxes(), pg_support_form_fields($page_id));

    if (!$decision['changed'] || $decision['marks']) {
        return;
    }

    $forms = (is_array($options) && isset($options['forms']) && is_array($options['forms'])) ? $options['forms'] : array();
    $forms[(string) $page_id] = $decision['row'];

    db("INSERT INTO connect_apps
        SET
            provider = '" . e(pg_support_form_key()) . "',
            options = '" . e(json_encode(array('forms' => $forms), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "',
            enabled = 1,
            updated_user_id = '" . (int) $user_id . "',
            updated_timestamp = UNIX_TIMESTAMP()
        ON DUPLICATE KEY UPDATE
            options = VALUES(options),
            updated_user_id = VALUES(updated_user_id),
            updated_timestamp = VALUES(updated_timestamp)");
}

/**
 * The support section of a custom form's settings, after the sender's
 * WhatsApp message. Drawn once the form has fields and the Workspace's
 * mail is there.
 *
 * @param int $page_id
 * @return string
 */
function pg_support_form_section_html($page_id)
{
    $page_id = (int) $page_id;
    $fields = ($page_id > 0) ? pg_support_form_fields($page_id) : array();

    if (!$fields || !pg_support_form_ready()) {
        return '';
    }

    $stored = pg_support_form_normalize(pg_support_form_options(), $page_id);
    $boxes = pg_support_form_boxes();
    $options = function ($choices, $chosen, $empty) {
        $html = '<option value="">' . h($empty) . '</option>';

        foreach ($choices as $id => $label) {
            $html .= '<option value="' . (int) $id . '"' . (((int) $id === (int) $chosen) ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
        }

        return $html;
    };
    $column = 'col-12 md:col-6 lg:col-4 my-3';
    $note = '';

    if (!$boxes) {
        $note = '
                    <div class="col-12 my-1"><div class="alert theme-info mb-0">' . h(lang('No support box can be chosen yet: add a mailbox with the Support box switch on in Site Settings, E-mail, Mailboxes.')) . '</div></div>';
    }

    return '
            <div class="col-12 my-3" id="support_form_row">
                <input type="hidden" name="support_form_card" value="1"/>
                <div class="form-field">
                    <div class="switch"><input value="1"' . ($stored['on'] ? ' checked="checked"' : '') . ' id="support_form_on" name="support_form_on" class="collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_support_form" /></div>
                    <label class="" for="support_form_on" id="support_form_label">' . h(lang('Support Request')) . '</label>
                </div>
                <div class="collapse border rounded p-5 mt-3 mb-3" id="show_or_hide_support_form" role="group" aria-labelledby="support_form_label">
                    <div class="row">
                        <div class="col-12 form-text my-1">' . h(lang('Each submission opens a request in the support box, which answers the customer by e-mail; their reply joins the same request.')) . '</div>' . $note . '
                        <div class="' . $column . '">
                            <label class="form-label" for="support_form_box">' . h(lang('Support Box')) . '</label>
                            <select name="support_form_box" id="support_form_box" class="form-control">' . $options($boxes, $stored['box_id'], lang('Choose a mailbox')) . '</select>
                        </div>
                        <div class="' . $column . '">
                            <label class="form-label" for="support_form_email_field">' . h(lang('E-mail Address Field')) . '</label>
                            <select name="support_form_email_field" id="support_form_email_field" class="form-control">' . $options(pg_support_form_email_choices($fields), $stored['email_field'], lang('Choose a field')) . '</select>
                        </div>
                        <div class="' . $column . '">
                            <label class="form-label" for="support_form_subject_field">' . h(lang('Subject Field')) . '</label>
                            <select name="support_form_subject_field" id="support_form_subject_field" class="form-control">' . $options(pg_support_form_subject_choices($fields), $stored['subject_field'], lang('The form\'s name')) . '</select>
                        </div>
                    </div>
                </div>
            </div>';
}

/**
 * The request's subject (pure): the subject field's answer, else the form's
 * name and the record's reference code; at most 255 characters.
 *
 * @param string $answer
 * @param string $form_name
 * @param string $reference_code
 * @return string
 */
function pg_support_form_subject($answer, $form_name, $reference_code)
{
    $subject = trim(preg_replace('/\s+/u', ' ', (string) $answer));

    if ($subject === '') {
        $subject = trim(trim((string) $form_name) . ' ' . trim((string) $reference_code));
    }

    return mb_substr($subject, 0, 255);
}

/**
 * The request's text (pure): one line per answered field, a field answered
 * on several lines below its label, a check box group's answers joined,
 * then the record's reference code.
 *
 * @param array  $rows           form_data rows: form_field_id, data
 * @param array  $labels         field id => label
 * @param string $reference_code
 * @param string $reference_label
 * @return string
 */
function pg_support_form_body($rows, $labels, $reference_code, $reference_label)
{
    $answers = array();

    foreach ((array) $rows as $row) {
        $id = (int) $row['form_field_id'];
        $data = trim(str_replace("\r\n", "\n", (string) $row['data']));

        if (($data === '') || !isset($labels[$id])) {
            continue;
        }

        $answers[$id] = isset($answers[$id]) ? $answers[$id] . ', ' . $data : $data;
    }

    $lines = array();

    foreach ($answers as $id => $data) {
        $lines[] = $labels[$id] . ':' . ((strpos($data, "\n") !== false) ? "\n" : ' ') . $data;
    }

    $lines[] = '';
    $lines[] = $reference_label . ': ' . $reference_code;

    return implode("\n", $lines);
}

/**
 * The mail a request is taken in as (pure): plain text in UTF-8, the
 * customer as its sender, a Message-ID of its own so a second run for the
 * same record is refused as a duplicate. No header marks it automatic.
 *
 * @param array $mail from_email, from_name, to, subject, text, message_id
 *                    (without brackets), date (unix time)
 * @return string
 */
function pg_support_form_mail_raw($mail)
{
    $line = function ($value) {
        return trim(preg_replace('/[\r\n\t]+/', ' ', (string) $value));
    };
    $word = function ($value) use ($line) {
        $value = $line($value);

        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    };
    $from_email = $line($mail['from_email'] ?? '');
    $from_name = $line($mail['from_name'] ?? '');
    $from = ($from_name !== '')
        ? (preg_match('/[^\x20-\x7e]/', $from_name) ? $word($from_name) : '"' . addcslashes($from_name, '"\\') . '"') . ' <' . $from_email . '>'
        : $from_email;

    $headers = array(
        'From: ' . $from,
        'To: ' . $line($mail['to'] ?? ''),
        'Subject: ' . $word($mail['subject'] ?? ''),
        'Date: ' . date('r', (int) ($mail['date'] ?? time())),
        'Message-ID: <' . $line($mail['message_id'] ?? '') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    );

    return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode(str_replace("\r\n", "\n", (string) ($mail['text'] ?? ''))), 76, "\r\n");
}

/**
 * Who a request is tied to (pure): the signed-in user who sent the form and
 * the record's contact, each only when its address is the request's, so an
 * address typed for someone else reaches no one's account.
 *
 * @param string $address       the request's address
 * @param int    $user_id       the record's user
 * @param string $user_email    that user's address
 * @param int    $contact_id    the record's contact
 * @param string $contact_email that contact's address
 * @return array contact_id, user_id
 */
function pg_support_form_tie($address, $user_id, $user_email, $contact_id, $contact_email)
{
    $address = mb_strtolower(trim((string) $address));
    $same = function ($other) use ($address) {
        return ($address !== '') && ($address === mb_strtolower(trim((string) $other)));
    };

    return array(
        'contact_id' => (((int) $contact_id > 0) && $same($contact_email)) ? (int) $contact_id : 0,
        'user_id'    => (((int) $user_id > 0) && $same($user_email)) ? (int) $user_id : 0,
    );
}

/**
 * Opens a submitted form's support request. Quiet: a form switched off, a
 * box gone, switched off or no longer a support box, or an address that is
 * not one opens nothing and stops nothing; a request the intake refuses
 * writes an activity-log line.
 *
 * @param int $page_id
 * @param int $form_id the record (forms.id)
 * @return int the conversation's id, 0 when none was opened
 */
function pg_support_form_request($page_id, $form_id)
{
    $page_id = (int) $page_id;
    $form_id = (int) $form_id;
    $stored = pg_support_form_normalize(pg_support_form_options(), $page_id);

    if (!$stored['on'] || ($form_id <= 0) || !pg_support_form_ready()) {
        return 0;
    }

    $box = ws_mail_box($stored['box_id']);
    $record = db_item("SELECT id, user_id, contact_id, reference_code FROM forms WHERE id = '" . $form_id . "' AND page_id = '" . $page_id . "'");

    if (!$box || !pg_support_form_box_offered($box) || !$record) {
        return 0;
    }

    $rows = (array) db_items("SELECT form_field_id, data FROM form_data WHERE form_id = '" . $form_id . "' ORDER BY id");
    $answer = function ($field_id) use ($rows) {
        foreach ($rows as $row) {
            if (((int) $row['form_field_id'] === (int) $field_id) && (trim((string) $row['data']) !== '')) {
                return trim((string) $row['data']);
            }
        }

        return '';
    };
    $address = mb_strtolower($answer($stored['email_field']));

    if (!validate_email_address($address)) {
        return 0;
    }

    $fields = pg_support_form_fields($page_id);
    $labels = array();
    $name = '';

    foreach ($fields as $field) {
        $labels[(int) $field['id']] = pg_support_form_field_label($field);

        if (($name === '') && ((string) $field['contact_field'] === 'first_name')) {
            $name = $answer($field['id']);
        }
    }

    foreach ($fields as $field) {
        if (((string) $field['contact_field'] === 'last_name') && ($answer($field['id']) !== '')) {
            $name = trim($name . ' ' . $answer($field['id']));
        }
    }

    $user = ((int) $record['user_id'] > 0) ? db_item("SELECT user_email FROM user WHERE user_id = '" . (int) $record['user_id'] . "'") : null;
    $contact = ((int) $record['contact_id'] > 0) ? db_item("SELECT email_address FROM contacts WHERE id = '" . (int) $record['contact_id'] . "'") : null;
    $form_name = (string) db_value("SELECT form_name FROM custom_form_pages WHERE page_id = '" . $page_id . "'");
    $reference_code = (string) $record['reference_code'];

    $raw = pg_support_form_mail_raw(array(
        'from_email' => $address,
        'from_name'  => $name,
        'to'         => (string) $box['address'],
        'subject'    => pg_support_form_subject($stored['subject_field'] ? $answer($stored['subject_field']) : '', $form_name, $reference_code),
        'text'       => pg_support_form_body($rows, $labels, $reference_code, lang('Reference Code')),
        'message_id' => 'pg-form-' . $form_id . '@' . pg_mail_hostname(),
        'date'       => time(),
    ));

    try {
        $result = ws_mail_ingest($raw, $box, array(
            'tie' => pg_support_form_tie($address, (int) $record['user_id'], (string) ($user['user_email'] ?? ''), (int) $record['contact_id'], (string) ($contact['email_address'] ?? '')),
            // The request's list label leads back to the record.
            'source' => 'form',
            'source_id' => $form_id,
        ));
    } catch (Throwable $error) {
        $result = array('status' => 'refused', 'reason' => 'error', 'conversation_id' => 0);
    }

    if (!in_array($result['status'], array('stored', 'duplicate'), true)) {
        log_activity(pg_log_lang(array('string' => 'submitted form ({var:1}) opened no support request: {var:2}', 'vars' => array($reference_code, trim($result['status'] . ' ' . $result['reason'])))));

        return 0;
    }

    return (int) ($result['conversation_id'] ?? 0);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp message a custom form sends to whoever submitted it, beside
 * the sender's e-mail copy: which of the form's fields holds the number, one
 * of the account's approved templates, and which field fills each of its
 * variables.
 *
 * Kept in connect_apps under 'whatsapp_forms' (not secret, no schema), one
 * entry per form page. Off until the form's switch is turned on: every
 * message is paid for. A message is only queued (pg_whatsapp_enqueue()),
 * when the form's e-mails go (pg_cf_form_mails_send()), so a form that owes
 * a fee sends it when the record is paid, as it does its e-mails.
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
function pg_whatsapp_form_key()
{
    return 'whatsapp_forms';
}

/**
 * One form's stored choice, every key present.
 *
 * @param array|null $options connect_apps options of 'whatsapp_forms'
 * @param int        $page_id
 * @return array on, phone_field (form field id, 0 for none), template
 *               ('name|language'), vars (slot key => value)
 */
function pg_whatsapp_form_normalize($options, $page_id)
{
    $forms = (is_array($options) && isset($options['forms']) && is_array($options['forms'])) ? $options['forms'] : array();
    $row = (isset($forms[(string) (int) $page_id]) && is_array($forms[(string) (int) $page_id])) ? $forms[(string) (int) $page_id] : array();
    $vars = array();

    if (isset($row['vars']) && is_array($row['vars'])) {
        foreach ($row['vars'] as $slot => $value) {
            if (is_string($slot) && is_string($value) && ($value !== '')) {
                $vars[$slot] = $value;
            }
        }
    }

    $template = (isset($row['template']) && is_string($row['template'])) ? $row['template'] : '';
    $phone_field = isset($row['phone_field']) ? max(0, (int) $row['phone_field']) : 0;

    return array(
        'on'          => !empty($row['on']) && ($template !== '') && ($phone_field > 0),
        'phone_field' => $phone_field,
        'template'    => $template,
        'vars'        => $vars,
    );
}

/**
 * The stored choices of every form.
 *
 * @return array|null connect_apps options of 'whatsapp_forms'
 */
function pg_whatsapp_form_options()
{
    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app(pg_whatsapp_form_key()) : null;

    return (is_array($app) && is_array($app['options'])) ? $app['options'] : null;
}

/**
 * The form's fields a message can use: neither an information block nor a
 * file upload.
 *
 * @param int $page_id
 * @return array rows: id, label, name, type, contact_field
 */
function pg_whatsapp_form_fields($page_id)
{
    return (array) db_items(
        "SELECT id, label, name, type, contact_field
        FROM form_fields
        WHERE (page_id = '" . (int) $page_id . "') AND (form_type = 'custom') AND (type NOT IN ('information', 'file upload'))
        ORDER BY sort_order, id");
}

/**
 * A field as the lists name it: its label without its colon, else its name.
 *
 * @param array $field
 * @return string
 */
function pg_whatsapp_form_field_label($field)
{
    // A form's label usually ends with a colon; a list item does not.
    $label = rtrim(trim(strip_tags((string) ($field['label'] ?? ''))), " :\t");

    return ($label !== '') ? $label : (string) ($field['name'] ?? '');
}

/**
 * The fields that may hold the number (pure): text boxes, the one filling
 * the contact's mobile phone first.
 *
 * @param array $fields pg_whatsapp_form_fields()
 * @return array field id => label
 */
function pg_whatsapp_form_phone_choices($fields)
{
    $first = array();
    $rest = array();

    foreach ((array) $fields as $field) {
        if ((string) ($field['type'] ?? '') !== 'text box') {
            continue;
        }

        if ((string) ($field['contact_field'] ?? '') === 'mobile_phone') {
            $first[(int) $field['id']] = pg_whatsapp_form_field_label($field);
        } else {
            $rest[(int) $field['id']] = pg_whatsapp_form_field_label($field);
        }
    }

    return $first + $rest;
}

/**
 * The values a variable may take (pure): the store's name, the record's
 * reference code, or one of the form's fields.
 *
 * @param array $fields pg_whatsapp_form_fields()
 * @return array value => label
 */
function pg_whatsapp_form_value_choices($fields)
{
    $choices = array(
        'store'          => lang('Store name'),
        'reference_code' => lang('Reference code'),
    );

    foreach ((array) $fields as $field) {
        $choices['field:' . (int) $field['id']] = pg_whatsapp_form_field_label($field);
    }

    return $choices;
}

/**
 * What a post of a form's WhatsApp section asks for; no database.
 *
 * @param array $post
 * @param array $before  pg_whatsapp_form_normalize()
 * @param array $offered pg_whatsapp_message_offered()
 * @param array $fields  pg_whatsapp_form_fields()
 * @return array|null row, changed, marks (field => sentence); null when the
 *                    post has no such section
 */
function pg_whatsapp_form_settings_decide($post, $before, $offered, $fields)
{
    if (empty($post['wa_form_card'])) {
        return null;
    }

    $decided = pg_whatsapp_choice_decide($post, 'wa_form', $before, $offered, pg_whatsapp_form_value_choices($fields));
    $phones = pg_whatsapp_form_phone_choices($fields);
    $phone = (isset($post['wa_form_phone_field']) && is_scalar($post['wa_form_phone_field'])) ? (int) $post['wa_form_phone_field'] : 0;
    $marks = $decided['marks'];

    if (($phone > 0) && !isset($phones[$phone])) {
        $marks['wa_form_phone_field'] = lang('This field is not a text box of this form.');
        $phone = 0;
    }

    if (!empty($post['wa_form_on']) && ($phone === 0) && !isset($marks['wa_form_phone_field'])) {
        $marks['wa_form_phone_field'] = lang('Choose the field that holds the mobile number.');
    }

    $row = array(
        'on'          => $decided['row']['on'] && ($phone > 0),
        'phone_field' => $phone,
        'template'    => $decided['row']['template'],
        'vars'        => $decided['row']['vars'],
    );

    return array(
        'row'     => $row,
        'changed' => ($row !== $before),
        'marks'   => $marks,
    );
}

/**
 * Checks a form's WhatsApp section before the page is saved: called beside
 * pg_page_form_number_errors() where a custom form page is saved.
 *
 * @param string $page_type
 * @param int    $page_id
 * @param array  $post
 * @return array field => sentence
 */
function pg_whatsapp_form_errors($page_type, $page_id, $post)
{
    if (($page_type !== 'custom form') || ((int) $page_id <= 0) || empty($post['wa_form_card'])) {
        return array();
    }

    $decision = pg_whatsapp_form_settings_decide($post, pg_whatsapp_form_normalize(pg_whatsapp_form_options(), $page_id), pg_whatsapp_message_templates(), pg_whatsapp_form_fields($page_id));

    return $decision['marks'];
}

/**
 * Writes a form's choice, only when it changed and was not refused.
 *
 * @param int   $page_id
 * @param array $post
 * @param int   $user_id
 */
function pg_whatsapp_form_save($page_id, $post, $user_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || empty($post['wa_form_card']) || !pg_connect_schema_ready()) {
        return;
    }

    $options = pg_whatsapp_form_options();
    $decision = pg_whatsapp_form_settings_decide($post, pg_whatsapp_form_normalize($options, $page_id), pg_whatsapp_message_templates(), pg_whatsapp_form_fields($page_id));

    if (!$decision['changed'] || $decision['marks']) {
        return;
    }

    $forms = (is_array($options) && isset($options['forms']) && is_array($options['forms'])) ? $options['forms'] : array();
    $forms[(string) $page_id] = $decision['row'];

    db("INSERT INTO connect_apps
        SET
            provider = '" . e(pg_whatsapp_form_key()) . "',
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
 * The WhatsApp section of a custom form's settings, after the sender's
 * e-mail copy. Drawn once the form has fields: a new page has none yet.
 *
 * @param int $page_id
 * @return string
 */
function pg_whatsapp_form_section_html($page_id)
{
    $page_id = (int) $page_id;
    $fields = ($page_id > 0) ? pg_whatsapp_form_fields($page_id) : array();

    if (!$fields) {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    $stored = pg_whatsapp_form_normalize(pg_whatsapp_form_options(), $page_id);
    $offered = pg_whatsapp_message_templates();
    $phones = pg_whatsapp_form_phone_choices($fields);
    $column = 'col-12 sm:col-6 my-1';

    $phone_list = '<option value="">' . h(lang('Choose a field')) . '</option>';

    foreach ($phones as $id => $label) {
        $phone_list .= '<option value="' . (int) $id . '"' . (($id === $stored['phone_field']) ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
    }

    $notes = '';

    if (!pg_whatsapp_ready()) {
        $notes .= '
                            <div class="col-12 my-1"><div class="alert theme-warning mb-0">' . lang('WhatsApp is not set up: no message is sent until the WhatsApp account is saved and switched on in the Communication settings.') . '</div></div>';
    }

    if (!$offered) {
        $notes .= '
                            <div class="col-12 my-1"><div class="alert theme-info mb-0">' . lang('No approved template can be chosen yet. Templates are read from Meta every six hours and after the WhatsApp card is saved; one with an image, video or document header or a copy-code button cannot be used here.') . '</div></div>';
    }

    return '
            <div class="col-12 my-3" id="wa_form_row">
                <input type="hidden" name="wa_form_card" value="1"/>
                <div class="form-field">
                    <div class="switch"><input value="1"' . ($stored['on'] ? ' checked="checked"' : '') . ' id="wa_form_on" name="wa_form_on" class="collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_wa_form" /></div>
                    <label class="" for="wa_form_on" id="wa_form_label">' . lang('WhatsApp Message to Submitter') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="show_or_hide_wa_form" role="group" aria-labelledby="wa_form_label">
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 form-text my-1">' . lang('One approved WhatsApp template goes to the number typed in the field below once the form is submitted, beside the e-mail copy, or once its fee is paid. Choose a utility template: a marketing one goes only to a number with marketing consent.') . '</div>' . $notes . '
                            <div class="' . $column . '">
                                <label class="form-label" for="wa_form_phone_field">' . lang('Mobile Number Field') . '</label>
                                <select name="wa_form_phone_field" id="wa_form_phone_field" class="form-control">' . $phone_list . '</select>
                            </div>' . pg_whatsapp_choice_html('wa_form', $stored, $offered, pg_whatsapp_form_value_choices($fields), $column) . '
                        </div>
                    </div>
                </div>
            </div>' . pg_whatsapp_message_settings_script();
}

/**
 * The record's answers by value key (pure over the rows): a check box group
 * keeps one row per ticked box, so its answers are joined.
 *
 * @param array $rows form_data rows: form_field_id, data
 * @return array 'field:<id>' => text
 */
function pg_whatsapp_form_values($rows)
{
    $values = array();

    foreach ((array) $rows as $row) {
        $key = 'field:' . (int) $row['form_field_id'];
        $data = trim((string) $row['data']);

        if ($data === '') {
            continue;
        }

        $values[$key] = isset($values[$key]) ? $values[$key] . ', ' . $data : $data;
    }

    return $values;
}

/**
 * Queues a submitted form's WhatsApp message. Quiet: a form switched off,
 * WhatsApp not set up, a template no longer approved or a number WhatsApp
 * cannot take sends nothing and stops nothing.
 *
 * @param int $page_id
 * @param int $form_id the record (forms.id)
 * @return bool whether a message is queued (or already was)
 */
function pg_whatsapp_form_message($page_id, $form_id)
{
    $page_id = (int) $page_id;
    $form_id = (int) $form_id;
    $setting = pg_whatsapp_form_normalize(pg_whatsapp_form_options(), $page_id);

    if (!$setting['on'] || ($form_id <= 0)) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    if (!pg_whatsapp_ready()) {
        return false;
    }

    $values = pg_whatsapp_form_values(db_items("SELECT form_field_id, data FROM form_data WHERE form_id = '" . $form_id . "' ORDER BY id"));
    $phone = isset($values['field:' . $setting['phone_field']]) ? $values['field:' . $setting['phone_field']] : '';

    if ($phone === '') {
        return false;
    }

    $record = db_item("SELECT contact_id, reference_code FROM forms WHERE id = '" . $form_id . "'");
    $values += array(
        'store'          => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '',
        'reference_code' => is_array($record) ? (string) $record['reference_code'] : '',
    );

    list($name, $language) = array_pad(explode('|', $setting['template'], 2), 2, '');
    $template = pg_whatsapp_template_find($name, $language);
    $filled = pg_whatsapp_message_components($template, $setting['vars'], $values);

    if (!$filled['ok']) {
        if (function_exists('log_activity')) {
            log_activity('WhatsApp message of the form on page ' . $page_id . ' was not queued: ' . $filled['error']);
        }

        return false;
    }

    $queued = pg_whatsapp_enqueue($phone, array(
        'id'         => (int) $template['id'],
        'name'       => $template['name'],
        'language'   => $template['language'],
        'category'   => $template['category'],
        'components' => $filled['components'],
    ), array(
        'kind'       => 'form',
        'reference'  => 'form:' . $form_id,
        'contact_id' => is_array($record) ? (int) $record['contact_id'] : 0,
        'expires_at' => time() + 2 * 86400,
    ));

    return $queued['ok'];
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Key Codes list (view_key_codes.php) and its quick edit: every
 * value of the list the key code screen (edit_key_code.php) changes is a
 * field, checked and written by that screen's own form code
 * (includes/forms/key_code_form.php); the notes keep their lines. The
 * offer's message and Last Modified stay text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/key_code_form.php.
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
 * The quick edit fields, keyed by the list's column. The lengths are the
 * columns' 50 characters; the expiration date is typed in the site's date
 * order and read by the screen's check. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_key_code_edit_columns()
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));

    return array(
        'code' => array('type' => 'text', 'label' => lang('Key Code'), 'max' => 50, 'required' => true),
        'offer_code' => array('type' => 'text', 'label' => lang('Offer Code'), 'max' => 50, 'required' => true),
        'enabled' => array('type' => 'select', 'label' => lang('Enabled'), 'options' => array('1' => lang('Enabled'), '0' => lang('Disabled'))),
        'expiration_date' => array('type' => 'text', 'label' => lang('Expiration Date'), 'max' => 20),
        'notes' => array('type' => 'textarea', 'label' => lang('Notes')),
        'single_use' => array('type' => 'select', 'label' => lang('Single-Use'), 'options' => $yes_no),
        'report' => array('type' => 'select', 'label' => lang('Report'), 'options' => array('key_code' => lang('Key Code'), 'offer_code' => lang('Offer Code'))),
    );
}

/**
 * The value a field opens with, as the key code screen shows it: the
 * expiration date in the site's date order (blank when it never expires),
 * the switches as 1 or 0.
 *
 * @param array  $key_code a key_codes row
 * @param string $field
 * @return string
 */
function pg_key_code_quick_value($key_code, $field)
{
    $stored = $key_code[$field] ?? '';

    switch ($field) {
        case 'expiration_date':
            return (((string) $stored === '') || ((string) $stored === '0000-00-00')) ? '' : (string) prepare_form_data_for_output((string) $stored, 'date', false);

        case 'enabled':
        case 'single_use':
            return !empty($stored) ? '1' : '0';

        case 'report':
            return ((string) $stored === 'offer_code') ? 'offer_code' : 'key_code';
    }

    return (string) $stored;
}

/**
 * The fields of the key code screen's form for one quick edit: the stored
 * key code as the screen opens it, with the one change laid over it.
 * enabled_was is the stored flag, so the screen's save writes Enabled only
 * when that is the field changed (pg_key_code_form_values()). An unticked
 * switch posts nothing, so off is empty.
 *
 * @param array  $key_code a key_codes row
 * @param string $field    a key of pg_key_code_edit_columns()
 * @param mixed  $value    as pg_list_edit_parse() read it
 * @return array form field => value
 */
function pg_key_code_quick_form_values($key_code, $field, $value)
{
    $values = array();

    foreach (array_keys(pg_key_code_edit_columns()) as $key) {
        $values[$key] = pg_key_code_quick_value($key_code, $key);
    }

    $values['enabled_was'] = $values['enabled'];

    if (array_key_exists($field, $values)) {
        $values[$field] = (string) $value;
    }

    foreach (array('enabled', 'single_use') as $switch) {
        $values[$switch] = ($values[$switch] === '1') ? '1' : '';
    }

    return $values;
}

/**
 * The list's query, without its ORDER BY. Two offers can share a code; the
 * list joins them the same way and colours the row by the first.
 *
 * @param int $id one key code only, 0 for all
 * @return string
 */
function pg_key_code_list_sql($id = 0)
{
    return "SELECT
            key_codes.id,
            key_codes.code,
            key_codes.offer_code,
            key_codes.enabled,
            key_codes.expiration_date,
            key_codes.notes,
            key_codes.single_use,
            key_codes.report,
            last_modified_user.user_username AS last_modified_username,
            key_codes.timestamp AS last_modified_timestamp,
            offers.id AS offer_id,
            offers.description AS offer_description,
            offers.status AS offer_status,
            offers.start_date AS offer_start_date,
            offers.end_date AS offer_end_date
        FROM key_codes
        LEFT JOIN offers ON key_codes.offer_code = offers.code
        LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id"
        . (((int) $id > 0) ? "
        WHERE key_codes.id = '" . (int) $id . "'
        LIMIT 1" : '');
}

/**
 * A key code's cells on the list, with their quick edit attributes: the
 * code, green when it works at checkout today (pg_key_code_list_cells()),
 * is the link to the key code; the offer code links to its offer.
 *
 * @param array  $key_code   a row of pg_key_code_list_sql()
 * @param string $today      Y-m-d
 * @param array  $quick_edit pg_key_code_edit_columns(), empty without quick edit
 * @return array key => pg_list_cell()
 */
function pg_key_code_list_row_cells($key_code, $today, array $quick_edit)
{
    $id = (int) $key_code['id'];
    $cells = pg_key_code_list_cells($key_code, $today);
    $expires = (((string) $key_code['expiration_date'] !== '') && ((string) $key_code['expiration_date'] !== '0000-00-00')) ? strtotime((string) $key_code['expiration_date']) : 0;
    $yes = '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>';

    $attributes = function ($field, array $more = array()) use ($key_code, $quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_key_code_quick_value($key_code, $field));
    };

    return array(
        'code' => pg_list_cell(pg_commerce_list_open_link('edit_key_code.php?id=' . $id, $cells['code']['html']), $cells['code']['class'], $attributes('code', array('data-pg-sort-value' => (string) $key_code['code']))),
        'offer_code' => pg_list_cell(!empty($key_code['offer_id'])
            ? '<a class="pg-link-secondary" href="edit_offer.php?id=' . (int) $key_code['offer_id'] . '" title="' . h(lang('Edit Offer')) . '">' . h((string) $key_code['offer_code']) . '</a>'
            : h((string) $key_code['offer_code']), 'align-middle', $attributes('offer_code')),
        'offer_message' => pg_list_cell(h((string) ($key_code['offer_description'] ?? ''))),
        'enabled' => pg_list_cell($cells['enabled']['html'], $cells['enabled']['class'], $attributes('enabled', array('data-pg-sort-value' => !empty($key_code['enabled']) ? '1' : '0'))),
        'expiration_date' => pg_list_cell($expires ? get_absolute_time(array('timestamp' => $expires, 'type' => 'date')) : '', 'align-middle', $attributes('expiration_date', array('data-pg-sort-value' => (string) (int) $expires))),
        'notes' => pg_list_cell(nl2br(h((string) $key_code['notes'])), 'align-middle', $attributes('notes')),
        'single_use' => pg_list_cell(!empty($key_code['single_use']) ? $yes : '', 'align-middle text-center', $attributes('single_use', array('data-pg-sort-value' => !empty($key_code['single_use']) ? '1' : '0'))),
        'report' => pg_list_cell('<span class="badge fw-light theme-secondary">' . h(((string) $key_code['report'] === 'offer_code') ? lang('Offer Code') : lang('Key Code')) . '</span>', 'align-middle', $attributes('report')),
        'last_modified' => pg_list_cell($cells['last_modified']['html'], $cells['last_modified']['class'], array('data-pg-sort-value' => (string) (int) $key_code['last_modified_timestamp'])),
    );
}

/**
 * The quick edit answer from a key code read again: the edited cell and
 * every other cell of the row (the code's colour follows Enabled, the
 * expiration date and the offer code; Last Modified follows every change),
 * each with the value its field opens with.
 *
 * @param array  $cells pg_key_code_list_row_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_key_code_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the key code screen's own
 * check and save: the code required, at most 50 characters, used by no
 * other key code and no offer's own code; the offer code that of an offer;
 * the expiration date a real date or blank (pg_key_code_form_validate()).
 * The same values again write nothing.
 *
 * @param int    $id
 * @param string $field      a key of pg_key_code_edit_columns()
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_key_code_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_key_code_quick_update($id, $field, $value, $user_id, array $quick_edit)
{
    $id = (int) $id;

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => (string) $message);
    };

    $key_code = db_item(pg_key_code_list_sql($id));

    if (!$key_code) {
        return $refuse(lang('Record not found.'), 'not_found');
    }

    $form = new liveform('key_code_quick_edit');
    $form->remove_form();

    foreach (pg_key_code_quick_form_values($key_code, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    $form->validate_required_field('code', lang('Key Code is required.'));
    pg_key_code_form_validate($form, $id);

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? html_entity_decode(strip_tags($messages[0]), ENT_QUOTES, 'UTF-8') : lang('The value could not be saved.'));
    }

    $values = pg_key_code_form_values($form, (string) $form->get_field_value('code'), $key_code['enabled']);
    $form->remove_form();

    $stored = array(
        (string) $key_code['code'],
        (string) $key_code['offer_code'],
        (string) $key_code['enabled'],
        (string) $key_code['expiration_date'],
        (string) $key_code['notes'],
        (string) $key_code['single_use'],
        (string) $key_code['report'],
    );

    // The date column reads '' back as 0000-00-00: both mean never.
    $compare = $values;
    $compare[3] = ($compare[3] === '') ? '0000-00-00' : $compare[3];

    if ($compare !== $stored) {
        $saved = db_q(
            "UPDATE key_codes
            SET
                code = ?,
                offer_code = ?,
                enabled = ?,
                expiration_date = ?,
                notes = ?,
                single_use = ?,
                report = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            array_merge($values, array((string) (int) $user_id, (string) $id)));

        if (!$saved) {
            return $refuse(lang('The value could not be saved.'));
        }

        log_activity(
            lang(array(
                'string' => 'Key code ({var:1}) was changed on the key code list: {var:2} from "{var:3}" to "{var:4}".',
                'vars'   => array((string) $key_code['code'], (string) ($quick_edit[$field]['label'] ?? $field), pg_key_code_quick_value($key_code, $field), (string) $value),
            )),
            $_SESSION['sessionusername'] ?? '');

        $key_code = db_item(pg_key_code_list_sql($id));

        if (!$key_code) {
            return $refuse(lang('Record not found.'), 'not_found');
        }
    }

    return pg_key_code_quick_saved(pg_key_code_list_row_cells($key_code, date('Y-m-d'), $quick_edit), $field);
}

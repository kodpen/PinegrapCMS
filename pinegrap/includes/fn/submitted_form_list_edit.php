<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The submitted forms list's quick edit (view_submitted_forms.php): the
 * values the submitted form screen (edit_submitted_form.php) changes for
 * the record itself, the status and the form editor, are fields, checked
 * and written as that screen does. The field values differ per custom form
 * and stay on the screen.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The quick edit fields: the status (the screen's Convert Complete and
 * Convert Incomplete buttons) and the form editor's username (empty for
 * none, at most 100 characters as on the screen). Pure but for lang().
 *
 * @return array field => definition
 */
function pg_submitted_form_quick_edit_columns()
{
    return array(
        'status' => array('type' => 'select', 'label' => lang('Status'), 'options' => array(
            '1' => lang('Complete'),
            '0' => lang('Incomplete'),
        )),
        'form_editor' => array('type' => 'text', 'label' => lang('Form Editor'), 'max' => 100),
    );
}

/**
 * The value a form's field opens with: the stored one. Pure.
 *
 * @param string $field a pg_submitted_form_quick_edit_columns() field
 * @param array  $record a record of the list's read
 * @return string
 */
function pg_submitted_form_quick_value($field, array $record)
{
    if ($field === 'status') {
        return !empty($record['complete']) ? '1' : '0';
    }

    if ($field === 'form_editor') {
        return (string) ($record['form_editor_username'] ?? '');
    }

    return '';
}

/**
 * A list row with the quick edit attributes on its field cells. Pure.
 *
 * @param array $row        pg_submitted_form_list_row()
 * @param array $record     the record it was drawn from
 * @param array $quick_edit field => definition, empty for none
 * @return array the row
 */
function pg_submitted_form_quick_cells(array $row, array $record, array $quick_edit)
{
    foreach (array_keys($quick_edit) as $field) {
        if (isset($row['cells'][$field])) {
            $row['cells'][$field]['attributes'] = pg_list_edit_cell_attributes($quick_edit, $field, $row['id'], pg_submitted_form_quick_value($field, $record))
                + (array) ($row['cells'][$field]['attributes'] ?? array());
        }
    }

    return $row;
}

/**
 * Why a form cannot be completed: the first required field without a
 * stored value (a file for an upload field), as the screen's Complete
 * button checks; '' when every one is filled. Pure but for lang().
 *
 * @param array $fields form_fields rows (id, label, type, required)
 * @param array $stored form_field_id => stored value (file id for an upload)
 * @return string the message, '' when nothing is missing
 */
function pg_submitted_form_quick_missing(array $fields, array $stored)
{
    foreach ($fields as $field) {
        if (empty($field['required']) || (($field['type'] ?? '') === 'information')) {
            continue;
        }

        $value = $stored[(string) $field['id']] ?? '';

        if (trim((string) $value) !== '' && !((($field['type'] ?? '') === 'file upload') && ((int) $value <= 0))) {
            continue;
        }

        return ((string) ($field['label'] ?? '') !== '')
            ? lang(array('string' => '{var:1} is required.', 'vars' => (string) $field['label']))
            : lang('A required field is empty.');
    }

    return '';
}

/**
 * The stored values of a form's fields, for pg_submitted_form_quick_missing():
 * the values of a multi-value field joined, the file id for an upload.
 *
 * @param int $form_id
 * @param int $page_id the custom form
 * @return array array('fields' => form_fields rows, 'stored' => id => value)
 */
function pg_submitted_form_quick_stored_fields($form_id, $page_id)
{
    $fields = (array) db_items(
        "SELECT id, label, type, required
         FROM form_fields
         WHERE (page_id = '" . (int) $page_id . "') AND (type != 'information')
         ORDER BY sort_order");

    $stored = array();
    $result = db_q("SELECT form_field_id, data, file_id FROM form_data WHERE form_id = ?", array((string) (int) $form_id));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $key = (string) $row['form_field_id'];
        $value = ((int) $row['file_id'] > 0) ? (string) (int) $row['file_id'] : trim((string) $row['data']);

        if (trim((string) ($stored[$key] ?? '')) === '') {
            $stored[$key] = $value;
        }
    }

    return array('fields' => $fields, 'stored' => $stored);
}

/**
 * Writes one value changed on the list as the submitted form screen does:
 * the user's rights on the form's folder (a manager or above, or a user who
 * manages forms with edit access), the required fields before a form is
 * completed, a known username for the form editor; then the change, the
 * modifier and time, the form's revision, the custom form's e-mails and
 * auto campaigns when it was just completed, and the log.
 *
 * @param array  $user  validate_user()
 * @param int    $id    forms.id
 * @param string $field a pg_submitted_form_quick_edit_columns() field
 * @param string $value pg_list_edit_request()'s value
 * @return array the answer for pg_list_edit_reply()
 */
function pg_submitted_form_quick_update(array $user, $id, $field, $value)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => (string) $message);
    };

    $form = db_item(
        "SELECT
            forms.id,
            forms.page_id,
            forms.contact_id,
            forms.complete,
            forms.reference_code,
            forms.form_editor_user_id,
            page.page_folder,
            custom_form_pages.form_name,
            form_editor_user.user_username AS form_editor_username
        FROM forms
        LEFT JOIN page ON forms.page_id = page.page_id
        LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
        LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id
        WHERE forms.id = '" . (int) $id . "'");

    if (!$form) {
        return $refuse(lang('Record not found.'), 'not_found');
    }

    if (((int) $user['role'] > 2) && ((($user['manage_forms'] ?? false) != true) || (check_edit_access($form['page_folder']) == false))) {
        return $refuse(lang('Access denied.'), 'denied');
    }

    $id = (int) $form['id'];
    $value = (string) $value;
    $was_complete = !empty($form['complete']);

    if (pg_submitted_form_quick_value($field, $form) !== $value) {
        if ($field === 'status') {
            $set = "complete = '" . (($value === '1') ? 1 : 0) . "'";

            if ($value === '1') {
                $data = pg_submitted_form_quick_stored_fields($id, $form['page_id']);
                $missing = pg_submitted_form_quick_missing($data['fields'], $data['stored']);

                if ($missing !== '') {
                    return $refuse($missing);
                }
            }
        } else {
            $editor_id = 0;

            if ($value !== '') {
                $result = db_q("SELECT user_id, user_username FROM user WHERE user_username = ?", array($value));
                $editor = $result ? mysqli_fetch_assoc($result) : null;
                $editor_id = $editor ? (int) $editor['user_id'] : 0;

                if ($editor_id <= 0) {
                    return $refuse(lang('Please enter a valid username for the form editor.'));
                }

                // The username as stored, whatever case it was typed in.
                $value = (string) $editor['user_username'];
            }

            $set = "form_editor_user_id = '" . $editor_id . "'";
        }

        $revision_before = pg_record_revision_before($id);

        // $set is built above from the field's own column, never the request.
        $updated = db_q(
            "UPDATE forms
             SET " . $set . ", last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP()
             WHERE id = ?",
            array((string) (int) $user['id'], (string) $id));

        if ($updated === false) {
            return $refuse(lang('The change could not be saved.'), 'failed');
        }

        pg_record_revision_after($id, $revision_before, 'edit', (int) $user['id']);

        // A form just completed sends what the custom form sends then, as
        // the screen's Complete button does.
        if (($field === 'status') && !$was_complete && ($value === '1')) {
            pg_send_custom_form_notifications($form['page_id'], $id, $form['contact_id'], true);
        }

        log_activity(lang(array('string' => 'submitted form (form name: {var:1}, reference code: {var:2}) was modified', 'vars' => array($form['form_name'], $form['reference_code']))), $_SESSION['sessionusername'] ?? '');
    }

    // The row as the list draws it: the edited cell and Last Modified.
    $source = pg_submitted_form_list_source($user, array());
    $row = $source ? pg_list_table_record_row($source, $id) : null;
    $now = ($field === 'status') ? (($value === '1') ? '1' : '0') : $value;
    $answer = array('ok' => true, 'value' => $now)
        + pg_list_table_edit_answer($row, $field, array('last_modified'));

    // Out of the list's filter now (the Incomplete view, say): the cell
    // still says what was stored.
    if (!isset($answer['html'])) {
        $options = pg_submitted_form_quick_edit_columns()['status']['options'];
        $answer['html'] = ($field === 'status') ? h($options[$now]) : h($now);
    }

    return $answer;
}
